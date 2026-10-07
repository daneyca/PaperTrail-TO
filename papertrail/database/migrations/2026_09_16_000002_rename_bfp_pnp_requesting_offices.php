<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameOffice('BFP', 'Municipal Fire Station - BFP', 'BFP-001');
        $this->renameOffice('PNP', 'Municipal Police Station - PNP', 'PNP-001');
    }

    public function down(): void
    {
        $this->renameOffice('BFP', 'Bureau of Fire Protection - Municipal Fire Station', 'BFP-001');
        $this->renameOffice('PNP', 'Philippine National Police - Municipal Police Station', 'PNP-001');
    }

    private function renameOffice(string $code, string $name, string $primaryUserId): void
    {
        $officeId = DB::table('offices')->where('code', $code)->value('id');

        DB::table('offices')
            ->where('code', $code)
            ->update([
                'name' => $name,
                'updated_at' => now(),
            ]);

        if ($officeId) {
            DB::table('users')
                ->where('office_id', $officeId)
                ->update([
                    'office' => $name,
                    'updated_at' => now(),
                ]);
        }

        DB::table('users')
            ->where('user_id', $primaryUserId)
            ->update([
                'name' => $name,
                'office' => $name,
                'updated_at' => now(),
            ]);
    }
};
