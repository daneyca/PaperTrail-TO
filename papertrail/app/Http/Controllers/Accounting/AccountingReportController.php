<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AccountingReview;
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

class AccountingReportController extends Controller
{
    public function index(Request $request): View
    {
        AuditLogger::log(
            'Accounting Reports',
            $request->query() ? 'Accounting Report Filtered/Viewed' : 'Accounting Reports Page Viewed',
            'Accounting Officer viewed accounting reports.',
            null,
            null,
            null,
            'info',
            ['filters' => $this->filterInputs($request)],
        );

        return $this->reportView($request);
    }

    public function summary(Request $request): View
    {
        AuditLogger::log('Accounting Reports', 'Accounting Review Summary Viewed', 'Accounting Officer viewed accounting review summary report.');

        return $this->reportView($request);
    }

    public function export(Request $request): StreamedResponse
    {
        AuditLogger::log('Accounting Reports', 'Accounting Report Exported', 'Accounting Officer exported accounting report.', null, null, null, 'info', [
            'filters' => $this->filterInputs($request),
        ]);

        $rows = $this->filteredReportQuery($request)->get();
        $filename = 'papertrail-accounting-report-' . now()->format('Y-m-d') . '.csv';

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
        AuditLogger::log('Accounting Reports', 'Accounting Report Printed', 'Accounting Officer opened accounting report print view.', null, null, null, 'info', [
            'filters' => $this->filterInputs($request),
        ]);

        $rows = $this->filteredReportQuery($request)->limit(500)->get();

        return view('accounting.reports.print', [
            'title' => 'Accounting Reports',
            'lguName' => SystemSettingService::get('lgu.name', 'Municipality of Tomas Oppus'),
            'generatedBy' => $request->user()->name,
            'filters' => $this->filterSummary($request),
            'summary' => $this->summaryValues($rows),
            'headers' => $this->headers(),
            'rows' => $rows,
        ]);
    }

    private function reportView(Request $request): View
    {
        $rows = $this->filteredReportQuery($request)->get();
        $baseForOptions = $this->baseReportQuery($request->user());

        return view('accounting.reports.index', [
            'documents' => $this->filteredReportQuery($request)->paginate(10)->withQueryString(),
            'summary' => $this->summaryValues($rows),
            'returnedDocuments' => $this->returnedDocuments($rows),
            'officeSummary' => $this->officeSummary($rows),
            'monthlyActivity' => $this->monthlyActivity($rows),
            'accountCodeSummary' => $this->accountCodeSummary($rows),
            'statusBreakdown' => $this->statusBreakdown($rows),
            'filters' => $this->filterInputs($request),
            'filterSummary' => $this->filterSummary($request),
            'documentTypes' => (clone $baseForOptions)->select('document_type')->distinct()->orderBy('document_type')->pluck('document_type'),
            'fiscalYears' => (clone $baseForOptions)->select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
            'offices' => Office::whereIn('id', (clone $baseForOptions)->select('submitting_office_id'))->orderBy('name')->get(),
            'accountingStatuses' => [
                AccountingReview::STATUS_PENDING,
                AccountingReview::STATUS_UNDER_REVIEW,
                AccountingReview::STATUS_ACCOUNTING_VERIFIED,
                AccountingReview::STATUS_RETURNED,
                AccountingReview::STATUS_NON_COMPLIANT,
                'reviewed',
                'endorsed',
            ],
            'statuses' => [
                ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW,
                ProcurementDocument::STATUS_UNDER_ACCOUNTING_REVIEW,
                ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING,
                ProcurementDocument::STATUS_ACCOUNTING_REVIEWED,
                ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW,
                'under_bac_secretariat_review',
                'bac_secretariat_reviewed',
                'approved',
            ],
        ]);
    }

    private function filteredReportQuery(Request $request): Builder
    {
        $query = $this->baseReportQuery($request->user())
            ->with([
                'submittingOffice',
                'currentOffice',
                'accountingReviewedBy',
                'accountingReviews.reviewedBy',
                'routingHistories.toOffice',
            ]);

        $this->applyFilters($query, $request);

        return $query->latest('accounting_reviewed_at')->latest('updated_at');
    }

    private function baseReportQuery(User $user): Builder
    {
        return ProcurementDocument::query()
            ->where(function (Builder $query) use ($user) {
                $query->where(fn (Builder $visible) => $visible->visibleToAccountingOfficer($user))
                    ->orWhere('accounting_reviewed_by_user_id', $user->id)
                    ->orWhereHas('accountingReviews', fn (Builder $review) => $review->where('reviewed_by_user_id', $user->id))
                    ->orWhereHas('routingHistories', function (Builder $history) use ($user) {
                        $history->where('action_by_user_id', $user->id)
                            ->orWhereIn('status_to', [
                                ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW,
                                ProcurementDocument::STATUS_UNDER_ACCOUNTING_REVIEW,
                                ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING,
                                ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW,
                            ]);
                    });
            })
            ->where(function (Builder $query) {
                $query->whereIn('status', [
                    ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW,
                    ProcurementDocument::STATUS_UNDER_ACCOUNTING_REVIEW,
                    ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING,
                    ProcurementDocument::STATUS_ACCOUNTING_REVIEWED,
                    ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW,
                    'under_bac_secretariat_review',
                    'bac_secretariat_reviewed',
                    'approved',
                ])->orWhereNotNull('accounting_status')
                    ->orWhereHas('accountingReviews');
            });
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        foreach ([
            'fiscal_year' => 'fiscal_year',
            'requesting_office' => 'submitting_office_id',
            'document_type' => 'document_type',
            'accounting_status' => 'accounting_status',
            'status' => 'status',
        ] as $input => $column) {
            $query->when($request->filled($input), fn (Builder $builder) => $builder->where($column, $request->input($input)));
        }

        $query->when($request->filled('date_from'), function (Builder $builder) use ($request) {
            $date = $request->date('date_from');
            $builder->where(function (Builder $nested) use ($date) {
                $nested->whereDate('accounting_reviewed_at', '>=', $date)
                    ->orWhereDate('submitted_at', '>=', $date)
                    ->orWhereHas('accountingReviews', fn (Builder $review) => $review->whereDate('completed_at', '>=', $date))
                    ->orWhereHas('routingHistories', fn (Builder $history) => $history->whereDate('action_at', '>=', $date));
            });
        });

        $query->when($request->filled('date_to'), function (Builder $builder) use ($request) {
            $date = $request->date('date_to');
            $builder->where(function (Builder $nested) use ($date) {
                $nested->whereDate('accounting_reviewed_at', '<=', $date)
                    ->orWhereDate('submitted_at', '<=', $date)
                    ->orWhereHas('accountingReviews', fn (Builder $review) => $review->whereDate('completed_at', '<=', $date))
                    ->orWhereHas('routingHistories', fn (Builder $history) => $history->whereDate('action_at', '<=', $date));
            });
        });
    }

    private function summaryValues(Collection $documents): array
    {
        return [
            'totalProcessed' => $documents->count(),
            'pendingAccountingReview' => $documents->where('status', ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW)->count(),
            'accountingVerified' => $documents->filter(fn (ProcurementDocument $document) => $this->isVerified($document))->count(),
            'returnedByAccounting' => $documents->filter(fn (ProcurementDocument $document) => $this->isReturned($document))->count(),
            'forwardedToBac' => $documents->filter(fn (ProcurementDocument $document) => $this->isForwardedToBac($document))->count(),
            'totalAmountReviewed' => (float) $documents->filter(fn (ProcurementDocument $document) => $this->reviewedDate($document) !== null || $this->isReturned($document))->sum('total_amount'),
        ];
    }

    private function returnedDocuments(Collection $documents): Collection
    {
        return $documents->filter(fn (ProcurementDocument $document) => $this->isReturned($document))->values();
    }

    private function officeSummary(Collection $documents): Collection
    {
        return $documents
            ->groupBy(fn (ProcurementDocument $document) => $document->submittingOffice?->name ?? 'Unassigned Office')
            ->map(fn (Collection $group, string $office) => [
                'office' => $office,
                'total' => $group->count(),
                'verified' => $group->filter(fn (ProcurementDocument $document) => $this->isVerified($document))->count(),
                'returned' => $group->filter(fn (ProcurementDocument $document) => $this->isReturned($document))->count(),
                'pending' => $group->whereIn('status', [ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW, ProcurementDocument::STATUS_UNDER_ACCOUNTING_REVIEW])->count(),
                'amount' => (float) $group->sum('total_amount'),
            ])
            ->sortByDesc('total')
            ->values();
    }

    private function monthlyActivity(Collection $documents): Collection
    {
        return $documents
            ->filter(fn (ProcurementDocument $document) => $this->activityDate($document) !== null)
            ->groupBy(fn (ProcurementDocument $document) => $this->activityDate($document)?->format('Y-m'))
            ->map(fn (Collection $group, string $month) => [
                'month' => \Carbon\Carbon::createFromFormat('Y-m', $month)->format('M Y'),
                'reviewed' => $group->filter(fn (ProcurementDocument $document) => $this->reviewedDate($document) !== null)->count(),
                'verified' => $group->filter(fn (ProcurementDocument $document) => $this->isVerified($document))->count(),
                'returned' => $group->filter(fn (ProcurementDocument $document) => $this->isReturned($document))->count(),
                'forwarded' => $group->filter(fn (ProcurementDocument $document) => $this->isForwardedToBac($document))->count(),
                'amount' => (float) $group->sum('total_amount'),
            ])
            ->values();
    }

    private function accountCodeSummary(Collection $documents): Collection
    {
        return $documents
            ->filter(fn (ProcurementDocument $document) => filled($document->account_code) || filled($document->object_code))
            ->groupBy(fn (ProcurementDocument $document) => implode('|', [
                $document->account_code ?: 'N/A',
                $document->object_code ?: 'N/A',
                $document->responsibility_center ?: 'N/A',
            ]))
            ->map(function (Collection $group, string $key) {
                [$accountCode, $objectCode, $responsibilityCenter] = explode('|', $key);

                return [
                    'account_code' => $accountCode,
                    'object_code' => $objectCode,
                    'responsibility_center' => $responsibilityCenter,
                    'documents' => $group->count(),
                    'amount' => (float) $group->sum('total_amount'),
                    'latest_reviewed' => $group->map(fn (ProcurementDocument $document) => $this->reviewedDate($document))->filter()->sortDesc()->first(),
                ];
            })
            ->sortByDesc('documents')
            ->values();
    }

    private function statusBreakdown(Collection $documents): Collection
    {
        return $documents
            ->groupBy(fn (ProcurementDocument $document) => $document->accounting_status ?: $document->status)
            ->map(fn (Collection $group, string $status) => [
                'label' => str($status)->replace('_', ' ')->title()->toString(),
                'value' => $group->count(),
            ])
            ->values();
    }

    private function filterInputs(Request $request): array
    {
        return $request->only(['fiscal_year', 'date_from', 'date_to', 'requesting_office', 'document_type', 'accounting_status', 'status']);
    }

    private function filterSummary(Request $request): array
    {
        $filters = [];

        foreach ([
            'fiscal_year' => 'Fiscal Year',
            'date_from' => 'Date From',
            'date_to' => 'Date To',
            'document_type' => 'Document Type',
            'accounting_status' => 'Accounting Status',
            'status' => 'Current Status',
        ] as $key => $label) {
            if ($request->filled($key)) {
                $value = in_array($key, ['accounting_status', 'status'], true)
                    ? str($request->input($key))->replace('_', ' ')->title()->toString()
                    : $request->input($key);
                $filters[$label] = $value;
            }
        }

        if ($request->filled('requesting_office')) {
            $filters['Requesting Office'] = Office::find($request->input('requesting_office'))?->name ?? $request->input('requesting_office');
        }

        return $filters ?: ['Filters' => 'All accounting report records'];
    }

    private function headers(): array
    {
        return ['Tracking Number', 'Document Type', 'Title', 'Requesting Office', 'Total Amount', 'Budget Status', 'Accounting Status', 'Current Status', 'Account Code', 'Object Code', 'Responsibility Center', 'Reviewed By', 'Reviewed Date', 'Current Office', 'Stage'];
    }

    private function mapRow(ProcurementDocument $document): array
    {
        $latestReview = $document->accountingReviews->sortByDesc(fn (AccountingReview $review) => $review->completed_at ?? $review->created_at)->first();

        return [
            $document->tracking_number,
            $document->document_type,
            $document->title,
            $document->submittingOffice?->name ?? 'N/A',
            number_format((float) $document->total_amount, 2, '.', ''),
            str($document->budget_status ?? 'N/A')->replace('_', ' ')->title()->toString(),
            str($document->accounting_status ?? 'N/A')->replace('_', ' ')->title()->toString(),
            str($document->status)->replace('_', ' ')->title()->toString(),
            $document->account_code ?? 'N/A',
            $document->object_code ?? 'N/A',
            $document->responsibility_center ?? 'N/A',
            $document->accountingReviewedBy?->name ?? $latestReview?->reviewedBy?->name ?? 'N/A',
            $this->reviewedDate($document)?->format('Y-m-d H:i') ?? 'N/A',
            $document->currentOffice?->name ?? 'N/A',
            $document->stage ?? 'N/A',
        ];
    }

    private function reviewedDate(ProcurementDocument $document): ?\Carbon\Carbon
    {
        return $document->accounting_reviewed_at
            ?? $document->accountingReviews->sortByDesc(fn (AccountingReview $review) => $review->completed_at ?? $review->created_at)->first()?->completed_at;
    }

    private function activityDate(ProcurementDocument $document): ?\Carbon\Carbon
    {
        return $this->reviewedDate($document)
            ?? $document->routingHistories->sortByDesc('action_at')->first()?->action_at
            ?? $document->submitted_at;
    }

    private function isVerified(ProcurementDocument $document): bool
    {
        return $document->accounting_status === AccountingReview::STATUS_ACCOUNTING_VERIFIED
            || in_array($document->status, [ProcurementDocument::STATUS_ACCOUNTING_REVIEWED, ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW], true);
    }

    private function isReturned(ProcurementDocument $document): bool
    {
        return $document->status === ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING
            || in_array($document->accounting_status, [AccountingReview::STATUS_RETURNED, AccountingReview::STATUS_NON_COMPLIANT], true);
    }

    private function isForwardedToBac(ProcurementDocument $document): bool
    {
        return $document->status === ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW
            || $document->currentOffice?->code === 'BACSEC'
            || $document->currentOffice?->name === 'BAC Secretariat';
    }
}
