@extends('layouts.dashboard')

@section('title', 'BAC Chair BAC Resolutions | PaperTrail')

@section('content')
    @php
        $label = fn ($value) => $value ? str($value)->replace('_', ' ')->title() : 'N/A';
        $money = fn ($amount) => 'PHP ' . number_format((float) $amount, 2);
        $activeStatus = $filters['status'] ?? request('status');
    @endphp

    <section class="dashboard-hero admin-users-hero pt-smooth-enter" style="--pt-delay: 0ms">
        <div>
            <p class="eyebrow">BAC Chair</p>
            <h1>BAC Resolutions</h1>
            <p>Review BAC Resolutions submitted by the BAC Secretariat, then confirm, return, or forward them to HOPE.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern pt-smooth-enter" style="--pt-delay: 120ms" aria-label="BAC Resolution summary">
        <x-dashboard.stat-card href="{{ route('bac-chair.resolutions.index', ['status' => \App\Models\BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR]) }}" value="{{ $summary['pending'] }}" label="Pending BAC Resolutions" accent="gold" class="{{ blank($activeStatus) || $activeStatus === \App\Models\BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-chair.resolutions.index', ['status' => \App\Models\BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR]) }}" value="{{ $summary['confirmed'] }}" label="Confirmed Resolutions" accent="green" class="{{ $activeStatus === \App\Models\BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-chair.resolutions.index', ['status' => \App\Models\BacResolution::STATUS_FORWARDED_TO_HOPE]) }}" value="{{ $summary['forwarded'] }}" label="Forwarded to HOPE" accent="navy" class="{{ $activeStatus === \App\Models\BacResolution::STATUS_FORWARDED_TO_HOPE ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-chair.resolutions.index', ['status' => \App\Models\BacResolution::STATUS_RETURNED_BY_BAC_CHAIR]) }}" value="{{ $summary['returned'] }}" label="Returned Resolutions" accent="red" class="{{ $activeStatus === \App\Models\BacResolution::STATUS_RETURNED_BY_BAC_CHAIR ? 'is-filter-active' : '' }}" />
    </section>

    <section class="table-panel pt-smooth-enter" style="--pt-delay: 220ms" aria-label="BAC Resolutions table">
        <form method="GET" action="{{ route('bac-chair.resolutions.index') }}" class="budget-filter-toolbar app-filter-toolbar">
            <div class="user-search">
                <label for="resolution-search">Search</label>
                <input id="resolution-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Resolution no., title, PR, office">
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
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label($status) }}</option>
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
                <a href="{{ route('bac-chair.resolutions.index') }}">Clear</a>
            </div>
        </form>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>Resolution No.</th>
                        <th>Title</th>
                        <th>Source PR</th>
                        <th>Requesting Office</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Submitted By</th>
                        <th>Submitted Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($resolutions as $resolution)
                        <tr>
                            <td class="nowrap">{{ $resolution->resolution_number ?: 'Pending No.' }}</td>
                            <td>{{ str($resolution->title ?? 'Untitled BAC Resolution')->limit(85) }}</td>
                            <td class="nowrap">{{ $resolution->sourcePrDocument?->tracking_number ?? $resolution->pr_number ?? 'N/A' }}</td>
                            <td>{{ $resolution->requesting_office_name ?? $resolution->sourcePrDocument?->submittingOffice?->name ?? 'N/A' }}</td>
                            <td class="nowrap">{{ $money($resolution->total_amount ?? $resolution->abc_amount) }}</td>
                            <td><span class="status-pill status-{{ $resolution->status }}">{{ $label($resolution->status) }}</span></td>
                            <td>{{ $resolution->submittedBy?->name ?? 'N/A' }}</td>
                            <td class="nowrap">{{ $resolution->submitted_at?->format('M d, Y') ?? 'N/A' }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('bac-chair.resolutions.show', $resolution) }}">View</a>
                                    <a href="{{ route('bac-chair.resolutions.print', $resolution) }}" target="_blank">Print</a>

                                    @if ($resolution->status === \App\Models\BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR)
                                        <a href="{{ route('bac-chair.resolutions.show', $resolution) }}">Sign / Return</a>
                                    @endif

                                    @if ($resolution->status === \App\Models\BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR)
                                        <a href="{{ route('bac-chair.resolutions.show', $resolution) }}">Review Signature</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <strong>No BAC Resolutions pending</strong>
                                    <p>BAC Resolutions submitted by the BAC Secretariat will appear here.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($resolutions->hasPages())
            <div class="pagination-wrap">
                {{ $resolutions->appends(request()->query())->links('vendor.pagination.papertrail') }}
            </div>
        @endif
    </section>
@endsection
