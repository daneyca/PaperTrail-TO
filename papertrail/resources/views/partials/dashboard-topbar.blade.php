@php
    $user = auth()->user();
    $unreadNotifications = \App\Services\SystemNotificationService::unreadCount($user);
    $recentNotifications = \App\Services\SystemNotificationService::latestForUser($user, 5);
    $profileHref = \Illuminate\Support\Facades\Route::has('profile.show') ? route('profile.show') : null;
    $profileEditHref = \Illuminate\Support\Facades\Route::has('profile.edit') ? route('profile.edit') : null;
    $officeName = trim((string) ($user?->assignedOffice?->name ?? $user?->office ?? ''));
    $roleName = trim((string) ($user?->assignedRole?->name ?? $user?->role ?? ''));
    $roleCode = trim((string) ($user?->assignedRole?->code ?? ''));
    $roleDisplayLabel = function (?string $value): string {
        $value = trim((string) $value);
        $normalized = strtolower(trim(preg_replace('/[\s_\-]+/', ' ', $value) ?? $value));

        return $normalized === 'bac chair' ? 'BAC Chairman' : $value;
    };
    $isHeadOfficeEndUser = collect([$roleCode, $roleName, $user?->role])
        ->filter()
        ->contains(fn ($value) => in_array(strtolower(trim((string) $value)), [
            'head_office',
            'head of office / end user',
        ], true));
    $displayRoleName = $isHeadOfficeEndUser ? '' : $roleDisplayLabel($roleName);
    $displayOfficeName = $isHeadOfficeEndUser ? $officeName : '';
    if ($displayOfficeName !== '' && $displayRoleName !== '' && strcasecmp($displayOfficeName, $displayRoleName) === 0) {
        $displayOfficeName = '';
    }
    $displayName = trim((string) ($user?->name ?? '')) ?: ($officeName ?: ($roleName ?: $user?->user_id));
    $profileLabel = collect([$displayName, $displayRoleName, $displayOfficeName])
        ->filter()
        ->unique(fn ($value) => strtolower(trim((string) $value)))
        ->implode(', ');
@endphp

<header class="dashboard-topbar enterprise-topbar" aria-label="Dashboard toolbar">
    <div class="enterprise-topbar__left">
        <form class="dashboard-search enterprise-topbar__search" role="search">
            <label class="sr-only" for="dashboard-search">Search documents</label>
            <span class="dashboard-search-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                    <path d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" />
                </svg>
            </span>
            <input id="dashboard-search" type="search" placeholder="Search this page...">
        </form>
    </div>

    <div class="enterprise-topbar__right">
        <div class="pht-clock-widget" data-pht-clock aria-live="polite">
            <span>Philippine Standard Time (PHT)</span>
            <strong data-pht-date>{{ now('Asia/Manila')->format('l, F d, Y') }}</strong>
            <time data-pht-time datetime="{{ now('Asia/Manila')->toIso8601String() }}">{{ now('Asia/Manila')->format('h:i:s A') }}</time>
        </div>

        <div class="dashboard-utility-group" aria-label="Dashboard utilities">
            <button class="dashboard-utility-button" type="button" data-theme-toggle aria-label="Switch to dark mode" title="Theme">
                <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M12 4V2M12 22v-2M4 12H2M22 12h-2M5.65 5.65 4.25 4.25M19.75 19.75l-1.4-1.4M18.35 5.65l1.4-1.4M4.25 19.75l1.4-1.4" />
                    <circle cx="12" cy="12" r="4" />
                </svg>
                <svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M20 15.25A8.25 8.25 0 0 1 8.75 4a7.25 7.25 0 1 0 11.25 11.25Z" />
                </svg>
            </button>

            <button class="dashboard-utility-button" type="button" data-fullscreen-toggle aria-label="Enter fullscreen" title="Fullscreen">
                <svg class="fullscreen-icon fullscreen-icon-enter" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M8.25 4.75H4.75v3.5M15.75 4.75h3.5v3.5M4.75 15.75v3.5h3.5M19.25 15.75v3.5h-3.5" />
                </svg>
                <svg class="fullscreen-icon fullscreen-icon-exit" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M9 4.75v4.5h-4.25M15 4.75v4.5h4.25M9 19.25v-4.5h-4.25M15 19.25v-4.5h4.25" />
                </svg>
            </button>
        </div>

        <div class="dashboard-profile">
            <div class="notification-menu">
                <button
                    class="notification-button"
                    type="button"
                    aria-label="{{ $unreadNotifications > 0 ? $unreadNotifications . ' unread notifications' : 'Notifications' }}"
                    title="Notifications"
                    data-notification-toggle
                    aria-expanded="false"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M18 9.75a6 6 0 0 0-12 0c0 6-2.25 6.25-2.25 6.25h16.5S18 15.75 18 9.75Z" />
                        <path d="M9.75 19a2.25 2.25 0 0 0 4.5 0" />
                    </svg>
                    @if ($unreadNotifications > 0)
                        <span class="notification-badge">{{ $unreadNotifications > 9 ? '9+' : $unreadNotifications }}</span>
                    @endif
                </button>

                <div class="notification-dropdown" data-notification-dropdown>
                    <div class="notification-dropdown-header">
                        <strong>Notifications</strong>
                        <a href="{{ route('notifications.index') }}">View all notifications</a>
                    </div>

                    <div class="notification-dropdown-list">
                        @forelse ($recentNotifications as $notification)
                            <a href="{{ route('notifications.show', $notification) }}" class="{{ $notification->read_at ? '' : 'is-unread' }}">
                                <span class="notification-preview-meta">
                                    <b class="notification-type-dot type-{{ $notification->type }}"></b>
                                    {{ $notification->module ?? 'System' }} &middot; {{ $notification->created_at?->diffForHumans() }}
                                </span>
                                <strong>{{ $notification->title }}</strong>
                                <span>{{ str($notification->message)->limit(78) }}</span>
                            </a>
                        @empty
                            <div class="notification-empty-state">
                                <strong>No notifications yet</strong>
                                <span>Document routing alerts, review updates, and deliberation notices will appear here.</span>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="topbar-account-menu" data-user-menu>
                <button
                    class="pt-user-profile-block topbar-account-toggle"
                    type="button"
                    data-user-menu-toggle
                    aria-haspopup="true"
                    aria-expanded="false"
                    aria-label="Open account menu for {{ $profileLabel }}"
                    title="{{ $profileLabel }}"
                >
                    <x-user-avatar :user="$user" size="sm" />
                    <span class="pt-user-profile-block__copy">
                        <strong>{{ $displayName }}</strong>
                        @if ($displayRoleName !== '')
                            <span class="pt-user-profile-block__role">{{ $displayRoleName }}</span>
                        @endif
                        @if ($displayOfficeName !== '')
                            <span class="pt-user-profile-block__office" title="{{ $displayOfficeName }}">{{ $displayOfficeName }}</span>
                        @endif
                    </span>
                </button>

                <div class="topbar-account-dropdown" data-user-menu-dropdown>
                    @if ($profileHref)
                        <a href="{{ $profileHref }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M20 21a8 8 0 0 0-16 0M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z" />
                            </svg>
                            <span>View Profile</span>
                        </a>
                    @endif
                    @if ($profileEditHref)
                        <a href="{{ $profileEditHref }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" />
                            </svg>
                            <span>Edit Profile</span>
                        </a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M10 5H5v14h5M14 8l4 4-4 4M8 12h10" />
                            </svg>
                            <span>Logout</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
