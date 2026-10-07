<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('annual_procurement_plan_items')) {
            return;
        }

        Schema::table('annual_procurement_plan_items', function (Blueprint $table) {
            if (! Schema::hasColumn('annual_procurement_plan_items', 'source_ppmp_document_id')) {
                $table->foreignId('source_ppmp_document_id')
                    ->nullable()
                    ->after('annual_procurement_plan_id')
                    ->constrained('procurement_documents')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('annual_procurement_plan_items', 'source_ppmp_item_id')) {
                $table->unsignedBigInteger('source_ppmp_item_id')
                    ->nullable()
                    ->after('source_ppmp_document_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('annual_procurement_plan_items')) {
            return;
        }

        Schema::table('annual_procurement_plan_items', function (Blueprint $table) {
            if (Schema::hasColumn('annual_procurement_plan_items', 'source_ppmp_document_id')) {
                $table->dropConstrainedForeignId('source_ppmp_document_id');
            }

            if (Schema::hasColumn('annual_procurement_plan_items', 'source_ppmp_item_id')) {
                $table->dropColumn('source_ppmp_item_id');
            }
        });
    }
};
