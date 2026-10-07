<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\SvpProcurementChain;
use App\Services\AuditLogger;
use App\Services\SvpChainService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SvpChainController extends Controller
{
    public function __construct(private readonly SvpChainService $chains)
    {
    }

    public function index(Request $request): View
    {
        AuditLogger::log('SVP Chains', 'Admin SVP Chains Viewed', 'Admin viewed all SVP procurement chains.');

        $query = SvpProcurementChain::query()
            ->with([
                'sourcePrDocument.assignedTo',
                'sourcePrDocument.currentOffice',
                'sourcePrDocument.submittingOffice',
                'sourcePrDocument.routingHistories.actionBy',
                'sourcePrDocument.routingHistories.fromOffice',
                'sourcePrDocument.routingHistories.toOffice',
                'office',
            ]);
        $this->applyFilters($query, $request);

        return view('svp-tracking.index', [
            'chains' => $query->latest('updated_at')->paginate(10)->withQueryString(),
            'filters' => $request->only(['search', 'office_id', 'fiscal_year', 'stage', 'status', 'date_from', 'date_to']),
            'fiscalYears' => ProcurementDocument::query()->whereNotNull('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
            'stages' => SvpProcurementChain::workflowStages(),
            'statuses' => SvpProcurementChain::query()->whereNotNull('current_status')->distinct()->orderBy('current_status')->pluck('current_status'),
            'offices' => Office::query()->where('status', Office::STATUS_ACTIVE)->orderBy('name')->get(),
            'context' => 'admin',
            'title' => 'SVP Chains',
            'eyebrow' => 'Administration',
            'subtitle' => 'View all SVP document chains across offices.',
            'indexRoute' => 'admin.svp-chains.index',
            'showRoute' => 'admin.svp-chains.show',
            'showOfficeFilter' => true,
        ]);
    }

    public function show(SvpProcurementChain $chain): View
    {
        AuditLogger::log('SVP Chains', 'Admin SVP Timeline Viewed', 'Admin viewed an SVP procurement chain timeline.', $chain);

        $chain->load([
            'sourcePrDocument.submittingOffice',
            'sourcePrDocument.assignedTo',
            'sourcePrDocument.currentOffice',
            'sourcePrDocument.routingHistories.actionBy',
            'sourcePrDocument.routingHistories.fromOffice',
            'sourcePrDocument.routingHistories.toOffice',
            'bacResolution',
            'rfq',
            'abstract',
            'purchaseOrder',
            'inspection',
            'latestPostingRecord',
            'events.performedBy',
            'events.fromOffice',
            'events.toOffice',
        ]);

        return view('svp-tracking.show', [
            'chain' => $chain,
            'cards' => $this->chains->documentCards($chain, 'admin'),
            'workflowSteps' => $this->chains->workflowSteps($chain),
            'context' => 'admin',
            'backRoute' => 'admin.svp-chains.index',
            'eyebrow' => 'Administration',
        ]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();
            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('chain_number', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhere('office_name', 'like', "%{$search}%")
                    ->orWhereHas('sourcePrDocument', function (Builder $document) use ($search) {
                        $document->where('tracking_number', 'like', "%{$search}%")
                            ->orWhere('pr_no', 'like', "%{$search}%")
                            ->orWhere('title', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
            });
        });

        $query->when($request->filled('office_id'), fn (Builder $builder) => $builder->where('office_id', $request->input('office_id')));
        $query->when($request->filled('fiscal_year'), fn (Builder $builder) => $builder->whereHas('sourcePrDocument', fn (Builder $document) => $document->where('fiscal_year', $request->input('fiscal_year'))));
        $query->when($request->filled('stage'), fn (Builder $builder) => $builder->where('current_stage', $request->input('stage')));
        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', fn (Builder $builder) => $builder->where('current_status', $request->input('status')));
        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('updated_at', '>=', $request->input('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('updated_at', '<=', $request->input('date_to')));
    }
}
