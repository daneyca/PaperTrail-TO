<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_NAME = 'Office Document Preparer';

    private const NEW_NAME = 'Procurement Staff';

    public function up(): void
    {
        if (Schema::hasTable('roles')) {
            DB::table('roles')
                ->where('code', 'office_preparer')
                ->update(['name' => self::NEW_NAME, 'updated_at' => now()]);
        }

        if (Schema::hasTable('users')) {
            DB::table('users')
                ->where('role', self::OLD_NAME)
                ->update(['role' => self::NEW_NAME, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('roles')) {
            DB::table('roles')
                ->where('code', 'office_preparer')
                ->update(['name' => self::OLD_NAME, 'updated_at' => now()]);
        }

        if (Schema::hasTable('users')) {
            DB::table('users')
                ->where('role', self::NEW_NAME)
                ->update(['role' => self::OLD_NAME, 'updated_at' => now()]);
        }
    }
};
