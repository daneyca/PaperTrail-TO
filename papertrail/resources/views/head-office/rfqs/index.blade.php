@extends('layouts.dashboard')

@section('title', 'RFQ | PaperTrail')

@section('content')
    @php
        $activeStatus = $filters['status'] ?? request('status');
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Head Office</p>
            <h1>RFQ</h1>
            <p>Prepare Request for Quotation documents after BAC Resolution or approved procurement records.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern head-office-summary-grid" aria-label="RFQ summary">
        <x-dashboard.stat-card href="{{ route('head-office.rfqs.index', ['status' => 'draft']) }}" value="{{ $summary['draft'] }}" label="Draft" accent="gold" class="{{ $activeStatus === 'draft' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('head-office.rfqs.index', ['status' => 'submitted']) }}" value="{{ $summary['submitted'] }}" label="Submitted" accent="blue" class="{{ $activeStatus === 'submitted' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('head-office.rfqs.index', ['status' => 'issued']) }}" value="{{ $summary['issued'] }}" label="Issued" accent="navy" class="{{ $activeStatus === 'issued' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('head-office.rfqs.index', ['status' => 'quoted']) }}" value="{{ $summary['quoted'] }}" label="Quoted" accent="green" class="{{ $activeStatus === 'quoted' ? 'is-filter-active' : '' }}" />
    </section>

    @php
        $readyRfqPrSources = $readyRfqPrSources ?? collect();
        $readyRfqResolutionSources = $readyRfqResolutionSources ?? collect();
        $readyRfqSourceCount = $readyRfqPrSources->count() + $readyRfqResolutionSources->count();
    @endphp

    @if ($readyRfqSourceCount > 0)
        <section class="table-panel" aria-label="Ready for RFQ sources">
            <div class="compact-list-header">
                <div>
                    <p class="eyebrow">Ready for RFQ</p>
                    <h2>Sources ready for RFQ creation</h2>
                    <span>Ready PRs and completed BAC Resolutions appear here before an RFQ record is created.</span>
                </div>
                <span class="status-pill status-ready_for_rfq">{{ $readyRfqSourceCount }} Ready</span>
            </div>

            <div class="table-scroll">
                <table class="user-management-table">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Source PR</th>
                            <th>Project Title</th>
                            <th>ABC</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($readyRfqPrSources as $source)
                            <tr>
                                <td class="nowrap">Ready PR</td>
                                <td class="nowrap">{{ $source->pr_no ?? $source->tracking_number ?? 'N/A' }}</td>
                                <td>{{ str($source->title ?? $source->purpose ?? 'Untitled PR source')->limit(90) }}</td>
                                <td class="nowrap">PHP {{ number_format((float) $source->total_amount, 2) }}</td>
                                <td><span class="status-pill status-ready_for_rfq">BAC Resolution Pending</span></td>
                                <td>
                                    <a class="dashboard-action" href="{{ route('head-office.rfqs.create', ['source_pr_document_id' => $source->id]) }}">Create RFQ</a>
                                </td>
                            </tr>
                        @endforeach

                        @foreach ($readyRfqResolutionSources as $source)
                            @php($sourcePr = $source->sourcePrDocument)
                            <tr>
                                <td class="nowrap">{{ $source->resolution_number ?? 'BAC Resolution' }}</td>
                                <td class="nowrap">{{ $sourcePr?->pr_no ?? $sourcePr?->tracking_number ?? 'N/A' }}</td>
                                <td>{{ str($source->project_title ?? $source->title ?? $sourcePr?->title ?? 'Untitled source')->limit(90) }}</td>
                                <td class="nowrap">PHP {{ number_format((float) ($source->total_amount ?? $source->abc_amount ?? $sourcePr?->total_amount), 2) }}</td>
                                <td><span class="status-pill status-ready_for_rfq">Ready for RFQ</span></td>
                                <td>
                                    <a class="dashboard-action" href="{{ route('head-office.rfqs.create', ['source_bac_resolution_id' => $source->id]) }}">Create RFQ</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="table-panel" aria-label="RFQs">
        <x-document-filter-card :action="route('head-office.rfqs.index')">
            <div class="user-search">
                <label for="rfq-search">Search RFQs</label>
                <input id="rfq-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Tracking number, RFQ no., supplier, PR, or office">
            </div>
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
                <label for="status-filter">Status</label>
                <select id="status-filter" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="user-filter"><label for="date-from">Date From</label><input id="date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"></div>
            <div class="user-filter"><label for="date-to">Date To</label><input id="date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"></div>
            <div class="filter-actions"><button type="submit">Apply</button><a href="{{ route('head-office.rfqs.index') }}">Clear</a></div>
        </x-document-filter-card>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>RFQ No.</th>
                        <th>Tracking Number</th>
                        <th>Date</th>
                        <th>Purpose</th>
                        <th>ABC</th>
                        <th>Status</th>
                        <th>Prepared By</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rfqs as $rfq)
                        <tr>
                            <td class="nowrap">{{ $rfq->rfq_number ?? 'Draft' }}</td>
                            <td class="nowrap">{{ $rfq->document_reference_number ?? 'Pending' }}</td>
                            <td class="nowrap">{{ $rfq->rfq_date?->format('M d, Y') ?? 'N/A' }}</td>
                            <td>{{ str($rfq->purpose ?? 'Untitled RFQ')->limit(90) }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $rfq->abc_amount, 2) }}</td>
                            <td><span class="status-pill status-{{ $rfq->status }}">{{ str($rfq->status)->replace('_', ' ')->title() }}</span></td>
                            <td>{{ $rfq->preparedBy?->name ?? 'N/A' }}</td>
                            <td class="nowrap">{{ $rfq->updated_at?->format('M d, Y') }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('head-office.rfqs.show', $rfq) }}">View</a>
                                    @if ($rfq->isEditable())
                                        <a href="{{ route('head-office.rfqs.edit', $rfq) }}">Continue Editing</a>
                                    @endif
                                    <a href="{{ route('head-office.rfqs.print', $rfq) }}" target="_blank">Print</a>
                                    @if ($rfq->canSubmit())
                                        <form method="POST" action="{{ route('head-office.rfqs.submit', $rfq) }}" onsubmit="return confirm('Submit this RFQ?');">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit">Submit</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty-state"><strong>No RFQs yet</strong><p>RFQs prepared by Head Office will appear here.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($rfqs->hasPages())
            <div class="pagination-wrap">{{ $rfqs->appends(request()->query())->links('vendor.pagination.papertrail') }}</div>
        @endif
    </section>
@endsection
