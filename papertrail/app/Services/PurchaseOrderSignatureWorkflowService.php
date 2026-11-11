<?php

namespace App\Services;

use App\Models\ElectronicSignature;
use App\Models\PurchaseOrder;
use App\Models\SignatureRequest;
use App\Models\SupplierSignatureRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseOrderSignatureWorkflowService
{
    public function __construct(private readonly SignatureRequestService $signatureRequests)
    {
    }

    public function submit(PurchaseOrder $purchaseOrder, User $requester): PurchaseOrder
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_SUBMITTED
            || blank($purchaseOrder->po_number)
            || blank($purchaseOrder->po_date)) {
            throw new RuntimeException('The Procurement Controller must assign the PO number and date first.');
        }

        return DB::transaction(function () use ($purchaseOrder, $requester): PurchaseOrder {
            $suppliers = app(PurchaseOrderSupplierService::class);
            $quote = $suppliers->quotation($purchaseOrder->sourceAbstract, $purchaseOrder->supplier_name);
            if ($purchaseOrder->source_rfq_supplier_quotation_id && $quote?->id !== $purchaseOrder->source_rfq_supplier_quotation_id) {
                throw new RuntimeException('The linked supplier quotation is no longer verified. Review the PO supplier before requesting signatures.');
            }
            $suppliers->fillMissing($purchaseOrder);
            $purchaseOrder->save();
            $requests = $this->signatureRequests->createRequestsForDocument(
                $purchaseOrder,
                'purchase_order',
                [
                    [
                        'signatory_slot' => 'authorized_official',
                        'signatory_label' => 'Authorized Official',
                        'account_code' => 'MO-001',
                        'designation' => 'Municipal Mayor',
                        'is_required' => true,
                        'signing_order' => 1,
                    ],
                    [
                        'signatory_slot' => 'municipal_accountant',
                        'signatory_label' => 'Municipal Accountant',
                        'account_code' => 'ACC-001',
                        'designation' => 'Municipal Accountant',
                        'is_required' => true,
                        'signing_order' => 1,
                    ],
                ],
                $requester
            );

            if ($requests->count() !== 2 || $requests->contains(fn (SignatureRequest $request) => ! $request->requested_to_user_id)) {
                throw new RuntimeException('The Mayor and Municipal Accounting accounts must be active before submitting the PO.');
            }

            SupplierSignatureRequest::query()
                ->where('document_type', 'purchase_order')
                ->where('tracking_number', $purchaseOrder->displayNumber())
                ->whereIn('status', SupplierSignatureRequest::ACTIVE_STATUSES)
                ->update(['status' => SupplierSignatureRequest::STATUS_CANCELLED, 'cancelled_at' => now()]);

            SupplierSignatureRequest::create([
                'document_type' => 'purchase_order',
                'requested_by_user_id' => $requester->getKey(),
                'supplier_name' => $purchaseOrder->supplier_name ?: 'Supplier',
                'supplier_email' => $purchaseOrder->supplier_email,
                'supplier_contact' => $purchaseOrder->supplier_contact,
                'signatory_name' => $purchaseOrder->supplier_representative_name,
                'signatory_position' => $purchaseOrder->supplier_representative_designation,
                'document_title' => 'Purchase Order '.$purchaseOrder->displayNumber(),
                'tracking_number' => $purchaseOrder->displayNumber(),
                'status' => SupplierSignatureRequest::STATUS_PENDING,
                'expires_at' => now()->addDays(30),
                'instructions' => 'Review this Purchase Order, then sign below to confirm your acceptance. Your RFQ signature is separate from this PO Conforme.',
                'metadata' => [
                    'purchase_order_id' => $purchaseOrder->getKey(),
                    'signatory_slot' => 'supplier_conforme',
                    'source_rfq_supplier_quotation_id' => $purchaseOrder->source_rfq_supplier_quotation_id,
                    'supplier_tin' => $purchaseOrder->supplier_tin,
                    'purchase_order_snapshot' => $suppliers->snapshot($purchaseOrder),
                ],
            ]);

            $purchaseOrder->forceFill([
                'status' => PurchaseOrder::STATUS_FORWARDED_TO_SUPPLIER,
            ])->save();

            return $purchaseOrder->refresh();
        });
    }

    public function signatureSlots(PurchaseOrder $purchaseOrder): Collection
    {
        return $this->signatureRequests->signedSignaturesForDocument($purchaseOrder, 'purchase_order');
    }

    public function supplierRequestFor(PurchaseOrder $purchaseOrder): ?SupplierSignatureRequest
    {
        return SupplierSignatureRequest::query()
            ->where('document_type', 'purchase_order')
            ->where('tracking_number', $purchaseOrder->displayNumber())
            ->latest('id')
            ->first();
    }

    public function handleInternalSigned(
        PurchaseOrder $purchaseOrder,
        SignatureRequest $request,
        ElectronicSignature $signature
    ): void {
        $attributes = match ($request->signatory_slot) {
            'authorized_official' => [
                'authorized_official_name' => $signature->signer_name,
                'authorized_official_designation' => $signature->signer_position ?: 'Municipal Mayor',
            ],
            'municipal_accountant' => [
                'accountant_name' => $signature->signer_name,
                'accountant_designation' => $signature->signer_position ?: 'Municipal Accountant',
            ],
            default => [],
        };

        if ($attributes !== []) {
            $purchaseOrder->forceFill($attributes)->save();
        }

        $this->completeIfReady($purchaseOrder);
    }

    public function handleSupplierSigned(SupplierSignatureRequest $request): void
    {
        $purchaseOrder = PurchaseOrder::query()->find(data_get($request->metadata, 'purchase_order_id'));
        if (! $purchaseOrder) {
            return;
        }

        $purchaseOrder->forceFill([
            'supplier_representative_name' => $request->signatory_name ?: $request->supplier_name,
            'supplier_representative_designation' => $request->signatory_position ?: data_get($request->metadata, 'supplier_designation', 'Authorized Supplier Representative'),
            'supplier_conforme_date' => now()->toDateString(),
        ])->save();

        $this->completeIfReady($purchaseOrder);
    }

    public function handleDeclined(PurchaseOrder $purchaseOrder): void
    {
        DB::transaction(function () use ($purchaseOrder): void {
            $purchaseOrder->forceFill(['status' => PurchaseOrder::STATUS_RETURNED])->save();

            SignatureRequest::query()
                ->where('document_type', 'purchase_order')
                ->where('document_id', $purchaseOrder->getKey())
                ->whereIn('status', [SignatureRequest::STATUS_PENDING, SignatureRequest::STATUS_NOTIFIED, SignatureRequest::STATUS_VIEWED])
                ->update(['status' => SignatureRequest::STATUS_CANCELLED]);

            SupplierSignatureRequest::query()
                ->where('document_type', 'purchase_order')
                ->where('tracking_number', $purchaseOrder->displayNumber())
                ->whereIn('status', SupplierSignatureRequest::ACTIVE_STATUSES)
                ->update(['status' => SupplierSignatureRequest::STATUS_CANCELLED, 'cancelled_at' => now()]);
        });
    }

    private function completeIfReady(PurchaseOrder $purchaseOrder): void
    {
        $internalComplete = $this->signatureRequests
            ->documentHasRequiredSignatures($purchaseOrder, 'purchase_order');

        // The supplier Conforme is optional and must not block the internal PO approval.
        if ($internalComplete) {
            $purchaseOrder->forceFill(['status' => PurchaseOrder::STATUS_APPROVED])->save();
        }
    }
}
