<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inspection_acceptance_records')) {
            Schema::create('inspection_acceptance_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
                $table->foreignId('source_pr_document_id')->nullable()->constrained('procurement_documents')->nullOnDelete();
                $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
                $table->foreignId('inspected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('draft')->index();
                $table->date('inspection_date')->nullable();
                $table->date('acceptance_date')->nullable();
                $table->string('delivery_receipt_number')->nullable();
                $table->string('invoice_number')->nullable();
                $table->string('quantity_condition')->nullable();
                $table->string('quality_condition')->nullable();
                $table->text('findings')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('inspection_acceptance_records', function (Blueprint $table) {
            if (! Schema::hasColumn('inspection_acceptance_records', 'delivery_receipt_number')) {
                $table->string('delivery_receipt_number')->nullable()->after('acceptance_date');
            }

            if (! Schema::hasColumn('inspection_acceptance_records', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->after('delivery_receipt_number');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_acceptance_records');
    }
};
