<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_acceptance_records', function (Blueprint $table) {
            $table->date('report_date')->nullable();
            $table->date('invoice_date')->nullable();
            $table->string('inspector_name')->nullable();
            $table->string('inspection_chair_name')->nullable();
            $table->string('acceptance_officer_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inspection_acceptance_records', function (Blueprint $table) {
            $table->dropColumn(['report_date', 'invoice_date', 'inspector_name', 'inspection_chair_name', 'acceptance_officer_name']);
        });
    }
};
