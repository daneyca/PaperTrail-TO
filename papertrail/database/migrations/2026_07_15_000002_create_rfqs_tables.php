<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_pr_document_id')->nullable()->constrained('procurement_documents')->nullOnDelete();
            $table->foreignId('source_bac_resolution_id')->nullable()->constrained('bac_resolutions')->nullOnDelete();
            $table->string('rfq_number')->nullable();
            $table->date('rfq_date')->nullable();
            $table->string('supplier_name')->nullable();
            $table->text('supplier_address')->nullable();
            $table->string('procurement_officer_name')->nullable();
            $table->string('procurement_officer_designation')->nullable();
            $table->decimal('abc_amount', 14, 2)->nullable();
            $table->text('purpose')->nullable();
            $table->string('delivery_period')->nullable();
            $table->text('warranty_text')->nullable();
            $table->text('price_validity_text')->nullable();
            $table->text('philgeps_requirement_text')->nullable();
            $table->text('mayors_permit_requirement_text')->nullable();
            $table->longText('document_html')->nullable();
            $table->longText('document_text')->nullable();
            $table->json('items_json')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('prepared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
            $table->index('rfq_number');
        });

        Schema::create('rfq_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->string('item_no')->nullable();
            $table->text('description')->nullable();
            $table->decimal('quantity', 14, 2)->nullable();
            $table->string('unit_of_issue')->nullable();
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('total', 14, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_items');
        Schema::dropIfExists('rfqs');
    }
};
