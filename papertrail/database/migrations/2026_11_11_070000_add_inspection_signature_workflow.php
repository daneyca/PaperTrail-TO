<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_acceptance_records', function (Blueprint $table) {
            $table->unsignedInteger('signature_revision')->default(0);
            $table->char('signature_document_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inspection_acceptance_records', function (Blueprint $table) {
            $table->dropColumn(['signature_revision', 'signature_document_hash']);
        });
    }
};
