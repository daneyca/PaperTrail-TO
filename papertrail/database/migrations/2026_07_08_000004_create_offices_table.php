<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('offices')) {
            Schema::create('offices', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name')->unique();
                $table->string('type');
                $table->text('description')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('offices');
    }
};
