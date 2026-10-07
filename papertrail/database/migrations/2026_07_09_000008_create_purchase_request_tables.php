<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('procurement_documents', 'pr_status')) {
                $table->string('pr_status')->nullable()->index()->after('route_remarks');
            }
            if (!Schema::hasColumn('procurement_documents', 'pr_received_by_user_id')) {
                $table->foreignId('pr_received_by_user_id')->nullable()->after('pr_status')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('procurement_documents', 'pr_received_at')) {
                $table->timestamp('pr_received_at')->nullable()->after('pr_received_by_user_id');
            }
            if (!Schema::hasColumn('procurement_documents', 'pr_validation_started_at')) {
                $table->timestamp('pr_validation_started_at')->nullable()->after('pr_received_at');
            }
            if (!Schema::hasColumn('procurement_documents', 'pr_validated_by_user_id')) {
                $table->foreignId('pr_validated_by_user_id')->nullable()->after('pr_validation_started_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('procurement_documents', 'pr_validated_at')) {
                $table->timestamp('pr_validated_at')->nullable()->after('pr_validated_by_user_id');
            }
            if (!Schema::hasColumn('procurement_documents', 'pr_remarks')) {
                $table->text('pr_remarks')->nullable()->after('pr_validated_at');
            }
            if (!Schema::hasColumn('procurement_documents', 'app_consolidation_id')) {
                $table->foreignId('app_consolidation_id')->nullable()->after('pr_remarks')->constrained('app_consolidations')->nullOnDelete();
            }
            if (!Schema::hasColumn('procurement_documents', 'app_item_id')) {
                $table->foreignId('app_item_id')->nullable()->after('app_consolidation_id')->constrained('app_items')->nullOnDelete();
            }
            if (!Schema::hasColumn('procurement_documents', 'requested_delivery_date')) {
                $table->date('requested_delivery_date')->nullable()->after('app_item_id');
            }
            if (!Schema::hasColumn('procurement_documents', 'purpose')) {
                $table->text('purpose')->nullable()->after('requested_delivery_date');
            }
        });

        if (!Schema::hasTable('purchase_request_items')) {
            Schema::create('purchase_request_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('procurement_document_id')->constrained('procurement_documents')->cascadeOnDelete();
                $table->foreignId('app_item_id')->nullable()->constrained('app_items')->nullOnDelete();
                $table->string('item_no')->nullable();
                $table->text('item_description');
                $table->decimal('quantity', 14, 2)->default(0);
                $table->string('unit')->nullable();
                $table->decimal('estimated_unit_cost', 14, 2)->default(0);
                $table->decimal('estimated_total_cost', 14, 2)->default(0);
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('purchase_request_validations')) {
            Schema::create('purchase_request_validations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('procurement_document_id')->constrained('procurement_documents')->cascadeOnDelete();
                $table->foreignId('validated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('validation_status')->index();
                $table->boolean('app_reference_checked')->default(false);
                $table->boolean('attachments_checked')->default(false);
                $table->boolean('item_details_checked')->default(false);
                $table->text('remarks')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_validations');
        Schema::dropIfExists('purchase_request_items');
    }
};
