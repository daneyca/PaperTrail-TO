<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_ROLE = 'PR Numbering Staff';

    private const NEW_ROLE = 'Procurement Controller';

    private const OLD_USER_ID = 'PRNO-001';

    private const NEW_USER_ID = 'PC-001';

    private const ROLE_COLUMNS = [
        'procurement_documents' => ['route_destination_role'],
        'signature_requests' => ['requested_to_role'],
        'electronic_signatures' => ['signer_role'],
        'svp_chain_events' => ['from_role', 'to_role'],
        'routing_rules' => ['from_role', 'to_role', 'notify_role'],
        'bac_deliberation_participants' => ['role_name'],
        'audit_logs' => ['role_name'],
    ];

    private const USER_IDENTIFIER_COLUMNS = [
        'electronic_signatures' => ['signer_user_identifier'],
        'audit_logs' => ['user_identifier'],
    ];

    public function up(): void
    {
        $this->rename(self::OLD_ROLE, self::NEW_ROLE, self::OLD_USER_ID, self::NEW_USER_ID);
    }

    public function down(): void
    {
        $this->rename(self::NEW_ROLE, self::OLD_ROLE, self::NEW_USER_ID, self::OLD_USER_ID);
    }

    private function rename(string $fromRole, string $toRole, string $fromUserId, string $toUserId): void
    {
        DB::transaction(function () use ($fromRole, $toRole, $fromUserId, $toUserId): void {
            if (Schema::hasTable('roles')) {
                DB::table('roles')
                    ->where('code', 'pr_numbering_staff')
                    ->update([
                        'name' => $toRole,
                        'description' => 'Records and controls official procurement numbers and dates, including PR, RFQ, PO, and inspection details.',
                        'updated_at' => now(),
                    ]);
            }

            if (Schema::hasTable('users')) {
                $targetExists = DB::table('users')->where('user_id', $toUserId)->exists();

                if (! $targetExists) {
                    DB::table('users')->where('user_id', $fromUserId)->update(['user_id' => $toUserId]);
                }

                DB::table('users')
                    ->whereIn('user_id', [$fromUserId, $toUserId])
                    ->update(['role' => $toRole, 'updated_at' => now()]);

                DB::table('users')
                    ->whereIn('user_id', [$fromUserId, $toUserId])
                    ->where('name', $fromRole)
                    ->update(['name' => $toRole, 'updated_at' => now()]);
            }

            $this->replaceValues(self::ROLE_COLUMNS, $fromRole, $toRole);
            $this->replaceValues(self::USER_IDENTIFIER_COLUMNS, $fromUserId, $toUserId);
        });
    }

    private function replaceValues(array $tables, string $from, string $to): void
    {
        foreach ($tables as $table => $columns) {
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
