@extends('layouts.dashboard')

@section('title', 'Email Notifications | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Administration</p>
            <h1>Email Notifications</h1>
            <p>Review safe mail configuration details and send a PaperTrail SMTP test email.</p>
        </div>
    </section>

    @if ($errors->any())
        <div class="session-alert session-alert-error" role="alert">
            Please enter a valid recipient email address.
        </div>
    @endif

    @if (session('status'))
        <div class="session-alert" role="status">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="session-alert session-alert-error" role="alert">{{ session('error') }}</div>
    @endif

    <section class="settings-console" aria-label="Settings console">
        <aside class="settings-category-nav" aria-label="Settings categories">
            <div class="settings-nav-heading">
                <span>Categories</span>
                <strong>{{ count($groups) }}</strong>
            </div>

            <nav>
                @foreach ($groups as $key => $item)
                    @php
                        $href = isset($item['route'])
                            ? route($item['route'])
                            : ($key === 'general' ? route('admin.settings.index') : ($key === 'email' ? route('admin.settings.email') : route('admin.settings.group', $key)));
                        $isActive = $groupKey === $key || (isset($item['route']) && request()->routeIs($item['route']));
                    @endphp
                    <a href="{{ $href }}" class="{{ $isActive ? 'is-active' : '' }}">
                        <strong>{{ $item['title'] }}</strong>
                        <span>{{ $item['description'] }}</span>
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="settings-console-main">
            <header class="settings-console-header">
                <div>
                    <p class="eyebrow">Email</p>
                    <h2>Email Notifications</h2>
                    <p>SMTP credentials are read from the environment and are never displayed here.</p>
                </div>
            </header>

            <div class="settings-list-panel" aria-label="Email notification configuration">
                <div class="settings-row">
                    <div class="settings-row-copy">
                        <strong>Email Notification Status</strong>
                        <p>Controls whether workflow notifications attempt to send email after creating in-app alerts.</p>
                    </div>
                    <div class="settings-row-control">
                        <span class="status-badge {{ $mail['enabled'] ? 'status-active' : 'status-inactive' }}">
                            {{ $mail['enabled'] ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                </div>

                <div class="settings-row">
                    <div class="settings-row-copy">
                        <strong>Mail Driver</strong>
                        <p>The configured Laravel mail transport.</p>
                    </div>
                    <div class="settings-row-control">
                        <input type="text" value="{{ $mail['driver'] ?: 'Not configured' }}" disabled>
                    </div>
                </div>

                <div class="settings-row">
                    <div class="settings-row-copy">
                        <strong>Mail Host</strong>
                        <p>The SMTP host currently loaded by Laravel configuration.</p>
                    </div>
                    <div class="settings-row-control">
                        <input type="text" value="{{ $mail['host'] ?: 'Not configured' }}" disabled>
                    </div>
                </div>

                <div class="settings-row">
                    <div class="settings-row-copy">
                        <strong>Mail Port</strong>
                        <p>The SMTP port currently loaded by Laravel configuration.</p>
                    </div>
                    <div class="settings-row-control">
                        <input type="text" value="{{ $mail['port'] ?: 'Not configured' }}" disabled>
                    </div>
                </div>

                <div class="settings-row">
                    <div class="settings-row-copy">
                        <strong>Mail Encryption</strong>
                        <p>Gmail SMTP on port 587 should use TLS.</p>
                    </div>
                    <div class="settings-row-control">
                        <input type="text" value="{{ $mail['encryption'] ?: 'None' }}" disabled>
                    </div>
                </div>

                <div class="settings-row">
                    <div class="settings-row-copy">
                        <strong>From Address</strong>
                        <p>The sender identity used for PaperTrail email notifications.</p>
                    </div>
                    <div class="settings-row-control">
                        <input type="text" value="{{ $mail['from_address'] ?: 'Not configured' }}" disabled>
                    </div>
                </div>

                <div class="settings-row">
                    <div class="settings-row-copy">
                        <strong>From Name</strong>
                        <p>The display name used in PaperTrail email notifications.</p>
                    </div>
                    <div class="settings-row-control">
                        <input type="text" value="{{ $mail['from_name'] ?: 'Not configured' }}" disabled>
                    </div>
                </div>

                @if (! empty($mailWarnings))
                    <div class="settings-row">
                        <div class="settings-row-copy">
                            <strong>Delivery Warnings</strong>
                            <p>These warnings affect inbox delivery after SMTP accepts the message.</p>
                        </div>
                        <div class="settings-row-control">
                            <div class="mail-warning-list">
                                @foreach ($mailWarnings as $warning)
                                    <p>{{ $warning }}</p>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <form method="POST" action="{{ route('admin.settings.email.test') }}" class="settings-list-panel">
                @csrf

                <div class="settings-row">
                    <div class="settings-row-copy">
                        <label for="test-email">Send Test Email</label>
                        <p>Use this to verify Gmail SMTP without exposing the app password.</p>
                    </div>
                    <div class="settings-row-control">
                        <input id="test-email" name="email" type="email" value="{{ old('email') }}" placeholder="receiver@example.com" required>
                        @error('email')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="settings-console-actions">
                    <button type="submit">Send Test Email</button>
                    <a href="{{ route('admin.settings.index') }}">Back to Settings</a>
                </div>
            </form>
        </div>
    </section>
@endsection
