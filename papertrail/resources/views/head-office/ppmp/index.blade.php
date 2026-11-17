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
            <p class="ppmp-summary-budget">Total budget <strong>PHP {{ number_format((float) $summary['budget'], 2) }}</strong></p>
        </div>
        <div class="hero-actions">
            @if (auth()->user()->canManageSvpDocuments())
                <a href="{{ route('head-office.ppmp.create') }}" class="dashboard-action">Create PPMP</a>
            @endif
        </div>
    </section>

    <section class="workflow-summary-grid" aria-label="PPMP summary">
        <x-dashboard.workflow-stat-card :href="route('head-office.ppmp.index')" :count="$summary['total']" :total="$summary['total']" label="Total PPMPs" accent="navy" icon="layers" :active="blank($activeStatus)" />
        <x-dashboard.workflow-stat-card :href="route('head-office.ppmp.index', ['status' => 'draft'])" :count="$summary['draft']" :total="$summary['total']" label="Drafts" accent="gold" :active="$activeStatus === 'draft'" />
        <x-dashboard.workflow-stat-card :href="route('head-office.ppmp.index', ['status' => 'submitted'])" :count="$summary['submitted']" :total="$summary['total']" label="Submitted to BAC" accent="blue" icon="send" :active="$activeStatus === 'submitted'" />
        <x-dashboard.workflow-stat-card :href="route('head-office.ppmp.index', ['status' => 'reviewed'])" :count="$summary['reviewed']" :total="$summary['total']" label="Under APP Consolidation" accent="green" icon="consolidate" :active="$activeStatus === 'reviewed'" />
        <x-dashboard.workflow-stat-card :href="route('head-office.ppmp.index', ['status' => 'returned'])" :count="$summary['returned']" :total="$summary['total']" label="Returned" accent="red" icon="return" :active="$activeStatus === 'returned'" />
        <x-dashboard.workflow-stat-card :href="route('head-office.ppmp.index', ['status' => 'accepted'])" :count="$summary['accepted']" :total="$summary['total']" label="Accepted" accent="green" icon="check" :active="$activeStatus === 'accepted'" />
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
