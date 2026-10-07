<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_requirement_rules')) {
            Schema::create('document_requirement_rules', function (Blueprint $table) {
                $table->id();
                $table->string('document_type')->nullable()->index();
                $table->string('rule_name')->nullable();
                $table->string('requirement_type')->nullable()->index();
                $table->string('field_key')->nullable();
                $table->string('attachment_category')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_required')->default(true);
                $table->string('severity')->default('warning')->index();
                $table->string('applies_to_status')->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->integer('sort_order')->default(0);
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_assist_rules')) {
            Schema::create('ai_assist_rules', function (Blueprint $table) {
                $table->id();
                $table->string('feature_type')->nullable()->index();
                $table->string('document_type')->nullable()->index();
                $table->string('rule_name')->nullable();
                $table->text('prompt_instruction')->nullable();
                $table->json('expected_output_schema')->nullable();
                $table->text('system_note')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->integer('sort_order')->default(0);
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('routing_rules')) {
            Schema::create('routing_rules', function (Blueprint $table) {
                $table->id();
                $table->string('document_type')->nullable()->index();
                $table->string('current_status')->nullable()->index();
                $table->string('next_status')->nullable();
                $table->string('from_role')->nullable();
                $table->string('to_role')->nullable();
                $table->foreignId('from_office_id')->nullable()->constrained('offices')->nullOnDelete();
                $table->foreignId('to_office_id')->nullable()->constrained('offices')->nullOnDelete();
                $table->string('route_label')->nullable();
                $table->text('rule_description')->nullable();
                $table->boolean('requires_signature')->default(false);
                $table->boolean('requires_attachment_check')->default(false);
                $table->boolean('is_active')->default(true)->index();
                $table->integer('sort_order')->default(0);
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('delay_threshold_rules')) {
            Schema::create('delay_threshold_rules', function (Blueprint $table) {
                $table->id();
                $table->string('document_type')->nullable()->index();
                $table->string('stage')->nullable()->index();
                $table->string('status')->nullable()->index();
                $table->unsignedInteger('low_risk_days')->default(1);
                $table->unsignedInteger('medium_risk_days')->default(3);
                $table->unsignedInteger('high_risk_days')->default(5);
                $table->unsignedInteger('critical_risk_days')->default(7);
                $table->string('notify_role')->nullable();
                $table->foreignId('notify_office_id')->nullable()->constrained('offices')->nullOnDelete();
                $table->boolean('is_active')->default(true)->index();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('notification_templates')) {
            Schema::create('notification_templates', function (Blueprint $table) {
                $table->id();
                $table->string('template_key')->nullable()->unique();
                $table->string('channel')->default('email')->index();
                $table->string('subject')->nullable();
                $table->longText('body')->nullable();
                $table->string('action_text')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('delay_threshold_rules');
        Schema::dropIfExists('routing_rules');
        Schema::dropIfExists('ai_assist_rules');
        Schema::dropIfExists('document_requirement_rules');
    }
};
