@extends(request()->boolean('modal') ? 'layouts.modal' : 'layouts.dashboard')

@section('title', 'Add Role | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-role-hero">
        <div>
            <p class="eyebrow">Administration</p>
            <div class="admin-role-title-row">
                <h1>Add Role</h1>
                <span class="future-enhancement-badge">Future Enhancement</span>
            </div>
            <p>Dynamic role creation is planned for a future RBAC release. Current access is based on predefined PaperTrail procurement roles.</p>
        </div>
        <a href="{{ route('admin.roles.index') }}" class="dashboard-action secondary-action">Back to Roles</a>
    </section>

    <section class="form-panel admin-role-edit-card">
        <div class="admin-role-card-header">
            <div>
                <p class="eyebrow">Role Creation Disabled</p>
                <h2>Custom roles are not operational yet</h2>
                <p>New roles require dashboard routing, sidebar generation, permissions, and route authorization before they can safely be used.</p>
            </div>
            <span class="admin-role-status-pill status-inactive">Planned</span>
        </div>

        <div class="admin-role-readonly-body">
            <div class="admin-role-readonly-notice">
                <strong>No new roles can be created in this release.</strong>
                <p>Use the predefined LGU procurement roles for active accounts. This page is retained for documentation and future development planning.</p>
            </div>
            <div class="form-actions">
                <a href="{{ route('admin.roles.index') }}">Back to Roles</a>
            </div>
        </div>
    </section>
@endsection
