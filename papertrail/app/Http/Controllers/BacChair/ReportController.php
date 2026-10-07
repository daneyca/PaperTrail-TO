<?php

namespace App\Http\Controllers\BacChair;

use App\Http\Controllers\Controller;
use App\Models\BacChairReview;
use App\Models\BacDeliberation;
use App\Models\BacMemberReview;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SystemSettingService;
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
        AuditLogger::log('BAC Chair Reports', $request->query() ? 'BAC Chair Report Filtered' : 'BAC Chair Reports Page Viewed', 'BAC Chair viewed reports.', null, null, null, 'info', [
            'filters' => $this->filterInputs($request),
        ]);

        return view('bac-chair.reports.index', $this->reportData($request));
    }

    public function export(Request $request): StreamedResponse
    {
        AuditLogger::log('BAC Chair Reports', 'BAC Chair Report Exported', 'BAC Chair exported reports.', null, null, null, 'info', [
            'filters' => $this->filterInputs($request),
        ]);

        $rows = $this->filteredReportQuery($request)->limit(1000)->get();
        $filename = 'papertrail-bac-chair-reports-' . now()->format('Y-m-d') . '.csv';

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
        AuditLogger::log('BAC Chair Reports', 'BAC Chair Report Printed', 'BAC Chair opened print report.', null, null, null, 'info', [
            'filters' => $this->filterInputs($request),
        ]);

        return view('bac-chair.reports.print', $this->reportData($request) + [
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
        $deliberations = $this->deliberations($request, $rows);

        return [
            'documents' => $this->filteredReportQuery($request)->paginate(10)->withQueryString(),
            'summary' => $this->summary($rows),
            'approvals' => $this->enabled($reportType, ['all', 'approvals']) ? $this->approvals($rows) : collect(),
            'confirmations' => $this->enabled($reportType, ['all', 'confirmations']) ? $this->confirmations($rows) : collect(),
            'forwarded' => $this->enabled($reportType, ['all', 'forwarded']) ? $this->forwarded($rows) : collect(),
            'returned' => $this->enabled($reportType, ['all', 'returned']) ? $this->returned($rows) : collect(),
            'deliberations' => $this->enabled($reportType, ['all', 'deliberations']) ? $deliberations : collect(),
            'memberRecommendations' => $this->enabled($reportType, ['all', 'deliberations']) ? $this->memberRecommendations($rows) : collect(),
            'monthlyActivity' => $this->enabled($reportType, ['all', 'monthly']) ? $this->monthlyActivity($rows) : collect(),
            'officeSummary' => $this->enabled($reportType, ['all', 'office']) ? $this->officeSummary($rows) : collect(),
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
                'confirmed' => 'Confirmed',
                'forwarded' => 'Forwarded',
                'returned' => 'Returned',
                'approved' => 'Approved',
            ],
            'reportTypes' => [
                'all' => 'All Reports',
                'approvals' => 'BAC Approvals',
                'confirmations' => 'Documents for Confirmation',
                'reviewed' => 'Reviewed Documents',
                'returned' => 'Returned Documents',
                'forwarded' => 'Forwarded to Head of the Procuring Entity',
                'deliberations' => 'BAC Deliberations',
                'monthly' => 'Monthly Activity',
                'office' => 'Office Summary',
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
                'bacChairReviewedBy',
                'bacChairReviews.reviewedBy',
                'bacMemberReviews.reviewedBy',
                'bacDeliberations.participants.user',
                'routingHistories.toOffice',
                'routingHistories.actionBy',
            ]);

        $this->applyFilters($query, $request);

        return $query->latest('updated_at');
    }

    private function baseReportQuery(User $user): Builder
    {
        return ProcurementDocument::query()
            ->where(function (Builder $query) use ($user) {
                $query->where('assigned_to_user_id', $user->id)
                    ->orWhere('bac_chair_reviewed_by_user_id', $user->id)
                    ->orWhereHas('bacChairReviews', fn (Builder $review) => $review->where('reviewed_by_user_id', $user->id))
                    ->orWhereHas('routingHistories', fn (Builder $history) => $history->where('action_by_user_id', $user->id))
                    ->orWhere(function (Builder $bacOffice) {
                        $bacOffice->whereIn('status', $this->statuses())
                            ->whereHas('currentOffice', function (Builder $office) {
                                $office->where('code', 'BAC')
                                    ->orWhere('name', 'Bids and Awards Committee');
                            });
                    });
            })
            ->where(function (Builder $query) {
                $query->whereIn('status', $this->statuses())
                    ->orWhereIn('bac_chair_status', [
                        BacChairReview::STATUS_PENDING,
                        BacChairReview::STATUS_UNDER_REVIEW,
                        BacChairReview::STATUS_CONFIRMED,
                        BacChairReview::STATUS_RETURNED,
                    ])
                    ->orWhereIn('bac_chair_confirmation_status', ['pending_confirmation', 'confirmed', 'returned', 'deferred']);
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
                'pending' => $builder->where('status', ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW),
                'under_review' => $builder->whereIn('status', [ProcurementDocument::STATUS_UNDER_BAC_CHAIR_REVIEW, ProcurementDocument::STATUS_READY_FOR_BAC_CHAIR_CONFIRMATION]),
                'confirmed' => $builder->where('bac_chair_status', BacChairReview::STATUS_CONFIRMED),
                'forwarded' => $builder->where('status', ProcurementDocument::STATUS_PENDING_APPROVAL),
                'returned' => $builder->where('status', ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR),
                'approved' => $builder->where('status', 'approved'),
                default => null,
            };
        });

        $query->when($request->filled('report_type'), function (Builder $builder) use ($request) {
            match ($request->input('report_type')) {
                'approvals' => $builder->whereIn('status', [ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW, ProcurementDocument::STATUS_UNDER_BAC_CHAIR_REVIEW]),
                'confirmations' => $builder->whereIn('status', [ProcurementDocument::STATUS_UNDER_BAC_CHAIR_REVIEW, ProcurementDocument::STATUS_READY_FOR_BAC_CHAIR_CONFIRMATION]),
                'reviewed' => $builder->whereNotNull('bac_chair_reviewed_at'),
                'returned' => $builder->where('status', ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR),
                'forwarded' => $builder->where('status', ProcurementDocument::STATUS_PENDING_APPROVAL),
                default => null,
            };
        });

        $query->when($request->filled('date_from'), function (Builder $builder) use ($request) {
            $date = $request->date('date_from');
            $builder->where(function (Builder $nested) use ($date) {
                $nested->whereDate('bac_chair_review_started_at', '>=', $date)
                    ->orWhereDate('bac_chair_reviewed_at', '>=', $date)
                    ->orWhereDate('bac_chair_confirmed_at', '>=', $date)
                    ->orWhereHas('routingHistories', fn (Builder $history) => $history->whereDate('action_at', '>=', $date));
            });
        });

        $query->when($request->filled('date_to'), function (Builder $builder) use ($request) {
            $date = $request->date('date_to');
            $builder->where(function (Builder $nested) use ($date) {
                $nested->whereDate('bac_chair_review_started_at', '<=', $date)
                    ->orWhereDate('bac_chair_reviewed_at', '<=', $date)
                    ->orWhereDate('bac_chair_confirmed_at', '<=', $date)
                    ->orWhereHas('routingHistories', fn (Builder $history) => $history->whereDate('action_at', '<=', $date));
            });
        });
    }

    private function summary(Collection $documents): array
    {
        return [
            'pendingApprovals' => $documents->where('status', ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW)->count(),
            'forConfirmation' => $documents->whereIn('status', [ProcurementDocument::STATUS_UNDER_BAC_CHAIR_REVIEW, ProcurementDocument::STATUS_READY_FOR_BAC_CHAIR_CONFIRMATION])->count(),
            'confirmed' => $documents->filter(fn (ProcurementDocument $document) => $this->isConfirmed($document))->count(),
            'returned' => $documents->filter(fn (ProcurementDocument $document) => $this->isReturned($document))->count(),
            'forwarded' => $documents->where('status', ProcurementDocument::STATUS_PENDING_APPROVAL)->count(),
            'reviewedThisMonth' => $documents->filter(fn (ProcurementDocument $document) => $this->activityDate($document)?->isSameMonth(now()))->count(),
        ];
    }

    private function approvals(Collection $documents): Collection
    {
        return $documents->whereIn('status', [ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW, ProcurementDocument::STATUS_UNDER_BAC_CHAIR_REVIEW])->values();
    }

    private function confirmations(Collection $documents): Collection
    {
        return $documents->whereIn('status', [ProcurementDocument::STATUS_UNDER_BAC_CHAIR_REVIEW, ProcurementDocument::STATUS_READY_FOR_BAC_CHAIR_CONFIRMATION])->values();
    }

    private function forwarded(Collection $documents): Collection
    {
        return $documents->filter(fn (ProcurementDocument $document) => $this->isConfirmed($document))->values();
    }

    private function returned(Collection $documents): Collection
    {
        return $documents->filter(fn (ProcurementDocument $document) => $this->isReturned($document))->values();
    }

    private function deliberations(Request $request, Collection $documents): Collection
    {
        $ids = $documents->pluck('id')->all();

        if (!$ids) {
            return collect();
        }

        return BacDeliberation::with(['procurementDocument.submittingOffice', 'participants.user', 'chair'])
            ->whereIn('procurement_document_id', $ids)
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('updated_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('updated_at', '<=', $request->date('date_to')))
            ->latest('updated_at')
            ->get();
    }

    private function memberRecommendations(Collection $documents): Collection
    {
        return $documents
            ->flatMap(fn (ProcurementDocument $document) => $document->bacMemberReviews->map(fn (BacMemberReview $review) => ['document' => $document, 'review' => $review]))
            ->values();
    }

    private function monthlyActivity(Collection $documents): Collection
    {
        return $documents
            ->filter(fn (ProcurementDocument $document) => $this->activityDate($document) !== null)
            ->groupBy(fn (ProcurementDocument $document) => $this->activityDate($document)?->format('Y-m'))
            ->map(function (Collection $group, string $month) {
                return [
                    'month' => \Carbon\Carbon::createFromFormat('Y-m', $month)->format('M Y'),
                    'received' => $group->where('status', ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW)->count(),
                    'started' => $group->filter(fn (ProcurementDocument $document) => $document->bac_chair_review_started_at !== null)->count(),
                    'confirmed' => $group->filter(fn (ProcurementDocument $document) => $this->isConfirmed($document))->count(),
                    'returned' => $group->filter(fn (ProcurementDocument $document) => $this->isReturned($document))->count(),
                    'forwarded' => $group->where('status', ProcurementDocument::STATUS_PENDING_APPROVAL)->count(),
                    'pendingApproval' => $group->where('status', ProcurementDocument::STATUS_PENDING_APPROVAL)->count(),
                ];
            })
            ->values();
    }

    private function officeSummary(Collection $documents): Collection
    {
        return $documents
            ->groupBy(fn (ProcurementDocument $document) => $document->submittingOffice?->name ?? 'Unassigned Office')
            ->map(fn (Collection $group, string $office) => [
                'office' => $office,
                'total' => $group->count(),
                'pending' => $group->where('status', ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW)->count(),
                'underReview' => $group->whereIn('status', [ProcurementDocument::STATUS_UNDER_BAC_CHAIR_REVIEW, ProcurementDocument::STATUS_READY_FOR_BAC_CHAIR_CONFIRMATION])->count(),
                'confirmed' => $group->filter(fn (ProcurementDocument $document) => $this->isConfirmed($document))->count(),
                'returned' => $group->filter(fn (ProcurementDocument $document) => $this->isReturned($document))->count(),
                'forwarded' => $group->where('status', ProcurementDocument::STATUS_PENDING_APPROVAL)->count(),
                'amount' => (float) $group->sum('total_amount'),
            ])
            ->sortByDesc('total')
            ->values();
    }

    private function processingTime(Collection $documents): array
    {
        $started = $documents->filter(fn (ProcurementDocument $document) => $document->routed_at && $document->bac_chair_review_started_at);
        $confirmed = $documents->filter(fn (ProcurementDocument $document) => $document->bac_chair_review_started_at && ($document->bac_chair_confirmed_at || $document->bac_chair_reviewed_at) && $this->isConfirmed($document));
        $returned = $documents->filter(fn (ProcurementDocument $document) => $document->routed_at && $document->bac_chair_reviewed_at && $this->isReturned($document));
        $pending = $documents->filter(fn (ProcurementDocument $document) => in_array($document->status, [ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW, ProcurementDocument::STATUS_UNDER_BAC_CHAIR_REVIEW, ProcurementDocument::STATUS_READY_FOR_BAC_CHAIR_CONFIRMATION], true));

        return [
            'hasData' => $started->isNotEmpty() || $confirmed->isNotEmpty() || $returned->isNotEmpty(),
            'assignmentToStart' => $this->averageDays($started, fn (ProcurementDocument $document) => $document->routed_at->diffInDays($document->bac_chair_review_started_at)),
            'startToConfirmation' => $this->averageDays($confirmed, fn (ProcurementDocument $document) => $document->bac_chair_review_started_at->diffInDays($document->bac_chair_confirmed_at ?? $document->bac_chair_reviewed_at)),
            'assignmentToReturn' => $this->averageDays($returned, fn (ProcurementDocument $document) => $document->routed_at->diffInDays($document->bac_chair_reviewed_at)),
            'longestPending' => $pending->sortByDesc(fn (ProcurementDocument $document) => ($document->routed_at ?? $document->updated_at)?->diffInDays(now()) ?? 0)->first(),
            'overThreeDays' => $pending->filter(fn (ProcurementDocument $document) => (($document->routed_at ?? $document->updated_at)?->diffInDays(now()) ?? 0) > 3)->count(),
        ];
    }

    private function averageDays(Collection $documents, callable $callback): ?float
    {
        if ($documents->isEmpty()) {
            return null;
        }

        return round($documents->map($callback)->average(), 1);
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
            'outcome' => 'Outcome',
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

        return $filters ?: ['Filters' => 'All BAC Chair report records'];
    }

    private function headers(): array
    {
        return ['Report Type', 'Tracking Number', 'Document Type', 'Title', 'Requesting Office', 'Total Amount', 'BAC Member Recommendation', 'BAC Chair Status', 'Confirmation Status', 'Outcome', 'Current Status', 'Current Stage', 'Current Office', 'Reviewed By', 'Reviewed Date', 'Forwarded / Returned To', 'Remarks'];
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
            $this->memberRecommendation($document),
            str($document->bac_chair_status ?? 'N/A')->replace('_', ' ')->title()->toString(),
            str($document->bac_chair_confirmation_status ?? 'N/A')->replace('_', ' ')->title()->toString(),
            $this->outcome($document),
            str($document->status)->replace('_', ' ')->title()->toString(),
            $document->stage ?? 'N/A',
            $document->currentOffice?->name ?? 'N/A',
            $document->bacChairReviewedBy?->name ?? $document->bacChairReviews->sortByDesc(fn (BacChairReview $review) => $review->completed_at ?? $review->created_at)->first()?->reviewedBy?->name ?? 'N/A',
            $this->activityDate($document)?->format('Y-m-d H:i') ?? 'N/A',
            $this->destination($document),
            $document->bac_chair_confirmation_remarks ?? $document->bac_chair_remarks ?? 'N/A',
        ];
    }

    private function reportType(ProcurementDocument $document): string
    {
        if ($this->isReturned($document)) {
            return 'Returned Documents';
        }

        if ($this->isConfirmed($document)) {
            return 'Confirmed / Forwarded Documents';
        }

        if (in_array($document->status, [ProcurementDocument::STATUS_UNDER_BAC_CHAIR_REVIEW, ProcurementDocument::STATUS_READY_FOR_BAC_CHAIR_CONFIRMATION], true)) {
            return 'Documents for Confirmation';
        }

        return 'BAC Approvals';
    }

    public function outcome(ProcurementDocument $document): string
    {
        if ($document->status === 'approved') {
            return 'Approved';
        }

        if ($this->isReturned($document)) {
            return 'Returned';
        }

        if ($document->status === ProcurementDocument::STATUS_PENDING_APPROVAL || $this->isConfirmed($document)) {
            return 'Forwarded';
        }

        if (in_array($document->status, [ProcurementDocument::STATUS_UNDER_BAC_CHAIR_REVIEW, ProcurementDocument::STATUS_READY_FOR_BAC_CHAIR_CONFIRMATION], true)) {
            return 'Under Review';
        }

        return 'Pending';
    }

    public function destination(ProcurementDocument $document): string
    {
        return $document->routingHistories
            ->first(fn ($history) => in_array($history->action, ['Routed to Head of the Procuring Entity', 'Routed to Approving Authority', 'Returned by BAC Chair'], true))
            ?->toOffice?->name
            ?? $document->currentOffice?->name
            ?? 'N/A';
    }

    public function memberRecommendation(ProcurementDocument $document): string
    {
        $review = $document->bacMemberReviews->sortByDesc(fn (BacMemberReview $item) => $item->completed_at ?? $item->created_at)->first();

        return str($review?->recommendation ?? 'N/A')->replace('_', ' ')->title()->toString();
    }

    private function isConfirmed(ProcurementDocument $document): bool
    {
        return $document->status === ProcurementDocument::STATUS_PENDING_APPROVAL
            || $document->status === ProcurementDocument::STATUS_CONFIRMED_BY_BAC_CHAIR
            || $document->bac_chair_status === BacChairReview::STATUS_CONFIRMED
            || $document->bac_chair_confirmation_status === 'confirmed'
            || $document->status === 'approved';
    }

    private function isReturned(ProcurementDocument $document): bool
    {
        return $document->status === ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR
            || $document->bac_chair_status === BacChairReview::STATUS_RETURNED
            || $document->bac_chair_confirmation_status === 'returned';
    }

    private function activityDate(ProcurementDocument $document): ?\Carbon\Carbon
    {
        return $document->bac_chair_confirmed_at
            ?? $document->bac_chair_reviewed_at
            ?? $document->bac_chair_review_started_at
            ?? $document->routingHistories->first()?->action_at
            ?? $document->updated_at;
    }

    private function statuses(): array
    {
        return [
            ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW,
            ProcurementDocument::STATUS_UNDER_BAC_CHAIR_REVIEW,
            ProcurementDocument::STATUS_READY_FOR_BAC_CHAIR_CONFIRMATION,
            ProcurementDocument::STATUS_CONFIRMED_BY_BAC_CHAIR,
            ProcurementDocument::STATUS_PENDING_APPROVAL,
            ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR,
            'approved',
        ];
    }

    private function enabled(string $selected, array $types): bool
    {
        return in_array($selected, $types, true);
    }
}
