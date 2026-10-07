<?php

namespace App\Http\Controllers\BacChair;

use App\Http\Controllers\Controller;
use App\Models\AccountingReview;
use App\Models\BacChairReview;
use App\Models\BacMemberReview;
use App\Models\BacSecretariatReview;
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
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DocumentConfirmationController extends Controller
{
    public function index(Request $request): View
    {
        AuditLogger::log('BAC Chair Confirmation', 'BAC Chair Documents for Confirmation Page Viewed', 'BAC Chair viewed documents for confirmation.');

        $query = $this->baseConfirmationQuery($request->user())
            ->with(['submittingOffice', 'currentOffice', 'submittedBy', 'bacMemberReviews.reviewedBy', 'bacDeliberations']);

        $this->applyFilters($query, $request);

        return view('bac-chair.confirmation.index', [
            'documents' => $query->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary($request->user()),
            'filters' => $request->only(['search', 'document_type', 'fiscal_year', 'confirmation_status', 'status', 'date_from', 'date_to']),
            'documentTypes' => ProcurementDocument::query()->select('document_type')->distinct()->orderBy('document_type')->pluck('document_type'),
            'fiscalYears' => ProcurementDocument::query()->select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
            'statuses' => [
                ProcurementDocument::STATUS_UNDER_BAC_CHAIR_REVIEW,
                ProcurementDocument::STATUS_READY_FOR_BAC_CHAIR_CONFIRMATION,
            ],
            'confirmationStatuses' => ['pending_confirmation', 'deferred'],
        ]);
    }

    public function show(Request $request, ProcurementDocument $document): View|RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('BAC Chair Confirmation', 'Unauthorized Access Attempt', 'BAC Chair attempted to access a document outside the confirmation queue.', $document, null, null, 'warning');

            return redirect()
                ->route('bac-chair.confirmation.index')
                ->with('error', 'You are not authorized to confirm this document.');
        }

        AuditLogger::log('BAC Chair Confirmation', 'BAC Chair Confirmation Detail Viewed', 'BAC Chair viewed confirmation document detail.', $document);

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

        return view('bac-chair.confirmation.show', [
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

    public function confirm(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('BAC Chair Confirmation', 'Unauthorized Access Attempt', 'Invalid BAC Chair confirmation attempt.', $document, null, null, 'warning');

            return back()->with('error', 'This document cannot be confirmed from this queue.');
        }

        $validated = $request->validate([
            'confirm_decision' => ['accepted'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $approver = $this->activeUserByRoleOrUserId(User::ROLE_APPROVING_AUTHORITY, 'HOPE-001');
        $approverOffice = $this->officeByCodeOrNames('OMM', ['Office of the Municipal Mayor', 'Mayor\'s Office', 'Head of the Procuring Entity', 'Approving Authority']);

        if (!$approver && !$approverOffice) {
            return back()->with('error', 'Head of the Procuring Entity routing target is not configured.')->withInput();
        }

        DB::transaction(function () use ($request, $document, $validated, $approver, $approverOffice) {
            $oldStatus = $document->status;
            $fromOffice = $document->current_office_id;
            $toOffice = $approverOffice?->id ?? $approver?->office_id;
            $remarks = $validated['remarks'] ?? null;

            $document->update([
                'status' => ProcurementDocument::STATUS_PENDING_APPROVAL,
                'bac_chair_status' => BacChairReview::STATUS_CONFIRMED,
                'bac_chair_confirmation_status' => 'confirmed',
                'bac_chair_decision' => BacChairReview::DECISION_CONFIRM_FOR_APPROVING_AUTHORITY,
                'stage' => ProcurementDocument::STAGE_APPROVING_AUTHORITY_REVIEW,
                'current_office_id' => $toOffice,
                'assigned_to_user_id' => $approver?->id,
                'bac_chair_reviewed_by_user_id' => $request->user()->id,
                'bac_chair_reviewed_at' => now(),
                'bac_chair_confirmed_at' => now(),
                'bac_chair_remarks' => $remarks,
                'bac_chair_confirmation_remarks' => $remarks,
                'route_destination_role' => User::ROLE_APPROVING_AUTHORITY,
                'route_destination_office_id' => $toOffice,
                'route_remarks' => $remarks,
            ]);

            $this->reviewRecord($document, $request->user())->fill([
                'review_status' => BacChairReview::STATUS_CONFIRMED,
                'decision' => BacChairReview::DECISION_CONFIRM_FOR_APPROVING_AUTHORITY,
                'remarks' => $remarks,
                'started_at' => $document->bac_chair_review_started_at,
                'completed_at' => now(),
            ])->save();

            $this->recordRouting($document, $request->user(), 'BAC Chair Confirmed Review', $oldStatus, ProcurementDocument::STATUS_CONFIRMED_BY_BAC_CHAIR, $remarks ?: 'BAC Chair confirmed BAC-level review.', $fromOffice, $toOffice);
            $this->recordRouting($document, $request->user(), 'Routed to Head of the Procuring Entity', ProcurementDocument::STATUS_CONFIRMED_BY_BAC_CHAIR, $document->status, $remarks ?: 'Document routed to Head of the Procuring Entity.', $fromOffice, $toOffice);

            SystemNotificationService::notify(
                $approver,
                'Document Forwarded for Final Approval',
                "Document {$document->tracking_number} has been confirmed by the BAC Chair and forwarded for approval.",
                SystemNotification::TYPE_SUCCESS,
                'BAC Chair Confirmation',
                $document,
                Route::has('approving-authority.pending.show') ? route('approving-authority.pending.show', $document) : null,
            );

            AuditLogger::log('BAC Chair Confirmation', 'BAC Chair Confirmed Document', 'BAC Chair confirmed a procurement document.', $document, ['status' => $oldStatus], ['status' => $document->status]);
        });

        return redirect()
            ->route('bac-chair.confirmation.index')
            ->with('status', 'Document confirmed and forwarded to Head of the Procuring Entity.');
    }

    public function return(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('BAC Chair Confirmation', 'Unauthorized Access Attempt', 'Invalid BAC Chair confirmation return attempt.', $document, null, null, 'warning');

            return back()->with('error', 'This document cannot be returned from this queue.');
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
                'status' => ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR,
                'bac_chair_status' => BacChairReview::STATUS_RETURNED,
                'bac_chair_confirmation_status' => 'returned',
                'bac_chair_decision' => $target['decision'],
                'stage' => $target['stage'],
                'current_office_id' => $toOffice,
                'assigned_to_user_id' => $target['user']?->id,
                'bac_chair_reviewed_by_user_id' => $request->user()->id,
                'bac_chair_reviewed_at' => now(),
                'bac_chair_remarks' => $validated['comments'],
                'bac_chair_confirmation_remarks' => $validated['comments'],
                'remarks' => $validated['comments'],
            ]);

            $this->reviewRecord($document, $request->user())->fill([
                'review_status' => BacChairReview::STATUS_RETURNED,
                'decision' => $target['decision'],
                'remarks' => $validated['comments'],
                'started_at' => $document->bac_chair_review_started_at,
                'completed_at' => now(),
            ])->save();

            $this->recordRouting($document, $request->user(), 'Returned by BAC Chair', $oldStatus, $document->status, $validated['comments'], $fromOffice, $toOffice);

            SystemNotificationService::notify(
                $target['user'],
                'Document Returned by BAC Chair',
                "Document {$document->tracking_number} was returned for correction or clarification.",
                SystemNotification::TYPE_WARNING,
                'BAC Chair Review',
                $document,
                $this->targetActionUrl($target['user'], $document),
            );

            AuditLogger::log('BAC Chair Confirmation', 'BAC Chair Returned Document', 'BAC Chair returned a procurement document from confirmation.', $document, ['status' => $oldStatus], ['status' => $document->status, 'return_target' => $validated['return_target']], 'warning');
        });

        return redirect()
            ->route('bac-chair.confirmation.index')
            ->with('status', 'Document returned successfully.');
    }

    private function baseConfirmationQuery(User $user): Builder
    {
        return ProcurementDocument::query()->forBacChairConfirmation($user);
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

        $query->when($request->filled('confirmation_status'), fn (Builder $builder) => $builder->where('bac_chair_confirmation_status', $request->input('confirmation_status')));
        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('bac_chair_review_started_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('bac_chair_review_started_at', '<=', $request->date('date_to')));
    }

    private function canAccess(User $user, ProcurementDocument $document): bool
    {
        return $this->baseConfirmationQuery($user)->whereKey($document->id)->exists();
    }

    private function summary(User $user): array
    {
        $base = $this->baseConfirmationQuery($user);

        return [
            'forConfirmation' => (clone $base)->count(),
            'confirmed' => ProcurementDocument::query()->reviewedByBacChair($user)->where('bac_chair_confirmation_status', 'confirmed')->count(),
            'returned' => ProcurementDocument::query()->reviewedByBacChair($user)->where('bac_chair_confirmation_status', 'returned')->count(),
            'pendingApproval' => ProcurementDocument::query()->reviewedByBacChair($user)->where('status', ProcurementDocument::STATUS_PENDING_APPROVAL)->count(),
        ];
    }

    private function reviewRecord(ProcurementDocument $document, User $user): BacChairReview
    {
        return BacChairReview::firstOrNew([
            'procurement_document_id' => $document->id,
            'reviewed_by_user_id' => $user->id,
        ]);
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

    private function resolveReturnTarget(ProcurementDocument $document, string $target): array
    {
        return match ($target) {
            'bac_member' => [
                'office' => $this->officeByCodeOrNames('BAC', ['Bids and Awards Committee']),
                'user' => $this->activeUserByRoleOrUserId(User::ROLE_BAC_MEMBER),
                'stage' => ProcurementDocument::STAGE_RETURNED_TO_BAC_MEMBER,
                'decision' => BacChairReview::DECISION_RETURN_TO_BAC_MEMBER,
                'missingMessage' => 'BAC Member routing target is not configured.',
            ],
            'accounting' => [
                'office' => $this->officeByCodeOrNames('MACCO', ['Accounting Office']),
                'user' => $this->activeUserByRoleOrUserId(User::ROLE_ACCOUNTING),
                'stage' => ProcurementDocument::STAGE_RETURNED_TO_ACCOUNTING_OFFICE,
                'decision' => BacChairReview::DECISION_RETURN_TO_ACCOUNTING,
                'missingMessage' => 'Accounting Office routing target is not configured.',
            ],
            'requesting_office' => [
                'office' => $document->submittingOffice,
                'user' => $document->submittedBy,
                'stage' => ProcurementDocument::STAGE_RETURNED_TO_REQUESTING_OFFICE,
                'decision' => BacChairReview::DECISION_RETURN_TO_REQUESTING_OFFICE,
                'missingMessage' => 'Requesting office routing target is not configured.',
            ],
            default => [
                'office' => $this->officeByCodeOrNames('BACSEC', ['BAC Secretariat']),
                'user' => $this->activeUserByRoleOrUserId(User::ROLE_BAC_SECRETARIAT, 'BACSEC-001'),
                'stage' => ProcurementDocument::STAGE_RETURNED_TO_BAC_SECRETARIAT,
                'decision' => BacChairReview::DECISION_RETURN_TO_BAC_SECRETARIAT,
                'missingMessage' => 'BAC Secretariat routing target is not configured.',
            ],
        };
    }

    private function returnTargets(): array
    {
        return [
            'bac_member' => 'Return to BAC Member',
            'bac_secretariat' => 'Return to BAC Secretariat',
            'accounting' => 'Return to Accounting Office',
            'requesting_office' => 'Return to Requesting Office',
        ];
    }

    private function targetActionUrl(?User $user, ProcurementDocument $document): ?string
    {
        if (!$user) {
            return null;
        }

        $route = match ($user->roleSlug()) {
            'bac-member' => 'bac-member.review.show',
            'bac-secretariat' => 'bac-secretariat.routing.show',
            'accounting' => 'accounting.returned.show',
            default => null,
        };

        return $route && Route::has($route) ? route($route, $document) : null;
    }

    private function officeByCodeOrNames(string $code, array $names): ?Office
    {
        return Office::where('code', $code)
            ->orWhereIn('name', $names)
            ->first();
    }

    private function activeUserByRoleOrUserId(string $role, ?string $userId = null): ?User
    {
        return User::where('status', User::STATUS_ACTIVE)
            ->where(function (Builder $query) use ($role, $userId) {
                if ($role === User::ROLE_APPROVING_AUTHORITY) {
                    $query->whereHas('assignedRole', fn (Builder $roleQuery) => $roleQuery->where('code', 'approving_authority'))
                        ->orWhere('role', $role)
                        ->orWhere('user_id', 'MAYOR-001');

                    if ($userId) {
                        $query->orWhere('user_id', $userId);
                    }

                    return;
                }

                $query->where('role', $role);

                if ($userId) {
                    $query->orWhere('user_id', $userId);
                }
            })
            ->first();
    }
}
