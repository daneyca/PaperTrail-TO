<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Models\BudgetReview;
use App\Models\DocumentRoutingHistory;
use App\Models\Office;
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

class PendingBudgetReviewController extends Controller
{
    public function index(Request $request): View
    {
        AuditLogger::log('Budget Review', 'Pending Budget Review Page Viewed', 'Budget Officer viewed pending budget review queue.');

        $query = $this->baseBudgetQuery($request->user())
            ->whereIn('status', [
                ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW,
                ProcurementDocument::STATUS_UNDER_BUDGET_REVIEW,
            ])
            ->with(['submittingOffice', 'submittedBy']);

        $this->applyFilters($query, $request);

        return view('budget.pending-review.index', [
            'documents' => $query->latest('submitted_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary($request->user()),
            'filters' => $request->only(['search', 'document_type', 'fiscal_year', 'priority', 'status', 'date_from', 'date_to']),
            'documentTypes' => ProcurementDocument::query()->select('document_type')->distinct()->orderBy('document_type')->pluck('document_type'),
            'fiscalYears' => ProcurementDocument::query()->select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
        ]);
    }

    public function show(Request $request, ProcurementDocument $document): View|RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('Budget Review', 'Unauthorized Access Attempt', 'Budget Officer attempted to access an unassigned document.', $document, null, null, 'warning');

            return redirect()
                ->route('budget.pending-review.index')
                ->with('error', 'You are not authorized to review this document.');
        }

        AuditLogger::log('Budget Review', 'Budget Review Detail Viewed', 'Budget Officer viewed budget review details.', $document);

        return view('budget.pending-review.show', [
            'document' => $document->load([
                'submittingOffice',
                'currentOffice',
                'submittedBy',
                'assignedTo',
                'budgetReviewedBy',
                'budgetReviews.reviewedBy',
                'routingHistories.actionBy',
                'routingHistories.fromOffice',
                'routingHistories.toOffice',
            ]),
            'latestBudgetReview' => $document->budgetReviews()->latest()->first(),
        ]);
    }

    public function start(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document) || $document->status !== ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW) {
            AuditLogger::log('Budget Review', 'Unauthorized Access Attempt', 'Invalid budget review start attempt.', $document, null, null, 'warning');

            return back()->with('error', 'You are not authorized to review this document.');
        }

        DB::transaction(function () use ($request, $document) {
            $oldStatus = $document->status;

            $document->update([
                'status' => ProcurementDocument::STATUS_UNDER_BUDGET_REVIEW,
                'budget_status' => BudgetReview::STATUS_UNDER_REVIEW,
                'stage' => ProcurementDocument::STAGE_BUDGET_REVIEW,
                'assigned_to_user_id' => $request->user()->id,
                'budget_review_started_at' => now(),
            ]);

            $document->budgetReviews()->updateOrCreate(
                ['review_status' => BudgetReview::STATUS_UNDER_REVIEW],
                [
                    'reviewed_by_user_id' => $request->user()->id,
                    'requested_amount' => $document->total_amount,
                    'started_at' => now(),
                ],
            );

            $this->recordRouting($document, $request->user(), 'Budget Review Started', $oldStatus, $document->status, 'Budget review started.');
            SystemNotificationService::notify(
                $document->submittedBy,
                'Budget Review Started',
                "Your document {$document->tracking_number} is now under budget review.",
                SystemNotification::TYPE_INFO,
                'Budget Review',
                $document,
            );
            AuditLogger::log('Budget Review', 'Budget Review Started', 'Budget review started.', $document, ['status' => $oldStatus], ['status' => $document->status]);
        });

        return back()->with('status', 'Review started successfully.');
    }

    public function return(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)
            || !in_array($document->status, [ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW, ProcurementDocument::STATUS_UNDER_BUDGET_REVIEW], true)) {
            AuditLogger::log('Budget Review', 'Unauthorized Access Attempt', 'Invalid budget return attempt.', $document, null, null, 'warning');

            return back()->with('error', 'You are not authorized to review this document.');
        }

        $validated = $request->validate([
            'comments' => ['required', 'string', 'min:5'],
        ]);

        DB::transaction(function () use ($request, $document, $validated) {
            $oldStatus = $document->status;
            $fromOffice = $document->current_office_id;

            $document->update([
                'status' => ProcurementDocument::STATUS_RETURNED_BY_BUDGET,
                'budget_status' => BudgetReview::STATUS_RETURNED,
                'stage' => ProcurementDocument::STAGE_RETURNED_TO_END_USER,
                'current_office_id' => $document->submitting_office_id,
                'assigned_to_user_id' => $document->submitted_by_user_id,
                'budget_remarks' => $validated['comments'],
                'remarks' => $validated['comments'],
                'budget_reviewed_by_user_id' => $request->user()->id,
                'budget_reviewed_at' => now(),
            ]);

            $document->budgetReviews()->create([
                'reviewed_by_user_id' => $request->user()->id,
                'review_status' => BudgetReview::STATUS_RETURNED,
                'requested_amount' => $document->total_amount,
                'remarks' => $validated['comments'],
                'started_at' => $document->budget_review_started_at,
                'completed_at' => now(),
            ]);

            $this->recordRouting($document, $request->user(), 'Returned by Budget Officer', $oldStatus, $document->status, $validated['comments'], $fromOffice, $document->submitting_office_id);
            SystemNotificationService::notify(
                $document->submittedBy,
                'Document returned by Budget Office',
                "Document {$document->tracking_number} was returned for correction.",
                SystemNotification::TYPE_WARNING,
                'Budget Review',
                $document,
            );
            AuditLogger::log('Budget Review', 'Document Returned by Budget Officer', 'Document returned to requesting office.', $document, ['status' => $oldStatus], ['status' => $document->status, 'comments' => $validated['comments']], 'warning');
        });

        return redirect()
            ->route('budget.pending-review.index')
            ->with('status', 'Document returned to requesting office.');
    }

    public function markAvailable(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document) || $document->status !== ProcurementDocument::STATUS_UNDER_BUDGET_REVIEW) {
            AuditLogger::log('Budget Review', 'Unauthorized Access Attempt', 'Invalid budget availability attempt.', $document, null, null, 'warning');

            return back()->with('error', 'You are not authorized to review this document.');
        }

        $validated = $request->validate([
            'fund_source' => ['required', 'string', 'max:255'],
            'available_amount' => ['required', 'numeric', 'gte:' . $document->total_amount],
            'appropriation_code' => ['nullable', 'string', 'max:255'],
            'responsibility_center' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ]);

        $accountingOffice = $this->accountingOffice();
        $accountingUser = $this->accountingOfficer();

        if (!$accountingOffice && !$accountingUser) {
            return back()->with('error', 'Accounting Office routing target is not configured.');
        }

        DB::transaction(function () use ($request, $document, $validated, $accountingOffice, $accountingUser) {
            $oldStatus = $document->status;
            $fromOffice = $document->current_office_id;

            $document->update([
                'status' => ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW,
                'budget_status' => BudgetReview::STATUS_BUDGET_AVAILABLE,
                'stage' => ProcurementDocument::STAGE_ACCOUNTING_REVIEW,
                'current_office_id' => $accountingOffice?->id ?? $accountingUser?->office_id,
                'assigned_to_user_id' => $accountingUser?->id,
                'budget_reviewed_by_user_id' => $request->user()->id,
                'budget_reviewed_at' => now(),
                'budget_remarks' => $validated['remarks'] ?? null,
            ]);

            $document->budgetReviews()->create([
                'reviewed_by_user_id' => $request->user()->id,
                'review_status' => BudgetReview::STATUS_BUDGET_AVAILABLE,
                'requested_amount' => $document->total_amount,
                'available_amount' => $validated['available_amount'],
                'fund_source' => $validated['fund_source'],
                'appropriation_code' => $validated['appropriation_code'] ?? null,
                'responsibility_center' => $validated['responsibility_center'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'started_at' => $document->budget_review_started_at,
                'completed_at' => now(),
            ]);

            $this->recordRouting($document, $request->user(), 'Budget Available / Forwarded to Accounting', $oldStatus, $document->status, $validated['remarks'] ?? 'Budget available.', $fromOffice, $document->current_office_id);
            SystemNotificationService::notify(
                $accountingUser,
                'New Document for Accounting Review',
                "Document {$document->tracking_number} has been forwarded for accounting verification.",
                SystemNotification::TYPE_INFO,
                'Budget Review',
                $document,
                route('accounting.pending-review.show', $document),
            );
            AuditLogger::log('Budget Review', 'Budget Availability Marked', 'Budget availability marked and document forwarded to Accounting Office.', $document, ['status' => $oldStatus], ['status' => $document->status, 'fund_source' => $validated['fund_source']]);
        });

        return redirect()
            ->route('budget.pending-review.index')
            ->with('status', 'Budget availability marked and document forwarded to Accounting Office.');
    }

    private function baseBudgetQuery(User $user): Builder
    {
        return ProcurementDocument::query()->visibleToBudgetOfficer($user);
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

        foreach (['document_type', 'fiscal_year', 'priority', 'status'] as $field) {
            $query->when($request->filled($field), fn (Builder $builder) => $builder->where($field, $request->input($field)));
        }

        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('submitted_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('submitted_at', '<=', $request->date('date_to')));
    }

    private function canAccess(User $user, ProcurementDocument $document): bool
    {
        return $this->baseBudgetQuery($user)->whereKey($document->id)->exists();
    }

    private function summary(User $user): array
    {
        $base = $this->baseBudgetQuery($user);

        return [
            'pending' => (clone $base)->where('status', ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW)->count(),
            'underReview' => (clone $base)->where('status', ProcurementDocument::STATUS_UNDER_BUDGET_REVIEW)->count(),
            'returned' => ProcurementDocument::query()->returnedByBudgetOfficer($user)->count(),
            'reviewed' => ProcurementDocument::query()->reviewedByBudgetOfficer($user)->count(),
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
}
