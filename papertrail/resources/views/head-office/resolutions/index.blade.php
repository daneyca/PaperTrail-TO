@extends('layouts.dashboard')

@section('title', 'Received BAC Resolutions | PaperTrail')

@section('content')
    @php
        $activeStatus = $filters['status'] ?? request('status');
        $activeWorkflow = $filters['workflow'] ?? request('workflow');
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Head Office / End User</p>
            <h1>Received BAC Resolutions</h1>
            <p>Review BAC Resolutions returned to your office and acknowledge them before continuing the SVP process.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern head-office-summary-grid" aria-label="Received BAC Resolution summary">
        <x-dashboard.stat-card href="{{ route('head-office.resolutions.index') }}" value="{{ $summary['received'] }}" label="Total Received" accent="gold" class="{{ blank($activeStatus) && blank($activeWorkflow) ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('head-office.resolutions.index', ['status' => 'awaiting']) }}" value="{{ $summary['awaiting'] }}" label="Awaiting Acknowledgment" accent="navy" class="{{ $activeStatus === 'awaiting' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('head-office.resolutions.index', ['workflow' => 'posting_required']) }}" value="{{ $summary['postingRequired'] }}" label="For BACSEC-004 Posting" accent="gold" class="{{ $activeWorkflow === 'posting_required' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('head-office.resolutions.index', ['workflow' => 'ready_for_rfq']) }}" value="{{ $summary['readyForRfq'] }}" label="Ready for RFQ" accent="green" class="{{ $activeWorkflow === 'ready_for_rfq' ? 'is-filter-active' : '' }}" />
    </section>

    <section class="table-panel" aria-label="Received BAC Resolutions">
        <x-document-filter-card :action="route('head-office.resolutions.index')">
            <div class="user-search">
                <label for="resolution-search">Search Resolutions</label>
                <input id="resolution-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Resolution no., PR no., title, or tracking number">
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
            <div class="filter-actions"><button type="submit">Apply</button><a href="{{ route('head-office.resolutions.index') }}">Clear</a></div>
        </x-document-filter-card>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>Resolution No.</th>
                        <th>Tracking Number</th>
                        <th>Source PR</th>
                        <th>Project Title</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Next Step</th>
                        <th>Returned Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($resolutions as $resolution)
                        @php($nextStep = $resolution->svp_next_step ?? [])
                        <tr>
                            <td class="nowrap">{{ $resolution->resolution_number ?? 'Draft' }}</td>
                            <td class="nowrap">{{ $resolution->document_reference_number ?? 'Pending' }}</td>
                            <td class="nowrap">{{ $resolution->sourcePrDocument?->pr_no ?? $resolution->sourcePrDocument?->tracking_number ?? 'N/A' }}</td>
                            <td>{{ str($resolution->project_title ?? $resolution->title ?? 'Untitled BAC Resolution')->limit(90) }}</td>
                            <td class="nowrap">PHP {{ number_format((float) ($resolution->total_amount ?? $resolution->abc_amount), 2) }}</td>
                            <td><span class="status-pill status-{{ $resolution->status }}">{{ str($resolution->status)->replace('_', ' ')->title() }}</span></td>
                            <td>
                                <span class="status-pill status-{{ $nextStep['key'] ?? $resolution->sourcePrDocument?->status ?? 'pending' }}" title="{{ $nextStep['description'] ?? '' }}">
                                    {{ $nextStep['label'] ?? $resolution->sourcePrDocument?->stage ?? 'N/A' }}
                                </span>
                            </td>
                            <td class="nowrap">{{ $resolution->returned_at?->format('M d, Y') ?? $resolution->updated_at?->format('M d, Y') }}</td>
                            <td><div class="table-actions"><a href="{{ route('head-office.resolutions.show', $resolution) }}">View</a></div></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <strong>No received BAC resolutions</strong>
                                    <p>BAC Resolutions returned by BAC Secretariat to your office will appear here.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($resolutions->hasPages())
            <div class="pagination-wrap">{{ $resolutions->appends(request()->query())->links('vendor.pagination.papertrail') }}</div>
        @endif
    </section>
@endsection
