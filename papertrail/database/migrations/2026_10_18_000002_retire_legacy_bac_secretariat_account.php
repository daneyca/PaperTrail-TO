<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        DB::transaction(function (): void {
            $replacement = DB::table('users')->where('user_id', 'BACSEC-002')->lockForUpdate()->first();

            // A new installation has no accounts yet; the seeder creates only BACSEC-001.
            if (! $replacement) {
                return;
            }

            $legacy = DB::table('users')->where('user_id', 'BACSEC-001')->lockForUpdate()->first();

            if ($legacy) {
                if (DB::table('users')->where('user_id', 'BACSEC-RETIRED-001')->exists()) {
                    throw new RuntimeException('The BAC Secretariat retirement ID is already in use. Resolve the accounts before migrating.');
                }

                // Keep the original row and its foreign-key history, but prevent new logins.
                DB::table('users')->where('id', $legacy->id)->update([
                    'user_id' => 'BACSEC-RETIRED-001',
                    'status' => 'inactive',
                ]);
            }

            DB::table('users')->where('id', $replacement->id)->update([
                'user_id' => 'BACSEC-001',
            ]);
        });
    }

    public function down(): void
    {
        // Do not reverse account identities or reactivate a retired login on rollback.
    }
};
