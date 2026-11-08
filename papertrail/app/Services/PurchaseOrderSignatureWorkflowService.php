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

            SupplierSignatureRequest::query()->updateOrCreate(
                [
                    'document_type' => 'purchase_order',
                    'tracking_number' => $purchaseOrder->displayNumber(),
                ],
                [
                    'requested_by_user_id' => $requester->getKey(),
                    'supplier_name' => $purchaseOrder->supplier_name ?: 'Supplier',
                    'supplier_contact' => $purchaseOrder->supplier_contact,
                    'document_title' => 'Purchase Order '.$purchaseOrder->displayNumber(),
                    'tracking_number' => $purchaseOrder->displayNumber(),
                    'status' => SupplierSignatureRequest::STATUS_PENDING,
                    'metadata' => [
                        'purchase_order_id' => $purchaseOrder->getKey(),
                        'signatory_slot' => 'supplier_conforme',
                    ],
                ]
            );

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
            'supplier_representative_designation' => data_get($request->metadata, 'supplier_designation', 'Authorized Supplier Representative'),
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
                ->where('status', SupplierSignatureRequest::STATUS_PENDING)
                ->update(['status' => SupplierSignatureRequest::STATUS_CANCELLED]);
        });
    }

    private function completeIfReady(PurchaseOrder $purchaseOrder): void
    {
        $internalComplete = $this->signatureRequests
            ->documentHasRequiredSignatures($purchaseOrder, 'purchase_order');
        $supplierComplete = SupplierSignatureRequest::query()
            ->where('document_type', 'purchase_order')
            ->where('tracking_number', $purchaseOrder->displayNumber())
            ->whereIn('status', [SupplierSignatureRequest::STATUS_SUBMITTED, SupplierSignatureRequest::STATUS_ACCEPTED])
            ->exists();

        if ($internalComplete && $supplierComplete) {
            $purchaseOrder->forceFill(['status' => PurchaseOrder::STATUS_APPROVED])->save();
        }
    }
}
