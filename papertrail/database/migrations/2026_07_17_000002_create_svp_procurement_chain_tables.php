<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('svp_procurement_chains', function (Blueprint $table) {
            $table->id();
            $table->string('chain_number')->nullable()->unique();
            $table->string('tracking_number')->nullable()->index();
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->string('office_name')->nullable();
            $table->foreignId('source_pr_document_id')->nullable()->constrained('procurement_documents')->nullOnDelete();
            $table->foreignId('bac_resolution_id')->nullable()->constrained('bac_resolutions')->nullOnDelete();
            $table->foreignId('rfq_id')->nullable()->constrained('rfqs')->nullOnDelete();
            $table->foreignId('abstract_id')->nullable()->constrained('abstracts')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('inspection_id')->nullable()->constrained('inspection_acceptance_records')->nullOnDelete();
            $table->string('current_stage')->nullable()->index();
            $table->string('current_status')->nullable()->index();
            $table->string('procurement_mode')->default('SVP');
            $table->decimal('total_amount', 14, 2)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['office_id', 'current_stage', 'current_status'], 'svp_chain_office_stage_status_idx');
        });

        Schema::create('svp_chain_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('svp_procurement_chain_id')->constrained('svp_procurement_chains')->cascadeOnDelete();
            $table->string('document_type')->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->string('action')->nullable();
            $table->string('stage')->nullable();
            $table->string('status')->nullable();
            $table->string('from_role')->nullable();
            $table->string('to_role')->nullable();
            $table->foreignId('from_office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->foreignId('to_office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['document_type', 'document_id'], 'svp_event_document_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('svp_chain_events');
        Schema::dropIfExists('svp_procurement_chains');
    }
};
