<?php

namespace App\Http\Controllers\HeadOffice;

use App\Http\Controllers\Controller;
use App\Models\BacResolution;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ElectronicSignatureService;
use App\Services\SignatureRequestService;
use App\Services\SvpChainService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceivedBacResolutionController extends Controller
{
    public function index(Request $request): View
    {
        AuditLogger::log('Received BAC Resolutions', 'Received BAC Resolutions Viewed', 'Head Office viewed received BAC Resolutions.');

        $query = $this->officeResolutionQuery($request->user())
            ->with(['sourcePrDocument.submittingOffice', 'preparedBy']);

        $this->applyFilters($query, $request);

        $resolutions = $query->latest('updated_at')->paginate(10)->withQueryString();
        $chains = app(SvpChainService::class);

        $resolutions->getCollection()->each(function (BacResolution $resolution) use ($chains) {
            $resolution->setAttribute('svp_next_step', $chains->bacResolutionNextStep($resolution));
        });

        return view('head-office.resolutions.index', [
            'resolutions' => $resolutions,
            'summary' => $this->summary($request->user()),
            'filters' => $request->only(['search', 'status', 'workflow', 'date_from', 'date_to']),
            'statuses' => [
                BacResolution::STATUS_RETURNED_TO_END_USER,
                BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR,
                BacResolution::STATUS_APPROVED_BY_HOPE,
            ],
        ]);
    }

    public function show(Request $request, BacResolution $resolution): View
    {
        if (! $this->canAccess($request->user(), $resolution)) {
            AuditLogger::log('Received BAC Resolutions', 'Unauthorized BAC Resolution Access Attempt', 'Head Office attempted to view another office BAC Resolution.', $resolution, null, null, 'warning');
            abort(403);
        }

        AuditLogger::log('Received BAC Resolutions', 'BAC Resolution Viewed', 'Head Office viewed a received BAC Resolution.', $resolution);

        $resolution->load([
            'sourcePrDocument.submittingOffice',
            'sourcePrDocument.submittedBy',
            'sourcePrDocument.routingHistories.actionBy',
            'sourcePrDocument.routingHistories.fromOffice',
            'sourcePrDocument.routingHistories.toOffice',
            'preparedBy',
            'submittedBy',
            'approvedBy',
            'items',
        ]);

        return view('head-office.resolutions.show', [
            'resolution' => $resolution,
            'canAcknowledge' => $this->canAcknowledge($resolution),
            'nextStep' => app(SvpChainService::class)->bacResolutionNextStep($resolution),
            'signedSignature' => app(ElectronicSignatureService::class)->signedSignatureFor($resolution, 'bac-resolution', 'confirmed'),
            'signatureSlots' => app(SignatureRequestService::class)->signedSignaturesForDocument($resolution, 'bac-resolution'),
        ]);
    }

    public function acknowledge(Request $request, BacResolution $resolution): RedirectResponse
    {
        if (! $this->canAccess($request->user(), $resolution)) {
            AuditLogger::log('Received BAC Resolutions', 'Unauthorized BAC Resolution Acknowledge Attempt', 'Head Office attempted to acknowledge another office BAC Resolution.', $resolution, null, null, 'warning');
            abort(403);
        }

        $resolution->load(['sourcePrDocument.submittingOffice', 'sourcePrDocument.submittedBy']);
        $sourceDocument = $resolution->sourcePrDocument;

        if (! $sourceDocument || ! $this->canAcknowledge($resolution)) {
            return back()->with('error', 'This BAC Resolution cannot be acknowledged.');
        }

        $result = app(SvpChainService::class)->routeAfterBacResolutionAcknowledged($resolution, $request->user());

        return back()->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    private function officeResolutionQuery(User $user): Builder
    {
        return BacResolution::query()
            ->whereHas('sourcePrDocument', function (Builder $query) use ($user) {
                $query->where(function (Builder $office) use ($user) {
                    $office->where('submitting_office_id', $user->office_id)
                        ->orWhere('current_office_id', $user->office_id)
                        ->orWhere('submitted_by_user_id', $user->id)
                        ->orWhere('prepared_by_user_id', $user->id);
                    })
                    ->whereIn('status', [
                        ProcurementDocument::STATUS_BAC_RESOLUTION_RETURNED_TO_END_USER,
                        ProcurementDocument::STATUS_SVP_POSTING_REQUIRED,
                        ProcurementDocument::STATUS_SVP_POSTING_COMPLETED,
                        ProcurementDocument::STATUS_READY_FOR_RFQ,
                    ]);
            });
    }

    private function canAccess(User $user, BacResolution $resolution): bool
    {
        return $this->officeResolutionQuery($user)->whereKey($resolution->id)->exists();
    }

    private function canAcknowledge(BacResolution $resolution): bool
    {
        return in_array($resolution->status, [
                BacResolution::STATUS_RETURNED_TO_END_USER,
                BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR,
            ], true)
            && $resolution->sourcePrDocument?->status === ProcurementDocument::STATUS_BAC_RESOLUTION_RETURNED_TO_END_USER;
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();
            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('resolution_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('project_title', 'like', "%{$search}%")
                    ->orWhere('pr_number', 'like', "%{$search}%")
                    ->orWhereHas('sourcePrDocument', fn (Builder $document) => $document->where('tracking_number', 'like', "%{$search}%")
                        ->orWhere('pr_no', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%"));
            });
        });

        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', function (Builder $builder) use ($request) {
            if ($request->input('status') === 'awaiting') {
                $builder->whereIn('status', [
                    BacResolution::STATUS_RETURNED_TO_END_USER,
                    BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR,
                ])->whereHas('sourcePrDocument', fn (Builder $document) => $document->where('status', ProcurementDocument::STATUS_BAC_RESOLUTION_RETURNED_TO_END_USER));

                return;
            }

            $builder->where('status', $request->input('status'));
        });

        $query->when($request->input('workflow') === 'ready_for_rfq', fn (Builder $builder) => $builder->whereHas('sourcePrDocument', fn (Builder $document) => $document->where('status', ProcurementDocument::STATUS_READY_FOR_RFQ)));
        $query->when($request->input('workflow') === 'posting_required', fn (Builder $builder) => $builder->whereHas('sourcePrDocument', fn (Builder $document) => $document->where('status', ProcurementDocument::STATUS_SVP_POSTING_REQUIRED)));
        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('updated_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('updated_at', '<=', $request->date('date_to')));
    }

    private function summary(User $user): array
    {
        $base = $this->officeResolutionQuery($user);

        return [
            'received' => (clone $base)->count(),
            'awaiting' => (clone $base)->whereIn('status', [
                BacResolution::STATUS_RETURNED_TO_END_USER,
                BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR,
            ])->whereHas('sourcePrDocument', fn (Builder $document) => $document->where('status', ProcurementDocument::STATUS_BAC_RESOLUTION_RETURNED_TO_END_USER))->count(),
            'postingRequired' => (clone $base)->whereHas('sourcePrDocument', fn (Builder $document) => $document->where('status', ProcurementDocument::STATUS_SVP_POSTING_REQUIRED))->count(),
            'readyForRfq' => (clone $base)->whereHas('sourcePrDocument', fn (Builder $document) => $document->where('status', ProcurementDocument::STATUS_READY_FOR_RFQ))->count(),
        ];
    }
}
