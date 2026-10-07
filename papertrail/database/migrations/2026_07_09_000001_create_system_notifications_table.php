<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_notifications')) {
            Schema::create('system_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('title');
                $table->text('message');
                $table->string('type')->default('info');
                $table->string('module')->nullable();
                $table->string('related_type')->nullable();
                $table->unsignedBigInteger('related_id')->nullable();
                $table->string('action_url')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'read_at']);
                $table->index(['related_type', 'related_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_notifications');
    }
};
