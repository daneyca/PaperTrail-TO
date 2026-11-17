<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve the highest counter if older assignments created duplicates.
        DB::transaction(function (): void {
            $duplicates = DB::table('pr_number_sequences')
                ->whereNotNull('prefix')
                ->select(['fiscal_year', 'prefix'])
                ->groupBy('fiscal_year', 'prefix')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($duplicates as $duplicate) {
                $rows = DB::table('pr_number_sequences')
                    ->where('fiscal_year', $duplicate->fiscal_year)
                    ->where('prefix', $duplicate->prefix)
                    ->orderBy('id')->lockForUpdate()->get();
                $first = $rows->first();

                DB::table('pr_number_sequences')->where('id', $first->id)->update([
                    'last_sequence' => (int) $rows->max('last_sequence'),
                ]);
                DB::table('pr_number_sequences')->whereIn('id', $rows->skip(1)->pluck('id'))->delete();
            }
        });

        Schema::table('pr_number_sequences', function (Blueprint $table): void {
            $table->unique(['fiscal_year', 'prefix'], 'pr_number_sequences_year_prefix_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pr_number_sequences', function (Blueprint $table): void {
            $table->dropUnique('pr_number_sequences_year_prefix_unique');
        });
    }
};
