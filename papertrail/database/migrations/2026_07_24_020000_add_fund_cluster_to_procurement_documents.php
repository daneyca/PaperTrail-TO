<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('procurement_documents', 'fund_cluster')) {
                $table->string('fund_cluster')->nullable()->after('department_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table) {
            if (Schema::hasColumn('procurement_documents', 'fund_cluster')) {
                $table->dropColumn('fund_cluster');
            }
        });
    }
};
