<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['rfqs', 'purchase_orders'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'control_assigned_by_user_id')) {
                    $blueprint->foreignId('control_assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn($table, 'control_assigned_at')) {
                    $blueprint->timestamp('control_assigned_at')->nullable();
                }

                if (! Schema::hasColumn($table, 'control_remarks')) {
                    $blueprint->text('control_remarks')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['rfqs', 'purchase_orders'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::hasColumn($table, 'control_assigned_by_user_id')) {
                    $blueprint->dropConstrainedForeignId('control_assigned_by_user_id');
                }

                $columns = array_values(array_filter(
                    ['control_assigned_at', 'control_remarks'],
                    fn (string $column) => Schema::hasColumn($table, $column)
                ));

                if ($columns !== []) {
                    $blueprint->dropColumn($columns);
                }
            });
        }
    }
};
