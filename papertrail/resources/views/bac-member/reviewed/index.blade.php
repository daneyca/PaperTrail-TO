@extends('layouts.dashboard')

@section('title', 'BAC Member Reviewed Documents | PaperTrail')

@section('content')
    @php
        $label = fn ($value) => $value ? str($value)->replace('_', ' ')->title() : 'N/A';
        $money = fn ($amount) => 'PHP ' . number_format((float) $amount, 2);
        $activeOutcome = $filters['outcome'] ?? request('outcome');
        $activeDateFrom = $filters['date_from'] ?? request('date_from');
        $monthStart = now()->startOfMonth()->toDateString();
    @endphp

    <section class="dashboard-hero admin-users-hero pt-smooth-enter" style="--pt-delay: 0ms">
        <div>
            <p class="eyebrow">BAC Member</p>
            <h1>Reviewed Documents</h1>
            <p>View procurement documents already reviewed or acted on by the BAC Member.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern pt-smooth-enter" style="--pt-delay: 120ms" aria-label="BAC Member reviewed documents summary">
        <x-dashboard.stat-card href="{{ route('bac-member.reviewed.index') }}" value="{{ $summary['totalReviewed'] }}" label="Total Reviewed" accent="navy" class="{{ blank($activeOutcome) && blank($activeDateFrom) ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-member.reviewed.index', ['outcome' => 'endorsed']) }}" value="{{ $summary['endorsed'] }}" label="Endorsed to BAC Chair" accent="green" class="{{ $activeOutcome === 'endorsed' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-member.reviewed.index', ['outcome' => 'returned']) }}" value="{{ $summary['returned'] }}" label="Returned Documents" accent="red" class="{{ $activeOutcome === 'returned' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-member.reviewed.index', ['date_from' => $monthStart]) }}" value="{{ $summary['reviewedThisMonth'] }}" label="Reviewed This Month" accent="gold" class="{{ $activeDateFrom === $monthStart ? 'is-filter-active' : '' }}" />
    </section>

    <section class="table-panel pt-smooth-enter" style="--pt-delay: 220ms" aria-label="Reviewed BAC Member documents">
        <form method="GET" action="{{ route('bac-member.reviewed.index') }}" class="budget-filter-toolbar reviewed-filter-toolbar">
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
                    <option value="endorsed" @selected(($filters['outcome'] ?? '') === 'endorsed')>Endorsed</option>
                    <option value="returned" @selected(($filters['outcome'] ?? '') === 'returned')>Returned</option>
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
                <a href="{{ route('bac-member.reviewed.index') }}">Clear</a>
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
                        @php
                            $latestReview = $document->bacMemberReviews->sortByDesc(fn ($review) => $review->completed_at ?? $review->created_at)->first();
                            $latestAction = $document->routingHistories->first(fn ($history) => in_array($history->action, ['Returned by BAC Member', 'Endorsed by BAC Member', 'Routed to BAC Chair'], true));
                            $reviewedDate = $document->bac_member_reviewed_at ?? $latestReview?->completed_at ?? $latestAction?->action_at;
                            $isReturned = $document->status === \App\Models\ProcurementDocument::STATUS_RETURNED_BY_BAC_MEMBER;
                        @endphp
                        <tr>
                            <td class="nowrap">{{ $document->tracking_number }}</td>
                            <td>{{ $document->document_type }}</td>
                            <td>
                                <strong>{{ $document->title }}</strong>
                            </td>
                            <td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td>
                            <td class="nowrap">{{ $money($document->total_amount) }}</td>
                            <td><span class="status-pill status-{{ $isReturned ? 'returned_by_bac_member' : 'endorsed_by_bac_member' }}">{{ $isReturned ? 'Returned' : 'Endorsed to BAC Chair' }}</span></td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ $label($document->status) }}</span></td>
                            <td class="nowrap">{{ $reviewedDate?->format('M d, Y') ?? 'N/A' }}</td>
                            <td>{{ $latestAction?->toOffice?->name ?? $document->currentOffice?->name ?? $document->assignedTo?->office ?? 'N/A' }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('bac-member.reviewed.show', $document) }}">View Details</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                <div class="empty-state">
                                    <strong>No reviewed documents yet</strong>
                                    <p>Documents will appear here after you endorse or return assigned procurement records.</p>
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
