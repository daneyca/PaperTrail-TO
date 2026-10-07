<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            }

            if (! Schema::hasColumn('users', 'contact_verified_at')) {
                $table->timestamp('contact_verified_at')->nullable()->after('contact_number');
            }

            if (! Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('password');
            }
        });

        if (Schema::hasColumn('users', 'email') && ! $this->hasIndex('users', 'users_email_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('email', 'users_email_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if ($this->hasIndex('users', 'users_email_unique')) {
                $table->dropUnique('users_email_unique');
            }

            foreach (['must_change_password', 'contact_verified_at', 'email_verified_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM {$table}"))
            ->contains(fn ($row) => ($row->Key_name ?? null) === $index);
    }
};
