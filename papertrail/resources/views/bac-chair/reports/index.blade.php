@extends('layouts.dashboard')

@section('title', 'BAC Chair Reports | PaperTrail')

@section('content')
    @php
        $label = fn ($value) => $value ? str($value)->replace('_', ' ')->title() : 'N/A';
        $money = fn ($amount) => 'PHP ' . number_format((float) $amount, 2);
        $memberRecommendation = fn ($document) => $document->bacMemberReviews->sortByDesc(fn ($review) => $review->completed_at ?? $review->created_at)->first()?->recommendation;
        $deliberationStatus = fn ($document) => $document->bacDeliberations->sortByDesc(fn ($item) => $item->completed_at ?? $item->updated_at)->first()?->status;
        $destination = fn ($document) => $document->routingHistories->first(fn ($history) => in_array($history->action, ['Routed to Head of the Procuring Entity', 'Routed to Approving Authority', 'Returned by BAC Chair'], true))?->toOffice?->name ?? $document->currentOffice?->name ?? 'N/A';
        $reviewedBy = fn ($document) => $document->bacChairReviewedBy?->name ?? $document->bacChairReviews->sortByDesc(fn ($review) => $review->completed_at ?? $review->created_at)->first()?->reviewedBy?->name ?? 'N/A';
        $reviewedDate = fn ($document) => $document->bac_chair_confirmed_at ?? $document->bac_chair_reviewed_at ?? $document->updated_at;
    @endphp

    <section class="dashboard-hero admin-users-hero pt-smooth-enter" style="--pt-delay: 0ms">
        <div>
            <p class="eyebrow">BAC Chair</p>
            <h1>Reports</h1>
            <p>Generate BAC Chair review, confirmation, return, and routing summaries.</p>
        </div>

        <div class="report-header-actions">
            <x-ui.action-button
                :href="route('bac-chair.reports.export', request()->query())"
                icon="download"
                label="Download Report"
                tooltip="Download Report"
                variant="download"
            />
            <x-ui.action-button
                :href="route('bac-chair.reports.print', request()->query())"
                icon="print"
                label="Print Report"
                tooltip="Print Report"
                variant="print"
                target="_blank"
                rel="noopener"
            />
        </div>
    </section>

    <section class="stat-grid stat-grid-modern pt-smooth-enter" style="--pt-delay: 120ms" aria-label="BAC Chair report summary">
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['pendingApprovals'] }}" label="Pending BAC Approvals" accent="gold" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['forConfirmation'] }}" label="Documents for Confirmation" accent="blue" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['confirmed'] }}" label="Confirmed Documents" accent="green" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['returned'] }}" label="Returned Documents" accent="red" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['forwarded'] }}" label="Forwarded to HOPE" accent="green" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['reviewedThisMonth'] }}" label="Reviewed This Month" accent="blue" />
    </section>

    <section class="table-panel pt-smooth-enter" style="--pt-delay: 220ms" aria-label="BAC Chair report filters">
        <form method="GET" action="{{ route('bac-chair.reports.index') }}" class="budget-filter-toolbar">
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
                <label for="report-type">Report Type</label>
                <select id="report-type" name="report_type">
                    @foreach ($reportTypes as $value => $text)
                        <option value="{{ $value }}" @selected(($filters['report_type'] ?? 'all') === $value)>{{ $text }}</option>
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
                <label for="status-filter">Current Status</label>
                <select id="status-filter" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="user-filter">
                <label for="outcome-filter">Outcome</label>
                <select id="outcome-filter" name="outcome">
                    <option value="">All outcomes</option>
                    @foreach ($outcomes as $value => $text)
                        <option value="{{ $value }}" @selected(($filters['outcome'] ?? '') === $value)>{{ $text }}</option>
                    @endforeach
                </select>
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
            <div class="user-filter"><label for="date-from">Date From</label><input id="date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"></div>
            <div class="user-filter"><label for="date-to">Date To</label><input id="date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"></div>
            <div class="filter-actions"><button type="submit">Apply</button><a href="{{ route('bac-chair.reports.index') }}">Clear</a></div>
        </form>
    </section>

    <section class="table-panel pt-smooth-enter" style="--pt-delay: 300ms">
        <div class="panel-heading"><div><p class="eyebrow">Report Registry</p><h2>Filtered BAC Chair Records</h2></div></div>
        <div class="table-scroll">
            <table class="user-management-table">
                <thead><tr><th>Tracking Number</th><th>Type</th><th>Requesting Office</th><th>Total Amount</th><th>Outcome</th><th>Status</th><th>Reviewed Date</th><th>Destination</th></tr></thead>
                <tbody>
                    @forelse ($documents as $document)
                        <tr>
                            <td class="nowrap">{{ $document->tracking_number }}</td>
                            <td>{{ $document->document_type }}</td>
                            <td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td>
                            <td class="nowrap">{{ $money($document->total_amount) }}</td>
                            <td>{{ app(\App\Http\Controllers\BacChair\ReportController::class)->outcome($document) }}</td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ $label($document->status) }}</span></td>
                            <td class="nowrap">{{ $reviewedDate($document)?->format('M d, Y') ?? 'N/A' }}</td>
                            <td>{{ $destination($document) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty-state"><strong>No report data available</strong><p>BAC Chair reports will appear once documents are assigned, reviewed, confirmed, or returned.</p></div></td></tr>
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
            <div class="widget-heading"><div><p class="eyebrow">BAC Approvals Summary</p><h2>Routed to BAC Chair</h2></div></div>
            <div class="table-scroll"><table class="user-management-table"><thead><tr><th>Tracking Number</th><th>Type</th><th>Requesting Office</th><th>Title</th><th>Total Amount</th><th>BAC Member Recommendation</th><th>Status</th><th>Stage</th><th>Assigned</th><th>Pending Days</th></tr></thead><tbody>
                @forelse ($approvals->take(10) as $document)
                    @php $assigned = $document->routed_at ?? $document->updated_at; @endphp
                    <tr><td class="nowrap">{{ $document->tracking_number }}</td><td>{{ $document->document_type }}</td><td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td><td>{{ $document->title ?? $document->purpose }}</td><td>{{ $money($document->total_amount) }}</td><td>{{ $label($memberRecommendation($document)) }}</td><td>{{ $label($document->status) }}</td><td>{{ $document->stage ?? 'N/A' }}</td><td>{{ $assigned?->format('M d, Y') ?? 'N/A' }}</td><td>{{ $assigned ? $assigned->diffInDays(now()) : 0 }}</td></tr>
                @empty
                    <tr><td colspan="10"><div class="empty-state"><strong>No BAC approval records</strong><p>No routed BAC Chair approval documents match the filters.</p></div></td></tr>
                @endforelse
            </tbody></table></div>
        </article>

        <article class="dashboard-widget widget-wide">
            <div class="widget-heading"><div><p class="eyebrow">Documents for Confirmation</p><h2>Under BAC Chair Review</h2></div></div>
            <div class="table-scroll"><table class="user-management-table"><thead><tr><th>Tracking Number</th><th>Type</th><th>Requesting Office</th><th>Total Amount</th><th>Deliberation</th><th>Recommendation</th><th>Review Started</th><th>Status</th><th>Action Status</th></tr></thead><tbody>
                @forelse ($confirmations->take(10) as $document)
                    <tr><td class="nowrap">{{ $document->tracking_number }}</td><td>{{ $document->document_type }}</td><td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td><td>{{ $money($document->total_amount) }}</td><td>{{ $label($deliberationStatus($document)) }}</td><td>{{ $label($memberRecommendation($document)) }}</td><td>{{ $document->bac_chair_review_started_at?->format('M d, Y') ?? 'N/A' }}</td><td>{{ $label($document->status) }}</td><td>{{ $label($document->bac_chair_confirmation_status ?? $document->bac_chair_status) }}</td></tr>
                @empty
                    <tr><td colspan="9"><div class="empty-state"><strong>No confirmation records</strong><p>No documents for confirmation match the filters.</p></div></td></tr>
                @endforelse
            </tbody></table></div>
        </article>

        <article class="dashboard-widget widget-wide">
            <div class="widget-heading"><div><p class="eyebrow">Confirmed / Forwarded</p><h2>Forwarded to Head of the Procuring Entity</h2></div></div>
            <div class="table-scroll"><table class="user-management-table"><thead><tr><th>Tracking Number</th><th>Type</th><th>Office</th><th>Total Amount</th><th>Confirmed By</th><th>Confirmed Date</th><th>Forwarded To</th><th>Status</th><th>Current Office</th></tr></thead><tbody>
                @forelse ($forwarded->take(10) as $document)
                    <tr><td class="nowrap">{{ $document->tracking_number }}</td><td>{{ $document->document_type }}</td><td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td><td>{{ $money($document->total_amount) }}</td><td>{{ $reviewedBy($document) }}</td><td>{{ ($document->bac_chair_confirmed_at ?? $document->bac_chair_reviewed_at)?->format('M d, Y') ?? 'N/A' }}</td><td>{{ $destination($document) }}</td><td>{{ $label($document->status) }}</td><td>{{ $document->currentOffice?->name ?? 'N/A' }}</td></tr>
                @empty
                    <tr><td colspan="9"><div class="empty-state"><strong>No forwarded documents</strong><p>Confirmed documents will appear here.</p></div></td></tr>
                @endforelse
            </tbody></table></div>
        </article>

        <article class="dashboard-widget widget-wide">
            <div class="widget-heading"><div><p class="eyebrow">Returned Documents</p><h2>Returned by BAC Chair</h2></div></div>
            <div class="table-scroll"><table class="user-management-table"><thead><tr><th>Tracking Number</th><th>Type</th><th>Office</th><th>Total Amount</th><th>Returned To</th><th>Return Reason</th><th>Returned Date</th><th>Status</th></tr></thead><tbody>
                @forelse ($returned->take(10) as $document)
                    <tr><td class="nowrap">{{ $document->tracking_number }}</td><td>{{ $document->document_type }}</td><td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td><td>{{ $money($document->total_amount) }}</td><td>{{ $destination($document) }}</td><td>{{ str($document->bac_chair_confirmation_remarks ?? $document->bac_chair_remarks ?? 'N/A')->limit(80) }}</td><td>{{ $document->bac_chair_reviewed_at?->format('M d, Y') ?? 'N/A' }}</td><td>{{ $label($document->status) }}</td></tr>
                @empty
                    <tr><td colspan="8"><div class="empty-state"><strong>No returned documents</strong><p>Returned BAC Chair records will appear here.</p></div></td></tr>
                @endforelse
            </tbody></table></div>
        </article>

        <article class="dashboard-widget widget-wide">
            <div class="widget-heading"><div><p class="eyebrow">BAC Deliberations</p><h2>Deliberation Outcomes</h2></div></div>
            <div class="table-scroll"><table class="user-management-table"><thead><tr><th>Deliberation Number</th><th>Document</th><th>Agenda</th><th>Office</th><th>Status</th><th>Participants</th><th>Recommendations</th><th>Chair</th><th>Started / Completed</th></tr></thead><tbody>
                @forelse ($deliberations->take(10) as $deliberation)
                    <tr><td>{{ $deliberation->deliberation_number ?? 'N/A' }}</td><td>{{ $deliberation->procurementDocument?->tracking_number ?? 'N/A' }}</td><td>{{ $deliberation->agenda ?? $deliberation->title }}</td><td>{{ $deliberation->procurementDocument?->submittingOffice?->name ?? 'N/A' }}</td><td>{{ $label($deliberation->status) }}</td><td>{{ $deliberation->participants->count() }}</td><td>{{ $deliberation->participants->whereNotNull('recommendation')->count() }}</td><td>{{ $deliberation->chair?->name ?? 'N/A' }}</td><td>{{ $deliberation->started_at?->format('M d, Y') ?? 'N/A' }} / {{ $deliberation->completed_at?->format('M d, Y') ?? 'N/A' }}</td></tr>
                @empty
                    <tr><td colspan="9"><div class="empty-state"><strong>No BAC deliberation records available yet.</strong><p>Deliberation records will appear after BAC deliberations are created.</p></div></td></tr>
                @endforelse
            </tbody></table></div>
        </article>

        <article class="dashboard-widget widget-wide">
            <div class="widget-heading"><div><p class="eyebrow">BAC Member Recommendations</p><h2>Recommendation Summary</h2></div></div>
            <div class="table-scroll"><table class="user-management-table"><thead><tr><th>Tracking Number</th><th>BAC Member</th><th>Recommendation</th><th>Remarks</th><th>Submitted Date</th><th>Status</th></tr></thead><tbody>
                @forelse ($memberRecommendations->take(10) as $row)
                    <tr><td class="nowrap">{{ $row['document']->tracking_number }}</td><td>{{ $row['review']->reviewedBy?->name ?? 'N/A' }}</td><td>{{ $label($row['review']->recommendation) }}</td><td>{{ $row['review']->remarks ?? 'N/A' }}</td><td>{{ $row['review']->completed_at?->format('M d, Y') ?? 'N/A' }}</td><td>{{ $label($row['document']->status) }}</td></tr>
                @empty
                    <tr><td colspan="6"><div class="empty-state"><strong>No BAC Member recommendations</strong><p>Recommendations will appear once BAC Members submit reviews.</p></div></td></tr>
                @endforelse
            </tbody></table></div>
        </article>

        <article class="dashboard-widget">
            <div class="widget-heading"><div><p class="eyebrow">Monthly Activity</p><h2>Activity Summary</h2></div></div>
            <div class="budget-review-record">
                @forelse ($monthlyActivity as $month)
                    <p><strong>{{ $month['month'] }}:</strong> {{ $month['received'] }} received, {{ $month['started'] }} started, {{ $month['confirmed'] }} confirmed, {{ $month['returned'] }} returned, {{ $month['forwarded'] }} forwarded</p>
                @empty
                    <div class="empty-state"><strong>No monthly activity yet</strong><p>Monthly activity appears when BAC Chair records exist.</p></div>
                @endforelse
            </div>
        </article>

        <article class="dashboard-widget">
            <div class="widget-heading"><div><p class="eyebrow">Processing Time</p><h2>Timing Summary</h2></div></div>
            @if ($processing['hasData'])
                <div class="budget-review-record">
                    <p><strong>Assignment to Start:</strong> {{ $processing['assignmentToStart'] ?? 'N/A' }} days average</p>
                    <p><strong>Start to Confirmation:</strong> {{ $processing['startToConfirmation'] ?? 'N/A' }} days average</p>
                    <p><strong>Assignment to Return:</strong> {{ $processing['assignmentToReturn'] ?? 'N/A' }} days average</p>
                    <p><strong>Longest Pending:</strong> {{ $processing['longestPending']?->tracking_number ?? 'N/A' }}</p>
                    <p><strong>Pending More Than 3 Days:</strong> {{ $processing['overThreeDays'] }}</p>
                </div>
            @else
                <div class="empty-state"><strong>Processing time data is not available yet.</strong><p>Timing summaries need BAC Chair assignment and review timestamps.</p></div>
            @endif
        </article>

        <article class="dashboard-widget widget-wide">
            <div class="widget-heading"><div><p class="eyebrow">Office Summary</p><h2>Requesting Office Totals</h2></div></div>
            <div class="table-scroll"><table class="user-management-table"><thead><tr><th>Office</th><th>Total</th><th>Pending</th><th>Under Review</th><th>Confirmed</th><th>Returned</th><th>Forwarded</th><th>Total Amount</th></tr></thead><tbody>
                @forelse ($officeSummary as $office)
                    <tr><td>{{ $office['office'] }}</td><td>{{ $office['total'] }}</td><td>{{ $office['pending'] }}</td><td>{{ $office['underReview'] }}</td><td>{{ $office['confirmed'] }}</td><td>{{ $office['returned'] }}</td><td>{{ $office['forwarded'] }}</td><td>{{ $money($office['amount']) }}</td></tr>
                @empty
                    <tr><td colspan="8"><div class="empty-state"><strong>No office summary available</strong><p>Office totals will appear when records match the filters.</p></div></td></tr>
                @endforelse
            </tbody></table></div>
        </article>
    </section>
@endsection
