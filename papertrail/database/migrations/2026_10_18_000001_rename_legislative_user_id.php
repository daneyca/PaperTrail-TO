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

        $legacy = DB::table('users')->where('user_id', 'LEGISLATIVE-0011')->first();

        if (! $legacy) {
            return;
        }

        if (DB::table('users')->where('user_id', 'LEGISLATIVE-001')->exists()) {
            throw new RuntimeException('Both Legislative user IDs exist; resolve the accounts before renaming.');
        }

        DB::table('users')->where('id', $legacy->id)->update([
            'user_id' => 'LEGISLATIVE-001',
        ]);
    }

    public function down(): void
    {
        // Leave account identifiers unchanged rather than renaming a newer account on rollback.
    }
};
