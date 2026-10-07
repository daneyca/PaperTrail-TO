<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_document_metadata')) {
            return;
        }

        Schema::create('ai_document_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('document_type')->nullable()->index();
            $table->unsignedBigInteger('document_id')->nullable()->index();
            $table->foreignId('attachment_id')->nullable()->constrained('document_attachments')->nullOnDelete();
            $table->json('extracted_metadata')->nullable();
            $table->json('classification_result')->nullable();
            $table->unsignedTinyInteger('confidence_score')->nullable();
            $table->string('status')->default('pending_review')->index();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->longText('ai_response')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['document_type', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_document_metadata');
    }
};
