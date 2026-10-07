@extends(request()->boolean('modal') ? 'layouts.modal' : 'layouts.dashboard')

@section('title', 'Manage Permissions | PaperTrail')

@section('content')
    @php
        $totalPermissions = $permissionsByGroup->sum(fn ($permissions) => $permissions->count());
        $selectedPermissions = count($selected);
    @endphp

    <section class="dashboard-hero admin-users-hero admin-permissions-hero">
        <div>
            <p class="eyebrow">Permissions</p>
            <div class="admin-role-title-row">
                <h1>{{ $role->name }}</h1>
                <span class="future-enhancement-badge">Future Enhancement</span>
            </div>
            <p>{{ $role->description ?: 'View access capabilities assigned to this role.' }}</p>
            <div class="admin-permissions-meta" aria-label="Role permission summary">
                <span>{{ $role->users_count }} assigned users</span>
                <span>{{ $selectedPermissions }} of {{ $totalPermissions }} permissions selected</span>
                <span>Read-only permission map</span>
            </div>
        </div>
        <a href="{{ route('admin.roles.index') }}" class="dashboard-action secondary-action">Back to Roles</a>
    </section>

    <section class="form-panel admin-permissions-workspace">
        <div class="admin-permissions-form">
            <div class="admin-role-readonly-notice">
                <strong>Permission assignment is currently disabled.</strong>
                <p>This map documents the current predefined access model. Dynamic permission assignment, module access control, dashboard mapping, route authorization, and sidebar customization are planned for future implementation.</p>
            </div>

            <div class="permission-matrix">
                @foreach ($permissionsByGroup as $group => $permissions)
                    @php
                        $groupSelectedCount = $permissions->whereIn('id', $selected)->count();
                    @endphp
                    <article class="permission-group">
                        <div class="permission-group-header">
                            <div>
                                <span class="permission-group-kicker">{{ $groupSelectedCount }}/{{ $permissions->count() }} selected</span>
                                <h2>{{ str($group)->replace(['_', '-'], ' ')->upper() }}</h2>
                            </div>
                        </div>
                        <div class="permission-list">
                            @foreach ($permissions as $permission)
                                <label class="permission-item permission-item-readonly {{ in_array($permission->id, $selected) ? 'is-selected' : '' }}">
                                    <input type="checkbox" value="{{ $permission->id }}" @checked(in_array($permission->id, $selected)) disabled>
                                    <span class="permission-item-copy">
                                        <strong>{{ $permission->name }}</strong>
                                        <small>{{ $permission->key }}</small>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="form-actions">
                <a href="{{ route('admin.roles.index') }}">Back to Roles</a>
            </div>
        </div>
    </section>
@endsection
