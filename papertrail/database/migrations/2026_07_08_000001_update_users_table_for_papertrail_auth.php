<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'user_id')) {
                $table->string('user_id')->nullable()->unique()->after('id');
            }

            if (!Schema::hasColumn('users', 'office')) {
                $table->string('office')->nullable()->after('name');
            }

            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->nullable()->after('office');
            }

            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status')->default('active')->after('password');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }

            if (Schema::hasColumn('users', 'office')) {
                $table->dropColumn('office');
            }

            if (Schema::hasColumn('users', 'user_id')) {
                $table->dropUnique('users_user_id_unique');
                $table->dropColumn('user_id');
            }
        });
    }
};
