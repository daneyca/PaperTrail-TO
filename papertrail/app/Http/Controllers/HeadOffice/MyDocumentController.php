<?php

namespace App\Http\Controllers\HeadOffice;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DocumentRoutingHistory;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyDocumentController extends Controller
{
    private const RETURNED_STATUSES = [
        ProcurementDocument::STATUS_RETURNED_BY_PR_NUMBERING_STAFF,
        ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
        ProcurementDocument::STATUS_RETURNED_BY_BUDGET,
        ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING,
        ProcurementDocument::STATUS_RETURNED_BY_BAC_MEMBER,
        ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR,
        ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY,
        ProcurementDocument::STATUS_PO_RETURNED,
        ProcurementDocument::STATUS_PPMP_SIGNATURE_RETURNED,
    ];

    private const APPROVED_STATUSES = [
        ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION,
        ProcurementDocument::STATUS_APPROVED,
        ProcurementDocument::STATUS_APP_APPROVED,
        ProcurementDocument::STATUS_READY_FOR_PO,
        ProcurementDocument::STATUS_PO_APPROVED,
        ProcurementDocument::STATUS_PO_ISSUED,
        ProcurementDocument::STATUS_PO_COMPLETED,
        'completed',
    ];

    private const DRAFT_STATUSES = [
        ProcurementDocument::STATUS_PPMP_DRAFT,
        ProcurementDocument::STATUS_PR_DRAFT,
        ProcurementDocument::STATUS_PO_DRAFT,
        'draft',
    ];

    private const SOURCE_OPTIONS = [
        'bac_secretariat' => 'BAC Secretariat',
        'pr_numbering' => 'PR Numbering Staff',
        'budget' => 'Budget Office',
        'accounting' => 'Accounting Office',
        'bac_member' => 'BAC Member',
        'bac_chair' => 'BAC Chair',
        'approving_authority' => 'Head of the Procuring Entity',
        'purchase_order' => 'Purchase Order Processing',
        'signatories' => 'Signatories',
    ];

    private const SOURCE_STATUS_MAP = [
        'pr_numbering' => [ProcurementDocument::STATUS_RETURNED_BY_PR_NUMBERING_STAFF],
        'bac_secretariat' => [ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT],
        'budget' => [ProcurementDocument::STATUS_RETURNED_BY_BUDGET],
        'accounting' => [ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING],
        'bac_member' => [ProcurementDocument::STATUS_RETURNED_BY_BAC_MEMBER],
        'bac_chair' => [ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR],
        'approving_authority' => [ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY],
        'purchase_order' => [ProcurementDocument::STATUS_PO_RETURNED],
        'signatories' => [ProcurementDocument::STATUS_PPMP_SIGNATURE_RETURNED],
    ];

    public function index(Request $request): View
    {
        AuditLogger::log('Head Office Documents', 'My Documents Page Viewed', 'Head of Office viewed office procurement documents.');

        $activeTab = $request->query('tab') === 'returned' ? 'returned' : 'all';

        if ($activeTab === 'returned') {
            $query = $this->returnedDocumentsQuery($request->user())
                ->with([
                    'submittingOffice',
                    'submittedBy',
                    'preparedBy',
                    'currentOffice',
                    'routingHistories.actionBy',
                    'routingHistories.fromOffice',
                    'routingHistories.toOffice',
                    'purchaseOrder.issuedBy',
                ]);

            $this->applyReturnedFilters($query, $request);

            $documents = $query->latest('updated_at')->paginate(10)->withQueryString();
            $documents->getCollection()->transform(function (ProcurementDocument $document) {
                $document->setAttribute('return_meta', $this->returnMeta($document));

                return $document;
            });

            $documentTypesQuery = $this->returnedDocumentsQuery($request->user());
            $statusesQuery = $this->returnedDocumentsQuery($request->user());
        } else {
            $query = $this->ownedDocumentsQuery($request->user())
                ->with(['submittingOffice', 'submittedBy', 'preparedBy', 'currentOffice']);

            $this->applyFilters($query, $request);

            $documents = $query->latest('updated_at')->paginate(10)->withQueryString();
            $documentTypesQuery = $this->ownedDocumentsQuery($request->user());
            $statusesQuery = $this->ownedDocumentsQuery($request->user());
        }

        return view('head-office.documents.index', [
            'activeTab' => $activeTab,
            'documents' => $documents,
            'summary' => $this->summary($request->user()),
            'returnedSummary' => $this->returnedSummary($request->user()),
            'filters' => $request->only(['tab', 'search', 'document_type', 'fiscal_year', 'status', 'stage', 'return_source', 'date_from', 'date_to']),
            'documentTypes' => $documentTypesQuery
                ->select('document_type')
                ->whereNotNull('document_type')
                ->distinct()
                ->orderBy('document_type')
                ->pluck('document_type'),
            'fiscalYears' => ($activeTab === 'returned' ? $this->returnedDocumentsQuery($request->user()) : $this->ownedDocumentsQuery($request->user()))
                ->select('fiscal_year')
                ->whereNotNull('fiscal_year')
                ->distinct()
                ->orderByDesc('fiscal_year')
                ->pluck('fiscal_year'),
            'statuses' => $statusesQuery
                ->select('status')
                ->whereNotNull('status')
                ->distinct()
                ->orderBy('status')
                ->pluck('status'),
            'stages' => $this->ownedDocumentsQuery($request->user())
                ->select('stage')
                ->whereNotNull('stage')
                ->distinct()
                ->orderBy('stage')
                ->pluck('stage'),
            'returnSources' => self::SOURCE_OPTIONS,
            'office' => $request->user()->assignedOffice,
        ]);
    }

    public function show(Request $request, ProcurementDocument $document): View
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('Head Office Documents', 'Unauthorized My Documents Access Attempt', 'Head of Office attempted to access a document outside their office scope.', $document, null, null, 'warning');
            abort(403);
        }

        AuditLogger::log('Head Office Documents', 'My Document Detail Viewed', 'Head of Office viewed document tracking details.', $document);

        $document->load([
            'submittingOffice',
            'submittedBy',
            'preparedBy',
            'currentOffice',
            'assignedTo',
            'ppmpItems',
            'purchaseRequestItems.appItem',
            'supplementalApps.items',
            'supplementalApps.acceptedBy',
            'purchaseOrder.items',
            'purchaseOrder.preparedBy',
            'purchaseOrder.issuedBy',
            'attachments.uploadedBy',
            'routingHistories.actionBy',
            'routingHistories.fromOffice',
            'routingHistories.toOffice',
        ]);

        return view('head-office.documents.show', [
            'document' => $document,
            'activities' => $this->documentActivities($document),
            'canEdit' => $this->canEdit($document),
        ]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();

            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('document_reference_number', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhere('pr_no', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%");
            });
        });

        foreach (['document_type', 'fiscal_year', 'stage', 'created_year', 'created_month'] as $field) {
            $query->when($request->filled($field), fn (Builder $builder) => $builder->where($field, $request->input($field)));
        }

        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', function (Builder $builder) use ($request): void {
            $status = (string) $request->input('status');

            match ($status) {
                'drafts' => $builder->whereIn('status', self::DRAFT_STATUSES),
                'in_process' => $builder->whereNotIn('status', array_merge(self::DRAFT_STATUSES, self::RETURNED_STATUSES, self::APPROVED_STATUSES)),
                'returned' => $builder->whereIn('status', self::RETURNED_STATUSES),
                'approved' => $builder->whereIn('status', self::APPROVED_STATUSES),
                default => $builder->where('status', $status),
            };
        });

        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('updated_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('updated_at', '<=', $request->date('date_to')));
    }

    private function applyReturnedFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();

            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('document_reference_number', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhere('pr_no', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%");
            });
        });

        $query->when($request->filled('fiscal_year'), fn (Builder $builder) => $builder->where('fiscal_year', $request->input('fiscal_year')));

        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', fn (Builder $builder) => $builder->where('status', $request->input('status')));

        $query->when($request->filled('document_type'), function (Builder $builder) use ($request): void {
            $documentType = (string) $request->input('document_type');
            $groups = [
                'pr' => ['PR', 'Purchase Request'],
            ];

            $builder->whereIn('document_type', $groups[strtolower($documentType)] ?? [$documentType]);
        });

        $query->when($request->filled('return_source'), function (Builder $builder) use ($request): void {
            $statuses = self::SOURCE_STATUS_MAP[$request->input('return_source')] ?? [];

            if ($statuses !== []) {
                $builder->whereIn('status', $statuses);
            }
        });

        $query->when($request->filled('date_from'), function (Builder $builder) use ($request): void {
            $date = $request->date('date_from');

            $builder->where(function (Builder $dateQuery) use ($date): void {
                $dateQuery->whereDate('returned_at', '>=', $date)
                    ->orWhereDate('returned_by_approving_authority_at', '>=', $date)
                    ->orWhereHas('routingHistories', fn (Builder $history) => $history->whereIn('status_to', self::RETURNED_STATUSES)->whereDate('action_at', '>=', $date))
                    ->orWhereDate('updated_at', '>=', $date);
            });
        });

        $query->when($request->filled('date_to'), function (Builder $builder) use ($request): void {
            $date = $request->date('date_to');

            $builder->where(function (Builder $dateQuery) use ($date): void {
                $dateQuery->whereDate('returned_at', '<=', $date)
                    ->orWhereDate('returned_by_approving_authority_at', '<=', $date)
                    ->orWhereHas('routingHistories', fn (Builder $history) => $history->whereIn('status_to', self::RETURNED_STATUSES)->whereDate('action_at', '<=', $date))
                    ->orWhereDate('updated_at', '<=', $date);
            });
        });
    }

    private function returnedDocumentsQuery(User $user): Builder
    {
        return $this->ownedDocumentsQuery($user)
            ->whereIn('status', self::RETURNED_STATUSES);
    }

    private function ownedDocumentsQuery(User $user): Builder
    {
        return ProcurementDocument::query()
            ->where(function (Builder $query) use ($user) {
                if ($user->office_id) {
                    $query->where('submitting_office_id', $user->office_id);
                }

                $query->orWhere('submitted_by_user_id', $user->id)
                    ->orWhere('prepared_by_user_id', $user->id);
            });
    }

    private function canAccess(User $user, ProcurementDocument $document): bool
    {
        return $this->ownedDocumentsQuery($user)->whereKey($document->id)->exists();
    }

    private function canEdit(ProcurementDocument $document): bool
    {
        if ($document->document_type === 'PPMP') {
            return in_array($document->status, [
                ProcurementDocument::STATUS_PPMP_DRAFT,
                ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
                ProcurementDocument::STATUS_PPMP_SIGNATURE_RETURNED,
            ], true);
        }

        if (in_array($document->document_type, ['PR', 'Purchase Request'], true)) {
            return in_array($document->status, [
                ProcurementDocument::STATUS_PR_DRAFT,
                ProcurementDocument::STATUS_RETURNED_BY_PR_NUMBERING_STAFF,
                ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
                ProcurementDocument::STATUS_RETURNED_BY_BUDGET,
                ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING,
                ProcurementDocument::STATUS_RETURNED_BY_BAC_MEMBER,
                ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR,
                ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY,
                'pr_returned',
                'returned',
            ], true);
        }

        return false;
    }

    private function summary(User $user): array
    {
        $base = $this->ownedDocumentsQuery($user);

        return [
            'total' => (clone $base)->count(),
            'drafts' => (clone $base)->whereIn('status', self::DRAFT_STATUSES)->count(),
            'inProcess' => (clone $base)
                ->whereNotIn('status', array_merge(self::DRAFT_STATUSES, self::RETURNED_STATUSES, self::APPROVED_STATUSES))
                ->count(),
            'returned' => (clone $base)->whereIn('status', self::RETURNED_STATUSES)->count(),
            'approved' => (clone $base)->whereIn('status', self::APPROVED_STATUSES)->count(),
        ];
    }

    private function returnedSummary(User $user): array
    {
        $base = $this->returnedDocumentsQuery($user);

        return [
            'total' => (clone $base)->count(),
            'ppmp' => (clone $base)->where('document_type', 'PPMP')->count(),
            'pr' => (clone $base)->whereIn('document_type', ['PR', 'Purchase Request'])->count(),
            'thisMonth' => (clone $base)
                ->where(function (Builder $query): void {
                    $query->whereMonth('returned_at', now()->month)->whereYear('returned_at', now()->year)
                        ->orWhereMonth('returned_by_approving_authority_at', now()->month)->whereYear('returned_by_approving_authority_at', now()->year)
                        ->orWhereHas('routingHistories', function (Builder $history): void {
                            $history->whereIn('status_to', self::RETURNED_STATUSES)
                                ->whereMonth('action_at', now()->month)
                                ->whereYear('action_at', now()->year);
                        });
                })
                ->count(),
        ];
    }

    private function returnMeta(ProcurementDocument $document): array
    {
        $history = $this->returnHistory($document);

        return [
            'history' => $history,
            'source' => $this->sourceLabel($document->status),
            'returned_by' => $history?->actionBy?->name ?? $this->returnedByFallback($document),
            'from_office' => $history?->fromOffice?->name ?? $this->sourceLabel($document->status),
            'to_office' => $history?->toOffice?->name ?? $document->currentOffice?->name ?? $document->submittingOffice?->name ?? 'N/A',
            'date' => $history?->action_at ?? $this->returnedDateFallback($document),
            'reason' => $this->returnReason($document, $history),
            'required_correction' => $this->returnReason($document, $history),
            'status_from' => $history?->status_from,
            'status_to' => $history?->status_to ?? $document->status,
        ];
    }

    private function returnHistory(ProcurementDocument $document): ?DocumentRoutingHistory
    {
        $document->loadMissing(['routingHistories.actionBy', 'routingHistories.fromOffice', 'routingHistories.toOffice']);

        return $document->routingHistories
            ->first(fn (DocumentRoutingHistory $history) => in_array($history->status_to, self::RETURNED_STATUSES, true)
                || str_contains(strtolower($history->action), 'returned'));
    }

    private function returnReason(ProcurementDocument $document, ?DocumentRoutingHistory $history): string
    {
        $reason = match ($document->status) {
            ProcurementDocument::STATUS_RETURNED_BY_BUDGET => $document->budget_remarks,
            ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING => $document->accounting_remarks,
            ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT => $document->bac_secretariat_remarks ?? $document->pr_remarks,
            ProcurementDocument::STATUS_RETURNED_BY_PR_NUMBERING_STAFF => $document->pr_number_remarks,
            ProcurementDocument::STATUS_RETURNED_BY_BAC_MEMBER => $document->bac_member_remarks,
            ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR => $document->bac_chair_confirmation_remarks ?? $document->bac_chair_remarks,
            ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY => $document->approval_remarks,
            ProcurementDocument::STATUS_PO_RETURNED => $document->purchaseOrder?->remarks ?? $document->remarks,
            default => null,
        };

        return $reason
            ?? $document->remarks
            ?? $document->approval_remarks
            ?? $document->budget_remarks
            ?? $document->accounting_remarks
            ?? $document->bac_secretariat_remarks
            ?? $document->bac_member_remarks
            ?? $document->bac_chair_confirmation_remarks
            ?? $document->bac_chair_remarks
            ?? $document->pr_remarks
            ?? $document->pr_number_remarks
            ?? $history?->comments
            ?? 'No return reason recorded.';
    }

    private function returnedByFallback(ProcurementDocument $document): string
    {
        return match ($document->status) {
            ProcurementDocument::STATUS_RETURNED_BY_BUDGET => $document->budgetReviewedBy?->name ?? 'Budget Office',
            ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING => $document->accountingReviewedBy?->name ?? 'Accounting Office',
            ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT => $document->prValidatedBy?->name ?? $document->bacSecretariatReceivedBy?->name ?? 'BAC Secretariat',
            ProcurementDocument::STATUS_RETURNED_BY_PR_NUMBERING_STAFF => $document->prNoAssignedBy?->name ?? 'PR Numbering Staff',
            ProcurementDocument::STATUS_RETURNED_BY_BAC_MEMBER => $document->bacMemberReviewedBy?->name ?? 'BAC Member',
            ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR => $document->bacChairReviewedBy?->name ?? 'BAC Chair',
            ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY => $document->approvedBy?->name ?? 'Head of the Procuring Entity',
            ProcurementDocument::STATUS_PO_RETURNED => $document->purchaseOrder?->issuedBy?->name ?? 'Purchase Order Processing',
            ProcurementDocument::STATUS_PPMP_SIGNATURE_RETURNED => 'Signatories',
            default => 'N/A',
        };
    }

    private function returnedDateFallback(ProcurementDocument $document)
    {
        return match ($document->status) {
            ProcurementDocument::STATUS_RETURNED_BY_BUDGET => $document->budget_reviewed_at,
            ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING => $document->accounting_reviewed_at,
            ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT => $document->bac_secretariat_processed_at ?? $document->pr_validated_at,
            ProcurementDocument::STATUS_RETURNED_BY_PR_NUMBERING_STAFF => $document->returned_at,
            ProcurementDocument::STATUS_RETURNED_BY_BAC_MEMBER => $document->bac_member_reviewed_at,
            ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR => $document->bac_chair_reviewed_at,
            ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY => $document->returned_by_approving_authority_at,
            ProcurementDocument::STATUS_PO_RETURNED => $document->purchaseOrder?->returned_at ?? $document->returned_at,
            default => $document->returned_at,
        } ?? $document->updated_at;
    }

    private function sourceLabel(?string $status): string
    {
        return match ($status) {
            ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT => 'BAC Secretariat',
            ProcurementDocument::STATUS_RETURNED_BY_PR_NUMBERING_STAFF => 'PR Numbering Staff',
            ProcurementDocument::STATUS_RETURNED_BY_BUDGET => 'Budget Office',
            ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING => 'Accounting Office',
            ProcurementDocument::STATUS_RETURNED_BY_BAC_MEMBER => 'BAC Member',
            ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR => 'BAC Chair',
            ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY => 'Head of the Procuring Entity',
            ProcurementDocument::STATUS_PO_RETURNED => 'Purchase Order Processing',
            ProcurementDocument::STATUS_PPMP_SIGNATURE_RETURNED => 'Signatories',
            default => 'N/A',
        };
    }

    private function documentActivities(ProcurementDocument $document)
    {
        return AuditLog::query()
            ->where('auditable_type', ProcurementDocument::class)
            ->where('auditable_id', $document->id)
            ->latest()
            ->limit(8)
            ->get();
    }
}
