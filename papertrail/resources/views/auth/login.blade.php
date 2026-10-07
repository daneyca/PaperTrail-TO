@extends('layouts.auth')

@section('title', 'Login | PaperTrail')

@section('content')
    <main class="pt-auth-page">
        <section class="pt-auth-visual" aria-label="PaperTrail system overview">
            <a class="pt-auth-home" href="{{ url('/') }}">Back to Home</a>
            <div class="pt-auth-visual-content">
                @if (file_exists(public_path('images/logos/lgu-logo.png')))
                    <img src="{{ asset('images/logos/lgu-logo.png') }}" alt="LGU Logo" class="pt-auth-seal">
                @endif
                <p class="pt-eyebrow">Authorized LGU Personnel Only</p>
                <h1>PaperTrail</h1>
                <h2>AI-Assisted Procurement Management System</h2>
                <p>
                    A secure workspace for LGU procurement document tracking, routing, review, approval, and monitoring.
                </p>
            </div>
        </section>

        <section class="pt-auth-panel" aria-labelledby="login-title">
            <x-floating-documents variant="auth" />
            <x-login-card :roles="$roles" :offices="$offices" />
        </section>
    </main>
@endsection

@push('scripts')
    <script>
        const roleSelect = document.querySelector('#role');
        const officeSelector = document.querySelector('#office-selector');
        const headOfficeRole = officeSelector?.dataset.headOfficeRole;
        const userIdInput = document.querySelector('#user_id');
        const captchaImage = document.querySelector('#captcha-image');
        const captchaRefresh = document.querySelector('#captcha-refresh');
        const securityCodeInput = document.querySelector('#security_code');
        const loginForm = document.querySelector('.pt-login-form');

        function toggleOfficeSelector() {
            if (!roleSelect || !officeSelector) {
                return;
            }

            const isHeadOffice = roleSelect.value === headOfficeRole;
            const officeSelect = officeSelector.querySelector('select[name="office"]');

            officeSelector.classList.toggle('is-visible', isHeadOffice);
            officeSelector.setAttribute('aria-hidden', String(!isHeadOffice));
            officeSelect.required = isHeadOffice;
            officeSelect.disabled = !isHeadOffice;

            if (!isHeadOffice) {
                officeSelect.value = '';
            }
        }

        function updateUserIdPlaceholder() {
            if (!roleSelect || !userIdInput) {
                return;
            }

            const selectedOption = roleSelect.options[roleSelect.selectedIndex];
            userIdInput.placeholder = selectedOption?.dataset.userIdPlaceholder || 'ADMIN-001';
        }

        roleSelect?.addEventListener('change', () => {
            toggleOfficeSelector();
            updateUserIdPlaceholder();
        });
        toggleOfficeSelector();
        updateUserIdPlaceholder();

        captchaRefresh?.addEventListener('click', function () {
            captchaImage.src = captchaImage.dataset.src + '?t=' + Date.now();
            securityCodeInput.value = '';
            securityCodeInput.focus();
        });

        loginForm?.addEventListener('submit', () => {
            if (!loginForm.checkValidity()) {
                return;
            }

            try {
                sessionStorage.setItem('papertrail_show_dashboard_loading_after_login', 'true');
            } catch (error) {
                // Loading hint is optional.
            }
        });
    </script>
@endpush
