@extends('layouts.dashboard')

@section('title', 'Accounts | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Administration</p>
            <h1>Accounts</h1>
            <p>Manage fixed internal accounts for LGU procurement roles.</p>
        </div>

        <a
            href="{{ route('admin.users.create') }}"
            class="dashboard-action"
            data-crud-modal-trigger
            data-crud-modal-title="Add Account"
            data-crud-modal-eyebrow="User Management"
            data-crud-modal-src="{{ route('admin.users.create', ['modal' => 1]) }}"
            data-crud-modal-return="{{ route('admin.users.index') }}"
        >Add Account</a>
    </section>

    <section class="table-panel" aria-label="Accounts">
        <form method="GET" action="{{ route('admin.users.index') }}" class="user-table-toolbar">
            <div class="user-search">
                <label for="user-search">Search accounts</label>
                <input id="user-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search User ID, name, or office">
            </div>

            <div class="user-filter">
                <label for="role-filter">Role</label>
                <select id="role-filter" name="role">
                    <option value="">All roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ $role }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="office-filter">Office</label>
                <select id="office-filter" name="office">
                    <option value="">All offices</option>
                    @foreach ($offices as $office)
                        <option value="{{ $office }}" @selected(($filters['office'] ?? '') === $office)>{{ $office }}</option>
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

            <div class="user-filter">
                <label for="email-verification-filter">Email Verification</label>
                <select id="email-verification-filter" name="email_verification">
                    <option value="">All email states</option>
                    <option value="verified" @selected(($filters['email_verification'] ?? '') === 'verified')>Verified</option>
                    <option value="unverified" @selected(($filters['email_verification'] ?? '') === 'unverified')>Not Verified</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit">Apply</button>
                <a href="{{ route('admin.users.index') }}">Clear</a>
            </div>
        </form>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Office</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Email</th>
                        <th>Email Verification</th>
                        <th>Created Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td class="nowrap">{{ $user->user_id }}</td>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->office }}</td>
                            <td>{{ $user->role }}</td>
                            <td>
                                <span class="status-pill status-{{ $user->status }}">
                                    {{ ucfirst($user->status) }}
                                </span>
                            </td>
                            <td>{{ $user->email ?? 'Not set' }}</td>
                            <td>
                                @if ($user->email && $user->email_verified_at)
                                    <span class="status-pill status-email-verified">Email Verified</span>
                                @else
                                    <span class="status-pill status-email-unverified">Email Not Verified</span>
                                @endif
                            </td>
                            <td>{{ $user->created_at?->format('M d, Y') }}</td>
                            <td>
                                <div class="table-actions">
                                    <button
                                        type="button"
                                        class="icon-action icon-action--view"
                                        aria-label="View account details"
                                        title="View account details"
                                        data-crud-modal-template="user-details-{{ $user->id }}"
                                        data-crud-modal-title="Account Details"
                                        data-crud-modal-eyebrow="{{ $user->user_id }}"
                                    >
                                        <x-papertrail.icon name="view" />
                                    </button>

                                    <a
                                        href="{{ route('admin.users.edit', $user) }}"
                                        class="icon-action icon-action--edit"
                                        aria-label="Edit account"
                                        title="Edit account"
                                        data-crud-modal-trigger
                                        data-crud-modal-title="Edit Account"
                                        data-crud-modal-eyebrow="{{ $user->user_id }}"
                                        data-crud-modal-src="{{ route('admin.users.edit', ['user' => $user, 'modal' => 1]) }}"
                                        data-crud-modal-return="{{ route('admin.users.index') }}"
                                    >
                                        <x-papertrail.icon name="edit" />
                                    </a>

                                    <form method="POST" action="{{ route('admin.users.reset-password', $user) }}" data-confirm="Assign the temporary password password123 and require this user to change it after login?">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="icon-action icon-action--password" aria-label="Reset password" title="Reset password">
                                            <x-papertrail.icon name="key" />
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.users.status', $user) }}" data-confirm="{{ $user->status === 'active' ? 'Deactivate this account?' : 'Activate this account?' }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="icon-action {{ $user->status === 'active' ? 'icon-action--danger' : 'icon-action--success' }}" aria-label="{{ $user->status === 'active' ? 'Deactivate account' : 'Activate account' }}" title="{{ $user->status === 'active' ? 'Deactivate account' : 'Activate account' }}">
                                            <x-papertrail.icon name="{{ $user->status === 'active' ? 'power' : 'check' }}" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <template id="user-details-{{ $user->id }}">
                            <div class="admin-modal-detail">
                                <div class="admin-modal-detail__identity">
                                    <span>{{ collect(explode(' ', $user->name))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('') }}</span>
                                    <div>
                                        <h3>{{ $user->name }}</h3>
                                        <p>{{ $user->user_id }}</p>
                                    </div>
                                    <span class="status-pill status-{{ $user->status }}">{{ ucfirst($user->status) }}</span>
                                </div>

                                <dl class="admin-modal-detail__grid">
                                    <div><dt>Office</dt><dd>{{ $user->office }}</dd></div>
                                    <div><dt>Role</dt><dd>{{ $user->role }}</dd></div>
                                    <div><dt>Position</dt><dd>{{ $user->position ?: 'Not set' }}</dd></div>
                                    <div><dt>Contact Number</dt><dd>{{ $user->contact_number ?: 'Not set' }}</dd></div>
                                    <div><dt>Email</dt><dd>{{ $user->email ?: 'Not set' }}</dd></div>
                                    <div>
                                        <dt>Email Verification</dt>
                                        <dd>{{ $user->email && $user->email_verified_at ? 'Verified' : 'Not verified' }}</dd>
                                    </div>
                                    <div><dt>Created Date</dt><dd>{{ $user->created_at?->format('M d, Y') }}</dd></div>
                                    <div><dt>Last Updated</dt><dd>{{ $user->updated_at?->format('M d, Y') }}</dd></div>
                                </dl>
                            </div>
                        </template>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <strong>No users found</strong>
                                    <p>Try adjusting the search text or filters.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="pagination-wrap">
                {{ $users->appends(request()->query())->links('vendor.pagination.papertrail') }}
            </div>
        @endif
    </section>
@endsection
