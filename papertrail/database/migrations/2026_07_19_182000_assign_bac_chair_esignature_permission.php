<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $permission = DB::table('permissions')->where('key', 'esignature.use')->first();

        if ($permission) {
            DB::table('permissions')
                ->where('id', $permission->id)
                ->update([
                    'name' => 'Esignature Use',
                    'group' => 'Electronic Signature',
                    'description' => null,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('permissions')->insert([
                'key' => 'esignature.use',
                'name' => 'Esignature Use',
                'group' => 'Electronic Signature',
                'description' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permissionId = DB::table('permissions')->where('key', 'esignature.use')->value('id');
        $roleId = DB::table('roles')->where('code', 'bac_chair')->value('id');

        if (! $roleId || ! $permissionId) {
            return;
        }

        DB::table('permission_role')->updateOrInsert(
            ['role_id' => $roleId, 'permission_id' => $permissionId],
            ['updated_at' => now(), 'created_at' => now()],
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('key', 'esignature.use')->value('id');
        $roleId = DB::table('roles')->where('code', 'bac_chair')->value('id');

        if ($roleId && $permissionId) {
            DB::table('permission_role')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->delete();
        }
    }
};
