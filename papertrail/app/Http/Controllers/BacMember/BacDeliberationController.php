<?php

namespace App\Http\Controllers\BacMember;

use App\Http\Controllers\Controller;
use App\Models\AccountingReview;
use App\Models\BacDeliberation;
use App\Models\BacDeliberationComment;
use App\Models\BacDeliberationParticipant;
use App\Models\BacMemberReview;
use App\Models\BacSecretariatReview;
use App\Models\BudgetReview;
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

class BacDeliberationController extends Controller
{
    public function index(Request $request): View
    {
        $this->syncDeliberations($request->user());

        AuditLogger::log('BAC Deliberations', 'BAC Deliberations Page Viewed', 'BAC Member viewed BAC deliberations.');

        $query = $this->baseQuery($request->user())
            ->with(['procurementDocument.submittingOffice', 'procurementDocument.currentOffice', 'participants.user', 'chair']);

        $this->applyFilters($query, $request);

        return view('bac-member.deliberations.index', [
            'deliberations' => $query->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary($request->user()),
            'filters' => $request->only(['search', 'fiscal_year', 'status', 'recommendation_status', 'date_from', 'date_to']),
            'fiscalYears' => ProcurementDocument::query()->select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
            'statuses' => [
                BacDeliberation::STATUS_SCHEDULED,
                BacDeliberation::STATUS_ONGOING,
                BacDeliberation::STATUS_COMPLETED,
                BacDeliberation::STATUS_CANCELLED,
            ],
        ]);
    }

    public function show(Request $request, BacDeliberation $deliberation): View|RedirectResponse
    {
        if (!$this->canAccess($request->user(), $deliberation)) {
            AuditLogger::log('BAC Deliberations', 'Unauthorized Access Attempt', 'BAC Member attempted to access an unrelated deliberation.', $deliberation, null, null, 'warning');

            return redirect()
                ->route('bac-member.deliberations.index')
                ->with('error', 'You are not authorized to view this deliberation.');
        }

        $participant = $this->ensureParticipant($deliberation, $request->user());

        AuditLogger::log('BAC Deliberations', 'BAC Deliberation Detail Viewed', 'BAC Member viewed deliberation details.', $deliberation);

        $deliberation->load([
            'procurementDocument.submittingOffice',
            'procurementDocument.currentOffice',
            'procurementDocument.submittedBy',
            'procurementDocument.assignedTo',
            'procurementDocument.budgetReviews.reviewedBy',
            'procurementDocument.accountingReviews.reviewedBy',
            'procurementDocument.bacSecretariatReviews.reviewedBy',
            'procurementDocument.bacMemberReviews.reviewedBy',
            'procurementDocument.routingHistories.actionBy',
            'procurementDocument.routingHistories.fromOffice',
            'procurementDocument.routingHistories.toOffice',
            'createdBy',
            'chair',
            'participants.user',
            'comments.user',
        ]);

        $document = $deliberation->procurementDocument;

        return view('bac-member.deliberations.show', [
            'deliberation' => $deliberation,
            'document' => $document,
            'participant' => $participant->fresh(),
            'latestBudgetReview' => $document?->budgetReviews->sortByDesc(fn (BudgetReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestAccountingReview' => $document?->accountingReviews->sortByDesc(fn (AccountingReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestBacSecretariatReview' => $document?->bacSecretariatReviews->sortByDesc(fn (BacSecretariatReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestBacMemberReview' => $document?->bacMemberReviews
                ->where('reviewed_by_user_id', $request->user()->id)
                ->sortByDesc(fn (BacMemberReview $review) => $review->completed_at ?? $review->created_at)
                ->first(),
            'recommendations' => $this->recommendations(),
            'visibilities' => $this->visibilities(),
        ]);
    }

    public function storeComment(Request $request, BacDeliberation $deliberation): RedirectResponse
    {
        if (!$this->canParticipate($request->user(), $deliberation)) {
            AuditLogger::log('BAC Deliberations', 'Unauthorized Access Attempt', 'BAC Member attempted to comment on an unrelated deliberation.', $deliberation, null, null, 'warning');

            return back()->with('error', 'You are not authorized to comment on this deliberation.');
        }

        if (!$deliberation->isOpen()) {
            return back()->with('error', 'This deliberation is closed.');
        }

        $validated = $request->validate([
            'comment' => ['required', 'string', 'min:3', 'max:3000'],
            'visibility' => ['nullable', Rule::in(array_keys($this->visibilities()))],
        ]);

        $comment = $deliberation->comments()->create([
            'user_id' => $request->user()->id,
            'comment' => $validated['comment'],
            'visibility' => $validated['visibility'] ?? BacDeliberationComment::VISIBILITY_INTERNAL,
        ]);

        AuditLogger::log('BAC Deliberations', 'BAC Deliberation Comment Added', 'BAC Member added a deliberation comment.', $deliberation, null, ['comment_id' => $comment->id]);

        return back()->with('status', 'Comment added.');
    }

    public function updateRecommendation(Request $request, BacDeliberation $deliberation): RedirectResponse
    {
        if (!$this->canParticipate($request->user(), $deliberation)) {
            AuditLogger::log('BAC Deliberations', 'Unauthorized Access Attempt', 'BAC Member attempted to update another deliberation recommendation.', $deliberation, null, null, 'warning');

            return back()->with('error', 'You are not authorized to submit a recommendation for this deliberation.');
        }

        if (!$deliberation->isOpen()) {
            return back()->with('error', 'This deliberation is closed.');
        }

        $validated = $request->validate([
            'recommendation' => ['required', Rule::in(array_keys($this->recommendations()))],
            'recommendation_remarks' => ['nullable', 'string', 'max:3000'],
        ]);

        $participant = $this->ensureParticipant($deliberation, $request->user());
        $oldRecommendation = $participant->recommendation;

        $participant->update([
            'attendance_status' => BacDeliberationParticipant::ATTENDANCE_PRESENT,
            'recommendation' => $validated['recommendation'],
            'recommendation_remarks' => $validated['recommendation_remarks'] ?? null,
            'submitted_at' => now(),
        ]);

        SystemNotificationService::notify(
            $deliberation->chair,
            'BAC Member Recommendation Submitted',
            "A BAC Member submitted a recommendation for deliberation {$deliberation->deliberation_number}.",
            SystemNotification::TYPE_INFO,
            'BAC Deliberations',
            $deliberation,
            null,
        );

        AuditLogger::log('BAC Deliberations', 'BAC Member Recommendation Submitted', 'BAC Member submitted a deliberation recommendation.', $deliberation, ['recommendation' => $oldRecommendation], ['recommendation' => $participant->recommendation]);

        return back()->with('status', 'Recommendation submitted.');
    }

    public function document(Request $request, BacDeliberation $deliberation): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $deliberation)) {
            AuditLogger::log('BAC Deliberations', 'Unauthorized Access Attempt', 'BAC Member attempted to open an unrelated deliberation document.', $deliberation, null, null, 'warning');

            return redirect()->route('bac-member.deliberations.index')->with('error', 'You are not authorized to view this document.');
        }

        $document = $deliberation->procurementDocument;

        if (in_array($document->status, [ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW, ProcurementDocument::STATUS_UNDER_BAC_MEMBER_REVIEW], true)) {
            return redirect()->route('bac-member.review.show', $document);
        }

        return redirect()->route('bac-member.reviewed.show', $document);
    }

    private function baseQuery(User $user): Builder
    {
        return BacDeliberation::query()
            ->whereHas('procurementDocument', fn (Builder $document) => $document->whereIn('status', $this->documentStatuses()))
            ->where(function (Builder $query) use ($user) {
                $query->whereHas('participants', fn (Builder $participant) => $participant->where('user_id', $user->id))
                    ->orWhereHas('procurementDocument', function (Builder $document) use ($user) {
                        $document->where('assigned_to_user_id', $user->id)
                            ->orWhere('bac_member_reviewed_by_user_id', $user->id);
                    });
            });
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();
            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('deliberation_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhereHas('procurementDocument', function (Builder $document) use ($search) {
                        $document->where('tracking_number', 'like', "%{$search}%")
                            ->orWhere('title', 'like', "%{$search}%")
                            ->orWhereHas('submittingOffice', fn (Builder $office) => $office->where('name', 'like', "%{$search}%"));
                    });
            });
        });

        $query->when($request->filled('fiscal_year'), fn (Builder $builder) => $builder->whereHas('procurementDocument', fn (Builder $document) => $document->where('fiscal_year', $request->input('fiscal_year'))));
        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', fn (Builder $builder) => $builder->where('status', $request->input('status')));
        $query->when($request->filled('recommendation_status'), function (Builder $builder) use ($request) {
            $builder->whereHas('participants', function (Builder $participant) use ($request) {
                $participant->where('user_id', $request->user()->id);

                if ($request->input('recommendation_status') === 'submitted') {
                    $participant->whereNotNull('recommendation');
                }

                if ($request->input('recommendation_status') === 'pending') {
                    $participant->whereNull('recommendation');
                }
            });
        });
        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('updated_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('updated_at', '<=', $request->date('date_to')));
    }

    private function summary(User $user): array
    {
        $base = $this->baseQuery($user);

        return [
            'active' => (clone $base)->whereIn('status', [BacDeliberation::STATUS_SCHEDULED, BacDeliberation::STATUS_ONGOING])->count(),
            'pendingRecommendation' => (clone $base)->whereHas('participants', fn (Builder $participant) => $participant->where('user_id', $user->id)->whereNull('recommendation'))->count(),
            'submittedRecommendations' => (clone $base)->whereHas('participants', fn (Builder $participant) => $participant->where('user_id', $user->id)->whereNotNull('recommendation'))->count(),
            'completed' => (clone $base)->where('status', BacDeliberation::STATUS_COMPLETED)->count(),
        ];
    }

    private function canAccess(User $user, BacDeliberation $deliberation): bool
    {
        return $this->baseQuery($user)->whereKey($deliberation->id)->exists();
    }

    private function canParticipate(User $user, BacDeliberation $deliberation): bool
    {
        return $deliberation->participants()->where('user_id', $user->id)->exists()
            || $deliberation->procurementDocument?->assigned_to_user_id === $user->id
            || $deliberation->procurementDocument?->bac_member_reviewed_by_user_id === $user->id;
    }

    private function ensureParticipant(BacDeliberation $deliberation, User $user): BacDeliberationParticipant
    {
        return $deliberation->participants()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'role_name' => $user->role,
                'attendance_status' => BacDeliberationParticipant::ATTENDANCE_PENDING,
            ],
        );
    }

    private function syncDeliberations(User $user): void
    {
        ProcurementDocument::query()
            ->whereIn('status', [
                ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW,
                ProcurementDocument::STATUS_UNDER_BAC_MEMBER_REVIEW,
            ])
            ->where(function (Builder $query) use ($user) {
                $query->where('assigned_to_user_id', $user->id)
                    ->orWhere(function (Builder $unassigned) {
                        $unassigned->whereNull('assigned_to_user_id')
                            ->whereHas('currentOffice', fn (Builder $office) => $office->where('code', 'BAC')->orWhere('name', 'Bids and Awards Committee'));
                    });
            })
            ->with(['bacDeliberations', 'submittingOffice'])
            ->get()
            ->each(function (ProcurementDocument $document) use ($user) {
                if ($document->bacDeliberations->isNotEmpty()) {
                    $deliberation = $document->bacDeliberations->first();
                    $participant = $this->ensureParticipant($deliberation, $user);

                    if ($participant->wasRecentlyCreated) {
                        $this->notifyDeliberationAssignment($user, $deliberation, $document);
                    }

                    return;
                }

                DB::transaction(function () use ($document, $user) {
                    $chair = $this->activeUserByRoleOrUserId(User::ROLE_BAC_CHAIR, 'BACCHAIR-001');

                    $deliberation = BacDeliberation::create([
                        'procurement_document_id' => $document->id,
                        'deliberation_number' => $this->nextDeliberationNumber(),
                        'title' => 'BAC Deliberation - ' . $document->tracking_number,
                        'agenda' => $document->title,
                        'status' => BacDeliberation::STATUS_SCHEDULED,
                        'scheduled_at' => now(),
                        'created_by_user_id' => $document->routed_by_user_id,
                        'chair_user_id' => $chair?->id,
                        'remarks' => 'Auto-created for a real document routed to BAC Member review.',
                    ]);

                    $this->ensureParticipant($deliberation, $user);

                    $this->notifyDeliberationAssignment($user, $deliberation, $document);
                });
            });
    }

    private function notifyDeliberationAssignment(User $user, BacDeliberation $deliberation, ProcurementDocument $document): void
    {
        SystemNotificationService::notify(
            $user,
            'BAC Deliberation Assigned',
            "You have been added to a BAC deliberation for document {$document->tracking_number}.",
            SystemNotification::TYPE_INFO,
            'BAC Deliberations',
            $deliberation,
            route('bac-member.deliberations.show', $deliberation),
        );
    }

    private function nextDeliberationNumber(): string
    {
        $year = now()->year;
        $count = BacDeliberation::whereYear('created_at', $year)->count() + 1;

        return 'BAC-DEL-' . $year . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function documentStatuses(): array
    {
        return [
            ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW,
            ProcurementDocument::STATUS_UNDER_BAC_MEMBER_REVIEW,
            ProcurementDocument::STATUS_ENDORSED_BY_BAC_MEMBER,
            ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW,
            'under_bac_chair_review',
            'bac_chair_reviewed',
            ProcurementDocument::STATUS_RETURNED_BY_BAC_MEMBER,
        ];
    }

    private function recommendations(): array
    {
        return [
            BacDeliberationParticipant::RECOMMEND_FOR_BAC_CHAIR_REVIEW => 'Recommend for BAC Chair Review',
            BacDeliberationParticipant::RECOMMEND_RETURN => 'Recommend Return',
            BacDeliberationParticipant::RECOMMEND_FOR_APPROVAL => 'Recommend for Approval',
            BacDeliberationParticipant::RECOMMEND_WITH_COMMENTS => 'Recommend with Comments',
        ];
    }

    private function visibilities(): array
    {
        return [
            BacDeliberationComment::VISIBILITY_INTERNAL => 'Internal',
            BacDeliberationComment::VISIBILITY_PUBLIC_TO_BAC => 'Public to BAC',
        ];
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
