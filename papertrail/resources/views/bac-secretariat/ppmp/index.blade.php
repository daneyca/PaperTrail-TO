@extends('layouts.dashboard')

@section('title', 'PPMP Review | PaperTrail')

@section('content')
    @php
        $ppmpStatusLabel = function (?string $status): string {
            return match ($status) {
                \App\Models\ProcurementDocument::STATUS_PENDING_PPMP_REVIEW => 'Submitted to BAC',
                \App\Models\ProcurementDocument::STATUS_UNDER_PPMP_REVIEW => 'Under APP Consolidation',
                \App\Models\ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT => 'Returned',
                \App\Models\ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION => 'Approved',
                default => str($status ?: 'Unknown')->replace('_', ' ')->title()->replace('Ppmp', 'PPMP')->toString(),
            };
        };

        $ppmpFilterFields = [
            'fiscal_year' => ['label' => 'Fiscal year', 'empty' => 'All years', 'options' => collect($fiscalYears)->mapWithKeys(fn ($year) => [$year => $year])->all()],
            'office_id' => ['label' => 'Requesting office', 'empty' => 'All offices', 'options' => $offices->pluck('name', 'id')->all()],
            'status' => ['label' => 'Status', 'empty' => 'All statuses', 'options' => collect($statuses)->mapWithKeys(fn ($status) => [$status => $ppmpStatusLabel($status)])->all()],
            'date_from' => ['label' => 'Updated from', 'type' => 'date'],
            'date_to' => ['label' => 'Updated to', 'type' => 'date'],
        ];
    @endphp

    @section('topbar-search')
        <x-document-list-controls :action="route('bac-secretariat.ppmp.index')" :filters="$filters" :fields="$ppmpFilterFields" />
    @endsection

    <section class="dashboard-hero admin-users-hero ppmp-page-hero">
        <div>
            <p class="eyebrow">BAC Secretariat</p>
            <h1>PPMP Review</h1>
            <p>Review PPMP submissions and accept eligible records for APP consolidation.</p>
        </div>
    </section>

    <section class="stat-grid-modern ppmp-summary-grid">
        <x-dashboard.stat-card href="{{ route('bac-secretariat.ppmp.index', ['status' => 'pending_ppmp_review']) }}" value="{{ $summary['submitted'] }}" label="Submitted to BAC" accent="blue" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.ppmp.index', ['status' => 'under_ppmp_review']) }}" value="{{ $summary['underReview'] }}" label="Under APP Consolidation" accent="blue" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.ppmp.index', ['status' => 'returned_by_bac_secretariat']) }}" value="{{ $summary['returned'] }}" label="Returned" accent="red" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.ppmp.index', ['status' => 'accepted_for_app_consolidation']) }}" value="{{ $summary['accepted'] }}" label="Approved" accent="green" />
    </section>

    <section class="table-panel">
        <x-document-filter-summary :action="route('bac-secretariat.ppmp.index')" :filters="$filters" :fields="$ppmpFilterFields" :total="$documents->total()" />

        <div class="table-scroll ppmp-table-scroll">
            <table class="user-management-table ppmp-registry-table head-office-doc-table">
                <thead>
                    <tr>
                        <th>Tracking Number</th>
                        <th>Requesting Office</th>
                        <th>Fiscal Year</th>
                        <th>Total Budget</th>
                        <th>Status</th>
                        <th>Submitted Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        <tr>
                            <td>{{ $document->tracking_number ?? $document->ppmp_no ?? 'Draft' }}</td>
                            <td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td>
                            <td>{{ $document->fiscal_year }}</td>
                            <td>PHP {{ number_format((float) $document->total_amount, 2) }}</td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ $ppmpStatusLabel($document->status) }}</span></td>
                            <td>{{ $document->submitted_at?->format('M d, Y') ?? $document->updated_at?->format('M d, Y') }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('bac-secretariat.ppmp.show', $document) }}">View / Review</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    @if (collect($filters)->contains(fn ($value) => filled($value)))
                                        <strong>No PPMPs match your search or filters</strong>
                                        <p>Try a different search or <a href="{{ route('bac-secretariat.ppmp.index') }}">clear search and filters</a>.</p>
                                    @else
                                    <strong>No PPMP documents for review</strong>
                                    <p>Submitted PPMP records from Head Office users will appear here.</p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $documents->links() }}
    </section>
@endsection
