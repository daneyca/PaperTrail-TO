@extends('layouts.dashboard')

@section('title', 'BAC Deliberations | PaperTrail')

@section('content')
    @php
        $label = fn ($value) => $value ? str($value)->replace('_', ' ')->title() : 'N/A';
        $activeStatus = $filters['status'] ?? request('status');
        $activeRecommendationStatus = $filters['recommendation_status'] ?? request('recommendation_status');
    @endphp

    <section class="dashboard-hero admin-users-hero pt-smooth-enter" style="--pt-delay: 0ms">
        <div>
            <p class="eyebrow">BAC Member</p>
            <h1>BAC Deliberations</h1>
            <p>View BAC deliberation records and submit member recommendations.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern pt-smooth-enter" style="--pt-delay: 120ms" aria-label="BAC deliberations summary">
        <x-dashboard.stat-card href="{{ route('bac-member.deliberations.index') }}" value="{{ $summary['active'] }}" label="Active Deliberations" accent="green" class="{{ blank($activeStatus) && blank($activeRecommendationStatus) ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-member.deliberations.index', ['recommendation_status' => 'pending']) }}" value="{{ $summary['pendingRecommendation'] }}" label="Pending Recommendation" accent="gold" class="{{ $activeRecommendationStatus === 'pending' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-member.deliberations.index', ['recommendation_status' => 'submitted']) }}" value="{{ $summary['submittedRecommendations'] }}" label="Submitted Recommendations" accent="blue" class="{{ $activeRecommendationStatus === 'submitted' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-member.deliberations.index', ['status' => \App\Models\BacDeliberation::STATUS_COMPLETED]) }}" value="{{ $summary['completed'] }}" label="Completed Deliberations" accent="green" class="{{ $activeStatus === \App\Models\BacDeliberation::STATUS_COMPLETED ? 'is-filter-active' : '' }}" />
    </section>

    <section class="table-panel pt-smooth-enter" style="--pt-delay: 220ms" aria-label="BAC deliberations list">
        <form method="GET" action="{{ route('bac-member.deliberations.index') }}" class="budget-filter-toolbar reviewed-filter-toolbar">
            <div class="user-search">
                <label for="deliberation-search">Search deliberations</label>
                <input id="deliberation-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Deliberation number, tracking number, title, or office">
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
                <label for="recommendation-status">Recommendation Status</label>
                <select id="recommendation-status" name="recommendation_status">
                    <option value="">All</option>
                    <option value="pending" @selected(($filters['recommendation_status'] ?? '') === 'pending')>Pending</option>
                    <option value="submitted" @selected(($filters['recommendation_status'] ?? '') === 'submitted')>Submitted</option>
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
                <a href="{{ route('bac-member.deliberations.index') }}">Clear</a>
            </div>
        </form>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>Deliberation Number</th>
                        <th>Document Tracking Number</th>
                        <th>Title / Agenda</th>
                        <th>Requesting Office</th>
                        <th>Document Type</th>
                        <th>Status</th>
                        <th>My Recommendation</th>
                        <th>Scheduled / Started Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($deliberations as $deliberation)
                        @php
                            $document = $deliberation->procurementDocument;
                            $participant = $deliberation->participants->firstWhere('user_id', auth()->id());
                            $date = $deliberation->started_at ?? $deliberation->scheduled_at ?? $deliberation->created_at;
                        @endphp
                        <tr>
                            <td class="nowrap">{{ $deliberation->deliberation_number ?? 'Pending No.' }}</td>
                            <td class="nowrap">{{ $document?->tracking_number ?? 'N/A' }}</td>
                            <td>
                                <strong>{{ $deliberation->title }}</strong>
                                @if ($deliberation->agenda)
                                    <br><span class="muted-text">{{ str($deliberation->agenda)->limit(80) }}</span>
                                @endif
                            </td>
                            <td>{{ $document?->submittingOffice?->name ?? 'N/A' }}</td>
                            <td>{{ $document?->document_type ?? 'N/A' }}</td>
                            <td><span class="status-pill status-{{ $deliberation->status }}">{{ $label($deliberation->status) }}</span></td>
                            <td>{{ $label($participant?->recommendation) }}</td>
                            <td class="nowrap">{{ $date?->format('M d, Y') ?? 'N/A' }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('bac-member.deliberations.show', $deliberation) }}">View Details</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <strong>No BAC deliberations yet</strong>
                                    <p>Deliberation records related to your assigned documents will appear here.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($deliberations->hasPages())
            <div class="pagination-wrap">
                {{ $deliberations->appends(request()->query())->links('vendor.pagination.papertrail') }}
            </div>
        @endif
    </section>
@endsection
