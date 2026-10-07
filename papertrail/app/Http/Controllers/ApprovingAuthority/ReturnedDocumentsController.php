<?php

namespace App\Http\Controllers\ApprovingAuthority;

use App\Http\Controllers\Controller;
use App\Models\AccountingReview;
use App\Models\BacChairReview;
use App\Models\BacMemberReview;
use App\Models\BacSecretariatReview;
use App\Models\BudgetReview;
use App\Models\DocumentApproval;
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
        AuditLogger::log('Head of the Procuring Entity', 'Returned Documents Page Viewed', 'Head of the Procuring Entity viewed returned documents.');

        $query = $this->baseReturnedQuery($request->user())
            ->with(['submittingOffice', 'currentOffice', 'assignedTo', 'submittedBy', 'approvedBy', 'documentApprovals.approvedBy', 'routingHistories.toOffice']);

        $this->applyFilters($query, $request);

        return view('approving-authority.returned.index', [
            'documents' => $query->latest('returned_by_approving_authority_at')->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary($request->user()),
            'filters' => $request->only(['search', 'document_type', 'fiscal_year', 'return_target', 'status', 'date_from', 'date_to']),
            'documentTypes' => ProcurementDocument::query()->select('document_type')->distinct()->orderBy('document_type')->pluck('document_type'),
            'fiscalYears' => ProcurementDocument::query()->select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
            'statuses' => [ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY],
            'returnTargets' => $this->returnTargets(),
        ]);
    }

    public function show(Request $request, ProcurementDocument $document): View|RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('Head of the Procuring Entity', 'Unauthorized Access Attempt', 'Head of the Procuring Entity attempted to view a returned document outside their scope.', $document, null, null, 'warning');

            return redirect()->route('approving-authority.returned.index')->with('error', 'You are not authorized to view this returned document.');
        }

        AuditLogger::log('Head of the Procuring Entity', 'Returned Document Detail Viewed', 'Head of the Procuring Entity viewed returned document detail.', $document);

        $document->load([
            'submittingOffice',
            'currentOffice',
            'submittedBy',
            'assignedTo',
            'approvedBy',
            'budgetReviews.reviewedBy',
            'accountingReviews.reviewedBy',
            'bacSecretariatReviews.reviewedBy',
            'bacMemberReviews.reviewedBy',
            'bacChairReviews.reviewedBy',
            'documentApprovals.approvedBy',
            'bacDeliberations.chair',
            'bacDeliberations.participants.user',
            'bacDeliberations.comments.user',
            'routingHistories.actionBy',
            'routingHistories.fromOffice',
            'routingHistories.toOffice',
            'purchaseRequestItems',
        ]);

        return view('approving-authority.returned.show', [
            'document' => $document,
            'returnTarget' => $this->returnTarget($document),
            'targetRole' => $this->targetRole($document),
            'latestBudgetReview' => $document->budgetReviews->sortByDesc(fn (BudgetReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestAccountingReview' => $document->accountingReviews->sortByDesc(fn (AccountingReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestBacSecretariatReview' => $document->bacSecretariatReviews->sortByDesc(fn (BacSecretariatReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestBacMemberReview' => $document->bacMemberReviews->sortByDesc(fn (BacMemberReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestBacChairReview' => $document->bacChairReviews->sortByDesc(fn (BacChairReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestApproval' => $document->documentApprovals->sortByDesc(fn (DocumentApproval $approval) => $approval->completed_at ?? $approval->created_at)->first(),
            'latestDeliberation' => $document->bacDeliberations->sortByDesc(fn ($deliberation) => $deliberation->completed_at ?? $deliberation->updated_at)->first(),
        ]);
    }

    private function baseReturnedQuery(User $user): Builder
    {
        return ProcurementDocument::query()
            ->whereNotIn('status', [
                ProcurementDocument::STATUS_PENDING_APPROVAL,
                ProcurementDocument::STATUS_UNDER_APPROVAL,
                ProcurementDocument::STATUS_APPROVED,
                ProcurementDocument::STATUS_APP_APPROVED,
                ProcurementDocument::STATUS_READY_FOR_PO,
                ProcurementDocument::STATUS_PO_APPROVED,
                ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW,
                ProcurementDocument::STATUS_UNDER_BAC_CHAIR_REVIEW,
                ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW,
                ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW,
                ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW,
            ])
            ->where(function (Builder $status) {
                $status->where('status', ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY)
                    ->orWhere('approval_status', DocumentApproval::STATUS_RETURNED)
                    ->orWhereIn('approval_decision', array_keys($this->returnTargets()));
            })
            ->where(function (Builder $owner) use ($user) {
                $owner->where('approved_by_user_id', $user->id)
                    ->orWhereHas('documentApprovals', function (Builder $approval) use ($user) {
                        $approval->where('approved_by_user_id', $user->id)
                            ->where('approval_status', DocumentApproval::STATUS_RETURNED);
                    })
                    ->orWhereHas('routingHistories', function (Builder $history) use ($user) {
                        $history->where('action_by_user_id', $user->id)
                            ->where(function (Builder $action) {
                                $action->where('action', 'like', '%Returned by Head of the Procuring Entity%')
                                    ->orWhere('action', 'like', '%Returned by Approving Authority%')
                                    ->orWhere('status_to', ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY);
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

        $query->when($request->filled('return_target'), fn (Builder $builder) => $builder->where('approval_decision', $request->input('return_target')));
        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('returned_by_approving_authority_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('returned_by_approving_authority_at', '<=', $request->date('date_to')));
    }

    private function canAccess(User $user, ProcurementDocument $document): bool
    {
        return $this->baseReturnedQuery($user)->whereKey($document->id)->exists();
    }

    private function summary(User $user): array
    {
        $base = $this->baseReturnedQuery($user);

        return [
            'total' => (clone $base)->count(),
            'bacChair' => (clone $base)->where('approval_decision', DocumentApproval::DECISION_RETURNED_TO_BAC_CHAIR)->count(),
            'bacSecretariat' => (clone $base)->where('approval_decision', DocumentApproval::DECISION_RETURNED_TO_BAC_SECRETARIAT)->count(),
            'thisMonth' => (clone $base)->whereMonth('returned_by_approving_authority_at', now()->month)->whereYear('returned_by_approving_authority_at', now()->year)->count(),
        ];
    }

    private function returnTargets(): array
    {
        return [
            DocumentApproval::DECISION_RETURNED_TO_BAC_CHAIR => 'BAC Chair',
            DocumentApproval::DECISION_RETURNED_TO_BAC_SECRETARIAT => 'BAC Secretariat',
            DocumentApproval::DECISION_RETURNED_TO_ACCOUNTING => 'Accounting Office',
            DocumentApproval::DECISION_RETURNED_TO_REQUESTING_OFFICE => 'Requesting Office',
        ];
    }

    private function returnTarget(ProcurementDocument $document): string
    {
        return $this->returnTargets()[$document->approval_decision] ?? 'Returned for Correction';
    }

    private function targetRole(ProcurementDocument $document): string
    {
        return match ($document->approval_decision) {
            DocumentApproval::DECISION_RETURNED_TO_BAC_CHAIR => 'BAC Chair',
            DocumentApproval::DECISION_RETURNED_TO_BAC_SECRETARIAT => 'BAC Secretariat',
            DocumentApproval::DECISION_RETURNED_TO_ACCOUNTING => 'Accounting Officer',
            DocumentApproval::DECISION_RETURNED_TO_REQUESTING_OFFICE => 'Requesting Office',
            default => 'Correction Target',
        };
    }
}
