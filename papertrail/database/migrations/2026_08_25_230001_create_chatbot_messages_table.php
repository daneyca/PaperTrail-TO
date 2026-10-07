<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chatbot_messages')) {
            Schema::create('chatbot_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('chatbot_conversations')->cascadeOnDelete();
                $table->string('role', 24);
                $table->longText('message');
                $table->unsignedInteger('tokens_used')->nullable();
                $table->timestamps();

                $table->index(['conversation_id', 'created_at']);
                $table->index('role');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_messages');
    }
};
