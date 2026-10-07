<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bac_deliberations')) {
            Schema::create('bac_deliberations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('procurement_document_id')->constrained('procurement_documents')->cascadeOnDelete();
                $table->string('deliberation_number')->nullable()->unique();
                $table->string('title');
                $table->text('agenda')->nullable();
                $table->string('status')->default('scheduled')->index();
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('chair_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('bac_deliberation_participants')) {
            Schema::create('bac_deliberation_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bac_deliberation_id')->constrained('bac_deliberations')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role_name')->nullable();
                $table->string('attendance_status')->default('pending')->index();
                $table->string('recommendation')->nullable();
                $table->text('recommendation_remarks')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
                $table->unique(['bac_deliberation_id', 'user_id'], 'bac_delib_participant_unique');
            });
        }

        if (!Schema::hasTable('bac_deliberation_comments')) {
            Schema::create('bac_deliberation_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bac_deliberation_id')->constrained('bac_deliberations')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('comment');
                $table->string('visibility')->default('internal')->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bac_deliberation_comments');
        Schema::dropIfExists('bac_deliberation_participants');
        Schema::dropIfExists('bac_deliberations');
    }
};
