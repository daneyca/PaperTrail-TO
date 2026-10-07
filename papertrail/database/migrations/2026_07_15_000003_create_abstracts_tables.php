<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abstracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_pr_document_id')->nullable()->constrained('procurement_documents')->nullOnDelete();
            $table->foreignId('source_rfq_id')->nullable()->constrained('rfqs')->nullOnDelete();
            $table->foreignId('source_bac_resolution_id')->nullable()->constrained('bac_resolutions')->nullOnDelete();
            $table->string('abstract_number')->nullable();
            $table->date('abstract_date')->nullable();
            $table->string('project_name')->nullable();
            $table->string('implementing_office')->nullable();
            $table->decimal('abc_amount', 14, 2)->nullable();
            $table->text('purpose')->nullable();
            $table->string('supplier_1_name')->nullable();
            $table->string('supplier_2_name')->nullable();
            $table->string('supplier_3_name')->nullable();
            $table->string('supplier_4_name')->nullable();
            $table->string('supplier_5_name')->nullable();
            $table->string('lowest_supplier_name')->nullable();
            $table->decimal('lowest_total_amount', 14, 2)->nullable();
            $table->longText('document_html')->nullable();
            $table->longText('document_text')->nullable();
            $table->json('items_json')->nullable();
            $table->json('suppliers_json')->nullable();
            $table->json('awards_json')->nullable();
            $table->json('committee_json')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('prepared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
            $table->index('abstract_number');
        });

        Schema::create('abstract_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abstract_id')->constrained('abstracts')->cascadeOnDelete();
            $table->string('item_no')->nullable();
            $table->text('name_of_goods_services')->nullable();
            $table->decimal('quantity', 14, 2)->nullable();
            $table->string('unit_of_measure')->nullable();
            $table->decimal('supplier_1_amount', 14, 2)->nullable();
            $table->decimal('supplier_2_amount', 14, 2)->nullable();
            $table->decimal('supplier_3_amount', 14, 2)->nullable();
            $table->decimal('supplier_4_amount', 14, 2)->nullable();
            $table->decimal('supplier_5_amount', 14, 2)->nullable();
            $table->decimal('total_lowest_price', 14, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abstract_items');
        Schema::dropIfExists('abstracts');
    }
};
