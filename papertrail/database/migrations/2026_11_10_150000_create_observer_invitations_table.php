<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observer_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prepared_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('competitive_bidding_record_id')->nullable()->constrained('competitive_bidding_records')->nullOnDelete();
            $table->json('payload');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observer_invitations');
    }
};
