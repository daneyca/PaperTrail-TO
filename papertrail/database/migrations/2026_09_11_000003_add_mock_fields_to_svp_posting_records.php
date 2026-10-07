<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('svp_posting_records')) {
            return;
        }

        if (! Schema::hasColumn('svp_posting_records', 'bac_resolution_reference')) {
            Schema::table('svp_posting_records', function (Blueprint $table) {
                $table->string('bac_resolution_reference')->nullable()->after('pr_reference');
            });
        }

        if (! Schema::hasColumn('svp_posting_records', 'posting_platform')) {
            Schema::table('svp_posting_records', function (Blueprint $table) {
                $table->string('posting_platform')->default('PhilGEPS')->after('procurement_method');
            });
        }

        if (! Schema::hasColumn('svp_posting_records', 'philgeps_reference_number')) {
            Schema::table('svp_posting_records', function (Blueprint $table) {
                $table->string('philgeps_reference_number')->nullable()->after('posting_platform');
            });
        }

        DB::table('svp_posting_records')->where('status', 'draft')->update(['status' => 'pending_posting']);
        DB::table('svp_posting_records')->where('status', 'completed')->update(['status' => 'posted']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('svp_posting_records')) {
            return;
        }

        Schema::table('svp_posting_records', function (Blueprint $table) {
            foreach (['philgeps_reference_number', 'posting_platform', 'bac_resolution_reference'] as $column) {
                if (Schema::hasColumn('svp_posting_records', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
