<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ppmps')) {
            Schema::create('ppmps', function (Blueprint $table) {
                $table->id();
                $table->string('ppmp_no')->nullable();
                $table->unsignedSmallInteger('fiscal_year')->default(2026);
                $table->string('end_user_unit');
                $table->string('plan_type')->default('indicative');
                $table->string('status')->default('draft');
                $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
                $table->string('office_name')->nullable();
                $table->string('prepared_by_name')->nullable();
                $table->string('prepared_by_position')->nullable();
                $table->string('submitted_by_name')->nullable();
                $table->string('submitted_by_position')->nullable();
                $table->date('prepared_date')->nullable();
                $table->date('submitted_date')->nullable();
                $table->decimal('total_budget', 15, 2)->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['fiscal_year', 'status']);
                $table->index(['office_id', 'created_by']);
            });
        }

        if (! Schema::hasTable('ppmp_items')) {
            Schema::create('ppmp_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ppmp_id')->nullable()->constrained('ppmps')->cascadeOnDelete();
                $table->foreignId('procurement_document_id')->nullable()->constrained('procurement_documents')->cascadeOnDelete();
                $table->unsignedInteger('row_order')->default(0);
                $table->string('item_no')->nullable();
                $table->text('general_description');
                $table->string('project_type')->nullable();
                $table->longText('quantity_size')->nullable();
                $table->decimal('quantity', 14, 2)->default(0);
                $table->string('unit')->nullable();
                $table->decimal('estimated_unit_cost', 14, 2)->default(0);
                $table->decimal('estimated_total_cost', 14, 2)->default(0);
                $table->string('procurement_mode')->nullable();
                $table->string('pre_procurement_conference')->nullable();
                $table->string('start_procurement_activity')->nullable();
                $table->string('end_procurement_activity')->nullable();
                $table->string('expected_delivery_period')->nullable();
                $table->string('source_of_funds')->nullable();
                $table->text('attached_supporting_documents')->nullable();
                $table->string('schedule_quarter')->nullable();
                $table->string('category')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->index(['ppmp_id', 'row_order']);
                $table->index(['procurement_document_id', 'item_no']);
            });

            return;
        }

        if (Schema::hasColumn('ppmp_items', 'procurement_document_id') && DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE ppmp_items MODIFY procurement_document_id BIGINT UNSIGNED NULL');
        }

        Schema::table('ppmp_items', function (Blueprint $table) {
            if (! Schema::hasColumn('ppmp_items', 'ppmp_id')) {
                $table->foreignId('ppmp_id')->nullable()->after('id')->constrained('ppmps')->cascadeOnDelete();
            }

            if (! Schema::hasColumn('ppmp_items', 'row_order')) {
                $table->unsignedInteger('row_order')->default(0)->after('ppmp_id');
            }

            if (! Schema::hasColumn('ppmp_items', 'project_type')) {
                $table->string('project_type')->nullable()->after('general_description');
            }

            if (! Schema::hasColumn('ppmp_items', 'quantity_size')) {
                $table->longText('quantity_size')->nullable()->after('project_type');
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
    }

    public function down(): void
    {
        if (Schema::hasTable('ppmp_items') && Schema::hasColumn('ppmp_items', 'ppmp_id')) {
            Schema::table('ppmp_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('ppmp_id');
            });
        }

        Schema::dropIfExists('ppmps');
    }
};
