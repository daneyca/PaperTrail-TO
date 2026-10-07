<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->where('code', 'approving_authority')
            ->update([
                'name' => 'Head of the Procuring Entity',
                'description' => 'Final authorized approval role for procurement documents.',
                'dashboard_route' => 'approving-authority.dashboard',
                'status' => 'active',
                'is_system' => true,
            ]);

        $role = DB::table('roles')->where('code', 'approving_authority')->first();
        $office = DB::table('offices')->where('code', 'MO')->first()
            ?? DB::table('offices')->where('code', 'OMM')->first();
        $mayor = DB::table('users')->where('user_id', 'MAYOR-001')->first();
        $hope = DB::table('users')->where('user_id', 'HOPE-001')->first();

        if ($mayor && ! $hope) {
            DB::table('users')
                ->where('id', $mayor->id)
                ->update([
                    'user_id' => 'HOPE-001',
                    'name' => 'Head of the Procuring Entity',
                    'office_id' => $office?->id,
                    'office' => 'Office of the Municipal Mayor',
                    'role_id' => $role?->id,
                    'role' => 'Head of the Procuring Entity',
                    'status' => 'active',
                    'updated_at' => now(),
                ]);

            return;
        }

        if ($hope) {
            DB::table('users')
                ->where('id', $hope->id)
                ->update([
                    'name' => 'Head of the Procuring Entity',
                    'office_id' => $office?->id,
                    'office' => 'Office of the Municipal Mayor',
                    'role_id' => $role?->id,
                    'role' => 'Head of the Procuring Entity',
                    'status' => 'active',
                    'updated_at' => now(),
                ]);
        }

        if ($mayor && $hope && $mayor->id !== $hope->id) {
            DB::table('users')
                ->where('id', $mayor->id)
                ->update([
                    'status' => 'inactive',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('code', 'approving_authority')
            ->update([
                'name' => 'Approving Authority',
                'description' => 'Reviews and approves documents requiring final authorization.',
                'dashboard_route' => 'approving-authority.dashboard',
            ]);
    }
};
