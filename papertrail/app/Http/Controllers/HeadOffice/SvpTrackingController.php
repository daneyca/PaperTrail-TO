<?php

namespace App\Http\Controllers\HeadOffice;

use App\Http\Controllers\Controller;
use App\Models\ProcurementDocument;
use App\Models\SvpProcurementChain;
use App\Services\AuditLogger;
use App\Services\SvpChainService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SvpTrackingController extends Controller
{
    public function __construct(private readonly SvpChainService $chains)
    {
    }

    public function index(Request $request): View
    {
        AuditLogger::log('SVP Tracking', 'SVP Tracking Viewed', 'Head Office viewed office SVP procurement chains.');

        $user = $request->user();
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

        $this->scopeChainsToUser($query, $user);

        $this->applyFilters($query, $request);

        return view('svp-tracking.index', [
            'chains' => $query->latest('updated_at')->paginate(10, ['*'], 'svp_page')->withQueryString(),
            'filters' => $request->only(['search', 'fiscal_year', 'stage', 'status', 'date_from', 'date_to']),
            'fiscalYears' => $this->fiscalYears($user),
            'stages' => $this->stages(),
            'statuses' => $this->statuses($user),
            'context' => 'head-office',
            'title' => 'Status Tracking',
            'eyebrow' => 'Head of Office / End User',
            'subtitle' => 'Track document movement by opening each document hierarchy.',
            'indexRoute' => 'head-office.svp-tracking.index',
            'showRoute' => 'head-office.svp-tracking.show',
            'showOfficeFilter' => false,
        ]);
    }

    public function show(Request $request, SvpProcurementChain $chain): View
    {
        if (! $this->canAccessChain($chain, $request->user())) {
            AuditLogger::log('SVP Tracking', 'Unauthorized SVP Chain Access Attempt', 'Head Office attempted to view another office SVP chain.', $chain, null, null, 'warning');
            abort(403);
        }

        AuditLogger::log('SVP Tracking', 'SVP Timeline Viewed', 'Head Office viewed an SVP procurement chain timeline.', $chain);

        $chain->load([
            'sourcePrDocument.assignedTo',
            'sourcePrDocument.currentOffice',
            'sourcePrDocument.submittingOffice',
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
            'cards' => $this->chains->documentCards($chain, 'head-office'),
            'workflowSteps' => $this->chains->workflowSteps($chain),
            'context' => 'head-office',
            'backRoute' => 'head-office.svp-tracking.index',
            'eyebrow' => 'Head of Office / End User',
        ]);
    }

    public function mockPreview(Request $request): View
    {
        AuditLogger::log('SVP Tracking', 'Mock SVP Movement Preview Viewed', 'Head Office viewed the mock status tracking hierarchy preview.');

        return view('svp-tracking.mock-preview', [
            'backRoute' => 'head-office.svp-tracking.index',
            'eyebrow' => 'Head of Office / End User',
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
                        $document->where('document_reference_number', 'like', "%{$search}%")
                            ->orWhere('tracking_number', 'like', "%{$search}%")
                            ->orWhere('pr_no', 'like', "%{$search}%")
                            ->orWhere('title', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
            });
        });

        $query->when($request->filled('fiscal_year'), function (Builder $builder) use ($request) {
            $builder->whereHas('sourcePrDocument', fn (Builder $document) => $document->where('fiscal_year', $request->input('fiscal_year')));
        });

        $query->when($request->filled('created_year'), function (Builder $builder) use ($request) {
            $builder->whereHas('sourcePrDocument', fn (Builder $document) => $document->where('created_year', $request->input('created_year')));
        });

        $query->when($request->filled('created_month'), function (Builder $builder) use ($request) {
            $builder->whereHas('sourcePrDocument', fn (Builder $document) => $document->where('created_month', $request->input('created_month')));
        });

        $query->when($request->filled('stage'), fn (Builder $builder) => $builder->where('current_stage', $request->input('stage')));
        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', fn (Builder $builder) => $builder->where('current_status', $request->input('status')));
        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('updated_at', '>=', $request->input('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('updated_at', '<=', $request->input('date_to')));
    }

    private function scopeChainsToUser(Builder $query, $user): void
    {
        if ($this->canCreatePrDocumentsAsBacsec002($user)) {
            $query->where(function (Builder $scoped) use ($user) {
                $scoped->whereHas('sourcePrDocument', function (Builder $document) use ($user) {
                    $document->where('prepared_by_user_id', $user->id)
                        ->orWhere('submitted_by_user_id', $user->id);
                });
            });

            return;
        }

        $query->where('office_id', $user?->office_id);
    }

    private function canAccessChain(SvpProcurementChain $chain, $user): bool
    {
        if (! $this->canCreatePrDocumentsAsBacsec002($user) && (int) $chain->office_id === (int) $user?->office_id) {
            return true;
        }

        if (! $this->canCreatePrDocumentsAsBacsec002($user)) {
            return false;
        }

        return $chain->sourcePrDocument()
            ->where(function (Builder $document) use ($user) {
                $document->where('prepared_by_user_id', $user->id)
                    ->orWhere('submitted_by_user_id', $user->id);
            })
            ->exists();
    }

    private function fiscalYears($user)
    {
        $chainQuery = SvpProcurementChain::query()
            ->whereNotNull('source_pr_document_id');

        $this->scopeChainsToUser($chainQuery, $user);

        return ProcurementDocument::query()
            ->whereIn('id', $chainQuery->pluck('source_pr_document_id'))
            ->whereNotNull('fiscal_year')
            ->distinct()
            ->orderByDesc('fiscal_year')
            ->pluck('fiscal_year');
    }

    private function statuses($user)
    {
        $query = SvpProcurementChain::query()
            ->whereNotNull('current_status');

        $this->scopeChainsToUser($query, $user);

        $chainStatuses = $query->distinct()
            ->orderBy('current_status')
            ->pluck('current_status');

        return $chainStatuses
            ->filter()
            ->unique()
            ->values();
    }

    private function stages()
    {
        return collect(SvpProcurementChain::workflowStages())
            ->filter()
            ->unique()
            ->values();
    }

    private function canCreatePrDocumentsAsBacsec002($user): bool
    {
        return $user
            && method_exists($user, 'hasBacsec002PurchaseRequestCapability')
            && $user->hasBacsec002PurchaseRequestCapability();
    }
}
