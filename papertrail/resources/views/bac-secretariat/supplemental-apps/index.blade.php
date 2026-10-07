@extends('layouts.dashboard')

@section('title', 'Supplemental APP | PaperTrail')

@section('content')
    @php
        $activeStatus = $filters['status'] ?? request('status');
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">BAC Secretariat</p>
            <h1>Supplemental APP</h1>
            <p>Create and manage Supplemental APP records for Purchase Requests without matching PPMP/APP references.</p>
        </div>

        <a href="{{ route('bac-secretariat.supplemental-apps.create') }}" class="dashboard-action">Create Supplemental APP</a>
    </section>

    <section class="stat-grid stat-grid-modern" aria-label="Supplemental APP summary">
        <x-dashboard.stat-card href="{{ route('bac-secretariat.supplemental-apps.index') }}" value="{{ $summary['total'] }}" label="Total Supplemental APPs" accent="navy" class="{{ blank($activeStatus) ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.supplemental-apps.index', ['status' => 'draft']) }}" value="{{ $summary['draft'] }}" label="Drafts" accent="gold" class="{{ $activeStatus === 'draft' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.supplemental-apps.index', ['status' => 'submitted']) }}" value="{{ $summary['submitted'] }}" label="Submitted" accent="blue" class="{{ $activeStatus === 'submitted' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.supplemental-apps.index', ['status' => 'accepted']) }}" value="{{ $summary['accepted'] }}" label="Accepted" accent="green" class="{{ $activeStatus === 'accepted' ? 'is-filter-active' : '' }}" />
    </section>

    <section class="table-panel" aria-label="Supplemental APP records">
        <x-document-filter-card :action="route('bac-secretariat.supplemental-apps.index')" class="app-filter-toolbar">
            <div class="user-search">
                <label for="sapp-search">Search Supplemental APP</label>
                <input id="sapp-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="SAPP number, PR number, title, or office">
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
                <label for="office-id">Requesting Office</label>
                <select id="office-id" name="office_id">
                    <option value="">All offices</option>
                    @foreach ($offices as $office)
                        <option value="{{ $office->id }}" @selected((string) ($filters['office_id'] ?? '') === (string) $office->id)>{{ $office->name }}</option>
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
                <a href="{{ route('bac-secretariat.supplemental-apps.index') }}">Clear</a>
            </div>
        </x-document-filter-card>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>Supplemental APP No.</th>
                        <th>Source PR</th>
                        <th>Requesting Office</th>
                        <th>Title</th>
                        <th>Fiscal Year</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Prepared By</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($supplementalApps as $supplementalApp)
                        <tr>
                            <td class="nowrap">{{ $supplementalApp->supplemental_app_number ?? 'Draft' }}</td>
                            <td class="nowrap">{{ $supplementalApp->sourcePrDocument?->pr_no ?? $supplementalApp->sourcePrDocument?->tracking_number ?? 'N/A' }}</td>
                            <td>{{ $supplementalApp->requestingOffice?->name ?? $supplementalApp->requesting_office_name ?? 'N/A' }}</td>
                            <td><strong>{{ $supplementalApp->title ?? 'Untitled Supplemental APP' }}</strong></td>
                            <td>{{ $supplementalApp->fiscal_year ?? 'N/A' }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $supplementalApp->total_amount, 2) }}</td>
                            <td><span class="status-pill status-{{ $supplementalApp->status }}">{{ str($supplementalApp->status)->replace('_', ' ')->title() }}</span></td>
                            <td>{{ $supplementalApp->preparedBy?->name ?? 'N/A' }}</td>
                            <td class="nowrap">{{ $supplementalApp->updated_at?->format('M d, Y') }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('bac-secretariat.supplemental-apps.show', $supplementalApp) }}">View</a>
                                    @if ($supplementalApp->isEditable())
                                        <a href="{{ route('bac-secretariat.supplemental-apps.edit', $supplementalApp) }}">Continue Editing</a>
                                    @endif
                                    <a href="{{ route('bac-secretariat.supplemental-apps.print', $supplementalApp) }}" target="_blank">Print</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><div class="empty-state"><strong>No Supplemental APP records yet</strong><p>Supplemental APP records created for PRs without PPMP/APP references will appear here.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($supplementalApps->hasPages())
            <div class="pagination-wrap">
                {{ $supplementalApps->appends(request()->query())->links('vendor.pagination.papertrail') }}
            </div>
        @endif
    </section>
@endsection
