<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('procurement_documents')) {
            Schema::create('procurement_documents', function (Blueprint $table) {
                $table->id();
                $table->string('tracking_number')->unique();
                $table->string('document_type');
                $table->string('title');
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('fiscal_year');
                $table->foreignId('submitting_office_id')->nullable()->constrained('offices')->nullOnDelete();
                $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('current_office_id')->nullable()->constrained('offices')->nullOnDelete();
                $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->index();
                $table->string('stage')->nullable()->index();
                $table->string('priority')->default('normal')->index();
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->text('remarks')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->string('budget_status')->nullable()->index();
                $table->foreignId('budget_reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('budget_review_started_at')->nullable();
                $table->timestamp('budget_reviewed_at')->nullable();
                $table->text('budget_remarks')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('procurement_documents', function (Blueprint $table) {
                if (!Schema::hasColumn('procurement_documents', 'budget_status')) {
                    $table->string('budget_status')->nullable()->index()->after('submitted_at');
                }
                if (!Schema::hasColumn('procurement_documents', 'budget_reviewed_by_user_id')) {
                    $table->foreignId('budget_reviewed_by_user_id')->nullable()->after('budget_status')->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('procurement_documents', 'budget_review_started_at')) {
                    $table->timestamp('budget_review_started_at')->nullable()->after('budget_reviewed_by_user_id');
                }
                if (!Schema::hasColumn('procurement_documents', 'budget_reviewed_at')) {
                    $table->timestamp('budget_reviewed_at')->nullable()->after('budget_review_started_at');
                }
                if (!Schema::hasColumn('procurement_documents', 'budget_remarks')) {
                    $table->text('budget_remarks')->nullable()->after('budget_reviewed_at');
                }
            });
        }

        if (!Schema::hasTable('budget_reviews')) {
            Schema::create('budget_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('procurement_document_id')->constrained('procurement_documents')->cascadeOnDelete();
                $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('review_status')->default('pending')->index();
                $table->decimal('requested_amount', 14, 2)->default(0);
                $table->decimal('available_amount', 14, 2)->nullable();
                $table->string('fund_source')->nullable();
                $table->string('appropriation_code')->nullable();
                $table->string('responsibility_center')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('document_routing_histories')) {
            Schema::create('document_routing_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('procurement_document_id')->constrained('procurement_documents')->cascadeOnDelete();
                $table->foreignId('action_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('from_office_id')->nullable()->constrained('offices')->nullOnDelete();
                $table->foreignId('to_office_id')->nullable()->constrained('offices')->nullOnDelete();
                $table->string('action');
                $table->string('status_from')->nullable();
                $table->string('status_to');
                $table->text('comments')->nullable();
                $table->timestamp('action_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_routing_histories');
        Schema::dropIfExists('budget_reviews');
        Schema::dropIfExists('procurement_documents');
    }
};
