@extends('layouts.dashboard')

@section('title', 'PPMP | PaperTrail')

@section('content')
    @php
        $activeStatus = $filters['status'] ?? request('status');
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Documents</p>
            <h1>PPMP</h1>
            <p>Create and manage Project Procurement Plans using the official LGU workbook layout.</p>
        </div>
        <div class="hero-actions">
            <a href="{{ route('ppmps.create') }}" class="dashboard-action">Create PPMP</a>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern ppmp-summary-grid" aria-label="PPMP summary">
        <x-dashboard.stat-card href="{{ route('ppmps.index') }}" value="{{ $summary['total'] }}" label="Total PPMPs" accent="navy" class="{{ blank($activeStatus) ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('ppmps.index', ['status' => 'draft']) }}" value="{{ $summary['draft'] }}" label="Drafts" accent="gold" class="{{ $activeStatus === 'draft' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('ppmps.index', ['status' => 'submitted']) }}" value="{{ $summary['submitted'] }}" label="Submitted" accent="blue" class="{{ $activeStatus === 'submitted' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('ppmps.index') }}" value="PHP {{ number_format((float) $summary['budget'], 2) }}" label="Total Budget" accent="green" />
    </section>

    <section class="table-panel" aria-label="PPMP records">
        <x-document-filter-card :action="route('ppmps.index')" class="ppmp-filter-toolbar">
            <div class="user-search">
                <label for="ppmp-search">Search Documents</label>
                <input id="ppmp-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="PPMP no., unit, or project details">
            </div>

            <div class="user-filter">
                <label for="ppmp-year">Fiscal Year</label>
                <select id="ppmp-year" name="fiscal_year">
                    <option value="">All years</option>
                    @foreach ($fiscalYears as $year)
                        <option value="{{ $year }}" @selected((string) ($filters['fiscal_year'] ?? '') === (string) $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="ppmp-plan-type">Plan Type</label>
                <select id="ppmp-plan-type" name="plan_type">
                    <option value="">All types</option>
                    @foreach ($planTypes as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['plan_type'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="ppmp-status">Status</label>
                <select id="ppmp-status" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit">Apply</button>
                <a href="{{ route('ppmps.index') }}">Clear</a>
            </div>
        </x-document-filter-card>

        <div class="table-scroll ppmp-table-scroll">
            <table class="user-management-table ppmp-registry-table">
                <thead>
                    <tr>
                        <th>PPMP No.</th>
                        <th>Fiscal Year</th>
                        <th>End-User / Unit</th>
                        <th>Plan Type</th>
                        <th>Status</th>
                        <th>Items</th>
                        <th>Total Budget</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ppmps as $ppmp)
                        <tr>
                            <td class="nowrap">{{ $ppmp->ppmp_no ?: 'Draft #' . $ppmp->id }}</td>
                            <td>{{ $ppmp->fiscal_year }}</td>
                            <td>{{ $ppmp->end_user_unit }}</td>
                            <td>{{ str($ppmp->plan_type)->title() }}</td>
                            <td><span class="status-pill status-{{ $ppmp->status }}">{{ str($ppmp->status)->replace('_', ' ')->title()->replace('Ppmp', 'PPMP') }}</span></td>
                            <td>{{ $ppmp->items_count }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $ppmp->total_budget, 2) }}</td>
                            <td class="nowrap">{{ $ppmp->updated_at?->format('M d, Y') }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('ppmps.show', $ppmp) }}">View</a>
                                    <a href="{{ route('ppmps.print', $ppmp) }}" target="_blank" rel="noopener">Print</a>
                                    @if ($ppmp->isEditable())
                                        <a href="{{ route('ppmps.edit', $ppmp) }}">Continue Editing</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <strong>No PPMP records yet</strong>
                                    <p>Create a PPMP draft once the official procurement plan details are ready.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($ppmps->hasPages())
            <div class="pagination-wrap">
                {{ $ppmps->appends(request()->query())->links('vendor.pagination.papertrail') }}
            </div>
        @endif
    </section>
@endsection
