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

class ReturnedDocumentsController extends Controller
{
    public function index(Request $request): View
    {
        AuditLogger::log('Accounting Review', 'Accounting Returned Documents Page Viewed', 'Accounting Officer viewed returned documents.');

        $query = $this->returnedQuery($request->user())
            ->with(['submittingOffice', 'submittedBy', 'currentOffice', 'assignedTo', 'accountingReviewedBy', 'accountingReviews.reviewedBy', 'routingHistories.actionBy', 'routingHistories.toOffice']);

        $this->applyFilters($query, $request);

        $optionsQuery = $this->returnedQuery($request->user());

        return view('accounting.returned.index', [
            'documents' => $query->latest('accounting_reviewed_at')->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary($request->user()),
            'filters' => $request->only(['search', 'document_type', 'fiscal_year', 'return_target', 'accounting_status', 'date_from', 'date_to']),
            'documentTypes' => (clone $optionsQuery)->select('document_type')->distinct()->orderBy('document_type')->pluck('document_type'),
            'fiscalYears' => (clone $optionsQuery)->select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
            'accountingStatuses' => [
                AccountingReview::STATUS_RETURNED,
                AccountingReview::STATUS_NON_COMPLIANT,
            ],
        ]);
    }

    public function show(Request $request, ProcurementDocument $document): View|RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('Accounting Review', 'Unauthorized Access Attempt', 'Accounting Officer attempted to view a returned document outside their scope.', $document, null, null, 'warning');

            return redirect()
                ->route('accounting.returned.index')
                ->with('error', 'You are not authorized to view this returned document.');
        }

        AuditLogger::log('Accounting Review', 'Accounting Returned Document Detail Viewed', 'Accounting Officer viewed returned document detail.', $document);

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

        return view('accounting.returned.show', [
            'document' => $document,
            'latestBudgetReview' => $document->budgetReviews
                ->sortByDesc(fn (BudgetReview $review) => $review->completed_at ?? $review->created_at)
                ->first(),
            'latestAccountingReview' => $this->latestReturnReview($document),
            'returnHistory' => $this->returnHistory($document),
        ]);
    }

    private function returnedQuery(User $user): Builder
    {
        return ProcurementDocument::query()->returnedByAccountingOfficer($user);
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

        foreach (['document_type', 'fiscal_year', 'accounting_status'] as $field) {
            $query->when($request->filled($field), fn (Builder $builder) => $builder->where($field, $request->input($field)));
        }

        $query->when($request->filled('return_target'), function (Builder $builder) use ($request) {
            $target = $request->input('return_target');
            $builder->whereHas('routingHistories', function (Builder $history) use ($target) {
                $history->where('status_to', ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING)
                    ->whereHas('toOffice', function (Builder $office) use ($target) {
                        if ($target === 'budget') {
                            $office->where('code', 'MBO')->orWhere('name', 'Budget Office');
                        } elseif ($target === 'requesting_office') {
                            $office->where('code', '!=', 'MBO')->where('name', '!=', 'Budget Office');
                        }
                    });
            });
        });

        $query->when($request->filled('date_from'), function (Builder $builder) use ($request) {
            $date = $request->date('date_from');
            $builder->where(function (Builder $nested) use ($date) {
                $nested->whereDate('accounting_reviewed_at', '>=', $date)
                    ->orWhereHas('accountingReviews', fn (Builder $review) => $review->whereDate('completed_at', '>=', $date))
                    ->orWhereHas('routingHistories', fn (Builder $history) => $history->where('status_to', ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING)->whereDate('action_at', '>=', $date));
            });
        });

        $query->when($request->filled('date_to'), function (Builder $builder) use ($request) {
            $date = $request->date('date_to');
            $builder->where(function (Builder $nested) use ($date) {
                $nested->whereDate('accounting_reviewed_at', '<=', $date)
                    ->orWhereHas('accountingReviews', fn (Builder $review) => $review->whereDate('completed_at', '<=', $date))
                    ->orWhereHas('routingHistories', fn (Builder $history) => $history->where('status_to', ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING)->whereDate('action_at', '<=', $date));
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
                $query->whereMonth('accounting_reviewed_at', now()->month)
                    ->whereYear('accounting_reviewed_at', now()->year)
                    ->orWhereHas('accountingReviews', function (Builder $review) {
                        $review->whereMonth('completed_at', now()->month)
                            ->whereYear('completed_at', now()->year);
                    })
                    ->orWhereHas('routingHistories', function (Builder $history) {
                        $history->where('status_to', ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING)
                            ->whereMonth('action_at', now()->month)
                            ->whereYear('action_at', now()->year);
                    });
            })->count(),
            'returnedToBudget' => $this->countReturnedTo($user, 'budget'),
            'returnedToRequestingOffice' => $this->countReturnedTo($user, 'requesting_office'),
        ];
    }

    private function countReturnedTo(User $user, string $target): int
    {
        return $this->returnedQuery($user)
            ->whereHas('routingHistories', function (Builder $history) use ($target) {
                $history->where('status_to', ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING)
                    ->whereHas('toOffice', function (Builder $office) use ($target) {
                        if ($target === 'budget') {
                            $office->where('code', 'MBO')->orWhere('name', 'Budget Office');
                        } else {
                            $office->where('code', '!=', 'MBO')->where('name', '!=', 'Budget Office');
                        }
                    });
            })
            ->count();
    }

    private function latestReturnReview(ProcurementDocument $document): ?AccountingReview
    {
        return $document->accountingReviews
            ->filter(fn (AccountingReview $review) => in_array($review->review_status, [AccountingReview::STATUS_RETURNED, AccountingReview::STATUS_NON_COMPLIANT], true))
            ->sortByDesc(fn (AccountingReview $review) => $review->completed_at ?? $review->created_at)
            ->first();
    }

    private function returnHistory(ProcurementDocument $document)
    {
        return $document->routingHistories
            ->first(fn ($history) => $history->status_to === ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING);
    }
}
