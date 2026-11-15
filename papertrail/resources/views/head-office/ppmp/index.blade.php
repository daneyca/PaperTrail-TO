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

        $ppmpStatusOptions = collect($statuses)->mapWithKeys(fn ($status) => [$status => $ppmpStatusLabel($status)])->all();
        $summaryStatusLabels = ['draft' => 'Drafts', 'signatures' => 'Pending Signature', 'ready' => 'Signed',
            'submitted' => 'Submitted to BAC', 'reviewed' => 'Under APP Consolidation', 'returned' => 'Returned', 'accepted' => 'Accepted'];
        if (isset($summaryStatusLabels[$filters['status'] ?? ''])) {
            $ppmpStatusOptions = [$filters['status'] => $summaryStatusLabels[$filters['status']]] + $ppmpStatusOptions;
        }
        $ppmpFilterFields = [
            'document_type' => ['label' => 'Document type', 'empty' => 'All types', 'options' => collect($documentTypes)->mapWithKeys(fn ($type) => [$type => $type])->all()],
            'fiscal_year' => ['label' => 'Fiscal year', 'empty' => 'All years', 'options' => collect($fiscalYears)->mapWithKeys(fn ($year) => [$year => $year])->all()],
            'status' => ['label' => 'Status', 'empty' => 'All statuses', 'options' => $ppmpStatusOptions],
            'stage' => ['label' => 'Stage', 'empty' => 'All stages', 'options' => collect($stages)->mapWithKeys(fn ($stage) => [$stage => $stage])->all()],
            'date_from' => ['label' => 'Updated from', 'type' => 'date'],
            'date_to' => ['label' => 'Updated to', 'type' => 'date'],
        ];
    @endphp

    @section('topbar-search')
        <x-document-list-controls :action="route('head-office.ppmp.index')" :filters="$filters" :fields="$ppmpFilterFields" />
    @endsection

    <section class="dashboard-hero admin-users-hero ppmp-page-hero">
        <div>
            <h1>Project Procurement Management Plan</h1>
        </div>
        <div class="hero-actions">
            @if (auth()->user()->canManageSvpDocuments())
                <a href="{{ route('head-office.ppmp.create') }}" class="dashboard-action">Create PPMP</a>
            @endif
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
        <x-document-filter-summary :action="route('head-office.ppmp.index')" :filters="$filters" :fields="$ppmpFilterFields" :total="$documents->total()" />

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
                                    @endif
                                    @if (auth()->user()->canManageSvpDocuments() && in_array($document->id, $deletableDocumentIds ?? [], true))
                                        <form
                                            method="POST"
                                            action="{{ route('head-office.ppmp.destroy', $document) }}"
                                            data-confirm-title="Delete PPMP?"
                                            data-confirm="This will permanently delete this PPMP, its saved item rows, and any unsigned signature requests."
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
                                    @if (collect($filters)->contains(fn ($value) => filled($value)))
                                        <strong>No PPMPs match your search or filters</strong>
                                        <p>Try a different search or <a href="{{ route('head-office.ppmp.index') }}">clear search and filters</a>.</p>
                                    @else
                                    <strong>No PPMP records yet</strong>
                                    <p>Create a PPMP draft to begin your office procurement planning.</p>
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
