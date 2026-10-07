<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'email_verification_code_hash')) {
                $table->string('email_verification_code_hash')->nullable()->after('email_verified_at');
            }

            if (! Schema::hasColumn('users', 'email_verification_code_sent_at')) {
                $table->timestamp('email_verification_code_sent_at')->nullable()->after('email_verification_code_hash');
            }

            if (! Schema::hasColumn('users', 'email_verification_code_expires_at')) {
                $table->timestamp('email_verification_code_expires_at')->nullable()->after('email_verification_code_sent_at');
            }

            if (! Schema::hasColumn('users', 'email_verification_attempts')) {
                $table->unsignedTinyInteger('email_verification_attempts')->default(0)->after('email_verification_code_expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'email_verification_attempts',
                'email_verification_code_expires_at',
                'email_verification_code_sent_at',
                'email_verification_code_hash',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
