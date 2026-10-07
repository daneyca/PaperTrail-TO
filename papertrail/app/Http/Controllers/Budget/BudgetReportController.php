<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Models\BudgetReview;
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

class BudgetReportController extends Controller
{
    public function index(Request $request): View
    {
        AuditLogger::log('Budget Reports', 'Budget Reports Viewed', 'Budget Officer viewed budget reports.');

        return $this->reportView($request);
    }

    public function reviewSummary(Request $request): View
    {
        AuditLogger::log('Budget Reports', 'Budget Review Summary Viewed', 'Budget Officer viewed budget review summary report.');

        return $this->reportView($request);
    }

    public function export(Request $request): StreamedResponse
    {
        AuditLogger::log('Budget Reports', 'Budget Report Exported', 'Budget Officer exported budget report.', null, null, null, 'info', [
            'filters' => $request->only(['fiscal_year', 'date_from', 'date_to', 'requesting_office', 'document_type', 'status']),
        ]);

        $rows = $this->filteredReportQuery($request)->get();
        $filename = 'papertrail-budget-report-' . now()->format('Y-m-d') . '.csv';

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
        AuditLogger::log('Budget Reports', 'Budget Report Printed', 'Budget Officer opened budget report print view.', null, null, null, 'info', [
            'filters' => $request->only(['fiscal_year', 'date_from', 'date_to', 'requesting_office', 'document_type', 'status']),
        ]);

        return view('budget.reports.print', [
            'title' => 'Budget Review Summary Report',
            'lguName' => SystemSettingService::get('lgu.name', 'Municipality of Tomas Oppus'),
            'generatedBy' => $request->user()->name,
            'filters' => $this->filterSummary($request),
            'headers' => $this->headers(),
            'rows' => $this->filteredReportQuery($request)->limit(500)->get(),
        ]);
    }

    private function reportView(Request $request): View
    {
        $rowsQuery = $this->filteredReportQuery($request);
        $allRows = $rowsQuery->get();
        $summaryQuery = $this->baseReportQuery($request->user());

        $this->applyFilters($summaryQuery, $request);

        return view('budget.reports.index', [
            'documents' => $this->filteredReportQuery($request)->paginate(10)->withQueryString(),
            'summary' => $this->summary($summaryQuery),
            'statusBreakdown' => $this->statusBreakdown($allRows),
            'officeBreakdown' => $this->officeBreakdown($allRows),
            'monthlyActivity' => $this->monthlyActivity($allRows),
            'fundSources' => $this->fundSources($allRows),
            'filters' => $request->only(['fiscal_year', 'date_from', 'date_to', 'requesting_office', 'document_type', 'status']),
            'filterSummary' => $this->filterSummary($request),
            'documentTypes' => $this->baseReportQuery($request->user())->select('document_type')->distinct()->orderBy('document_type')->pluck('document_type'),
            'fiscalYears' => $this->baseReportQuery($request->user())->select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
            'offices' => Office::whereIn('id', $this->baseReportQuery($request->user())->select('submitting_office_id'))->orderBy('name')->get(),
            'statuses' => [
                ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW,
                ProcurementDocument::STATUS_UNDER_BUDGET_REVIEW,
                ProcurementDocument::STATUS_RETURNED_BY_BUDGET,
                ProcurementDocument::STATUS_BUDGET_REVIEWED,
                ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW,
            ],
        ]);
    }

    private function filteredReportQuery(Request $request): Builder
    {
        $query = $this->baseReportQuery($request->user())
            ->with(['submittingOffice', 'budgetReviewedBy', 'budgetReviews.reviewedBy']);

        $this->applyFilters($query, $request);

        return $query->latest('budget_reviewed_at')->latest('submitted_at');
    }

    private function baseReportQuery(User $user): Builder
    {
        return ProcurementDocument::query()
            ->where(function (Builder $query) use ($user) {
                $query->where(fn (Builder $visible) => $visible->visibleToBudgetOfficer($user))
                    ->orWhere('budget_reviewed_by_user_id', $user->id)
                    ->orWhereHas('budgetReviews', fn (Builder $review) => $review->where('reviewed_by_user_id', $user->id));
            });
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        foreach ([
            'fiscal_year' => 'fiscal_year',
            'requesting_office' => 'submitting_office_id',
            'document_type' => 'document_type',
            'status' => 'status',
        ] as $input => $column) {
            $query->when($request->filled($input), fn (Builder $builder) => $builder->where($column, $request->input($input)));
        }

        $query->when($request->filled('date_from'), function (Builder $builder) use ($request) {
            $date = $request->date('date_from');
            $builder->where(function (Builder $nested) use ($date) {
                $nested->whereDate('budget_reviewed_at', '>=', $date)
                    ->orWhereDate('submitted_at', '>=', $date)
                    ->orWhereHas('budgetReviews', fn (Builder $review) => $review->whereDate('completed_at', '>=', $date));
            });
        });

        $query->when($request->filled('date_to'), function (Builder $builder) use ($request) {
            $date = $request->date('date_to');
            $builder->where(function (Builder $nested) use ($date) {
                $nested->whereDate('budget_reviewed_at', '<=', $date)
                    ->orWhereDate('submitted_at', '<=', $date)
                    ->orWhereHas('budgetReviews', fn (Builder $review) => $review->whereDate('completed_at', '<=', $date));
            });
        });
    }

    private function summary(Builder $query): array
    {
        return [
            'totalReviewed' => (clone $query)->where(function (Builder $builder) {
                $builder->whereNotNull('budget_reviewed_at')
                    ->orWhereHas('budgetReviews', fn (Builder $review) => $review->whereNotNull('completed_at'));
            })->count(),
            'pendingBudgetReview' => (clone $query)->where('status', ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW)->count(),
            'returnedByBudget' => (clone $query)->where('status', ProcurementDocument::STATUS_RETURNED_BY_BUDGET)->count(),
            'forwardedToAccounting' => (clone $query)->where('status', ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW)->count(),
            'totalAmountReviewed' => (float) (clone $query)->where(function (Builder $builder) {
                $builder->whereNotNull('budget_reviewed_at')
                    ->orWhereHas('budgetReviews', fn (Builder $review) => $review->whereNotNull('completed_at'));
            })->sum('total_amount'),
        ];
    }

    private function statusBreakdown(Collection $documents): Collection
    {
        return $documents
            ->groupBy('status')
            ->map(fn (Collection $group, string $status) => [
                'label' => str($status)->replace('_', ' ')->title()->toString(),
                'value' => $group->count(),
            ])
            ->values();
    }

    private function officeBreakdown(Collection $documents): Collection
    {
        return $documents
            ->groupBy(fn (ProcurementDocument $document) => $document->submittingOffice?->name ?? 'Unassigned Office')
            ->map(fn (Collection $group, string $office) => [
                'label' => $office,
                'value' => $group->count(),
            ])
            ->sortByDesc('value')
            ->values();
    }

    private function monthlyActivity(Collection $documents): Collection
    {
        return $documents
            ->filter(fn (ProcurementDocument $document) => $this->reviewedDate($document) !== null)
            ->groupBy(fn (ProcurementDocument $document) => $this->reviewedDate($document)?->format('Y-m'))
            ->map(fn (Collection $group, string $month) => [
                'label' => \Carbon\Carbon::createFromFormat('Y-m', $month)->format('M Y'),
                'value' => $group->count(),
            ])
            ->values();
    }

    private function fundSources(Collection $documents): Collection
    {
        return $documents
            ->flatMap(fn (ProcurementDocument $document) => $document->budgetReviews)
            ->filter(fn (BudgetReview $review) => filled($review->fund_source))
            ->groupBy('fund_source')
            ->map(fn (Collection $group, string $fundSource) => [
                'label' => $fundSource,
                'value' => $group->count(),
                'amount' => (float) $group->sum('available_amount'),
            ])
            ->sortByDesc('value')
            ->values();
    }

    private function filterSummary(Request $request): array
    {
        $filters = [];

        foreach ([
            'fiscal_year' => 'Fiscal Year',
            'date_from' => 'Date From',
            'date_to' => 'Date To',
            'document_type' => 'Document Type',
            'status' => 'Status',
        ] as $key => $label) {
            if ($request->filled($key)) {
                $value = $key === 'status'
                    ? str($request->input($key))->replace('_', ' ')->title()->toString()
                    : $request->input($key);
                $filters[$label] = $value;
            }
        }

        if ($request->filled('requesting_office')) {
            $filters['Requesting Office'] = Office::find($request->input('requesting_office'))?->name ?? $request->input('requesting_office');
        }

        return $filters ?: ['Filters' => 'All budget report records'];
    }

    private function headers(): array
    {
        return ['Tracking Number', 'Document Type', 'Requesting Office', 'Total Amount', 'Budget Status', 'Reviewed By', 'Reviewed Date', 'Current Status'];
    }

    private function mapRow(ProcurementDocument $document): array
    {
        return [
            $document->tracking_number,
            $document->document_type,
            $document->submittingOffice?->name ?? 'N/A',
            number_format((float) $document->total_amount, 2, '.', ''),
            str($document->budget_status ?? 'N/A')->replace('_', ' ')->title()->toString(),
            $document->budgetReviewedBy?->name ?? $document->budgetReviews->sortByDesc(fn (BudgetReview $review) => $review->completed_at ?? $review->created_at)->first()?->reviewedBy?->name ?? 'N/A',
            $this->reviewedDate($document)?->format('Y-m-d H:i') ?? 'N/A',
            str($document->status)->replace('_', ' ')->title()->toString(),
        ];
    }

    private function reviewedDate(ProcurementDocument $document): ?\Carbon\Carbon
    {
        return $document->budget_reviewed_at
            ?? $document->budgetReviews->sortByDesc(fn (BudgetReview $review) => $review->completed_at ?? $review->created_at)->first()?->completed_at;
    }
}
