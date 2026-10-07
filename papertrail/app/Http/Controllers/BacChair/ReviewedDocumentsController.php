<?php

namespace App\Http\Controllers\BacChair;

use App\Http\Controllers\Controller;
use App\Models\AccountingReview;
use App\Models\BacChairReview;
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
        AuditLogger::log('BAC Chair Reviewed Documents', 'BAC Chair Reviewed Documents Page Viewed', 'BAC Chair viewed reviewed documents.');

        $query = $this->baseReviewedQuery($request->user())
            ->with(['submittingOffice', 'currentOffice', 'submittedBy', 'assignedTo', 'bacChairReviews.reviewedBy', 'routingHistories.toOffice']);

        $this->applyFilters($query, $request);

        $documents = $query->latest('updated_at')->paginate(10)->withQueryString();

        return view('bac-chair.reviewed.index', [
            'documents' => $documents,
            'summary' => $this->summary($request->user()),
            'filters' => $request->only(['search', 'document_type', 'fiscal_year', 'outcome', 'status', 'date_from', 'date_to']),
            'documentTypes' => ProcurementDocument::query()->select('document_type')->distinct()->orderBy('document_type')->pluck('document_type'),
            'fiscalYears' => ProcurementDocument::query()->select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
            'statuses' => [
                ProcurementDocument::STATUS_PENDING_APPROVAL,
                ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR,
                ProcurementDocument::STATUS_CONFIRMED_BY_BAC_CHAIR,
                'approved',
            ],
            'outcomes' => [
                'forwarded' => 'Forwarded',
                'returned' => 'Returned',
                'approved' => 'Approved',
            ],
        ]);
    }

    public function show(Request $request, ProcurementDocument $document): View|RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('BAC Chair Reviewed Documents', 'Unauthorized Access Attempt', 'BAC Chair attempted to view reviewed document outside their scope.', $document, null, null, 'warning');

            return redirect()
                ->route('bac-chair.reviewed.index')
                ->with('error', 'You are not authorized to view this reviewed document.');
        }

        AuditLogger::log('BAC Chair Reviewed Documents', 'BAC Chair Reviewed Document Detail Viewed', 'BAC Chair viewed reviewed document detail.', $document);

        $document->load([
            'submittingOffice',
            'currentOffice',
            'submittedBy',
            'assignedTo',
            'budgetReviewedBy',
            'accountingReviewedBy',
            'bacSecretariatReceivedBy',
            'bacMemberReviewedBy',
            'bacChairReviewedBy',
            'budgetReviews.reviewedBy',
            'accountingReviews.reviewedBy',
            'bacSecretariatReviews.reviewedBy',
            'bacMemberReviews.reviewedBy',
            'bacChairReviews.reviewedBy',
            'bacDeliberations.chair',
            'bacDeliberations.participants.user',
            'bacDeliberations.comments.user',
            'routingHistories.actionBy',
            'routingHistories.fromOffice',
            'routingHistories.toOffice',
            'purchaseRequestItems',
        ]);

        return view('bac-chair.reviewed.show', [
            'document' => $document,
            'outcome' => $this->outcome($document),
            'destination' => $this->destination($document),
            'latestBudgetReview' => $document->budgetReviews->sortByDesc(fn (BudgetReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestAccountingReview' => $document->accountingReviews->sortByDesc(fn (AccountingReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestBacSecretariatReview' => $document->bacSecretariatReviews->sortByDesc(fn (BacSecretariatReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestBacMemberReview' => $document->bacMemberReviews->sortByDesc(fn (BacMemberReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestBacChairReview' => $document->bacChairReviews->sortByDesc(fn (BacChairReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestDeliberation' => $document->bacDeliberations->sortByDesc(fn ($deliberation) => $deliberation->completed_at ?? $deliberation->updated_at)->first(),
        ]);
    }

    private function baseReviewedQuery(User $user): Builder
    {
        return ProcurementDocument::query()
            ->whereNotIn('status', [
                ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW,
                ProcurementDocument::STATUS_UNDER_BAC_CHAIR_REVIEW,
                ProcurementDocument::STATUS_READY_FOR_BAC_CHAIR_CONFIRMATION,
                ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW,
                ProcurementDocument::STATUS_UNDER_BAC_MEMBER_REVIEW,
                ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW,
                ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW,
                ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW,
            ])
            ->where(function (Builder $status) {
                $status->whereIn('status', [
                    ProcurementDocument::STATUS_CONFIRMED_BY_BAC_CHAIR,
                    ProcurementDocument::STATUS_PENDING_APPROVAL,
                    ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR,
                    'approved',
                ])
                    ->orWhereIn('bac_chair_status', [BacChairReview::STATUS_CONFIRMED, BacChairReview::STATUS_RETURNED])
                    ->orWhereIn('bac_chair_confirmation_status', ['confirmed', 'returned']);
            })
            ->where(function (Builder $owner) use ($user) {
                $owner->where('bac_chair_reviewed_by_user_id', $user->id)
                    ->orWhereHas('bacChairReviews', fn (Builder $review) => $review->where('reviewed_by_user_id', $user->id))
                    ->orWhereHas('routingHistories', function (Builder $history) use ($user) {
                        $history->where('action_by_user_id', $user->id)
                            ->where(function (Builder $action) {
                                $action->where('action', 'like', '%BAC Chair%')
                                    ->orWhereIn('status_to', [
                                        ProcurementDocument::STATUS_CONFIRMED_BY_BAC_CHAIR,
                                        ProcurementDocument::STATUS_PENDING_APPROVAL,
                                        ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR,
                                    ]);
                            });
                    });
            });
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
            match ($request->input('outcome')) {
                'forwarded' => $builder->where('status', ProcurementDocument::STATUS_PENDING_APPROVAL),
                'returned' => $builder->where('status', ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR),
                'approved' => $builder->where('status', 'approved'),
                default => null,
            };
        });

        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('bac_chair_reviewed_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('bac_chair_reviewed_at', '<=', $request->date('date_to')));
    }

    private function canAccess(User $user, ProcurementDocument $document): bool
    {
        return $this->baseReviewedQuery($user)->whereKey($document->id)->exists();
    }

    private function summary(User $user): array
    {
        $base = $this->baseReviewedQuery($user);

        return [
            'total' => (clone $base)->count(),
            'forwarded' => (clone $base)->where('status', ProcurementDocument::STATUS_PENDING_APPROVAL)->count(),
            'returned' => (clone $base)->where('status', ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR)->count(),
            'thisMonth' => (clone $base)->whereMonth('bac_chair_reviewed_at', now()->month)->whereYear('bac_chair_reviewed_at', now()->year)->count(),
        ];
    }

    public function outcome(ProcurementDocument $document): string
    {
        if ($document->status === 'approved') {
            return 'Approved by Head of the Procuring Entity';
        }

        if ($document->status === ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR || $document->bac_chair_status === BacChairReview::STATUS_RETURNED) {
            return match ($document->bac_chair_decision) {
                BacChairReview::DECISION_RETURN_TO_BAC_MEMBER => 'Returned to BAC Member',
                BacChairReview::DECISION_RETURN_TO_ACCOUNTING => 'Returned to Accounting Office',
                BacChairReview::DECISION_RETURN_TO_REQUESTING_OFFICE => 'Returned to Requesting Office',
                default => 'Returned to BAC Secretariat',
            };
        }

        return 'Forwarded to Head of the Procuring Entity';
    }

    public function destination(ProcurementDocument $document): string
    {
        return $document->routingHistories
            ->first(fn ($history) => in_array($history->action, ['Routed to Head of the Procuring Entity', 'Routed to Approving Authority', 'Returned by BAC Chair'], true))
            ?->toOffice?->name
            ?? $document->currentOffice?->name
            ?? 'N/A';
    }
}
