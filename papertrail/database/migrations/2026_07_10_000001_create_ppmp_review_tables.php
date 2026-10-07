<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('procurement_documents')) {
            Schema::table('procurement_documents', function (Blueprint $table) {
                if (!Schema::hasColumn('procurement_documents', 'ppmp_review_status')) {
                    $table->string('ppmp_review_status')->nullable()->index()->after('accepted_at');
                }

                if (!Schema::hasColumn('procurement_documents', 'ppmp_reviewed_by_user_id')) {
                    $table->foreignId('ppmp_reviewed_by_user_id')->nullable()->after('ppmp_review_status')->constrained('users')->nullOnDelete();
                }

                if (!Schema::hasColumn('procurement_documents', 'ppmp_review_started_at')) {
                    $table->timestamp('ppmp_review_started_at')->nullable()->after('ppmp_reviewed_by_user_id');
                }

                if (!Schema::hasColumn('procurement_documents', 'ppmp_reviewed_at')) {
                    $table->timestamp('ppmp_reviewed_at')->nullable()->after('ppmp_review_started_at');
                }

                if (!Schema::hasColumn('procurement_documents', 'ppmp_remarks')) {
                    $table->text('ppmp_remarks')->nullable()->after('ppmp_reviewed_at');
                }
            });
        }

        if (!Schema::hasTable('ppmp_reviews')) {
            Schema::create('ppmp_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('procurement_document_id')->constrained('procurement_documents')->cascadeOnDelete();
                $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('review_status')->index();
                $table->text('remarks')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['procurement_document_id', 'review_status'], 'ppmp_review_document_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ppmp_reviews');

        if (Schema::hasTable('procurement_documents')) {
            Schema::table('procurement_documents', function (Blueprint $table) {
                if (Schema::hasColumn('procurement_documents', 'ppmp_remarks')) {
                    $table->dropColumn('ppmp_remarks');
                }

                if (Schema::hasColumn('procurement_documents', 'ppmp_reviewed_at')) {
                    $table->dropColumn('ppmp_reviewed_at');
                }

                if (Schema::hasColumn('procurement_documents', 'ppmp_review_started_at')) {
                    $table->dropColumn('ppmp_review_started_at');
                }

                if (Schema::hasColumn('procurement_documents', 'ppmp_reviewed_by_user_id')) {
                    $table->dropConstrainedForeignId('ppmp_reviewed_by_user_id');
                }

                if (Schema::hasColumn('procurement_documents', 'ppmp_review_status')) {
                    $table->dropColumn('ppmp_review_status');
                }
            });
        }
    }
};
