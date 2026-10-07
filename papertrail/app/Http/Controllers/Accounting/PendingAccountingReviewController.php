<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AccountingReview;
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

class PendingAccountingReviewController extends Controller
{
    public function index(Request $request): View
    {
        AuditLogger::log('Accounting Review', 'Accounting Pending Review Page Viewed', 'Accounting Officer viewed pending accounting review queue.');

        $query = $this->baseAccountingQuery($request->user())
            ->whereIn('status', [
                ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW,
                ProcurementDocument::STATUS_UNDER_ACCOUNTING_REVIEW,
            ])
            ->with(['submittingOffice', 'submittedBy']);

        $this->applyFilters($query, $request);

        return view('accounting.pending-review.index', [
            'documents' => $query->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary($request->user()),
            'filters' => $request->only(['search', 'document_type', 'fiscal_year', 'priority', 'status', 'date_from', 'date_to']),
            'documentTypes' => ProcurementDocument::query()->select('document_type')->distinct()->orderBy('document_type')->pluck('document_type'),
            'fiscalYears' => ProcurementDocument::query()->select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
        ]);
    }

    public function show(Request $request, ProcurementDocument $document): View|RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('Accounting Review', 'Unauthorized Access Attempt', 'Accounting Officer attempted to access an unassigned document.', $document, null, null, 'warning');

            return redirect()
                ->route('accounting.pending-review.index')
                ->with('error', 'You are not authorized to review this document.');
        }

        AuditLogger::log('Accounting Review', 'Accounting Review Detail Viewed', 'Accounting Officer viewed accounting review details.', $document);

        return view('accounting.pending-review.show', [
            'document' => $document->load([
                'submittingOffice',
                'currentOffice',
                'submittedBy',
                'assignedTo',
                'budgetReviewedBy',
                'accountingReviewedBy',
                'budgetReviews.reviewedBy',
                'accountingReviews.reviewedBy',
                'routingHistories.actionBy',
                'routingHistories.fromOffice',
                'routingHistories.toOffice',
            ]),
            'latestBudgetReview' => $document->budgetReviews()->latest()->first(),
            'latestAccountingReview' => $document->accountingReviews()->latest()->first(),
        ]);
    }

    public function start(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document) || $document->status !== ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW) {
            AuditLogger::log('Accounting Review', 'Unauthorized Access Attempt', 'Invalid accounting review start attempt.', $document, null, null, 'warning');

            return back()->with('error', 'You are not authorized to review this document.');
        }

        DB::transaction(function () use ($request, $document) {
            $oldStatus = $document->status;

            $document->update([
                'status' => ProcurementDocument::STATUS_UNDER_ACCOUNTING_REVIEW,
                'accounting_status' => AccountingReview::STATUS_UNDER_REVIEW,
                'stage' => ProcurementDocument::STAGE_ACCOUNTING_REVIEW,
                'assigned_to_user_id' => $request->user()->id,
                'accounting_review_started_at' => now(),
            ]);

            $document->accountingReviews()->updateOrCreate(
                ['review_status' => AccountingReview::STATUS_UNDER_REVIEW],
                [
                    'reviewed_by_user_id' => $request->user()->id,
                    'started_at' => now(),
                ],
            );

            $this->recordRouting($document, $request->user(), 'Accounting Review Started', $oldStatus, $document->status, 'Accounting review started.');
            SystemNotificationService::notify(
                $document->submittedBy,
                'Accounting Review Started',
                "Document {$document->tracking_number} is now under accounting review.",
                SystemNotification::TYPE_INFO,
                'Accounting Review',
                $document,
            );
            AuditLogger::log('Accounting Review', 'Accounting Review Started', 'Accounting review started.', $document, ['status' => $oldStatus], ['status' => $document->status]);
        });

        return back()->with('status', 'Accounting review started successfully.');
    }

    public function return(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)
            || !in_array($document->status, [ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW, ProcurementDocument::STATUS_UNDER_ACCOUNTING_REVIEW], true)) {
            AuditLogger::log('Accounting Review', 'Unauthorized Access Attempt', 'Invalid accounting return attempt.', $document, null, null, 'warning');

            return back()->with('error', 'You are not authorized to return this document.');
        }

        $validated = $request->validate([
            'comments' => ['required', 'string', 'min:5'],
            'return_target' => ['required', 'in:budget,requesting_office'],
        ]);

        $budgetOffice = $this->budgetOffice();
        $budgetUser = $this->budgetOfficer();

        if ($validated['return_target'] === 'budget' && !$budgetOffice && !$budgetUser) {
            return back()->with('error', 'Budget Office routing target is not configured.');
        }

        DB::transaction(function () use ($request, $document, $validated, $budgetOffice, $budgetUser) {
            $oldStatus = $document->status;
            $fromOffice = $document->current_office_id;
            $returnToBudget = $validated['return_target'] === 'budget';
            $toOffice = $returnToBudget ? ($budgetOffice?->id ?? $budgetUser?->office_id) : $document->submitting_office_id;
            $assignedTo = $returnToBudget ? $budgetUser?->id : $document->submitted_by_user_id;
            $stage = $returnToBudget ? ProcurementDocument::STAGE_RETURNED_TO_BUDGET_OFFICE : ProcurementDocument::STAGE_RETURNED_TO_REQUESTING_OFFICE;

            $document->update([
                'status' => ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING,
                'accounting_status' => AccountingReview::STATUS_RETURNED,
                'stage' => $stage,
                'current_office_id' => $toOffice,
                'assigned_to_user_id' => $assignedTo,
                'accounting_remarks' => $validated['comments'],
                'remarks' => $validated['comments'],
                'accounting_reviewed_by_user_id' => $request->user()->id,
                'accounting_reviewed_at' => now(),
            ]);

            $document->accountingReviews()->create([
                'reviewed_by_user_id' => $request->user()->id,
                'review_status' => AccountingReview::STATUS_RETURNED,
                'remarks' => $validated['comments'],
                'started_at' => $document->accounting_review_started_at,
                'completed_at' => now(),
            ]);

            $this->recordRouting($document, $request->user(), 'Returned by Accounting Officer', $oldStatus, $document->status, $validated['comments'], $fromOffice, $toOffice);
            SystemNotificationService::notify(
                $returnToBudget ? $budgetUser : $document->submittedBy,
                'Document returned by Accounting Office',
                "Document {$document->tracking_number} was returned for correction or clarification.",
                SystemNotification::TYPE_WARNING,
                'Accounting Review',
                $document,
            );
            AuditLogger::log('Accounting Review', 'Document Returned by Accounting Officer', 'Document returned from Accounting review.', $document, ['status' => $oldStatus], ['status' => $document->status, 'return_target' => $validated['return_target']], 'warning');
        });

        return redirect()
            ->route('accounting.pending-review.index')
            ->with('status', 'Document returned successfully.');
    }

    public function verify(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document) || $document->status !== ProcurementDocument::STATUS_UNDER_ACCOUNTING_REVIEW) {
            AuditLogger::log('Accounting Review', 'Unauthorized Access Attempt', 'Invalid accounting verification attempt.', $document, null, null, 'warning');

            return back()->with('error', 'You are not authorized to verify this document.');
        }

        $validated = $request->validate([
            'accounting_reference_no' => ['nullable', 'string', 'max:255'],
            'account_code' => ['required', 'string', 'max:255'],
            'object_code' => ['nullable', 'string', 'max:255'],
            'responsibility_center' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ]);

        $bacOffice = $this->bacSecretariatOffice();
        $bacUser = $this->bacSecretariatUser();

        if (!$bacOffice && !$bacUser) {
            return back()->with('error', 'BAC Secretariat routing target is not configured.');
        }

        DB::transaction(function () use ($request, $document, $validated, $bacOffice, $bacUser) {
            $oldStatus = $document->status;
            $fromOffice = $document->current_office_id;

            $document->update([
                'status' => ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW,
                'accounting_status' => AccountingReview::STATUS_ACCOUNTING_VERIFIED,
                'stage' => ProcurementDocument::STAGE_BAC_SECRETARIAT_REVIEW,
                'current_office_id' => $bacOffice?->id ?? $bacUser?->office_id,
                'assigned_to_user_id' => $bacUser?->id,
                'accounting_reviewed_by_user_id' => $request->user()->id,
                'accounting_reviewed_at' => now(),
                'accounting_remarks' => $validated['remarks'] ?? null,
                'accounting_reference_no' => $validated['accounting_reference_no'] ?? null,
                'account_code' => $validated['account_code'],
                'object_code' => $validated['object_code'] ?? null,
                'responsibility_center' => $validated['responsibility_center'],
            ]);

            $document->accountingReviews()->create([
                'reviewed_by_user_id' => $request->user()->id,
                'review_status' => AccountingReview::STATUS_ACCOUNTING_VERIFIED,
                'accounting_reference_no' => $validated['accounting_reference_no'] ?? null,
                'account_code' => $validated['account_code'],
                'object_code' => $validated['object_code'] ?? null,
                'responsibility_center' => $validated['responsibility_center'],
                'remarks' => $validated['remarks'] ?? null,
                'started_at' => $document->accounting_review_started_at,
                'completed_at' => now(),
            ]);

            $this->recordRouting($document, $request->user(), 'Accounting Verified / Forwarded to BAC Secretariat', $oldStatus, $document->status, $validated['remarks'] ?? 'Accounting verification completed.', $fromOffice, $document->current_office_id);
            SystemNotificationService::notify(
                $bacUser,
                'New Document for BAC Secretariat Review',
                "Document {$document->tracking_number} has been verified by Accounting and forwarded to BAC Secretariat.",
                SystemNotification::TYPE_SUCCESS,
                'Accounting Review',
                $document,
                route('bac-secretariat.incoming.show', $document),
            );
            AuditLogger::log('Accounting Review', 'Accounting Verification Completed', 'Accounting verified and document forwarded to BAC Secretariat.', $document, ['status' => $oldStatus], ['status' => $document->status, 'account_code' => $validated['account_code']]);
            AuditLogger::log('Accounting Review', 'Document Forwarded to BAC Secretariat', 'Document forwarded after accounting verification.', $document);
        });

        return redirect()
            ->route('accounting.pending-review.index')
            ->with('status', 'Accounting verified and document forwarded to BAC Secretariat.');
    }

    private function baseAccountingQuery(User $user): Builder
    {
        return ProcurementDocument::query()->visibleToAccountingOfficer($user);
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

        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('updated_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('updated_at', '<=', $request->date('date_to')));
    }

    private function canAccess(User $user, ProcurementDocument $document): bool
    {
        return $this->baseAccountingQuery($user)
            ->whereIn('status', [
                ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW,
                ProcurementDocument::STATUS_UNDER_ACCOUNTING_REVIEW,
            ])
            ->whereKey($document->id)
            ->exists();
    }

    private function summary(User $user): array
    {
        $base = $this->baseAccountingQuery($user);

        return [
            'pending' => (clone $base)->where('status', ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW)->count(),
            'underReview' => (clone $base)->where('status', ProcurementDocument::STATUS_UNDER_ACCOUNTING_REVIEW)->count(),
            'returned' => ProcurementDocument::query()->returnedByAccountingOfficer($user)->count(),
            'reviewed' => ProcurementDocument::query()->reviewedByAccountingOfficer($user)->count(),
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

    private function budgetOffice(): ?Office
    {
        return Office::where('code', 'MBO')
            ->orWhere('name', 'Budget Office')
            ->first();
    }

    private function budgetOfficer(): ?User
    {
        return User::where('role', User::ROLE_BUDGET)
            ->where('status', User::STATUS_ACTIVE)
            ->first();
    }

    private function bacSecretariatOffice(): ?Office
    {
        return Office::where('code', 'BACSEC')
            ->orWhere('name', 'BAC Secretariat')
            ->first();
    }

    private function bacSecretariatUser(): ?User
    {
        return User::where(function (Builder $query) {
                $query->where('role', User::ROLE_BAC_SECRETARIAT)
                    ->orWhere('user_id', 'BACSEC-001');
            })
            ->where('status', User::STATUS_ACTIVE)
            ->first();
    }
}
