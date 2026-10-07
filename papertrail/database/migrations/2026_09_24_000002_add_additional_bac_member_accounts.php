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
        $template = DB::table('users')->where('user_id', 'BACMEM-001')->first();
        $office = Schema::hasTable('offices')
            ? DB::table('offices')->where('code', 'BAC')->first()
            : null;
        $roleId = Schema::hasTable('roles')
            ? DB::table('roles')->where('name', User::ROLE_BAC_MEMBER)->orWhere('code', 'bac_member')->value('id')
            : null;

        foreach (['BACMEM-002', 'BACMEM-003'] as $userId) {
            $payload = [
                'name' => $template?->name ?? 'BAC Member',
                'office' => $template?->office ?? $office?->name,
                'role' => User::ROLE_BAC_MEMBER,
                'status' => $template?->status ?? User::STATUS_ACTIVE,
                'password' => $template?->password ?? Hash::make('password123'),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('users', 'office_id')) {
                $payload['office_id'] = $template?->office_id ?? $office?->id;
            }

            if (Schema::hasColumn('users', 'role_id')) {
                $payload['role_id'] = $template?->role_id ?? $roleId;
            }

            if (Schema::hasColumn('users', 'position')) {
                $payload['position'] = $template?->position;
            }

            if (Schema::hasColumn('users', 'signer_position')) {
                $payload['signer_position'] = $template?->signer_position ?? 'BAC Member';
            }

            $existing = DB::table('users')->where('user_id', $userId)->first();

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
        DB::table('users')->whereIn('user_id', ['BACMEM-002', 'BACMEM-003'])->delete();
    }
};
