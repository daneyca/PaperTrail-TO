@extends('layouts.dashboard')

@section('title', 'Head of the Procuring Entity Reports | PaperTrail')

@section('content')
    @php
        $report = app(\App\Http\Controllers\ApprovingAuthority\ReportController::class);
        $money = fn ($amount) => 'PHP ' . number_format((float) $amount, 2);
    @endphp

    <section class="dashboard-hero admin-users-hero pt-smooth-enter" style="--pt-delay: 0ms">
        <div>
            <p class="eyebrow">Head of the Procuring Entity</p>
            <h1>Reports</h1>
            <p>Generate final approval, returned document, and procurement authorization summaries.</p>
        </div>

        <div class="report-header-actions">
            <x-ui.action-button
                :href="route('approving-authority.reports.export', request()->query())"
                icon="download"
                label="Download Report"
                tooltip="Download Report"
                variant="download"
            />
            <x-ui.action-button
                :href="route('approving-authority.reports.print', request()->query())"
                icon="print"
                label="Print Report"
                tooltip="Print Report"
                variant="print"
                target="_blank"
                rel="noopener"
            />
        </div>
    </section>

    <section class="stat-grid stat-grid-modern pt-smooth-enter" style="--pt-delay: 120ms" aria-label="Head of the Procuring Entity report summary">
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['pending'] }}" label="Pending Approval" accent="gold" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['underReview'] }}" label="Under Review" accent="blue" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['approved'] }}" label="Approved Documents" accent="green" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['returned'] }}" label="Returned Documents" accent="red" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['completed'] }}" label="Completed Documents" accent="green" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $money($summary['amountApproved']) }}" label="Total Amount Approved" accent="green" class="budget-amount-card" />
    </section>

    <section class="table-panel pt-smooth-enter" style="--pt-delay: 220ms" aria-label="Head of the Procuring Entity report filters">
        <form method="GET" action="{{ route('approving-authority.reports.index') }}" class="budget-filter-toolbar">
            <div class="user-filter"><label for="fiscal-year">Fiscal Year</label><select id="fiscal-year" name="fiscal_year"><option value="">All years</option>@foreach ($fiscalYears as $year)<option value="{{ $year }}" @selected((string) ($filters['fiscal_year'] ?? '') === (string) $year)>{{ $year }}</option>@endforeach</select></div>
            <div class="user-filter"><label for="report-type">Report Type</label><select id="report-type" name="report_type">@foreach ($reportTypes as $value => $text)<option value="{{ $value }}" @selected(($filters['report_type'] ?? 'all') === $value)>{{ $text }}</option>@endforeach</select></div>
            <div class="user-filter"><label for="document-type">Document Type</label><select id="document-type" name="document_type"><option value="">All types</option>@foreach ($documentTypes as $type)<option value="{{ $type }}" @selected(($filters['document_type'] ?? '') === $type)>{{ $type }}</option>@endforeach</select></div>
            <div class="user-filter"><label for="status-filter">Current Status</label><select id="status-filter" name="status"><option value="">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $report->label($status) }}</option>@endforeach</select></div>
            <div class="user-filter"><label for="outcome-filter">Approval Outcome</label><select id="outcome-filter" name="outcome"><option value="">All outcomes</option>@foreach ($outcomes as $value => $text)<option value="{{ $value }}" @selected(($filters['outcome'] ?? '') === $value)>{{ $text }}</option>@endforeach</select></div>
            <div class="user-filter"><label for="requesting-office">Requesting Office</label><select id="requesting-office" name="requesting_office"><option value="">All offices</option>@foreach ($offices as $office)<option value="{{ $office->id }}" @selected((string) ($filters['requesting_office'] ?? '') === (string) $office->id)>{{ $office->name }}</option>@endforeach</select></div>
            <div class="user-filter"><label for="date-from">Date From</label><input id="date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"></div>
            <div class="user-filter"><label for="date-to">Date To</label><input id="date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"></div>
            <div class="filter-actions"><button type="submit">Apply</button><a href="{{ route('approving-authority.reports.index') }}">Clear</a></div>
        </form>
    </section>

    <section class="table-panel pt-smooth-enter" style="--pt-delay: 300ms">
        <div class="panel-heading"><div><p class="eyebrow">Report Registry</p><h2>Filtered Approval Records</h2></div></div>
        <div class="table-scroll">
            <table class="user-management-table report-table">
                <thead><tr><th>Tracking Number</th><th>Type</th><th>Requesting Office</th><th>Total Amount</th><th>Outcome</th><th>Status</th><th>Activity Date</th><th>Current Office</th></tr></thead>
                <tbody>
                    @forelse ($documents as $document)
                        @php $activityDate = $document->approved_at ?? $document->returned_by_approving_authority_at ?? $document->approval_started_at ?? $document->updated_at; @endphp
                        <tr>
                            <td class="nowrap">{{ $document->tracking_number }}</td>
                            <td>{{ $document->document_type }}</td>
                            <td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td>
                            <td class="nowrap">{{ $money($document->total_amount) }}</td>
                            <td>{{ $report->outcome($document) }}</td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ $report->label($document->status) }}</span></td>
                            <td class="nowrap">{{ $activityDate?->format('M d, Y') ?? 'N/A' }}</td>
                            <td>{{ $document->currentOffice?->name ?? 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty-state"><strong>No report data available</strong><p>Head of the Procuring Entity reports will appear once documents are forwarded, approved, returned, or completed.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($documents->hasPages())
            <div class="pagination-wrap">{{ $documents->appends(request()->query())->links('vendor.pagination.papertrail') }}</div>
        @endif
    </section>

    <section class="dashboard-widget-grid pt-smooth-enter" style="--pt-delay: 380ms">
        <article class="dashboard-widget widget-wide">
            <div class="widget-heading"><div><p class="eyebrow">Pending Approval Summary</p><h2>Waiting for Final Action</h2></div></div>
            <div class="table-scroll"><table class="user-management-table"><thead><tr><th>Tracking Number</th><th>Type</th><th>Requesting Office</th><th>Title</th><th>Total Amount</th><th>BAC Chair Decision</th><th>Status</th><th>Stage</th><th>Forwarded Date</th><th>Pending Days</th></tr></thead><tbody>
                @forelse ($pendingApprovals->take(10) as $document)
                    @php $forwarded = $report->forwardedDate($document); @endphp
                    <tr><td class="nowrap">{{ $document->tracking_number }}</td><td>{{ $document->document_type }}</td><td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td><td>{{ $document->title ?? $document->purpose }}</td><td>{{ $money($document->total_amount) }}</td><td>{{ $report->label($document->bac_chair_decision) }}</td><td>{{ $report->label($document->status) }}</td><td>{{ $document->stage ?? 'N/A' }}</td><td>{{ $forwarded?->format('M d, Y') ?? 'N/A' }}</td><td>{{ $forwarded ? $forwarded->diffInDays(now()) : 0 }}</td></tr>
                @empty
                    <tr><td colspan="10"><div class="empty-state"><strong>No pending approval records</strong><p>No pending approval documents match the filters.</p></div></td></tr>
                @endforelse
            </tbody></table></div>
        </article>

        <article class="dashboard-widget widget-wide">
            <div class="widget-heading"><div><p class="eyebrow">Approved Documents Summary</p><h2>Final Approval Outcomes</h2></div></div>
            <div class="table-scroll"><table class="user-management-table"><thead><tr><th>Tracking Number</th><th>Type</th><th>Office</th><th>Title</th><th>Total Amount</th><th>Outcome</th><th>Approved By</th><th>Approved Date</th><th>Status</th><th>Current Office</th></tr></thead><tbody>
                @forelse ($approvedDocuments->take(10) as $document)
                    @php $latestApproval = $report->latestApproval($document); @endphp
                    <tr><td class="nowrap">{{ $document->tracking_number }}</td><td>{{ $document->document_type }}</td><td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td><td>{{ $document->title ?? $document->purpose }}</td><td>{{ $money($document->total_amount) }}</td><td>{{ $report->outcome($document) }}</td><td>{{ $document->approvedBy?->name ?? $latestApproval?->approvedBy?->name ?? 'N/A' }}</td><td>{{ $document->approved_at?->format('M d, Y') ?? $latestApproval?->completed_at?->format('M d, Y') ?? 'N/A' }}</td><td>{{ $report->label($document->status) }}</td><td>{{ $document->currentOffice?->name ?? 'N/A' }}</td></tr>
                @empty
                    <tr><td colspan="10"><div class="empty-state"><strong>No approved documents yet</strong><p>Documents will appear here after you approve records from the Pending Approval page.</p></div></td></tr>
                @endforelse
            </tbody></table></div>
        </article>

        <article class="dashboard-widget widget-wide">
            <div class="widget-heading"><div><p class="eyebrow">Returned Documents Summary</p><h2>Returned for Correction</h2></div></div>
            <div class="table-scroll"><table class="user-management-table"><thead><tr><th>Tracking Number</th><th>Type</th><th>Office</th><th>Title</th><th>Total Amount</th><th>Returned To</th><th>Return Reason</th><th>Returned Date</th><th>Status</th></tr></thead><tbody>
                @forelse ($returnedDocuments->take(10) as $document)
                    <tr><td class="nowrap">{{ $document->tracking_number }}</td><td>{{ $document->document_type }}</td><td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td><td>{{ $document->title ?? $document->purpose }}</td><td>{{ $money($document->total_amount) }}</td><td>{{ $report->returnTarget($document) }}</td><td>{{ str($document->approval_remarks ?? $report->latestApproval($document)?->remarks ?? 'N/A')->limit(80) }}</td><td>{{ $document->returned_by_approving_authority_at?->format('M d, Y') ?? $report->latestApproval($document)?->completed_at?->format('M d, Y') ?? 'N/A' }}</td><td>{{ $report->label($document->status) }}</td></tr>
                @empty
                    <tr><td colspan="9"><div class="empty-state"><strong>No returned documents</strong><p>Returned approval records will appear here.</p></div></td></tr>
                @endforelse
            </tbody></table></div>
        </article>

        <article class="dashboard-widget widget-wide">
            <div class="widget-heading"><div><p class="eyebrow">Completed Documents Summary</p><h2>Completed After Approval</h2></div></div>
            <div class="table-scroll"><table class="user-management-table"><thead><tr><th>Tracking Number</th><th>Type</th><th>Office</th><th>Title</th><th>Total Amount</th><th>Approved Date</th><th>Completed Date</th><th>Status</th><th>Current Office</th></tr></thead><tbody>
                @forelse ($completedDocuments->take(10) as $document)
                    <tr><td class="nowrap">{{ $document->tracking_number }}</td><td>{{ $document->document_type }}</td><td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td><td>{{ $document->title ?? $document->purpose }}</td><td>{{ $money($document->total_amount) }}</td><td>{{ $document->approved_at?->format('M d, Y') ?? 'N/A' }}</td><td>{{ $document->updated_at?->format('M d, Y') ?? 'N/A' }}</td><td>{{ $report->label($document->status) }}</td><td>{{ $document->currentOffice?->name ?? 'N/A' }}</td></tr>
                @empty
                    <tr><td colspan="9"><div class="empty-state"><strong>No completed documents</strong><p>Completed approval records will appear here.</p></div></td></tr>
                @endforelse
            </tbody></table></div>
        </article>

        <article class="dashboard-widget widget-wide">
            <div class="widget-heading"><div><p class="eyebrow">Document Type Summary</p><h2>Approval Activity by Type</h2></div></div>
            <div class="table-scroll"><table class="user-management-table"><thead><tr><th>Document Type</th><th>Total</th><th>Pending</th><th>Approved</th><th>Returned</th><th>Completed</th><th>Total Amount</th></tr></thead><tbody>
                @forelse ($documentTypeSummary as $type)
                    <tr><td>{{ $type['type'] }}</td><td>{{ $type['total'] }}</td><td>{{ $type['pending'] }}</td><td>{{ $type['approved'] }}</td><td>{{ $type['returned'] }}</td><td>{{ $type['completed'] }}</td><td>{{ $money($type['amount']) }}</td></tr>
                @empty
                    <tr><td colspan="7"><div class="empty-state"><strong>No document type summary</strong><p>Type totals will appear when records match the filters.</p></div></td></tr>
                @endforelse
            </tbody></table></div>
        </article>

        <article class="dashboard-widget widget-wide">
            <div class="widget-heading"><div><p class="eyebrow">Office Summary</p><h2>Requesting Office Totals</h2></div></div>
            <div class="table-scroll"><table class="user-management-table"><thead><tr><th>Office</th><th>Total</th><th>Pending</th><th>Approved</th><th>Returned</th><th>Completed</th><th>Total Amount Approved</th></tr></thead><tbody>
                @forelse ($officeSummary as $office)
                    <tr><td>{{ $office['office'] }}</td><td>{{ $office['total'] }}</td><td>{{ $office['pending'] }}</td><td>{{ $office['approved'] }}</td><td>{{ $office['returned'] }}</td><td>{{ $office['completed'] }}</td><td>{{ $money($office['amountApproved']) }}</td></tr>
                @empty
                    <tr><td colspan="7"><div class="empty-state"><strong>No office summary available</strong><p>Office totals will appear when records match the filters.</p></div></td></tr>
                @endforelse
            </tbody></table></div>
        </article>

        <article class="dashboard-widget">
            <div class="widget-heading"><div><p class="eyebrow">Monthly Approval Activity</p><h2>Activity Summary</h2></div></div>
            <div class="budget-review-record">
                @forelse ($monthlyActivity as $month)
                    <p><strong>{{ $month['month'] }}:</strong> {{ $month['received'] }} received, {{ $month['started'] }} started, {{ $month['approved'] }} approved, {{ $month['returned'] }} returned, {{ $month['deferred'] }} deferred, {{ $month['completed'] }} completed</p>
                @empty
                    <div class="empty-state"><strong>No monthly activity yet</strong><p>Monthly activity appears when approval records exist.</p></div>
                @endforelse
            </div>
        </article>

        <article class="dashboard-widget">
            <div class="widget-heading"><div><p class="eyebrow">Processing Time</p><h2>Timing Summary</h2></div></div>
            @if ($processing['hasData'])
                <div class="budget-review-record">
                    <p><strong>Forwarded to Start:</strong> {{ $processing['forwardedToStart'] ?? 'N/A' }} days average</p>
                    <p><strong>Start to Approval:</strong> {{ $processing['startToApproval'] ?? 'N/A' }} days average</p>
                    <p><strong>Forwarded to Return:</strong> {{ $processing['forwardedToReturn'] ?? 'N/A' }} days average</p>
                    <p><strong>Longest Pending:</strong> {{ $processing['longestPending']?->tracking_number ?? 'N/A' }}</p>
                    <p><strong>Pending More Than 3 Days:</strong> {{ $processing['overThreeDays'] }}</p>
                </div>
            @else
                <div class="empty-state"><strong>Processing time data is not available yet.</strong><p>Timing summaries need forwarding, start, approval, or return timestamps.</p></div>
            @endif
        </article>
    </section>
@endsection
