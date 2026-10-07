<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('procurement_documents', 'prepared_by_name')) {
                $table->string('prepared_by_name')->nullable()->after('prepared_by_user_id');
            }

            if (! Schema::hasColumn('procurement_documents', 'submitted_by_name')) {
                $table->string('submitted_by_name')->nullable()->after('prepared_by_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table) {
            if (Schema::hasColumn('procurement_documents', 'submitted_by_name')) {
                $table->dropColumn('submitted_by_name');
            }

            if (Schema::hasColumn('procurement_documents', 'prepared_by_name')) {
                $table->dropColumn('prepared_by_name');
            }
        });
    }
};
