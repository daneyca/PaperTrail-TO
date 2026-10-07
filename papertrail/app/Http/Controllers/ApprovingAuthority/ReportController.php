<?php

namespace App\Http\Controllers\ApprovingAuthority;

use App\Http\Controllers\Controller;
use App\Models\DocumentApproval;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SystemSettingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        AuditLogger::log('Head of the Procuring Entity Reports', $request->query() ? 'Head of the Procuring Entity Report Filtered' : 'Head of the Procuring Entity Reports Page Viewed', 'Head of the Procuring Entity viewed reports.', null, null, null, 'info', [
            'filters' => $this->filterInputs($request),
        ]);

        return view('approving-authority.reports.index', $this->reportData($request));
    }

    public function export(Request $request): StreamedResponse
    {
        AuditLogger::log('Head of the Procuring Entity Reports', 'Head of the Procuring Entity Report Exported', 'Head of the Procuring Entity exported reports.', null, null, null, 'info', [
            'filters' => $this->filterInputs($request),
        ]);

        $rows = $this->filteredReportQuery($request)->limit(1000)->get();
        $filename = 'papertrail-approving-authority-reports-' . now()->format('Y-m-d') . '.csv';

        return Response::streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $this->headers());

            foreach ($rows as $document) {
                fputcsv($handle, $this->mapRow($document));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function print(Request $request): View
    {
        AuditLogger::log('Head of the Procuring Entity Reports', 'Head of the Procuring Entity Report Printed', 'Head of the Procuring Entity opened print report.', null, null, null, 'info', [
            'filters' => $this->filterInputs($request),
        ]);

        return view('approving-authority.reports.print', $this->reportData($request) + [
            'lguName' => SystemSettingService::get('lgu.name', 'Local Government Unit'),
            'generatedBy' => $request->user()->name,
            'generatedAt' => now(),
        ]);
    }

    private function reportData(Request $request): array
    {
        $rows = $this->filteredReportQuery($request)->get();
        $baseForOptions = $this->baseReportQuery($request->user());
        $reportType = $request->input('report_type', 'all');

        return [
            'documents' => $this->filteredReportQuery($request)->paginate(10)->withQueryString(),
            'summary' => $this->summary($rows),
            'pendingApprovals' => $this->enabled($reportType, ['all', 'pending']) ? $this->pendingApprovals($rows) : collect(),
            'approvedDocuments' => $this->enabled($reportType, ['all', 'approved']) ? $this->approvedDocuments($rows) : collect(),
            'returnedDocuments' => $this->enabled($reportType, ['all', 'returned']) ? $this->returnedDocuments($rows) : collect(),
            'completedDocuments' => $this->enabled($reportType, ['all', 'completed']) ? $this->completedDocuments($rows) : collect(),
            'documentTypeSummary' => $this->enabled($reportType, ['all', 'document_type']) ? $this->documentTypeSummary($rows) : collect(),
            'officeSummary' => $this->enabled($reportType, ['all', 'office']) ? $this->officeSummary($rows) : collect(),
            'monthlyActivity' => $this->enabled($reportType, ['all', 'monthly']) ? $this->monthlyActivity($rows) : collect(),
            'processing' => $this->processingTime($rows),
            'filters' => $this->filterInputs($request),
            'filterSummary' => $this->filterSummary($request),
            'documentTypes' => (clone $baseForOptions)->select('document_type')->distinct()->orderBy('document_type')->pluck('document_type'),
            'fiscalYears' => (clone $baseForOptions)->select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
            'offices' => Office::whereIn('id', (clone $baseForOptions)->select('submitting_office_id'))->orderBy('name')->get(),
            'statuses' => $this->statuses(),
            'outcomes' => [
                'pending' => 'Pending',
                'under_review' => 'Under Review',
                'approved' => 'Approved',
                'returned' => 'Returned',
                'deferred' => 'Deferred',
                'completed' => 'Completed',
            ],
            'reportTypes' => [
                'all' => 'All Reports',
                'pending' => 'Pending Approval',
                'approved' => 'Approved Documents',
                'returned' => 'Returned Documents',
                'completed' => 'Completed Documents',
                'monthly' => 'Monthly Activity',
                'office' => 'Office Summary',
                'document_type' => 'Document Type Summary',
            ],
            'headers' => $this->headers(),
            'exportRows' => $rows,
        ];
    }

    private function filteredReportQuery(Request $request): Builder
    {
        $query = $this->baseReportQuery($request->user())
            ->with([
                'submittingOffice',
                'currentOffice',
                'assignedTo',
                'approvedBy',
                'bacChairReviewedBy',
                'documentApprovals.approvedBy',
                'routingHistories.toOffice',
                'routingHistories.actionBy',
            ]);

        $this->applyFilters($query, $request);

        return $query->latest('updated_at');
    }

    private function baseReportQuery(User $user): Builder
    {
        return ProcurementDocument::query()
            ->where(function (Builder $scope) use ($user) {
                $scope->where('assigned_to_user_id', $user->id)
                    ->orWhere('approved_by_user_id', $user->id)
                    ->orWhereHas('documentApprovals', fn (Builder $approval) => $approval->where('approved_by_user_id', $user->id))
                    ->orWhereHas('routingHistories', fn (Builder $history) => $history->where('action_by_user_id', $user->id))
                    ->orWhere(function (Builder $officeScope) {
                        $officeScope->whereIn('status', $this->statuses())
                            ->whereHas('currentOffice', function (Builder $office) {
                                $office->where('code', 'OMM')
                                    ->orWhere('name', "Mayor's Office")
                                    ->orWhere('name', 'Office of the Municipal Mayor')
                                    ->orWhere('name', 'Head of the Procuring Entity')
                                    ->orWhere('name', 'Approving Authority');
                            });
                    });
            })
            ->where(function (Builder $workflow) {
                $workflow->whereIn('status', $this->statuses())
                    ->orWhereIn('approval_status', $this->approvalStatuses())
                    ->orWhereIn('approval_decision', $this->approvalDecisions());
            });
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        foreach ([
            'fiscal_year' => 'fiscal_year',
            'document_type' => 'document_type',
            'status' => 'status',
            'requesting_office' => 'submitting_office_id',
        ] as $input => $column) {
            $query->when($request->filled($input), fn (Builder $builder) => $builder->where($column, $request->input($input)));
        }

        $query->when($request->filled('outcome'), function (Builder $builder) use ($request) {
            match ($request->input('outcome')) {
                'pending' => $builder->where(fn (Builder $nested) => $nested->where('approval_status', DocumentApproval::STATUS_PENDING)->orWhere('status', ProcurementDocument::STATUS_PENDING_APPROVAL)),
                'under_review' => $builder->where(fn (Builder $nested) => $nested->where('approval_status', DocumentApproval::STATUS_UNDER_REVIEW)->orWhere('status', ProcurementDocument::STATUS_UNDER_APPROVAL)),
                'approved' => $builder->where(fn (Builder $nested) => $nested->where('approval_status', DocumentApproval::STATUS_APPROVED)->orWhere('approval_decision', DocumentApproval::DECISION_APPROVED)),
                'returned' => $builder->where(fn (Builder $nested) => $nested->where('approval_status', DocumentApproval::STATUS_RETURNED)->orWhere('approval_decision', 'like', 'returned_to_%')),
                'deferred' => $builder->where(fn (Builder $nested) => $nested->where('approval_status', DocumentApproval::STATUS_DEFERRED)->orWhere('status', ProcurementDocument::STATUS_APPROVAL_DEFERRED)),
                'completed' => $builder->whereIn('status', [ProcurementDocument::STATUS_PO_COMPLETED, 'completed']),
                default => null,
            };
        });

        $query->when($request->filled('report_type'), function (Builder $builder) use ($request) {
            match ($request->input('report_type')) {
                'pending' => $builder->whereIn('status', [ProcurementDocument::STATUS_PENDING_APPROVAL, ProcurementDocument::STATUS_UNDER_APPROVAL]),
                'approved' => $builder->where(function (Builder $nested) {
                    $nested->whereIn('status', $this->approvedStatuses())
                        ->orWhere('approval_status', DocumentApproval::STATUS_APPROVED)
                        ->orWhere('approval_decision', DocumentApproval::DECISION_APPROVED);
                }),
                'returned' => $builder->where(function (Builder $nested) {
                    $nested->where('status', ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY)
                        ->orWhere('approval_status', DocumentApproval::STATUS_RETURNED)
                        ->orWhere('approval_decision', 'like', 'returned_to_%');
                }),
                'completed' => $builder->whereIn('status', [ProcurementDocument::STATUS_PO_COMPLETED, 'completed']),
                default => null,
            };
        });

        $query->when($request->filled('date_from'), function (Builder $builder) use ($request) {
            $date = $request->date('date_from');
            $builder->where(function (Builder $nested) use ($date) {
                $nested->whereDate('approval_started_at', '>=', $date)
                    ->orWhereDate('approved_at', '>=', $date)
                    ->orWhereDate('returned_by_approving_authority_at', '>=', $date)
                    ->orWhereHas('documentApprovals', fn (Builder $approval) => $approval->whereDate('completed_at', '>=', $date))
                    ->orWhereHas('routingHistories', fn (Builder $history) => $history->whereDate('action_at', '>=', $date));
            });
        });

        $query->when($request->filled('date_to'), function (Builder $builder) use ($request) {
            $date = $request->date('date_to');
            $builder->where(function (Builder $nested) use ($date) {
                $nested->whereDate('approval_started_at', '<=', $date)
                    ->orWhereDate('approved_at', '<=', $date)
                    ->orWhereDate('returned_by_approving_authority_at', '<=', $date)
                    ->orWhereHas('documentApprovals', fn (Builder $approval) => $approval->whereDate('completed_at', '<=', $date))
                    ->orWhereHas('routingHistories', fn (Builder $history) => $history->whereDate('action_at', '<=', $date));
            });
        });
    }

    private function summary(Collection $documents): array
    {
        return [
            'pending' => $documents->where('status', ProcurementDocument::STATUS_PENDING_APPROVAL)->count(),
            'underReview' => $documents->where('status', ProcurementDocument::STATUS_UNDER_APPROVAL)->count(),
            'approved' => $documents->filter(fn (ProcurementDocument $document) => $this->isApproved($document))->count(),
            'returned' => $documents->filter(fn (ProcurementDocument $document) => $this->isReturned($document))->count(),
            'completed' => $documents->filter(fn (ProcurementDocument $document) => $this->isCompleted($document))->count(),
            'amountApproved' => (float) $documents->filter(fn (ProcurementDocument $document) => $this->isApproved($document))->sum('total_amount'),
        ];
    }

    private function pendingApprovals(Collection $documents): Collection
    {
        return $documents->whereIn('status', [ProcurementDocument::STATUS_PENDING_APPROVAL, ProcurementDocument::STATUS_UNDER_APPROVAL])->values();
    }

    private function approvedDocuments(Collection $documents): Collection
    {
        return $documents->filter(fn (ProcurementDocument $document) => $this->isApproved($document))->values();
    }

    private function returnedDocuments(Collection $documents): Collection
    {
        return $documents->filter(fn (ProcurementDocument $document) => $this->isReturned($document))->values();
    }

    private function completedDocuments(Collection $documents): Collection
    {
        return $documents->filter(fn (ProcurementDocument $document) => $this->isCompleted($document))->values();
    }

    private function documentTypeSummary(Collection $documents): Collection
    {
        return $documents
            ->groupBy(fn (ProcurementDocument $document) => $document->document_type ?: 'Unspecified')
            ->map(fn (Collection $group, string $type) => [
                'type' => $type,
                'total' => $group->count(),
                'pending' => $this->pendingApprovals($group)->count(),
                'approved' => $this->approvedDocuments($group)->count(),
                'returned' => $this->returnedDocuments($group)->count(),
                'completed' => $this->completedDocuments($group)->count(),
                'amount' => (float) $group->sum('total_amount'),
            ])
            ->sortBy('type')
            ->values();
    }

    private function officeSummary(Collection $documents): Collection
    {
        return $documents
            ->groupBy(fn (ProcurementDocument $document) => $document->submittingOffice?->name ?? 'Unassigned Office')
            ->map(fn (Collection $group, string $office) => [
                'office' => $office,
                'total' => $group->count(),
                'pending' => $this->pendingApprovals($group)->count(),
                'approved' => $this->approvedDocuments($group)->count(),
                'returned' => $this->returnedDocuments($group)->count(),
                'completed' => $this->completedDocuments($group)->count(),
                'amountApproved' => (float) $this->approvedDocuments($group)->sum('total_amount'),
            ])
            ->sortByDesc('total')
            ->values();
    }

    private function monthlyActivity(Collection $documents): Collection
    {
        return $documents
            ->filter(fn (ProcurementDocument $document) => $this->activityDate($document) !== null)
            ->groupBy(fn (ProcurementDocument $document) => $this->activityDate($document)?->format('Y-m'))
            ->map(function (Collection $group, string $month) {
                return [
                    'month' => Carbon::createFromFormat('Y-m', $month)->format('M Y'),
                    'received' => $group->whereIn('status', [ProcurementDocument::STATUS_PENDING_APPROVAL, ProcurementDocument::STATUS_UNDER_APPROVAL])->count(),
                    'started' => $group->filter(fn (ProcurementDocument $document) => $document->approval_started_at !== null)->count(),
                    'approved' => $this->approvedDocuments($group)->count(),
                    'returned' => $this->returnedDocuments($group)->count(),
                    'deferred' => $group->filter(fn (ProcurementDocument $document) => $document->approval_status === DocumentApproval::STATUS_DEFERRED || $document->status === ProcurementDocument::STATUS_APPROVAL_DEFERRED)->count(),
                    'completed' => $this->completedDocuments($group)->count(),
                    'amountApproved' => (float) $this->approvedDocuments($group)->sum('total_amount'),
                ];
            })
            ->values();
    }

    private function processingTime(Collection $documents): array
    {
        $started = $documents->filter(fn (ProcurementDocument $document) => $this->forwardedDate($document) && $document->approval_started_at);
        $approved = $documents->filter(fn (ProcurementDocument $document) => $document->approval_started_at && $document->approved_at && $this->isApproved($document));
        $returned = $documents->filter(fn (ProcurementDocument $document) => $this->forwardedDate($document) && $document->returned_by_approving_authority_at && $this->isReturned($document));
        $pending = $this->pendingApprovals($documents);

        return [
            'hasData' => $started->isNotEmpty() || $approved->isNotEmpty() || $returned->isNotEmpty() || $pending->isNotEmpty(),
            'forwardedToStart' => $this->averageDays($started, fn (ProcurementDocument $document) => $this->forwardedDate($document)->diffInDays($document->approval_started_at)),
            'startToApproval' => $this->averageDays($approved, fn (ProcurementDocument $document) => $document->approval_started_at->diffInDays($document->approved_at)),
            'forwardedToReturn' => $this->averageDays($returned, fn (ProcurementDocument $document) => $this->forwardedDate($document)->diffInDays($document->returned_by_approving_authority_at)),
            'longestPending' => $pending->sortByDesc(fn (ProcurementDocument $document) => ($this->forwardedDate($document) ?? $document->updated_at)?->diffInDays(now()) ?? 0)->first(),
            'overThreeDays' => $pending->filter(fn (ProcurementDocument $document) => (($this->forwardedDate($document) ?? $document->updated_at)?->diffInDays(now()) ?? 0) > 3)->count(),
        ];
    }

    private function averageDays(Collection $documents, callable $callback): ?float
    {
        return $documents->isEmpty() ? null : round($documents->map($callback)->average(), 1);
    }

    private function filterInputs(Request $request): array
    {
        return $request->only(['fiscal_year', 'report_type', 'document_type', 'status', 'outcome', 'requesting_office', 'date_from', 'date_to']);
    }

    private function filterSummary(Request $request): array
    {
        $filters = [];

        foreach ([
            'fiscal_year' => 'Fiscal Year',
            'report_type' => 'Report Type',
            'document_type' => 'Document Type',
            'status' => 'Current Status',
            'outcome' => 'Approval Outcome',
            'date_from' => 'Date From',
            'date_to' => 'Date To',
        ] as $key => $label) {
            if ($request->filled($key)) {
                $filters[$label] = str((string) $request->input($key))->replace('_', ' ')->title()->toString();
            }
        }

        if ($request->filled('requesting_office')) {
            $filters['Requesting Office'] = Office::find($request->input('requesting_office'))?->name ?? $request->input('requesting_office');
        }

        return $filters ?: ['Filters' => 'All Head of the Procuring Entity report records'];
    }

    private function headers(): array
    {
        return ['Report Type', 'Tracking Number', 'Document Type', 'Title', 'Requesting Office', 'Total Amount', 'Approval Status', 'Approval Decision', 'Outcome', 'Current Status', 'Current Stage', 'Current Office', 'Approved By', 'Approved Date', 'Returned To', 'Return Reason', 'Remarks'];
    }

    public function mapRow(ProcurementDocument $document): array
    {
        return [
            $this->reportType($document),
            $document->tracking_number,
            $document->document_type,
            $document->title ?? $document->purpose,
            $document->submittingOffice?->name ?? 'N/A',
            number_format((float) $document->total_amount, 2, '.', ''),
            $this->label($document->approval_status),
            $this->label($document->approval_decision),
            $this->outcome($document),
            $this->label($document->status),
            $document->stage ?? 'N/A',
            $document->currentOffice?->name ?? 'N/A',
            $document->approvedBy?->name ?? $this->latestApproval($document)?->approvedBy?->name ?? 'N/A',
            $document->approved_at?->format('Y-m-d H:i') ?? $this->latestApproval($document)?->completed_at?->format('Y-m-d H:i') ?? 'N/A',
            $this->returnTarget($document),
            $document->approval_remarks ?? $this->latestApproval($document)?->remarks ?? 'N/A',
            $document->remarks ?? $document->approval_remarks ?? 'N/A',
        ];
    }

    public function reportType(ProcurementDocument $document): string
    {
        if ($this->isCompleted($document)) {
            return 'Completed Documents';
        }

        if ($this->isReturned($document)) {
            return 'Returned Documents';
        }

        if ($this->isApproved($document)) {
            return 'Approved Documents';
        }

        return 'Pending Approval';
    }

    public function outcome(ProcurementDocument $document): string
    {
        if ($this->isCompleted($document)) {
            return 'Completed';
        }

        if ($this->isReturned($document)) {
            return 'Returned';
        }

        if ($document->approval_status === DocumentApproval::STATUS_DEFERRED || $document->status === ProcurementDocument::STATUS_APPROVAL_DEFERRED) {
            return 'Deferred';
        }

        if ($this->isApproved($document)) {
            return 'Approved';
        }

        return $document->status === ProcurementDocument::STATUS_UNDER_APPROVAL || $document->approval_status === DocumentApproval::STATUS_UNDER_REVIEW ? 'Under Review' : 'Pending';
    }

    public function returnTarget(ProcurementDocument $document): string
    {
        return match ($document->approval_decision) {
            DocumentApproval::DECISION_RETURNED_TO_BAC_CHAIR => 'BAC Chair',
            DocumentApproval::DECISION_RETURNED_TO_BAC_SECRETARIAT => 'BAC Secretariat',
            DocumentApproval::DECISION_RETURNED_TO_ACCOUNTING => 'Accounting Office',
            DocumentApproval::DECISION_RETURNED_TO_REQUESTING_OFFICE => 'Requesting Office',
            default => $this->isReturned($document) ? ($document->currentOffice?->name ?? 'Returned Target') : 'N/A',
        };
    }

    public function forwardedDate(ProcurementDocument $document): ?Carbon
    {
        return $document->routingHistories
            ->first(fn ($history) => in_array($history->status_to, [ProcurementDocument::STATUS_PENDING_APPROVAL, ProcurementDocument::STATUS_UNDER_APPROVAL], true) || str_contains(strtolower($history->action), 'approving authority'))
            ?->action_at
            ?? $document->routed_at
            ?? $document->updated_at;
    }

    public function latestApproval(ProcurementDocument $document): ?DocumentApproval
    {
        return $document->documentApprovals->sortByDesc(fn (DocumentApproval $approval) => $approval->completed_at ?? $approval->created_at)->first();
    }

    public function label(?string $value): string
    {
        return $value ? str($value)->replace('_', ' ')->title()->toString() : 'N/A';
    }

    private function isApproved(ProcurementDocument $document): bool
    {
        return in_array($document->status, $this->approvedStatuses(), true)
            || $document->approval_status === DocumentApproval::STATUS_APPROVED
            || $document->approval_decision === DocumentApproval::DECISION_APPROVED;
    }

    private function isReturned(ProcurementDocument $document): bool
    {
        return $document->status === ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY
            || $document->approval_status === DocumentApproval::STATUS_RETURNED
            || str_starts_with((string) $document->approval_decision, 'returned_to_');
    }

    private function isCompleted(ProcurementDocument $document): bool
    {
        return in_array($document->status, [ProcurementDocument::STATUS_PO_COMPLETED, 'completed'], true);
    }

    private function activityDate(ProcurementDocument $document): ?Carbon
    {
        return $document->approved_at
            ?? $document->returned_by_approving_authority_at
            ?? $document->approval_started_at
            ?? $this->latestApproval($document)?->completed_at
            ?? $document->routingHistories->first()?->action_at
            ?? $document->updated_at;
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

    private function statuses(): array
    {
        return [
            ProcurementDocument::STATUS_PENDING_APPROVAL,
            ProcurementDocument::STATUS_UNDER_APPROVAL,
            ProcurementDocument::STATUS_APPROVED,
            ProcurementDocument::STATUS_APP_APPROVED,
            ProcurementDocument::STATUS_READY_FOR_PO,
            ProcurementDocument::STATUS_PO_APPROVED,
            ProcurementDocument::STATUS_PO_COMPLETED,
            'completed',
            ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY,
            ProcurementDocument::STATUS_APPROVAL_DEFERRED,
        ];
    }

    private function approvalStatuses(): array
    {
        return [
            DocumentApproval::STATUS_PENDING,
            DocumentApproval::STATUS_UNDER_REVIEW,
            DocumentApproval::STATUS_APPROVED,
            DocumentApproval::STATUS_RETURNED,
            DocumentApproval::STATUS_DEFERRED,
        ];
    }

    private function approvalDecisions(): array
    {
        return [
            DocumentApproval::DECISION_APPROVED,
            DocumentApproval::DECISION_RETURNED_TO_BAC_CHAIR,
            DocumentApproval::DECISION_RETURNED_TO_BAC_SECRETARIAT,
            DocumentApproval::DECISION_RETURNED_TO_ACCOUNTING,
            DocumentApproval::DECISION_RETURNED_TO_REQUESTING_OFFICE,
            'deferred',
        ];
    }

    private function enabled(string $selected, array $types): bool
    {
        return in_array($selected, $types, true);
    }
}
