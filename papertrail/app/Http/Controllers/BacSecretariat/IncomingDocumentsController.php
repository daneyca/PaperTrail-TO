<?php

namespace App\Http\Controllers\BacSecretariat;

use App\Http\Controllers\Controller;
use App\Models\AccountingReview;
use App\Models\BacSecretariatReview;
use App\Models\BudgetReview;
use App\Models\DocumentRoutingHistory;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpReview;
use App\Models\ProcurementDocument;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SystemNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class IncomingDocumentsController extends Controller
{
    public function index(Request $request): View
    {
        AuditLogger::log('BAC Secretariat', 'BAC Secretariat Incoming Page Viewed', 'BAC Secretariat viewed incoming documents.');

        $includeReturned = $request->input('status') === ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT;
        $query = $this->applyAssignedPpmpVisibility(
            ProcurementDocument::query()->incomingForBacSecretariat($request->user(), $includeReturned),
            $request->user(),
        )->with(['submittingOffice', 'submittedBy', 'currentOffice']);

        $this->applyFilters($query, $request);

        return view('bac-secretariat.incoming.index', [
            'documents' => $query->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary($request->user()),
            'filters' => $request->only(['search', 'document_type', 'fiscal_year', 'status', 'date_from', 'date_to']),
            'documentTypes' => ProcurementDocument::query()->select('document_type')->distinct()->orderBy('document_type')->pluck('document_type'),
            'fiscalYears' => ProcurementDocument::query()->select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
            'statuses' => [
                ProcurementDocument::STATUS_PENDING_PPMP_REVIEW,
                ProcurementDocument::STATUS_UNDER_PPMP_REVIEW,
                ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW,
                ProcurementDocument::STATUS_RECEIVED_BY_BAC_SECRETARIAT,
                ProcurementDocument::STATUS_UNDER_BAC_SECRETARIAT_REVIEW,
                ProcurementDocument::STATUS_READY_FOR_DOCUMENT_ROUTING,
                ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
            ],
        ]);
    }

    public function show(Request $request, ProcurementDocument $document): View|RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document, true)) {
            AuditLogger::log('BAC Secretariat', 'Unauthorized Access Attempt', 'BAC Secretariat attempted to access a document outside their scope.', $document, null, null, 'warning');

            return redirect()
                ->route('bac-secretariat.incoming.index')
                ->with('error', 'You are not authorized to process this document.');
        }

        AuditLogger::log('BAC Secretariat', 'Incoming Document Detail Viewed', 'BAC Secretariat viewed incoming document detail.', $document);

        $document->load([
            'submittingOffice',
            'currentOffice',
            'submittedBy',
            'assignedTo',
            'budgetReviewedBy',
            'accountingReviewedBy',
            'bacSecretariatReceivedBy',
            'budgetReviews.reviewedBy',
            'accountingReviews.reviewedBy',
            'bacSecretariatReviews.reviewedBy',
            'routingHistories.actionBy',
            'routingHistories.fromOffice',
            'routingHistories.toOffice',
        ]);

        if (strtoupper((string) $document->document_type) === 'PPMP') {
            $document->loadMissing('ppmpItems');
        }

        return view('bac-secretariat.incoming.show', [
            'document' => $document,
            'latestBudgetReview' => $document->budgetReviews->sortByDesc(fn (BudgetReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestAccountingReview' => $document->accountingReviews->sortByDesc(fn (AccountingReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestBacReview' => $document->bacSecretariatReviews->sortByDesc(fn (BacSecretariatReview $review) => $review->completed_at ?? $review->created_at)->first(),
        ]);
    }

    public function acknowledge(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document) || $document->status !== ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW) {
            AuditLogger::log('BAC Secretariat', 'Unauthorized Access Attempt', 'Invalid BAC receipt acknowledgement attempt.', $document, null, null, 'warning');

            return back()->with('error', 'This document cannot be acknowledged.');
        }

        DB::transaction(function () use ($request, $document) {
            $oldStatus = $document->status;

            $document->update([
                'status' => ProcurementDocument::STATUS_RECEIVED_BY_BAC_SECRETARIAT,
                'bac_secretariat_status' => BacSecretariatReview::STATUS_RECEIVED,
                'bac_secretariat_received_by_user_id' => $request->user()->id,
                'bac_secretariat_received_at' => now(),
                'stage' => ProcurementDocument::STAGE_BAC_SECRETARIAT_REVIEW,
                'assigned_to_user_id' => $request->user()->id,
            ]);

            $document->bacSecretariatReviews()->create([
                'reviewed_by_user_id' => $request->user()->id,
                'review_status' => BacSecretariatReview::STATUS_RECEIVED,
                'started_at' => now(),
            ]);

            $this->recordRouting($document, $request->user(), 'Received by BAC Secretariat', $oldStatus, $document->status, 'Document receipt acknowledged.');
            AuditLogger::log('BAC Secretariat', 'Document Receipt Acknowledged', 'BAC Secretariat acknowledged document receipt.', $document, ['status' => $oldStatus], ['status' => $document->status]);
        });

        return back()->with('status', 'Document receipt acknowledged.');
    }

    public function startReview(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document) || $document->status !== ProcurementDocument::STATUS_RECEIVED_BY_BAC_SECRETARIAT) {
            AuditLogger::log('BAC Secretariat', 'Unauthorized Access Attempt', 'Invalid BAC review start attempt.', $document, null, null, 'warning');

            return back()->with('error', 'This document cannot be started for review.');
        }

        DB::transaction(function () use ($request, $document) {
            $oldStatus = $document->status;

            $document->update([
                'status' => ProcurementDocument::STATUS_UNDER_BAC_SECRETARIAT_REVIEW,
                'bac_secretariat_status' => BacSecretariatReview::STATUS_UNDER_REVIEW,
                'bac_secretariat_review_started_at' => now(),
                'stage' => ProcurementDocument::STAGE_BAC_SECRETARIAT_REVIEW,
                'assigned_to_user_id' => $request->user()->id,
            ]);

            $document->bacSecretariatReviews()->create([
                'reviewed_by_user_id' => $request->user()->id,
                'review_status' => BacSecretariatReview::STATUS_UNDER_REVIEW,
                'started_at' => now(),
            ]);

            $this->recordRouting($document, $request->user(), 'BAC Secretariat Review Started', $oldStatus, $document->status, 'BAC Secretariat review started.');
            AuditLogger::log('BAC Secretariat', 'BAC Secretariat Review Started', 'BAC Secretariat started document review.', $document, ['status' => $oldStatus], ['status' => $document->status]);
        });

        return back()->with('status', 'BAC Secretariat review started.');
    }

    public function return(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)
            || !in_array($document->status, [
                ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW,
                ProcurementDocument::STATUS_RECEIVED_BY_BAC_SECRETARIAT,
                ProcurementDocument::STATUS_UNDER_BAC_SECRETARIAT_REVIEW,
            ], true)) {
            AuditLogger::log('BAC Secretariat', 'Unauthorized Access Attempt', 'Invalid BAC return attempt.', $document, null, null, 'warning');

            return back()->with('error', 'This document cannot be returned.');
        }

        $validated = $request->validate([
            'return_target' => ['required', 'in:accounting,requesting_office'],
            'comments' => ['required', 'string', 'min:5'],
        ]);

        $accountingOffice = $this->accountingOffice();
        $accountingUser = $this->accountingOfficer();

        if ($validated['return_target'] === 'accounting' && !$accountingOffice && !$accountingUser) {
            return back()->with('error', 'Accounting Office routing target is not configured.');
        }

        DB::transaction(function () use ($request, $document, $validated, $accountingOffice, $accountingUser) {
            $oldStatus = $document->status;
            $fromOffice = $document->current_office_id;
            $returnToAccounting = $validated['return_target'] === 'accounting';
            $toOffice = $returnToAccounting ? ($accountingOffice?->id ?? $accountingUser?->office_id) : $document->submitting_office_id;
            $assignedTo = $returnToAccounting ? $accountingUser?->id : $document->submitted_by_user_id;

            $document->update([
                'status' => ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
                'bac_secretariat_status' => BacSecretariatReview::STATUS_RETURNED,
                'stage' => $returnToAccounting ? ProcurementDocument::STAGE_RETURNED_TO_ACCOUNTING_OFFICE : ProcurementDocument::STAGE_RETURNED_TO_REQUESTING_OFFICE,
                'current_office_id' => $toOffice,
                'assigned_to_user_id' => $assignedTo,
                'bac_secretariat_remarks' => $validated['comments'],
                'remarks' => $validated['comments'],
                'bac_secretariat_processed_at' => now(),
            ]);

            $document->bacSecretariatReviews()->create([
                'reviewed_by_user_id' => $request->user()->id,
                'review_status' => BacSecretariatReview::STATUS_RETURNED,
                'remarks' => $validated['comments'],
                'started_at' => $document->bac_secretariat_review_started_at ?? $document->bac_secretariat_received_at,
                'completed_at' => now(),
            ]);

            $this->recordRouting($document, $request->user(), 'Returned by BAC Secretariat', $oldStatus, $document->status, $validated['comments'], $fromOffice, $toOffice);
            SystemNotificationService::notify(
                $returnToAccounting ? $accountingUser : $document->submittedBy,
                'Document Returned by BAC Secretariat',
                "Document {$document->tracking_number} was returned for correction or clarification.",
                SystemNotification::TYPE_WARNING,
                'BAC Secretariat',
                $document,
            );
            AuditLogger::log('BAC Secretariat', 'Document Returned by BAC Secretariat', 'BAC Secretariat returned document.', $document, ['status' => $oldStatus], ['status' => $document->status, 'return_target' => $validated['return_target']], 'warning');
        });

        return redirect()
            ->route('bac-secretariat.incoming.index')
            ->with('status', 'Document returned successfully.');
    }

    public function markReady(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document) || $document->status !== ProcurementDocument::STATUS_UNDER_BAC_SECRETARIAT_REVIEW) {
            AuditLogger::log('BAC Secretariat', 'Unauthorized Access Attempt', 'Invalid mark ready attempt.', $document, null, null, 'warning');

            return back()->with('error', 'This document cannot be marked ready for routing.');
        }

        $validated = $request->validate([
            'remarks' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($request, $document, $validated) {
            $oldStatus = $document->status;

            $document->update([
                'status' => ProcurementDocument::STATUS_READY_FOR_DOCUMENT_ROUTING,
                'bac_secretariat_status' => BacSecretariatReview::STATUS_READY_FOR_ROUTING,
                'stage' => ProcurementDocument::STAGE_READY_FOR_ROUTING,
                'bac_secretariat_processed_at' => now(),
                'bac_secretariat_remarks' => $validated['remarks'] ?? $document->bac_secretariat_remarks,
                'assigned_to_user_id' => $request->user()->id,
            ]);

            $document->bacSecretariatReviews()->create([
                'reviewed_by_user_id' => $request->user()->id,
                'review_status' => BacSecretariatReview::STATUS_READY_FOR_ROUTING,
                'remarks' => $validated['remarks'] ?? null,
                'started_at' => $document->bac_secretariat_review_started_at,
                'completed_at' => now(),
            ]);

            $this->recordRouting($document, $request->user(), 'Marked Ready for Document Routing', $oldStatus, $document->status, $validated['remarks'] ?? 'Ready for routing.');
            AuditLogger::log('BAC Secretariat', 'Document Marked Ready for Routing', 'BAC Secretariat marked document ready for routing.', $document, ['status' => $oldStatus], ['status' => $document->status]);
        });

        return back()->with('status', 'Document marked ready for routing.');
    }

    public function acceptPpmpForAppConsolidation(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (! $this->canAcceptPpmp($request->user(), $document)) {
            AuditLogger::log('BAC Secretariat PPMP Review', 'Unauthorized Access Attempt', 'Invalid incoming PPMP accept attempt.', $document, null, null, 'warning');

            return back()->with('error', 'This document is not eligible for APP consolidation.');
        }

        $validated = $request->validate([
            'acceptance_remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($request, $document, $validated) {
            $oldStatus = $document->status;
            $startedAt = $document->ppmp_review_started_at ?: now();
            $remarks = trim((string) ($validated['acceptance_remarks'] ?? '')) ?: null;

            $document->update([
                'status' => ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION,
                'ppmp_review_status' => PpmpReview::STATUS_ACCEPTED,
                'ppmp_reviewed_by_user_id' => $request->user()->id,
                'ppmp_reviewed_at' => now(),
                'ppmp_review_started_at' => $startedAt,
                'stage' => ProcurementDocument::STAGE_ACCEPTED_FOR_APP_CONSOLIDATION,
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

            $this->recordRouting(
                $document,
                $request->user(),
                'PPMP Accepted for APP Consolidation',
                $oldStatus,
                $document->status,
                $remarks ?: 'PPMP accepted for APP consolidation.'
            );

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

            AuditLogger::log(
                'BAC Secretariat PPMP Review',
                'PPMP Accepted',
                'BAC Secretariat accepted an incoming PPMP for APP consolidation.',
                $document,
                ['status' => $oldStatus],
                ['status' => $document->status]
            );
        });

        return redirect()
            ->route('bac-secretariat.incoming.index')
            ->with('status', 'PPMP accepted for APP consolidation. It is now available for APP consolidation.');
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

        foreach (['document_type', 'fiscal_year'] as $field) {
            $query->when($request->filled($field), fn (Builder $builder) => $builder->where($field, $request->input($field)));
        }

        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', function (Builder $builder) use ($request) {
            if ($request->input('status') === 'incoming') {
                $builder->whereIn('status', [
                    ProcurementDocument::STATUS_PENDING_PPMP_REVIEW,
                    ProcurementDocument::STATUS_UNDER_PPMP_REVIEW,
                    ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW,
                    ProcurementDocument::STATUS_RECEIVED_BY_BAC_SECRETARIAT,
                ]);

                return;
            }

            $builder->where('status', $request->input('status'));
        });

        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('updated_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('updated_at', '<=', $request->date('date_to')));
    }

    private function canAccess(User $user, ProcurementDocument $document, bool $includeReturned = false): bool
    {
        $query = $this->applyAssignedPpmpVisibility(
            ProcurementDocument::query()->incomingForBacSecretariat($user, $includeReturned),
            $user,
        );

        return $query
            ->whereKey($document->id)
            ->exists();
    }

    private function canAcceptPpmp(User $user, ProcurementDocument $document): bool
    {
        return $this->applyAssignedPpmpVisibility(
            ProcurementDocument::query()->visibleToBacSecretariat($user),
            $user,
        )
            ->whereKey($document->id)
            ->where('document_type', 'PPMP')
            ->whereIn('status', [
                ProcurementDocument::STATUS_PENDING_PPMP_REVIEW,
                ProcurementDocument::STATUS_UNDER_PPMP_REVIEW,
            ])
            ->exists();
    }

    private function summary(User $user): array
    {
        $base = $this->applyAssignedPpmpVisibility(
            ProcurementDocument::query()->visibleToBacSecretariat($user),
            $user,
        );

        return [
            'incoming' => (clone $base)->whereIn('status', [ProcurementDocument::STATUS_PENDING_PPMP_REVIEW, ProcurementDocument::STATUS_UNDER_PPMP_REVIEW, ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW, ProcurementDocument::STATUS_RECEIVED_BY_BAC_SECRETARIAT])->count(),
            'received' => (clone $base)->where('status', ProcurementDocument::STATUS_RECEIVED_BY_BAC_SECRETARIAT)->count(),
            'underReview' => (clone $base)->whereIn('status', [ProcurementDocument::STATUS_UNDER_PPMP_REVIEW, ProcurementDocument::STATUS_UNDER_BAC_SECRETARIAT_REVIEW])->count(),
            'ready' => (clone $base)->where('status', ProcurementDocument::STATUS_READY_FOR_DOCUMENT_ROUTING)->count(),
        ];
    }

    private function applyAssignedPpmpVisibility(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $visibility) use ($user) {
            $visibility->whereNull('document_type')
                ->orWhereNotIn('document_type', ['PPMP', 'Project Procurement Management Plan'])
                ->orWhere('assigned_to_user_id', $user->id)
                ->orWhereNull('assigned_to_user_id');
        });
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

    private function accountingOffice(): ?Office
    {
        return Office::where('code', 'MACCO')
            ->orWhere('name', 'Accounting Office')
            ->first();
    }

    private function accountingOfficer(): ?User
    {
        return User::where('role', User::ROLE_ACCOUNTING)
            ->where('status', User::STATUS_ACTIVE)
            ->first();
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
