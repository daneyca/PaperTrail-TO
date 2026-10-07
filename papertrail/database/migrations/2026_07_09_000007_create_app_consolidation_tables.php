<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('app_consolidations')) {
            Schema::create('app_consolidations', function (Blueprint $table) {
                $table->id();
                $table->string('app_number')->nullable()->unique();
                $table->unsignedSmallInteger('fiscal_year')->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->foreignId('prepared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('procurement_document_id')->nullable()->constrained('procurement_documents')->nullOnDelete();
                $table->string('status')->default('draft')->index();
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->text('remarks')->nullable();
                $table->timestamp('consolidated_at')->nullable();
                $table->timestamp('submitted_for_approval_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('app_ppmp_sources')) {
            Schema::create('app_ppmp_sources', function (Blueprint $table) {
                $table->id();
                $table->foreignId('app_consolidation_id')->constrained('app_consolidations')->cascadeOnDelete();
                $table->foreignId('procurement_document_id')->constrained('procurement_documents')->cascadeOnDelete();
                $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
                $table->foreignId('included_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('included_at');
                $table->string('status')->default('included')->index();
                $table->timestamps();

                $table->unique(['app_consolidation_id', 'procurement_document_id'], 'app_ppmp_unique_source');
            });
        }

        if (!Schema::hasTable('app_items')) {
            Schema::create('app_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('app_consolidation_id')->constrained('app_consolidations')->cascadeOnDelete();
                $table->foreignId('source_ppmp_document_id')->nullable()->constrained('procurement_documents')->nullOnDelete();
                $table->unsignedBigInteger('source_ppmp_item_id')->nullable();
                $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
                $table->string('item_no')->nullable();
                $table->text('general_description');
                $table->decimal('quantity', 14, 2)->default(0);
                $table->string('unit')->nullable();
                $table->decimal('estimated_unit_cost', 14, 2)->default(0);
                $table->decimal('estimated_total_cost', 14, 2)->default(0);
                $table->string('procurement_mode')->nullable();
                $table->string('schedule_quarter')->nullable();
                $table->string('category')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_items');
        Schema::dropIfExists('app_ppmp_sources');
        Schema::dropIfExists('app_consolidations');
    }
};
