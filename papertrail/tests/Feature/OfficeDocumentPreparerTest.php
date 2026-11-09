<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\Role;
use App\Models\User;
use App\Services\EndUserPrSignatoryRoutingService;
use App\Services\HeadOfficePpmpSignatureWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OfficeDocumentPreparerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_preparer_with_a_temporary_password(): void
    {
        $adminRole = $this->role('admin', User::ROLE_ADMIN, 'admin.dashboard');
        $preparerRole = $this->role('office_preparer', User::ROLE_OFFICE_PREPARER, 'head-office.dashboard');
        $office = $this->office();
        $admin = User::factory()->create([
            'user_id' => 'ADMIN-PREPARER-TEST',
            'role' => User::ROLE_ADMIN,
            'role_id' => $adminRole->id,
            'status' => User::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'user_id' => 'mpdo-staff-001',
            'name' => 'MPDO Staff Member',
            'email' => 'mpdo.staff@example.test',
            'contact_number' => '09123456789',
            'position' => 'Administrative Aide',
            'office_id' => $office->id,
            'role_id' => $preparerRole->id,
            'password' => 'Temporary123!',
            'status' => User::STATUS_ACTIVE,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'user_id' => 'MPDO-STAFF-001',
            'role' => User::ROLE_OFFICE_PREPARER,
            'office_id' => $office->id,
            'must_change_password' => true,
        ]);
    }

    public function test_admin_cannot_assign_procurement_staff_to_an_office_managed_by_its_head(): void
    {
        $adminRole = $this->role('admin', User::ROLE_ADMIN, 'admin.dashboard');
        $preparerRole = $this->role('office_preparer', User::ROLE_OFFICE_PREPARER, 'head-office.dashboard');
        $office = Office::create([
            'code' => 'MCR',
            'name' => 'Municipal Civil Registrar',
            'type' => Office::TYPE_END_USER,
            'status' => Office::STATUS_ACTIVE,
            'is_requesting_office' => true,
        ]);
        $admin = User::factory()->create([
            'user_id' => 'ADMIN-PREPARER-RESTRICTED',
            'role' => User::ROLE_ADMIN,
            'role_id' => $adminRole->id,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'user_id' => 'MCR-STAFF',
            'name' => 'Unused MCR Staff',
            'office_id' => $office->id,
            'role_id' => $preparerRole->id,
            'password' => 'Temporary123!',
            'status' => User::STATUS_ACTIVE,
        ])->assertSessionHasErrors('office_id');

        $this->assertDatabaseMissing('users', ['user_id' => 'MCR-STAFF']);
    }

    public function test_admin_accounts_are_sorted_by_office_then_employee_name(): void
    {
        $adminRole = $this->role('admin', User::ROLE_ADMIN, 'admin.dashboard');
        $headRole = $this->role('head_office', User::ROLE_HEAD_OFFICE, 'head-office.dashboard');
        $admin = User::factory()->create([
            'user_id' => 'ADMIN-SORT-TEST',
            'role' => User::ROLE_ADMIN,
            'role_id' => $adminRole->id,
            'status' => User::STATUS_ACTIVE,
        ]);

        foreach (['Zoning Office', 'Assessors Office', 'Municipal Engineering Office'] as $index => $officeName) {
            $office = Office::create([
                'code' => 'SORT-' . ($index + 1),
                'name' => $officeName,
                'type' => Office::TYPE_END_USER,
                'status' => Office::STATUS_ACTIVE,
                'is_requesting_office' => true,
            ]);

            $this->user('SORT-USER-' . ($index + 1), $office, $headRole)->update([
                'name' => 'Office Sort Test ' . ($index + 1),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'Office Sort Test']))
            ->assertOk()
            ->assertViewHas('users', fn ($users) => $users->pluck('office')->all() === [
                'Assessors Office',
                'Municipal Engineering Office',
                'Zoning Office',
            ]);
    }

    public function test_preparer_ppmp_is_sent_to_the_head_of_the_same_office(): void
    {
        $office = $this->office();
        $preparer = $this->user('MPDO-STAFF-001', $office, $this->role('office_preparer', User::ROLE_OFFICE_PREPARER, 'head-office.dashboard'));
        $head = $this->user('MPDO-001', $office, $this->role('head_office', User::ROLE_HEAD_OFFICE, 'head-office.dashboard'));
        $document = $this->ppmp($office, $preparer);

        $requests = app(HeadOfficePpmpSignatureWorkflowService::class)->start($document, $preparer);

        $this->assertCount(2, $requests);
        $bySlot = $requests->keyBy('signatory_slot');
        $this->assertSame($preparer->id, $bySlot['prepared_by']->requested_to_user_id);
        $this->assertSame($head->id, $bySlot['submitted_by']->requested_to_user_id);
        $this->assertSame($preparer->id, $bySlot['submitted_by']->requested_by_user_id);
        $this->assertSame('waiting', $bySlot['submitted_by']->status);
        $this->assertDatabaseHas('procurement_documents', [
            'id' => $document->id,
            'status' => ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES,
            'assigned_to_user_id' => $preparer->id,
        ]);
    }

    public function test_head_created_ppmp_keeps_the_existing_self_signing_flow(): void
    {
        $office = $this->office();
        $head = $this->user('MPDO-001', $office, $this->role('head_office', User::ROLE_HEAD_OFFICE, 'head-office.dashboard'));
        $document = $this->ppmp($office, $head);

        $requests = app(HeadOfficePpmpSignatureWorkflowService::class)->start($document, $head);

        $this->assertCount(1, $requests);
        $this->assertSame($head->id, $requests->first()->requested_to_user_id);
        $this->assertSame('Prepared and Submitted By', $requests->first()->signatory_label);
    }

    public function test_preparer_purchase_request_uses_the_same_office_head_as_signer(): void
    {
        $office = $this->office();
        $preparer = $this->user('MPDO-STAFF-001', $office, $this->role('office_preparer', User::ROLE_OFFICE_PREPARER, 'head-office.dashboard'));
        $head = $this->user('MPDO-001', $office, $this->role('head_office', User::ROLE_HEAD_OFFICE, 'head-office.dashboard'));
        $document = $this->purchaseRequest($office, $preparer);
        $routing = app(EndUserPrSignatoryRoutingService::class);

        $this->assertTrue($routing->shouldUseForUser($preparer));
        $this->assertSame($head->id, $routing->signerForDesignation('Head of Office', $document)?->id);
    }

    public function test_preparer_cannot_submit_a_ppmp_when_the_office_has_no_active_head(): void
    {
        $office = $this->office();
        $preparer = $this->user('MPDO-STAFF-001', $office, $this->role('office_preparer', User::ROLE_OFFICE_PREPARER, 'head-office.dashboard'));
        $document = $this->ppmp($office, $preparer);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No active Head of Office account is configured for this office.');

        app(HeadOfficePpmpSignatureWorkflowService::class)->start($document, $preparer);
    }

    public function test_preparer_can_manage_the_rfq_workflow(): void
    {
        $office = $this->office();
        $preparer = $this->user('MPDO-STAFF-001', $office, $this->role('office_preparer', User::ROLE_OFFICE_PREPARER, 'head-office.dashboard'));

        $this->actingAs($preparer)
            ->get(route('head-office.rfqs.index'))
            ->assertOk();
    }

    public function test_preparer_cannot_switch_to_a_legacy_linked_workspace(): void
    {
        $office = $this->office();
        $preparer = $this->user('MPDO-STAFF-001', $office, $this->role('office_preparer', User::ROLE_OFFICE_PREPARER, 'head-office.dashboard'));
        $linkedWorkspace = $this->user('BACMEM-LEGACY', $office, $this->role('bac_member', User::ROLE_BAC_MEMBER, 'bac-member.dashboard'));
        $linkedWorkspace->forceFill(['principal_user_id' => $preparer->id])->save();

        $this->assertCount(1, $preparer->availableWorkspaces());
        $this->assertTrue($preparer->availableWorkspaces()->first()->is($preparer));
    }

    private function role(string $code, string $name, string $dashboardRoute): Role
    {
        return Role::updateOrCreate(['code' => $code], [
            'name' => $name,
            'description' => $name,
            'dashboard_route' => $dashboardRoute,
            'status' => Role::STATUS_ACTIVE,
            'is_system' => true,
        ]);
    }

    private function office(): Office
    {
        return Office::create([
            'code' => 'MPDO-TEST-' . fake()->unique()->numberBetween(100, 999),
            'name' => 'Municipal Planning Test Office ' . fake()->unique()->numberBetween(100, 999),
            'type' => Office::TYPE_END_USER,
            'status' => Office::STATUS_ACTIVE,
            'is_requesting_office' => true,
        ]);
    }

    private function user(string $userId, Office $office, Role $role): User
    {
        return User::factory()->create([
            'user_id' => $userId,
            'role' => $role->name,
            'role_id' => $role->id,
            'office' => $office->name,
            'office_id' => $office->id,
            'position' => $role->name,
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);
    }

    private function ppmp(Office $office, User $creator): ProcurementDocument
    {
        $id = DB::table('procurement_documents')->insertGetId([
            'tracking_number' => 'PPMP-PREPARER-' . fake()->unique()->numberBetween(1000, 9999),
            'document_type' => 'PPMP',
            'title' => 'Office PPMP',
            'fiscal_year' => 2027,
            'status' => ProcurementDocument::STATUS_PPMP_DRAFT,
            'stage' => ProcurementDocument::STAGE_PPMP_PREPARATION,
            'submitting_office_id' => $office->id,
            'current_office_id' => $office->id,
            'submitted_by_user_id' => $creator->id,
            'prepared_by_user_id' => $creator->id,
            'assigned_to_user_id' => $creator->id,
            'total_amount' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ProcurementDocument::findOrFail($id);
    }

    private function purchaseRequest(Office $office, User $creator): ProcurementDocument
    {
        $id = DB::table('procurement_documents')->insertGetId([
            'tracking_number' => 'PR-PREPARER-' . fake()->unique()->numberBetween(1000, 9999),
            'document_type' => 'PR',
            'title' => 'Office Purchase Request',
            'fiscal_year' => 2027,
            'status' => ProcurementDocument::STATUS_PR_DRAFT,
            'submitting_office_id' => $office->id,
            'current_office_id' => $office->id,
            'submitted_by_user_id' => $creator->id,
            'prepared_by_user_id' => $creator->id,
            'assigned_to_user_id' => $creator->id,
            'total_amount' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ProcurementDocument::findOrFail($id);
    }
}
