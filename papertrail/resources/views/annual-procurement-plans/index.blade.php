@extends('layouts.dashboard')

@section('title', 'Annual Procurement Plans | PaperTrail')

@section('content')
    @php
        $activeStatus = $filters['status'] ?? request('status');
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Procurement Planning</p>
            <h1>Annual Procurement Plans</h1>
            <p>Create, preview, and print the official LGU Annual Procurement Plan format.</p>
        </div>
        <a href="{{ route('annual-procurement-plans.create') }}" class="dashboard-action">Create APP</a>
    </section>

    <section class="stat-grid stat-grid-modern" aria-label="APP summary">
        <x-dashboard.stat-card href="{{ route('annual-procurement-plans.index') }}" value="{{ $summary['total'] }}" label="Total APPs" accent="navy" class="{{ blank($activeStatus) ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('annual-procurement-plans.index', ['status' => 'draft']) }}" value="{{ $summary['draft'] }}" label="Drafts" accent="gold" class="{{ $activeStatus === 'draft' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('annual-procurement-plans.index', ['status' => 'approved']) }}" value="{{ $summary['approved'] }}" label="Approved" accent="green" class="{{ $activeStatus === 'approved' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('annual-procurement-plans.index') }}" value="PHP {{ number_format((float) $summary['budget'], 2) }}" label="Total Budget" accent="blue" />
    </section>

    <section class="table-panel" aria-label="Annual Procurement Plan records">
        <x-document-filter-card :action="route('annual-procurement-plans.index')">
            <div class="user-search">
                <label for="app-search">Search</label>
                <input id="app-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="APP no., project title, end-user, or prepared by">
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
                <label for="plan-type">Plan Type</label>
                <select id="plan-type" name="plan_type">
                    <option value="">All types</option>
                    @foreach ($planTypes as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['plan_type'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="user-filter">
                <label for="status-filter">Status</label>
                <select id="status-filter" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit">Apply</button>
                <a href="{{ route('annual-procurement-plans.index') }}">Clear</a>
            </div>
        </x-document-filter-card>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>APP No.</th>
                        <th>Tracking Number</th>
                        <th>Fiscal Year</th>
                        <th>Plan Type</th>
                        <th>Status</th>
                        <th>Items</th>
                        <th>Total Estimated Budget</th>
                        <th>Prepared By</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($apps as $app)
                        <tr>
                            <td>{{ $app->app_no ?? $app->app_number ?? 'Draft' }}</td>
                            <td>{{ $app->document_reference_number ?? 'Pending' }}</td>
                            <td>{{ $app->fiscal_year }}</td>
                            <td>{{ $planTypes[$app->plan_type] ?? str($app->plan_type)->replace('_', ' ')->title() }}</td>
                            <td><span class="status-pill status-{{ $app->status }}">{{ $statuses[$app->status] ?? str($app->status)->replace('_', ' ')->title() }}</span></td>
                            <td>{{ $app->items_count }}</td>
                            <td>PHP {{ number_format((float) $app->total_estimated_budget, 2) }}</td>
                            <td>{{ $app->prepared_by_name ?: $app->createdBy?->name ?: 'N/A' }}</td>
                            <td>{{ $app->updated_at?->format('M d, Y') }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('annual-procurement-plans.show', $app) }}">View</a>
                                    <a href="{{ route('annual-procurement-plans.edit', $app) }}">Continue Editing</a>
                                    <a href="{{ route('annual-procurement-plans.print', $app) }}" target="_blank">Print</a>
                                    <form method="POST" action="{{ route('annual-procurement-plans.destroy', $app) }}" onsubmit="return confirm('Delete this Annual Procurement Plan?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                <div class="empty-state">
                                    <strong>No Annual Procurement Plans yet</strong>
                                    <p>Create an APP draft to start encoding the official LGU format.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($apps->hasPages())
            <div class="pagination-wrap">{{ $apps->appends(request()->query())->links('vendor.pagination.papertrail') }}</div>
        @endif
    </section>
@endsection
