<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ppmp_items') || Schema::hasColumn('ppmp_items', 'is_bold')) {
            return;
        }

        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->boolean('is_bold')->default(false)->after('remarks');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ppmp_items') || ! Schema::hasColumn('ppmp_items', 'is_bold')) {
            return;
        }

        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->dropColumn('is_bold');
        });
    }
};
