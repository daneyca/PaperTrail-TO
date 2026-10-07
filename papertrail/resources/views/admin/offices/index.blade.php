@extends('layouts.dashboard')

@section('title', 'Offices Management | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Administration</p>
            <h1>Offices Management</h1>
            <p>Manage LGU offices and departments used for procurement routing and user assignment.</p>
        </div>

        <a
            href="{{ route('admin.offices.create') }}"
            class="dashboard-action"
            data-crud-modal-trigger
            data-crud-modal-title="Add Office"
            data-crud-modal-eyebrow="Office Management"
            data-crud-modal-src="{{ route('admin.offices.create', ['modal' => 1]) }}"
            data-crud-modal-return="{{ route('admin.offices.index') }}"
        >Add Office</a>
    </section>

    <section class="stat-grid stat-grid-modern" aria-label="Office summary">
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['total'] }}" label="Total Offices" accent="navy" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['active'] }}" label="Active Offices" accent="green" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['endUser'] }}" label="End-User Offices" accent="gold" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['review'] }}" label="Review / Processing Offices" accent="blue" />
    </section>

    <section class="table-panel" aria-label="Offices">
        <form method="GET" action="{{ route('admin.offices.index') }}" class="office-table-toolbar">
            <div class="user-search">
                <label for="office-search">Search offices</label>
                <input id="office-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search code, name, or type">
            </div>

            <div class="user-filter">
                <label for="type-filter">Type</label>
                <select id="type-filter" name="type">
                    <option value="">All types</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="status-filter">Status</label>
                <select id="status-filter" name="status">
                    <option value="">All statuses</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit">Apply</button>
                <a href="{{ route('admin.offices.index') }}">Clear</a>
            </div>
        </form>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>Office Code</th>
                        <th>Office Name</th>
                        <th>Type</th>
                        <th>Assigned Users</th>
                        <th>Status</th>
                        <th>Created Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($offices as $office)
                        <tr>
                            <td class="nowrap">{{ $office->code }}</td>
                            <td>{{ $office->name }}</td>
                            <td>
                                {{ $office->type }}
                                @unless ($office->is_requesting_office)
                                    <span class="status-pill status-inactive">Not Requesting</span>
                                @endunless
                            </td>
                            <td>{{ $office->users_count }}</td>
                            <td><span class="status-pill status-{{ $office->status }}">{{ ucfirst($office->status) }}</span></td>
                            <td>{{ $office->created_at?->format('M d, Y') }}</td>
                            <td>
                                <div class="table-actions">
                                    <a
                                        href="{{ route('admin.offices.edit', $office) }}"
                                        class="icon-action icon-action--edit"
                                        aria-label="Edit office"
                                        title="Edit office"
                                        data-crud-modal-trigger
                                        data-crud-modal-title="Edit Office"
                                        data-crud-modal-eyebrow="{{ $office->code }}"
                                        data-crud-modal-src="{{ route('admin.offices.edit', ['office' => $office, 'modal' => 1]) }}"
                                        data-crud-modal-return="{{ route('admin.offices.index') }}"
                                    >
                                        <x-papertrail.icon name="edit" />
                                    </a>
                                    <a
                                        href="{{ route('admin.offices.show', $office) }}"
                                        class="icon-action icon-action--view"
                                        aria-label="View office users"
                                        title="View office users"
                                        data-crud-modal-trigger
                                        data-crud-modal-title="Office Details"
                                        data-crud-modal-eyebrow="{{ $office->code }}"
                                        data-crud-modal-src="{{ route('admin.offices.show', ['office' => $office, 'modal' => 1]) }}"
                                        data-crud-modal-return="{{ route('admin.offices.index') }}"
                                    >
                                        <x-papertrail.icon name="users" />
                                    </a>
                                    <form method="POST" action="{{ route('admin.offices.status', $office) }}" onsubmit="return confirm('{{ $office->status === 'active' ? 'Deactivate this office? Assigned users will remain linked.' : 'Activate this office?' }}');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="icon-action {{ $office->status === 'active' ? 'icon-action--danger' : 'icon-action--success' }}" aria-label="{{ $office->status === 'active' ? 'Deactivate office' : 'Activate office' }}" title="{{ $office->status === 'active' ? 'Deactivate office' : 'Activate office' }}">
                                            <x-papertrail.icon name="{{ $office->status === 'active' ? 'power' : 'check' }}" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <strong>No offices found</strong>
                                    <p>Try adjusting the search text or filters.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($offices->hasPages())
            <div class="pagination-wrap">
                {{ $offices->appends(request()->query())->links('vendor.pagination.papertrail') }}
            </div>
        @endif
    </section>
@endsection
