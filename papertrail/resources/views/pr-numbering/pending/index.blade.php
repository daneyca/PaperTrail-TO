@extends('layouts.dashboard')

@section('title', 'Pending PR Number Requests | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">PR Number Assignment</p>
            <h1>Pending PR Number Requests</h1>
            <p>Assign official LGU Purchase Request numbers before BAC Secretariat validation.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern" aria-label="PR numbering summary">
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['pending'] }}" label="Pending Requests" accent="gold" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['assignedToday'] }}" label="Assigned Today" accent="green" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['assignedByYou'] }}" label="Assigned by You" accent="navy" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['returned'] }}" label="Returned" accent="red" />
    </section>

    <section class="table-panel" aria-label="Pending PR number requests">
        <form method="GET" action="{{ route('pr-numbering.pending.index') }}" class="budget-filter-toolbar pr-filter-toolbar">
            <div class="user-search">
                <label for="pr-number-search">Search Requests</label>
                <input id="pr-number-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Tracking number, title, or office">
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
                <label for="date-from">Date From</label>
                <input id="date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}">
            </div>

            <div class="user-filter">
                <label for="date-to">Date To</label>
                <input id="date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}">
            </div>

            <div class="filter-actions">
                <button type="submit">Apply</button>
                <a href="{{ route('pr-numbering.pending.index') }}">Clear</a>
            </div>
        </form>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>Tracking Number</th>
                        <th>Requesting Office</th>
                        <th>Title</th>
                        <th>Total Amount</th>
                        <th>Submitted Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        <tr>
                            <td class="nowrap">{{ $document->tracking_number ?? 'Pending tracking' }}</td>
                            <td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td>
                            <td>
                                <strong>{{ $document->title ?? 'Purchase Request' }}</strong>
                            </td>
                            <td class="nowrap">PHP {{ number_format((float) $document->total_amount, 2) }}</td>
                            <td class="nowrap">{{ $document->pr_no_requested_at?->format('M d, Y') ?? $document->submitted_at?->format('M d, Y') ?? 'N/A' }}</td>
                            <td><span class="status-pill status-{{ $document->status }}">Pending Assignment</span></td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('pr-numbering.pending.show', $document) }}">View / Assign PR No.</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <strong>No pending PR number requests</strong>
                                    <p>Purchase Requests submitted for official PR number assignment will appear here.</p>
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
