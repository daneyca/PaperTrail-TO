<?php

namespace App\Console\Commands;

use App\Models\AnnualProcurementPlan;
use App\Models\AnnualProcurementPlanItem;
use App\Models\AnnualProcurementPlanVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MergeDuplicateAnnualProcurementPlans extends Command
{
    protected $signature = 'app:merge-duplicate-annual-apps {--execute : Apply the merge instead of only reporting it}';

    protected $description = 'Merge accidental duplicate APP records into the main fiscal-year APP record.';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $groups = AnnualProcurementPlan::query()
            ->whereNotNull('fiscal_year')
            ->where('status', '!=', AnnualProcurementPlan::STATUS_CANCELLED)
            ->get()
            ->groupBy('fiscal_year')
            ->filter(fn ($apps) => $apps->count() > 1);

        if ($groups->isEmpty()) {
            $this->info('No duplicate APP records found.');

            return self::SUCCESS;
        }

        foreach ($groups as $fiscalYear => $apps) {
            $ordered = $apps
                ->sortBy([
                    fn (AnnualProcurementPlan $app) => filled($app->app_number) ? 0 : 1,
                    fn (AnnualProcurementPlan $app) => $app->app_number ?? 'ZZZ',
                    fn (AnnualProcurementPlan $app) => $app->created_at?->timestamp ?? 0,
                    fn (AnnualProcurementPlan $app) => $app->id,
                ])
                ->values();
            $primary = $ordered->first();
            $duplicates = $ordered->slice(1);

            $this->line("FY {$fiscalYear}: keeping {$primary->displayNumber()} and merging {$duplicates->count()} duplicate record(s).");

            if (! $execute) {
                foreach ($duplicates as $duplicate) {
                    $this->line("  - would merge {$duplicate->displayNumber()}");
                }

                continue;
            }

            DB::transaction(function () use ($primary, $duplicates): void {
                $primary->refresh()->load('items');
                $this->snapshotPrimary($primary);
                $nextVersion = $this->currentVersion($primary) + 1;
                $movedRows = 0;

                foreach ($duplicates as $duplicate) {
                    $duplicate->refresh()->load('items');
                    $existingKeys = $this->sourceKeys($primary);

                    foreach ($duplicate->items as $item) {
                        $key = $this->sourceKey($item);

                        if ($key !== null && isset($existingKeys[$key])) {
                            $item->delete();
                            continue;
                        }

                        $item->forceFill([
                            'annual_procurement_plan_id' => $primary->id,
                            'app_version' => Schema::hasColumn('annual_procurement_plan_items', 'app_version') ? $nextVersion : $item->app_version,
                            'sort_order' => $primary->items()->max('sort_order') + 1,
                            'row_order' => $primary->items()->max('row_order') + 1,
                        ])->save();

                        if ($key !== null) {
                            $existingKeys[$key] = true;
                        }
                        $movedRows++;
                    }

                    $duplicate->forceFill([
                        'status' => AnnualProcurementPlan::STATUS_CANCELLED,
                        'remarks' => trim(($duplicate->remarks ? $duplicate->remarks . "\n" : '') . "Merged into {$primary->displayNumber()} as duplicate APP cleanup."),
                    ])->save();
                }

                if ($movedRows > 0) {
                    $primary->refresh()->load('items');
                    $total = $primary->items->sum(fn (AnnualProcurementPlanItem $item) => (float) ($item->estimated_budget ?: $item->estimated_total ?: 0));

                    $primary->forceFill([
                        'plan_type' => 'update',
                        'update_version_no' => (string) $nextVersion,
                        'total_estimated_budget' => $total,
                        'items_json' => null,
                    ])->save();
                }
            });
        }

        $this->info($execute ? 'Duplicate APP merge complete.' : 'Dry run complete. Re-run with --execute to apply changes.');

        return self::SUCCESS;
    }

    private function snapshotPrimary(AnnualProcurementPlan $primary): void
    {
        if (! Schema::hasTable('annual_procurement_plan_versions')) {
            return;
        }

        $versionNo = $this->currentVersion($primary);

        AnnualProcurementPlanVersion::firstOrCreate(
            [
                'annual_procurement_plan_id' => $primary->id,
                'version_no' => $versionNo,
            ],
            [
                'app_number' => $primary->app_number,
                'app_no' => $primary->app_no,
                'document_reference_number' => $primary->document_reference_number,
                'fiscal_year' => $primary->fiscal_year,
                'plan_type' => $primary->plan_type,
                'update_version_no' => $primary->update_version_no,
                'title' => $primary->title,
                'status' => $primary->status,
                'prepared_by_user_id' => $primary->prepared_by_user_id,
                'submitted_by_user_id' => $primary->submitted_by_user_id,
                'approved_by_user_id' => $primary->approved_by_user_id,
                'submitted_at' => $primary->submitted_at,
                'approved_at' => $primary->approved_at,
                'returned_at' => $primary->returned_at,
                'return_reason' => $primary->return_reason,
                'total_epa_budget' => $primary->total_epa_budget,
                'total_cse_budget' => $primary->total_cse_budget,
                'total_estimated_budget' => $primary->total_estimated_budget,
                'total_personal_outlay' => $primary->total_personal_outlay,
                'total_mooe' => $primary->total_mooe,
                'total_co' => $primary->total_co,
                'document_html' => $primary->document_html,
                'document_text' => $primary->document_text,
                'items_json' => $primary->items_json ?: $primary->items->toArray(),
                'signatories_json' => $primary->signatories_json,
                'remarks' => $primary->remarks,
                'change_summary' => 'Version preserved before duplicate APP cleanup.',
            ],
        );
    }

    private function currentVersion(AnnualProcurementPlan $app): int
    {
        if ($app->plan_type === 'update' && filled($app->update_version_no)) {
            return max((int) preg_replace('/[^0-9]/', '', (string) $app->update_version_no), 1);
        }

        return 1;
    }

    private function sourceKeys(AnnualProcurementPlan $app): array
    {
        return $app->items
            ->mapWithKeys(fn (AnnualProcurementPlanItem $item) => ($key = $this->sourceKey($item)) ? [$key => true] : [])
            ->all();
    }

    private function sourceKey(AnnualProcurementPlanItem $item): ?string
    {
        if (! filled($item->source_ppmp_document_id) || ! filled($item->source_ppmp_item_id)) {
            return null;
        }

        return (int) $item->source_ppmp_document_id . ':' . (int) $item->source_ppmp_item_id;
    }
}
