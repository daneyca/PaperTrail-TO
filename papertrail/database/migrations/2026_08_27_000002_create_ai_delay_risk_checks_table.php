<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_delay_risk_checks')) {
            return;
        }

        Schema::create('ai_delay_risk_checks', function (Blueprint $table) {
            $table->id();
            $table->string('document_type');
            $table->unsignedBigInteger('document_id');
            $table->string('tracking_number')->nullable();
            $table->string('current_status')->nullable();
            $table->string('current_holder')->nullable();
            $table->string('risk_level')->default('LOW')->index();
            $table->unsignedInteger('delay_days')->nullable();
            $table->json('risk_factors')->nullable();
            $table->text('recommendations')->nullable();
            $table->longText('ai_response')->nullable();
            $table->string('status')->default('processing')->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['document_type', 'document_id']);
            $table->index(['created_by_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_delay_risk_checks');
    }
};
