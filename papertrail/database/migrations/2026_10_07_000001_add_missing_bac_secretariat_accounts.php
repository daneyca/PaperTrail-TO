<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $template = DB::table('users')->where('user_id', 'BACSEC-001')->first();
        $office = Schema::hasTable('offices')
            ? DB::table('offices')->where('code', 'BACSEC')->first()
            : null;
        $roleId = Schema::hasTable('roles')
            ? DB::table('roles')->where('name', User::ROLE_BAC_SECRETARIAT)->orWhere('code', 'bac_secretariat')->value('id')
            : null;

        $accounts = [
            'BACSEC-002' => [
                'name' => 'BAC Secretariat Resolution Preparer',
                'position' => 'BAC Resolution Preparer',
            ],
            'BACSEC-003' => [
                'name' => 'Meagan C. Matutes',
                'position' => 'BAC Secretariat Signatory',
            ],
            'BACSEC-004' => [
                'name' => 'BAC Secretariat APP Consolidation',
                'position' => 'APP Consolidation / SVP Posting Processor',
            ],
        ];

        foreach ($accounts as $userId => $account) {
            $existing = DB::table('users')->where('user_id', $userId)->first();

            $payload = [
                'name' => $account['name'],
                'office' => $template?->office ?? $office?->name,
                'role' => User::ROLE_BAC_SECRETARIAT,
                'status' => User::STATUS_ACTIVE,
                'password' => $existing?->password ?? $template?->password ?? Hash::make('password123'),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('users', 'office_id')) {
                $payload['office_id'] = $template?->office_id ?? $office?->id;
            }

            if (Schema::hasColumn('users', 'role_id')) {
                $payload['role_id'] = $template?->role_id ?? $roleId;
            }

            if (Schema::hasColumn('users', 'position')) {
                $payload['position'] = $account['position'];
            }

            if (Schema::hasColumn('users', 'signer_position')) {
                $payload['signer_position'] = $account['position'];
            }

            if (Schema::hasColumn('users', 'must_change_password')) {
                $payload['must_change_password'] = false;
            }

            if ($existing) {
                DB::table('users')->where('id', $existing->id)->update($payload);

                continue;
            }

            DB::table('users')->insert([
                'user_id' => $userId,
                ...$payload,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        DB::table('users')->whereIn('user_id', [
            'BACSEC-002',
            'BACSEC-003',
            'BACSEC-004',
        ])->delete();
    }
};
