<?php

namespace App\Http\Controllers\BacMember;

use App\Http\Controllers\Controller;
use App\Models\AccountingReview;
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
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DocumentsForReviewController extends Controller
{
    public function index(Request $request): View
    {
        AuditLogger::log('BAC Member Review', 'BAC Member Documents for Review Page Viewed', 'BAC Member viewed assigned documents for review.');

        $query = $this->baseReviewQuery($request->user())
            ->with(['submittingOffice', 'currentOffice', 'submittedBy', 'routedBy']);

        $this->applyFilters($query, $request);

        return view('bac-member.review.index', [
            'documents' => $query->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary($request->user()),
            'filters' => $request->only(['search', 'document_type', 'fiscal_year', 'priority', 'status', 'date_from', 'date_to']),
            'documentTypes' => ProcurementDocument::query()->select('document_type')->distinct()->orderBy('document_type')->pluck('document_type'),
            'fiscalYears' => ProcurementDocument::query()->select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
            'statuses' => [
                ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW,
                ProcurementDocument::STATUS_UNDER_BAC_MEMBER_REVIEW,
            ],
        ]);
    }

    public function show(Request $request, ProcurementDocument $document): View|RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('BAC Member Review', 'Unauthorized Access Attempt', 'BAC Member attempted to access an unassigned document.', $document, null, null, 'warning');

            return redirect()
                ->route('bac-member.review.index')
                ->with('error', 'You are not authorized to review this document.');
        }

        AuditLogger::log('BAC Member Review', 'BAC Member Review Detail Viewed', 'BAC Member viewed document review details.', $document);

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

        return view('bac-member.review.show', [
            'document' => $document,
            'latestBudgetReview' => $document->budgetReviews->sortByDesc(fn (BudgetReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestAccountingReview' => $document->accountingReviews->sortByDesc(fn (AccountingReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestBacSecretariatReview' => $document->bacSecretariatReviews->sortByDesc(fn (BacSecretariatReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestBacMemberReview' => $document->bacMemberReviews->sortByDesc(fn (BacMemberReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'returnTargets' => $this->returnTargets(),
            'recommendations' => $this->recommendations(),
        ]);
    }

    public function start(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document) || $document->status !== ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW) {
            AuditLogger::log('BAC Member Review', 'Unauthorized Access Attempt', 'Invalid BAC Member review start attempt.', $document, null, null, 'warning');

            return back()->with('error', 'Only pending BAC Member review documents can be started.');
        }

        DB::transaction(function () use ($request, $document) {
            $oldStatus = $document->status;
            $fromOffice = $document->current_office_id;

            $document->update([
                'status' => ProcurementDocument::STATUS_UNDER_BAC_MEMBER_REVIEW,
                'bac_member_status' => BacMemberReview::STATUS_UNDER_REVIEW,
                'stage' => ProcurementDocument::STAGE_BAC_MEMBER_REVIEW,
                'assigned_to_user_id' => $request->user()->id,
                'bac_member_review_started_at' => now(),
            ]);

            $this->reviewRecord($document, $request->user())->fill([
                'review_status' => BacMemberReview::STATUS_UNDER_REVIEW,
                'started_at' => now(),
            ])->save();

            $this->recordRouting($document, $request->user(), 'BAC Member Review Started', $oldStatus, $document->status, 'BAC Member review started.', $fromOffice, $document->current_office_id);

            $bacSecretariatUser = $this->activeUserByRoleOrUserId(User::ROLE_BAC_SECRETARIAT, 'BACSEC-001');

            SystemNotificationService::notify(
                $bacSecretariatUser,
                'BAC Member Review Started',
                "BAC Member started reviewing document {$document->tracking_number}.",
                SystemNotification::TYPE_INFO,
                'BAC Member Review',
                $document,
            );

            AuditLogger::log('BAC Member Review', 'BAC Member Review Started', 'BAC Member started reviewing a procurement document.', $document, ['status' => $oldStatus], ['status' => $document->status]);
        });

        return back()->with('status', 'BAC Member review started.');
    }

    public function return(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)
            || !in_array($document->status, [ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW, ProcurementDocument::STATUS_UNDER_BAC_MEMBER_REVIEW], true)) {
            AuditLogger::log('BAC Member Review', 'Unauthorized Access Attempt', 'Invalid BAC Member return attempt.', $document, null, null, 'warning');

            return back()->with('error', 'This document cannot be returned from BAC Member review.');
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
                'status' => ProcurementDocument::STATUS_RETURNED_BY_BAC_MEMBER,
                'bac_member_status' => BacMemberReview::STATUS_RETURNED,
                'stage' => $target['stage'],
                'current_office_id' => $toOffice,
                'assigned_to_user_id' => $target['user']?->id,
                'bac_member_reviewed_by_user_id' => $request->user()->id,
                'bac_member_reviewed_at' => now(),
                'bac_member_remarks' => $validated['comments'],
                'remarks' => $validated['comments'],
            ]);

            $this->reviewRecord($document, $request->user())->fill([
                'review_status' => BacMemberReview::STATUS_RETURNED,
                'remarks' => $validated['comments'],
                'started_at' => $document->bac_member_review_started_at,
                'completed_at' => now(),
            ])->save();

            $this->recordRouting($document, $request->user(), 'Returned by BAC Member', $oldStatus, $document->status, $validated['comments'], $fromOffice, $toOffice);

            SystemNotificationService::notify(
                $target['user'],
                'Document Returned by BAC Member',
                "Document {$document->tracking_number} was returned for correction or clarification.",
                SystemNotification::TYPE_WARNING,
                'BAC Member Review',
                $document,
            );

            AuditLogger::log('BAC Member Review', 'Document Returned by BAC Member', 'BAC Member returned a procurement document.', $document, ['status' => $oldStatus], ['status' => $document->status, 'return_target' => $validated['return_target']], 'warning');
        });

        return redirect()
            ->route('bac-member.review.index')
            ->with('status', 'Document returned successfully.');
    }

    public function endorse(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document) || $document->status !== ProcurementDocument::STATUS_UNDER_BAC_MEMBER_REVIEW) {
            AuditLogger::log('BAC Member Review', 'Unauthorized Access Attempt', 'Invalid BAC Member endorsement attempt.', $document, null, null, 'warning');

            return back()->with('error', 'Only documents under BAC Member review can be endorsed.');
        }

        $validated = $request->validate([
            'recommendation' => ['required', Rule::in(array_keys($this->recommendations()))],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $bacOffice = $this->officeByCodeOrName('BAC', 'Bids and Awards Committee');
        $bacChair = $this->activeUserByRoleOrUserId(User::ROLE_BAC_CHAIR, 'BACCHAIR-001');

        if (!$bacChair) {
            return back()->with('error', 'BAC Chair routing target is not configured.')->withInput();
        }

        DB::transaction(function () use ($request, $document, $validated, $bacOffice, $bacChair) {
            $oldStatus = $document->status;
            $fromOffice = $document->current_office_id;
            $remarks = $validated['remarks'] ?? null;

            $document->update([
                'status' => ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW,
                'bac_member_status' => BacMemberReview::STATUS_ENDORSED,
                'stage' => ProcurementDocument::STAGE_BAC_CHAIR_REVIEW,
                'current_office_id' => $bacOffice?->id ?? $bacChair->office_id,
                'assigned_to_user_id' => $bacChair->id,
                'bac_member_reviewed_by_user_id' => $request->user()->id,
                'bac_member_reviewed_at' => now(),
                'bac_member_remarks' => $remarks,
                'route_destination_role' => 'BAC Chair',
                'route_destination_office_id' => $bacOffice?->id ?? $bacChair->office_id,
                'route_remarks' => $remarks,
            ]);

            $this->reviewRecord($document, $request->user())->fill([
                'review_status' => BacMemberReview::STATUS_ENDORSED,
                'recommendation' => $validated['recommendation'],
                'remarks' => $remarks,
                'started_at' => $document->bac_member_review_started_at,
                'completed_at' => now(),
            ])->save();

            $this->recordRouting($document, $request->user(), 'Endorsed by BAC Member', $oldStatus, ProcurementDocument::STATUS_ENDORSED_BY_BAC_MEMBER, $remarks ?: 'Recommended for BAC Chair review.', $fromOffice, $document->current_office_id);
            $this->recordRouting($document, $request->user(), 'Routed to BAC Chair', ProcurementDocument::STATUS_ENDORSED_BY_BAC_MEMBER, $document->status, $remarks ?: 'Document routed to BAC Chair.', $fromOffice, $document->current_office_id);

            SystemNotificationService::notify(
                $bacChair,
                'Document Endorsed for BAC Chair Review',
                "Document {$document->tracking_number} has been endorsed by a BAC Member for BAC Chair review.",
                SystemNotification::TYPE_INFO,
                'BAC Chair Review',
                $document,
                route('bac-chair.approvals.show', $document),
            );

            AuditLogger::log('BAC Member Review', 'Document Endorsed to BAC Chair', 'BAC Member endorsed a procurement document to the BAC Chair.', $document, ['status' => $oldStatus], ['status' => $document->status, 'recommendation' => $validated['recommendation']]);
        });

        return redirect()
            ->route('bac-member.review.index')
            ->with('status', 'Document endorsed to BAC Chair.');
    }

    private function baseReviewQuery(User $user): Builder
    {
        return ProcurementDocument::query()->assignedToBacMember($user);
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

        foreach (['document_type', 'fiscal_year', 'priority', 'status'] as $field) {
            $query->when($request->filled($field), fn (Builder $builder) => $builder->where($field, $request->input($field)));
        }

        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('updated_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('updated_at', '<=', $request->date('date_to')));
    }

    private function canAccess(User $user, ProcurementDocument $document): bool
    {
        return $this->baseReviewQuery($user)->whereKey($document->id)->exists();
    }

    private function summary(User $user): array
    {
        $base = $this->baseReviewQuery($user);

        return [
            'pending' => (clone $base)->where('status', ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW)->count(),
            'underReview' => (clone $base)->where('status', ProcurementDocument::STATUS_UNDER_BAC_MEMBER_REVIEW)->count(),
            'endorsed' => ProcurementDocument::query()->reviewedByBacMember($user)->count(),
            'returned' => ProcurementDocument::query()
                ->where('status', ProcurementDocument::STATUS_RETURNED_BY_BAC_MEMBER)
                ->where('bac_member_reviewed_by_user_id', $user->id)
                ->count(),
        ];
    }

    private function reviewRecord(ProcurementDocument $document, User $user): BacMemberReview
    {
        return BacMemberReview::firstOrNew([
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
            'bac_secretariat' => [
                'office' => $this->officeByCodeOrName('BACSEC', 'BAC Secretariat'),
                'user' => $this->activeUserByRoleOrUserId(User::ROLE_BAC_SECRETARIAT, 'BACSEC-001'),
                'stage' => ProcurementDocument::STAGE_RETURNED_TO_BAC_SECRETARIAT,
                'missingMessage' => 'BAC Secretariat routing target is not configured.',
            ],
            'accounting' => [
                'office' => $this->officeByCodeOrName('MACCO', 'Accounting Office'),
                'user' => $this->activeUserByRoleOrUserId(User::ROLE_ACCOUNTING),
                'stage' => ProcurementDocument::STAGE_RETURNED_TO_ACCOUNTING_OFFICE,
                'missingMessage' => 'Accounting Office routing target is not configured.',
            ],
            default => [
                'office' => $document->submittingOffice,
                'user' => $document->submittedBy,
                'stage' => ProcurementDocument::STAGE_RETURNED_TO_REQUESTING_OFFICE,
                'missingMessage' => 'Requesting office routing target is not configured.',
            ],
        };
    }

    private function returnTargets(): array
    {
        return [
            'bac_secretariat' => 'Return to BAC Secretariat',
            'accounting' => 'Return to Accounting Office',
            'requesting_office' => 'Return to Requesting Office',
        ];
    }

    private function recommendations(): array
    {
        return [
            'recommend_for_bac_chair_review' => 'Recommend for BAC Chair Review',
            'recommend_with_comments' => 'Recommend with Comments',
        ];
    }

    private function officeByCodeOrName(string $code, string $name): ?Office
    {
        return Office::where('code', $code)
            ->orWhere('name', $name)
            ->first();
    }

    private function activeUserByRoleOrUserId(string $role, ?string $userId = null): ?User
    {
        return User::where('status', User::STATUS_ACTIVE)
            ->where(function (Builder $query) use ($role, $userId) {
                $query->where('role', $role);

                if ($userId) {
                    $query->orWhere('user_id', $userId);
                }
            })
            ->first();
    }
}
