@extends('layouts.dashboard')

@section('title', 'Purchase Requests | PaperTrail')

@section('content')
    @php
        $activeStatus = $filters['status'] ?? request('status');
        $activePrStatus = $filters['pr_status'] ?? request('pr_status');
        $statusLabel = fn ($value) => str($value)->replace('_', ' ')->title()->replace('Bac', 'BAC')->replace('Pr', 'PR')->replace('Ppmp', 'PPMP');
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">BAC Secretariat</p>
            <h1>Purchase Requests</h1>
            <p>Validate and route Purchase Requests submitted by end-user offices.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern" aria-label="Purchase Request summary">
        <x-dashboard.stat-card href="{{ route('bac-secretariat.pr.index', ['status' => 'submitted']) }}" value="{{ $summary['submitted'] }}" label="Submitted PRs" accent="blue" class="{{ in_array($activeStatus, ['submitted', \App\Models\ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION], true) ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.pr.index', ['status' => \App\Models\ProcurementDocument::STATUS_UNDER_PR_VALIDATION]) }}" value="{{ $summary['underValidation'] }}" label="Under Validation" accent="navy" class="{{ $activeStatus === \App\Models\ProcurementDocument::STATUS_UNDER_PR_VALIDATION ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.pr.index', ['pr_status' => \App\Models\ProcurementDocument::PR_STATUS_ROUTED_TO_BUDGET]) }}" value="{{ $summary['routedToBudget'] }}" label="Routed to Budget" accent="green" class="{{ $activePrStatus === \App\Models\ProcurementDocument::PR_STATUS_ROUTED_TO_BUDGET ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.pr.index', ['pr_status' => \App\Models\ProcurementDocument::PR_STATUS_RETURNED]) }}" value="{{ $summary['returned'] }}" label="Returned PRs" accent="red" class="{{ $activePrStatus === \App\Models\ProcurementDocument::PR_STATUS_RETURNED ? 'is-filter-active' : '' }}" />
    </section>

    <section class="table-panel" aria-label="BAC Secretariat Purchase Requests">
        <x-document-filter-card :action="route('bac-secretariat.pr.index')" class="pr-filter-toolbar">
            <div class="user-search">
                <label for="pr-search">Search PRs</label>
                <input id="pr-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Tracking number, title, or requesting office">
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
                <label for="pr-status">PR Status</label>
                <select id="pr-status" name="pr_status">
                    <option value="">All PR statuses</option>
                    @foreach ($prStatuses as $status)
                        <option value="{{ $status }}" @selected(($filters['pr_status'] ?? '') === $status)>{{ $statusLabel($status) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="current-status">Current Status</label>
                <select id="current-status" name="status">
                    <option value="">All workflow statuses</option>
                    @foreach ($currentStatuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $statusLabel($status) }}</option>
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
                <a href="{{ route('bac-secretariat.pr.index') }}">Clear</a>
            </div>
        </x-document-filter-card>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>Tracking Number</th>
                        <th>Title</th>
                        <th>Requesting Office</th>
                        <th>Total Amount</th>
                        <th>APP Reference</th>
                        <th>PR Status</th>
                        <th>Current Status</th>
                        <th>Submitted Date</th>
                        <th>Current Office</th>
                        <th>Current Handler</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        @php
                            $linkedResolution = $document->latestBacResolution;
                            $isIncomingReference = $document->status === \App\Models\ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION
                                && (int) $document->assigned_to_user_id === (int) auth()->id();
                            $currentStatusLabel = $isIncomingReference
                                ? 'Submitted PR'
                                : $statusLabel($document->status);
                        @endphp
                        <tr>
                            <td class="nowrap">{{ $document->tracking_number }}</td>
                            <td>
                                <strong>{{ $document->title }}</strong>
                            </td>
                            <td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $document->total_amount, 2) }}</td>
                            <td>{{ $document->appConsolidation?->app_number ?? ($document->app_item_id ? 'APP Item Linked' : 'No APP reference') }}</td>
                            <td><span class="status-pill status-{{ $document->pr_status ?? 'submitted' }}">{{ $document->pr_status ? $statusLabel($document->pr_status) : 'N/A' }}</span></td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ $currentStatusLabel }}</span></td>
                            <td class="nowrap">{{ $document->submitted_at?->format('M d, Y') ?? 'N/A' }}</td>
                            <td>{{ $document->currentOffice?->name ?? 'N/A' }}</td>
                            <td>{{ $document->assignedTo?->user_id ? $document->assignedTo->user_id.' - '.$document->assignedTo->name : 'Unassigned' }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('bac-secretariat.pr.show', $document) }}">View PR</a>
                                    @if ($linkedResolution)
                                        <a href="{{ route('bac-secretariat.resolutions.show', $linkedResolution) }}">View BAC Resolution</a>
                                    @else
                                        <a href="{{ route('bac-secretariat.resolutions.create', ['source_pr_document_id' => $document->id]) }}">Create BAC Resolution</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11">
                                <div class="empty-state">
                                    <strong>No purchase requests yet</strong>
                                    <p>Purchase Requests submitted by end-user offices will appear here for validation and routing.</p>
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
