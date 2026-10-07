<?php

namespace App\Http\Controllers\BacSecretariat;

use App\Http\Controllers\Controller;
use App\Models\AppConsolidation;
use App\Models\DocumentRoutingHistory;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\PurchaseOrder;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        AuditLogger::log('BAC Secretariat Reports', $request->query() ? 'BAC Report Filtered' : 'BAC Reports Viewed', 'BAC Secretariat viewed reports.');

        return view('bac-secretariat.reports.index', $this->reportData($request));
    }

    public function export(Request $request): StreamedResponse
    {
        AuditLogger::log('BAC Secretariat Reports', 'BAC Report Exported', 'BAC Secretariat exported reports.');
        $data = $this->reportData($request);
        $filename = 'papertrail-bac-secretariat-reports-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Report Type', 'Tracking Number', 'Official / Form Number', 'Document Type', 'Title / Description', 'Requesting Office', 'Status', 'Stage', 'Current Office', 'Total Amount', 'Prepared / Routed By', 'Date', 'Remarks']);

            foreach ($data['exportRows'] as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function print(Request $request): View
    {
        AuditLogger::log('BAC Secretariat Reports', 'BAC Report Printed', 'BAC Secretariat opened print report.');

        return view('bac-secretariat.reports.print', $this->reportData($request));
    }

    private function reportData(Request $request): array
    {
        $reportType = $request->input('report_type', 'all');
        $incoming = $this->incomingQuery($request)->latest('updated_at')->limit(25)->get();
        $routing = $this->routingQuery($request)->latest('updated_at')->limit(25)->get();
        $apps = $this->appQuery($request)->with(['preparedBy', 'ppmpSources', 'appItems'])->latest('updated_at')->limit(25)->get();
        $prs = $this->purchaseRequestQuery($request)->latest('updated_at')->limit(25)->get();
        $pos = $this->purchaseOrderQuery($request)->latest('updated_at')->limit(25)->get();
        $returned = $this->returnedQuery($request)->latest('updated_at')->limit(25)->get();
        $monthly = $this->monthlyActivity($request);
        $officeSummary = $this->officeSummary($request);
        $processing = $this->processingTime($request);
        $shownIncoming = $this->enabled($reportType, ['all', 'incoming']) ? $incoming : collect();
        $shownRouting = $this->enabled($reportType, ['all', 'routing']) ? $routing : collect();
        $shownApps = $this->enabled($reportType, ['all', 'app']) ? $apps : collect();
        $shownPrs = $this->enabled($reportType, ['all', 'pr']) ? $prs : collect();
        $shownPos = $this->enabled($reportType, ['all', 'po']) ? $pos : collect();
        $shownReturned = $this->enabled($reportType, ['all', 'returned']) ? $returned : collect();
        $exportRows = $this->exportRows(
            $shownIncoming,
            $shownRouting,
            $shownApps,
            $shownPrs,
            $shownPos,
            $shownReturned,
            $this->enabled($reportType, ['all', 'monthly']) ? $monthly : collect(),
            $this->enabled($reportType, ['all', 'office']) ? $officeSummary : collect()
        );

        return [
            'summary' => [
                'incoming' => $this->incomingQuery($request)->count(),
                'routed' => $this->routingQuery($request)->count(),
                'apps' => $this->appQuery($request)->count(),
                'prs' => $this->purchaseRequestQuery($request)->count(),
                'pos' => $this->purchaseOrderQuery($request)->count(),
                'returned' => $this->returnedQuery($request)->count(),
                'completed' => $this->completedQuery($request)->count(),
                'amount' => $this->purchaseRequestQuery($request)->sum('total_amount') + $this->purchaseOrderQuery($request)->sum('total_amount'),
            ],
            'incoming' => $shownIncoming,
            'routing' => $shownRouting,
            'apps' => $shownApps,
            'prs' => $shownPrs,
            'pos' => $shownPos,
            'returned' => $shownReturned,
            'monthly' => $this->enabled($reportType, ['all', 'monthly']) ? $monthly : collect(),
            'officeSummary' => $this->enabled($reportType, ['all', 'office']) ? $officeSummary : collect(),
            'processing' => $processing,
            'exportRows' => $exportRows,
            'filters' => $request->only(['fiscal_year', 'report_type', 'document_type', 'status', 'office_id', 'date_from', 'date_to']),
            'offices' => Office::orderBy('name')->get(),
            'documentTypes' => ['APP', 'PR', 'Purchase Request', 'PO'],
            'statuses' => ProcurementDocument::query()->select('status')->distinct()->orderBy('status')->pluck('status'),
            'reportTypes' => [
                'all' => 'All Reports',
                'incoming' => 'Incoming Documents',
                'routing' => 'Document Routing',
                'app' => 'APP Consolidation',
                'pr' => 'Purchase Requests',
                'po' => 'Purchase Orders',
                'returned' => 'Returned Documents',
                'monthly' => 'Monthly Activity',
                'office' => 'Office Summary',
            ],
            'lguName' => class_exists(SystemSetting::class) ? (SystemSetting::where('key', 'lgu.name')->value('value') ?? 'Local Government Unit') : 'Local Government Unit',
        ];
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        foreach (['fiscal_year', 'document_type', 'status'] as $field) {
            $query->when($request->filled($field), fn (Builder $builder) => $builder->where($field, $request->input($field)));
        }

        $query->when($request->filled('office_id'), fn (Builder $builder) => $builder->where('submitting_office_id', $request->input('office_id')));
        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('updated_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('updated_at', '<=', $request->date('date_to')));
    }

    private function incomingQuery(Request $request): Builder
    {
        $query = ProcurementDocument::with(['submittingOffice', 'currentOffice'])
            ->whereIn('status', [
                ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW,
                ProcurementDocument::STATUS_RECEIVED_BY_BAC_SECRETARIAT,
                ProcurementDocument::STATUS_UNDER_BAC_SECRETARIAT_REVIEW,
                ProcurementDocument::STATUS_READY_FOR_DOCUMENT_ROUTING,
                ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
            ]);

        $this->applyFilters($query, $request);

        return $query;
    }

    private function routingQuery(Request $request): Builder
    {
        $query = ProcurementDocument::with(['submittingOffice', 'currentOffice', 'routingHistories.actionBy', 'routingHistories.toOffice'])
            ->where(function (Builder $builder) {
                $builder->whereIn('status', [
                    ProcurementDocument::STATUS_READY_FOR_DOCUMENT_ROUTING,
                    ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW,
                    ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW,
                    ProcurementDocument::STATUS_PENDING_APPROVAL,
                    ProcurementDocument::STATUS_ROUTED_FOR_ADDITIONAL_REVIEW,
                    ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
                ])->orWhereHas('routingHistories', fn (Builder $history) => $history->where('action', 'like', '%BAC%')->orWhere('action', 'like', '%Routed%'));
            });

        $this->applyFilters($query, $request);

        return $query;
    }

    private function appQuery(Request $request): Builder
    {
        return AppConsolidation::query()
            ->when($request->filled('fiscal_year'), fn (Builder $query) => $query->where('fiscal_year', $request->input('fiscal_year')))
            ->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', fn (Builder $query) => $query->where('status', $request->input('status')))
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('updated_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('updated_at', '<=', $request->date('date_to')));
    }

    private function purchaseRequestQuery(Request $request): Builder
    {
        $query = ProcurementDocument::with(['submittingOffice', 'currentOffice'])
            ->whereIn('document_type', ['PR', 'Purchase Request'])
            ->whereIn('status', [
                ProcurementDocument::STATUS_PR_SUBMITTED,
                ProcurementDocument::STATUS_PR_RECEIVED_BY_BAC_SECRETARIAT,
                ProcurementDocument::STATUS_UNDER_PR_VALIDATION,
                ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
                ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW,
                ProcurementDocument::STATUS_UNDER_BUDGET_REVIEW,
                ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW,
                ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW,
                ProcurementDocument::STATUS_READY_FOR_DOCUMENT_ROUTING,
                ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW,
                ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW,
                ProcurementDocument::STATUS_PENDING_APPROVAL,
                'approved',
                'ready_for_po',
            ]);

        $this->applyFilters($query, $request);

        return $query;
    }

    private function purchaseOrderQuery(Request $request): Builder
    {
        return PurchaseOrder::with(['sourcePrDocument.submittingOffice', 'preparedBy'])
            ->when($request->filled('fiscal_year'), fn (Builder $query) => $query->where('fiscal_year', $request->input('fiscal_year')))
            ->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', fn (Builder $query) => $query->where('status', $request->input('status')))
            ->when($request->filled('office_id'), fn (Builder $query) => $query->whereHas('sourcePrDocument', fn (Builder $pr) => $pr->where('submitting_office_id', $request->input('office_id'))))
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('updated_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('updated_at', '<=', $request->date('date_to')));
    }

    private function returnedQuery(Request $request): Builder
    {
        $query = ProcurementDocument::with(['submittingOffice', 'routingHistories.actionBy', 'routingHistories.fromOffice', 'routingHistories.toOffice'])
            ->whereIn('status', [
                ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
                'returned_by_bac_member',
                'returned_by_bac_chair',
                'returned_by_approving_authority',
                ProcurementDocument::STATUS_RETURNED_BY_BUDGET,
                ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING,
            ])
            ->where(function (Builder $builder) {
                $builder->where('status', ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT)
                    ->orWhereHas('routingHistories', fn (Builder $history) => $history->where('action', 'like', '%BAC%'));
            });

        $this->applyFilters($query, $request);

        return $query;
    }

    private function completedQuery(Request $request): Builder
    {
        $query = ProcurementDocument::query()
            ->whereIn('status', ['approved', 'completed', ProcurementDocument::STATUS_PO_COMPLETED]);

        $this->applyFilters($query, $request);

        return $query;
    }

    private function monthlyActivity(Request $request): Collection
    {
        $months = collect();

        foreach ($this->incomingQuery($request)->get() as $document) {
            $this->bumpMonth($months, $document->created_at, 'incoming');
        }

        foreach (DocumentRoutingHistory::whereIn('action', ['Routed Document', 'PR Routed to Budget Office', 'APP Submitted for Approval'])->get() as $history) {
            $this->bumpMonth($months, $history->action_at, 'routed');
        }

        foreach (AppConsolidation::whereNotNull('consolidated_at')->get() as $app) {
            $this->bumpMonth($months, $app->consolidated_at, 'apps');
        }

        foreach ($this->purchaseRequestQuery($request)->get() as $pr) {
            $this->bumpMonth($months, $pr->updated_at, 'prs');
        }

        foreach ($this->purchaseOrderQuery($request)->get() as $po) {
            if ($po->issued_at) {
                $this->bumpMonth($months, $po->issued_at, 'poIssued');
            }
            if ($po->completed_at) {
                $this->bumpMonth($months, $po->completed_at, 'poCompleted');
            }
        }

        foreach ($this->returnedQuery($request)->get() as $document) {
            $this->bumpMonth($months, $document->updated_at, 'returned');
        }

        return $months->sortKeysDesc();
    }

    private function officeSummary(Request $request): Collection
    {
        return $this->purchaseRequestQuery($request)
            ->get()
            ->groupBy(fn (ProcurementDocument $document) => $document->submittingOffice?->name ?? 'Unassigned Office')
            ->map(function (Collection $documents) {
                return [
                    'documents' => $documents->count(),
                    'prs' => $documents->whereIn('document_type', ['PR', 'Purchase Request'])->count(),
                    'returned' => $documents->where('status', ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT)->count(),
                    'completed' => $documents->whereIn('status', ['approved', 'completed', ProcurementDocument::STATUS_PO_COMPLETED])->count(),
                    'amount' => $documents->sum('total_amount'),
                ];
            });
    }

    private function processingTime(Request $request): array
    {
        $receiptToRouting = $this->incomingQuery($request)
            ->whereNotNull('bac_secretariat_received_at')
            ->whereNotNull('routed_at')
            ->get()
            ->map(fn (ProcurementDocument $document) => $document->bac_secretariat_received_at->diffInDays($document->routed_at));

        $prToBudget = $this->purchaseRequestQuery($request)
            ->whereNotNull('pr_received_at')
            ->whereNotNull('pr_validated_at')
            ->get()
            ->map(fn (ProcurementDocument $document) => $document->pr_received_at->diffInDays($document->pr_validated_at));

        $poToIssue = $this->purchaseOrderQuery($request)
            ->whereNotNull('created_at')
            ->whereNotNull('issued_at')
            ->get()
            ->map(fn (PurchaseOrder $po) => $po->created_at->diffInDays($po->issued_at));

        $pending = $this->incomingQuery($request)
            ->whereNotIn('status', [ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT, ProcurementDocument::STATUS_PO_COMPLETED, 'approved', 'completed'])
            ->oldest('updated_at')
            ->first();

        return [
            'available' => $receiptToRouting->isNotEmpty() || $prToBudget->isNotEmpty() || $poToIssue->isNotEmpty(),
            'receiptToRouting' => $receiptToRouting->avg(),
            'prToBudget' => $prToBudget->avg(),
            'poToIssue' => $poToIssue->avg(),
            'longestPending' => $pending,
            'overThreeDays' => $this->incomingQuery($request)->where('updated_at', '<', now()->subDays(3))->count(),
        ];
    }

    private function exportRows(Collection $incoming, Collection $routing, Collection $apps, Collection $prs, Collection $pos, Collection $returned, Collection $monthly, Collection $officeSummary): Collection
    {
        $rows = collect();

        foreach ($incoming as $document) {
            $rows->push(['Incoming Documents', $document->displayNumber(), $document->pr_no ?? '', $document->document_type, $document->title, $document->submittingOffice?->name ?? 'N/A', $document->status, $document->stage, $document->currentOffice?->name ?? 'N/A', $document->total_amount, '', $document->updated_at?->format('Y-m-d'), $document->remarks]);
        }

        foreach ($routing as $document) {
            $latest = $document->routingHistories->first();
            $rows->push(['Document Routing', $document->displayNumber(), $document->pr_no ?? '', $document->document_type, $document->title, $document->submittingOffice?->name ?? 'N/A', $document->status, $document->stage, $document->currentOffice?->name ?? 'N/A', $document->total_amount, $latest?->actionBy?->name, $latest?->action_at?->format('Y-m-d'), $latest?->comments]);
        }

        foreach ($apps as $app) {
            $rows->push(['APP Consolidation', $app->displayNumber(), $app->app_number ?? 'Draft', 'APP', $app->title, '', $app->status, '', '', $app->total_amount, $app->preparedBy?->name, $app->updated_at?->format('Y-m-d'), $app->remarks]);
        }

        foreach ($prs as $document) {
            $rows->push(['Purchase Requests', $document->displayNumber(), $document->pr_no ?? 'Not assigned', $document->document_type, $document->title, $document->submittingOffice?->name ?? 'N/A', $document->status, $document->stage, $document->currentOffice?->name ?? 'N/A', $document->total_amount, '', $document->submitted_at?->format('Y-m-d'), $document->pr_remarks]);
        }

        foreach ($pos as $po) {
            $rows->push(['Purchase Orders', $po->displayNumber(), $po->po_number ?? 'Draft', 'PO', $po->supplier_name ?? 'N/A', $po->sourcePrDocument?->submittingOffice?->name ?? 'N/A', $po->status, '', '', $po->total_amount, $po->preparedBy?->name, $po->updated_at?->format('Y-m-d'), $po->remarks]);
        }

        foreach ($returned as $document) {
            $latest = $document->routingHistories->first();
            $rows->push(['Returned Documents', $document->displayNumber(), $document->pr_no ?? '', $document->document_type, $document->title, $document->submittingOffice?->name ?? 'N/A', $document->status, $document->stage, $document->currentOffice?->name ?? 'N/A', $document->total_amount, $latest?->actionBy?->name, $latest?->action_at?->format('Y-m-d'), $latest?->comments ?? $document->remarks]);
        }

        foreach ($monthly as $month => $data) {
            $rows->push([
                'Monthly Activity',
                '',
                $month,
                '',
                'Monthly BAC Secretariat Activity',
                '',
                '',
                "Incoming: {$data['incoming']}; Routed: {$data['routed']}; APPs: {$data['apps']}; PRs: {$data['prs']}; Returned: {$data['returned']}",
                '',
                '',
                '',
                $month,
                "PO Issued: {$data['poIssued']}; PO Completed: {$data['poCompleted']}",
            ]);
        }

        foreach ($officeSummary as $office => $data) {
            $rows->push([
                'Office Request Summary',
                '',
                '',
                '',
                'Office procurement activity',
                $office,
                '',
                "Documents: {$data['documents']}; PRs: {$data['prs']}; Returned: {$data['returned']}; Completed: {$data['completed']}",
                '',
                $data['amount'],
                '',
                '',
                '',
            ]);
        }

        return $rows;
    }

    private function bumpMonth(Collection $months, $date, string $key): void
    {
        if (!$date) {
            return;
        }

        $month = $date->format('Y-m');
        $current = $months->get($month, [
            'incoming' => 0,
            'routed' => 0,
            'apps' => 0,
            'prs' => 0,
            'poIssued' => 0,
            'poCompleted' => 0,
            'returned' => 0,
        ]);
        $current[$key]++;
        $months->put($month, $current);
    }

    private function enabled(string $selected, array $allowed): bool
    {
        return in_array($selected, $allowed, true);
    }
}
