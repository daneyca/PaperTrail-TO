<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_document_checks')) {
            return;
        }

        Schema::create('ai_document_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_type');
            $table->unsignedBigInteger('document_id');
            $table->string('document_tracking_number')->nullable();
            $table->unsignedTinyInteger('completeness_score')->nullable();
            $table->string('status')->default('processing')->index();
            $table->json('missing_requirements')->nullable();
            $table->json('warnings')->nullable();
            $table->text('recommendations')->nullable();
            $table->longText('ai_response')->nullable();
            $table->timestamps();

            $table->index(['document_type', 'document_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_document_checks');
    }
};
