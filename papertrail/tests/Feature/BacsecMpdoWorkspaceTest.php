<?php

namespace Tests\Feature;

use App\Models\ElectronicSignature;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\BacsecMpdoWorkspaceService;
use App\Services\HeadOfficePpmpSignatureWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BacsecMpdoWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = parent::createApplication();
        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql.database', 'papertrail_testing');
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function account(): User
    {
        $account = User::where('user_id', 'BACSEC-001')->firstOrFail();
        $account->update(['must_change_password' => false]);
        return $account;
    }

    public function test_bacsec_001_opens_all_mpdo_svp_pages_directly_with_the_same_sidebar_and_session(): void
    {
        $account = $this->account();
        $originalOffice = $account->office_id;
        $this->actingAs($account)->get(route('bac-secretariat.dashboard'))->assertOk()
            ->assertSee('MPDO Documents')->assertSee(route('head-office.ppmp.index'), false)
            ->assertDontSee('Switch Workspace')->assertDontSee('sidebar-workspace-navigation');
        $token = session()->token();
        $this->get(route('head-office.ppmp.index'))->assertOk();
        $workspace = User::where('user_id', BacsecMpdoWorkspaceService::WORKSPACE)->firstOrFail();
        $this->assertSame($account->id, $workspace->principal_user_id);
        $this->assertSame('MPDO', $workspace->assignedOffice->code);
        $this->assertSame(User::ROLE_OFFICE_PREPARER, $workspace->role);
        foreach (BacsecMpdoWorkspaceService::DESTINATIONS as $destination) {
            $this->get(route($destination))->assertOk()->assertSee('PR Copies')
                ->assertDontSee('Competitive Bidding')->assertDontSee('SVP Plans')->assertDontSee('Posting Management')
                ->assertDontSee('Switch Workspace')->assertDontSee('BAC Secretariat Duties')
                ->assertDontSee('MPDO Procurement Staff')->assertSessionMissing('active_workspace_id');
        }
        $this->get(route('bac-secretariat.dashboard'))->assertOk()->assertSee('PR Copies')
            ->assertDontSee('Competitive Bidding')->assertDontSee('SVP Plans');
        $this->assertSame($token, session()->token());
        $this->assertSame(User::ROLE_BAC_SECRETARIAT, $account->refresh()->role);
        $this->assertSame($originalOffice, $account->office_id);
        $this->assertCount(1, $account->availableWorkspaces());
    }

    public function test_mpdo_ppmp_is_prepared_by_bacsec_001_and_submitted_to_mpdo_head(): void
    {
        $account = $this->account();
        $account->update(['password' => Hash::make('password'), 'typed_signature_name' => 'MPDO Preparer',
            'signer_position' => 'Procurement Staff', 'signature_style' => 'typed', 'signature_setup_completed_at' => now()]);
        $this->actingAs($account)->get(route('head-office.ppmp.index'))->assertOk();
        $workspace = User::where('user_id', BacsecMpdoWorkspaceService::WORKSPACE)->firstOrFail();
        $this->post(route('head-office.ppmp.store'), ['fiscal_year' => 2026, 'ppmp_plan_type' => 'indicative', 'save_action' => 'draft',
            'prepared_by_name' => 'MPDO Preparer'])->assertSessionHasNoErrors()->assertRedirect();
        $document = ProcurementDocument::where('prepared_by_user_id', $workspace->id)->where('document_type', 'PPMP')->firstOrFail();
        $head = User::where('user_id', 'MPDO-001')->firstOrFail();
        $this->assertSame($workspace->office_id, $document->submitting_office_id);
        $this->assertSame($head->name, $document->submitted_by_name);
        $requests = app(HeadOfficePpmpSignatureWorkflowService::class)->start($document, $workspace)->keyBy('signatory_slot');
        $this->assertSame($workspace->id, $requests['prepared_by']->requested_to_user_id);
        $this->assertSame($head->id, $requests['submitted_by']->requested_to_user_id);
        $this->get(route('bac-secretariat.dashboard'))->assertOk();
        $this->get(route('signature-requests.index', ['status' => 'pending']))->assertOk()
            ->assertSee(route('signature-requests.show', $requests['prepared_by']), false);
        $this->get(route('signature-requests.show', $requests['prepared_by']))->assertOk();
        $this->post(route('signature-requests.sign', $requests['prepared_by']), [
            'current_password' => 'password', 'signature_consent' => true,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('electronic_signatures', ['signature_request_id' => $requests['prepared_by']->id,
            'signature_status' => ElectronicSignature::STATUS_SIGNED]);
        $this->assertSame($head->id, $document->refresh()->assigned_to_user_id);
        $this->assertFalse(session()->has('active_workspace_id'));
        $this->post(route('signature-requests.sign', $requests['submitted_by']), [
            'current_password' => 'password', 'signature_consent' => true,
        ])->assertForbidden();
    }

    public function test_mpdo_preparation_does_not_allow_other_office_documents_or_acceptance_approval(): void
    {
        $account = $this->account();
        $this->actingAs($account)->get(route('head-office.ppmp.index'))->assertOk();
        $otherOffice = Office::where('code', 'MAO')->firstOrFail();
        $foreign = ProcurementDocument::create(['document_type' => 'PR', 'title' => 'Private MAO Request',
            'status' => ProcurementDocument::STATUS_PR_DRAFT, 'fiscal_year' => 2026, 'submitting_office_id' => $otherOffice->id]);
        $this->get(route('head-office.documents.index'))->assertOk()->assertDontSee('Private MAO Request');
        $this->get(route('head-office.pr.show', $foreign))->assertForbidden();
        $this->get(route('head-office.documents.show', $foreign))->assertForbidden();
        $workspace = User::where('user_id', BacsecMpdoWorkspaceService::WORKSPACE)->firstOrFail();
        $po = \App\Models\PurchaseOrder::create(['fiscal_year' => 2026, 'status' => 'issued', 'prepared_by_user_id' => $workspace->id]);
        $this->patch(route('head-office.inspection.accept', $po), ['acceptance_date' => '2026-11-09'])
            ->assertRedirect(route('head-office.dashboard'))->assertSessionHas('error');
        $this->assertSame('issued', $po->refresh()->status);
    }

    public function test_other_accounts_cannot_claim_the_mpdo_assignment(): void
    {
        foreach (['BACSEC-003', 'BACSEC-002', 'MPDO-001', 'MAO-001'] as $code) {
            $user = User::where('user_id', $code)->firstOrFail();
            $user->update(['must_change_password' => false]);
            $this->actingAs($user)->post(route('workspaces.bacsec-mpdo', ['section' => 'ppmp']))->assertForbidden();
        }
        $this->assertSame(0, User::where('user_id', BacsecMpdoWorkspaceService::WORKSPACE)->count());
    }

    public function test_existing_mpdo_work_is_transferred_without_recreating_documents_or_history(): void
    {
        $account = $this->account();
        $previous = User::where('user_id', 'BACSEC-003')->firstOrFail();
        $previous->update(['must_change_password' => false]);
        $service = app(BacsecMpdoWorkspaceService::class);
        $actor = $service->ensure($account);
        $document = ProcurementDocument::create([
            'document_type' => 'PR', 'tracking_number' => 'MPDO-SVP-HANDOFF',
            'title' => 'Existing MPDO purchase', 'fiscal_year' => 2026,
            'status' => ProcurementDocument::STATUS_PR_DRAFT,
            'submitting_office_id' => $actor->office_id, 'current_office_id' => $actor->office_id,
            'prepared_by_user_id' => $actor->id, 'assigned_to_user_id' => $actor->id,
        ]);
        $history = \App\Models\DocumentRoutingHistory::create([
            'procurement_document_id' => $document->id, 'action_by_user_id' => $actor->id,
            'action' => 'Created before handoff', 'status_to' => $document->status, 'action_at' => now(),
        ]);
        $notification = \App\Models\SystemNotification::create([
            'user_id' => $actor->id, 'title' => 'Existing MPDO task', 'message' => 'Continue preparing the PR', 'type' => 'info',
        ]);
        $actor->forceFill(['user_id' => 'BACSEC-003-MPDO', 'principal_user_id' => $previous->id])->save();
        $snapshot = $document->fresh()->getAttributes();
        $historySnapshot = $history->fresh()->getAttributes();

        try {
            $service->ensure($account);
            $this->fail('A replacement workspace must not be created before the transfer migration.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('workspace', $exception->errors());
        }
        $this->assertFalse(User::where('user_id', BacsecMpdoWorkspaceService::WORKSPACE)->exists());

        $migration = require database_path('migrations/2026_11_11_030000_transfer_mpdo_svp_workspace_to_bacsec_001.php');
        $migration->up();
        $migration->up();

        $this->assertSame($actor->id, $service->existing($account)->id);
        $this->assertNull($service->existing($previous));
        $this->assertSame($snapshot, $document->fresh()->getAttributes());
        $this->assertSame($historySnapshot, $history->fresh()->getAttributes());
        $this->assertSame($actor->id, $notification->fresh()->user_id);
        $this->assertSame(1, User::where('user_id', BacsecMpdoWorkspaceService::WORKSPACE)->count());
        $this->assertFalse(User::where('user_id', 'BACSEC-003-MPDO')->exists());

        $this->actingAs($account)->get(route('head-office.pr.edit', $document))->assertOk();
        $this->get(route('notifications.index'))->assertOk()->assertSee('Existing MPDO task');
        $this->get(route('status-tracking.index'))->assertOk()->assertSee('Existing MPDO purchase');
        $this->actingAs($previous)->get(route('notifications.index'))->assertOk()->assertDontSee('Existing MPDO task');
        $this->get(route('status-tracking.index'))->assertOk()->assertDontSee('Existing MPDO purchase');
        $this->withSession(['active_workspace_id' => $actor->id])->get(route('bac-secretariat.dashboard'))
            ->assertRedirect(route('dashboard'))->assertSessionMissing('active_workspace_id');
        $this->get(route('bac-secretariat.dashboard'))->assertOk()->assertSee('Posting Management')
            ->assertSee('SVP Plans')->assertDontSee('MPDO Documents')
            ->assertDontSee(route('head-office.rfqs.create'), false);
    }

    public function test_assignment_is_idempotent_and_does_not_replace_a_different_office_assignment(): void
    {
        $account = $this->account();
        $service = app(BacsecMpdoWorkspaceService::class);
        $workspace = $service->ensure($account);
        $this->assertSame($workspace->id, $service->ensure($account)->id);
        $this->assertSame(1, User::where('user_id', BacsecMpdoWorkspaceService::WORKSPACE)->count());
        $workspace->update(['office_id' => Office::where('code', 'MAO')->value('id')]);
        $this->actingAs($account)->get(route('head-office.ppmp.index'))
            ->assertRedirect(route('bac-secretariat.dashboard'))->assertSessionHas('error');
        $this->assertSame('MAO', $workspace->refresh()->assignedOffice->code);
    }

    public function test_old_mpdo_selection_is_cleared_and_cannot_be_selected_from_the_workspace_menu(): void
    {
        $account = $this->account();
        $actor = app(BacsecMpdoWorkspaceService::class)->ensure($account);
        $this->actingAs($account)->withSession(['active_workspace_id' => $actor->id])
            ->get(route('bac-secretariat.dashboard'))->assertOk()->assertDontSee('Switch Workspace')
            ->assertSessionMissing('active_workspace_id');
        $this->post(route('workspaces.switch'), ['workspace_id' => $actor->id])->assertForbidden();
        $this->get(route('head-office.ppmp.index'))->assertOk()->assertSessionMissing('active_workspace_id');
        $this->get(route('bac-secretariat.ppmp.index'))->assertForbidden()->assertSessionMissing('active_workspace_id');
    }

    public function test_mpdo_and_bac_notifications_share_the_same_inbox_without_other_office_notifications(): void
    {
        $account = $this->account();
        $actor = app(BacsecMpdoWorkspaceService::class)->ensure($account);
        $first = \App\Models\SystemNotification::create(['user_id' => $account->id,
            'title' => 'BAC posting task', 'message' => 'BAC work', 'type' => 'info']);
        $second = \App\Models\SystemNotification::create(['user_id' => $actor->id,
            'title' => 'MPDO preparation task', 'message' => 'MPDO work', 'type' => 'info']);
        $foreign = \App\Models\SystemNotification::create(['user_id' => User::where('user_id', 'MAO-001')->value('id'),
            'title' => 'Private MAO notification', 'message' => 'Other office', 'type' => 'info']);
        $this->actingAs($account)->get(route('notifications.index'))->assertOk()
            ->assertSee('BAC posting task')->assertSee('MPDO preparation task')->assertDontSee('Private MAO notification');
        $this->get(route('notifications.show', $second))->assertOk();
        $this->patch(route('notifications.read-all'))->assertRedirect();
        $this->assertNotNull($first->refresh()->read_at);
        $this->assertNotNull($second->refresh()->read_at);
        $this->assertNull($foreign->refresh()->read_at);
        $this->get(route('notifications.show', $foreign))->assertForbidden();
    }

    public function test_standard_svp_templates_stay_in_the_mpdo_preparation_context(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $account = $this->account();
        $this->actingAs($account)->get(route('document-template-imports.upload-form', ['documentType' => 'inspection-acceptance']))
            ->assertOk()->assertDontSee('Switch Workspace')->assertSessionMissing('active_workspace_id');
        $this->get(route('document-templates.download', ['documentType' => 'inspection-acceptance']))->assertOk();
        $this->get(route('bac-secretariat.dashboard'))->assertOk()->assertSee('MPDO Documents')->assertDontSee('Posting Management');
    }
}
