<?php

namespace Tests\Feature;

use App\Http\Controllers\HeadOffice\InspectionAcceptanceController;
use App\Models\DocumentTemplate;
use App\Models\InspectionAcceptanceRecord;
use App\Models\Office;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Templates\OfficialEditableTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class SvpInspectionAcceptanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_iar_template_is_available_without_any_purchase_orders(): void
    {
        [$user, $po] = $this->order();
        $po->delete();

        $this->actingAs($user)->get(route('head-office.inspection.index'))
            ->assertOk()->assertSee('Proceed to IAR Form')->assertSee('Blank Inspection / Acceptance Template')
            ->assertSee('data-iar-blank-template', false)->assertSee('INSPECTION AND ACCEPTANCE REPORT')
            ->assertSee('Print Template');

        $this->get(route('head-office.inspection.template-print'))
            ->assertOk()->assertSee('INSPECTION AND ACCEPTANCE REPORT')->assertSee('Municipality of Tomas Oppus')
            ->assertSee('Print Report');
        $this->assertDatabaseCount('inspection_acceptance_records', 0);
        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_svp_inspection_entry_opens_the_creation_flow_and_records_remain_accessible(): void
    {
        [$user] = $this->order();
        $this->actingAs($user)->get(route('head-office.inspection.index'))
            ->assertOk()->assertSee('Create Inspection / Acceptance')->assertSee('Recent IAR Drafts')
            ->assertSee('Select Inspection / Acceptance Source')->assertSee('Proceed to IAR Form')
            ->assertSee('PO-IAR-001')->assertDontSee('Inspection Drafts');
        $this->get(route('head-office.inspection.records'))->assertOk()->assertSee('Inspection Drafts');
    }

    public function test_selected_purchase_order_opens_the_editable_official_template_and_saves_a_draft(): void
    {
        [$user, $po] = $this->order();
        $this->actingAs($user)->get(route('head-office.inspection.create', ['purchase_order_id' => $po->id]))
            ->assertOk()->assertSee('INSPECTION AND ACCEPTANCE REPORT')->assertSee('Executive Table')
            ->assertSee('name="invoice_number"', false)->assertSee('name="inspection_date"', false)
            ->assertSee('name="acceptance_officer_name"', false)->assertSee('Save Draft');

        $this->post(route('head-office.inspection.store', $po), [
            'return_to' => 'editor', 'save_action' => 'draft', 'inspection_date' => '2026-11-09',
            'quantity_condition' => 'Partial', 'quality_condition' => 'Conforming', 'invoice_number' => 'DRAFT-001',
        ])->assertSessionHasNoErrors()->assertRedirect(route('head-office.inspection.create', ['purchase_order_id' => $po->id, 'edit' => 1]));
        $record = $po->latestInspectionAcceptanceRecord()->firstOrFail();
        $this->assertSame(InspectionAcceptanceRecord::STATUS_DRAFT, $record->status);
        $this->get(route('head-office.inspection.create', ['purchase_order_id' => $po->id, 'edit' => 1]))
            ->assertOk()->assertSee('DRAFT-001')->assertSee($record->document_reference_number);
    }

    public function test_source_picker_rejects_purchase_orders_outside_the_office(): void
    {
        [, $po] = $this->order();
        $other = User::factory()->create(['user_id' => 'IAR-OTHER', 'role' => User::ROLE_HEAD_OFFICE, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);
        $this->actingAs($other)->get(route('head-office.inspection.create', ['purchase_order_id' => $po->id]))->assertForbidden();
    }

    public function test_official_report_displays_purchase_order_items_and_saved_inspection_details(): void
    {
        [$user, $po] = $this->order();
        $record = InspectionAcceptanceRecord::create([
            'purchase_order_id' => $po->id,
            'status' => 'accepted',
            'report_date' => '2026-11-09',
            'invoice_date' => '2026-11-08',
            'invoice_number' => 'INV-009',
            'inspection_date' => '2026-11-09',
            'acceptance_date' => '2026-11-09',
            'quantity_condition' => 'Partial',
            'quality_condition' => 'Conforming',
            'inspector_name' => 'Test Inspector',
            'inspection_chair_name' => 'Test Assessor',
            'acceptance_officer_name' => 'Test Receiver',
        ]);

        $response = $this->actingAs($user)->get(route('head-office.inspection.print', $po));
        $response->assertOk()->assertSee('INSPECTION AND ACCEPTANCE REPORT')
            ->assertSee('Municipality of Tomas Oppus')->assertSee('R &amp; X Furniture', false)
            ->assertSee($record->document_reference_number)->assertSee('INV-009')
            ->assertSee('11/08/2026')->assertSee('Test Inspector')->assertSee('Test Assessor')
            ->assertSee('Test Receiver')->assertSee('Executive Table')->assertSee('>1.5</td>', false)
            ->assertDontSee('Unit Cost')->assertDontSee('ai-assistant-widget');
        $this->assertMatchesRegularExpression('/iar-checkbox">✓<\/span><span>Partial/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/iar-checkbox">✓<\/span><span>Complete/', $response->getContent());
    }

    public function test_unsaved_report_leaves_certifications_and_iar_number_blank(): void
    {
        [$user, $po] = $this->order();
        $this->actingAs($user)->get(route('head-office.inspection.print', $po))
            ->assertOk()->assertDontSee('✓')->assertSee('INSPECTION')->assertSee('ACCEPTANCE');
        $this->assertDatabaseCount('inspection_acceptance_records', 0);
    }

    public function test_another_office_cannot_print_the_report(): void
    {
        [, $po] = $this->order();
        $other = User::factory()->create(['user_id' => 'IAR-OTHER', 'role' => User::ROLE_HEAD_OFFICE, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);
        $request = Request::create('/');
        $request->setUserResolver(fn () => $other);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(InspectionAcceptanceController::class)->print($request, $po);
    }

    public function test_official_fields_are_saved_and_appear_in_the_report(): void
    {
        [$user, $po] = $this->order();
        $this->actingAs($user)->post(route('head-office.inspection.store', $po), [
            'inspection_date' => '2026-11-09', 'report_date' => '2026-11-09',
            'invoice_date' => '2026-11-08', 'invoice_number' => 'INV-101',
            'quantity_condition' => 'Complete', 'quality_condition' => 'Conforming',
            'inspector_name' => 'Saved Inspector', 'inspection_chair_name' => 'Saved Assessor',
            'acceptance_officer_name' => 'Saved Receiver',
        ])->assertSessionHasNoErrors()->assertRedirect(route('head-office.inspection.show', $po));
        $record = $po->latestInspectionAcceptanceRecord()->firstOrFail();
        $this->assertSame('2026-11-08', $record->invoice_date->toDateString());
        $this->assertSame('Saved Receiver', $record->acceptance_officer_name);
        $this->actingAs($user)->get(route('head-office.inspection.print', $po))
            ->assertOk()->assertSee('Saved Inspector')->assertSee('Saved Assessor')->assertSee('Saved Receiver');

        $this->actingAs($user)->patch(route('head-office.inspection.accept', $po), ['acceptance_date' => '2026-11-08'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('head-office.inspection.store', $po), [
            'inspection_date' => '2026-11-09', 'quantity_condition' => 'Complete', 'quality_condition' => 'Conforming',
            'inspector_name' => 'Updated Inspector',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(InspectionAcceptanceRecord::STATUS_ACCEPTED, $record->refresh()->status);
        $this->patch(route('head-office.inspection.complete', $po))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('2026-11-08', $record->refresh()->acceptance_date->toDateString());
        $this->assertSame('Saved Receiver', $record->acceptance_officer_name);
    }

    public function test_template_replaces_the_generic_default_and_preserves_admin_uploads(): void
    {
        Storage::fake('local');
        $templates = app(OfficialEditableTemplateService::class);
        $path = $templates->path('inspection_acceptance');
        Storage::disk('local')->put($path, 'old default');
        $templates->ensure('inspection_acceptance');
        $this->assertSame(file_get_contents(resource_path('templates/inspection-acceptance-tomas-oppus.xlsx')), Storage::disk('local')->get($path));
        $zip = new ZipArchive();
        $this->assertTrue($zip->open(Storage::disk('local')->path($path)));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('orientation="portrait"', $sheet);
        $this->assertStringContainsString('fitToHeight="1"', $sheet);
        $zip->close();

        $user = User::factory()->create(['user_id' => 'IAR-ADMIN', 'role' => User::ROLE_ADMIN]);
        DocumentTemplate::create([
            'document_type' => 'inspection_acceptance', 'template_name' => 'Admin version',
            'file_path' => $path, 'file_format' => 'xlsx', 'is_active' => true, 'uploaded_by' => $user->id,
        ]);
        Storage::disk('local')->put($path, 'admin uploaded contents');
        $templates->ensure('inspection_acceptance');
        $this->assertSame('admin uploaded contents', Storage::disk('local')->get($path));
    }

    private function order(): array
    {
        $office = Office::create(['code' => 'IAR-TEST', 'name' => 'Test Requesting Office', 'type' => Office::TYPE_END_USER, 'is_requesting_office' => true, 'status' => Office::STATUS_ACTIVE]);
        $user = User::factory()->create(['user_id' => 'IAR-HEAD', 'role' => User::ROLE_HEAD_OFFICE, 'office_id' => $office->id, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);
        $po = PurchaseOrder::create([
            'po_number' => 'PO-IAR-001', 'po_date' => '2026-11-07', 'fiscal_year' => 2026, 'supplier_name' => 'R & X Furniture',
            'status' => PurchaseOrder::STATUS_ISSUED, 'prepared_by_user_id' => $user->id,
        ]);
        $po->items()->create(['item_no' => 1, 'unit' => 'set', 'item_description' => 'Executive Table', 'description' => 'Executive Table', 'quantity' => 1.5, 'unit_cost' => 100, 'total_cost' => 150]);

        return [$user, $po];
    }
}
