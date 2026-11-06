<?php

namespace Tests\Feature;

use App\Models\ProcurementDocument;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Services\SignatureRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfficeWorkflowAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_login_has_no_duplicate_workflow_roles_or_seeded_accounts(): void
    {
        $response = $this->get(route('login'))->assertOk();
        foreach (User::officeWorkflowRoleNames() as $role) {
            $response->assertDontSee('value="'.$role.'"', false);
        }
        foreach (array_keys(User::RETIRED_ACCOUNT_REPLACEMENTS) as $code) {
            $this->assertDatabaseMissing('users', ['user_id' => $code, 'status' => User::STATUS_ACTIVE]);
        }
    }

    public function test_office_accounts_keep_their_own_work_and_gain_only_their_matching_duties(): void
    {
        foreach (User::OFFICE_WORKFLOW_ROLES as $code => $duty) {
            $user = User::where('user_id', $code)->firstOrFail();
            $this->assertSame('head-office', $user->roleSlug());
            $this->assertTrue($user->hasRole(User::ROLE_HEAD_OFFICE));
            $this->assertTrue($user->hasRole($duty['code']));
            $this->assertTrue($user->hasPermission('esignature.use'));
            $this->assertSame($user->id, User::officeWorkflowRecipient($duty['name'])->id);
            foreach (User::OFFICE_WORKFLOW_ROLES as $otherCode => $otherDuty) {
                $this->assertSame($code === $otherCode, $user->hasRole($otherDuty['name']));
            }
        }

        $ordinary = User::where('user_id', 'MCR-001')->firstOrFail();
        $this->assertNull($ordinary->officeWorkflowRole());
        $this->assertFalse($ordinary->hasPermission('review.budget'));
        $this->assertFalse($ordinary->hasPermission('documents.approve'));
    }

    public function test_office_accounts_can_open_review_pages_and_ordinary_offices_cannot(): void
    {
        foreach (['MBO-001' => 'budget.pending-review.index', 'ACC-001' => 'accounting.pending-review.index', 'MO-001' => 'approving-authority.pending.index'] as $code => $route) {
            $user = User::where('user_id', $code)->firstOrFail();
            $this->actingAs($user)->get(route($route))->assertOk()->assertSee('My Documents');
        }
        $ordinary = User::where('user_id', 'MCR-001')->firstOrFail();
        $this->actingAs($ordinary)->get(route('budget.pending-review.index'))->assertRedirect();
        $this->actingAs($ordinary)->get(route('approving-authority.pending.index'))->assertRedirect();
    }

    public function test_role_only_signatures_are_visible_only_to_the_matching_office(): void
    {
        $service = app(SignatureRequestService::class);
        foreach (User::OFFICE_WORKFLOW_ROLES as $code => $duty) {
            $request = $this->signature(['requested_to_role' => $duty['code']]);
            foreach (User::OFFICE_WORKFLOW_ROLES as $candidateCode => $candidateDuty) {
                $candidate = User::where('user_id', $candidateCode)->firstOrFail();
                $this->assertSame($code === $candidateCode, $service->userCanAccess($request, $candidate));
                $this->assertSame($code === $candidateCode, $service->requestsForUser($candidate)->whereKey($request->id)->exists());
            }
        }
    }

    public function test_cleanup_moves_pending_work_but_preserves_signed_history(): void
    {
        foreach (User::RETIRED_ACCOUNT_REPLACEMENTS as $oldCode => $officeCode) {
            $office = User::where('user_id', $officeCode)->firstOrFail();
            $old = User::factory()->create(['user_id' => $oldCode, 'role' => User::OFFICE_WORKFLOW_ROLES[$officeCode]['name'], 'status' => User::STATUS_ACTIVE]);
            $pending = $this->signature(['requested_to_user_id' => $old->id]);
            $waiting = $this->signature(['requested_to_user_id' => $old->id, 'status' => SignatureRequest::STATUS_WAITING]);
            $signed = $this->signature(['requested_to_user_id' => $old->id, 'status' => SignatureRequest::STATUS_SIGNED, 'signed_at' => now()]);
            $document = ProcurementDocument::withoutEvents(fn () => ProcurementDocument::create([
                'document_type' => 'PR', 'title' => 'Pending office review', 'fiscal_year' => 2026,
                'status' => ProcurementDocument::STATUS_PENDING_APPROVAL, 'assigned_to_user_id' => $old->id,
            ]));

            (require database_path('migrations/2026_11_06_000001_consolidate_office_workflow_accounts.php'))->up();

            $this->assertFalse($old->fresh()->isActive());
            $this->assertSame($office->id, $pending->fresh()->requested_to_user_id);
            $this->assertSame($office->id, $waiting->fresh()->requested_to_user_id);
            $this->assertSame($old->id, $signed->fresh()->requested_to_user_id);
            $this->assertNotNull($signed->fresh()->signed_at);
            $this->assertSame($office->id, $document->fresh()->assigned_to_user_id);
        }
    }

    private function signature(array $attributes = []): SignatureRequest
    {
        return SignatureRequest::create(array_merge([
            'request_uuid' => (string) Str::uuid(), 'document_type' => 'app', 'document_id' => 999,
            'signatory_slot' => 'approved_by', 'status' => SignatureRequest::STATUS_PENDING,
        ], $attributes));
    }
}
