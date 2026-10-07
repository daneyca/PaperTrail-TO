@props([
    'roles' => [],
    'offices' => [],
])

@php
    $roleDisplayLabels = [
        'Head of Office / End User' => 'Head of Office',
        'BAC Chair' => 'BAC Chairman',
    ];
    $roleUserIdHints = [
        'Admin' => 'ADMIN-001',
        'Head of Office / End User' => 'MCR-001',
        'Budget Officer' => 'BUDGET-001',
        'Accounting Officer' => 'ACCOUNTING-001',
        'BAC Secretariat' => 'BACSEC-001',
        'BAC Member' => 'BACMEM-001',
        'BAC Chair' => 'BACCHAIR-001',
        'BAC Vice Chairperson' => 'BACVICE-001',
        'Head of the Procuring Entity' => 'HOPE-001',
        'PR Numbering Staff' => 'PRNO-001',
    ];
@endphp

<div class="pt-auth-card">
    <x-logo :show-municipality="false" class="pt-auth-logo" />

    <div class="pt-auth-title">
        <p class="pt-eyebrow">Secure Access</p>
        <h2 id="login-title">Welcome Back</h2>
        <span>Sign in to your procurement workspace.</span>
        <small>Use your assigned account credentials to continue.</small>
    </div>

    @if ($errors->any())
        <div class="form-alert" role="alert">
            Please review the highlighted login details.
        </div>
    @endif

    @if (session('status'))
        <div class="form-alert success-alert" role="status">{{ session('status') }}</div>
    @endif

    @if (session('warning'))
        <div class="form-alert warning-alert" role="alert">{{ session('warning') }}</div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="pt-login-form">
        @csrf

        <div class="pt-field">
            <label for="role">Select Login As</label>
            <select id="role" name="role" required>
                <option value="">Choose role</option>
                @if (count($roles) > 0)
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" data-user-id-placeholder="{{ $roleUserIdHints[$role] ?? 'USER-001' }}" @selected(old('role') === $role)>
                            {{ $roleDisplayLabels[$role] ?? $role }}
                        </option>
                    @endforeach
                @else
                    <option value="" disabled>No roles available</option>
                @endif
            </select>
            @error('role')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div id="office-selector" class="pt-field office-selector" data-head-office-role="Head of Office / End User" aria-hidden="true">
            <label for="office">End-User Office</label>
            <select id="office" name="office" disabled>
                <option value="">Choose end-user office</option>
                @if (count($offices) > 0)
                    @foreach ($offices as $office)
                        <option value="{{ $office['value'] }}" @selected(old('office') === $office['value'])>{{ $office['label'] }}</option>
                    @endforeach
                @else
                    <option value="" disabled>No offices available</option>
                @endif
            </select>
            @error('office')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="pt-field">
            <label for="user_id">User ID</label>
            <input id="user_id" name="user_id" type="text" value="{{ old('user_id') }}" placeholder="{{ $roleUserIdHints[old('role')] ?? 'ADMIN-001' }}" autocomplete="username" required>
            @error('user_id')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="pt-field">
            <label for="password">Password</label>
            <div class="pt-password-field">
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                <button
                    class="pt-password-toggle"
                    type="button"
                    aria-label="Show password"
                    aria-controls="password"
                    data-password-toggle="password"
                    data-password-visible="false"
                    title="Show password"
                >
                    <svg class="pt-password-icon pt-password-icon--show" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                    <svg class="pt-password-icon pt-password-icon--hide" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M3 3l18 18" />
                        <path d="M10.6 10.6A2 2 0 0 0 12 14a2 2 0 0 0 1.4-.6" />
                        <path d="M7.1 7.1C4.2 8.6 2.5 12 2.5 12s3.5 6 9.5 6c1.4 0 2.7-.3 3.8-.8" />
                        <path d="M17.4 14.9C20 13.3 21.5 12 21.5 12s-3.5-6-9.5-6c-.9 0-1.8.1-2.6.4" />
                    </svg>
                </button>
            </div>
            @error('password')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="pt-security-grid">
            <div class="pt-field">
                <label for="security_code">Security Code</label>
                <input id="security_code" name="security_code" type="text" inputmode="text" placeholder="Enter code" autocomplete="off" required>
                @error('security_code')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>
            <div class="pt-captcha-controls">
                <img
                    id="captcha-image"
                    class="captcha-image"
                    src="{{ route('captcha.image') }}?t={{ time() }}"
                    data-src="{{ route('captcha.image') }}"
                    alt="Security code image"
                    width="132"
                    height="44"
                >
                <button id="captcha-refresh" class="captcha-refresh" type="button" aria-label="Refresh security code" title="Refresh security code">
                    <span aria-hidden="true">&#8635;</span>
                </button>
            </div>
        </div>

        <button type="submit" class="pt-button pt-button-primary pt-login-button">Login</button>

        @if (\Illuminate\Support\Facades\Route::has('password.request'))
            <a class="pt-forgot-link" href="{{ route('password.request') }}">Forgot Password</a>
        @endif

        <p class="pt-auth-footnote">Secure access for authorized personnel only</p>
    </form>
</div>

@once
    <script>
        document.addEventListener('click', (event) => {
            const toggle = event.target.closest('[data-password-toggle]');

            if (!toggle) {
                return;
            }

            const passwordInput = document.getElementById(toggle.dataset.passwordToggle);

            if (!passwordInput) {
                return;
            }

            const shouldShow = passwordInput.type === 'password';
            passwordInput.type = shouldShow ? 'text' : 'password';
            toggle.dataset.passwordVisible = shouldShow ? 'true' : 'false';
            toggle.setAttribute('aria-label', shouldShow ? 'Hide password' : 'Show password');
            toggle.setAttribute('title', shouldShow ? 'Hide password' : 'Show password');
        });
    </script>
@endonce
