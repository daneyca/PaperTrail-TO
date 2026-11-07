<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $signaturePermission = DB::table('permissions')->where('key', 'esignature.use')->value('id');
            if ($signaturePermission) {
                foreach (DB::table('roles')->whereIn('code', ['budget_officer', 'accounting_officer'])->pluck('id') as $roleId) {
                    DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $signaturePermission]);
                }
            }

            $accounts = ['BUDGET-001' => 'MBO-001', 'ACCOUNTING-001' => 'ACC-001', 'HOPE-001' => 'MO-001'];

            foreach ($accounts as $oldCode => $officeCode) {
                $old = DB::table('users')->where('user_id', $oldCode)->first();
                if (! $old) {
                    continue;
                }

                $office = DB::table('users')->where('user_id', $officeCode)
                    ->whereIn('role', ['Head of Office / End User', 'Head of Office'])
                    ->where('status', 'active')->first();
                if (! $office) {
                    throw new RuntimeException("Cannot retire {$oldCode}: active office account {$officeCode} is missing.");
                }

                // Transfer unfinished work only; signed requests and past actions keep their original author.
                if (Schema::hasTable('signature_requests')) {
                    DB::table('signature_requests')->where('requested_to_user_id', $old->id)
                        ->whereIn('status', ['pending', 'notified', 'viewed', 'waiting'])
                        ->update(['requested_to_user_id' => $office->id, 'requested_to_office_id' => $office->office_id, 'updated_at' => now()]);
                }

                if (Schema::hasColumn('procurement_documents', 'assigned_to_user_id')) {
                    DB::table('procurement_documents')->where('assigned_to_user_id', $old->id)
                        ->whereIn('status', ['pending_budget_review', 'under_budget_review',
                            'pending_accounting_review', 'under_accounting_review', 'pending_approval', 'under_approval'])
                        ->update(['assigned_to_user_id' => $office->id, 'current_office_id' => $office->office_id, 'updated_at' => now()]);
                }

                DB::table('users')->where('id', $old->id)
                    ->update(['status' => 'inactive', 'remember_token' => null, 'updated_at' => now()]);
                if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
                    DB::table('sessions')->where('user_id', $old->id)->delete();
                }
            }
        });
    }

    public function down(): void
    {
        // Do not reactivate retired logins or move work back after it may have been signed.
    }
};
