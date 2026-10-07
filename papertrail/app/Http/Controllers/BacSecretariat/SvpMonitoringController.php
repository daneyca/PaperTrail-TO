<?php

namespace App\Http\Controllers\BacSecretariat;

use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\SvpProcurementChain;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SvpChainService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SvpMonitoringController extends Controller
{
    public function __construct(private readonly SvpChainService $chains)
    {
    }

    public function index(Request $request): View
    {
        AuditLogger::log('SVP Monitoring', 'SVP Monitoring Viewed', 'BAC Secretariat viewed SVP procurement chains.');

        $query = $this->baseQuery($request->user())
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
            'fiscalYears' => $this->fiscalYears(),
            'stages' => $this->stages(),
            'statuses' => $this->statuses(),
            'offices' => Office::query()->where('status', Office::STATUS_ACTIVE)->orderBy('name')->get(),
            'context' => 'bac-secretariat',
            'title' => 'SVP Monitoring',
            'eyebrow' => 'BAC Secretariat',
            'subtitle' => 'Monitor SVP procurement chains that have passed through BAC processing.',
            'indexRoute' => 'bac-secretariat.svp-monitoring.index',
            'showRoute' => 'bac-secretariat.svp-monitoring.show',
            'showOfficeFilter' => true,
        ]);
    }

    public function show(SvpProcurementChain $chain): View
    {
        if (! $this->canAccess($chain)) {
            AuditLogger::log('SVP Monitoring', 'Unauthorized SVP Chain Access Attempt', 'BAC Secretariat attempted to view an unrelated SVP chain.', $chain, null, null, 'warning');
            abort(403);
        }

        AuditLogger::log('SVP Monitoring', 'SVP Timeline Viewed', 'BAC Secretariat viewed an SVP procurement chain timeline.', $chain);

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
            'cards' => $this->chains->documentCards($chain, 'bac-secretariat'),
            'workflowSteps' => $this->chains->workflowSteps($chain),
            'canCompletePosting' => $this->chains->canCompletePosting($chain, auth()->user()),
            'context' => 'bac-secretariat',
            'backRoute' => 'bac-secretariat.svp-monitoring.index',
            'eyebrow' => 'BAC Secretariat',
        ]);
    }

    public function completePosting(Request $request, SvpProcurementChain $chain): RedirectResponse
    {
        if (! $this->canAccess($chain)) {
            AuditLogger::log('SVP Monitoring', 'Unauthorized SVP Posting Completion Attempt', 'BAC Secretariat attempted to complete an unrelated SVP posting task.', $chain, null, null, 'warning');
            abort(403);
        }

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $chain->loadMissing('latestPostingRecord');

        if (! $chain->latestPostingRecord) {
            return redirect()
                ->route('bac-secretariat.svp-posting.create', $chain)
                ->with('error', 'Create a posting record and upload the required posting evidence before completing this task.');
        }

        $result = $this->chains->completePosting($chain, $request->user(), $validated['remarks'] ?? null, $chain->latestPostingRecord);

        return back()->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    private function baseQuery(?User $user = null): Builder
    {
        $query = SvpProcurementChain::query()
            ->where(function (Builder $query) {
                $query->whereNotNull('bac_resolution_id')
                    ->orWhereHas('events', function (Builder $event) {
                        $event->where('from_role', 'like', '%BAC%')
                            ->orWhere('to_role', 'like', '%BAC%')
                            ->orWhere('action', 'like', '%BAC%')
                            ->orWhere('stage', 'like', '%BAC%');
                    })
                    ->orWhereHas('sourcePrDocument', function (Builder $document) {
                        $document->whereIn('status', [
                            ProcurementDocument::STATUS_SUBMITTED_TO_BAC_SECRETARIAT,
                            ProcurementDocument::STATUS_PR_RECEIVED_BY_BAC_SECRETARIAT,
                            ProcurementDocument::STATUS_UNDER_PR_VALIDATION,
                            ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION,
                            ProcurementDocument::STATUS_BAC_RESOLUTION_CREATED,
                            ProcurementDocument::STATUS_BAC_RESOLUTION_RETURNED_TO_END_USER,
                            ProcurementDocument::STATUS_SVP_POSTING_REQUIRED,
                            ProcurementDocument::STATUS_SVP_POSTING_COMPLETED,
                            ProcurementDocument::STATUS_READY_FOR_RFQ,
                        ]);
                    });
            });

        if ($user?->user_id === 'BACSEC-004') {
            $query->where(function (Builder $posting) {
                $posting->where('current_stage', SvpProcurementChain::STAGE_POSTING)
                    ->orWhere('current_stage', SvpProcurementChain::STAGE_POSTING_COMPLETED)
                    ->orWhere('current_status', ProcurementDocument::STATUS_SVP_POSTING_REQUIRED)
                    ->orWhere('current_status', ProcurementDocument::STATUS_SVP_POSTING_COMPLETED)
                    ->orWhereHas('postingRecords');
            });
        }

        return $query;
    }

    private function canAccess(SvpProcurementChain $chain): bool
    {
        return (clone $this->baseQuery(auth()->user()))->whereKey($chain->id)->exists();
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
        $query->when($request->filled('fiscal_year'), function (Builder $builder) use ($request) {
            $builder->whereHas('sourcePrDocument', fn (Builder $document) => $document->where('fiscal_year', $request->input('fiscal_year')));
        });
        $query->when($request->filled('stage'), fn (Builder $builder) => $builder->where('current_stage', $request->input('stage')));
        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', fn (Builder $builder) => $builder->where('current_status', $request->input('status')));
        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('updated_at', '>=', $request->input('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('updated_at', '<=', $request->input('date_to')));
    }

    private function fiscalYears()
    {
        return ProcurementDocument::query()
            ->whereNotNull('fiscal_year')
            ->distinct()
            ->orderByDesc('fiscal_year')
            ->pluck('fiscal_year');
    }

    private function statuses()
    {
        return SvpProcurementChain::query()
            ->whereNotNull('current_status')
            ->distinct()
            ->orderBy('current_status')
            ->pluck('current_status');
    }

    private function stages(): array
    {
        return SvpProcurementChain::workflowStages();
    }
}
