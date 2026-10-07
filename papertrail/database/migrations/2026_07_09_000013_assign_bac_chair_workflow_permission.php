<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions') || !Schema::hasTable('permission_role')) {
            return;
        }

        $roleId = DB::table('roles')->where('code', 'bac_chair')->value('id');
        $permissionId = DB::table('permissions')->where('key', 'workflow.update_status')->value('id');

        if (!$roleId || !$permissionId) {
            return;
        }

        $exists = DB::table('permission_role')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->exists();

        if (!$exists) {
            DB::table('permission_role')->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Keep role permissions intact on rollback to avoid removing an administrator-assigned permission.
    }
};
