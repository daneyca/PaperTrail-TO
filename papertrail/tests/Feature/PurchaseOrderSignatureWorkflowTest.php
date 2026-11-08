<?php

namespace Tests\Feature;

use App\Models\ElectronicSignature;
use App\Models\PurchaseOrder;
use App\Models\SignatureRequest;
use App\Models\SupplierSignatureRequest;
use App\Models\User;
use App\Services\PurchaseOrderSignatureWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseOrderSignatureWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_purchase_order_requires_mayor_accountant_and_supplier_signatures(): void
    {
        $requester = User::query()->where('user_id', 'BACSEC-003')->firstOrFail();
        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-2026-TEST-001',
            'po_date' => '2026-11-08',
            'fiscal_year' => 2026,
            'supplier_name' => 'Test Supplier',
            'total_amount' => 3600,
            'status' => PurchaseOrder::STATUS_SUBMITTED,
            'prepared_by_user_id' => $requester->id,
        ]);

        $workflow = app(PurchaseOrderSignatureWorkflowService::class);
        $purchaseOrder = $workflow->submit($purchaseOrder, $requester);

        $this->assertSame(PurchaseOrder::STATUS_FORWARDED_TO_SUPPLIER, $purchaseOrder->status);
        $this->assertEqualsCanonicalizing(
            ['MO-001', 'ACC-001'],
            SignatureRequest::query()
                ->where('document_type', 'purchase_order')
                ->where('document_id', $purchaseOrder->id)
                ->with('requestedTo')
                ->get()
                ->pluck('requestedTo.user_id')
                ->all()
        );

        $supplierRequest = $workflow->supplierRequestFor($purchaseOrder);
        $this->assertNotNull($supplierRequest);
        $this->assertSame(SupplierSignatureRequest::STATUS_PENDING, $supplierRequest->status);

        $internalRequests = SignatureRequest::query()
            ->where('document_type', 'purchase_order')
            ->where('document_id', $purchaseOrder->id)
            ->get();

        foreach ($internalRequests as $request) {
            $request->forceFill([
                'status' => SignatureRequest::STATUS_SIGNED,
                'signed_at' => now(),
            ])->save();

            $signature = ElectronicSignature::create([
                'signature_uuid' => (string) Str::uuid(),
                'signature_request_id' => $request->id,
                'document_type' => 'purchase_order',
                'document_id' => $purchaseOrder->id,
                'signer_user_id' => $request->requested_to_user_id,
                'signer_name' => $request->requestedTo?->name,
                'signer_position' => data_get($request->metadata, 'designation'),
                'signature_status' => ElectronicSignature::STATUS_SIGNED,
                'signatory_slot' => $request->signatory_slot,
                'signatory_label' => $request->signatory_label,
                'signed_at' => now(),
            ]);

            $workflow->handleInternalSigned($purchaseOrder->refresh(), $request, $signature);
        }

        $this->assertSame(
            PurchaseOrder::STATUS_FORWARDED_TO_SUPPLIER,
            $purchaseOrder->refresh()->status
        );

        $supplierRequest->forceFill([
            'status' => SupplierSignatureRequest::STATUS_SUBMITTED,
            'signatory_name' => 'Supplier Representative',
            'submitted_at' => now(),
        ])->save();
        $workflow->handleSupplierSigned($supplierRequest->refresh());

        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $purchaseOrder->refresh()->status);
        $this->assertSame('Supplier Representative', $purchaseOrder->supplier_representative_name);
        $this->assertNotNull($purchaseOrder->supplier_conforme_date);
    }
}
