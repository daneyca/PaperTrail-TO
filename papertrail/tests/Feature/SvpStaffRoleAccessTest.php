<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\User;
use App\Services\ElectronicSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SvpStaffRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_procurement_staff_can_open_all_operational_svp_stages(): void
    {
        [$staff] = $this->officeAccounts();
        $this->actingAs($staff);

        foreach ([
            'head-office.resolutions.index', 'head-office.resolutions.menu',
            'head-office.rfqs.create', 'head-office.rfqs.index',
            'head-office.abstracts.create', 'head-office.abstracts.index',
            'head-office.purchase-orders.create', 'head-office.purchase-orders.index',
            'head-office.inspection.index', 'head-office.inspection.records',
        ] as $routeName) {
            $this->get(route($routeName))->assertOk();
        }
    }

    public function test_procurement_staff_can_save_an_rfq_draft_and_use_official_templates(): void
    {
        Storage::fake('local');
        [$staff] = $this->officeAccounts();
        $this->actingAs($staff)->post(route('head-office.rfqs.store'), ['save_action' => 'draft'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('rfqs', ['prepared_by_user_id' => $staff->id, 'status' => Rfq::STATUS_DRAFT]);

        $this->get(route('document-templates.download', ['documentType' => 'inspection-acceptance']))->assertOk();
        $this->get(route('document-template-imports.upload-form', ['documentType' => 'inspection-acceptance']))->assertOk();
    }

    public function test_staff_records_inspection_but_only_the_head_accepts_and_completes_it(): void
    {
        [$staff, $head] = $this->officeAccounts();
        $pr = $this->purchaseRequest($staff);
        $po = PurchaseOrder::create([
            'fiscal_year' => 2026, 'status' => PurchaseOrder::STATUS_SUBMITTED,
            'prepared_by_user_id' => $staff->id, 'source_pr_document_id' => $pr->id,
        ]);
        $this->actingAs($staff)->post(route('head-office.inspection.store', $po), [
            'inspection_date' => '2026-11-09', 'quantity_condition' => 'Complete', 'quality_condition' => 'Conforming',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->patch(route('head-office.inspection.accept', $po), ['acceptance_date' => '2026-11-09'])
            ->assertRedirect(route('head-office.dashboard'))->assertSessionHas('error');
        $this->assertSame(PurchaseOrder::STATUS_SUBMITTED, $po->refresh()->status);

        $this->actingAs($head)->patch(route('head-office.inspection.accept', $po), ['acceptance_date' => '2026-11-09'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $po->refresh()->status);
        $this->actingAs($staff)->patch(route('head-office.inspection.complete', $po))
            ->assertRedirect(route('head-office.dashboard'))->assertSessionHas('error');
        $this->actingAs($head)->patch(route('head-office.inspection.complete', $po))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(PurchaseOrder::STATUS_COMPLETED, $po->refresh()->status);
    }

    public function test_staff_cannot_sign_or_approve_documents(): void
    {
        [$staff] = $this->officeAccounts();
        $result = app(ElectronicSignatureService::class)->canSignWithReason($staff, $this->purchaseRequest($staff), 'purchase_request', 'confirmed');
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Signatures and approvals', $result['message']);
    }

    public function test_head_with_procurement_staff_reviews_documents_without_preparing_them(): void
    {
        [$staff, $head] = $this->officeAccounts();
        $rfq = Rfq::create([
            'prepared_by_user_id' => $staff->id, 'source_pr_document_id' => $this->purchaseRequest($staff)->id,
            'status' => Rfq::STATUS_DRAFT,
        ]);
        $this->actingAs($head);
        foreach (['head-office.rfqs.create', 'head-office.abstracts.create', 'head-office.purchase-orders.create', 'head-office.inspection.index'] as $routeName) {
            $this->get(route($routeName))->assertRedirect(route('head-office.dashboard'))->assertSessionHas('error');
        }
        $this->post(route('head-office.rfqs.store'), ['save_action' => 'draft'])
            ->assertRedirect(route('head-office.dashboard'));
        $this->get(route('head-office.rfqs.show', $rfq))->assertOk()
            ->assertDontSee(route('head-office.rfqs.edit', $rfq), false);
        $this->get(route('signature-requests.index'))->assertOk();
    }

    public function test_offices_without_active_staff_keep_head_managed_processing(): void
    {
        [$staff, $head] = $this->officeAccounts();
        $staff->update(['status' => User::STATUS_INACTIVE]);
        $this->assertTrue($head->canManageSvpDocuments());
        $this->actingAs($head)->get(route('head-office.rfqs.create'))->assertOk();
    }

    private function officeAccounts(): array
    {
        $office = Office::create([
            'code' => 'STAFF-SVP', 'name' => 'Test SVP Office', 'type' => Office::TYPE_END_USER,
            'status' => Office::STATUS_ACTIVE, 'is_requesting_office' => true,
        ]);
        $staff = User::factory()->create([
            'user_id' => 'SVP-STAFF', 'role' => User::ROLE_OFFICE_PREPARER, 'office_id' => $office->id,
            'status' => User::STATUS_ACTIVE, 'must_change_password' => false,
        ]);
        $head = User::factory()->create([
            'user_id' => 'SVP-HEAD', 'role' => User::ROLE_HEAD_OFFICE, 'office_id' => $office->id,
            'status' => User::STATUS_ACTIVE, 'must_change_password' => false,
        ]);

        return [$staff, $head];
    }

    private function purchaseRequest(User $staff): ProcurementDocument
    {
        return ProcurementDocument::create([
            'tracking_number' => 'PR-STAFF-SVP', 'document_type' => 'PR', 'title' => 'Office supplies',
            'fiscal_year' => 2026, 'status' => ProcurementDocument::STATUS_READY_FOR_RFQ,
            'submitting_office_id' => $staff->office_id, 'prepared_by_user_id' => $staff->id,
            'submitted_by_user_id' => $staff->id, 'total_amount' => 750,
        ]);
    }
}
