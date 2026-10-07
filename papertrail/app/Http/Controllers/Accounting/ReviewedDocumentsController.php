<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AccountingReview;
use App\Models\BudgetReview;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewedDocumentsController extends Controller
{
    public function index(Request $request): View
    {
        AuditLogger::log('Accounting Review', 'Accounting Reviewed Documents Page Viewed', 'Accounting Officer viewed reviewed documents.');

        $query = $this->reviewedQuery($request->user())
            ->with(['submittingOffice', 'submittedBy', 'currentOffice', 'assignedTo', 'accountingReviews.reviewedBy']);

        $this->applyFilters($query, $request);

        $baseForOptions = $this->reviewedQuery($request->user());

        return view('accounting.reviewed.index', [
            'documents' => $query->latest('accounting_reviewed_at')->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary($request->user()),
            'filters' => $request->only(['search', 'document_type', 'fiscal_year', 'accounting_status', 'status', 'date_from', 'date_to']),
            'documentTypes' => (clone $baseForOptions)->select('document_type')->distinct()->orderBy('document_type')->pluck('document_type'),
            'fiscalYears' => (clone $baseForOptions)->select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
            'accountingStatuses' => [
                AccountingReview::STATUS_ACCOUNTING_VERIFIED,
                'reviewed',
                'endorsed',
            ],
            'statuses' => [
                ProcurementDocument::STATUS_ACCOUNTING_REVIEWED,
                ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW,
                'under_bac_secretariat_review',
                'bac_secretariat_reviewed',
                'approved',
            ],
        ]);
    }

    public function show(Request $request, ProcurementDocument $document): View|RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('Accounting Review', 'Unauthorized Access Attempt', 'Accounting Officer attempted to view a reviewed document outside their scope.', $document, null, null, 'warning');

            return redirect()
                ->route('accounting.reviewed.index')
                ->with('error', 'You are not authorized to view this reviewed document.');
        }

        AuditLogger::log('Accounting Review', 'Accounting Reviewed Document Detail Viewed', 'Accounting Officer viewed reviewed document detail.', $document);

        $document->load([
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
        ]);

        return view('accounting.reviewed.show', [
            'document' => $document,
            'latestBudgetReview' => $document->budgetReviews
                ->sortByDesc(fn (BudgetReview $review) => $review->completed_at ?? $review->created_at)
                ->first(),
            'latestAccountingReview' => $document->accountingReviews
                ->sortByDesc(fn (AccountingReview $review) => $review->completed_at ?? $review->created_at)
                ->first(),
        ]);
    }

    private function reviewedQuery(User $user): Builder
    {
        return ProcurementDocument::query()->reviewedByAccountingOfficer($user);
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

        foreach (['document_type', 'fiscal_year', 'accounting_status', 'status'] as $field) {
            $query->when($request->filled($field), fn (Builder $builder) => $builder->where($field, $request->input($field)));
        }

        $query->when($request->filled('date_from'), function (Builder $builder) use ($request) {
            $date = $request->date('date_from');
            $builder->where(function (Builder $nested) use ($date) {
                $nested->whereDate('accounting_reviewed_at', '>=', $date)
                    ->orWhereHas('accountingReviews', fn (Builder $review) => $review->whereDate('completed_at', '>=', $date));
            });
        });

        $query->when($request->filled('date_to'), function (Builder $builder) use ($request) {
            $date = $request->date('date_to');
            $builder->where(function (Builder $nested) use ($date) {
                $nested->whereDate('accounting_reviewed_at', '<=', $date)
                    ->orWhereHas('accountingReviews', fn (Builder $review) => $review->whereDate('completed_at', '<=', $date));
            });
        });
    }

    private function canAccess(User $user, ProcurementDocument $document): bool
    {
        return $this->reviewedQuery($user)->whereKey($document->id)->exists();
    }

    private function summary(User $user): array
    {
        $base = $this->reviewedQuery($user);

        return [
            'totalReviewed' => (clone $base)->count(),
            'forwardedToBac' => (clone $base)->where('status', ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW)->count(),
            'accountingVerified' => (clone $base)->where('accounting_status', AccountingReview::STATUS_ACCOUNTING_VERIFIED)->count(),
            'reviewedThisMonth' => (clone $base)->where(function (Builder $query) {
                $query->whereMonth('accounting_reviewed_at', now()->month)
                    ->whereYear('accounting_reviewed_at', now()->year)
                    ->orWhereHas('accountingReviews', function (Builder $review) {
                        $review->whereMonth('completed_at', now()->month)
                            ->whereYear('completed_at', now()->year);
                    });
            })->count(),
        ];
    }
}
