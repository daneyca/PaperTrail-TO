<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dashboard_events')) {
            return;
        }

        Schema::create('dashboard_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('event_date');
            $table->time('event_time')->nullable();
            $table->string('event_type')->nullable();
            $table->string('visibility')->default('office');
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('related_document_type')->nullable();
            $table->unsignedBigInteger('related_document_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('color')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamps();

            $table->index(['event_date', 'visibility']);
            $table->index(['office_id', 'event_date']);
            $table->index(['role_id', 'event_date']);
            $table->index(['created_by', 'event_date']);
            $table->index(['related_document_type', 'related_document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_events');
    }
};
