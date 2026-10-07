@extends(request()->boolean('modal') ? 'layouts.modal' : 'layouts.dashboard')

@section('title', 'Assigned Users | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Office Users</p>
            <h1>{{ $office->name }}</h1>
            <p>{{ $office->code }} &middot; {{ $office->type }}</p>
        </div>

        <a href="{{ route('admin.offices.index') }}" class="dashboard-action">Back to Offices</a>
    </section>

    <section class="table-panel" aria-label="Assigned users">
        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($office->users as $user)
                        <tr>
                            <td class="nowrap">{{ $user->user_id }}</td>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->role }}</td>
                            <td><span class="status-pill status-{{ $user->status }}">{{ ucfirst($user->status) }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="empty-state">
                                    <strong>No assigned users</strong>
                                    <p>This office has no linked user accounts yet.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
