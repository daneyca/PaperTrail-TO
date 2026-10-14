<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfMissing('submitted_by_user_id', function (Blueprint $table) {
            $table->unsignedBigInteger('submitted_by_user_id')->nullable()->after('remarks');
        });
        $this->addColumnIfMissing('submitted_at', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('submitted_by_user_id');
        });
        $this->addColumnIfMissing('source_type', function (Blueprint $table) {
            $table->string('source_type')->nullable()->after('submitted_at');
        });
        $this->addColumnIfMissing('source_id', function (Blueprint $table) {
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
        });
        $this->addColumnIfMissing('source_reference', function (Blueprint $table) {
            $table->string('source_reference')->nullable()->after('source_id');
        });
        $this->addColumnIfMissing('source_status', function (Blueprint $table) {
            $table->string('source_status')->nullable()->after('source_reference');
        });
        $this->addColumnIfMissing('source_version', function (Blueprint $table) {
            $table->unsignedInteger('source_version')->default(0)->after('source_status');
        });

        if (! $this->foreignKeyExists('cb_supporting_submitted_by_fk')) {
            Schema::table('competitive_bidding_supporting_items', function (Blueprint $table) {
                $table->foreign('submitted_by_user_id', 'cb_supporting_submitted_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! $this->indexExists('cb_supporting_source_idx')) {
            Schema::table('competitive_bidding_supporting_items', function (Blueprint $table) {
                $table->index(['source_type', 'source_id'], 'cb_supporting_source_idx');
            });
        }

        DB::table('competitive_bidding_supporting_items')
            ->where('requirement_key', 'bac_secretariat_head_certification')
            ->update(['document_source' => 'BACSEC-004']);
    }

    public function down(): void
    {
        Schema::table('competitive_bidding_supporting_items', function (Blueprint $table) {
            $table->dropIndex('cb_supporting_source_idx');
            $table->dropForeign('cb_supporting_submitted_by_fk');
            $table->dropColumn([
                'submitted_by_user_id',
                'submitted_at',
                'source_type',
                'source_id',
                'source_reference',
                'source_status',
                'source_version',
            ]);
        });
    }

    private function addColumnIfMissing(string $column, callable $callback): void
    {
        if (! Schema::hasColumn('competitive_bidding_supporting_items', $column)) {
            Schema::table('competitive_bidding_supporting_items', $callback);
        }
    }

    private function foreignKeyExists(string $constraintName): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('table_name', 'competitive_bidding_supporting_items')
            ->where('constraint_name', $constraintName)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }

    private function indexExists(string $indexName): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'competitive_bidding_supporting_items')
            ->where('index_name', $indexName)
            ->exists();
    }
};
