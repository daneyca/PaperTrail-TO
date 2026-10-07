<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bac_resolutions')) {
            Schema::table('bac_resolutions', function (Blueprint $table) {
                if (! Schema::hasColumn('bac_resolutions', 'bac_chair_confirmed_by_user_id')) {
                    $table->foreignId('bac_chair_confirmed_by_user_id')
                        ->nullable()
                        ->after('remarks')
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('bac_resolutions', 'bac_chair_confirmed_at')) {
                    $table->timestamp('bac_chair_confirmed_at')->nullable()->after('bac_chair_confirmed_by_user_id');
                }

                if (! Schema::hasColumn('bac_resolutions', 'bac_chair_remarks')) {
                    $table->text('bac_chair_remarks')->nullable()->after('bac_chair_confirmed_at');
                }

                if (! Schema::hasColumn('bac_resolutions', 'forwarded_to_hope_by_user_id')) {
                    $table->foreignId('forwarded_to_hope_by_user_id')
                        ->nullable()
                        ->after('bac_chair_remarks')
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('bac_resolutions', 'forwarded_to_hope_at')) {
                    $table->timestamp('forwarded_to_hope_at')->nullable()->after('forwarded_to_hope_by_user_id');
                }
            });
        }

        $this->ensureBacChairResolutionPermissions();
    }

    public function down(): void
    {
        if (Schema::hasTable('bac_resolutions')) {
            Schema::table('bac_resolutions', function (Blueprint $table) {
                foreach (['forwarded_to_hope_at', 'bac_chair_remarks', 'bac_chair_confirmed_at'] as $column) {
                    if (Schema::hasColumn('bac_resolutions', $column)) {
                        $table->dropColumn($column);
                    }
                }

                foreach (['forwarded_to_hope_by_user_id', 'bac_chair_confirmed_by_user_id'] as $column) {
                    if (Schema::hasColumn('bac_resolutions', $column)) {
                        $table->dropConstrainedForeignId($column);
                    }
                }
            });
        }
    }

    private function ensureBacChairResolutionPermissions(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $permissionKeys = [
            'bac.resolution.view',
            'bac.resolution.review',
            'bac.resolution.confirm',
            'bac.resolution.return',
            'bac.resolution.forward_to_hope',
        ];

        foreach ($permissionKeys as $key) {
            $attributes = [
                    'name' => str($key)->replace(['.', '_'], ' ')->title()->toString(),
                    'group' => 'BAC Resolution',
                    'description' => null,
                    'updated_at' => now(),
                ];

            if (DB::table('permissions')->where('key', $key)->exists()) {
                DB::table('permissions')->where('key', $key)->update($attributes);
            } else {
                DB::table('permissions')->insert([
                    'key' => $key,
                    ...$attributes,
                    'created_at' => now(),
                ]);
            }
        }

        $roleId = DB::table('roles')->where('code', 'bac_chair')->value('id');

        if (! $roleId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('key', array_merge($permissionKeys, ['notifications.view', 'profile.view']))
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }
};
