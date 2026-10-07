<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('group');
                $table->string('label');
                $table->longText('value')->nullable();
                $table->string('type');
                $table->json('options')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_public')->default(false);
                $table->boolean('is_locked')->default(false);
                $table->integer('sort_order')->default(0);
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
