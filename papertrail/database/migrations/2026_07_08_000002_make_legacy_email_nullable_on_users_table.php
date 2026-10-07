<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'email')) {
            DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'email')) {
            DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NOT NULL');
        }
    }
};
