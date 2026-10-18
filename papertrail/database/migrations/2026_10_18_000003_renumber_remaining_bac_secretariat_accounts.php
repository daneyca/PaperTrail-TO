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
            $signer = DB::table('users')->where('user_id', 'BACSEC-003')->lockForUpdate()->first();
            $posting = DB::table('users')->where('user_id', 'BACSEC-004')->lockForUpdate()->first();

            // On a new installation, the seeder creates the final IDs after migrations.
            if (! $signer && ! $posting) {
                return;
            }

            if (! $signer || ! $posting || DB::table('users')->where('user_id', 'BACSEC-002')->exists()) {
                throw new RuntimeException('BAC Secretariat account IDs are not ready for renumbering. Run the prior account migration first.');
            }

            DB::table('users')->where('id', $signer->id)->update(['user_id' => 'BACSEC-002']);
            DB::table('users')->where('id', $posting->id)->update(['user_id' => 'BACSEC-003']);
        });
    }

    public function down(): void
    {
        // Do not reverse live account identities on rollback.
    }
};
