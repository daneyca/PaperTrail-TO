<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('procurement_documents', 'bac_chair_status')) {
                $table->string('bac_chair_status')->nullable()->index()->after('bac_member_remarks');
            }
            if (!Schema::hasColumn('procurement_documents', 'bac_chair_reviewed_by_user_id')) {
                $table->foreignId('bac_chair_reviewed_by_user_id')->nullable()->after('bac_chair_status')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('procurement_documents', 'bac_chair_review_started_at')) {
                $table->timestamp('bac_chair_review_started_at')->nullable()->after('bac_chair_reviewed_by_user_id');
            }
            if (!Schema::hasColumn('procurement_documents', 'bac_chair_reviewed_at')) {
                $table->timestamp('bac_chair_reviewed_at')->nullable()->after('bac_chair_review_started_at');
            }
            if (!Schema::hasColumn('procurement_documents', 'bac_chair_decision')) {
                $table->string('bac_chair_decision')->nullable()->index()->after('bac_chair_reviewed_at');
            }
            if (!Schema::hasColumn('procurement_documents', 'bac_chair_remarks')) {
                $table->text('bac_chair_remarks')->nullable()->after('bac_chair_decision');
            }
        });

        if (!Schema::hasTable('bac_chair_reviews')) {
            Schema::create('bac_chair_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('procurement_document_id')->constrained('procurement_documents')->cascadeOnDelete();
                $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('review_status')->default('pending')->index();
                $table->string('decision')->nullable()->index();
                $table->text('remarks')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bac_chair_reviews');
    }
};
