<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('procurement_documents', 'bac_chair_confirmation_status')) {
                $table->string('bac_chair_confirmation_status')->nullable()->index()->after('bac_chair_remarks');
            }
            if (!Schema::hasColumn('procurement_documents', 'bac_chair_confirmed_at')) {
                $table->timestamp('bac_chair_confirmed_at')->nullable()->after('bac_chair_confirmation_status');
            }
            if (!Schema::hasColumn('procurement_documents', 'bac_chair_confirmation_remarks')) {
                $table->text('bac_chair_confirmation_remarks')->nullable()->after('bac_chair_confirmed_at');
            }
        });
    }

    public function down(): void
    {
        // Intentionally leave confirmation history columns intact on rollback.
    }
};
