<?php

namespace App\Http\Controllers\BacSecretariat;

use App\Http\Controllers\Controller;
use App\Models\DocumentRoutingHistory;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpReview;
use App\Models\ProcurementDocument;
use App\Models\SignatureRequest;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SystemNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PpmpReviewController extends Controller
{
    private const REVIEW_STATUSES = [
        ProcurementDocument::STATUS_PENDING_PPMP_REVIEW,
        ProcurementDocument::STATUS_UNDER_PPMP_REVIEW,
        ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
        ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION,
    ];

    public function index(Request $request): View
    {
        AuditLogger::log('BAC Secretariat PPMP Review', 'PPMP Review Page Viewed', 'BAC Secretariat viewed PPMP review queue.');

        $query = $this->reviewQuery($request->user())
            ->with(['submittingOffice', 'submittedBy', 'assignedTo']);

        $this->applyFilters($query, $request);

        return view('bac-secretariat.ppmp.index', [
            'documents' => $query->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary($request->user()),
            'filters' => $request->only(['search', 'fiscal_year', 'office_id', 'status', 'date_from', 'date_to']),
            'fiscalYears' => $this->reviewQuery($request->user())->select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
            'offices' => Office::orderBy('name')->get(['id', 'name']),
            'statuses' => self::REVIEW_STATUSES,
        ]);
    }

    public function show(Request $request, ProcurementDocument $document): View|RedirectResponse
    {
        if (! $this->canAccess($request->user(), $document)) {
            AuditLogger::log('BAC Secretariat PPMP Review', 'Unauthorized Access Attempt', 'BAC Secretariat attempted to access a PPMP outside the review queue.', $document, null, null, 'warning');

            return redirect()
                ->route('bac-secretariat.ppmp.index')
                ->with('error', 'You are not authorized to review this PPMP.');
        }

        AuditLogger::log('BAC Secretariat PPMP Review', 'PPMP Review Detail Viewed', 'BAC Secretariat viewed PPMP review detail.', $document);

        $document->load([
            'submittingOffice',
            'submittedBy',
            'preparedBy',
            'currentOffice',
            'assignedTo',
            'ppmpItems',
            'attachments.uploadedBy',
        ]);

        return view('bac-secretariat.ppmp.show', [
            'document' => $document,
        ]);
    }

    public function print(Request $request, ProcurementDocument $document): View|RedirectResponse
    {
        if (! $this->canAccess($request->user(), $document)) {
            AuditLogger::log('BAC Secretariat PPMP Review', 'Unauthorized Access Attempt', 'BAC Secretariat attempted to print a PPMP outside the review queue.', $document, null, null, 'warning');

            return redirect()
                ->route('bac-secretariat.ppmp.index')
                ->with('error', 'You are not authorized to print this PPMP.');
        }

        $document->load([
            'submittingOffice',
            'submittedBy',
            'preparedBy',
            'ppmpItems',
        ]);

        AuditLogger::log('BAC Secretariat PPMP Review', 'PPMP Print Viewed', 'BAC Secretariat opened PPMP print view.', $document);

        return view('bac-secretariat.ppmp.print', [
            'document' => $document,
        ]);
    }

    public function start(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (! $this->canAccess($request->user(), $document) || $document->status !== ProcurementDocument::STATUS_PENDING_PPMP_REVIEW) {
            AuditLogger::log('BAC Secretariat PPMP Review', 'Unauthorized Access Attempt', 'Invalid PPMP start review attempt.', $document, null, null, 'warning');

            return back()->with('error', 'This PPMP cannot be started for review.');
        }

        DB::transaction(function () use ($request, $document) {
            $oldStatus = $document->status;

            $document->update([
                'status' => ProcurementDocument::STATUS_UNDER_PPMP_REVIEW,
                'ppmp_review_status' => PpmpReview::STATUS_UNDER_REVIEW,
                'ppmp_review_started_at' => now(),
                'stage' => ProcurementDocument::STAGE_PPMP_UNDER_APP_CONSOLIDATION,
                'assigned_to_user_id' => $request->user()->id,
            ]);

            $document->ppmpReviews()->create([
                'reviewed_by_user_id' => $request->user()->id,
                'review_status' => PpmpReview::STATUS_UNDER_REVIEW,
                'started_at' => now(),
            ]);

            $this->recordRouting($document, $request->user(), 'PPMP APP Consolidation Started', $oldStatus, $document->status, 'BACSEC-004 started PPMP APP consolidation review.');

            AuditLogger::log('BAC Secretariat PPMP Review', 'PPMP APP Consolidation Started', 'BACSEC-004 started PPMP APP consolidation review.', $document, ['status' => $oldStatus], ['status' => $document->status]);
        });

        return back()->with('status', 'PPMP APP consolidation review started.');
    }

    public function return(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (! $this->canAccess($request->user(), $document)
            || ! in_array($document->status, [ProcurementDocument::STATUS_PENDING_PPMP_REVIEW, ProcurementDocument::STATUS_UNDER_PPMP_REVIEW], true)) {
            AuditLogger::log('BAC Secretariat PPMP Review', 'Unauthorized Access Attempt', 'Invalid PPMP return attempt.', $document, null, null, 'warning');

            return back()->with('error', 'This PPMP cannot be returned.');
        }

        $validated = $request->validate([
            'comments' => ['required', 'string', 'min:5'],
        ]);

        DB::transaction(function () use ($request, $document, $validated) {
            $oldStatus = $document->status;
            $fromOffice = $document->current_office_id;

            $document->update([
                'status' => ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
                'ppmp_review_status' => PpmpReview::STATUS_RETURNED,
                'stage' => ProcurementDocument::STAGE_RETURNED_TO_REQUESTING_OFFICE,
                'current_office_id' => $document->submitting_office_id,
                'assigned_to_user_id' => $document->submitted_by_user_id,
                'ppmp_reviewed_by_user_id' => $request->user()->id,
                'ppmp_reviewed_at' => now(),
                'ppmp_remarks' => $validated['comments'],
                'remarks' => $validated['comments'],
                'returned_at' => now(),
            ]);

            $document->ppmpReviews()->create([
                'reviewed_by_user_id' => $request->user()->id,
                'review_status' => PpmpReview::STATUS_RETURNED,
                'remarks' => $validated['comments'],
                'started_at' => $document->ppmp_review_started_at,
                'completed_at' => now(),
            ]);

            $this->recordRouting($document, $request->user(), 'PPMP Returned by BAC Secretariat', $oldStatus, $document->status, $validated['comments'], $fromOffice, $document->submitting_office_id);
            $this->syncStandalonePpmpStatus($document, Ppmp::STATUS_RETURNED);

            SystemNotificationService::notify(
                $document->submittedBy,
                'PPMP Returned by BAC Secretariat',
                "PPMP {$document->tracking_number} was returned for correction or clarification.",
                SystemNotification::TYPE_WARNING,
                'BAC Secretariat PPMP Review',
                $document,
                route('head-office.returned.show', $document)
            );

            AuditLogger::log('BAC Secretariat PPMP Review', 'PPMP Returned', 'BAC Secretariat returned a PPMP to the submitting office.', $document, ['status' => $oldStatus], ['status' => $document->status], 'warning');
        });

        return redirect()
            ->route('bac-secretariat.ppmp.index')
            ->with('status', 'PPMP returned to the submitting office.');
    }

    public function accept(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (! $this->canAccess($request->user(), $document)
            || ! in_array($document->status, [ProcurementDocument::STATUS_PENDING_PPMP_REVIEW, ProcurementDocument::STATUS_UNDER_PPMP_REVIEW], true)) {
            AuditLogger::log('BAC Secretariat PPMP Review', 'Unauthorized Access Attempt', 'Invalid PPMP accept attempt.', $document, null, null, 'warning');

            return back()->with('error', 'This PPMP cannot be accepted for APP consolidation.');
        }

        $validated = $request->validate([
            'remarks' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($request, $document, $validated) {
            $oldStatus = $document->status;
            $startedAt = $document->ppmp_review_started_at ?: now();
            $remarks = $validated['remarks'] ?? null;

            $document->update([
                'status' => ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION,
                'ppmp_review_status' => PpmpReview::STATUS_ACCEPTED,
                'ppmp_reviewed_by_user_id' => $request->user()->id,
                'ppmp_reviewed_at' => now(),
                'ppmp_review_started_at' => $startedAt,
                'stage' => ProcurementDocument::STAGE_ACCEPTED_FOR_APP_CONSOLIDATION,
                'current_office_id' => $document->current_office_id,
                'assigned_to_user_id' => $request->user()->id,
                'ppmp_remarks' => $remarks ?: $document->ppmp_remarks,
                'accepted_at' => now(),
            ]);

            $document->ppmpReviews()->create([
                'reviewed_by_user_id' => $request->user()->id,
                'review_status' => PpmpReview::STATUS_ACCEPTED,
                'remarks' => $remarks,
                'started_at' => $startedAt,
                'completed_at' => now(),
            ]);

            $this->recordRouting($document, $request->user(), 'PPMP Accepted for APP Consolidation', $oldStatus, $document->status, $remarks ?: 'PPMP accepted for APP consolidation.');
            $this->syncStandalonePpmpStatus($document, Ppmp::STATUS_REVIEWED);

            SystemNotificationService::notify(
                $document->submittedBy,
                'PPMP Accepted for APP Consolidation',
                "PPMP {$document->tracking_number} was accepted for APP consolidation.",
                SystemNotification::TYPE_SUCCESS,
                'BAC Secretariat PPMP Review',
                $document,
                route('head-office.documents.show', $document)
            );

            AuditLogger::log('BAC Secretariat PPMP Review', 'PPMP Accepted', 'BAC Secretariat accepted a PPMP for APP consolidation.', $document, ['status' => $oldStatus], ['status' => $document->status]);
        });

        return redirect()
            ->route('bac-secretariat.app.index')
            ->with('status', 'PPMP accepted for APP consolidation. You can now add it to an APP draft.');
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();

            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('tracking_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhereHas('submittingOffice', fn (Builder $office) => $office->where('name', 'like', "%{$search}%"));
            });
        });

        $query->when($request->filled('fiscal_year'), fn (Builder $builder) => $builder->where('fiscal_year', $request->input('fiscal_year')));
        $query->when($request->filled('office_id'), fn (Builder $builder) => $builder->where('submitting_office_id', $request->input('office_id')));
        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', fn (Builder $builder) => $builder->where('status', $request->input('status')));
        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('updated_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('updated_at', '<=', $request->date('date_to')));
    }

    private function reviewQuery(?User $user = null): Builder
    {
        return ProcurementDocument::query()
            ->where('document_type', 'PPMP')
            ->whereIn('status', self::REVIEW_STATUSES)
            ->whereExists(function ($signature) {
                $signature->selectRaw('1')
                    ->from('signature_requests')
                    ->whereColumn('signature_requests.document_id', 'procurement_documents.id')
                    ->where('signature_requests.document_type', 'ppmp')
                    ->where('signature_requests.is_required', true);
            })
            ->whereNotExists(function ($signature) {
                $signature->selectRaw('1')
                    ->from('signature_requests')
                    ->whereColumn('signature_requests.document_id', 'procurement_documents.id')
                    ->where('signature_requests.document_type', 'ppmp')
                    ->where('signature_requests.is_required', true)
                    ->where('signature_requests.status', '!=', SignatureRequest::STATUS_SIGNED);
            })
            ->when($user, function (Builder $query) use ($user) {
                $query->where(function (Builder $visibility) use ($user) {
                    $visibility->where('assigned_to_user_id', $user->id)
                        ->orWhere(function (Builder $unassigned) {
                            $unassigned->whereNull('assigned_to_user_id')
                                ->whereHas('currentOffice', function (Builder $officeQuery) {
                                    $officeQuery->where('code', 'BACSEC')
                                        ->orWhere('name', 'BAC Secretariat');
                                });
                        });
                });
            });
    }

    private function canAccess(User $user, ProcurementDocument $document): bool
    {
        return $this->reviewQuery($user)
            ->whereKey($document->id)
            ->exists();
    }

    private function summary(User $user): array
    {
        $base = $this->reviewQuery($user);

        return [
            'submitted' => (clone $base)->where('status', ProcurementDocument::STATUS_PENDING_PPMP_REVIEW)->count(),
            'underReview' => (clone $base)->where('status', ProcurementDocument::STATUS_UNDER_PPMP_REVIEW)->count(),
            'returned' => (clone $base)->where('status', ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT)->count(),
            'accepted' => (clone $base)->where('status', ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION)->count(),
        ];
    }

    private function recordRouting(ProcurementDocument $document, User $user, string $action, ?string $fromStatus, string $toStatus, ?string $comments = null, ?int $fromOfficeId = null, ?int $toOfficeId = null): void
    {
        DocumentRoutingHistory::create([
            'procurement_document_id' => $document->id,
            'action_by_user_id' => $user->id,
            'from_office_id' => $fromOfficeId ?? $document->getOriginal('current_office_id'),
            'to_office_id' => $toOfficeId ?? $document->current_office_id,
            'action' => $action,
            'status_from' => $fromStatus,
            'status_to' => $toStatus,
            'comments' => $comments,
            'action_at' => now(),
        ]);
    }

    private function syncStandalonePpmpStatus(ProcurementDocument $document, string $status): void
    {
        $text = trim((string) $document->description . ' ' . (string) $document->remarks);

        if (! preg_match('/Source PPMP ID:\s*(\d+);/', $text, $matches)) {
            return;
        }

        Ppmp::whereKey((int) $matches[1])->update(['status' => $status]);
    }
}
