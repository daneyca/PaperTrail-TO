@extends('layouts.dashboard')

@section('title', 'Roles Management | PaperTrail')

@section('content')
    @php
        $predefinedRoles = [
            'Administrator',
            'Head of Office / End User',
            'BAC Secretariat',
            'BAC Member',
            'BAC Chairperson',
            'Budget Officer',
            'Accounting Officer',
            'Approving Authority',
        ];

        $plannedFeatures = [
            'Dynamic role creation',
            'Permission assignment',
            'Module access control',
            'Dashboard mapping',
            'Route authorization',
            'Sidebar customization',
        ];
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Administration</p>
            <div class="admin-role-title-row">
                <h1>Roles & Permissions</h1>
                <span class="future-enhancement-badge">Future Enhancement</span>
            </div>
            <p>Dynamic role creation and permission assignment are planned for future implementation. Current PaperTrail access is based on predefined LGU procurement roles.</p>
        </div>
        <button type="button" class="dashboard-action admin-role-disabled-action" disabled>Add Role Unavailable</button>
    </section>

    <section class="stat-grid stat-grid-modern" aria-label="Role summary">
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['total'] }}" label="Total Roles" accent="navy" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['active'] }}" label="Active Roles" accent="green" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['system'] }}" label="System Roles" accent="gold" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['permissions'] }}" label="Permissions Configured" accent="blue" />
    </section>

    <section class="admin-role-future-grid" aria-label="Role management future enhancement">
        <article class="admin-role-future-card">
            <div class="admin-role-card-header">
                <div>
                    <p class="eyebrow">Current Access Model</p>
                    <h2>Predefined LGU Procurement Roles</h2>
                    <p>These roles remain active and continue to control dashboards, sidebar navigation, route access, and procurement workflows.</p>
                </div>
                <span class="admin-role-status-pill status-active">Read Only</span>
            </div>
            <div class="admin-role-chip-list" aria-label="Predefined system roles">
                @foreach ($predefinedRoles as $predefinedRole)
                    <span>{{ $predefinedRole }}</span>
                @endforeach
            </div>
        </article>

        <article class="admin-role-future-card">
            <div class="admin-role-card-header">
                <div>
                    <p class="eyebrow">Planned Scope</p>
                    <h2>Dynamic RBAC Roadmap</h2>
                    <p>The controls below are documented here for future development and are intentionally disabled in this release.</p>
                </div>
                <span class="admin-role-status-pill status-inactive">Planned</span>
            </div>
            <div class="admin-role-feature-list" aria-label="Planned role management features">
                @foreach ($plannedFeatures as $plannedFeature)
                    <span>{{ $plannedFeature }}</span>
                @endforeach
            </div>
        </article>
    </section>

    <section class="table-panel">
        <form method="GET" action="{{ route('admin.roles.index') }}" class="office-table-toolbar">
            <div class="user-search">
                <label for="role-search">Search roles</label>
                <input id="role-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search code, name, or description">
            </div>
            <div class="user-filter">
                <label for="status-filter">Status</label>
                <select id="status-filter" name="status">
                    <option value="">All statuses</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="user-filter">
                <label for="type-filter">Type</label>
                <select id="type-filter" name="type">
                    <option value="">All types</option>
                    <option value="system" @selected(($filters['type'] ?? '') === 'system')>System</option>
                    <option value="custom" @selected(($filters['type'] ?? '') === 'custom')>Custom</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit">Apply</button>
                <a href="{{ route('admin.roles.index') }}">Clear</a>
            </div>
        </form>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>Role Code</th>
                        <th>Role Name</th>
                        <th>Description</th>
                        <th>Assigned Users</th>
                        <th>Permissions</th>
                        <th>Status</th>
                        <th>Type</th>
                        <th>Created Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roles as $role)
                        <tr>
                            <td class="nowrap">{{ $role->code }}</td>
                            <td>{{ $role->name }}</td>
                            <td>{{ $role->description }}</td>
                            <td>{{ $role->users_count }}</td>
                            <td>{{ $role->permissions_count }}</td>
                            <td><span class="status-pill status-{{ $role->status }}">{{ ucfirst($role->status) }}</span></td>
                            <td><span class="status-pill {{ $role->is_system ? 'status-active' : '' }}">{{ $role->is_system ? 'System' : 'Custom' }}</span></td>
                            <td>{{ $role->created_at?->format('M d, Y') }}</td>
                            <td>
                                <div class="table-actions">
                                    <a
                                        href="{{ route('admin.roles.edit', $role) }}"
                                        class="icon-action icon-action--view"
                                        aria-label="View role"
                                        title="View role"
                                        data-crud-modal-trigger
                                        data-crud-modal-title="View Role"
                                        data-crud-modal-eyebrow="{{ $role->code }}"
                                        data-crud-modal-src="{{ route('admin.roles.edit', ['role' => $role, 'modal' => 1]) }}"
                                        data-crud-modal-return="{{ route('admin.roles.index') }}"
                                    >
                                        <x-papertrail.icon name="view" />
                                    </a>
                                    <a
                                        href="{{ route('admin.roles.permissions', $role) }}"
                                        class="icon-action icon-action--permissions"
                                        aria-label="View permissions"
                                        title="View permissions"
                                        data-crud-modal-trigger
                                        data-crud-modal-title="View Permissions"
                                        data-crud-modal-eyebrow="{{ $role->name }}"
                                        data-crud-modal-size="wide"
                                        data-crud-modal-src="{{ route('admin.roles.permissions', ['role' => $role, 'modal' => 1]) }}"
                                        data-crud-modal-return="{{ route('admin.roles.index') }}"
                                    >
                                        <x-papertrail.icon name="shield" />
                                    </a>
                                    <span class="admin-role-locked-action" title="Role changes are planned for a future RBAC release">Locked</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty-state"><strong>No roles found</strong><p>Try adjusting the search text or filters.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($roles->hasPages())
            <div class="pagination-wrap">
                {{ $roles->appends(request()->query())->links('vendor.pagination.papertrail') }}
            </div>
        @endif
    </section>
@endsection
