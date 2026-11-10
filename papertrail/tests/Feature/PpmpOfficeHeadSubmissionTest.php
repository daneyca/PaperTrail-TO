<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Permission;
use App\Models\ProcurementDocument;
use App\Models\ElectronicSignature;
use App\Models\SignatureRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\HeadOfficePpmpSignatureWorkflowService;
use App\Services\ElectronicSignatureService;
use App\Services\SignatureRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PpmpOfficeHeadSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_signs_prepared_by_before_the_head_can_sign_submitted_by(): void
    {
        [$staff, $head] = $this->accounts();
        $document = ProcurementDocument::create([
            'document_type' => 'PPMP', 'title' => 'MAO PPMP', 'fiscal_year' => 2026, 'ppmp_no' => '1',
            'status' => ProcurementDocument::STATUS_PPMP_DRAFT, 'submitting_office_id' => $staff->office_id,
            'prepared_by_user_id' => $staff->id, 'submitted_by_user_id' => $staff->id,
        ]);
        $workflow = app(HeadOfficePpmpSignatureWorkflowService::class);
        $requests = $workflow->start($document, $staff)->keyBy('signatory_slot');
        $signatures = app(ElectronicSignatureService::class);
        $this->assertTrue($signatures->canSign($staff, $document, 'ppmp', 'confirmed', $requests['prepared_by']));
        $this->assertFalse($signatures->canSign($head, $document, 'ppmp', 'confirmed', $requests['submitted_by']));
        $this->assertFalse($signatures->canSign($staff, $document, 'ppmp', 'confirmed', $requests['submitted_by']));

        $this->actingAs($staff)->get(route('head-office.ppmp.show', $document))->assertOk()->assertSee('Sign Prepared By');
        $this->get(route('signature-requests.show', $requests['prepared_by']))->assertOk();
        $this->get(route('profile.signature.edit'))->assertOk();

        $this->actingAs($staff);
        $result = $signatures->signDocument($document, $staff, 'ppmp', 'confirmed', ['current_password' => 'password', 'signature_consent' => true], null, $requests['prepared_by']);
        $this->assertTrue($result['ok'], $result['message']);
        $this->assertSame(ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES, $document->refresh()->status);
        $this->assertSame($head->id, $document->assigned_to_user_id);
        $this->assertNull($document->submitted_at);
        $this->assertSame(0, \App\Models\SystemNotification::where('title', 'New PPMP for APP Consolidation')->count());
        $this->assertFalse($workflow->canSubmitToAppConsolidation($staff, $document));
        $this->assertTrue($signatures->canSign($head, $document, 'ppmp', 'confirmed', $requests['submitted_by']->refresh()));

        $this->actingAs($head);
        $result = $signatures->signDocument($document, $head, 'ppmp', 'confirmed', ['current_password' => 'password', 'signature_consent' => true], null, $requests['submitted_by']);
        $this->assertTrue($result['ok'], $result['message']);
        $this->assertSame(ProcurementDocument::STATUS_PENDING_PPMP_REVIEW, $document->refresh()->status);
        $handler = User::where('user_id', 'BACSEC-003')->firstOrFail();
        $this->assertSame($handler->id, $document->assigned_to_user_id);
        $this->assertSame($handler->office_id, $document->current_office_id);
        $this->assertNotNull($document->submitted_at);
        $this->assertSame('1', $document->ppmp_no);
        $this->assertStringContainsString('automatically sent to BAC Secretariat', $result['message']);
        $this->assertFalse($workflow->canSubmitToAppConsolidation($staff, $document));
        $this->assertFalse($workflow->canSubmitToAppConsolidation($head, $document));
        $this->assertSame(2, ElectronicSignature::where('document_type', 'ppmp')->where('document_id', $document->id)->where('signature_status', 'signed')->count());
        $this->assertDatabaseHas('system_notifications', ['user_id' => $staff->id, 'related_id' => $document->id, 'title' => 'PPMP Automatically Submitted']);
        $this->assertDatabaseHas('system_notifications', ['user_id' => $handler->id, 'related_id' => $document->id, 'title' => 'New PPMP for APP Consolidation']);
        $this->assertDatabaseHas('system_notifications', ['user_id' => $head->id, 'related_id' => $document->id, 'title' => 'PPMP Automatically Submitted']);
        $this->actingAs($staff)->get(route('head-office.ppmp.show', $document))->assertOk()
            ->assertSee('Current Office')->assertSee('BAC Secretariat')->assertDontSee('Submit to BAC Secretariat');
        $this->actingAs($head)->get(route('signature-requests.show', $requests['submitted_by']))->assertOk()
            ->assertSee('Current Office')->assertSee('BAC Secretariat')->assertDontSee('Submit to BAC Secretariat');
        $this->get(route('head-office.documents.index'))->assertOk()->assertDontSee('Not submitted');
        $this->actingAs($handler)->get(route('bac-secretariat.ppmp.index'))->assertOk()->assertSee($document->tracking_number);
        $this->get(route('bac-secretariat.ppmp.show', $document))->assertOk()->assertSee($document->tracking_number);

        $submittedAt = $document->submitted_at;
        $workflow->handleSigned($document, $head, true);
        $this->assertEquals($submittedAt, $document->refresh()->submitted_at);
        $this->assertSame(1, $document->routingHistories()->where('action', 'PPMP Submitted to BAC')->count());
        $this->assertSame(1, \App\Models\SystemNotification::where('user_id', $handler->id)->where('related_id', $document->id)
            ->where('title', 'New PPMP for APP Consolidation')->count());
        $document->update(['status' => ProcurementDocument::STATUS_UNDER_PPMP_REVIEW]);
        $workflow->handleSigned($document, $head, true);
        $this->assertSame(ProcurementDocument::STATUS_UNDER_PPMP_REVIEW, $document->refresh()->status);

        $html = $this->actingAs($staff)->get(route('head-office.ppmp.print', $document))->assertOk()->getContent();
        $dom = new \DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);
        $this->assertStringContainsString($staff->name, $xpath->query('//div[@aria-label="Prepared By electronic signature"]')->item(0)->textContent);
        $this->assertStringContainsString($head->name, $xpath->query('//div[@aria-label="Submitted By electronic signature"]')->item(0)->textContent);
    }

    public function test_signed_ppmps_wait_for_an_active_handler_and_existing_valid_approvals_are_routed_once(): void
    {
        [$staff, $head] = $this->accounts();
        $handler = User::where('user_id', 'BACSEC-003')->firstOrFail();
        $handler->update(['status' => User::STATUS_INACTIVE]);
        $document = ProcurementDocument::create([
            'document_type' => 'PPMP', 'title' => 'Existing approved PPMP', 'fiscal_year' => 2026,
            'status' => ProcurementDocument::STATUS_PPMP_DRAFT, 'submitting_office_id' => $staff->office_id,
            'prepared_by_user_id' => $staff->id, 'submitted_by_user_id' => $staff->id,
        ]);
        $workflow = app(HeadOfficePpmpSignatureWorkflowService::class);
        $requests = $workflow->start($document, $staff)->keyBy('signatory_slot');
        foreach (['prepared_by' => $staff, 'submitted_by' => $head] as $slot => $signer) {
            $this->actingAs($signer);
            $result = app(ElectronicSignatureService::class)->signDocument($document, $signer, 'ppmp', 'confirmed',
                ['current_password' => 'password', 'signature_consent' => true], null, $requests[$slot]->refresh());
            $this->assertTrue($result['ok'], $result['message']);
        }
        $this->assertSame(ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED, $document->refresh()->status);
        $this->assertNull($document->submitted_at);
        $this->assertSame(2, ElectronicSignature::where('document_type', 'ppmp')->where('document_id', $document->id)->where('signature_status', 'signed')->count());
        $this->assertDatabaseHas('system_notifications', ['user_id' => $staff->id, 'title' => 'PPMP Signed — Routing Pending']);

        $unsigned = ProcurementDocument::create([
            'document_type' => 'PPMP', 'title' => 'Unsigned PPMP', 'fiscal_year' => 2026,
            'status' => ProcurementDocument::STATUS_PPMP_DRAFT, 'submitting_office_id' => $staff->office_id,
            'prepared_by_user_id' => $staff->id, 'submitted_by_user_id' => $staff->id,
        ]);
        $workflow->start($unsigned, $staff)->each(fn ($request) => $request->update(['status' => SignatureRequest::STATUS_SIGNED, 'signed_at' => now()]));
        $unsigned->update(['status' => ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED]);
        $handler->update(['status' => User::STATUS_ACTIVE]);

        $migration = require database_path('migrations/2026_11_10_000002_automatically_route_fully_signed_ppmps.php');
        $migration->up();
        $this->assertSame(ProcurementDocument::STATUS_PENDING_PPMP_REVIEW, $document->refresh()->status);
        $this->assertSame($handler->id, $document->assigned_to_user_id);
        $this->assertNotNull($document->submitted_at);
        $this->assertSame(ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED, $unsigned->refresh()->status);
        $this->assertNull($unsigned->submitted_at);

        $submittedAt = $document->submitted_at;
        $migration->up();
        $workflow->routeSignedToBac($document, $staff);
        $this->assertEquals($submittedAt, $document->refresh()->submitted_at);
        $this->assertSame(1, $document->routingHistories()->where('action', 'PPMP Submitted to BAC')->count());
        $this->assertSame(1, \App\Models\SystemNotification::where('user_id', $handler->id)->where('related_id', $document->id)
            ->where('title', 'New PPMP for APP Consolidation')->count());
    }

    public function test_signature_remarks_are_visible_to_the_signer_and_next_signatory_without_changing_the_printed_form(): void
    {
        [$staff, $head] = $this->accounts();
        $document = ProcurementDocument::create([
            'document_type' => 'PPMP', 'title' => 'PPMP with signing remarks', 'fiscal_year' => 2026,
            'status' => ProcurementDocument::STATUS_PPMP_DRAFT, 'submitting_office_id' => $staff->office_id,
            'prepared_by_user_id' => $staff->id, 'submitted_by_user_id' => $staff->id,
        ]);
        $requests = app(HeadOfficePpmpSignatureWorkflowService::class)->start($document, $staff)->keyBy('signatory_slot');
        $remarks = "done\nPlease review the delivery schedule. <script>alert('test')</script>";
        $this->actingAs($staff)->post(route('signature-requests.sign', $requests['prepared_by']), [
            'current_password' => 'password', 'signature_consent' => true, 'remarks' => $remarks,
        ])->assertRedirect()->assertSessionMissing('error');
        $signature = ElectronicSignature::where('signature_request_id', $requests['prepared_by']->id)->firstOrFail();
        $this->assertSame($remarks, data_get($signature->metadata, 'remarks'));
        $this->assertSame($remarks, $requests['prepared_by']->refresh()->remarks);

        $this->get(route('signature-requests.show', $requests['prepared_by']))->assertOk()
            ->assertSee('Signature Remarks')->assertSee($remarks)->assertDontSee($remarks, false);
        $this->get(route('head-office.ppmp.show', $document))->assertOk()->assertSee('Remarks')->assertSee($remarks);
        $this->get(route('e-signatures.certificate', $signature))->assertOk()->assertSee('Remarks')->assertSee($remarks);
        $this->get(route('head-office.ppmp.print', $document))->assertOk()->assertDontSee($remarks);
        $this->actingAs($head)->get(route('signature-requests.show', $requests['submitted_by']))->assertOk()
            ->assertSee('Signature Remarks')->assertSee($remarks);
    }

    public function test_pending_legacy_head_only_request_is_replaced_by_staff_and_head_requests(): void
    {
        [$staff, $head] = $this->accounts();
        $document = ProcurementDocument::create([
            'document_type' => 'PPMP', 'title' => 'Legacy MAO PPMP', 'fiscal_year' => 2026,
            'status' => ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES, 'submitting_office_id' => $staff->office_id,
            'prepared_by_user_id' => $staff->id, 'submitted_by_user_id' => $staff->id,
        ]);
        $legacy = app(SignatureRequestService::class)->createOrUpdateRequest($document, 'ppmp', [
            'slot' => 'prepared_and_submitted_by', 'label' => 'Head of Office Approval', 'account_code' => $head->user_id, 'required' => true,
        ], $staff);
        $workflow = app(HeadOfficePpmpSignatureWorkflowService::class);
        $workflow->ensureRequiredSignatures($document, $staff);
        $this->assertFalse($legacy->refresh()->is_required);
        $this->assertSame(SignatureRequest::STATUS_SKIPPED, $legacy->status);
        $this->assertCount(2, $workflow->signatureRequests($document));
        $this->assertFalse($workflow->hasCompletedRequiredSignatures($document));
    }

    public function test_mao_staff_ppmp_displays_and_routes_submission_to_the_mao_head(): void
    {
        [$staff, $head] = $this->accounts();
        $this->actingAs($staff)->get(route('head-office.ppmp.create'))->assertOk()
            ->assertSee('value="MAO Head" aria-label="Submitted by printed name"', false)
            ->assertSee('Head of Office: MAO-001');

        $payload = ['fiscal_year' => 2026, 'ppmp_plan_type' => 'indicative', 'save_action' => 'draft', 'prepared_by_name' => 'Anafe N. Paloma', 'submitted_by_name' => 'Anafe N. Paloma'];
        $this->post(route('head-office.ppmp.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $document = ProcurementDocument::where('document_type', 'PPMP')->firstOrFail();
        $this->assertSame($staff->id, $document->prepared_by_user_id);
        $this->assertSame($staff->name, $document->prepared_by_name);
        $this->assertSame($head->name, $document->submitted_by_name);
        $this->patch(route('head-office.ppmp.update', $document), $payload)
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($head->name, $document->refresh()->submitted_by_name);

        $requests = app(HeadOfficePpmpSignatureWorkflowService::class)->start($document, $staff);
        $this->assertCount(2, $requests);
        $bySlot = $requests->keyBy('signatory_slot');
        $this->assertSame($staff->id, $bySlot['prepared_by']->requested_to_user_id);
        $this->assertSame($head->id, $bySlot['submitted_by']->requested_to_user_id);
        $this->assertSame($staff->id, $bySlot['submitted_by']->requested_by_user_id);
        $this->assertSame($staff->id, $document->refresh()->assigned_to_user_id);
        $this->assertSame('MAO-001', $bySlot['submitted_by']->requestedTo->user_id);
    }

    public function test_existing_draft_with_staff_submitted_name_displays_the_same_office_head(): void
    {
        [$staff, $head] = $this->accounts();
        $document = ProcurementDocument::create([
            'document_type' => 'PPMP', 'title' => 'MAO PPMP', 'fiscal_year' => 2026,
            'status' => ProcurementDocument::STATUS_PPMP_DRAFT, 'submitting_office_id' => $staff->office_id,
            'prepared_by_user_id' => $staff->id, 'submitted_by_user_id' => $staff->id,
            'prepared_by_name' => $staff->name, 'submitted_by_name' => $staff->name,
        ]);
        $this->actingAs($staff)->get(route('head-office.ppmp.edit', $document))->assertOk()
            ->assertSee('value="MAO Head" aria-label="Submitted by printed name"', false)
            ->assertSee('Head of Office: MAO-001');
        $this->get(route('head-office.ppmp.print', $document))->assertOk()->assertSee('MAO Head')->assertSee('Anafe N. Paloma');
        $this->assertSame($head->office_id, $document->submitting_office_id);
    }

    private function accounts(): array
    {
        $office = Office::create(['code' => 'MAO', 'name' => 'Municipal Agriculture Office', 'type' => Office::TYPE_END_USER, 'is_requesting_office' => true, 'status' => Office::STATUS_ACTIVE]);
        $role = Role::where('code', 'office_preparer')->firstOrFail();
        foreach (['ppmp.view', 'ppmp.submit', 'documents.submit', 'documents.edit.own'] as $key) {
            $permission = Permission::firstOrCreate(['key' => $key], ['name' => $key, 'group' => 'PPMP']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $staff = User::factory()->create(['user_id' => 'MAO-STAFF', 'name' => 'Anafe N. Paloma', 'role' => User::ROLE_OFFICE_PREPARER, 'role_id' => $role->id, 'office_id' => $office->id, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);
        $head = User::factory()->create(['user_id' => 'MAO-001', 'name' => 'MAO Head', 'role' => User::ROLE_HEAD_OFFICE, 'office_id' => $office->id, 'position' => 'Municipal Agriculturist', 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);
        $staff->update(['typed_signature_name' => $staff->name, 'signer_position' => 'Procurement Staff', 'signature_style' => 'typed', 'signature_setup_completed_at' => now()]);
        $head->update(['typed_signature_name' => $head->name, 'signer_position' => 'Municipal Agriculturist', 'signature_style' => 'typed', 'signature_setup_completed_at' => now()]);
        $otherOffice = Office::create(['code' => 'OTHER', 'name' => 'Other Office', 'type' => Office::TYPE_END_USER, 'is_requesting_office' => true, 'status' => Office::STATUS_ACTIVE]);
        User::factory()->create(['user_id' => 'OTHER-001', 'name' => 'Other Head', 'role' => User::ROLE_HEAD_OFFICE, 'office_id' => $otherOffice->id, 'status' => User::STATUS_ACTIVE]);

        $bacOffice = Office::firstOrCreate(['code' => 'BACSEC'], ['name' => 'BAC Secretariat', 'type' => Office::TYPE_PROCUREMENT, 'status' => Office::STATUS_ACTIVE]);
        $bacRole = Role::firstOrCreate(['code' => 'bac_secretariat'], ['name' => User::ROLE_BAC_SECRETARIAT, 'status' => Role::STATUS_ACTIVE]);
        foreach (['ppmp.review', 'documents.view.all', 'documents.track', 'documents.return', 'documents.comment', 'workflow.update_status'] as $key) {
            $permission = Permission::firstOrCreate(['key' => $key], ['name' => $key, 'group' => 'PPMP']);
            $bacRole->permissions()->syncWithoutDetaching([$permission->id]);
        }
        User::where('user_id', 'BACSEC-003')->firstOrFail()->update(['office_id' => $bacOffice->id, 'role_id' => $bacRole->id,
            'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);

        return [$staff, $head];
    }
}
