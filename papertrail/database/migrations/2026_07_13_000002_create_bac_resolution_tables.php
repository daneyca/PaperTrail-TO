<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bac_resolutions', function (Blueprint $table) {
            $table->id();
            $table->string('resolution_number')->nullable();
            $table->string('resolution_series')->nullable();
            $table->unsignedSmallInteger('fiscal_year')->nullable();
            $table->date('resolution_date')->nullable();
            $table->foreignId('source_pr_document_id')->nullable()->constrained('procurement_documents')->nullOnDelete();
            $table->foreignId('procurement_document_id')->nullable()->constrained('procurement_documents')->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('project_title')->nullable();
            $table->string('procurement_mode')->nullable();
            $table->string('supplier_name')->nullable();
            $table->text('supplier_address')->nullable();
            $table->string('supplier_contact')->nullable();
            $table->string('requesting_office_name')->nullable();
            $table->string('pr_number')->nullable();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('total_amount_words')->nullable();
            $table->string('philgeps_reference_no')->nullable();
            $table->string('solicitation_no')->nullable();
            $table->string('status')->index();
            $table->foreignId('prepared_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->text('remarks')->nullable();
            $table->json('header_lines')->nullable();
            $table->json('whereas_clauses')->nullable();
            $table->json('resolved_clauses')->nullable();
            $table->json('signatories')->nullable();
            $table->json('approval_signatory')->nullable();
            $table->timestamps();
        });

        Schema::create('bac_resolution_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bac_resolution_id')->constrained('bac_resolutions')->cascadeOnDelete();
            $table->foreignId('source_pr_item_id')->nullable()->constrained('purchase_request_items')->nullOnDelete();
            $table->string('item_no')->nullable();
            $table->text('description');
            $table->decimal('quantity', 14, 2)->default(0);
            $table->string('unit')->nullable();
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->decimal('total_cost', 14, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bac_resolution_items');
        Schema::dropIfExists('bac_resolutions');
    }
};
