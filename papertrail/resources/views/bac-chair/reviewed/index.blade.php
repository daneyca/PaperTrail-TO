@extends('layouts.dashboard')

@section('title', 'BAC Chair Reviewed Documents | PaperTrail')

@section('content')
    @php
        $label = fn ($value) => $value ? str($value)->replace('_', ' ')->title() : 'N/A';
        $outcome = function ($document) {
            if ($document->status === 'approved') {
                return 'Approved by Head of the Procuring Entity';
            }
            if ($document->status === 'returned_by_bac_chair' || $document->bac_chair_status === 'returned') {
                return match ($document->bac_chair_decision) {
                    'return_to_bac_member' => 'Returned to BAC Member',
                    'return_to_accounting' => 'Returned to Accounting Office',
                    'return_to_requesting_office' => 'Returned to Requesting Office',
                    default => 'Returned to BAC Secretariat',
                };
            }
            return 'Forwarded to Head of the Procuring Entity';
        };
        $destination = fn ($document) => $document->routingHistories->first(fn ($history) => in_array($history->action, ['Routed to Head of the Procuring Entity', 'Routed to Approving Authority', 'Returned by BAC Chair'], true))?->toOffice?->name ?? $document->currentOffice?->name ?? 'N/A';
        $activeOutcome = $filters['outcome'] ?? request('outcome');
        $activeDateFrom = $filters['date_from'] ?? request('date_from');
        $monthStart = now()->startOfMonth()->toDateString();
    @endphp

    <section class="dashboard-hero admin-users-hero pt-smooth-enter" style="--pt-delay: 0ms">
        <div>
            <p class="eyebrow">BAC Chair</p>
            <h1>Reviewed Documents</h1>
            <p>View documents already confirmed, forwarded, or returned by the BAC Chair.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern pt-smooth-enter" style="--pt-delay: 120ms" aria-label="BAC Chair reviewed documents summary">
        <x-dashboard.stat-card href="{{ route('bac-chair.reviewed.index') }}" value="{{ $summary['total'] }}" label="Total Reviewed" accent="gold" class="{{ blank($activeOutcome) && blank($activeDateFrom) ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-chair.reviewed.index', ['outcome' => 'forwarded']) }}" value="{{ $summary['forwarded'] }}" label="Forwarded to HOPE" accent="green" class="{{ $activeOutcome === 'forwarded' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-chair.reviewed.index', ['outcome' => 'returned']) }}" value="{{ $summary['returned'] }}" label="Returned Documents" accent="red" class="{{ $activeOutcome === 'returned' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-chair.reviewed.index', ['date_from' => $monthStart]) }}" value="{{ $summary['thisMonth'] }}" label="Reviewed This Month" accent="navy" class="{{ $activeDateFrom === $monthStart ? 'is-filter-active' : '' }}" />
    </section>

    <section class="table-panel pt-smooth-enter" style="--pt-delay: 220ms" aria-label="BAC Chair reviewed documents">
        <form method="GET" action="{{ route('bac-chair.reviewed.index') }}" class="budget-filter-toolbar">
            <div class="user-search">
                <label for="reviewed-search">Search documents</label>
                <input id="reviewed-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Tracking number, title, or requesting office">
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
                <label for="fiscal-year">Fiscal Year</label>
                <select id="fiscal-year" name="fiscal_year">
                    <option value="">All years</option>
                    @foreach ($fiscalYears as $year)
                        <option value="{{ $year }}" @selected((string) ($filters['fiscal_year'] ?? '') === (string) $year)>{{ $year }}</option>
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
                <label for="status-filter">Current Status</label>
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
                <a href="{{ route('bac-chair.reviewed.index') }}">Clear</a>
            </div>
        </form>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>Tracking Number</th>
                        <th>Document Type</th>
                        <th>Title</th>
                        <th>Requesting Office</th>
                        <th>Total Amount</th>
                        <th>Outcome</th>
                        <th>Current Status</th>
                        <th>Reviewed Date</th>
                        <th>Forwarded / Returned To</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        <tr>
                            <td class="nowrap">{{ $document->tracking_number }}</td>
                            <td>{{ $document->document_type }}</td>
                            <td>
                                <strong>{{ $document->title }}</strong>
                            </td>
                            <td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $document->total_amount, 2) }}</td>
                            <td>{{ $outcome($document) }}</td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ $label($document->status) }}</span></td>
                            <td class="nowrap">{{ $document->bac_chair_reviewed_at?->format('M d, Y') ?? $document->updated_at?->format('M d, Y') ?? 'N/A' }}</td>
                            <td>{{ $destination($document) }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('bac-chair.reviewed.show', $document) }}">View Details</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                <div class="empty-state">
                                    <strong>No reviewed documents yet</strong>
                                    <p>Documents will appear here after you confirm or return BAC Chair review records.</p>
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
