@extends('layouts.dashboard')

@section('title', 'BAC Resolutions | PaperTrail')

@section('content')
    @php
        $activeStatus = $filters['status'] ?? request('status');
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">BAC Secretariat</p>
            <h1>BAC Resolutions</h1>
            <p>Prepare official BAC Resolution drafts from eligible Purchase Requests and route them for electronic signatures.</p>
        </div>
        <a href="{{ route('bac-secretariat.resolutions.create') }}" class="dashboard-action">Create BAC Resolution</a>
    </section>

    <section class="stat-grid stat-grid-modern" aria-label="BAC Resolution summary">
        <x-dashboard.stat-card href="{{ route('bac-secretariat.resolutions.index', ['status' => 'draft']) }}" value="{{ $summary['draft'] }}" label="Drafts" accent="gold" class="{{ $activeStatus === 'draft' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.resolutions.index', ['status' => 'submitted_to_bac_chair']) }}" value="{{ $summary['submitted'] }}" label="For Signature" accent="blue" class="{{ $activeStatus === 'submitted_to_bac_chair' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.resolutions.index', ['status' => 'confirmed_by_bac_chair']) }}" value="{{ $summary['confirmed'] }}" label="Fully Signed" accent="green" class="{{ $activeStatus === 'confirmed_by_bac_chair' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.resolutions.index', ['status' => 'approved_by_hope']) }}" value="{{ $summary['approved'] }}" label="HOPE Approved" accent="green" class="{{ $activeStatus === 'approved_by_hope' ? 'is-filter-active' : '' }}" />
    </section>

    <section class="table-panel" aria-label="BAC Resolutions">
        <x-document-filter-card :action="route('bac-secretariat.resolutions.index')" class="app-filter-toolbar">
            <div class="user-search"><label for="resolution-search">Search</label><input id="resolution-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Resolution no., title, PR, office"></div>
            <div class="user-filter"><label for="fiscal-year">Fiscal Year</label><select id="fiscal-year" name="fiscal_year"><option value="">All years</option>@foreach ($fiscalYears as $year)<option value="{{ $year }}" @selected((string) ($filters['fiscal_year'] ?? '') === (string) $year)>{{ $year }}</option>@endforeach</select></div>
            <div class="user-filter"><label for="status-filter">Status</label><select id="status-filter" name="status"><option value="">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach</select></div>
            <div class="user-filter"><label for="date-from">Date From</label><input id="date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"></div>
            <div class="user-filter"><label for="date-to">Date To</label><input id="date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"></div>
            <div class="filter-actions"><button type="submit">Apply</button><a href="{{ route('bac-secretariat.resolutions.index') }}">Clear</a></div>
        </x-document-filter-card>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>Resolution No.</th>
                        <th>Tracking Number</th>
                        <th>Title</th>
                        <th>Source PR</th>
                        <th>Requesting Office</th>
                        <th>Status</th>
                        <th>Prepared By</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($resolutions as $resolution)
                        <tr>
                            <td class="nowrap">{{ $resolution->resolution_number ?: 'Draft' }}</td>
                            <td class="nowrap">{{ $resolution->document_reference_number ?? 'Pending' }}</td>
                            <td>{{ str($resolution->title ?? 'Untitled BAC Resolution')->limit(90) }}</td>
                            <td class="nowrap">{{ $resolution->sourcePrDocument?->tracking_number ?? $resolution->pr_number ?? 'N/A' }}</td>
                            <td>{{ $resolution->requesting_office_name ?? $resolution->sourcePrDocument?->submittingOffice?->name ?? 'N/A' }}</td>
                            <td><span class="status-pill status-{{ $resolution->status }}">{{ str($resolution->status)->replace('_', ' ')->title() }}</span></td>
                            <td>{{ $resolution->preparedBy?->name ?? 'N/A' }}</td>
                            <td class="nowrap">{{ $resolution->updated_at?->format('M d, Y') }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('bac-secretariat.resolutions.show', $resolution) }}">View</a>
                                    @if ($resolution->isEditable())
                                        <a href="{{ route('bac-secretariat.resolutions.edit', $resolution) }}">Continue Editing</a>
                                    @endif
                                    <a href="{{ route('bac-secretariat.resolutions.print', $resolution) }}" target="_blank">Print</a>
                                    @if ($resolution->canSubmit())
                                        <form method="POST" action="{{ route('bac-secretariat.resolutions.submit', $resolution) }}" onsubmit="return confirm('Submit this BAC Resolution for electronic signatures?');">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit">Submit for Signatures</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty-state"><strong>No BAC Resolutions yet</strong><p>Resolution drafts prepared from eligible Purchase Requests will appear here.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($resolutions->hasPages())
            <div class="pagination-wrap">{{ $resolutions->appends(request()->query())->links('vendor.pagination.papertrail') }}</div>
        @endif
    </section>
@endsection
