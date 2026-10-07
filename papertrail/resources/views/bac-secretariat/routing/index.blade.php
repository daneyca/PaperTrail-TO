@extends('layouts.dashboard')

@section('title', 'Document Routing | PaperTrail')

@section('content')
    @php
        $activeStatus = $filters['status'] ?? request('status');
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">BAC Secretariat</p>
            <h1>Document Routing</h1>
            <p>Assign ready procurement documents to the next authorized reviewer or approving office.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern" aria-label="BAC Secretariat routing summary">
        <x-dashboard.stat-card href="{{ route('bac-secretariat.routing.index', ['status' => \App\Models\ProcurementDocument::STATUS_READY_FOR_DOCUMENT_ROUTING]) }}" value="{{ $summary['ready'] }}" label="Ready for Routing" accent="gold" class="{{ blank($activeStatus) || $activeStatus === \App\Models\ProcurementDocument::STATUS_READY_FOR_DOCUMENT_ROUTING ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.routing.index', ['status' => 'bac_member']) }}" value="{{ $summary['bacMember'] }}" label="Routed to BAC Member" accent="blue" class="{{ $activeStatus === 'bac_member' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.routing.index', ['status' => 'bac_chair']) }}" value="{{ $summary['bacChair'] }}" label="Routed to BAC Chair" accent="navy" class="{{ $activeStatus === 'bac_chair' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.routing.index', ['status' => 'approval']) }}" value="{{ $summary['approval'] }}" label="Routed for Approval" accent="green" class="{{ $activeStatus === 'approval' ? 'is-filter-active' : '' }}" />
    </section>

    <section class="table-panel" aria-label="BAC Secretariat document routing list">
        <x-document-filter-card :action="route('bac-secretariat.routing.index')" class="bac-routing-filter">
            <div class="user-search">
                <label for="routing-search">Search documents</label>
                <input id="routing-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Tracking number, title, or requesting office">
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
                <label for="status-filter">Current Status</label>
                <select id="status-filter" name="status">
                    <option value="">All routing statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="destination-role">Destination Role</label>
                <select id="destination-role" name="destination_role">
                    <option value="">All destinations</option>
                    @foreach ($destinationRoles as $label)
                        <option value="{{ $label }}" @selected(($filters['destination_role'] ?? '') === $label)>{{ $label }}</option>
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
                <a href="{{ route('bac-secretariat.routing.index') }}">Clear</a>
            </div>
        </x-document-filter-card>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>Tracking Number</th>
                        <th>Tracking Number</th>
                        <th>Document Type</th>
                        <th>Title</th>
                        <th>Requesting Office</th>
                        <th>Total Amount</th>
                        <th>Current Status</th>
                        <th>Current Stage</th>
                        <th>Last Routed To</th>
                        <th>Routed Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        <tr>
                            <td class="nowrap">{{ $document->tracking_number }}</td>
                            <td class="nowrap">{{ $document->document_reference_number ?? 'Pending' }}</td>
                            <td>{{ $document->document_type }}</td>
                            <td>
                                <strong>{{ $document->title }}</strong>
                            </td>
                            <td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $document->total_amount, 2) }}</td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ str($document->status)->replace('_', ' ')->title() }}</span></td>
                            <td>{{ $document->stage ?? 'N/A' }}</td>
                            <td>
                                {{ $document->route_destination_role ?? 'Not routed yet' }}
                                @if ($document->routeDestinationOffice)
                                    <br><span class="muted-text">{{ $document->routeDestinationOffice->name }}</span>
                                @endif
                            </td>
                            <td class="nowrap">{{ $document->routed_at?->format('M d, Y') ?? 'N/A' }}</td>
                            <td><div class="table-actions"><a href="{{ route('bac-secretariat.routing.show', $document) }}">View / Route</a></div></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11">
                                <div class="empty-state">
                                    <strong>No documents ready for routing</strong>
                                    <p>Documents marked ready by the BAC Secretariat will appear here.</p>
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
