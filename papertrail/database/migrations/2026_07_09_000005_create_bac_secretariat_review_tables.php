<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('procurement_documents', 'bac_secretariat_status')) {
                $table->string('bac_secretariat_status')->nullable()->index()->after('responsibility_center');
            }
            if (!Schema::hasColumn('procurement_documents', 'bac_secretariat_received_by_user_id')) {
                $table->unsignedBigInteger('bac_secretariat_received_by_user_id')->nullable()->after('bac_secretariat_status');
                $table->foreign('bac_secretariat_received_by_user_id', 'pt_bac_received_by_fk')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('procurement_documents', 'bac_secretariat_received_at')) {
                $table->timestamp('bac_secretariat_received_at')->nullable()->after('bac_secretariat_received_by_user_id');
            }
            if (!Schema::hasColumn('procurement_documents', 'bac_secretariat_review_started_at')) {
                $table->timestamp('bac_secretariat_review_started_at')->nullable()->after('bac_secretariat_received_at');
            }
            if (!Schema::hasColumn('procurement_documents', 'bac_secretariat_processed_at')) {
                $table->timestamp('bac_secretariat_processed_at')->nullable()->after('bac_secretariat_review_started_at');
            }
            if (!Schema::hasColumn('procurement_documents', 'bac_secretariat_remarks')) {
                $table->text('bac_secretariat_remarks')->nullable()->after('bac_secretariat_processed_at');
            }
        });

        if (!Schema::hasTable('bac_secretariat_reviews')) {
            Schema::create('bac_secretariat_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('procurement_document_id')->constrained('procurement_documents')->cascadeOnDelete();
                $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('review_status')->default('pending')->index();
                $table->text('remarks')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bac_secretariat_reviews');
    }
};
