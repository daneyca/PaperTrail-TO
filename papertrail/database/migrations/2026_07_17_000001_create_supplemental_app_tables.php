<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplemental_apps')) {
            Schema::create('supplemental_apps', function (Blueprint $table) {
                $table->id();
                $table->string('supplemental_app_number')->nullable()->unique();
                $table->unsignedSmallInteger('fiscal_year')->nullable()->index();
                $table->foreignId('source_pr_document_id')->nullable()->constrained('procurement_documents')->nullOnDelete();
                $table->foreignId('requesting_office_id')->nullable()->constrained('offices')->nullOnDelete();
                $table->string('requesting_office_name')->nullable();
                $table->string('title')->nullable();
                $table->text('purpose')->nullable();
                $table->text('justification')->nullable();
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->string('status')->default('draft')->index();
                $table->foreignId('prepared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('accepted_at')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->index(['source_pr_document_id', 'status']);
                $table->index(['requesting_office_id', 'status']);
            });
        }

        if (! Schema::hasTable('supplemental_app_items')) {
            Schema::create('supplemental_app_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplemental_app_id')->constrained('supplemental_apps')->cascadeOnDelete();
                $table->foreignId('source_pr_item_id')->nullable()->constrained('purchase_request_items')->nullOnDelete();
                $table->string('item_no')->nullable();
                $table->text('description')->nullable();
                $table->decimal('quantity', 14, 2)->nullable();
                $table->string('unit')->nullable();
                $table->decimal('estimated_unit_cost', 14, 2)->nullable();
                $table->decimal('estimated_total_cost', 14, 2)->nullable();
                $table->text('remarks')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplemental_app_items');
        Schema::dropIfExists('supplemental_apps');
    }
};
