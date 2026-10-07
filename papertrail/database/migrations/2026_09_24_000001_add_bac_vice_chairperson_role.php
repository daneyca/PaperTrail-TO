<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $bacChairPermissionKeys = [
        'dashboard.view',
        'profile.view',
        'profile.update',
        'notifications.view',
        'documents.view.assigned',
        'documents.track',
        'documents.comment',
        'documents.return',
        'review.bac',
        'workflow.update_status',
        'workflow.view_history',
        'ppmp.review',
        'app.approve',
        'pr.review',
        'bac.resolution.view',
        'bac.resolution.review',
        'bac.resolution.confirm',
        'bac.resolution.return',
        'bac.resolution.forward_to_hope',
        'esignature.use',
        'reports.procurement',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $this->deletePresidentSampleRole();

        $now = now();

        $rolePayload = [
            'name' => 'BAC Vice Chairperson',
            'description' => 'Reviews and signs BAC documents using the BAC Chair workspace.',
            'dashboard_route' => 'bac-chair.dashboard',
            'status' => 'active',
            'is_system' => true,
            'updated_at' => $now,
        ];

        if (DB::table('roles')->where('code', 'bac_vice_chairperson')->exists()) {
            DB::table('roles')->where('code', 'bac_vice_chairperson')->update($rolePayload);
        } else {
            DB::table('roles')->insert([
                'code' => 'bac_vice_chairperson',
                ...$rolePayload,
                'created_at' => $now,
            ]);
        }

        $roleId = DB::table('roles')->where('code', 'bac_vice_chairperson')->value('id');

        if ($roleId && Schema::hasTable('permissions') && Schema::hasTable('permission_role')) {
            $permissionIds = DB::table('permissions')
                ->whereIn('key', $this->bacChairPermissionKeys)
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionId],
                    ['updated_at' => $now, 'created_at' => $now],
                );
            }
        }

        $this->upsertViceChairUser((int) $roleId);
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $roleId = DB::table('roles')->where('code', 'bac_vice_chairperson')->value('id');

        if ($roleId && Schema::hasTable('permission_role')) {
            DB::table('permission_role')->where('role_id', $roleId)->delete();
        }

        if (Schema::hasTable('users')) {
            DB::table('users')->where('user_id', 'BACVICE-001')->delete();
        }

        DB::table('roles')->where('code', 'bac_vice_chairperson')->delete();

        $presidentPayload = [
            'name' => 'President of the Philippines',
            'description' => null,
            'dashboard_route' => 'admin/president',
            'status' => 'active',
            'is_system' => true,
            'updated_at' => now(),
        ];

        if (DB::table('roles')->where('code', '099')->exists()) {
            DB::table('roles')->where('code', '099')->update($presidentPayload);
        } else {
            DB::table('roles')->insert([
                'code' => '099',
                ...$presidentPayload,
                'created_at' => now(),
            ]);
        }
    }

    private function deletePresidentSampleRole(): void
    {
        $role = DB::table('roles')
            ->where('code', '099')
            ->orWhere('name', 'President of the Philippines')
            ->first();

        if (! $role) {
            return;
        }

        if (Schema::hasTable('permission_role')) {
            DB::table('permission_role')->where('role_id', $role->id)->delete();
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role_id')) {
            DB::table('users')->where('role_id', $role->id)->update(['role_id' => null]);
        }

        DB::table('roles')->where('id', $role->id)->delete();
    }

    private function upsertViceChairUser(int $roleId): void
    {
        if (! $roleId || ! Schema::hasTable('users')) {
            return;
        }

        $office = Schema::hasTable('offices')
            ? DB::table('offices')->where('code', 'BAC')->first()
            : null;

        $payload = [
            'name' => 'BAC Vice Chairperson',
            'office' => $office?->name,
            'role' => 'BAC Vice Chairperson',
            'status' => 'active',
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('users', 'office_id')) {
            $payload['office_id'] = $office?->id;
        }

        if (Schema::hasColumn('users', 'role_id')) {
            $payload['role_id'] = $roleId;
        }

        $existing = DB::table('users')->where('user_id', 'BACVICE-001')->first();

        if ($existing) {
            DB::table('users')->where('id', $existing->id)->update($payload);

            return;
        }

        DB::table('users')->insert([
            'user_id' => 'BACVICE-001',
            ...$payload,
            'password' => Hash::make('password123'),
            'created_at' => now(),
        ]);
    }
};
