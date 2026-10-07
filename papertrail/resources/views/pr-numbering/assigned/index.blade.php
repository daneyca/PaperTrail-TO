@extends('layouts.dashboard')

@section('title', 'Assigned PR Numbers | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">PR Number Assignment</p>
            <h1>Assigned PR Numbers</h1>
            <p>Review Purchase Requests with official LGU PR numbers assigned by the PR Numbering Staff.</p>
        </div>

        <a href="{{ route('pr-numbering.pending.index') }}" class="dashboard-action">Pending Requests</a>
    </section>

    <section class="stat-grid stat-grid-modern" aria-label="PR numbering summary">
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['pending'] }}" label="Pending Requests" accent="gold" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['assignedToday'] }}" label="Assigned Today" accent="green" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['assignedByYou'] }}" label="Assigned by You" accent="navy" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['returned'] }}" label="Returned" accent="red" />
    </section>

    <section class="table-panel" aria-label="Assigned PR numbers">
        <form method="GET" action="{{ route('pr-numbering.assigned.index') }}" class="budget-filter-toolbar pr-filter-toolbar">
            <div class="user-search">
                <label for="assigned-pr-search">Search Assigned PRs</label>
                <input id="assigned-pr-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="PR number, tracking number, title, or office">
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
                <a href="{{ route('pr-numbering.assigned.index') }}">Clear</a>
            </div>
        </form>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>Official PR No.</th>
                        <th>Tracking Number</th>
                        <th>Requesting Office</th>
                        <th>Title</th>
                        <th>Assigned By</th>
                        <th>Assigned Date</th>
                        <th>Current Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        <tr>
                            <td class="nowrap"><strong>{{ $document->pr_no }}</strong></td>
                            <td class="nowrap">{{ $document->tracking_number ?? 'N/A' }}</td>
                            <td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td>
                            <td>
                                <strong>{{ $document->title ?? 'Purchase Request' }}</strong>
                            </td>
                            <td>{{ $document->prNoAssignedBy?->name ?? 'N/A' }}</td>
                            <td class="nowrap">{{ $document->pr_no_assigned_at?->format('M d, Y') ?? 'N/A' }}</td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ str($document->status)->replace('_', ' ')->title() }}</span></td>
                            <td>
                                <div class="table-actions">
                                    @if (Route::has('bac-secretariat.pr.show'))
                                        <a href="{{ route('bac-secretariat.pr.show', $document) }}">View Workflow</a>
                                    @else
                                        <span>N/A</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <strong>No assigned PR numbers yet</strong>
                                    <p>Completed PR number assignments will appear here.</p>
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
