<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table) {
            $table->index(['submitting_office_id', 'status'], 'proc_docs_office_status_idx');
            $table->index(['submitted_by_user_id', 'status'], 'proc_docs_submitter_status_idx');
            $table->index(['prepared_by_user_id', 'status'], 'proc_docs_preparer_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table) {
            $table->dropIndex('proc_docs_office_status_idx');
            $table->dropIndex('proc_docs_submitter_status_idx');
            $table->dropIndex('proc_docs_preparer_status_idx');
        });
    }
};
