<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
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
        AuditLogger::log('Budget Review', 'Budget Reviewed Documents Page Viewed', 'Budget Officer viewed reviewed documents.');

        $query = $this->reviewedQuery($request->user())
            ->with(['submittingOffice', 'submittedBy', 'currentOffice', 'assignedTo', 'budgetReviews.reviewedBy']);

        $this->applyFilters($query, $request);

        $baseForOptions = $this->reviewedQuery($request->user());

        return view('budget.reviewed.index', [
            'documents' => $query->latest('budget_reviewed_at')->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary($request->user()),
            'filters' => $request->only(['search', 'document_type', 'fiscal_year', 'status', 'budget_status', 'date_from', 'date_to']),
            'documentTypes' => (clone $baseForOptions)->select('document_type')->distinct()->orderBy('document_type')->pluck('document_type'),
            'fiscalYears' => (clone $baseForOptions)->select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
            'statuses' => [
                ProcurementDocument::STATUS_BUDGET_REVIEWED,
                ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW,
            ],
        ]);
    }

    public function show(Request $request, ProcurementDocument $document): View|RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('Budget Review', 'Unauthorized Access Attempt', 'Budget Officer attempted to view a reviewed document outside their scope.', $document, null, null, 'warning');

            return redirect()
                ->route('budget.reviewed.index')
                ->with('error', 'You are not authorized to view this reviewed document.');
        }

        AuditLogger::log('Budget Review', 'Reviewed Document Detail Viewed', 'Budget Officer viewed reviewed document detail.', $document);

        $document->load([
            'submittingOffice',
            'currentOffice',
            'submittedBy',
            'assignedTo',
            'budgetReviewedBy',
            'budgetReviews.reviewedBy',
            'routingHistories.actionBy',
            'routingHistories.fromOffice',
            'routingHistories.toOffice',
        ]);

        return view('budget.reviewed.show', [
            'document' => $document,
            'latestBudgetReview' => $document->budgetReviews
                ->sortByDesc(fn (BudgetReview $review) => $review->completed_at ?? $review->created_at)
                ->first(),
        ]);
    }

    private function reviewedQuery(User $user): Builder
    {
        return ProcurementDocument::query()->reviewedByBudgetOfficer($user);
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

        foreach (['document_type', 'fiscal_year', 'status', 'budget_status'] as $field) {
            $query->when($request->filled($field), fn (Builder $builder) => $builder->where($field, $request->input($field)));
        }

        $query->when($request->filled('date_from'), function (Builder $builder) use ($request) {
            $date = $request->date('date_from');
            $builder->where(function (Builder $nested) use ($date) {
                $nested->whereDate('budget_reviewed_at', '>=', $date)
                    ->orWhereHas('budgetReviews', fn (Builder $review) => $review->whereDate('completed_at', '>=', $date));
            });
        });

        $query->when($request->filled('date_to'), function (Builder $builder) use ($request) {
            $date = $request->date('date_to');
            $builder->where(function (Builder $nested) use ($date) {
                $nested->whereDate('budget_reviewed_at', '<=', $date)
                    ->orWhereHas('budgetReviews', fn (Builder $review) => $review->whereDate('completed_at', '<=', $date));
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
            'forwardedToAccounting' => (clone $base)->where('status', ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW)->count(),
            'budgetAvailable' => (clone $base)->where('budget_status', BudgetReview::STATUS_BUDGET_AVAILABLE)->count(),
            'reviewedThisMonth' => (clone $base)->where(function (Builder $query) {
                $query->whereMonth('budget_reviewed_at', now()->month)
                    ->whereYear('budget_reviewed_at', now()->year)
                    ->orWhereHas('budgetReviews', function (Builder $review) {
                        $review->whereMonth('completed_at', now()->month)
                            ->whereYear('completed_at', now()->year);
                    });
            })->count(),
        ];
    }
}
