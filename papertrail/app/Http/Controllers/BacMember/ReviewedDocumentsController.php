<?php

namespace App\Http\Controllers\BacMember;

use App\Http\Controllers\Controller;
use App\Models\AccountingReview;
use App\Models\BacMemberReview;
use App\Models\BacSecretariatReview;
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
        AuditLogger::log('BAC Member Review', 'BAC Member Reviewed Documents Page Viewed', 'BAC Member viewed reviewed documents.');

        $query = $this->reviewedQuery($request->user())
            ->with([
                'submittingOffice',
                'currentOffice',
                'submittedBy',
                'assignedTo',
                'bacMemberReviewedBy',
                'bacMemberReviews.reviewedBy',
                'routingHistories.actionBy',
                'routingHistories.toOffice',
            ]);

        $this->applyFilters($query, $request);

        $baseForOptions = $this->reviewedQuery($request->user());

        return view('bac-member.reviewed.index', [
            'documents' => $query->latest('bac_member_reviewed_at')->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary($request->user()),
            'filters' => $request->only(['search', 'document_type', 'fiscal_year', 'outcome', 'status', 'date_from', 'date_to']),
            'documentTypes' => (clone $baseForOptions)->select('document_type')->distinct()->orderBy('document_type')->pluck('document_type'),
            'fiscalYears' => (clone $baseForOptions)->select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
            'statuses' => [
                ProcurementDocument::STATUS_ENDORSED_BY_BAC_MEMBER,
                ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW,
                'under_bac_chair_review',
                'bac_chair_reviewed',
                ProcurementDocument::STATUS_RETURNED_BY_BAC_MEMBER,
            ],
        ]);
    }

    public function show(Request $request, ProcurementDocument $document): View|RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('BAC Member Review', 'Unauthorized Access Attempt', 'BAC Member attempted to view a reviewed document outside their scope.', $document, null, null, 'warning');

            return redirect()
                ->route('bac-member.reviewed.index')
                ->with('error', 'You are not authorized to view this reviewed document.');
        }

        AuditLogger::log('BAC Member Review', 'BAC Member Reviewed Document Detail Viewed', 'BAC Member viewed reviewed document detail.', $document);

        $document->load([
            'submittingOffice',
            'currentOffice',
            'submittedBy',
            'assignedTo',
            'budgetReviewedBy',
            'accountingReviewedBy',
            'bacSecretariatReceivedBy',
            'bacMemberReviewedBy',
            'budgetReviews.reviewedBy',
            'accountingReviews.reviewedBy',
            'bacSecretariatReviews.reviewedBy',
            'bacMemberReviews.reviewedBy',
            'routingHistories.actionBy',
            'routingHistories.fromOffice',
            'routingHistories.toOffice',
            'purchaseRequestItems',
        ]);

        return view('bac-member.reviewed.show', [
            'document' => $document,
            'latestBudgetReview' => $document->budgetReviews->sortByDesc(fn (BudgetReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestAccountingReview' => $document->accountingReviews->sortByDesc(fn (AccountingReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestBacSecretariatReview' => $document->bacSecretariatReviews->sortByDesc(fn (BacSecretariatReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestBacMemberReview' => $this->latestBacMemberReview($document, $request->user()),
            'outcome' => $this->outcome($document),
            'destination' => $this->destination($document),
        ]);
    }

    private function reviewedQuery(User $user): Builder
    {
        return ProcurementDocument::query()->reviewedByBacMember($user);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();
            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('tracking_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhereHas('submittingOffice', fn (Builder $office) => $office->where('name', 'like', "%{$search}%"));
            });
        });

        foreach (['document_type', 'fiscal_year', 'status'] as $field) {
            $query->when($request->filled($field), fn (Builder $builder) => $builder->where($field, $request->input($field)));
        }

        $query->when($request->filled('outcome'), function (Builder $builder) use ($request) {
            if ($request->input('outcome') === 'endorsed') {
                $builder->whereIn('status', [
                    ProcurementDocument::STATUS_ENDORSED_BY_BAC_MEMBER,
                    ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW,
                    'under_bac_chair_review',
                    'bac_chair_reviewed',
                ]);
            }

            if ($request->input('outcome') === 'returned') {
                $builder->where('status', ProcurementDocument::STATUS_RETURNED_BY_BAC_MEMBER);
            }
        });

        $query->when($request->filled('date_from'), function (Builder $builder) use ($request) {
            $date = $request->date('date_from');
            $builder->where(function (Builder $nested) use ($date) {
                $nested->whereDate('bac_member_reviewed_at', '>=', $date)
                    ->orWhereHas('bacMemberReviews', fn (Builder $review) => $review->whereDate('completed_at', '>=', $date))
                    ->orWhereHas('routingHistories', fn (Builder $history) => $history->whereDate('action_at', '>=', $date));
            });
        });

        $query->when($request->filled('date_to'), function (Builder $builder) use ($request) {
            $date = $request->date('date_to');
            $builder->where(function (Builder $nested) use ($date) {
                $nested->whereDate('bac_member_reviewed_at', '<=', $date)
                    ->orWhereHas('bacMemberReviews', fn (Builder $review) => $review->whereDate('completed_at', '<=', $date))
                    ->orWhereHas('routingHistories', fn (Builder $history) => $history->whereDate('action_at', '<=', $date));
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
            'endorsed' => (clone $base)->whereIn('status', [
                ProcurementDocument::STATUS_ENDORSED_BY_BAC_MEMBER,
                ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW,
                'under_bac_chair_review',
                'bac_chair_reviewed',
            ])->count(),
            'returned' => (clone $base)->where('status', ProcurementDocument::STATUS_RETURNED_BY_BAC_MEMBER)->count(),
            'reviewedThisMonth' => (clone $base)->where(function (Builder $query) {
                $query->whereMonth('bac_member_reviewed_at', now()->month)
                    ->whereYear('bac_member_reviewed_at', now()->year)
                    ->orWhereHas('bacMemberReviews', function (Builder $review) {
                        $review->whereMonth('completed_at', now()->month)
                            ->whereYear('completed_at', now()->year);
                    });
            })->count(),
        ];
    }

    private function latestBacMemberReview(ProcurementDocument $document, User $user): ?BacMemberReview
    {
        return $document->bacMemberReviews
            ->where('reviewed_by_user_id', $user->id)
            ->sortByDesc(fn (BacMemberReview $review) => $review->completed_at ?? $review->created_at)
            ->first();
    }

    public function outcome(ProcurementDocument $document): string
    {
        return $document->status === ProcurementDocument::STATUS_RETURNED_BY_BAC_MEMBER
            ? 'Returned'
            : 'Endorsed to BAC Chair';
    }

    public function destination(ProcurementDocument $document): string
    {
        $history = $document->routingHistories
            ->first(fn ($item) => in_array($item->action, ['Returned by BAC Member', 'Routed to BAC Chair', 'Endorsed by BAC Member'], true));

        return $history?->toOffice?->name
            ?? $document->currentOffice?->name
            ?? $document->assignedTo?->office
            ?? 'N/A';
    }
}
