@extends('layouts.dashboard')

@section('title', 'BAC Secretariat Reports | PaperTrail')

@section('content')
    @php
        $money = fn ($amount) => 'PHP ' . number_format((float) $amount, 2);
        $label = fn ($value) => $value ? str($value)->replace('_', ' ')->title() : 'N/A';
    @endphp

    <section class="dashboard-hero admin-users-hero pt-smooth-enter" style="--pt-delay: 0ms">
        <div>
            <p class="eyebrow">BAC Secretariat</p>
            <h1>Reports</h1>
            <p>Generate consolidated summaries of incoming documents, routing activity, APP, PR, and PO processing.</p>
        </div>
        <div class="hero-actions">
            <x-ui.action-button
                :href="route('bac-secretariat.reports.export', request()->query())"
                icon="download"
                label="Download Report"
                tooltip="Download Report"
                variant="download"
            />
            <x-ui.action-button
                :href="route('bac-secretariat.reports.print', request()->query())"
                icon="print"
                label="Print Report"
                tooltip="Print Report"
                variant="print"
                target="_blank"
                rel="noopener"
            />
        </div>
    </section>

    <section class="stat-grid stat-grid-modern pt-smooth-enter" style="--pt-delay: 120ms" aria-label="BAC Secretariat reports summary">
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['incoming'] }}" label="Incoming Documents" accent="gold" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['routed'] }}" label="Routed Documents" accent="navy" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['apps'] }}" label="APP Consolidations" accent="green" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['prs'] }}" label="Purchase Requests" accent="blue" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['pos'] }}" label="Purchase Orders" accent="gold" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['returned'] }}" label="Returned Documents" accent="red" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['completed'] }}" label="Completed Documents" accent="green" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $money($summary['amount']) }}" label="Total Amount Processed" accent="navy" />
    </section>

    <section class="table-panel pt-smooth-enter" style="--pt-delay: 220ms">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Report Filters</p>
                <h2>Refine Report Output</h2>
            </div>
        </div>

        <x-document-filter-card :action="route('bac-secretariat.reports.index')" class="app-filter-toolbar">
            <div class="user-filter">
                <label for="fiscal-year">Fiscal Year</label>
                <input id="fiscal-year" name="fiscal_year" type="number" value="{{ $filters['fiscal_year'] ?? '' }}" placeholder="{{ now()->year }}">
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
                <label for="status-filter">Status</label>
                <select id="status-filter" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="user-filter">
                <label for="office-id">Requesting Office</label>
                <select id="office-id" name="office_id">
                    <option value="">All offices</option>
                    @foreach ($offices as $office)
                        <option value="{{ $office->id }}" @selected((string) ($filters['office_id'] ?? '') === (string) $office->id)>{{ $office->name }}</option>
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
            <div class="filter-actions">
                <button type="submit">Apply</button>
                <a href="{{ route('bac-secretariat.reports.index') }}">Clear</a>
            </div>
        </x-document-filter-card>
    </section>

    <section class="table-panel pt-smooth-enter" style="--pt-delay: 300ms">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Incoming Documents</p>
                <h2>Incoming Documents Summary</h2>
            </div>
        </div>
        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr><th>Tracking Number</th><th>Document Type</th><th>Requesting Office</th><th>Total Amount</th><th>Status</th><th>Current Office</th><th>Updated</th></tr>
                </thead>
                <tbody>
                    @forelse ($incoming as $document)
                        <tr>
                            <td class="nowrap">{{ $document->displayNumber() }}</td>
                            <td>{{ $document->document_type }}</td>
                            <td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td>
                            <td>{{ $money($document->total_amount) }}</td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ $label($document->status) }}</span></td>
                            <td>{{ $document->currentOffice?->name ?? 'N/A' }}</td>
                            <td>{{ $document->updated_at?->format('M d, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty-state"><strong>No incoming records found</strong><p>BAC Secretariat incoming documents will appear here when available.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="table-panel pt-smooth-enter" style="--pt-delay: 340ms">
        <div class="section-heading"><div><p class="eyebrow">Routing</p><h2>Document Routing Summary</h2></div></div>
        <div class="table-scroll">
            <table class="user-management-table">
                <thead><tr><th>Tracking Number</th><th>Title</th><th>Stage</th><th>Latest Action By</th><th>Latest Routed Date</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($routing as $document)
                        @php $latestHistory = $document->routingHistories->first(); @endphp
                        <tr>
                            <td class="nowrap">{{ $document->displayNumber() }}</td>
                            <td>{{ $document->title }}</td>
                            <td>{{ $document->stage ?? 'N/A' }}</td>
                            <td>{{ $latestHistory?->actionBy?->name ?? 'N/A' }}</td>
                            <td>{{ $latestHistory?->action_at?->format('M d, Y') ?? $document->routed_at?->format('M d, Y') ?? 'N/A' }}</td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ $label($document->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state"><strong>No routing records found</strong><p>Document routing history will appear here after routing actions are recorded.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="table-panel pt-smooth-enter" style="--pt-delay: 380ms">
        <div class="section-heading"><div><p class="eyebrow">APP</p><h2>APP Consolidation Summary</h2></div></div>
        <div class="table-scroll">
            <table class="user-management-table">
                <thead><tr><th>Tracking Number</th><th>APP Number</th><th>Fiscal Year</th><th>Title</th><th>PPMP Sources</th><th>Items</th><th>Total Amount</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($apps as $app)
                        <tr>
                            <td class="nowrap">{{ $app->displayNumber() }}</td>
                            <td class="nowrap">{{ $app->app_number ?? 'Draft' }}</td>
                            <td>{{ $app->fiscal_year }}</td>
                            <td>{{ $app->title }}</td>
                            <td>{{ $app->ppmpSources->count() }}</td>
                            <td>{{ $app->appItems->count() }}</td>
                            <td>{{ $money($app->total_amount) }}</td>
                            <td><span class="status-pill status-{{ $app->status }}">{{ $label($app->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty-state"><strong>No APP records found</strong><p>APP consolidation records will appear after actual consolidation activity.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="table-panel pt-smooth-enter" style="--pt-delay: 420ms">
        <div class="section-heading"><div><p class="eyebrow">Requests</p><h2>Purchase Requests Summary</h2></div></div>
        <div class="table-scroll">
            <table class="user-management-table">
                <thead><tr><th>Tracking Number</th><th>Official PR No.</th><th>Requesting Office</th><th>Title</th><th>Total Amount</th><th>PR Status</th><th>Current Status</th><th>Updated</th></tr></thead>
                <tbody>
                    @forelse ($prs as $document)
                        <tr>
                            <td class="nowrap">{{ $document->displayNumber() }}</td>
                            <td class="nowrap">{{ $document->pr_no ?? 'N/A' }}</td>
                            <td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td>
                            <td>{{ $document->title ?? $document->purpose }}</td>
                            <td>{{ $money($document->total_amount) }}</td>
                            <td>{{ $label($document->pr_status) }}</td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ $label($document->status) }}</span></td>
                            <td>{{ $document->updated_at?->format('M d, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty-state"><strong>No purchase request records found</strong><p>Purchase request activity will appear here once submitted through the workflow.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="table-panel pt-smooth-enter" style="--pt-delay: 460ms">
        <div class="section-heading"><div><p class="eyebrow">Orders</p><h2>Purchase Orders Summary</h2></div></div>
        <div class="table-scroll">
            <table class="user-management-table">
                <thead><tr><th>Tracking Number</th><th>PO Number</th><th>Source PR</th><th>Supplier</th><th>Requesting Office</th><th>Total Amount</th><th>Status</th><th>Updated</th></tr></thead>
                <tbody>
                    @forelse ($pos as $po)
                        <tr>
                            <td class="nowrap">{{ $po->displayNumber() }}</td>
                            <td class="nowrap">{{ $po->po_number ?? 'Draft' }}</td>
                            <td class="nowrap">{{ $po->sourcePrDocument?->displayNumber() ?? 'N/A' }}</td>
                            <td>{{ $po->supplier_name ?? 'N/A' }}</td>
                            <td>{{ $po->sourcePrDocument?->submittingOffice?->name ?? 'N/A' }}</td>
                            <td>{{ $money($po->total_amount) }}</td>
                            <td><span class="status-pill status-{{ $po->status }}">{{ $label($po->status) }}</span></td>
                            <td>{{ $po->updated_at?->format('M d, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty-state"><strong>No purchase order records found</strong><p>Purchase orders will appear here after preparation or issuance.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="table-panel pt-smooth-enter" style="--pt-delay: 500ms">
        <div class="section-heading"><div><p class="eyebrow">Returned</p><h2>Returned Documents Summary</h2></div></div>
        <div class="table-scroll">
            <table class="user-management-table">
                <thead><tr><th>Tracking Number</th><th>Document Type</th><th>Requesting Office</th><th>Return Remarks</th><th>Returned Date</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($returned as $document)
                        @php $latestReturn = $document->routingHistories->first(); @endphp
                        <tr>
                            <td class="nowrap">{{ $document->displayNumber() }}</td>
                            <td>{{ $document->document_type }}</td>
                            <td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td>
                            <td>{{ $latestReturn?->comments ?? $document->remarks ?? $document->bac_secretariat_remarks ?? 'N/A' }}</td>
                            <td>{{ $latestReturn?->action_at?->format('M d, Y') ?? $document->updated_at?->format('M d, Y') }}</td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ $label($document->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state"><strong>No returned records found</strong><p>Returned BAC Secretariat documents will appear here only after real return actions.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="table-panel pt-smooth-enter" style="--pt-delay: 540ms">
        <div class="section-heading"><div><p class="eyebrow">Activity</p><h2>Monthly Activity Summary</h2></div></div>
        <div class="table-scroll">
            <table class="user-management-table">
                <thead><tr><th>Month</th><th>Incoming</th><th>Routed</th><th>APPs</th><th>PRs</th><th>PO Issued</th><th>PO Completed</th><th>Returned</th></tr></thead>
                <tbody>
                    @forelse ($monthly as $month => $data)
                        <tr>
                            <td class="nowrap">{{ $month }}</td>
                            <td>{{ $data['incoming'] }}</td>
                            <td>{{ $data['routed'] }}</td>
                            <td>{{ $data['apps'] }}</td>
                            <td>{{ $data['prs'] }}</td>
                            <td>{{ $data['poIssued'] }}</td>
                            <td>{{ $data['poCompleted'] }}</td>
                            <td>{{ $data['returned'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty-state"><strong>No monthly activity found</strong><p>Monthly totals will build from existing workflow records.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="table-panel pt-smooth-enter" style="--pt-delay: 580ms">
        <div class="section-heading"><div><p class="eyebrow">Offices</p><h2>Office Request Summary</h2></div></div>
        <div class="table-scroll">
            <table class="user-management-table">
                <thead><tr><th>Requesting Office</th><th>Documents</th><th>Purchase Requests</th><th>Returned</th><th>Completed</th><th>Total Amount</th></tr></thead>
                <tbody>
                    @forelse ($officeSummary as $office => $data)
                        <tr>
                            <td>{{ $office }}</td>
                            <td>{{ $data['documents'] }}</td>
                            <td>{{ $data['prs'] }}</td>
                            <td>{{ $data['returned'] }}</td>
                            <td>{{ $data['completed'] }}</td>
                            <td>{{ $money($data['amount']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state"><strong>No office summary found</strong><p>Office totals will appear once purchase request records exist.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($processing['available'] || $processing['longestPending'])
        <section class="table-panel pt-smooth-enter" style="--pt-delay: 620ms">
            <div class="section-heading"><div><p class="eyebrow">Processing Time</p><h2>Processing Time Snapshot</h2></div></div>
            <div class="budget-detail-grid">
                <div><span>Average Receipt to Routing</span><strong>{{ is_null($processing['receiptToRouting']) ? 'N/A' : number_format($processing['receiptToRouting'], 1) . ' days' }}</strong></div>
                <div><span>Average PR Receipt to Validation</span><strong>{{ is_null($processing['prToBudget']) ? 'N/A' : number_format($processing['prToBudget'], 1) . ' days' }}</strong></div>
                <div><span>Average PO Preparation to Issue</span><strong>{{ is_null($processing['poToIssue']) ? 'N/A' : number_format($processing['poToIssue'], 1) . ' days' }}</strong></div>
                <div><span>Incoming Over 3 Days</span><strong>{{ $processing['overThreeDays'] }}</strong></div>
            </div>
        </section>
    @endif
@endsection
