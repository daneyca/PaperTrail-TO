<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->transfer('BACSEC-003', 'BACSEC-001');
    }

    public function down(): void
    {
        $this->transfer('BACSEC-001', 'BACSEC-003');
    }

    private function transfer(string $from, string $to): void
    {
        DB::transaction(function () use ($from, $to): void {
            $workspace = DB::table('users')->where('user_id', $from.'-MPDO')->lockForUpdate()->first();
            if (! $workspace) {
                return; // Fresh installations create the workspace on first use.
            }

            $previous = DB::table('users')->where('user_id', $from)->first();
            $owner = DB::table('users')->where('user_id', $to)->lockForUpdate()->first();
            $office = DB::table('offices')->where('id', $workspace->office_id)->value('code');
            if (! $previous || ! $owner || $owner->principal_user_id
                || (int) $workspace->principal_user_id !== (int) $previous->id || $office !== 'MPDO'
                || DB::table('users')->where('user_id', $to.'-MPDO')->exists()) {
                throw new RuntimeException('Review the MPDO SVP workspace assignment before transferring it.');
            }

            // Keep the actor's numeric ID: documents, assignments, signatures, notifications,
            // and routing history continue to reference the same existing records.
            DB::table('users')->where('id', $workspace->id)->update([
                'user_id' => $to.'-MPDO',
                'principal_user_id' => $owner->id,
                'updated_at' => now(),
            ]);
        });
    }
};
