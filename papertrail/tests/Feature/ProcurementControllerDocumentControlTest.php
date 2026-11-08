<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementControllerDocumentControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_procurement_controller_assigns_rfq_and_po_control_details(): void
    {
        $controller = User::where('user_id', User::PROCUREMENT_CONTROLLER_USER_ID)->firstOrFail();
        $originator = User::where('user_id', 'MCR-001')->firstOrFail();

        $rfq = Rfq::create([
            'status' => Rfq::STATUS_PENDING_CONTROL,
            'prepared_by_user_id' => $originator->id,
            'submitted_by_user_id' => $originator->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($controller)
            ->patch(route('pr-numbering.rfqs.assign', $rfq), [
                'rfq_number' => 'RFQ-2026-001',
                'rfq_date' => '2026-11-08',
            ])
            ->assertRedirect(route('pr-numbering.pending.index'));

        $this->assertDatabaseHas('rfqs', [
            'id' => $rfq->id,
            'rfq_number' => 'RFQ-2026-001',
            'rfq_date' => '2026-11-08',
            'status' => Rfq::STATUS_SUBMITTED,
            'control_assigned_by_user_id' => $controller->id,
        ]);

        $purchaseOrder = PurchaseOrder::create([
            'fiscal_year' => 2026,
            'status' => PurchaseOrder::STATUS_PENDING_CONTROL,
            'total_amount' => 1500,
            'prepared_by_user_id' => $originator->id,
            'submitted_by_user_id' => $originator->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($controller)
            ->patch(route('pr-numbering.purchase-orders.assign', $purchaseOrder), [
                'po_number' => 'PO-2026-001',
                'po_date' => '2026-11-08',
            ])
            ->assertRedirect(route('pr-numbering.pending.index'));

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'po_number' => 'PO-2026-001',
            'po_date' => '2026-11-08',
            'status' => PurchaseOrder::STATUS_SUBMITTED,
            'control_assigned_by_user_id' => $controller->id,
        ]);

        $this->assertDatabaseHas('procurement_documents', [
            'id' => $purchaseOrder->fresh()->procurement_document_id,
            'tracking_number' => 'PO-2026-001',
            'assigned_to_user_id' => $originator->id,
        ]);
    }

    public function test_head_office_can_submit_an_incomplete_po_for_control_assignment(): void
    {
        $originator = User::where('user_id', 'MCR-001')->firstOrFail();

        $purchaseOrder = PurchaseOrder::create([
            'fiscal_year' => 2026,
            'status' => PurchaseOrder::STATUS_DRAFT,
            'prepared_by_user_id' => $originator->id,
        ]);

        $this->actingAs($originator)
            ->patch(route('head-office.purchase-orders.update', $purchaseOrder), [
                'save_action' => 'submit',
            ])
            ->assertRedirect(route('head-office.purchase-orders.show', $purchaseOrder));

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => PurchaseOrder::STATUS_PENDING_CONTROL,
            'submitted_by_user_id' => $originator->id,
        ]);
    }
}
