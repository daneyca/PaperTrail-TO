<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('procurement_documents', 'department_name')) {
                $table->string('department_name')->nullable()->after('purpose');
            }
            if (! Schema::hasColumn('procurement_documents', 'section')) {
                $table->string('section')->nullable()->after('department_name');
            }
            if (! Schema::hasColumn('procurement_documents', 'pr_no')) {
                $table->string('pr_no')->nullable()->index()->after('section');
            }
            if (! Schema::hasColumn('procurement_documents', 'pr_date')) {
                $table->date('pr_date')->nullable()->after('pr_no');
            }
            if (! Schema::hasColumn('procurement_documents', 'sai_no')) {
                $table->string('sai_no')->nullable()->after('pr_date');
            }
            if (! Schema::hasColumn('procurement_documents', 'sai_date')) {
                $table->date('sai_date')->nullable()->after('sai_no');
            }
            if (! Schema::hasColumn('procurement_documents', 'alobs_no')) {
                $table->string('alobs_no')->nullable()->after('sai_date');
            }
            if (! Schema::hasColumn('procurement_documents', 'alobs_date')) {
                $table->date('alobs_date')->nullable()->after('alobs_no');
            }
            if (! Schema::hasColumn('procurement_documents', 'requested_by_name')) {
                $table->string('requested_by_name')->nullable()->after('alobs_date');
            }
            if (! Schema::hasColumn('procurement_documents', 'requested_by_designation')) {
                $table->string('requested_by_designation')->nullable()->after('requested_by_name');
            }
            if (! Schema::hasColumn('procurement_documents', 'approved_by_name')) {
                $table->string('approved_by_name')->nullable()->after('requested_by_designation');
            }
            if (! Schema::hasColumn('procurement_documents', 'approved_by_designation')) {
                $table->string('approved_by_designation')->nullable()->after('approved_by_name');
            }
            if (! Schema::hasColumn('procurement_documents', 'certification_text')) {
                $table->text('certification_text')->nullable()->after('approved_by_designation');
            }
        });

        if (Schema::hasTable('purchase_request_items')) {
            Schema::table('purchase_request_items', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_request_items', 'unit_of_issue')) {
                    $table->string('unit_of_issue')->nullable()->after('quantity');
                }
                if (! Schema::hasColumn('purchase_request_items', 'description')) {
                    $table->text('description')->nullable()->after('unit_of_issue');
                }
                if (! Schema::hasColumn('purchase_request_items', 'stock_no')) {
                    $table->string('stock_no')->nullable()->after('description');
                }
                if (! Schema::hasColumn('purchase_request_items', 'estimated_cost')) {
                    $table->decimal('estimated_cost', 14, 2)->default(0)->after('estimated_unit_cost');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table) {
            foreach ([
                'department_name',
                'section',
                'pr_no',
                'pr_date',
                'sai_no',
                'sai_date',
                'alobs_no',
                'alobs_date',
                'requested_by_name',
                'requested_by_designation',
                'approved_by_name',
                'approved_by_designation',
                'certification_text',
            ] as $column) {
                if (Schema::hasColumn('procurement_documents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (Schema::hasTable('purchase_request_items')) {
            Schema::table('purchase_request_items', function (Blueprint $table) {
                foreach (['unit_of_issue', 'description', 'stock_no', 'estimated_cost'] as $column) {
                    if (Schema::hasColumn('purchase_request_items', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
