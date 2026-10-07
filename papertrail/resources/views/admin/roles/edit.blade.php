@extends(request()->boolean('modal') ? 'layouts.modal' : 'layouts.dashboard')

@section('title', 'Edit Role | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-role-hero">
        <div>
            <p class="eyebrow">Administration</p>
            <div class="admin-role-title-row">
                <h1>View Role</h1>
                <span class="future-enhancement-badge">Future Enhancement</span>
            </div>
            <p>Role records are shown for documentation. Dynamic role editing is planned for future implementation.</p>
        </div>
        <a href="{{ route('admin.roles.index') }}" class="dashboard-action secondary-action">Back to Roles</a>
    </section>

    <section class="form-panel admin-role-edit-card">
        <div class="admin-role-card-header">
            <div>
                <p class="eyebrow">Role Information</p>
                <h2>{{ $role->name }}</h2>
                <p>Current predefined role configuration used by PaperTrail access control.</p>
            </div>
            <span class="admin-role-status-pill status-{{ $role->status }}">{{ str($role->status)->title() }}</span>
        </div>

        <div class="admin-role-readonly-body">
            <dl class="admin-role-readonly-grid">
                <div>
                    <dt>Role Code</dt>
                    <dd>{{ $role->code }}</dd>
                </div>
                <div>
                    <dt>Role Name</dt>
                    <dd>{{ $role->name }}</dd>
                </div>
                <div>
                    <dt>Dashboard Route</dt>
                    <dd>{{ $role->dashboard_route ?: 'Not configured' }}</dd>
                </div>
                <div>
                    <dt>Status</dt>
                    <dd>{{ str($role->status)->title() }}</dd>
                </div>
                <div>
                    <dt>Role Type</dt>
                    <dd>{{ $role->is_system ? 'System role' : 'Custom role' }}</dd>
                </div>
                <div>
                    <dt>Created</dt>
                    <dd>{{ $role->created_at?->format('M d, Y') ?? 'N/A' }}</dd>
                </div>
                <div class="admin-role-readonly-wide">
                    <dt>Description</dt>
                    <dd>{{ $role->description ?: 'No description provided.' }}</dd>
                </div>
            </dl>

            <div class="admin-role-readonly-notice">
                <strong>Role editing is currently disabled.</strong>
                <p>Changing role codes, dashboard routes, permissions, or statuses requires the future dynamic RBAC implementation.</p>
            </div>

            <div class="form-actions">
                <a href="{{ route('admin.roles.index') }}">Back to Roles</a>
            </div>
        </div>
    </section>
@endsection
