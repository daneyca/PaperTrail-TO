@extends('layouts.dashboard')

@section('title', 'Documents for Confirmation | PaperTrail')

@section('content')
    @php
        $label = fn ($value) => $value ? str($value)->replace('_', ' ')->title() : 'N/A';
        $latestMemberReview = fn ($document) => $document->bacMemberReviews->sortByDesc(fn ($review) => $review->completed_at ?? $review->created_at)->first();
        $latestDeliberation = fn ($document) => $document->bacDeliberations->sortByDesc(fn ($deliberation) => $deliberation->completed_at ?? $deliberation->updated_at)->first();
        $activeConfirmationStatus = $filters['confirmation_status'] ?? request('confirmation_status');
    @endphp

    <section class="dashboard-hero admin-users-hero pt-smooth-enter" style="--pt-delay: 0ms">
        <div>
            <p class="eyebrow">BAC Chair</p>
            <h1>Documents for Confirmation</h1>
            <p>Confirm BAC-reviewed procurement documents before forwarding them to the Head of the Procuring Entity.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern pt-smooth-enter" style="--pt-delay: 120ms" aria-label="BAC Chair confirmation summary">
        <x-dashboard.stat-card href="{{ route('bac-chair.confirmation.index') }}" value="{{ $summary['forConfirmation'] }}" label="For Confirmation" accent="gold" class="{{ blank($activeConfirmationStatus) ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-chair.reviewed.index', ['outcome' => 'forwarded']) }}" value="{{ $summary['confirmed'] }}" label="Confirmed" accent="green" />
        <x-dashboard.stat-card href="{{ route('bac-chair.reviewed.index', ['outcome' => 'returned']) }}" value="{{ $summary['returned'] }}" label="Returned" accent="red" />
        <x-dashboard.stat-card href="{{ route('bac-chair.reviewed.index', ['status' => \App\Models\ProcurementDocument::STATUS_PENDING_APPROVAL]) }}" value="{{ $summary['pendingApproval'] }}" label="Pending Approval" accent="navy" />
    </section>

    <section class="table-panel pt-smooth-enter" style="--pt-delay: 220ms" aria-label="Documents for confirmation">
        <form method="GET" action="{{ route('bac-chair.confirmation.index') }}" class="budget-filter-toolbar">
            <div class="user-search">
                <label for="confirmation-search">Search documents</label>
                <input id="confirmation-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Tracking number, title, or requesting office">
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
                <label for="confirmation-status">Confirmation Status</label>
                <select id="confirmation-status" name="confirmation_status">
                    <option value="">All confirmation</option>
                    @foreach ($confirmationStatuses as $status)
                        <option value="{{ $status }}" @selected(($filters['confirmation_status'] ?? '') === $status)>{{ $label($status) }}</option>
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
                <a href="{{ route('bac-chair.confirmation.index') }}">Clear</a>
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
                        <th>BAC Member Recommendation</th>
                        <th>Deliberation Status</th>
                        <th>Current Status</th>
                        <th>Review Started</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        @php
                            $memberReview = $latestMemberReview($document);
                            $deliberation = $latestDeliberation($document);
                        @endphp
                        <tr>
                            <td class="nowrap">{{ $document->tracking_number }}</td>
                            <td>{{ $document->document_type }}</td>
                            <td>
                                <strong>{{ $document->title }}</strong>
                            </td>
                            <td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $document->total_amount, 2) }}</td>
                            <td>{{ $label($memberReview?->recommendation) }}</td>
                            <td>{{ $label($deliberation?->status) }}</td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ $label($document->status) }}</span></td>
                            <td class="nowrap">{{ $document->bac_chair_review_started_at?->format('M d, Y') ?? 'N/A' }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('bac-chair.confirmation.show', $document) }}">View / Confirm</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                <div class="empty-state">
                                    <strong>No documents for confirmation</strong>
                                    <p>Documents under BAC Chair review will appear here once they are ready for confirmation.</p>
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
