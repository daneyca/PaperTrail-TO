<?php

namespace Tests\Feature;

use App\Http\Controllers\HeadOffice\InspectionAcceptanceController;
use App\Models\DocumentTemplate;
use App\Models\InspectionAcceptanceRecord;
use App\Models\Office;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\SignatureRequest;
use App\Models\ElectronicSignature;
use App\Services\InspectionSignatureWorkflowService;
use App\Services\ElectronicSignatureService;
use App\Services\SignatureRequestService;
use Illuminate\Support\Facades\Hash;
use App\Services\Templates\OfficialEditableTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class SvpInspectionAcceptanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = parent::createApplication();
        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql.database', 'papertrail_testing');
        return $app;
    }

    public function test_acceptance_completion_link_opens_and_keeps_saved_names_through_completion(): void
    {
        [$user, $po] = $this->order();
        $record = InspectionAcceptanceRecord::create([
            'purchase_order_id' => $po->id, 'status' => InspectionAcceptanceRecord::STATUS_INSPECTED,
            'inspection_date' => '2026-11-09', 'quantity_condition' => 'Complete', 'quality_condition' => 'Conforming',
            'inspector_name' => 'Saved Inspector', 'inspection_chair_name' => 'Saved Assessor',
            'acceptance_officer_name' => 'Saved Receiver',
        ]);
        $this->actingAs($user)->get(route('head-office.inspection.create', ['purchase_order_id' => $po->id, 'edit' => 1]))
            ->assertOk()->assertSee('Acceptance / Completion')->assertSee(route('head-office.inspection.show', $po), false);
        $this->get(route('head-office.inspection.show', $po))->assertOk()
            ->assertDontSee('Mark Accepted')->assertSee('Saved Inspector')->assertSee('Saved Assessor')->assertSee('Saved Receiver')
            ->assertSee('placeholder="Enter Name"', false);
        $this->signReport($user, $po, $record);
        $this->get(route('head-office.inspection.show', $po))->assertOk()->assertSee('Mark Accepted');
        $this->patch(route('head-office.inspection.accept', $po), ['acceptance_date' => '2026-11-09'])
            ->assertSessionHasNoErrors()->assertRedirect(route('head-office.inspection.show', $po));
        $this->get(route('head-office.inspection.show', $po))->assertOk()->assertSee('Mark Completed');
        $this->patch(route('head-office.inspection.complete', $po))
            ->assertSessionHasNoErrors()->assertRedirect(route('head-office.inspection.show', $po));
        $this->get(route('head-office.inspection.show', $po))->assertOk()->assertSee('Saved Receiver');
        $this->assertSame(InspectionAcceptanceRecord::STATUS_COMPLETED, $record->refresh()->status);
    }

    public function test_acceptance_page_also_opens_before_an_inspection_record_is_saved(): void
    {
        [$user, $po] = $this->order();
        $this->actingAs($user)->get(route('head-office.inspection.show', $po))->assertOk()
            ->assertDontSee('Mark Accepted')->assertSee('placeholder="Enter Name"', false);
        $this->assertDatabaseCount('inspection_acceptance_records', 0);
    }

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
            ->assertSee('name="acceptance_officer_name"', false)->assertSee('Save Draft')
            ->assertSee('name="inspector_designation"', false)->assertSee('name="inspection_chair_designation"', false)
            ->assertSee('name="acceptance_officer_designation"', false);

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
            'inspector_designation' => 'Inspection Team Member', 'inspection_chair_designation' => 'Inspection Team Chair',
            'acceptance_officer_designation' => 'Supply Officer II',
        ])->assertSessionHasNoErrors()->assertRedirect(route('head-office.inspection.show', $po));
        $record = $po->latestInspectionAcceptanceRecord()->firstOrFail();
        $this->assertSame('2026-11-08', $record->invoice_date->toDateString());
        $this->assertSame('Saved Receiver', $record->acceptance_officer_name);
        $this->assertSame('Supply Officer II', $record->acceptance_officer_designation);
        $this->actingAs($user)->get(route('head-office.inspection.print', $po))
            ->assertOk()->assertSee('Saved Inspector')->assertSee('Saved Assessor')->assertSee('Saved Receiver')
            ->assertSee('Inspection Team Member')->assertSee('Inspection Team Chair')->assertSee('Supply Officer II')
            ->assertDontSee('Admin Aide I');
        $this->get(route('head-office.inspection.create', ['purchase_order_id' => $po->id, 'edit' => 1]))
            ->assertOk()->assertSee('value="Supply Officer II"', false);
        $this->get(route('head-office.inspection.show', $po))->assertOk()
            ->assertSee('name="acceptance_officer_designation"', false)->assertSee('value="Supply Officer II"', false);

        $record->update(['acceptance_date' => '2026-11-08']);
        $this->signReport($user, $po, $record);

        $this->actingAs($user)->patch(route('head-office.inspection.accept', $po), ['acceptance_date' => '2026-11-08'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('head-office.inspection.store', $po), [
            'inspection_date' => '2026-11-09', 'quantity_condition' => 'Complete', 'quality_condition' => 'Conforming',
            'inspector_name' => 'Updated Inspector',
        ])->assertSessionHasErrors('signatures')->assertRedirect();
        $this->assertSame(InspectionAcceptanceRecord::STATUS_ACCEPTED, $record->refresh()->status);
        $this->patch(route('head-office.inspection.complete', $po))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('2026-11-08', $record->refresh()->acceptance_date->toDateString());
        $this->assertSame('Saved Receiver', $record->acceptance_officer_name);
        $this->assertSame('Inspection Team Member', $record->inspector_designation);
        $this->assertSame('Inspection Team Chair', $record->inspection_chair_designation);
        $this->assertSame('Supply Officer II', $record->acceptance_officer_designation);
        $this->assertSame('Saved Inspector', $record->inspector_name);
    }

    public function test_iar_routes_to_exact_accounts_and_requires_all_three_actual_signatures(): void
    {
        [$owner, $po] = $this->order();
        $signers = $this->signers($owner);
        $this->actingAs($owner)->post(route('head-office.inspection.store', $po), $this->signaturePayload())
            ->assertSessionHasNoErrors()->assertRedirect();
        $record = $po->latestInspectionAcceptanceRecord()->firstOrFail();
        $this->assertSame(InspectionAcceptanceRecord::STATUS_PENDING_SIGNATURES, $record->status);
        $requests = app(InspectionSignatureWorkflowService::class)->currentRequests($record)->with('requestedTo')->get();
        $this->assertCount(3, $requests);
        $this->assertSame(['inspection_officer' => 'MO-STAFF', 'inspection_chair' => 'MCR-001', 'acceptance_officer' => 'PC-001'],
            $requests->pluck('requestedTo.user_id', 'signatory_slot')->all());
        $this->patch(route('head-office.inspection.accept', $po), ['acceptance_date' => '2026-11-09'])->assertSessionHas('error');
        $this->patch(route('head-office.inspection.complete', $po))->assertSessionHas('error');
        foreach ($requests as $index => $request) {
            $signer = $signers[$request->requestedTo->user_id];
            $this->actingAs($signer)->get(route('signature-requests.index'))->assertOk()->assertSee($record->displayNumber())->assertSee('Pending Signatures');
            $this->get(route('signature-requests.show', $request))->assertOk()->assertSee('Executive Table')->assertSee('INSPECTION AND ACCEPTANCE REPORT');
            $this->post(route('signature-requests.sign', $request), ['current_password' => 'password', 'signature_consent' => true])
                ->assertSessionHasNoErrors()->assertSessionMissing('error')->assertRedirect();
            $this->assertSame(SignatureRequest::STATUS_SIGNED, $request->refresh()->status);
            if ($index < 2) {
                $this->assertFalse(app(InspectionSignatureWorkflowService::class)->isFullySigned($record->refresh()));
            }
        }
        $this->assertTrue(app(InspectionSignatureWorkflowService::class)->isFullySigned($record->refresh()));
        $this->assertSame(InspectionAcceptanceRecord::STATUS_FULLY_SIGNED, $record->status);
        $this->actingAs($owner)->get(route('head-office.inspection.print', $po))->assertOk()->assertSee('Electronic signature');
        $this->patch(route('head-office.inspection.accept', $po), ['acceptance_date' => '2026-11-09'])->assertSessionMissing('error')->assertRedirect();
        $this->patch(route('head-office.inspection.complete', $po))->assertSessionMissing('error')->assertRedirect();
        $this->assertSame(InspectionAcceptanceRecord::STATUS_COMPLETED, $record->refresh()->status);
        $this->assertSame($signers['PC-001']->id, $record->accepted_by_user_id);
    }

    public function test_iar_revision_invalidates_old_signatures_and_routes_new_requests_without_erasing_history(): void
    {
        [$owner, $po] = $this->order();
        $this->signers($owner);
        $this->actingAs($owner)->post(route('head-office.inspection.store', $po), $this->signaturePayload())->assertSessionHasNoErrors();
        $record = $po->latestInspectionAcceptanceRecord()->firstOrFail();
        $request = app(InspectionSignatureWorkflowService::class)->currentRequests($record)->firstOrFail();
        $this->actingAs($request->requestedTo)->post(route('signature-requests.sign', $request), ['current_password' => 'password', 'signature_consent' => true])->assertSessionMissing('error');
        $oldSignature = ElectronicSignature::where('signature_request_id', $request->id)->firstOrFail();
        $this->actingAs($owner)->patch(route('head-office.inspection.revise', $po))->assertSessionHasNoErrors();
        $this->assertSame(ElectronicSignature::STATUS_INVALIDATED, $oldSignature->refresh()->signature_status);
        $this->assertSame(InspectionAcceptanceRecord::STATUS_DRAFT, $record->refresh()->status);
        $this->post(route('head-office.inspection.store', $po), [...$this->signaturePayload(), 'inspector_designation' => 'Revised Inspector'])
            ->assertSessionHasNoErrors()->assertSessionMissing('error');
        $record->refresh();
        $this->assertSame(2, $record->signature_revision);
        $newRequests = app(InspectionSignatureWorkflowService::class)->currentRequests($record)->get();
        $this->assertCount(3, $newRequests);
        $this->assertFalse($newRequests->contains('id', $request->id));
        $this->assertDatabaseHas('electronic_signatures', ['id' => $oldSignature->id]);
        $this->get(route('head-office.inspection.print', $po))->assertOk()->assertDontSee($oldSignature->signature_code);
        $this->actingAs($request->requestedTo)->post(route('signature-requests.sign', $request), ['current_password' => 'password', 'signature_consent' => true])->assertSessionHas('error');
    }

    public function test_iar_decline_tampering_inactive_accounts_and_unassigned_users_cannot_bypass_signatures(): void
    {
        [$owner, $po] = $this->order();
        $signers = $this->signers($owner);
        $signers['MO-STAFF']->update(['status' => User::STATUS_INACTIVE]);
        $this->actingAs($owner)->post(route('head-office.inspection.store', $po), $this->signaturePayload())->assertSessionHasErrors('signatures');
        $this->assertDatabaseCount('signature_requests', 0);
        $signers['MO-STAFF']->update(['status' => User::STATUS_ACTIVE]);
        $this->post(route('head-office.inspection.store', $po), $this->signaturePayload())->assertSessionHasNoErrors();
        $record = $po->latestInspectionAcceptanceRecord()->firstOrFail();
        $request = app(InspectionSignatureWorkflowService::class)->currentRequests($record)->firstOrFail();
        $peer = User::factory()->create(['user_id' => 'IAR-PEER', 'office_id' => $owner->office_id, 'role' => User::ROLE_HEAD_OFFICE, 'must_change_password' => false, 'status' => User::STATUS_ACTIVE]);
        $this->actingAs($peer)->get(route('signature-requests.show', $request))->assertForbidden();
        $this->post(route('signature-requests.sign', $request), ['current_password' => 'password', 'signature_consent' => true])->assertForbidden();
        $item = $po->items()->firstOrFail();
        $item->update(['quantity' => 99]);
        $this->actingAs($request->requestedTo)->post(route('signature-requests.sign', $request), ['current_password' => 'password', 'signature_consent' => true])->assertSessionHas('error');
        $this->assertDatabaseCount('electronic_signatures', 0);
        $item->update(['quantity' => 1.5]);
        $this->post(route('signature-requests.decline', $request), ['reason' => 'Correct the delivery details'])->assertSessionMissing('error');
        $this->assertSame(InspectionAcceptanceRecord::STATUS_RETURNED, $record->refresh()->status);
        $this->assertSame(0, app(InspectionSignatureWorkflowService::class)->currentRequests($record)->count());
    }

    private function signaturePayload(): array
    {
        return ['save_action' => 'submit', 'inspection_date' => '2026-11-09', 'acceptance_date' => '2026-11-09',
            'quantity_condition' => 'Complete', 'quality_condition' => 'Conforming'];
    }

    private function signers(User $owner): array
    {
        $users = [];
        foreach (['MO-STAFF', 'MCR-001', 'PC-001'] as $code) {
            $office = $code === 'MCR-001' ? $owner->office_id : Office::firstOrCreate(['code' => 'IAR-'.$code],
                ['name' => 'IAR Signatory '.$code, 'type' => Office::TYPE_END_USER, 'is_requesting_office' => true, 'status' => Office::STATUS_ACTIVE])->id;
            $user = User::where('user_id', $code)->first() ?: User::factory()->create(['user_id' => $code,
                'role' => $code === 'MO-STAFF' ? User::ROLE_OFFICE_PREPARER : User::ROLE_HEAD_OFFICE]);
            $user->update(['role_id' => null, 'role' => $code === 'MO-STAFF' ? User::ROLE_OFFICE_PREPARER : User::ROLE_HEAD_OFFICE,
                'office_id' => $office, 'password' => Hash::make('password'), 'status' => User::STATUS_ACTIVE,
                'must_change_password' => false, 'typed_signature_name' => $code.' Signer', 'signer_position' => 'Assigned IAR Signatory',
                'signature_style' => 'typed', 'signature_setup_completed_at' => now()]);
            $users[$code] = $user;
        }
        return $users;
    }

    private function signReport(User $owner, PurchaseOrder $po, InspectionAcceptanceRecord $record): void
    {
        $this->signers($owner);
        $record->update(['acceptance_date' => $record->acceptance_date ?: '2026-11-09']);
        app(InspectionSignatureWorkflowService::class)->submit($record, $owner);
        foreach (app(InspectionSignatureWorkflowService::class)->currentRequests($record->refresh())->with('requestedTo')->get() as $request) {
            $result = app(ElectronicSignatureService::class)->signDocument($record->refresh(), $request->requestedTo, 'inspection_acceptance', 'confirmed',
                ['current_password' => 'password', 'signature_consent' => true], null, $request);
            $this->assertTrue($result['ok'], $result['message']);
        }
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
