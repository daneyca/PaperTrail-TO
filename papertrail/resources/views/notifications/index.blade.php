@extends('layouts.dashboard')

@section('title', 'Notifications | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero notifications-header">
        <div>
            <p class="eyebrow">Account Center</p>
            <h1>Notifications</h1>
            <p>{{ $subtitle ?? 'View system alerts, routing updates, and pending actions.' }}</p>
        </div>

        <div class="report-header-actions">
            @if ($counts['unread'] > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="dashboard-action">Mark All as Read</button>
                </form>
            @endif

            @if ($counts['read'] > 0)
                <form method="POST" action="{{ route('notifications.clear-read') }}" onsubmit="return confirm('Delete all read notifications?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="dashboard-action secondary-action">Clear Read</button>
                </form>
            @endif
        </div>
    </section>

    <section class="stat-grid stat-grid-modern notification-summary-strip" aria-label="Notification summary">
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $counts['all'] }}" label="All Notifications" accent="navy" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $counts['unread'] }}" label="Unread" accent="gold" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $counts['read'] }}" label="Read" accent="green" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $counts['today'] }}" label="Today" accent="blue" />
    </section>

    <section class="table-panel notification-filter-panel" aria-label="Notification filters">
        <form method="GET" action="{{ route('notifications.index') }}" class="notification-filter-toolbar">
            <div class="user-search">
                <label for="notification-search">Search notifications</label>
                <input id="notification-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Title or message">
            </div>

            <div class="user-filter">
                <label for="type-filter">Type</label>
                <select id="type-filter" name="type">
                    <option value="">All types</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="module-filter">Module</label>
                <select id="module-filter" name="module">
                    <option value="">All modules</option>
                    @foreach ($modules as $module)
                        <option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>{{ $module }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="status-filter">Status</label>
                <select id="status-filter" name="status">
                    <option value="">All</option>
                    <option value="unread" @selected(($filters['status'] ?? request('filter')) === 'unread')>Unread</option>
                    <option value="read" @selected(($filters['status'] ?? request('filter')) === 'read')>Read</option>
                </select>
            </div>

            <div class="user-filter">
                <label for="date-from">Date From</label>
                <input id="date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}">
            </div>

            <div class="user-filter">
                <label for="date-to">Date To</label>
                <input id="date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}">
            </div>

            <div class="filter-actions">
                <button type="submit">Apply</button>
                <a href="{{ route('notifications.index') }}">Clear</a>
            </div>
        </form>
    </section>

    <section class="table-panel notification-list-panel" aria-label="Notifications list">
        <div class="notification-list">
            @forelse ($notifications as $notification)
                <article class="notification-row {{ $notification->read_at ? 'is-read' : 'is-unread' }}">
                    <div class="notification-main">
                        <div class="notification-title-line">
                            <span class="notification-type type-{{ $notification->type }}">{{ ucfirst($notification->type) }}</span>
                            <span class="notification-module">{{ $notification->module ?? 'System' }}</span>
                            <span class="notification-unread-dot">{{ $notification->read_at ? 'Read' : 'Unread' }}</span>
                        </div>

                        <h2>{{ $notification->title }}</h2>
                        <p>{{ $notification->message }}</p>
                        <time datetime="{{ $notification->created_at?->toIso8601String() }}">{{ $notification->created_at?->format('M d, Y h:i A') }} &middot; {{ $notification->created_at?->diffForHumans() }}</time>
                    </div>

                    <div class="notification-actions">
                        <x-ui.action-button
                            :href="route('notifications.show', $notification)"
                            icon="view"
                            label="View Details"
                            tooltip="View Details"
                            variant="view"
                            icon-only
                        />

                        @if ($notification->action_url)
                            <x-ui.action-button
                                :href="$notification->action_url"
                                icon="open"
                                label="Open Related Document"
                                tooltip="Open Related Document"
                                variant="view"
                                icon-only
                            />
                        @endif

                        @unless ($notification->read_at)
                            <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                @csrf
                                @method('PATCH')
                                <x-ui.action-button
                                    type="submit"
                                    icon="check"
                                    label="Mark as Read"
                                    tooltip="Mark as Read"
                                    variant="success"
                                    icon-only
                                />
                            </form>
                        @endunless

                        <form method="POST" action="{{ route('notifications.destroy', $notification) }}" onsubmit="return confirm('Delete this notification?');">
                            @csrf
                            @method('DELETE')
                            <x-ui.action-button
                                type="submit"
                                icon="delete"
                                label="Delete Record"
                                tooltip="Delete Record"
                                variant="danger"
                                icon-only
                            />
                        </form>
                    </div>
                </article>
            @empty
                <div class="empty-state">
                    <strong>No notifications yet</strong>
                    <p>{{ auth()->user()?->role === \App\Models\User::ROLE_BAC_CHAIR ? 'BAC approval alerts, confirmation updates, and routing notices will appear here.' : 'Document routing alerts, review updates, and deliberation notices will appear here.' }}</p>
                </div>
            @endforelse
        </div>

        @if ($notifications->hasPages())
            <div class="pagination-wrap">
                {{ $notifications->appends(request()->query())->links('vendor.pagination.papertrail') }}
            </div>
        @endif
    </section>
@endsection
