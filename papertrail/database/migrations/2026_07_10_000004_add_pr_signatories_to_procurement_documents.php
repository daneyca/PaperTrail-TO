<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('procurement_documents', 'pr_signatories')) {
                $table->json('pr_signatories')->nullable()->after('certification_text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table) {
            if (Schema::hasColumn('procurement_documents', 'pr_signatories')) {
                $table->dropColumn('pr_signatories');
            }
        });
    }
};
