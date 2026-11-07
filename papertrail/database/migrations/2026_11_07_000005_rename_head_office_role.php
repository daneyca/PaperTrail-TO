<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_NAME = 'Head of Office / End User';

    private const NEW_NAME = 'Head of Office';

    private const ROLE_COLUMNS = [
        'procurement_documents' => ['route_destination_role'],
        'signature_requests' => ['requested_to_role'],
        'electronic_signatures' => ['signer_role'],
        'svp_chain_events' => ['from_role', 'to_role'],
        'routing_rules' => ['from_role', 'to_role', 'notify_role'],
        'bac_deliberation_participants' => ['role_name'],
    ];

    public function up(): void
    {
        $this->rename(self::OLD_NAME, self::NEW_NAME);
    }

    public function down(): void
    {
        $this->rename(self::NEW_NAME, self::OLD_NAME);
    }

    private function rename(string $from, string $to): void
    {
        if (Schema::hasTable('roles')) {
            DB::table('roles')
                ->where('code', 'head_office')
                ->update(['name' => $to, 'updated_at' => now()]);
        }

        if (Schema::hasTable('users')) {
            DB::table('users')
                ->where('role', $from)
                ->update(['role' => $to, 'updated_at' => now()]);
        }

        foreach (self::ROLE_COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    DB::table($table)->where($column, $from)->update([$column => $to]);
                }
            }
        }
    }
};
