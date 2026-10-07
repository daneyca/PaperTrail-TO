<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs') || Schema::hasColumn('audit_logs', 'document_reference_number')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('document_reference_number', 32)->nullable()->index()->after('tracking_number');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('audit_logs') || ! Schema::hasColumn('audit_logs', 'document_reference_number')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropColumn('document_reference_number');
        });
    }
};
