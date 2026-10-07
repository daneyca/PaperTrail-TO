@extends('layouts.dashboard')

@section('title', 'Budget Reports | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero budget-reports-header">
        <div>
            <p class="eyebrow">Budget Office</p>
            <h1>Budget Reports</h1>
            <p>Generate summaries of budget review activity and document routing outcomes.</p>
        </div>

        <div class="report-header-actions">
            <x-ui.action-button
                :href="route('budget.reports.export', request()->query())"
                icon="download"
                label="Download Report"
                tooltip="Download Report"
                variant="download"
            />
            <x-ui.action-button
                :href="route('budget.reports.print', request()->query())"
                icon="print"
                label="Print Report"
                tooltip="Print Report"
                variant="print"
                target="_blank"
                rel="noopener"
            />
        </div>
    </section>

    <section class="stat-grid stat-grid-modern budget-report-summary" aria-label="Budget report summary">
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['totalReviewed'] }}" label="Total Documents Reviewed" accent="navy" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['pendingBudgetReview'] }}" label="Pending Budget Review" accent="gold" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['returnedByBudget'] }}" label="Returned by Budget" accent="red" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['forwardedToAccounting'] }}" label="Forwarded to Accounting" accent="green" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="PHP {{ number_format($summary['totalAmountReviewed'], 2) }}" label="Total Amount Reviewed" accent="navy" class="budget-amount-card" />
    </section>

    <section class="table-panel budget-report-filter-panel" aria-label="Budget report filters">
        <form method="GET" action="{{ route('budget.reports.index') }}" class="budget-report-filter-toolbar">
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
                <label for="status-filter">Status</label>
                <select id="status-filter" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit">Apply</button>
                <a href="{{ route('budget.reports.index') }}">Clear</a>
            </div>
        </form>
    </section>

    <section class="budget-report-grid" aria-label="Budget report sections">
        <article class="table-panel budget-report-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Budget Review Summary</p>
                    <h2>Documents by Status</h2>
                </div>
            </div>
            @include('budget.reports.partials.bars', ['items' => $statusBreakdown, 'empty' => 'No status data available.'])
        </article>

        <article class="table-panel budget-report-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Office Request Summary</p>
                    <h2>Documents by Requesting Office</h2>
                </div>
            </div>
            @include('budget.reports.partials.bars', ['items' => $officeBreakdown, 'empty' => 'No requesting office data available.'])
        </article>

        <article class="table-panel budget-report-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Monthly Review Activity</p>
                    <h2>Completed Reviews by Month</h2>
                </div>
            </div>
            @include('budget.reports.partials.bars', ['items' => $monthlyActivity, 'empty' => 'No completed review activity yet.'])
        </article>

        <article class="table-panel budget-report-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Fund Source Summary</p>
                    <h2>Available Fund Sources</h2>
                </div>
            </div>
            @include('budget.reports.partials.bars', ['items' => $fundSources, 'empty' => 'No fund source data recorded yet.', 'showAmount' => true])
        </article>
    </section>

    <section class="table-panel" aria-label="Budget review summary table">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Report Table</p>
                <h2>Budget Review Summary</h2>
                <p>Filtered budget review records from actual procurement document data.</p>
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
                        <th>Budget Status</th>
                        <th>Reviewed By</th>
                        <th>Reviewed Date</th>
                        <th>Current Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        @php
                            $latestReview = $document->budgetReviews
                                ->sortByDesc(fn ($review) => $review->completed_at ?? $review->created_at)
                                ->first();
                            $reviewedBy = $document->budgetReviewedBy?->name ?? $latestReview?->reviewedBy?->name ?? 'N/A';
                            $reviewedDate = $document->budget_reviewed_at ?? $latestReview?->completed_at;
                        @endphp
                        <tr>
                            <td class="nowrap">{{ $document->tracking_number }}</td>
                            <td>{{ $document->document_type }}</td>
                            <td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $document->total_amount, 2) }}</td>
                            <td><span class="status-pill status-{{ $document->budget_status }}">{{ $document->budget_status ? str($document->budget_status)->replace('_', ' ')->title() : 'N/A' }}</span></td>
                            <td>{{ $reviewedBy }}</td>
                            <td class="nowrap">{{ $reviewedDate?->format('M d, Y') ?? 'N/A' }}</td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ str($document->status)->replace('_', ' ')->title() }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <strong>No budget report records found</strong>
                                    <p>Budget report data will appear after real procurement documents are routed or reviewed.</p>
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
@endsection
