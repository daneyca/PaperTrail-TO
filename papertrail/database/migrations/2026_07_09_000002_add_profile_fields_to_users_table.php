<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'email')) {
                $table->string('email')->nullable()->after('name');
            }

            if (! Schema::hasColumn('users', 'contact_number')) {
                $table->string('contact_number')->nullable()->after('email');
            }

            if (! Schema::hasColumn('users', 'position')) {
                $table->string('position')->nullable()->after('contact_number');
            }

            if (! Schema::hasColumn('users', 'profile_photo_path')) {
                $table->string('profile_photo_path')->nullable()->after('position');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['profile_photo_path', 'position', 'contact_number', 'email'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
