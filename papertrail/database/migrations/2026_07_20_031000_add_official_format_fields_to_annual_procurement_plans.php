<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('annual_procurement_plans')) {
            Schema::table('annual_procurement_plans', function (Blueprint $table) {
                if (! Schema::hasColumn('annual_procurement_plans', 'app_no')) {
                    $table->string('app_no')->nullable()->unique()->after('app_number');
                }

                if (! Schema::hasColumn('annual_procurement_plans', 'municipality')) {
                    $table->string('municipality')->default('MUNICIPALITY OF TOMAS OPPUS')->after('fiscal_year');
                }

                if (! Schema::hasColumn('annual_procurement_plans', 'province')) {
                    $table->string('province')->default('Province of Southern Leyte')->after('municipality');
                }

                if (! Schema::hasColumn('annual_procurement_plans', 'plan_type')) {
                    $table->string('plan_type')->default('indicative')->after('province');
                }

                if (! Schema::hasColumn('annual_procurement_plans', 'update_version_no')) {
                    $table->string('update_version_no')->nullable()->after('plan_type');
                }

                if (! Schema::hasColumn('annual_procurement_plans', 'prepared_by_name')) {
                    $table->string('prepared_by_name')->nullable()->after('return_reason');
                }

                if (! Schema::hasColumn('annual_procurement_plans', 'prepared_by_position')) {
                    $table->string('prepared_by_position')->nullable()->after('prepared_by_name');
                }

                if (! Schema::hasColumn('annual_procurement_plans', 'prepared_by_office')) {
                    $table->string('prepared_by_office')->nullable()->after('prepared_by_position');
                }

                if (! Schema::hasColumn('annual_procurement_plans', 'recommended_by_name')) {
                    $table->string('recommended_by_name')->nullable()->after('prepared_by_office');
                }

                if (! Schema::hasColumn('annual_procurement_plans', 'recommended_by_position')) {
                    $table->string('recommended_by_position')->nullable()->after('recommended_by_name');
                }

                if (! Schema::hasColumn('annual_procurement_plans', 'recommended_by_office')) {
                    $table->string('recommended_by_office')->nullable()->after('recommended_by_position');
                }

                if (! Schema::hasColumn('annual_procurement_plans', 'approved_by_name')) {
                    $table->string('approved_by_name')->nullable()->after('recommended_by_office');
                }

                if (! Schema::hasColumn('annual_procurement_plans', 'approved_by_position')) {
                    $table->string('approved_by_position')->nullable()->after('approved_by_name');
                }

                if (! Schema::hasColumn('annual_procurement_plans', 'approved_by_office')) {
                    $table->string('approved_by_office')->nullable()->after('approved_by_position');
                }

                if (! Schema::hasColumn('annual_procurement_plans', 'prepared_date')) {
                    $table->date('prepared_date')->nullable()->after('approved_by_office');
                }

                if (! Schema::hasColumn('annual_procurement_plans', 'recommended_date')) {
                    $table->date('recommended_date')->nullable()->after('prepared_date');
                }

                if (! Schema::hasColumn('annual_procurement_plans', 'approved_date')) {
                    $table->date('approved_date')->nullable()->after('recommended_date');
                }

                if (! Schema::hasColumn('annual_procurement_plans', 'total_epa_budget')) {
                    $table->decimal('total_epa_budget', 15, 2)->default(0)->after('approved_date');
                }

                if (! Schema::hasColumn('annual_procurement_plans', 'total_cse_budget')) {
                    $table->decimal('total_cse_budget', 15, 2)->default(0)->after('total_epa_budget');
                }

                if (! Schema::hasColumn('annual_procurement_plans', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->after('approved_by_user_id')->constrained('users')->nullOnDelete();
                }
            });

            DB::table('annual_procurement_plans')
                ->whereNull('app_no')
                ->whereNotNull('app_number')
                ->update(['app_no' => DB::raw('app_number')]);
        }

        if (Schema::hasTable('annual_procurement_plan_items')) {
            Schema::table('annual_procurement_plan_items', function (Blueprint $table) {
                if (! Schema::hasColumn('annual_procurement_plan_items', 'row_order')) {
                    $table->unsignedInteger('row_order')->default(0)->after('annual_procurement_plan_id');
                }

                if (! Schema::hasColumn('annual_procurement_plan_items', 'category')) {
                    $table->string('category')->default('general_requirements')->after('row_order');
                }

                if (! Schema::hasColumn('annual_procurement_plan_items', 'project_title')) {
                    $table->text('project_title')->nullable()->after('category');
                }

                if (! Schema::hasColumn('annual_procurement_plan_items', 'end_user_unit')) {
                    $table->string('end_user_unit')->nullable()->after('project_title');
                }

                if (! Schema::hasColumn('annual_procurement_plan_items', 'general_description')) {
                    $table->text('general_description')->nullable()->after('end_user_unit');
                }

                if (! Schema::hasColumn('annual_procurement_plan_items', 'early_procurement_activity')) {
                    $table->string('early_procurement_activity')->nullable()->after('mode_of_procurement');
                }

                if (! Schema::hasColumn('annual_procurement_plan_items', 'bid_evaluation_criteria')) {
                    $table->text('bid_evaluation_criteria')->nullable()->after('early_procurement_activity');
                }

                if (! Schema::hasColumn('annual_procurement_plan_items', 'start_procurement_activity')) {
                    $table->string('start_procurement_activity')->nullable()->after('bid_evaluation_criteria');
                }

                if (! Schema::hasColumn('annual_procurement_plan_items', 'end_procurement_activity')) {
                    $table->string('end_procurement_activity')->nullable()->after('start_procurement_activity');
                }

                if (! Schema::hasColumn('annual_procurement_plan_items', 'estimated_budget')) {
                    $table->decimal('estimated_budget', 15, 2)->default(0)->after('source_of_funds');
                }

                if (! Schema::hasColumn('annual_procurement_plan_items', 'procurement_strategy_or_tools')) {
                    $table->text('procurement_strategy_or_tools')->nullable()->after('estimated_budget');
                }
            });

            DB::table('annual_procurement_plan_items')
                ->whereNull('project_title')
                ->whereNotNull('procurement_program_project')
                ->update([
                    'project_title' => DB::raw('procurement_program_project'),
                    'end_user_unit' => DB::raw('pmo_end_user'),
                    'start_procurement_activity' => DB::raw('ads_post_ib_rei'),
                    'end_procurement_activity' => DB::raw('contract_signing'),
                    'estimated_budget' => DB::raw('COALESCE(estimated_total, 0)'),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('annual_procurement_plan_items')) {
            Schema::table('annual_procurement_plan_items', function (Blueprint $table) {
                foreach ([
                    'procurement_strategy_or_tools',
                    'estimated_budget',
                    'end_procurement_activity',
                    'start_procurement_activity',
                    'bid_evaluation_criteria',
                    'early_procurement_activity',
                    'general_description',
                    'end_user_unit',
                    'project_title',
                    'category',
                    'row_order',
                ] as $column) {
                    if (Schema::hasColumn('annual_procurement_plan_items', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('annual_procurement_plans')) {
            Schema::table('annual_procurement_plans', function (Blueprint $table) {
                if (Schema::hasColumn('annual_procurement_plans', 'created_by')) {
                    $table->dropConstrainedForeignId('created_by');
                }

                foreach ([
                    'total_cse_budget',
                    'total_epa_budget',
                    'approved_date',
                    'recommended_date',
                    'prepared_date',
                    'approved_by_office',
                    'approved_by_position',
                    'approved_by_name',
                    'recommended_by_office',
                    'recommended_by_position',
                    'recommended_by_name',
                    'prepared_by_office',
                    'prepared_by_position',
                    'prepared_by_name',
                    'update_version_no',
                    'plan_type',
                    'province',
                    'municipality',
                    'app_no',
                ] as $column) {
                    if (Schema::hasColumn('annual_procurement_plans', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
