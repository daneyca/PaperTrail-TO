<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bac_resolutions', function (Blueprint $table) {
            if (! Schema::hasColumn('bac_resolutions', 'contractor_name')) {
                $table->string('contractor_name')->nullable()->after('project_title');
            }

            if (! Schema::hasColumn('bac_resolutions', 'abc_amount')) {
                $table->decimal('abc_amount', 14, 2)->nullable()->after('supplier_contact');
            }

            if (! Schema::hasColumn('bac_resolutions', 'refund_amount')) {
                $table->decimal('refund_amount', 14, 2)->nullable()->after('abc_amount');
            }

            if (! Schema::hasColumn('bac_resolutions', 'body_json')) {
                $table->json('body_json')->nullable()->after('solicitation_no');
            }

            if (! Schema::hasColumn('bac_resolutions', 'approval_details')) {
                $table->json('approval_details')->nullable()->after('signatories');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bac_resolutions', function (Blueprint $table) {
            foreach (['approval_details', 'body_json', 'refund_amount', 'abc_amount', 'contractor_name'] as $column) {
                if (Schema::hasColumn('bac_resolutions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
