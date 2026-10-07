@extends('layouts.dashboard')

@section('title', 'Annual Procurement Plan | PaperTrail')

@section('content')
    @php
        $activeStatus = $filters['status'] ?? request('status');
        $pendingPpmps = collect($pendingPpmps ?? []);
        $readyPpmps = collect($readyPpmps ?? []);
        $ppmpStatusLabel = function (?string $status): string {
            return match ($status) {
                \App\Models\ProcurementDocument::STATUS_PENDING_PPMP_REVIEW => 'Submitted to BAC',
                \App\Models\ProcurementDocument::STATUS_UNDER_PPMP_REVIEW => 'Under APP Consolidation',
                \App\Models\ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION => 'Approved',
                default => str($status ?: 'record')->replace('_', ' ')->title()->toString(),
            };
        };
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">BAC Secretariat</p>
            <h1>Annual Procurement Plan</h1>
            <p>Create and manage official landscape APP records for LGU procurement planning.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern" aria-label="APP summary">
        <x-dashboard.stat-card href="{{ route('bac-secretariat.app.index') }}" value="{{ $summary['total'] }}" label="Total APPs" accent="navy" class="{{ blank($activeStatus) ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.app.index', ['status' => 'draft']) }}" value="{{ $summary['draft'] }}" label="Draft APPs" accent="gold" class="{{ $activeStatus === 'draft' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.app.index', ['status' => 'submitted']) }}" value="{{ $summary['submitted'] }}" label="Submitted APPs" accent="blue" class="{{ $activeStatus === 'submitted' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.app.index', ['status' => 'approved']) }}" value="{{ $summary['approved'] }}" label="Approved APPs" accent="green" class="{{ $activeStatus === 'approved' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.app.index', ['status' => 'returned']) }}" value="{{ $summary['returned'] }}" label="Returned APPs" accent="red" class="{{ $activeStatus === 'returned' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.app.index') }}" value="PHP {{ number_format((float) $summary['budget'], 2) }}" label="Total Budget" accent="navy" class="budget-amount-card" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.ppmp.index', ['status' => \App\Models\ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION]) }}" value="{{ $acceptedPpmpCount ?? 0 }}" label="Ready for APP" accent="gold" />
    </section>

    @if ($readyPpmps->isNotEmpty())
        <section class="table-panel" aria-label="Approved PPMP records ready for APP consolidation">
            <div class="table-scroll">
                <table class="user-management-table">
                    <thead>
                        <tr>
                            <th>Tracking Number</th>
                            <th>Requesting Office</th>
                            <th>Fiscal Year</th>
                            <th>Status</th>
                            <th>Total Budget</th>
                            <th>Ready Since</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($readyPpmps as $ppmp)
                            <tr>
                                <td class="nowrap"><strong>{{ $ppmp->tracking_number ?? $ppmp->ppmp_no ?? 'PPMP #' . $ppmp->id }}</strong></td>
                                <td>{{ $ppmp->submittingOffice?->name ?? 'Requesting Office' }}</td>
                                <td>{{ $ppmp->fiscal_year ?? 'N/A' }}</td>
                                <td><span class="status-pill status-{{ $ppmp->status }}">{{ $ppmpStatusLabel($ppmp->status) }}</span></td>
                                <td class="nowrap">PHP {{ number_format((float) $ppmp->total_amount, 2) }}</td>
                                <td class="nowrap">{{ $ppmp->updated_at?->format('M d, Y h:i A') ?? 'N/A' }}</td>
                                <td>
                                    <div class="table-actions">
                                        @if ($canConsolidate)
                                            <form method="POST" action="{{ route('bac-secretariat.app.add-ppmp', $ppmp) }}">
                                                @csrf
                                                <button type="submit" class="pt-action-button pt-action-button--success pt-action-button--labeled app-add-to-app-action">
                                                    <span class="pt-action-button__label">Add to APP</span>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($pendingPpmps->isNotEmpty())
        <section class="table-panel" aria-label="Incoming PPMP records for APP consolidation">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Incoming PPMPs</p>
                    <h2>Needs Review Before APP Import</h2>
                    <p>Submitted PPMP records assigned to BACSEC-004 appear here before they become available for APP consolidation.</p>
                </div>
            </div>

            <div class="table-scroll">
                <table class="user-management-table">
                    <thead>
                        <tr>
                            <th>PPMP No.</th>
                            <th>Requesting Office</th>
                            <th>Status</th>
                            <th>Total Budget</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pendingPpmps as $ppmp)
                            <tr>
                                <td class="nowrap">{{ $ppmp->tracking_number ?? $ppmp->ppmp_no ?? 'PPMP #' . $ppmp->id }}</td>
                                <td>{{ $ppmp->submittingOffice?->name ?? 'Requesting Office' }}</td>
                                <td><span class="status-pill status-{{ $ppmp->status }}">{{ $ppmpStatusLabel($ppmp->status) }}</span></td>
                                <td class="nowrap">PHP {{ number_format((float) $ppmp->total_amount, 2) }}</td>
                                <td class="nowrap">{{ $ppmp->updated_at?->format('M d, Y h:i A') ?? 'N/A' }}</td>
                                <td>
                                    <div class="table-actions">
                                        <a href="{{ route('bac-secretariat.ppmp.show', $ppmp) }}">Review / Accept</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="table-panel" aria-label="Annual Procurement Plans">
        <x-document-filter-card :action="route('bac-secretariat.app.index')">
            <div class="user-search">
                <label for="app-search">Search</label>
                <input id="app-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="APP no., title, office, prepared by">
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
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="user-filter"><label for="date-from">Date From</label><input id="date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"></div>
            <div class="user-filter"><label for="date-to">Date To</label><input id="date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"></div>
            <div class="filter-actions"><button type="submit">Apply</button><a href="{{ route('bac-secretariat.app.index') }}">Clear</a></div>
        </x-document-filter-card>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>APP No.</th>
                        <th>Tracking Number</th>
                        <th>Fiscal Year</th>
                        <th>Status</th>
                        <th>Total Estimated Budget</th>
                        <th>Prepared By</th>
                        <th>Submitted At</th>
                        <th>Approved At</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($apps as $app)
                        <tr>
                            <td class="nowrap">
                                <strong>{{ $app->app_number ?? 'Draft' }}</strong>
                                <span class="app-version-chip">v{{ $app->update_version_no ?: 1 }}</span>
                            </td>
                            <td class="nowrap">{{ $app->document_reference_number ?? 'Pending' }}</td>
                            <td>{{ $app->fiscal_year ?? 'N/A' }}</td>
                            <td><span class="status-pill status-{{ $app->status }}">{{ str($app->status)->replace('_', ' ')->title() }}</span></td>
                            <td class="nowrap">PHP {{ number_format((float) $app->total_estimated_budget, 2) }}</td>
                            <td>{{ $app->preparedBy?->name ?? 'N/A' }}</td>
                            <td class="nowrap">{{ $app->submitted_at?->format('M d, Y') ?? 'N/A' }}</td>
                            <td class="nowrap">{{ $app->approved_at?->format('M d, Y') ?? 'N/A' }}</td>
                            <td class="nowrap">{{ $app->updated_at?->format('M d, Y') }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('bac-secretariat.app.show', $app) }}">View</a>
                                    @if ($canConsolidate && $app->isEditable())
                                        <a href="{{ route('bac-secretariat.app.edit', $app) }}">Continue Editing</a>
                                    @endif
                                    <a href="{{ route('bac-secretariat.app.print', $app) }}" target="_blank">Print</a>
                                    @if ($canConsolidate && $app->canSubmit())
                                        <form method="POST" action="{{ route('bac-secretariat.app.submit', $app) }}" data-confirm="Submit this APP for approval?">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit">Submit</button>
                                        </form>
                                    @endif
                                    @if ($canConsolidate && $app->status === \App\Models\AnnualProcurementPlan::STATUS_DRAFT)
                                        <form
                                            method="POST"
                                            action="{{ route('bac-secretariat.app.destroy', $app) }}"
                                            data-confirm-title="Delete APP Draft?"
                                            data-confirm="This will remove this APP draft and its draft APP items only. Source PPMP records will remain available for consolidation."
                                            data-confirm-label="Delete Draft"
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
                        <tr><td colspan="10"><div class="empty-state"><strong>No APP records yet</strong><p>Annual Procurement Plan records created by BAC Secretariat will appear here.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($apps->hasPages())
            <div class="pagination-wrap">{{ $apps->appends(request()->query())->links('vendor.pagination.papertrail') }}</div>
        @endif
    </section>
@endsection
