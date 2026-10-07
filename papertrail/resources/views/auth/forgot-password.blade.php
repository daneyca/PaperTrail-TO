@extends('layouts.auth')

@section('title', 'Forgot Password | PaperTrail')

@section('content')
    <main class="pt-auth-page pt-reset-page">
        <section class="pt-auth-visual" aria-label="PaperTrail account recovery">
            <a class="pt-auth-home" href="{{ url('/') }}">Back to Home</a>
            <div class="pt-auth-visual-content">
                @if (file_exists(public_path('images/logos/lgu-logo.png')))
                    <img src="{{ asset('images/logos/lgu-logo.png') }}" alt="LGU Logo" class="pt-auth-seal">
                @endif
                <p class="pt-eyebrow">Account Recovery</p>
                <h1>PaperTrail</h1>
                <h2>Secure password reset</h2>
                <p>
                    Password recovery is available only through verified official email addresses assigned to authorized LGU personnel.
                </p>

                <div class="pt-reset-steps" aria-label="Password reset steps">
                    <div><span>01</span><p>Enter your assigned User ID and official email.</p></div>
                    <div><span>02</span><p>Complete the security code before requesting the link.</p></div>
                    <div><span>03</span><p>Contact the administrator if your email is not verified.</p></div>
                </div>
            </div>
        </section>

        <section class="pt-auth-panel" aria-labelledby="forgot-title">
            <x-floating-documents variant="auth" />

            <div class="pt-auth-card pt-reset-card">
                <x-logo :show-municipality="false" class="pt-auth-logo" />

                <div class="pt-auth-title">
                    <p class="pt-eyebrow">Secure Reset</p>
                    <h2 id="forgot-title">Send Reset Link</h2>
                    <span>Recover access to your procurement workspace.</span>
                    <small>Use your assigned account credentials to continue.</small>
                </div>

                @if (session('status'))
                    <div class="pt-form-alert success-alert" role="status">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="pt-form-alert" role="alert">Please review the highlighted details.</div>
                @endif

                <form method="POST" action="{{ route('password.email') }}" class="pt-login-form">
                    @csrf

                    <div class="pt-field">
                        <label for="user_id">User ID</label>
                        <input id="user_id" name="user_id" type="text" value="{{ old('user_id') }}" placeholder="ADMIN-001" autocomplete="username" required>
                        @error('user_id')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="pt-field">
                        <label for="email">Official Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="name@lgu.gov.ph" autocomplete="email" required>
                        @error('email')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="pt-security-grid">
                        <div class="pt-field">
                            <label for="security_code">Security Code</label>
                            <input id="security_code" name="security_code" type="text" placeholder="Enter code" autocomplete="off" required>
                            @error('security_code')<p class="field-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="pt-captcha-controls">
                            <img id="captcha-image" class="captcha-image" src="{{ route('captcha.image') }}?t={{ time() }}" data-src="{{ route('captcha.image') }}" alt="Security code image" width="132" height="44">
                            <button id="captcha-refresh" class="captcha-refresh" type="button" aria-label="Refresh security code" title="Refresh security code">
                                <span aria-hidden="true">&#8635;</span>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="pt-login-button">Send Reset Link</button>
                    <a class="pt-forgot-link" href="{{ route('login') }}">Return to Sign In</a>

                    <p class="pt-auth-footnote">Secure access for authorized personnel only</p>
                </form>
            </div>
        </section>
    </main>
@endsection

@push('scripts')
    <script>
        const captchaImage = document.querySelector('#captcha-image');
        const captchaRefresh = document.querySelector('#captcha-refresh');
        const securityCodeInput = document.querySelector('#security_code');

        captchaRefresh?.addEventListener('click', function () {
            captchaImage.src = captchaImage.dataset.src + '?t=' + Date.now();
            securityCodeInput.value = '';
            securityCodeInput.focus();
        });
    </script>
@endpush
