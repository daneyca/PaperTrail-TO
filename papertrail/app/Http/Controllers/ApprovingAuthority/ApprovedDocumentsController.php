<?php

namespace App\Http\Controllers\ApprovingAuthority;

use App\Http\Controllers\Controller;
use App\Models\AccountingReview;
use App\Models\AnnualProcurementPlan;
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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ApprovedDocumentsController extends Controller
{
    public function index(Request $request): View
    {
        AuditLogger::log('Head of the Procuring Entity', 'Approved Documents Page Viewed', 'Head of the Procuring Entity viewed approved documents.');

        $query = $this->baseApprovedQuery($request->user())
            ->with(['submittingOffice', 'currentOffice', 'submittedBy', 'approvedBy', 'documentApprovals.approvedBy', 'routingHistories.toOffice']);

        $this->applyFilters($query, $request);

        $appQuery = $this->baseApprovedAppQuery($request->user());
        $this->applyAppFilters($appQuery, $request);

        $documents = $this->approvedRows($query->get(), $appQuery->get(), $request);

        return view('approving-authority.approved.index', [
            'documents' => $documents,
            'summary' => $this->summary($request->user()),
            'filters' => $request->only(['search', 'document_type', 'fiscal_year', 'outcome', 'status', 'date_from', 'date_to']),
            'documentTypes' => ProcurementDocument::query()->select('document_type')->distinct()->orderBy('document_type')->pluck('document_type')->push('APP')->unique()->sort()->values(),
            'fiscalYears' => ProcurementDocument::query()->select('fiscal_year')->distinct()->pluck('fiscal_year')->merge(AnnualProcurementPlan::query()->select('fiscal_year')->distinct()->pluck('fiscal_year'))->filter()->unique()->sortDesc()->values(),
            'statuses' => collect($this->approvedStatuses())->push(AnnualProcurementPlan::STATUS_APPROVED)->unique()->values(),
            'outcomes' => [
                'app_approved' => 'APP Approved',
                'ready_for_po' => 'Approved / Ready for Purchase Order',
                'po_approved' => 'Purchase Order Approved',
                'completed' => 'Completed',
                'approved' => 'Approved',
            ],
        ]);
    }

    public function show(Request $request, ProcurementDocument $document): View|RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('Head of the Procuring Entity', 'Unauthorized Access Attempt', 'Head of the Procuring Entity attempted to view an approved document outside their scope.', $document, null, null, 'warning');

            return redirect()->route('approving-authority.approved.index')->with('error', 'You are not authorized to view this approved document.');
        }

        AuditLogger::log('Head of the Procuring Entity', 'Approved Document Detail Viewed', 'Head of the Procuring Entity viewed approved document detail.', $document);

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
            'appConsolidation',
            'purchaseOrder',
        ]);

        return view('approving-authority.approved.show', [
            'document' => $document,
            'outcome' => $this->outcome($document),
            'destination' => $this->destination($document),
            'latestBudgetReview' => $document->budgetReviews->sortByDesc(fn (BudgetReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestAccountingReview' => $document->accountingReviews->sortByDesc(fn (AccountingReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestBacSecretariatReview' => $document->bacSecretariatReviews->sortByDesc(fn (BacSecretariatReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestBacMemberReview' => $document->bacMemberReviews->sortByDesc(fn (BacMemberReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestBacChairReview' => $document->bacChairReviews->sortByDesc(fn (BacChairReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestApproval' => $document->documentApprovals->sortByDesc(fn (DocumentApproval $approval) => $approval->completed_at ?? $approval->created_at)->first(),
            'latestDeliberation' => $document->bacDeliberations->sortByDesc(fn ($deliberation) => $deliberation->completed_at ?? $deliberation->updated_at)->first(),
        ]);
    }

    private function baseApprovedQuery(User $user): Builder
    {
        return ProcurementDocument::query()
            ->whereNotIn('status', [
                ProcurementDocument::STATUS_PENDING_APPROVAL,
                ProcurementDocument::STATUS_UNDER_APPROVAL,
                ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY,
                ProcurementDocument::STATUS_APPROVAL_DEFERRED,
                ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW,
                ProcurementDocument::STATUS_UNDER_BAC_CHAIR_REVIEW,
                ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW,
                ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW,
                ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW,
            ])
            ->where(function (Builder $status) {
                $status->whereIn('status', $this->approvedStatuses())
                    ->orWhere('approval_status', DocumentApproval::STATUS_APPROVED)
                    ->orWhere('approval_decision', DocumentApproval::DECISION_APPROVED);
            })
            ->where(function (Builder $owner) use ($user) {
                $owner->where('approved_by_user_id', $user->id)
                    ->orWhereHas('documentApprovals', function (Builder $approval) use ($user) {
                        $approval->where('approved_by_user_id', $user->id)
                            ->where('approval_status', DocumentApproval::STATUS_APPROVED);
                    })
                    ->orWhereHas('routingHistories', function (Builder $history) use ($user) {
                        $history->where('action_by_user_id', $user->id)
                            ->where(function (Builder $action) {
                                $action->where('action', 'like', '%Approved by Head of the Procuring Entity%')
                                    ->orWhere('action', 'like', '%Approved by Approving Authority%')
                                    ->orWhereIn('status_to', $this->approvedStatuses());
                            });
                    });
            });
    }

    private function baseApprovedAppQuery(User $user): Builder
    {
        return AnnualProcurementPlan::query()
            ->with(['office', 'submittedBy', 'approvedBy'])
            ->where('status', AnnualProcurementPlan::STATUS_APPROVED)
            ->where('approved_by_user_id', $user->id);
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
                'app_approved' => $builder->where('status', ProcurementDocument::STATUS_APP_APPROVED),
                'ready_for_po' => $builder->where('status', ProcurementDocument::STATUS_READY_FOR_PO),
                'po_approved' => $builder->where('status', ProcurementDocument::STATUS_PO_APPROVED),
                'completed' => $builder->whereIn('status', [ProcurementDocument::STATUS_PO_COMPLETED, 'completed']),
                'approved' => $builder->where('status', ProcurementDocument::STATUS_APPROVED),
                default => null,
            };
        });

        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('approved_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('approved_at', '<=', $request->date('date_to')));
    }

    private function applyAppFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();
            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('app_number', 'like', "%{$search}%")
                    ->orWhere('app_no', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('office_name', 'like', "%{$search}%")
                    ->orWhereHas('office', fn (Builder $office) => $office->where('name', 'like', "%{$search}%"));
            });
        });

        $query->when($request->filled('document_type') && ! in_array(strtolower((string) $request->input('document_type')), ['app', 'annual procurement plan'], true), fn (Builder $builder) => $builder->whereRaw('1 = 0'));
        $query->when($request->filled('fiscal_year'), fn (Builder $builder) => $builder->where('fiscal_year', $request->input('fiscal_year')));
        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', fn (Builder $builder) => $builder->where('status', $request->input('status')));
        $query->when($request->filled('outcome') && $request->input('outcome') !== 'app_approved', fn (Builder $builder) => $builder->whereRaw('1 = 0'));
        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('approved_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('approved_at', '<=', $request->date('date_to')));
    }

    private function approvedRows(Collection $documents, Collection $apps, Request $request): LengthAwarePaginator
    {
        $documentRows = collect($documents->map(function (ProcurementDocument $document) {
            return (object) [
                'tracking_number' => $document->tracking_number,
                'document_type' => $document->document_type,
                'title' => $document->title,
                'description' => $document->purpose ?? $document->description,
                'requesting_office' => $document->submittingOffice?->name ?? 'N/A',
                'total_amount' => (float) $document->total_amount,
                'outcome' => $this->outcome($document),
                'status' => $document->status,
                'approved_at' => $document->approved_at ?? $document->updated_at,
                'current_office' => $document->currentOffice?->name ?? 'N/A',
                'action_url' => route('approving-authority.approved.show', $document),
                'sort_date' => $document->approved_at ?? $document->updated_at,
            ];
        })->all());

        $appRows = collect($apps->map(function (AnnualProcurementPlan $app) {
            return (object) [
                'tracking_number' => $app->displayNumber(),
                'document_type' => 'APP',
                'title' => $app->title ?: 'Annual Procurement Plan CY ' . $app->fiscal_year,
                'description' => 'Annual Procurement Plan approved by the Head of the Procuring Entity.',
                'requesting_office' => $app->office?->name ?? $app->office_name ?? 'BAC Secretariat',
                'total_amount' => (float) $app->total_estimated_budget,
                'outcome' => 'APP Approved',
                'status' => $app->status,
                'approved_at' => $app->approved_at ?? $app->updated_at,
                'current_office' => 'BAC Secretariat',
                'action_url' => route('bac-secretariat.app.show', $app),
                'sort_date' => $app->approved_at ?? $app->updated_at,
            ];
        })->all());

        $rows = $documentRows
            ->merge($appRows)
            ->sortByDesc(fn ($row) => $row->sort_date?->timestamp ?? 0)
            ->values();

        return $this->paginateRows($rows, $request);
    }

    private function paginateRows(Collection $rows, Request $request, int $perPage = 10): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    private function canAccess(User $user, ProcurementDocument $document): bool
    {
        return $this->baseApprovedQuery($user)->whereKey($document->id)->exists();
    }

    private function summary(User $user): array
    {
        $base = $this->baseApprovedQuery($user);
        $appBase = $this->baseApprovedAppQuery($user);

        return [
            'total' => (clone $base)->count() + (clone $appBase)->count(),
            'appApproved' => (clone $base)->where('status', ProcurementDocument::STATUS_APP_APPROVED)->count() + (clone $appBase)->count(),
            'readyForPo' => (clone $base)->where('status', ProcurementDocument::STATUS_READY_FOR_PO)->count(),
            'thisMonth' => (clone $base)->whereMonth('approved_at', now()->month)->whereYear('approved_at', now()->year)->count()
                + (clone $appBase)->whereMonth('approved_at', now()->month)->whereYear('approved_at', now()->year)->count(),
        ];
    }

    private function approvedStatuses(): array
    {
        return [
            ProcurementDocument::STATUS_APPROVED,
            ProcurementDocument::STATUS_APP_APPROVED,
            ProcurementDocument::STATUS_READY_FOR_PO,
            ProcurementDocument::STATUS_PO_APPROVED,
            ProcurementDocument::STATUS_PO_COMPLETED,
            'completed',
        ];
    }

    private function outcome(ProcurementDocument $document): string
    {
        if (in_array($document->status, [ProcurementDocument::STATUS_PO_COMPLETED, 'completed'], true)) {
            return 'Completed';
        }

        return match (strtolower($document->document_type)) {
            'app' => 'APP Approved',
            'pr', 'purchase request' => 'Approved / Ready for Purchase Order',
            'po', 'purchase order' => 'Purchase Order Approved',
            default => 'Approved',
        };
    }

    private function destination(ProcurementDocument $document): string
    {
        return $document->routingHistories
            ->first(fn ($history) => in_array($history->action, ['Document Approved by Head of the Procuring Entity', 'Document Approved by Approving Authority', 'Routed back to BAC Secretariat for PO Preparation'], true))
            ?->toOffice?->name
            ?? $document->currentOffice?->name
            ?? 'N/A';
    }
}
