<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $permissionKeys = [
        'dashboard.view',
        'profile.view',
        'profile.update',
        'notifications.view',
        'documents.view.own',
        'documents.track',
        'documents.submit',
        'documents.edit.own',
        'documents.upload',
        'documents.comment',
        'ppmp.submit',
        'ppmp.view',
        'pr.submit',
        'pr.view',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $now = now();
        $payload = [
            'name' => 'Office Document Preparer',
            'description' => 'Prepares and submits office procurement documents for Head of Office approval.',
            'dashboard_route' => 'head-office.dashboard',
            'status' => 'active',
            'is_system' => true,
            'updated_at' => $now,
        ];

        DB::table('roles')->updateOrInsert(
            ['code' => 'office_preparer'],
            [...$payload, 'created_at' => $now],
        );

        if (! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $roleId = DB::table('roles')->where('code', 'office_preparer')->value('id');
        $permissionIds = DB::table('permissions')->whereIn('key', $this->permissionKeys)->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $roleId = DB::table('roles')->where('code', 'office_preparer')->value('id');

        if (! $roleId) {
            return;
        }

        if (Schema::hasTable('users') && DB::table('users')->where('role_id', $roleId)->exists()) {
            return;
        }

        if (Schema::hasTable('permission_role')) {
            DB::table('permission_role')->where('role_id', $roleId)->delete();
        }

        DB::table('roles')->where('id', $roleId)->delete();
    }
};
