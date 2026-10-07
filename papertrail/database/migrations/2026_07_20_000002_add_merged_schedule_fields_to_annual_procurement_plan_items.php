<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('annual_procurement_plan_items')) {
            return;
        }

        Schema::table('annual_procurement_plan_items', function (Blueprint $table) {
            if (! Schema::hasColumn('annual_procurement_plan_items', 'schedule_merged')) {
                $table->text('schedule_merged')->nullable()->after('contract_signing');
            }

            if (! Schema::hasColumn('annual_procurement_plan_items', 'is_schedule_merged')) {
                $table->boolean('is_schedule_merged')->default(false)->after('schedule_merged');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('annual_procurement_plan_items')) {
            return;
        }

        Schema::table('annual_procurement_plan_items', function (Blueprint $table) {
            if (Schema::hasColumn('annual_procurement_plan_items', 'is_schedule_merged')) {
                $table->dropColumn('is_schedule_merged');
            }

            if (Schema::hasColumn('annual_procurement_plan_items', 'schedule_merged')) {
                $table->dropColumn('schedule_merged');
            }
        });
    }
};
