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

class ReturnedDocumentsController extends Controller
{
    public function index(Request $request): View
    {
        AuditLogger::log('Budget Review', 'Returned Documents Page Viewed', 'Budget Officer viewed returned documents.');

        $query = $this->returnedQuery($request->user())
            ->with(['submittingOffice', 'submittedBy', 'currentOffice', 'assignedTo', 'budgetReviewedBy', 'budgetReviews.reviewedBy', 'routingHistories.actionBy']);

        $this->applyFilters($query, $request);

        $optionsQuery = $this->returnedQuery($request->user());

        return view('budget.returned.index', [
            'documents' => $query->latest('budget_reviewed_at')->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary($request->user()),
            'filters' => $request->only(['search', 'document_type', 'fiscal_year', 'status', 'budget_status', 'date_from', 'date_to']),
            'documentTypes' => (clone $optionsQuery)->select('document_type')->distinct()->orderBy('document_type')->pluck('document_type'),
            'fiscalYears' => (clone $optionsQuery)->select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
            'budgetStatuses' => [
                BudgetReview::STATUS_RETURNED,
                BudgetReview::STATUS_INSUFFICIENT_FUNDS,
            ],
        ]);
    }

    public function show(Request $request, ProcurementDocument $document): View|RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('Budget Review', 'Unauthorized Access Attempt', 'Budget Officer attempted to view a returned document outside their scope.', $document, null, null, 'warning');

            return redirect()
                ->route('budget.returned.index')
                ->with('error', 'You are not authorized to view this returned document.');
        }

        AuditLogger::log('Budget Review', 'Returned Document Detail Viewed', 'Budget Officer viewed returned document detail.', $document);

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

        return view('budget.returned.show', [
            'document' => $document,
            'latestBudgetReview' => $this->latestReturnReview($document),
            'returnHistory' => $this->returnHistory($document),
        ]);
    }

    private function returnedQuery(User $user): Builder
    {
        return ProcurementDocument::query()->returnedByBudgetOfficer($user);
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
                    ->orWhereHas('budgetReviews', fn (Builder $review) => $review->whereDate('completed_at', '>=', $date))
                    ->orWhereHas('routingHistories', fn (Builder $history) => $history->where('status_to', ProcurementDocument::STATUS_RETURNED_BY_BUDGET)->whereDate('action_at', '>=', $date));
            });
        });

        $query->when($request->filled('date_to'), function (Builder $builder) use ($request) {
            $date = $request->date('date_to');
            $builder->where(function (Builder $nested) use ($date) {
                $nested->whereDate('budget_reviewed_at', '<=', $date)
                    ->orWhereHas('budgetReviews', fn (Builder $review) => $review->whereDate('completed_at', '<=', $date))
                    ->orWhereHas('routingHistories', fn (Builder $history) => $history->where('status_to', ProcurementDocument::STATUS_RETURNED_BY_BUDGET)->whereDate('action_at', '<=', $date));
            });
        });
    }

    private function canAccess(User $user, ProcurementDocument $document): bool
    {
        return $this->returnedQuery($user)->whereKey($document->id)->exists();
    }

    private function summary(User $user): array
    {
        $base = $this->returnedQuery($user);

        return [
            'totalReturned' => (clone $base)->count(),
            'returnedThisMonth' => (clone $base)->where(function (Builder $query) {
                $query->whereMonth('budget_reviewed_at', now()->month)
                    ->whereYear('budget_reviewed_at', now()->year)
                    ->orWhereHas('budgetReviews', function (Builder $review) {
                        $review->whereMonth('completed_at', now()->month)
                            ->whereYear('completed_at', now()->year);
                    })
                    ->orWhereHas('routingHistories', function (Builder $history) {
                        $history->where('status_to', ProcurementDocument::STATUS_RETURNED_BY_BUDGET)
                            ->whereMonth('action_at', now()->month)
                            ->whereYear('action_at', now()->year);
                    });
            })->count(),
            'insufficientFunds' => (clone $base)->where('budget_status', BudgetReview::STATUS_INSUFFICIENT_FUNDS)->count(),
            'awaitingResubmission' => (clone $base)->where('status', ProcurementDocument::STATUS_RETURNED_BY_BUDGET)->count(),
        ];
    }

    private function latestReturnReview(ProcurementDocument $document): ?BudgetReview
    {
        return $document->budgetReviews
            ->filter(fn (BudgetReview $review) => in_array($review->review_status, [BudgetReview::STATUS_RETURNED, BudgetReview::STATUS_INSUFFICIENT_FUNDS], true))
            ->sortByDesc(fn (BudgetReview $review) => $review->completed_at ?? $review->created_at)
            ->first();
    }

    private function returnHistory(ProcurementDocument $document)
    {
        return $document->routingHistories
            ->first(fn ($history) => $history->status_to === ProcurementDocument::STATUS_RETURNED_BY_BUDGET);
    }
}
