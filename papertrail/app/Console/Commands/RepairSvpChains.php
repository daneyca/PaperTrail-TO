<?php

namespace App\Console\Commands;

use App\Models\AbstractQuotation;
use App\Models\BacResolution;
use App\Models\InspectionAcceptanceRecord;
use App\Models\ProcurementDocument;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Services\SvpChainService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairSvpChains extends Command
{
    protected $signature = 'papertrail:repair-svp-chains {--dry-run : Show counts without creating or updating chains}';

    protected $description = 'Create and repair SVP procurement chains from existing PR, BAC Resolution, RFQ, Abstract, PO, and Inspection records.';

    public function handle(SvpChainService $chains): int
    {
        $prs = ProcurementDocument::query()
            ->whereIn('document_type', ['PR', 'Purchase Request'])
            ->with('submittingOffice')
            ->get();

        if ($this->option('dry-run')) {
            $this->info("Purchase Requests found: {$prs->count()}");
            $this->info('Dry run only. No SVP chains were changed.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($prs, $chains) {
            $bar = $this->output->createProgressBar($prs->count());
            $bar->start();

            foreach ($prs as $pr) {
                $chain = $chains->findOrCreateFromPr($pr, null, 'Purchase Request linked during SVP chain repair');

                $resolution = BacResolution::query()
                    ->where('source_pr_document_id', $pr->id)
                    ->latest('updated_at')
                    ->first();

                if ($resolution) {
                    $chains->linkBacResolution($resolution, null, 'BAC Resolution linked during SVP chain repair');
                }

                $rfq = Rfq::query()
                    ->where(function ($query) use ($pr, $resolution) {
                        $query->where('source_pr_document_id', $pr->id);

                        if ($resolution) {
                            $query->orWhere('source_bac_resolution_id', $resolution->id);
                        }
                    })
                    ->latest('updated_at')
                    ->first();

                if ($rfq) {
                    $chains->linkRfq($rfq, null, 'RFQ linked during SVP chain repair');
                }

                $abstract = AbstractQuotation::query()
                    ->where(function ($query) use ($pr, $resolution, $rfq) {
                        $query->where('source_pr_document_id', $pr->id);

                        if ($resolution) {
                            $query->orWhere('source_bac_resolution_id', $resolution->id);
                        }

                        if ($rfq) {
                            $query->orWhere('source_rfq_id', $rfq->id);
                        }
                    })
                    ->latest('updated_at')
                    ->first();

                if ($abstract) {
                    $chains->linkAbstract($abstract, null, 'Abstract linked during SVP chain repair');
                }

                $purchaseOrder = PurchaseOrder::query()
                    ->where(function ($query) use ($pr, $resolution, $abstract) {
                        $query->where('source_pr_document_id', $pr->id);

                        if ($resolution) {
                            $query->orWhere('source_bac_resolution_id', $resolution->id);
                        }

                        if ($abstract) {
                            $query->orWhere('source_abstract_id', $abstract->id);
                        }
                    })
                    ->latest('updated_at')
                    ->first();

                if ($purchaseOrder) {
                    $chains->linkPurchaseOrder($purchaseOrder, null, 'Purchase Order linked during SVP chain repair');
                }

                $inspection = InspectionAcceptanceRecord::query()
                    ->where(function ($query) use ($pr, $purchaseOrder) {
                        $query->where('source_pr_document_id', $pr->id);

                        if ($purchaseOrder) {
                            $query->orWhere('purchase_order_id', $purchaseOrder->id);
                        }
                    })
                    ->latest('updated_at')
                    ->first();

                if ($inspection) {
                    $chains->linkInspection($inspection, null, 'Inspection / Acceptance linked during SVP chain repair');
                }

                $chain->refresh();
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        });

        $this->info('SVP chains repaired successfully.');

        return self::SUCCESS;
    }
}
