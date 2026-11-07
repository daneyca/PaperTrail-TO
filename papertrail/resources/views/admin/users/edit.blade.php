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
    @if (! $user->isAdmin() && ! $user->isOfficeDocumentPreparer())
        <section class="form-panel workspace-management-panel" aria-labelledby="linked-workspaces-title">
            <header class="workspace-management-header">
                <div>
                    <p class="workspace-management-eyebrow">Account access</p>
                    <h2 id="linked-workspaces-title">Linked Workspaces</h2>
                    <p>Manage the additional roles this employee can open using the same login.</p>
                </div>
                <span class="workspace-count">{{ $linkedWorkspaces->count() }} linked</span>
            </header>

            <div class="workspace-table-wrap">
                <table class="data-table workspace-table">
                    <thead>
                        <tr>
                            <th>Account</th>
                            <th>Workspace</th>
                            <th>Status</th>
                            <th class="workspace-table__action-heading">Access</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($linkedWorkspaces as $workspace)
                            <tr>
                                <td>
                                    <strong>{{ $workspace->user_id }}</strong>
                                    <small>{{ $workspace->name }}</small>
                                </td>
                                <td>{{ $workspace->workspaceLabel() }}</td>
                                <td>
                                    <span class="status-pill status-{{ $workspace->status }}">
                                        {{ ucfirst($workspace->status) }}
                                    </span>
                                </td>
                                <td class="workspace-table__action">
                                    <form method="POST" action="{{ route('admin.users.workspaces.status', [$user, $workspace]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $workspace->isActive() ? 'inactive' : 'active' }}">
                                        <button type="submit" class="btn workspace-access-button {{ $workspace->isActive() ? 'is-disable' : 'is-enable' }}">
                                            {{ $workspace->isActive() ? 'Disable' : 'Enable' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr class="workspace-empty-row">
                                <td colspan="4">
                                    <div class="workspace-empty-state">
                                        <strong>No linked workspaces yet</strong>
                                        <span>This employee currently uses only the {{ $user->workspaceLabel() }} workspace.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="workspace-link-section">
                <div class="workspace-link-heading">
                    <h3>Link Another Workspace</h3>
                    <p>Use this only when both accounts belong to the same employee.</p>
                </div>

                <form method="POST" action="{{ route('admin.users.workspaces.store', $user) }}" class="workspace-assignment-form">
                    @csrf
                    <div class="workspace-assignment-field">
                        <label for="workspace-assignment">Workspace account</label>
                        <select id="workspace-assignment" name="workspace_id" required @disabled($availableWorkspaces->isEmpty())>
                            <option value="">
                                {{ $availableWorkspaces->isEmpty() ? 'No eligible accounts available' : 'Choose an account to link' }}
                            </option>
                            @foreach ($availableWorkspaces as $workspace)
                                <option value="{{ $workspace->id }}">{{ $workspace->user_id }} - {{ $workspace->name }} - {{ $workspace->workspaceLabel() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <label class="workspace-confirmation">
                        <input type="checkbox" name="same_person" value="1" required @disabled($availableWorkspaces->isEmpty())>
                        <span>
                            <strong>Confirm that both accounts belong to the same employee</strong>
                            <small>The linked account will be opened through workspace switching and will no longer have a separate login.</small>
                        </span>
                    </label>

                    <div class="workspace-link-actions">
                        <button type="submit" class="btn btn-primary" @disabled($availableWorkspaces->isEmpty())>Link Workspace</button>
                    </div>
                </form>
            </div>
        </section>
    @endif
@endsection
