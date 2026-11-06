@extends(request()->boolean('modal') ? 'layouts.modal' : 'layouts.dashboard')

@section('title', 'Edit Account | PaperTrail')

@section('content')
    <section class="dashboard-hero">
        <div>
            <p class="eyebrow">Administration</p>
            <h1>Edit Account</h1>
            <p>Update account details for {{ $user->user_id }}.</p>
        </div>
    </section>

    <section class="form-panel">
        <form
            method="POST"
            action="{{ route('admin.users.update', $user) }}"
            data-confirm-status-change="true"
            data-current-status="{{ $user->status }}"
        >
            @method('PUT')
            @include('admin.users._form', ['mode' => 'edit'])
        </form>
    </section>
    @if (! $user->isAdmin())
        <section class="form-panel">
            <h2>Assigned Workspaces</h2>
            <table class="data-table">
                <thead><tr><th>Workspace</th><th>Role</th><th>Status</th><th>Access</th></tr></thead>
                <tbody>
                    @forelse ($linkedWorkspaces as $workspace)
                        <tr>
                            <td>{{ $workspace->user_id }}</td>
                            <td>{{ $workspace->workspaceLabel() }}</td>
                            <td>{{ ucfirst($workspace->status) }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.users.workspaces.status', [$user, $workspace]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $workspace->isActive() ? 'inactive' : 'active' }}">
                                    <button type="submit" class="btn btn-secondary">{{ $workspace->isActive() ? 'Disable' : 'Enable' }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No additional workspaces.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <form method="POST" action="{{ route('admin.users.workspaces.store', $user) }}" class="workspace-assignment-form">
                @csrf
                <label for="workspace-assignment">Additional Workspace</label>
                <select id="workspace-assignment" name="workspace_id" required>
                    <option value="">Select account</option>
                    @foreach ($availableWorkspaces as $workspace)
                        <option value="{{ $workspace->id }}">{{ $workspace->user_id }} - {{ $workspace->name }} - {{ $workspace->workspaceLabel() }}</option>
                    @endforeach
                </select>
                <label><input type="checkbox" name="same_person" value="1" required> These accounts belong to the same person. The additional account will no longer have a separate login.</label>
                <button type="submit" class="btn btn-primary">Link Workspace</button>
            </form>
        </section>
    @endif
@endsection
