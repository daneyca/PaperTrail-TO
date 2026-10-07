@extends('layouts.dashboard')

@section('title', 'PPMP | PaperTrail')

@section('content')
    @php
        $activeStatus = $filters['status'] ?? request('status');
        $ppmpStatusLabel = function (?string $status): string {
            return match ($status) {
                \App\Models\ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES => 'Pending Signature',
                \App\Models\ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED => 'Signed',
                \App\Models\ProcurementDocument::STATUS_PENDING_PPMP_REVIEW => 'Submitted to BAC',
                \App\Models\ProcurementDocument::STATUS_UNDER_PPMP_REVIEW => 'Under APP Consolidation',
                \App\Models\ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION => 'Approved',
                default => str($status ?: 'Unknown')->replace('_', ' ')->title()->replace('Ppmp', 'PPMP')->toString(),
            };
        };
    @endphp

    <section class="dashboard-hero admin-users-hero ppmp-page-hero">
        <div>
            <p class="eyebrow">Head of Office / End User</p>
            <h1>PPMP</h1>
            <p>Prepare, revise, and track Project Procurement Plans for your office.</p>
        </div>
        <div class="hero-actions">
            <a href="{{ route('head-office.ppmp.create') }}" class="dashboard-action">Create PPMP</a>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern head-office-summary-grid ppmp-summary-grid" aria-label="PPMP summary">
        <x-dashboard.stat-card href="{{ route('head-office.ppmp.index') }}" value="{{ $summary['total'] }}" label="Total PPMPs" accent="navy" class="{{ blank($activeStatus) ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('head-office.ppmp.index', ['status' => 'draft']) }}" value="{{ $summary['draft'] }}" label="Drafts" accent="gold" class="{{ $activeStatus === 'draft' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('head-office.ppmp.index', ['status' => 'submitted']) }}" value="{{ $summary['submitted'] }}" label="Submitted to BAC" accent="blue" class="{{ $activeStatus === 'submitted' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('head-office.ppmp.index', ['status' => 'reviewed']) }}" value="{{ $summary['reviewed'] }}" label="Under APP Consolidation" accent="green" class="{{ $activeStatus === 'reviewed' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('head-office.ppmp.index', ['status' => 'returned']) }}" value="{{ $summary['returned'] }}" label="Returned" accent="red" class="{{ $activeStatus === 'returned' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('head-office.ppmp.index', ['status' => 'accepted']) }}" value="{{ $summary['accepted'] }}" label="Accepted" accent="green" class="{{ $activeStatus === 'accepted' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('head-office.ppmp.index') }}" value="PHP {{ number_format((float) $summary['budget'], 2) }}" label="Total Budget" accent="navy" class="budget-amount-card" />
    </section>

    <section class="table-panel">
        <form method="GET" action="{{ route('head-office.ppmp.index') }}" class="ppmp-filter-toolbar">
            <label>
                <span>SEARCH DOCUMENTS</span>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Tracking number, title, or description">
            </label>
            <label>
                <span>DOCUMENT TYPE</span>
                <select name="document_type">
                    <option value="">All types</option>
                    @foreach ($documentTypes as $type)
                        <option value="{{ $type }}" @selected(($filters['document_type'] ?? '') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>FISCAL YEAR</span>
                <select name="fiscal_year">
                    <option value="">All years</option>
                    @foreach ($fiscalYears as $year)
                        <option value="{{ $year }}" @selected((string) ($filters['fiscal_year'] ?? '') === (string) $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>STATUS</span>
                <select name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $ppmpStatusLabel($status) }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>STAGE</span>
                <select name="stage">
                    <option value="">All stages</option>
                    @foreach ($stages as $stage)
                        <option value="{{ $stage }}" @selected(($filters['stage'] ?? '') === $stage)>{{ $stage }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>DATE FROM</span>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </label>
            <label>
                <span>DATE TO</span>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </label>
            <div class="filter-actions">
                <button type="submit">Apply</button>
                <a href="{{ route('head-office.ppmp.index') }}">Clear</a>
            </div>
        </form>

        <div class="table-scroll ppmp-table-scroll">
            <table class="user-management-table ppmp-registry-table head-office-doc-table">
                <thead>
                    <tr>
                        <th>Tracking Number</th>
                        <th>Document Type</th>
                        <th>Title</th>
                        <th>Fiscal Year</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Stage</th>
                        <th>Submitted Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        @php
                            $canEdit = in_array($document->status, ['ppmp_draft', 'returned_by_bac_secretariat', 'ppmp_signature_returned'], true);
                        @endphp
                        <tr>
                            <td>{{ $document->displayNumber() }}</td>
                            <td>{{ $document->document_type }}</td>
                            <td>
                                <span class="ppmp-list-title-text">{{ $document->title ?? 'Project Procurement Plan' }}</span>
                            </td>
                            <td>{{ $document->fiscal_year }}</td>
                            <td>PHP {{ number_format((float) $document->total_amount, 2) }}</td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ $ppmpStatusLabel($document->status) }}</span></td>
                            <td>{{ $document->stage ?? 'N/A' }}</td>
                            <td>{{ $document->submitted_at?->format('M d, Y') ?? $document->updated_at?->format('M d, Y') }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('head-office.ppmp.show', $document) }}">View</a>
                                    @if (Route::has('head-office.ppmp.print'))
                                        <a href="{{ route('head-office.ppmp.print', $document) }}" target="_blank" rel="noopener">Print</a>
                                    @endif
                                    @if ($canEdit)
                                        <a href="{{ route('head-office.ppmp.edit', $document) }}">Continue Editing</a>
                                        <form
                                            method="POST"
                                            action="{{ route('head-office.ppmp.destroy', $document) }}"
                                            data-confirm-title="Delete PPMP?"
                                            data-confirm="This will permanently remove this draft or returned PPMP record and its saved item rows."
                                            data-confirm-label="Delete PPMP"
                                            data-confirm-type="danger"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="danger-action">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <strong>No PPMP records yet</strong>
                                    <p>Create a PPMP draft to begin your office procurement planning.</p>
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
