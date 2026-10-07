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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PendingApprovalController extends Controller
{
    public function index(Request $request): View
    {
        AuditLogger::log('Head of the Procuring Entity', 'Pending Approval Page Viewed', 'Head of the Procuring Entity viewed pending approvals.');

        $query = $this->baseApprovalQuery($request->user())
            ->with(['submittingOffice', 'currentOffice', 'submittedBy', 'bacChairReviews.reviewedBy', 'routingHistories.toOffice']);

        $this->applyFilters($query, $request);

        $appQuery = $this->baseAppApprovalQuery();
        $this->applyAppFilters($appQuery, $request);

        $documents = $this->approvalRows($query->get(), $appQuery->get(), $request);

        return view('approving-authority.pending.index', [
            'documents' => $documents,
            'summary' => $this->summary($request->user()),
            'filters' => $request->only(['search', 'document_type', 'fiscal_year', 'status', 'priority', 'date_from', 'date_to']),
            'documentTypes' => ProcurementDocument::query()->select('document_type')->distinct()->orderBy('document_type')->pluck('document_type')->push('APP')->unique()->sort()->values(),
            'fiscalYears' => ProcurementDocument::query()->select('fiscal_year')->distinct()->pluck('fiscal_year')->merge(AnnualProcurementPlan::query()->select('fiscal_year')->distinct()->pluck('fiscal_year'))->filter()->unique()->sortDesc()->values(),
            'statuses' => [ProcurementDocument::STATUS_PENDING_APPROVAL, ProcurementDocument::STATUS_UNDER_APPROVAL, AnnualProcurementPlan::STATUS_SUBMITTED, AnnualProcurementPlan::STATUS_CONSOLIDATED],
        ]);
    }

    public function show(Request $request, ProcurementDocument $document): View|RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('Head of the Procuring Entity', 'Unauthorized Access Attempt', 'Head of the Procuring Entity attempted to access an unassigned document.', $document, null, null, 'warning');

            return redirect()->route('approving-authority.pending.index')->with('error', 'You are not authorized to approve this document.');
        }

        AuditLogger::log('Head of the Procuring Entity', 'Pending Approval Detail Viewed', 'Head of the Procuring Entity viewed approval detail.', $document);

        $document->load([
            'submittingOffice',
            'currentOffice',
            'submittedBy',
            'assignedTo',
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
            'documentApprovals.approvedBy',
        ]);

        return view('approving-authority.pending.show', [
            'document' => $document,
            'latestBudgetReview' => $document->budgetReviews->sortByDesc(fn (BudgetReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestAccountingReview' => $document->accountingReviews->sortByDesc(fn (AccountingReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestBacSecretariatReview' => $document->bacSecretariatReviews->sortByDesc(fn (BacSecretariatReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestBacMemberReview' => $document->bacMemberReviews->sortByDesc(fn (BacMemberReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestBacChairReview' => $document->bacChairReviews->sortByDesc(fn (BacChairReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestDeliberation' => $document->bacDeliberations->sortByDesc(fn ($deliberation) => $deliberation->completed_at ?? $deliberation->updated_at)->first(),
            'returnTargets' => $this->returnTargets(),
        ]);
    }

    public function start(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document) || $document->status !== ProcurementDocument::STATUS_PENDING_APPROVAL) {
            AuditLogger::log('Head of the Procuring Entity', 'Unauthorized Access Attempt', 'Invalid approval review start attempt.', $document, null, null, 'warning');

            return back()->with('error', 'Only pending approval documents can be started.');
        }

        DB::transaction(function () use ($request, $document) {
            $oldStatus = $document->status;

            $document->update([
                'status' => ProcurementDocument::STATUS_UNDER_APPROVAL,
                'approval_status' => DocumentApproval::STATUS_UNDER_REVIEW,
                'approval_started_at' => now(),
                'stage' => ProcurementDocument::STAGE_APPROVING_AUTHORITY_REVIEW,
                'assigned_to_user_id' => $request->user()->id,
            ]);

            $this->approvalRecord($document, $request->user())->fill([
                'approval_status' => DocumentApproval::STATUS_UNDER_REVIEW,
                'started_at' => now(),
            ])->save();

            $this->recordRouting($document, $request->user(), 'Approval Review Started', $oldStatus, $document->status, 'Head of the Procuring Entity review started.');

            AuditLogger::log('Head of the Procuring Entity', 'Approval Review Started', 'Head of the Procuring Entity started final approval review.', $document, ['status' => $oldStatus], ['status' => $document->status]);
        });

        return back()->with('status', 'Approval review started.');
    }

    public function approve(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document) || $document->status !== ProcurementDocument::STATUS_UNDER_APPROVAL) {
            AuditLogger::log('Head of the Procuring Entity', 'Unauthorized Access Attempt', 'Invalid approval attempt.', $document, null, null, 'warning');

            return back()->with('error', 'Only documents under approval review can be approved.');
        }

        $validated = $request->validate([
            'approval_confirmation' => ['accepted'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $target = $this->approvalTarget($document);

        DB::transaction(function () use ($request, $document, $validated, $target) {
            $oldStatus = $document->status;
            $fromOffice = $document->current_office_id;
            $remarks = $validated['remarks'] ?? null;

            $document->update([
                'status' => $target['status'],
                'stage' => $target['stage'],
                'current_office_id' => $target['office']?->id ?? $document->current_office_id,
                'assigned_to_user_id' => $target['user']?->id,
                'approval_status' => DocumentApproval::STATUS_APPROVED,
                'approval_decision' => DocumentApproval::DECISION_APPROVED,
                'approved_by_user_id' => $request->user()->id,
                'approved_at' => now(),
                'approval_remarks' => $remarks,
            ]);

            $this->approvalRecord($document, $request->user())->fill([
                'approval_status' => DocumentApproval::STATUS_APPROVED,
                'decision' => DocumentApproval::DECISION_APPROVED,
                'remarks' => $remarks,
                'started_at' => $document->approval_started_at,
                'completed_at' => now(),
            ])->save();

            $this->recordRouting($document, $request->user(), 'Document Approved by Head of the Procuring Entity', $oldStatus, $document->status, $remarks ?: 'Document approved.', $fromOffice, $document->current_office_id);

            if ($document->status === ProcurementDocument::STATUS_READY_FOR_PO) {
                $this->recordRouting($document, $request->user(), 'Routed back to BAC Secretariat for PO Preparation', $oldStatus, $document->status, 'Approved PR is ready for purchase order preparation.', $fromOffice, $document->current_office_id);
            }

            SystemNotificationService::notify($target['user'], 'Document Approved', "Document {$document->tracking_number} has been approved and is ready for the next procurement action.", SystemNotification::TYPE_SUCCESS, 'Head of the Procuring Entity', $document, $this->targetActionUrl($target['user'], $document));
            SystemNotificationService::notify($document->submittedBy, 'Document Approved', "Document {$document->tracking_number} has been approved by the Head of the Procuring Entity.", SystemNotification::TYPE_SUCCESS, 'Head of the Procuring Entity', $document);
            SystemNotificationService::sendToRole(User::ROLE_BAC_CHAIR, 'Document Approved by Head of the Procuring Entity', "Document {$document->tracking_number} has been approved.", SystemNotification::TYPE_SUCCESS, 'Head of the Procuring Entity', null, $document);

            AuditLogger::log('Head of the Procuring Entity', 'Document Approved by Head of the Procuring Entity', 'Head of the Procuring Entity approved a procurement document.', $document, ['status' => $oldStatus], ['status' => $document->status]);
        });

        return redirect()->route('approving-authority.pending.index')->with('status', 'Document approved successfully.');
    }

    public function return(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document) || !in_array($document->status, [ProcurementDocument::STATUS_PENDING_APPROVAL, ProcurementDocument::STATUS_UNDER_APPROVAL], true)) {
            AuditLogger::log('Head of the Procuring Entity', 'Unauthorized Access Attempt', 'Invalid approval return attempt.', $document, null, null, 'warning');

            return back()->with('error', 'This document cannot be returned from approval review.');
        }

        $validated = $request->validate([
            'return_target' => ['required', Rule::in(array_keys($this->returnTargets()))],
            'comments' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $target = $this->resolveReturnTarget($document, $validated['return_target']);

        if (!$target['office'] && !$target['user']) {
            return back()->with('error', $target['missingMessage'])->withInput();
        }

        DB::transaction(function () use ($request, $document, $validated, $target) {
            $oldStatus = $document->status;
            $fromOffice = $document->current_office_id;
            $toOffice = $target['office']?->id ?? $target['user']?->office_id;

            $document->update([
                'status' => ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY,
                'approval_status' => DocumentApproval::STATUS_RETURNED,
                'approval_decision' => $target['decision'],
                'approval_remarks' => $validated['comments'],
                'returned_by_approving_authority_at' => now(),
                'stage' => $target['stage'],
                'current_office_id' => $toOffice,
                'assigned_to_user_id' => $target['user']?->id,
            ]);

            $this->approvalRecord($document, $request->user())->fill([
                'approval_status' => DocumentApproval::STATUS_RETURNED,
                'decision' => $target['decision'],
                'remarks' => $validated['comments'],
                'started_at' => $document->approval_started_at,
                'completed_at' => now(),
            ])->save();

            $this->recordRouting($document, $request->user(), 'Document Returned by Head of the Procuring Entity', $oldStatus, $document->status, $validated['comments'], $fromOffice, $toOffice);

            SystemNotificationService::notify($target['user'], 'Document Returned by Head of the Procuring Entity', "Document {$document->tracking_number} was returned for correction or clarification.", SystemNotification::TYPE_WARNING, 'Head of the Procuring Entity', $document, $this->targetActionUrl($target['user'], $document));
            SystemNotificationService::notify($document->submittedBy, 'Document Returned by Head of the Procuring Entity', "Document {$document->tracking_number} was returned for correction or clarification.", SystemNotification::TYPE_WARNING, 'Head of the Procuring Entity', $document);

            AuditLogger::log('Head of the Procuring Entity', 'Document Returned by Head of the Procuring Entity', 'Head of the Procuring Entity returned a procurement document.', $document, ['status' => $oldStatus], ['status' => $document->status, 'return_target' => $validated['return_target']], 'warning');
        });

        return redirect()->route('approving-authority.pending.index')->with('status', 'Document returned successfully.');
    }

    private function baseApprovalQuery(User $user): Builder
    {
        return ProcurementDocument::query()->assignedToApprovingAuthority($user);
    }

    private function baseAppApprovalQuery(): Builder
    {
        return AnnualProcurementPlan::query()
            ->with(['office', 'submittedBy', 'approvedBy'])
            ->whereIn('status', [AnnualProcurementPlan::STATUS_SUBMITTED, AnnualProcurementPlan::STATUS_CONSOLIDATED]);
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

        foreach (['document_type', 'fiscal_year', 'status', 'priority'] as $field) {
            $query->when($request->filled($field), fn (Builder $builder) => $builder->where($field, $request->input($field)));
        }

        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('updated_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('updated_at', '<=', $request->date('date_to')));
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
        $query->when($request->filled('priority'), fn (Builder $builder) => $builder->whereRaw('1 = 0'));
        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('submitted_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('submitted_at', '<=', $request->date('date_to')));
    }

    private function approvalRows(Collection $documents, Collection $apps, Request $request): LengthAwarePaginator
    {
        $documentRows = collect($documents->map(function (ProcurementDocument $document) {
            $forwardedDate = $document->bac_chair_confirmed_at ?? $document->bac_chair_reviewed_at ?? $document->updated_at;
            $latestBacChairReview = $document->bacChairReviews->sortByDesc(fn (BacChairReview $review) => $review->completed_at ?? $review->created_at)->first();

            return (object) [
                'tracking_number' => $document->tracking_number,
                'document_type' => $document->document_type,
                'title' => $document->title,
                'description' => $document->purpose ?? $document->description,
                'requesting_office' => $document->submittingOffice?->name ?? 'N/A',
                'total_amount' => (float) $document->total_amount,
                'decision' => $document->bac_chair_decision ?? $latestBacChairReview?->decision,
                'status' => $document->status,
                'stage' => $document->stage ?? 'N/A',
                'forwarded_date' => $forwardedDate,
                'pending_days' => $forwardedDate ? $forwardedDate->diffInDays(now()) : 0,
                'action_url' => route('approving-authority.pending.show', $document),
                'sort_date' => $document->updated_at,
            ];
        })->all());

        $appRows = collect($apps->map(function (AnnualProcurementPlan $app) {
            $forwardedDate = $app->submitted_at ?? $app->updated_at;

            return (object) [
                'tracking_number' => $app->displayNumber(),
                'document_type' => 'APP',
                'title' => $app->title ?: 'Annual Procurement Plan CY ' . $app->fiscal_year,
                'description' => 'Annual Procurement Plan submitted for approval.',
                'requesting_office' => $app->office?->name ?? $app->office_name ?? 'BAC Secretariat',
                'total_amount' => (float) $app->total_estimated_budget,
                'decision' => 'For APP Approval',
                'status' => $app->status,
                'stage' => 'APP Approval',
                'forwarded_date' => $forwardedDate,
                'pending_days' => $forwardedDate ? $forwardedDate->diffInDays(now()) : 0,
                'action_url' => route('bac-secretariat.app.show', $app),
                'sort_date' => $app->updated_at,
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
        return $this->baseApprovalQuery($user)->whereKey($document->id)->exists();
    }

    private function summary(User $user): array
    {
        return [
            'pending' => ProcurementDocument::query()->assignedToApprovingAuthority($user)->where('status', ProcurementDocument::STATUS_PENDING_APPROVAL)->count()
                + AnnualProcurementPlan::query()->whereIn('status', [AnnualProcurementPlan::STATUS_SUBMITTED, AnnualProcurementPlan::STATUS_CONSOLIDATED])->count(),
            'underReview' => ProcurementDocument::query()->assignedToApprovingAuthority($user)->where('status', ProcurementDocument::STATUS_UNDER_APPROVAL)->count(),
            'approved' => ProcurementDocument::query()->approvedByApprovingAuthority($user)->count()
                + AnnualProcurementPlan::query()->where('status', AnnualProcurementPlan::STATUS_APPROVED)->where('approved_by_user_id', $user->id)->count(),
            'returned' => ProcurementDocument::query()->where('status', ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY)->whereHas('documentApprovals', fn (Builder $approval) => $approval->where('approved_by_user_id', $user->id))->count(),
        ];
    }

    private function approvalRecord(ProcurementDocument $document, User $user): DocumentApproval
    {
        return DocumentApproval::firstOrNew([
            'procurement_document_id' => $document->id,
            'approved_by_user_id' => $user->id,
        ]);
    }

    private function approvalTarget(ProcurementDocument $document): array
    {
        $bacSecretariat = $this->activeUserByRoleOrUserId(User::ROLE_BAC_SECRETARIAT, 'BACSEC-001');
        $bacOffice = $this->officeByCodeOrNames('BACSEC', ['BAC Secretariat']);
        $type = strtolower($document->document_type);

        if (in_array($type, ['pr', 'purchase request'], true)) {
            return ['status' => ProcurementDocument::STATUS_READY_FOR_PO, 'stage' => ProcurementDocument::STAGE_READY_FOR_PURCHASE_ORDER, 'office' => $bacOffice, 'user' => $bacSecretariat];
        }

        if ($type === 'app') {
            return ['status' => ProcurementDocument::STATUS_APP_APPROVED, 'stage' => ProcurementDocument::STAGE_APP_APPROVED, 'office' => $bacOffice, 'user' => $bacSecretariat];
        }

        if (in_array($type, ['po', 'purchase order'], true)) {
            return ['status' => ProcurementDocument::STATUS_PO_APPROVED, 'stage' => ProcurementDocument::STAGE_PURCHASE_ORDER_APPROVED, 'office' => $bacOffice, 'user' => $bacSecretariat];
        }

        return ['status' => ProcurementDocument::STATUS_APPROVED, 'stage' => ProcurementDocument::STAGE_APPROVED, 'office' => $document->currentOffice, 'user' => null];
    }

    private function resolveReturnTarget(ProcurementDocument $document, string $target): array
    {
        return match ($target) {
            'bac_secretariat' => ['office' => $this->officeByCodeOrNames('BACSEC', ['BAC Secretariat']), 'user' => $this->activeUserByRoleOrUserId(User::ROLE_BAC_SECRETARIAT, 'BACSEC-001'), 'stage' => ProcurementDocument::STAGE_RETURNED_TO_BAC_SECRETARIAT, 'decision' => DocumentApproval::DECISION_RETURNED_TO_BAC_SECRETARIAT, 'missingMessage' => 'BAC Secretariat routing target is not configured.'],
            'accounting' => ['office' => $this->officeByCodeOrNames('MACCO', ['Accounting Office']), 'user' => $this->activeUserByRoleOrUserId(User::ROLE_ACCOUNTING), 'stage' => ProcurementDocument::STAGE_RETURNED_TO_ACCOUNTING_OFFICE, 'decision' => DocumentApproval::DECISION_RETURNED_TO_ACCOUNTING, 'missingMessage' => 'Accounting Office routing target is not configured.'],
            'requesting_office' => ['office' => $document->submittingOffice, 'user' => $document->submittedBy, 'stage' => ProcurementDocument::STAGE_RETURNED_TO_REQUESTING_OFFICE, 'decision' => DocumentApproval::DECISION_RETURNED_TO_REQUESTING_OFFICE, 'missingMessage' => 'Requesting office routing target is not configured.'],
            default => ['office' => $this->officeByCodeOrNames('BAC', ['Bids and Awards Committee']), 'user' => $this->activeUserByRoleOrUserId(User::ROLE_BAC_CHAIR, 'BACCHAIR-001'), 'stage' => 'Returned to BAC Chair', 'decision' => DocumentApproval::DECISION_RETURNED_TO_BAC_CHAIR, 'missingMessage' => 'BAC Chair routing target is not configured.'],
        };
    }

    private function returnTargets(): array
    {
        return ['bac_chair' => 'Return to BAC Chair', 'bac_secretariat' => 'Return to BAC Secretariat', 'accounting' => 'Return to Accounting Office', 'requesting_office' => 'Return to Requesting Office'];
    }

    private function recordRouting(ProcurementDocument $document, User $user, string $action, ?string $fromStatus, string $toStatus, ?string $comments = null, ?int $fromOfficeId = null, ?int $toOfficeId = null): void
    {
        DocumentRoutingHistory::create(['procurement_document_id' => $document->id, 'action_by_user_id' => $user->id, 'from_office_id' => $fromOfficeId ?? $document->getOriginal('current_office_id'), 'to_office_id' => $toOfficeId ?? $document->current_office_id, 'action' => $action, 'status_from' => $fromStatus, 'status_to' => $toStatus, 'comments' => $comments, 'action_at' => now()]);
    }

    private function targetActionUrl(?User $user, ProcurementDocument $document): ?string
    {
        if (!$user) return null;
        $route = match ($user->roleSlug()) {
            'bac-secretariat' => 'bac-secretariat.routing.show',
            'accounting' => 'accounting.returned.show',
            'bac-chair' => 'bac-chair.reviewed.show',
            default => null,
        };

        return $route && Route::has($route) ? route($route, $document) : null;
    }

    private function officeByCodeOrNames(string $code, array $names): ?Office
    {
        return Office::where('code', $code)->orWhereIn('name', $names)->first();
    }

    private function activeUserByRoleOrUserId(string $role, ?string $userId = null): ?User
    {
        return User::where('status', User::STATUS_ACTIVE)->where(function (Builder $query) use ($role, $userId) {
            $query->where('role', $role);
            if ($userId) $query->orWhere('user_id', $userId);
        })->first();
    }
}
