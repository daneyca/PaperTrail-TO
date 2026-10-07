@extends('layouts.dashboard')

@section('title', 'Accounting Reports | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero budget-reports-header">
        <div>
            <p class="eyebrow">Accounting Office</p>
            <h1>Accounting Reports</h1>
            <p>Generate summaries of accounting review activity, returned documents, and verified procurement records.</p>
        </div>

        <div class="report-header-actions">
            <x-ui.action-button
                :href="route('accounting.reports.export', request()->query())"
                icon="download"
                label="Download Report"
                tooltip="Download Report"
                variant="download"
            />
            <x-ui.action-button
                :href="route('accounting.reports.print', request()->query())"
                icon="print"
                label="Print Report"
                tooltip="Print Report"
                variant="print"
                target="_blank"
                rel="noopener"
            />
        </div>
    </section>

    <section class="stat-grid stat-grid-modern budget-report-summary" aria-label="Accounting report summary">
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['totalProcessed'] }}" label="Total Documents Processed" accent="navy" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['pendingAccountingReview'] }}" label="Pending Accounting Review" accent="gold" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['accountingVerified'] }}" label="Accounting Verified" accent="green" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['returnedByAccounting'] }}" label="Returned by Accounting" accent="red" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['forwardedToBac'] }}" label="Forwarded to BAC Secretariat" accent="navy" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="PHP {{ number_format($summary['totalAmountReviewed'], 2) }}" label="Total Amount Reviewed" accent="navy" class="budget-amount-card" />
    </section>

    <section class="table-panel budget-report-filter-panel" aria-label="Accounting report filters">
        <form method="GET" action="{{ route('accounting.reports.index') }}" class="budget-report-filter-toolbar">
            <div class="user-filter">
                <label for="fiscal-year">Fiscal Year</label>
                <select id="fiscal-year" name="fiscal_year">
                    <option value="">All years</option>
                    @foreach ($fiscalYears as $year)
                        <option value="{{ $year }}" @selected((string) ($filters['fiscal_year'] ?? '') === (string) $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="date-from">Date From</label>
                <input id="date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}">
            </div>

            <div class="user-filter">
                <label for="date-to">Date To</label>
                <input id="date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}">
            </div>

            <div class="user-filter">
                <label for="requesting-office">Requesting Office</label>
                <select id="requesting-office" name="requesting_office">
                    <option value="">All offices</option>
                    @foreach ($offices as $office)
                        <option value="{{ $office->id }}" @selected((string) ($filters['requesting_office'] ?? '') === (string) $office->id)>{{ $office->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="document-type">Document Type</label>
                <select id="document-type" name="document_type">
                    <option value="">All types</option>
                    @foreach ($documentTypes as $type)
                        <option value="{{ $type }}" @selected(($filters['document_type'] ?? '') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="accounting-status">Accounting Status</label>
                <select id="accounting-status" name="accounting_status">
                    <option value="">All accounting statuses</option>
                    @foreach ($accountingStatuses as $status)
                        <option value="{{ $status }}" @selected(($filters['accounting_status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="status-filter">Current Status</label>
                <select id="status-filter" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit">Apply</button>
                <a href="{{ route('accounting.reports.index') }}">Clear</a>
            </div>
        </form>
    </section>

    <section class="budget-report-grid" aria-label="Accounting report visual summaries">
        <article class="table-panel budget-report-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Accounting Review Summary</p>
                    <h2>Documents by Accounting Status</h2>
                </div>
            </div>
            @include('budget.reports.partials.bars', ['items' => $statusBreakdown, 'empty' => 'No accounting status data available.'])
        </article>

        <article class="table-panel budget-report-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Office Request Summary</p>
                    <h2>Documents by Requesting Office</h2>
                </div>
            </div>
            @include('budget.reports.partials.bars', ['items' => $officeSummary->map(fn ($row) => ['label' => $row['office'], 'value' => $row['total']]), 'empty' => 'No requesting office data available.'])
        </article>
    </section>

    <section class="table-panel" aria-label="Accounting review summary table">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Accounting Review Summary</p>
                <h2>Filtered Accounting Records</h2>
                <p>Report rows are based on actual procurement documents routed through Accounting.</p>
            </div>
        </div>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>Tracking Number</th>
                        <th>Document Type</th>
                        <th>Requesting Office</th>
                        <th>Total Amount</th>
                        <th>Accounting Status</th>
                        <th>Current Status</th>
                        <th>Reviewed By</th>
                        <th>Reviewed Date</th>
                        <th>Forwarded To</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        @php
                            $latestReview = $document->accountingReviews->sortByDesc(fn ($review) => $review->completed_at ?? $review->created_at)->first();
                            $reviewedBy = $document->accountingReviewedBy?->name ?? $latestReview?->reviewedBy?->name ?? 'N/A';
                            $reviewedDate = $document->accounting_reviewed_at ?? $latestReview?->completed_at;
                        @endphp
                        <tr>
                            <td class="nowrap">{{ $document->tracking_number }}</td>
                            <td>{{ $document->document_type }}</td>
                            <td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $document->total_amount, 2) }}</td>
                            <td><span class="status-pill status-{{ $document->accounting_status }}">{{ $document->accounting_status ? str($document->accounting_status)->replace('_', ' ')->title() : 'N/A' }}</span></td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ str($document->status)->replace('_', ' ')->title() }}</span></td>
                            <td>{{ $reviewedBy }}</td>
                            <td class="nowrap">{{ $reviewedDate?->format('M d, Y') ?? 'N/A' }}</td>
                            <td>{{ $document->currentOffice?->name ?? 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <strong>No accounting report data yet</strong>
                                    <p>Accounting report data will appear after documents are reviewed or returned by the Accounting Office.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($documents->hasPages())
            <div class="pagination-wrap">
                {{ $documents->appends(request()->query())->links('vendor.pagination.papertrail') }}
            </div>
        @endif
    </section>

    <section class="table-panel" aria-label="Returned accounting documents summary">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Returned Documents Summary</p>
                <h2>Returned by Accounting</h2>
            </div>
        </div>
        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>Tracking Number</th>
                        <th>Document Type</th>
                        <th>Requesting Office</th>
                        <th>Total Amount</th>
                        <th>Return Target</th>
                        <th>Return Reason</th>
                        <th>Returned Date</th>
                        <th>Returned By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($returnedDocuments as $document)
                        @php
                            $latestReview = $document->accountingReviews->filter(fn ($review) => in_array($review->review_status, ['returned', 'non_compliant'], true))->sortByDesc(fn ($review) => $review->completed_at ?? $review->created_at)->first();
                            $returnHistory = $document->routingHistories->first(fn ($history) => $history->status_to === 'returned_by_accounting');
                        @endphp
                        <tr>
                            <td class="nowrap">{{ $document->tracking_number }}</td>
                            <td>{{ $document->document_type }}</td>
                            <td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $document->total_amount, 2) }}</td>
                            <td>{{ $returnHistory?->toOffice?->name ?? $document->currentOffice?->name ?? 'N/A' }}</td>
                            <td>{{ $document->accounting_remarks ?? $latestReview?->remarks ?? $returnHistory?->comments ?? 'N/A' }}</td>
                            <td class="nowrap">{{ ($document->accounting_reviewed_at ?? $latestReview?->completed_at ?? $returnHistory?->action_at)?->format('M d, Y') ?? 'N/A' }}</td>
                            <td>{{ $document->accountingReviewedBy?->name ?? $latestReview?->reviewedBy?->name ?? $returnHistory?->actionBy?->name ?? 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty-state"><strong>No returned accounting documents</strong><p>Returned document report rows will appear after Accounting returns a document.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="table-panel" aria-label="Office request summary">
        <div class="panel-heading"><div><p class="eyebrow">Office / Requesting Unit Summary</p><h2>Requests by Office</h2></div></div>
        <div class="table-scroll">
            <table class="user-management-table">
                <thead><tr><th>Office</th><th>Total Documents</th><th>Verified</th><th>Returned</th><th>Pending</th><th>Total Amount</th></tr></thead>
                <tbody>
                    @forelse ($officeSummary as $row)
                        <tr>
                            <td>{{ $row['office'] }}</td>
                            <td>{{ $row['total'] }}</td>
                            <td>{{ $row['verified'] }}</td>
                            <td>{{ $row['returned'] }}</td>
                            <td>{{ $row['pending'] }}</td>
                            <td class="nowrap">PHP {{ number_format($row['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state"><strong>No office summary data</strong><p>Office summaries will appear after accounting records exist.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="table-panel" aria-label="Monthly accounting review activity">
        <div class="panel-heading"><div><p class="eyebrow">Monthly Accounting Review Activity</p><h2>Activity by Month</h2></div></div>
        <div class="table-scroll">
            <table class="user-management-table">
                <thead><tr><th>Month</th><th>Reviewed Documents</th><th>Verified Documents</th><th>Returned Documents</th><th>Forwarded to BAC</th><th>Total Amount Reviewed</th></tr></thead>
                <tbody>
                    @forelse ($monthlyActivity as $row)
                        <tr>
                            <td>{{ $row['month'] }}</td>
                            <td>{{ $row['reviewed'] }}</td>
                            <td>{{ $row['verified'] }}</td>
                            <td>{{ $row['returned'] }}</td>
                            <td>{{ $row['forwarded'] }}</td>
                            <td class="nowrap">PHP {{ number_format($row['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state"><strong>No monthly activity yet</strong><p>Monthly activity appears after Accounting reviews documents.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="table-panel" aria-label="Account code summary">
        <div class="panel-heading"><div><p class="eyebrow">Account Code / Object Code Summary</p><h2>Accounting Classifications</h2></div></div>
        <div class="table-scroll">
            <table class="user-management-table">
                <thead><tr><th>Account Code</th><th>Object Code</th><th>Responsibility Center</th><th>Number of Documents</th><th>Total Amount</th><th>Latest Reviewed Date</th></tr></thead>
                <tbody>
                    @forelse ($accountCodeSummary as $row)
                        <tr>
                            <td>{{ $row['account_code'] }}</td>
                            <td>{{ $row['object_code'] }}</td>
                            <td>{{ $row['responsibility_center'] }}</td>
                            <td>{{ $row['documents'] }}</td>
                            <td class="nowrap">PHP {{ number_format($row['amount'], 2) }}</td>
                            <td class="nowrap">{{ $row['latest_reviewed']?->format('M d, Y') ?? 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state"><strong>No account code data available yet.</strong><p>Account code summaries will appear once Accounting records classifications.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
