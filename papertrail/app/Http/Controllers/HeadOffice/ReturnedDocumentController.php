<?php

namespace App\Http\Controllers\HeadOffice;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DocumentRoutingHistory;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class ReturnedDocumentController extends Controller
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
    ];

    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('head-office.documents.index', array_merge($request->query(), [
            'tab' => 'returned',
        ]));
    }

    public function show(Request $request, ProcurementDocument $document): View
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('Head Office Returned Documents', 'Unauthorized Returned Documents Access Attempt', 'Head of Office attempted to access a returned document outside their office scope.', $document, null, null, 'warning');
            abort(403);
        }

        AuditLogger::log('Head Office Returned Documents', 'Returned Document Detail Viewed', 'Head of Office viewed returned document details.', $document);

        $document->load([
            'submittingOffice',
            'submittedBy',
            'preparedBy',
            'currentOffice',
            'assignedTo',
            'budgetReviewedBy',
            'accountingReviewedBy',
            'bacSecretariatReceivedBy',
            'bacMemberReviewedBy',
            'bacChairReviewedBy',
            'approvedBy',
            'prValidatedBy',
            'prNoAssignedBy',
            'ppmpItems',
            'purchaseRequestItems.appItem',
            'purchaseOrder.items',
            'purchaseOrder.preparedBy',
            'purchaseOrder.issuedBy',
            'attachments.uploadedBy',
            'routingHistories.actionBy',
            'routingHistories.fromOffice',
            'routingHistories.toOffice',
        ]);

        return view('head-office.returned.show', [
            'document' => $document,
            'returnMeta' => $this->returnMeta($document),
            'activities' => $this->documentActivities($document),
            'revision' => $this->revisionTarget($document),
        ]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();

            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('tracking_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%");
            });
        });

        foreach (['fiscal_year'] as $field) {
            $query->when($request->filled($field), fn (Builder $builder) => $builder->where($field, $request->input($field)));
        }

        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', fn (Builder $builder) => $builder->where('status', $request->input('status')));

        $query->when($request->filled('document_type'), function (Builder $builder) use ($request): void {
            $documentType = (string) $request->input('document_type');
            $groups = [
                'pr' => ['PR', 'Purchase Request'],
            ];

            $builder->whereIn('document_type', $groups[strtolower($documentType)] ?? [$documentType]);
        });

        $query->when($request->filled('return_source'), function (Builder $builder) use ($request) {
            $statuses = self::SOURCE_STATUS_MAP[$request->input('return_source')] ?? [];

            if ($statuses !== []) {
                $builder->whereIn('status', $statuses);
            }
        });

        $query->when($request->filled('date_from'), function (Builder $builder) use ($request) {
            $date = $request->date('date_from');
            $builder->where(function (Builder $dateQuery) use ($date) {
                $dateQuery->whereDate('returned_at', '>=', $date)
                    ->orWhereDate('returned_by_approving_authority_at', '>=', $date)
                    ->orWhereHas('routingHistories', fn (Builder $history) => $history->whereIn('status_to', self::RETURNED_STATUSES)->whereDate('action_at', '>=', $date))
                    ->orWhereDate('updated_at', '>=', $date);
            });
        });

        $query->when($request->filled('date_to'), function (Builder $builder) use ($request) {
            $date = $request->date('date_to');
            $builder->where(function (Builder $dateQuery) use ($date) {
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
        return $this->returnedDocumentsQuery($user)->whereKey($document->id)->exists();
    }

    private function summary(User $user): array
    {
        $base = $this->returnedDocumentsQuery($user);

        return [
            'total' => (clone $base)->count(),
            'ppmp' => (clone $base)->where('document_type', 'PPMP')->count(),
            'pr' => (clone $base)->whereIn('document_type', ['PR', 'Purchase Request'])->count(),
            'thisMonth' => (clone $base)
                ->where(function (Builder $query) {
                    $query->whereMonth('returned_at', now()->month)->whereYear('returned_at', now()->year)
                        ->orWhereMonth('returned_by_approving_authority_at', now()->month)->whereYear('returned_by_approving_authority_at', now()->year)
                        ->orWhereHas('routingHistories', function (Builder $history) {
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
            default => 'N/A',
        };
    }

    private function revisionTarget(ProcurementDocument $document): ?array
    {
        if ($document->document_type === 'PPMP'
            && $document->status === ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT
            && Route::has('head-office.ppmp.edit')) {
            return [
                'label' => 'Revise PPMP',
                'url' => route('head-office.ppmp.edit', $document),
            ];
        }

        $prEditRoutes = [
            'head-office.pr.edit',
            'head-office.purchase-requests.edit',
        ];

        if (in_array($document->document_type, ['PR', 'Purchase Request'], true)
            && in_array($document->status, [
                ProcurementDocument::STATUS_RETURNED_BY_PR_NUMBERING_STAFF,
                ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
            ], true)) {
            foreach ($prEditRoutes as $route) {
                if (Route::has($route)) {
                    return [
                        'label' => 'Revise Purchase Request',
                        'url' => route($route, $document),
                    ];
                }
            }
        }

        return null;
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
