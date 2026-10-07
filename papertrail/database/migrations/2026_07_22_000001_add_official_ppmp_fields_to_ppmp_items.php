<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('procurement_documents')) {
            Schema::table('procurement_documents', function (Blueprint $table) {
                if (! Schema::hasColumn('procurement_documents', 'ppmp_no')) {
                    $table->string('ppmp_no')->nullable()->after('tracking_number');
                }

                if (! Schema::hasColumn('procurement_documents', 'ppmp_plan_type')) {
                    $table->string('ppmp_plan_type')->default('indicative')->after('ppmp_no');
                }
            });
        }

        if (! Schema::hasTable('ppmp_items')) {
            return;
        }

        Schema::table('ppmp_items', function (Blueprint $table) {
            if (! Schema::hasColumn('ppmp_items', 'row_order')) {
                $table->unsignedInteger('row_order')->default(0)->after('procurement_document_id');
            }

            if (! Schema::hasColumn('ppmp_items', 'project_type')) {
                $table->string('project_type')->nullable()->after('general_description');
            }

            if (! Schema::hasColumn('ppmp_items', 'quantity_size')) {
                $table->text('quantity_size')->nullable()->after('project_type');
            }

            if (! Schema::hasColumn('ppmp_items', 'pre_procurement_conference')) {
                $table->string('pre_procurement_conference')->nullable()->after('procurement_mode');
            }

            if (! Schema::hasColumn('ppmp_items', 'start_procurement_activity')) {
                $table->string('start_procurement_activity')->nullable()->after('pre_procurement_conference');
            }

            if (! Schema::hasColumn('ppmp_items', 'end_procurement_activity')) {
                $table->string('end_procurement_activity')->nullable()->after('start_procurement_activity');
            }

            if (! Schema::hasColumn('ppmp_items', 'expected_delivery_period')) {
                $table->string('expected_delivery_period')->nullable()->after('end_procurement_activity');
            }

            if (! Schema::hasColumn('ppmp_items', 'source_of_funds')) {
                $table->string('source_of_funds')->nullable()->after('expected_delivery_period');
            }

            if (! Schema::hasColumn('ppmp_items', 'attached_supporting_documents')) {
                $table->text('attached_supporting_documents')->nullable()->after('estimated_total_cost');
            }
        });

        DB::table('ppmp_items')
            ->whereNull('quantity_size')
            ->update([
                'quantity_size' => DB::raw("TRIM(CONCAT(COALESCE(quantity, ''), ' ', COALESCE(unit, '')))"),
                'start_procurement_activity' => DB::raw('schedule_quarter'),
                'end_procurement_activity' => DB::raw('schedule_quarter'),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('ppmp_items')) {
            Schema::table('ppmp_items', function (Blueprint $table) {
                foreach ([
                    'attached_supporting_documents',
                    'source_of_funds',
                    'expected_delivery_period',
                    'end_procurement_activity',
                    'start_procurement_activity',
                    'pre_procurement_conference',
                    'quantity_size',
                    'project_type',
                    'row_order',
                ] as $column) {
                    if (Schema::hasColumn('ppmp_items', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('procurement_documents')) {
            Schema::table('procurement_documents', function (Blueprint $table) {
                foreach (['ppmp_plan_type', 'ppmp_no'] as $column) {
                    if (Schema::hasColumn('procurement_documents', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
