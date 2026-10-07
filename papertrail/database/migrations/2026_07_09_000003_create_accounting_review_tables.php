<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('procurement_documents', 'accounting_status')) {
                $table->string('accounting_status')->nullable()->index()->after('budget_remarks');
            }
            if (!Schema::hasColumn('procurement_documents', 'accounting_reviewed_by_user_id')) {
                $table->foreignId('accounting_reviewed_by_user_id')->nullable()->after('accounting_status')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('procurement_documents', 'accounting_review_started_at')) {
                $table->timestamp('accounting_review_started_at')->nullable()->after('accounting_reviewed_by_user_id');
            }
            if (!Schema::hasColumn('procurement_documents', 'accounting_reviewed_at')) {
                $table->timestamp('accounting_reviewed_at')->nullable()->after('accounting_review_started_at');
            }
            if (!Schema::hasColumn('procurement_documents', 'accounting_remarks')) {
                $table->text('accounting_remarks')->nullable()->after('accounting_reviewed_at');
            }
            if (!Schema::hasColumn('procurement_documents', 'accounting_reference_no')) {
                $table->string('accounting_reference_no')->nullable()->after('accounting_remarks');
            }
            if (!Schema::hasColumn('procurement_documents', 'account_code')) {
                $table->string('account_code')->nullable()->after('accounting_reference_no');
            }
            if (!Schema::hasColumn('procurement_documents', 'object_code')) {
                $table->string('object_code')->nullable()->after('account_code');
            }
            if (!Schema::hasColumn('procurement_documents', 'responsibility_center')) {
                $table->string('responsibility_center')->nullable()->after('object_code');
            }
        });

        if (!Schema::hasTable('accounting_reviews')) {
            Schema::create('accounting_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('procurement_document_id')->constrained('procurement_documents')->cascadeOnDelete();
                $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('review_status')->default('pending')->index();
                $table->string('accounting_reference_no')->nullable();
                $table->string('account_code')->nullable();
                $table->string('object_code')->nullable();
                $table->string('responsibility_center')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_reviews');
    }
};
