<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chatbot_messages')) {
            return;
        }

        Schema::table('chatbot_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('chatbot_messages', 'response_type')) {
                $table->string('response_type', 40)->default('text')->after('message');
            }

            if (! Schema::hasColumn('chatbot_messages', 'payload')) {
                $table->json('payload')->nullable()->after('response_type');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('chatbot_messages')) {
            return;
        }

        Schema::table('chatbot_messages', function (Blueprint $table) {
            if (Schema::hasColumn('chatbot_messages', 'payload')) {
                $table->dropColumn('payload');
            }

            if (Schema::hasColumn('chatbot_messages', 'response_type')) {
                $table->dropColumn('response_type');
            }
        });
    }
};
