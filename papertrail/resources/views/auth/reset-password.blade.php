@extends('layouts.public')

@section('title', 'Reset Password | PaperTrail')

@section('content')
    <main class="auth-page">
        <section class="auth-shell auth-shell-compact" aria-labelledby="reset-title">
            <div class="auth-instructions">
                <x-logo :show-municipality="false" class="auth-brand" />

                <p class="eyebrow">Account Security</p>
                <h1 id="reset-title">Reset Password</h1>
                <p class="auth-copy">Create a new password for your PaperTrail account. Reset links are single-use and expire automatically.</p>

                <a class="back-link" href="{{ route('login') }}">Back to Login</a>
            </div>

            <div class="login-panel">
                <div class="panel-title">
                    <p class="eyebrow">Secure Reset</p>
                    <h2>Set New Password</h2>
                </div>

                @if ($errors->any())
                    <div class="form-alert" role="alert">Invalid or expired reset link. Please request a new one.</div>
                @endif

                <form method="POST" action="{{ route('password.update') }}" class="login-form">
                    @csrf

                    <input type="hidden" name="token" value="{{ $token }}">
                    <input type="hidden" name="email" value="{{ old('email', $email) }}">

                    <label for="password">New Password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required>
                    @error('password')<p class="field-error">{{ $message }}</p>@enderror

                    <label for="password_confirmation">Confirm New Password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>

                    @error('email')<p class="field-error">{{ $message }}</p>@enderror

                    <button type="submit" class="button button-primary">Reset Password</button>
                    <a class="forgot-link" href="{{ route('password.request') }}">Request a New Link</a>
                </form>
            </div>
        </section>
    </main>
@endsection
