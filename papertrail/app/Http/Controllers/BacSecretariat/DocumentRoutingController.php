<?php

namespace App\Http\Controllers\BacSecretariat;

use App\Http\Controllers\Controller;
use App\Models\AccountingReview;
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

class DocumentRoutingController extends Controller
{
    private const DESTINATION_BAC_MEMBER = 'bac_member';
    private const DESTINATION_BAC_CHAIR = 'bac_chair';
    private const DESTINATION_APPROVING_AUTHORITY = 'approving_authority';
    private const DESTINATION_ADDITIONAL_REVIEW = 'additional_review';

    public function index(Request $request): View
    {
        AuditLogger::log('BAC Secretariat', 'Document Routing Page Viewed', 'BAC Secretariat viewed document routing queue.');

        $query = ProcurementDocument::query()
            ->routingForBacSecretariat($request->user())
            ->with(['submittingOffice', 'currentOffice', 'routeDestinationOffice', 'routedBy']);

        $this->applyFilters($query, $request);

        return view('bac-secretariat.routing.index', [
            'documents' => $query->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary($request->user()),
            'filters' => $request->only(['search', 'document_type', 'fiscal_year', 'status', 'destination_role', 'date_from', 'date_to']),
            'documentTypes' => ProcurementDocument::query()->select('document_type')->distinct()->orderBy('document_type')->pluck('document_type'),
            'fiscalYears' => ProcurementDocument::query()->select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
            'statuses' => $this->routingStatuses(),
            'destinationRoles' => $this->destinationOptions(),
        ]);
    }

    public function show(Request $request, ProcurementDocument $document): View|RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('BAC Secretariat', 'Unauthorized Access Attempt', 'BAC Secretariat attempted to view a routing document outside their scope.', $document, null, null, 'warning');

            return redirect()
                ->route('bac-secretariat.routing.index')
                ->with('error', 'You are not authorized to route this document.');
        }

        AuditLogger::log('BAC Secretariat', 'Routing Detail Viewed', 'BAC Secretariat viewed document routing detail.', $document);

        $document->load([
            'submittingOffice',
            'currentOffice',
            'submittedBy',
            'assignedTo',
            'budgetReviewedBy',
            'accountingReviewedBy',
            'bacSecretariatReceivedBy',
            'routedBy',
            'routeDestinationOffice',
            'budgetReviews.reviewedBy',
            'accountingReviews.reviewedBy',
            'bacSecretariatReviews.reviewedBy',
            'routingHistories.actionBy',
            'routingHistories.fromOffice',
            'routingHistories.toOffice',
        ]);

        return view('bac-secretariat.routing.show', [
            'document' => $document,
            'latestBudgetReview' => $document->budgetReviews->sortByDesc(fn (BudgetReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestAccountingReview' => $document->accountingReviews->sortByDesc(fn (AccountingReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'latestBacReview' => $document->bacSecretariatReviews->sortByDesc(fn (BacSecretariatReview $review) => $review->completed_at ?? $review->created_at)->first(),
            'destinationRoles' => $this->destinationOptions(),
            'offices' => Office::where('status', Office::STATUS_ACTIVE)->orderBy('name')->get(),
            'users' => User::where('status', User::STATUS_ACTIVE)->orderBy('name')->get(),
        ]);
    }

    public function route(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document) || $document->status !== ProcurementDocument::STATUS_READY_FOR_DOCUMENT_ROUTING) {
            AuditLogger::log('BAC Secretariat', 'Unauthorized Access Attempt', 'Invalid BAC document routing attempt.', $document, null, null, 'warning');

            return back()->with('error', 'Only documents marked ready for routing can be routed.');
        }

        $validated = $request->validate([
            'destination_role' => ['required', Rule::in(array_keys($this->destinationOptions()))],
            'destination_office_id' => ['nullable', 'exists:offices,id'],
            'assigned_to_user_id' => ['nullable', 'exists:users,id'],
            'route_remarks' => ['nullable', 'string', 'max:2000'],
            'expected_action' => ['nullable', 'string', 'max:500'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
        ]);

        if ($validated['destination_role'] === self::DESTINATION_ADDITIONAL_REVIEW && empty($validated['destination_office_id'])) {
            return back()->withErrors(['destination_office_id' => 'Destination office is required for additional review.'])->withInput();
        }

        $target = $this->resolveDestination($validated);

        if (!$target['office']) {
            return back()->with('error', 'Routing destination office is not configured.')->withInput();
        }

        if ($target['requiresUser'] && !$target['user']) {
            return back()->with('error', 'Routing target user is not configured.')->withInput();
        }

        $remarks = trim(collect([$validated['route_remarks'] ?? null, $validated['expected_action'] ?? null])->filter()->implode("\nInstruction: "));

        DB::transaction(function () use ($request, $document, $validated, $target, $remarks) {
            $oldStatus = $document->status;
            $fromOffice = $document->current_office_id;

            $document->update([
                'status' => $target['status'],
                'stage' => $target['stage'],
                'current_office_id' => $target['office']->id,
                'assigned_to_user_id' => $target['user']?->id,
                'routed_by_user_id' => $request->user()->id,
                'routed_at' => now(),
                'route_destination_role' => $target['label'],
                'route_destination_office_id' => $target['office']->id,
                'route_remarks' => $remarks ?: null,
                'priority' => $validated['priority'] ?? $document->priority,
            ]);

            $this->recordRouting($document, $request->user(), 'Routed Document', $oldStatus, $document->status, $remarks ?: 'Document routed for next action.', $fromOffice, $target['office']->id);

            $isBacMemberRouting = $document->status === ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW;
            $isBacChairRouting = $document->status === ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW;
            $actionUrl = match (true) {
                $isBacMemberRouting => route('bac-member.review.show', $document),
                $isBacChairRouting => route('bac-chair.approvals.show', $document),
                default => null,
            };

            SystemNotificationService::notify(
                $target['user'],
                match (true) {
                    $isBacMemberRouting => 'Document Assigned for BAC Review',
                    $isBacChairRouting => 'Document Routed to BAC Chair',
                    default => 'Document Routed to You',
                },
                match (true) {
                    $isBacMemberRouting => "Document {$document->tracking_number} has been routed to you for BAC Member review.",
                    $isBacChairRouting => "Document {$document->tracking_number} has been routed to you for BAC Chair review.",
                    default => "Document {$document->tracking_number} has been routed to you for review.",
                },
                SystemNotification::TYPE_INFO,
                $isBacMemberRouting ? 'BAC Member Review' : ($isBacChairRouting ? 'Document Routing' : 'Document Routing'),
                $document,
                $actionUrl,
            );

            AuditLogger::log('BAC Secretariat', $target['auditAction'], 'BAC Secretariat routed a procurement document.', $document, ['status' => $oldStatus], ['status' => $document->status, 'destination' => $target['label']]);
        });

        return redirect()
            ->route('bac-secretariat.routing.show', $document)
            ->with('status', 'Document routed successfully.');
    }

    public function return(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document) || !in_array($document->status, $this->routingStatuses(), true)) {
            AuditLogger::log('BAC Secretariat', 'Unauthorized Access Attempt', 'Invalid BAC routing return attempt.', $document, null, null, 'warning');

            return back()->with('error', 'This document cannot be returned from routing.');
        }

        $validated = $request->validate([
            'return_target' => ['required', Rule::in(['accounting', 'requesting_office'])],
            'comments' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $accountingOffice = $this->officeByCodeOrName('MACCO', 'Accounting Office');
        $accountingUser = $this->activeUserByRole(User::ROLE_ACCOUNTING);

        if ($validated['return_target'] === 'accounting' && !$accountingOffice && !$accountingUser) {
            return back()->with('error', 'Accounting Office routing target is not configured.')->withInput();
        }

        DB::transaction(function () use ($request, $document, $validated, $accountingOffice, $accountingUser) {
            $oldStatus = $document->status;
            $fromOffice = $document->current_office_id;
            $returnToAccounting = $validated['return_target'] === 'accounting';
            $toOffice = $returnToAccounting ? ($accountingOffice?->id ?? $accountingUser?->office_id) : $document->submitting_office_id;
            $assignedTo = $returnToAccounting ? $accountingUser?->id : $document->submitted_by_user_id;

            $document->update([
                'status' => ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
                'bac_secretariat_status' => BacSecretariatReview::STATUS_RETURNED,
                'stage' => $returnToAccounting ? ProcurementDocument::STAGE_RETURNED_TO_ACCOUNTING_OFFICE : ProcurementDocument::STAGE_RETURNED_TO_REQUESTING_OFFICE,
                'current_office_id' => $toOffice,
                'assigned_to_user_id' => $assignedTo,
                'route_remarks' => $validated['comments'],
                'bac_secretariat_remarks' => $validated['comments'],
                'bac_secretariat_processed_at' => now(),
            ]);

            $this->recordRouting($document, $request->user(), 'Returned by BAC Secretariat', $oldStatus, $document->status, $validated['comments'], $fromOffice, $toOffice);

            SystemNotificationService::notify(
                $returnToAccounting ? $accountingUser : $document->submittedBy,
                'Document Returned by BAC Secretariat',
                "Document {$document->tracking_number} was returned for correction or clarification.",
                SystemNotification::TYPE_WARNING,
                'Document Routing',
                $document,
            );

            AuditLogger::log('BAC Secretariat', 'Document Returned From Routing', 'BAC Secretariat returned a document from routing.', $document, ['status' => $oldStatus], ['status' => $document->status, 'return_target' => $validated['return_target']], 'warning');
        });

        return redirect()
            ->route('bac-secretariat.routing.index')
            ->with('status', 'Document returned successfully.');
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

        foreach (['document_type', 'fiscal_year'] as $field) {
            $query->when($request->filled($field), fn (Builder $builder) => $builder->where($field, $request->input($field)));
        }

        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', function (Builder $builder) use ($request) {
            match ($request->input('status')) {
                'bac_member' => $builder->whereIn('status', [ProcurementDocument::STATUS_ROUTED_TO_BAC_MEMBER, ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW]),
                'bac_chair' => $builder->whereIn('status', [ProcurementDocument::STATUS_ROUTED_TO_BAC_CHAIR, ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW]),
                'approval' => $builder->whereIn('status', [ProcurementDocument::STATUS_ROUTED_TO_APPROVING_AUTHORITY, ProcurementDocument::STATUS_PENDING_APPROVAL]),
                default => $builder->where('status', $request->input('status')),
            };
        });

        $query->when($request->filled('destination_role'), fn (Builder $builder) => $builder->where('route_destination_role', $request->input('destination_role')));
        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('updated_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('updated_at', '<=', $request->date('date_to')));
    }

    private function canAccess(User $user, ProcurementDocument $document): bool
    {
        return ProcurementDocument::query()
            ->routingForBacSecretariat($user)
            ->whereKey($document->id)
            ->exists();
    }

    private function summary(User $user): array
    {
        $base = ProcurementDocument::query()->routingForBacSecretariat($user);

        return [
            'ready' => (clone $base)->where('status', ProcurementDocument::STATUS_READY_FOR_DOCUMENT_ROUTING)->count(),
            'bacMember' => (clone $base)->whereIn('status', [ProcurementDocument::STATUS_ROUTED_TO_BAC_MEMBER, ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW])->count(),
            'bacChair' => (clone $base)->whereIn('status', [ProcurementDocument::STATUS_ROUTED_TO_BAC_CHAIR, ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW])->count(),
            'approval' => (clone $base)->whereIn('status', [ProcurementDocument::STATUS_ROUTED_TO_APPROVING_AUTHORITY, ProcurementDocument::STATUS_PENDING_APPROVAL])->count(),
        ];
    }

    private function resolveDestination(array $validated): array
    {
        return match ($validated['destination_role']) {
            self::DESTINATION_BAC_MEMBER => [
                'label' => 'BAC Member',
                'office' => $this->officeByCodeOrName('BAC', 'Bids and Awards Committee'),
                'user' => $this->activeUserByRole(User::ROLE_BAC_MEMBER),
                'requiresUser' => true,
                'status' => ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW,
                'stage' => ProcurementDocument::STAGE_BAC_MEMBER_REVIEW,
                'auditAction' => 'Document Routed to BAC Member',
            ],
            self::DESTINATION_BAC_CHAIR => [
                'label' => 'BAC Chair',
                'office' => $this->officeByCodeOrName('BAC', 'Bids and Awards Committee'),
                'user' => $this->activeUserByRole(User::ROLE_BAC_CHAIR),
                'requiresUser' => true,
                'status' => ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW,
                'stage' => ProcurementDocument::STAGE_BAC_CHAIR_REVIEW,
                'auditAction' => 'Document Routed to BAC Chair',
            ],
            self::DESTINATION_APPROVING_AUTHORITY => [
                'label' => 'Head of the Procuring Entity',
                'office' => $this->officeByCodeOrName('OMM', "Mayor's Office"),
                'user' => $this->activeUserByRole(User::ROLE_APPROVING_AUTHORITY),
                'requiresUser' => true,
                'status' => ProcurementDocument::STATUS_PENDING_APPROVAL,
                'stage' => ProcurementDocument::STAGE_APPROVING_AUTHORITY_REVIEW,
                'auditAction' => 'Document Routed to Head of the Procuring Entity',
            ],
            default => [
                'label' => 'Additional Review / Other Office',
                'office' => Office::find($validated['destination_office_id'] ?? null),
                'user' => User::where('status', User::STATUS_ACTIVE)->find($validated['assigned_to_user_id'] ?? null),
                'requiresUser' => false,
                'status' => ProcurementDocument::STATUS_ROUTED_FOR_ADDITIONAL_REVIEW,
                'stage' => ProcurementDocument::STAGE_ADDITIONAL_REVIEW,
                'auditAction' => 'Document Routed for Additional Review',
            ],
        };
    }

    private function recordRouting(ProcurementDocument $document, User $user, string $action, ?string $fromStatus, string $toStatus, ?string $comments = null, ?int $fromOfficeId = null, ?int $toOfficeId = null): void
    {
        DocumentRoutingHistory::create([
            'procurement_document_id' => $document->id,
            'action_by_user_id' => $user->id,
            'from_office_id' => $fromOfficeId,
            'to_office_id' => $toOfficeId,
            'action' => $action,
            'status_from' => $fromStatus,
            'status_to' => $toStatus,
            'comments' => $comments,
            'action_at' => now(),
        ]);
    }

    private function routingStatuses(): array
    {
        return [
            ProcurementDocument::STATUS_READY_FOR_DOCUMENT_ROUTING,
            ProcurementDocument::STATUS_ROUTED_TO_BAC_MEMBER,
            ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW,
            ProcurementDocument::STATUS_ROUTED_TO_BAC_CHAIR,
            ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW,
            ProcurementDocument::STATUS_ROUTED_TO_APPROVING_AUTHORITY,
            ProcurementDocument::STATUS_PENDING_APPROVAL,
            ProcurementDocument::STATUS_ROUTED_FOR_ADDITIONAL_REVIEW,
        ];
    }

    private function destinationOptions(): array
    {
        return [
            self::DESTINATION_BAC_MEMBER => 'BAC Member',
            self::DESTINATION_BAC_CHAIR => 'BAC Chair',
            self::DESTINATION_APPROVING_AUTHORITY => 'Head of the Procuring Entity',
            self::DESTINATION_ADDITIONAL_REVIEW => 'Additional Review / Other Office',
        ];
    }

    private function officeByCodeOrName(string $code, string $name): ?Office
    {
        return Office::where('code', $code)
            ->orWhere('name', $name)
            ->first();
    }

    private function activeUserByRole(string $role): ?User
    {
        return User::where('status', User::STATUS_ACTIVE)
            ->where(function (Builder $query) use ($role) {
                $query->where('role', $role);

                if ($role === User::ROLE_APPROVING_AUTHORITY) {
                    $query->orWhereHas('assignedRole', fn (Builder $roleQuery) => $roleQuery->where('code', 'approving_authority'))
                        ->orWhere('user_id', 'HOPE-001')
                        ->orWhere('user_id', 'MAYOR-001');
                }
            })
            ->first();
    }
}
