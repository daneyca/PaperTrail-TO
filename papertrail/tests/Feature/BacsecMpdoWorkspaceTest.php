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

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function account(): User
    {
        $account = User::where('user_id', 'BACSEC-003')->firstOrFail();
        $account->update(['must_change_password' => false]);
        return $account;
    }

    public function test_bacsec_003_opens_all_mpdo_svp_pages_and_can_return_to_bac_duties(): void
    {
        $account = $this->account();
        $originalOffice = $account->office_id;
        $this->actingAs($account)->get(route('bac-secretariat.dashboard'))->assertOk()
            ->assertSee('MPDO Documents')->assertSee(route('workspaces.bacsec-mpdo', ['section' => 'ppmp']), false);
        $this->post(route('workspaces.bacsec-mpdo', ['section' => 'ppmp']))
            ->assertRedirect(route('head-office.ppmp.index'));
        $workspace = User::where('user_id', BacsecMpdoWorkspaceService::WORKSPACE)->firstOrFail();
        $this->assertSame($account->id, $workspace->principal_user_id);
        $this->assertSame('MPDO', $workspace->assignedOffice->code);
        $this->assertSame(User::ROLE_OFFICE_PREPARER, $workspace->role);
        foreach (BacsecMpdoWorkspaceService::DESTINATIONS as $destination) {
            $this->get(route($destination))->assertOk()->assertSee('MPDO Procurement Staff')
                ->assertSee('BAC Secretariat Duties');
        }
        $this->post(route('workspaces.bacsec-mpdo', ['section' => 'bac-secretariat']))
            ->assertRedirect(route('bac-secretariat.dashboard'));
        $this->get(route('bac-secretariat.dashboard'))->assertOk()->assertSee('Posting Management')
            ->assertSee('Competitive Bidding')->assertSee('SVP Plans');
        $this->assertSame(User::ROLE_BAC_SECRETARIAT, $account->refresh()->role);
        $this->assertSame($originalOffice, $account->office_id);
        $this->assertCount(2, $account->availableWorkspaces());
    }

    public function test_mpdo_ppmp_is_prepared_by_bacsec_003_and_submitted_to_mpdo_head(): void
    {
        $account = $this->account();
        $account->update(['password' => Hash::make('password'), 'typed_signature_name' => 'MPDO Preparer',
            'signer_position' => 'Procurement Staff', 'signature_style' => 'typed', 'signature_setup_completed_at' => now()]);
        $this->actingAs($account)->post(route('workspaces.bacsec-mpdo', ['section' => 'ppmp']));
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
        $this->get(route('signature-requests.show', $requests['prepared_by']))->assertOk();
        $this->post(route('signature-requests.sign', $requests['prepared_by']), [
            'current_password' => 'password', 'signature_consent' => true,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('electronic_signatures', ['signature_request_id' => $requests['prepared_by']->id,
            'signature_status' => ElectronicSignature::STATUS_SIGNED]);
        $this->assertSame($head->id, $document->refresh()->assigned_to_user_id);
    }

    public function test_mpdo_preparation_does_not_allow_other_office_documents_or_acceptance_approval(): void
    {
        $account = $this->account();
        $this->actingAs($account)->post(route('workspaces.bacsec-mpdo', ['section' => 'ppmp']));
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
        foreach (['BACSEC-001', 'MPDO-001', 'MAO-001'] as $code) {
            $user = User::where('user_id', $code)->firstOrFail();
            $user->update(['must_change_password' => false]);
            $this->actingAs($user)->post(route('workspaces.bacsec-mpdo', ['section' => 'ppmp']))->assertForbidden();
        }
        $this->assertSame(0, User::where('user_id', BacsecMpdoWorkspaceService::WORKSPACE)->count());
    }

    public function test_assignment_is_idempotent_and_does_not_replace_a_different_office_assignment(): void
    {
        $account = $this->account();
        $service = app(BacsecMpdoWorkspaceService::class);
        $workspace = $service->ensure($account);
        $this->assertSame($workspace->id, $service->ensure($account)->id);
        $this->assertSame(1, User::where('user_id', BacsecMpdoWorkspaceService::WORKSPACE)->count());
        $workspace->update(['office_id' => Office::where('code', 'MAO')->value('id')]);
        $this->actingAs($account)->post(route('workspaces.bacsec-mpdo', ['section' => 'ppmp']))
            ->assertSessionHas('error');
        $this->assertSame('MAO', $workspace->refresh()->assignedOffice->code);
    }
}
