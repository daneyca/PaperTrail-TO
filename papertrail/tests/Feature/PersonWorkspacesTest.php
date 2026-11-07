<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\AnnualProcurementPlan;
use App\Models\BacResolution;
use App\Models\ElectronicSignature;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Services\ElectronicSignatureService;
use App\Services\PersonWorkspaceService;
use App\Services\SignatureRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PersonWorkspacesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function user(string $code): User
    {
        return User::where('user_id', $code)->firstOrFail();
    }

    private function linkAccounts(): void
    {
        app(PersonWorkspaceService::class)->linkInitialAccounts();
    }

    public function test_six_pairs_have_one_login_and_separate_permissions(): void
    {
        $this->linkAccounts();
        $this->linkAccounts();
        foreach (PersonWorkspaceService::INITIAL_ASSIGNMENTS as [$officeCode, $bacCode, $name, $role]) {
            $office = $this->user($officeCode);
            $bac = $this->user($bacCode);
            $this->assertSame($office->id, $bac->loginAccount()->id);
            $this->assertSame($name, $bac->name);
            $this->assertCount(2, $office->availableWorkspaces());
            $this->assertTrue($bac->hasRole($role));
            $this->assertFalse($office->hasRole($role));
            $this->assertFalse($bac->hasRole(User::ROLE_HEAD_OFFICE));
            $this->assertFalse(Hash::check('password123', $bac->password));
        }
        $this->assertCount(1, $this->user('BACSEC-003')->availableWorkspaces());
    }

    public function test_switch_uses_only_owned_workspaces_and_persists_without_changing_login(): void
    {
        $this->linkAccounts();
        $account = $this->user('MPDO-001');
        $chair = $this->user('BACCHAIR-001');
        $this->actingAs($account)->withSession(['_token' => 'old-token'])
            ->post(route('workspaces.switch'), ['workspace_id' => $chair->id])
            ->assertRedirect(route($chair->dashboardRoute()))->assertSessionHas('active_workspace_id', $chair->id);
        $this->assertNotSame('old-token', session()->token());
        $this->assertAuthenticatedAs($account);
        $this->get(route('bac-chair.dashboard'))->assertOk()->assertSee('Switch Workspace')->assertSee('BACCHAIR-001');
        $this->get(route('head-office.dashboard'))->assertRedirect(route('dashboard'));
        $this->post(route('workspaces.switch'), ['workspace_id' => $this->user('BACMEM-001')->id])->assertForbidden();
        $this->post(route('workspaces.switch'), ['workspace_id' => $account->id])->assertRedirect(route('head-office.dashboard'));
        $this->get(route('head-office.dashboard'))->assertOk();
    }

    public function test_chairperson_label_does_not_change_the_routing_role(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertDontSee('value="'.User::ROLE_BAC_CHAIR.'"', false)
            ->assertDontSee('BAC Chairman');
        $this->linkAccounts();
        $account = $this->user('MPDO-001');
        $chair = $this->user('BACCHAIR-001');
        $chair->update(['position' => 'BAC Chairman']);

        $this->assertSame('BAC Chairperson', $chair->workspaceLabel());
        $this->assertSame('BAC Chair', $chair->role);
        $this->assertTrue($chair->hasRole(User::ROLE_BAC_CHAIR));
        $this->actingAs($account)->withSession(['active_workspace_id' => $chair->id])
            ->get(route('bac-chair.dashboard'))->assertOk()
            ->assertSee('BAC Chairperson Dashboard')
            ->assertSee('BAC Chairperson Review')
            ->assertSee('BAC Chairperson<small>BACCHAIR-001', false)
            ->assertSee('class="pt-user-profile-block__role">BAC Chairperson</span>', false)
            ->assertDontSee('BAC Chairman');
        $this->blade('<x-user-profile-block :user="$user" />', ['user' => $chair])
            ->assertSee('BAC Chairperson')->assertDontSee('BAC Chairman');
        $this->assertNotSame('BAC Chairperson', $this->user('BACVICE-001')->workspaceLabel());
    }

    public function test_single_role_user_cannot_select_another_users_workspace(): void
    {
        $this->linkAccounts();
        $this->actingAs($this->user('BACSEC-003'))->post(route('workspaces.switch'), [
            'workspace_id' => $this->user('BACSEC-002')->id,
        ])->assertForbidden();
        $this->get(route('bac-secretariat.dashboard'))->assertOk()->assertDontSee('Switch Workspace');
    }

    public function test_main_office_credentials_work_but_linked_account_cannot_log_in(): void
    {
        $this->linkAccounts();
        $account = $this->user('MPDO-001');
        $this->withSession(['login_captcha_hash' => Hash::make('ABCDE')])->post(route('login.store'), [
            'user_id' => $account->user_id, 'role' => $account->role, 'office' => (string) $account->office_id,
            'password' => 'password123', 'security_code' => 'ABCDE',
        ])->assertSessionHasNoErrors()->assertRedirect(route('head-office.dashboard'));
        $this->assertAuthenticatedAs($account);
        $this->post(route('logout'))->assertRedirect();
        $this->assertGuest();
        $this->withSession(['login_captcha_hash' => Hash::make('ABCDE')])->post(route('login.store'), [
            'user_id' => 'BACCHAIR-001', 'role' => User::ROLE_BAC_CHAIR,
            'password' => 'password123', 'security_code' => 'ABCDE',
        ])->assertSessionHasErrors('role');
        $this->assertGuest();
    }

    public function test_old_linked_login_session_is_invalidated_not_promoted_to_personal_account(): void
    {
        $this->linkAccounts();
        $this->actingAs($this->user('BACCHAIR-001'))->get(route('bac-chair.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_disabled_principal_cannot_use_active_bac_workspace(): void
    {
        $this->linkAccounts();
        $account = $this->user('MPDO-001');
        $account->update(['status' => User::STATUS_INACTIVE]);
        $this->actingAs($account)->withSession(['active_workspace_id' => $this->user('BACCHAIR-001')->id])
            ->get(route('bac-chair.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_revoked_workspace_cannot_be_used_by_an_existing_session(): void
    {
        $this->linkAccounts();
        $chair = $this->user('BACCHAIR-001');
        $chair->update(['status' => User::STATUS_INACTIVE]);
        $this->actingAs($this->user('MPDO-001'))->withSession(['active_workspace_id' => $chair->id])
            ->patch(route('profile.update'), ['name' => 'Wrong role'])->assertStatus(409);
        $this->post(route('workspaces.switch'), ['workspace_id' => $chair->id])->assertForbidden();
    }

    public function test_profile_password_and_signature_setup_belong_to_main_login(): void
    {
        $this->linkAccounts();
        $account = $this->user('MPDO-001');
        $chair = $this->user('BACCHAIR-001');
        $this->actingAs($account)->withSession(['active_workspace_id' => $chair->id])
            ->patch(route('profile.password.update'), [
                'current_password' => 'password123', 'password' => 'changed-password-42',
                'password_confirmation' => 'changed-password-42',
            ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('changed-password-42', $account->fresh()->password));
        $this->assertFalse(Hash::check('changed-password-42', $chair->fresh()->password));
        $this->post(route('profile.signature.update'), [
            'typed_signature_name' => 'Edmar Peter T. Tambis', 'signer_position' => 'MPDO Head', 'signature_style' => 'typed',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Edmar Peter T. Tambis', $account->fresh()->typed_signature_name);
        $this->assertTrue(app(ElectronicSignatureService::class)->ensureSignatureProfile($chair->fresh()));
        $this->get(route('profile.show'))->assertOk()->assertSee('MPDO-001');
    }

    public function test_link_keeps_pending_and_signed_requests_and_scopes_them_to_active_role(): void
    {
        $chair = $this->user('BACCHAIR-001');
        $pending = $this->signature($chair);
        $signed = $this->signature($chair, SignatureRequest::STATUS_SIGNED);
        $pending->update(['signatory_label' => 'Recommended By']);
        $signed->update(['signatory_label' => 'Recommended By']);
        $before = $signed->fresh()->getAttributes();
        $this->linkAccounts();
        $this->assertSame($chair->id, $pending->fresh()->requested_to_user_id);
        $this->assertSame($before, $signed->fresh()->getAttributes());
        $service = app(SignatureRequestService::class);
        $this->assertTrue($service->userCanAccess($pending, $chair->fresh()));
        $this->assertFalse($service->userCanAccess($pending, $this->user('MPDO-001')));
        $this->assertFalse($service->userCanAccess($pending, $this->user('BACMEM-001')));
        $this->assertTrue($service->requestsForUser($chair->fresh())->whereKey($pending->id)->exists());
        foreach (['pending', 'signed'] as $status) {
            $this->actingAs($this->user('MPDO-001'))->withSession(['active_workspace_id' => $chair->id])
                ->get(route('signature-requests.index', ['status' => $status, 'document_type' => 'app']))
                ->assertOk()
                ->assertSee('<th>Signing As</th>', false)
                ->assertSee('<span>APP</span>', false)
                ->assertSee('Recommending Approval')
                ->assertDontSee('<th>Signatory Role</th>', false)
                ->assertDontSee('<span>App</span>', false)
                ->assertDontSee('Recommended By');
        }
    }

    public function test_admin_sees_one_person_with_both_roles_and_can_revoke_access(): void
    {
        $this->linkAccounts();
        $account = $this->user('MPDO-001');
        $chair = $this->user('BACCHAIR-001');
        $this->actingAs($this->user('ADMIN-001'))->get(route('admin.users.index', ['search' => 'Edmar']))
            ->assertOk()->assertViewHas('users', fn ($users) => $users->total() === 1);
        $this->get(route('admin.users.edit', $account))->assertOk()->assertSee('Linked Workspaces')->assertSee('BACCHAIR-001');
        $this->patch(route('admin.users.workspaces.status', [$account, $chair]), ['status' => 'inactive'])->assertRedirect();
        $this->assertSame(User::STATUS_INACTIVE, $chair->fresh()->status);
        $this->assertSame($account->id, $chair->fresh()->principal_user_id);
    }

    public function test_only_admin_can_link_and_confirmation_is_required(): void
    {
        $account = $this->user('MPDO-001');
        $chair = $this->user('BACCHAIR-001');
        $this->actingAs($account)->post(route('admin.users.workspaces.store', $account), [
            'workspace_id' => $chair->id, 'same_person' => '1',
        ])->assertRedirect(route('dashboard'));
        $this->assertNull($chair->fresh()->principal_user_id);
        $this->actingAs($this->user('ADMIN-001'))->post(route('admin.users.workspaces.store', $account), [
            'workspace_id' => $chair->id,
        ])->assertSessionHasErrors('same_person');
        $this->post(route('admin.users.workspaces.store', $account), [
            'workspace_id' => $chair->id, 'same_person' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame($account->id, $chair->fresh()->principal_user_id);
    }

    public function test_link_cannot_steal_assigned_roles_or_create_cycles(): void
    {
        $this->linkAccounts();
        $this->expectException(ValidationException::class);
        app(PersonWorkspaceService::class)->link($this->user('MEO-001'), $this->user('BACCHAIR-001'));
    }

    public function test_shared_email_does_not_overwrite_existing_primary_email(): void
    {
        $account = $this->user('MPDO-001');
        $chair = $this->user('BACCHAIR-001');
        $account->update(['email' => 'person@example.test']);
        $chair->update(['email' => 'old-role@example.test']);
        $this->linkAccounts();
        $this->assertSame('person@example.test', $chair->fresh()->email);
        $this->assertSame('old-role@example.test', $chair->fresh()->getRawOriginal('email'));
    }

    public function test_email_moves_to_empty_primary_account_and_audit_records_actor_and_role(): void
    {
        $chair = $this->user('BACCHAIR-001');
        $chair->update(['email' => 'verified@example.test', 'email_verified_at' => now()]);
        $this->linkAccounts();
        $account = $this->user('MPDO-001');
        $this->assertSame('verified@example.test', $account->email);
        $this->assertTrue($account->hasVerifiedEmail());
        $this->actingAs($account)->withSession(['active_workspace_id' => $chair->id])->get(route('profile.show'))->assertOk();
        $audit = AuditLog::where('user_id', $chair->id)->latest('id')->firstOrFail();
        $this->assertSame($account->id, $audit->metadata['login_account_id']);
        $this->assertSame($chair->id, $audit->metadata['workspace_id']);
    }

    private function signature(User $user, string $status = SignatureRequest::STATUS_PENDING): SignatureRequest
    {
        return SignatureRequest::create([
            'request_uuid' => (string) Str::uuid(), 'document_type' => 'app', 'document_id' => 999,
            'signatory_slot' => 'recommended_by', 'requested_to_user_id' => $user->id,
            'status' => $status, 'signed_at' => $status === SignatureRequest::STATUS_SIGNED ? now() : null,
        ]);
    }

    public function test_signing_uses_personal_password_and_signature_but_records_only_the_selected_role(): void
    {
        $account = $this->user('MPDO-001');
        $account->update([
            'email' => 'edmar@example.test', 'password' => Hash::make('personal-secret-42'),
            'typed_signature_name' => 'Edmar Peter T. Tambis', 'signer_position' => 'MPDO Head',
        ]);
        $this->linkAccounts();
        $chair = $this->user('BACCHAIR-001');
        $document = AnnualProcurementPlan::create([
            'app_number' => 'APP-WORKSPACE-TEST', 'title' => 'Workspace signing test', 'fiscal_year' => 2026,
            'status' => AnnualProcurementPlan::STATUS_SUBMITTED, 'prepared_by_user_id' => $account->id,
        ]);
        $request = $this->signature($chair);
        $request->update([
            'document_id' => $document->id,
            'document_label' => 'APP ' . $document->displayNumber(),
            'requested_to_role' => User::ROLE_BAC_CHAIR,
        ]);
        $other = $this->signature($account);
        $other->update(['document_id' => $document->id, 'signatory_slot' => 'prepared_by']);
        $this->actingAs($account)->withSession(['active_workspace_id' => $chair->id])
            ->get(route('signature-requests.index'))
            ->assertOk()
            ->assertSee($document->displayNumber())
            ->assertDontSee('APP ' . $document->displayNumber());

        $this->actingAs($account)->withSession(['active_workspace_id' => $chair->id])
            ->get(route('signature-requests.show', $request))
            ->assertOk()
            ->assertSee('Account Password')
            ->assertDontSee('Send Code')
            ->assertDontSee('6-Digit Code');

        $this->actingAs($account)->withSession(['active_workspace_id' => $chair->id])
            ->post(route('signature-requests.sign', $request), [
                'signature_consent' => '1',
                'current_password' => 'wrong-old-password',
            ])
            ->assertSessionHas('error', 'The current password is incorrect.');

        $this->actingAs($account)->withSession(['active_workspace_id' => $chair->id])
            ->post(route('signature-requests.sign', $request), [
                'signature_consent' => '1',
                'current_password' => 'personal-secret-42',
            ])
            ->assertSessionHas('status');

        $signature = ElectronicSignature::where('signature_request_id', $request->id)->firstOrFail();
        $this->assertSame($chair->id, $signature->signer_user_id);
        $this->assertSame(User::ROLE_BAC_CHAIR, $signature->signer_role);
        $this->assertSame($account->id, $signature->metadata['login_account_id']);
        $this->assertSame('account_password', $signature->metadata['authentication_method']);
        $this->assertNull($signature->signing_code_hash);
        $this->assertSame(SignatureRequest::STATUS_PENDING, $other->fresh()->status);
    }

    public function test_pending_bac_signer_can_sign_when_resolution_is_already_marked_confirmed(): void
    {
        $this->linkAccounts();
        $viceChair = $this->user('BACVICE-001');
        $resolution = BacResolution::create([
            'resolution_number' => 'BAC-WORKSPACE-TEST',
            'title' => 'Pending BAC signatory test',
            'prepared_by_user_id' => $viceChair->id,
            'status' => BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR,
            'signature_status' => 'pending_signatures',
        ]);
        $signatureRequest = SignatureRequest::create([
            'request_uuid' => (string) Str::uuid(),
            'document_type' => 'bac_resolution',
            'document_id' => $resolution->id,
            'signatory_slot' => 'bac_vice_chairperson',
            'signatory_label' => 'BAC Vice Chairperson',
            'requested_to_user_id' => $viceChair->id,
            'requested_to_role' => User::ROLE_BAC_VICE_CHAIRPERSON,
            'status' => SignatureRequest::STATUS_VIEWED,
            'is_required' => true,
        ]);

        $result = app(ElectronicSignatureService::class)->canSignWithReason(
            $viceChair,
            $resolution,
            'bac_resolution',
            'confirmed',
            $signatureRequest,
        );

        $this->assertTrue($result['ok']);
        $this->assertSame($signatureRequest->id, $result['signature_request']->id);

        $resolution->update(['status' => BacResolution::STATUS_FORWARDED_TO_HOPE]);

        $this->assertTrue(app(ElectronicSignatureService::class)->canSign(
            $viceChair,
            $resolution->fresh(),
            'bac_resolution',
            'confirmed',
            $signatureRequest,
        ));

        $resolution->update(['status' => BacResolution::STATUS_APPROVED_BY_HOPE]);

        $this->assertTrue(app(ElectronicSignatureService::class)->canSign(
            $viceChair,
            $resolution->fresh(),
            'bac_resolution',
            'confirmed',
            $signatureRequest,
        ));

        $resolution->update(['status' => BacResolution::STATUS_RETURNED_TO_END_USER]);

        $this->assertTrue(app(ElectronicSignatureService::class)->canSign(
            $viceChair,
            $resolution->fresh(),
            'bac_resolution',
            'confirmed',
            $signatureRequest,
        ));

        $resolution->update(['status' => BacResolution::STATUS_RETURNED_BY_BAC_CHAIR]);

        $this->assertFalse(app(ElectronicSignatureService::class)->canSign(
            $viceChair,
            $resolution->fresh(),
            'bac_resolution',
            'confirmed',
            $signatureRequest,
        ));
    }

    public function test_bac_resolution_approved_by_waits_for_all_attested_by_signatures(): void
    {
        $this->linkAccounts();
        $chair = $this->user('BACCHAIR-001');
        $viceChair = $this->user('BACVICE-001');
        $approver = $this->user('MO-001');
        $resolution = BacResolution::create([
            'resolution_number' => 'BAC-STAGED-SIGNATURE-TEST',
            'title' => 'Staged BAC Resolution signing test',
            'prepared_by_user_id' => $chair->id,
            'status' => BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR,
            'signature_status' => 'pending_signatures',
        ]);
        $requests = app(SignatureRequestService::class)->createRequestsForDocument(
            $resolution,
            'bac_resolution',
            [
                'signers' => [
                    [
                        'slot' => 'bac_chairperson',
                        'label' => 'BAC Chairperson',
                        'account_code' => $chair->user_id,
                        'required' => true,
                    ],
                    [
                        'slot' => 'bac_vice_chairperson',
                        'label' => 'BAC Vice Chairperson',
                        'account_code' => $viceChair->user_id,
                        'required' => true,
                    ],
                    [
                        'slot' => 'hope',
                        'label' => 'Approved By',
                        'account_code' => $approver->user_id,
                        'required' => true,
                        'initial_status' => SignatureRequest::STATUS_WAITING,
                    ],
                ],
            ],
            $chair,
        );
        $chairRequest = $requests->firstWhere('signatory_slot', 'bac_chairperson');
        $viceRequest = $requests->firstWhere('signatory_slot', 'bac_vice_chairperson');
        $approvalRequest = $requests->firstWhere('signatory_slot', 'hope');

        $this->assertSame(SignatureRequest::STATUS_WAITING, $approvalRequest->status);
        $this->assertNull($approvalRequest->notification_sent_at);

        $blocked = app(ElectronicSignatureService::class)->canSignWithReason(
            $approver,
            $resolution,
            'bac_resolution',
            'confirmed',
            $approvalRequest,
        );

        $this->assertFalse($blocked['ok']);
        $this->assertSame('All Attested By signatures must be completed before Approved By can sign.', $blocked['message']);

        $chairRequest->update(['status' => SignatureRequest::STATUS_SIGNED, 'signed_at' => now()]);
        app(SignatureRequestService::class)->markSigned(
            $viceRequest,
            new ElectronicSignature(['signed_at' => now(), 'metadata' => []]),
        );

        $this->assertContains($approvalRequest->fresh()->status, [
            SignatureRequest::STATUS_PENDING,
            SignatureRequest::STATUS_NOTIFIED,
        ]);
        $this->assertTrue(app(ElectronicSignatureService::class)->canSign(
            $approver,
            $resolution->fresh(),
            'bac_resolution',
            'confirmed',
            $approvalRequest->fresh(),
        ));
    }
}
