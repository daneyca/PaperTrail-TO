<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('svp_posting_records')) {
            return;
        }

        Schema::create('svp_posting_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('svp_procurement_chain_id')->constrained('svp_procurement_chains')->cascadeOnDelete();
            $table->foreignId('source_pr_document_id')->nullable()->constrained('procurement_documents')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pr_reference')->nullable()->index();
            $table->string('procurement_title')->nullable();
            $table->string('requesting_office')->nullable();
            $table->string('procurement_method')->default('SVP');
            $table->decimal('approved_budget', 14, 2)->nullable();
            $table->date('posting_date')->nullable();
            $table->date('closing_date')->nullable();
            $table->string('status')->default('draft')->index();
            $table->text('remarks')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique('svp_procurement_chain_id', 'svp_posting_chain_unique');
            $table->index(['svp_procurement_chain_id', 'status'], 'svp_posting_chain_status_idx');
            $table->index(['source_pr_document_id', 'status'], 'svp_posting_pr_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('svp_posting_records');
    }
};
