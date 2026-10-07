<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_summary_reports')) {
            return;
        }

        Schema::create('ai_summary_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_period')->nullable()->index();
            $table->string('summary_type')->nullable()->index();
            $table->json('summary_data')->nullable();
            $table->text('ai_summary')->nullable();
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['summary_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_summary_reports');
    }
};
