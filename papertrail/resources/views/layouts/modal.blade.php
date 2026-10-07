<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'PaperTrail')</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/saas.css') }}?v={{ file_exists(public_path('css/saas.css')) ? filemtime(public_path('css/saas.css')) : time() }}">
    <link rel="stylesheet" href="{{ asset('css/svp-tracking.css') }}?v={{ file_exists(public_path('css/svp-tracking.css')) ? filemtime(public_path('css/svp-tracking.css')) : time() }}">
    <link rel="stylesheet" href="{{ asset('css/document-forms.css') }}?v={{ file_exists(public_path('css/document-forms.css')) ? filemtime(public_path('css/document-forms.css')) : time() }}">
    <link rel="stylesheet" href="{{ asset('css/document-info.css') }}?v={{ file_exists(public_path('css/document-info.css')) ? filemtime(public_path('css/document-info.css')) : time() }}">
    <link rel="stylesheet" href="{{ asset('css/papertrail-density.css') }}?v={{ file_exists(public_path('css/papertrail-density.css')) ? filemtime(public_path('css/papertrail-density.css')) : time() }}">
    <link rel="stylesheet" href="{{ asset('css/ai-feature-tools.css') }}?v={{ file_exists(public_path('css/ai-feature-tools.css')) ? filemtime(public_path('css/ai-feature-tools.css')) : time() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/papertrail-dark-mode.css') }}?v={{ file_exists(public_path('css/papertrail-dark-mode.css')) ? filemtime(public_path('css/papertrail-dark-mode.css')) : time() }}">
    <style>
        body.dashboard-body.modal-document-body {
            min-height: 0 !important;
            overflow-x: hidden !important;
        }

        body.dashboard-body.modal-document-body .modal-document-content {
            gap: 0 !important;
            padding: 8px 10px 10px !important;
        }

        body.dashboard-body.modal-document-body .dashboard-hero {
            display: none !important;
        }

        body.dashboard-body.modal-document-body .form-panel > form,
        body.dashboard-body.modal-document-body .admin-role-edit-card form {
            gap: 8px !important;
            padding: 10px !important;
        }

        body.dashboard-body.modal-document-body .form-panel .form-grid,
        body.dashboard-body.modal-document-body .admin-role-edit-card .form-grid {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 8px 10px !important;
        }

        body.dashboard-body.modal-document-body .form-panel .form-field,
        body.dashboard-body.modal-document-body .admin-role-edit-card .form-field {
            gap: 3px !important;
            min-width: 0 !important;
        }

        body.dashboard-body.modal-document-body .form-panel .form-field-full,
        body.dashboard-body.modal-document-body .admin-role-edit-card .form-field-full {
            grid-column: 1 / -1 !important;
        }

        body.dashboard-body.modal-document-body .form-panel .form-field label:not(.inline-check),
        body.dashboard-body.modal-document-body .admin-role-edit-card .form-field label:not(.inline-check) {
            font-size: 0.62rem !important;
            font-weight: 800 !important;
            line-height: 1.15 !important;
        }

        body.dashboard-body.modal-document-body .form-panel .form-field :where(input:not([type="checkbox"]):not([type="radio"]), select, textarea),
        body.dashboard-body.modal-document-body .admin-role-edit-card .form-field :where(input:not([type="checkbox"]):not([type="radio"]), select, textarea) {
            min-height: 32px !important;
            border-radius: 9px !important;
            padding: 5px 9px !important;
            font-size: 0.8rem !important;
            line-height: 1.2 !important;
        }

        body.dashboard-body.modal-document-body .form-panel .form-field textarea,
        body.dashboard-body.modal-document-body .admin-role-edit-card .form-field textarea {
            min-height: 58px !important;
            resize: vertical !important;
        }

        body.dashboard-body.modal-document-body .form-panel .form-actions,
        body.dashboard-body.modal-document-body .admin-role-edit-card .form-actions {
            gap: 8px !important;
            padding-top: 0 !important;
        }

        body.dashboard-body.modal-document-body .form-panel .form-actions :where(button, a),
        body.dashboard-body.modal-document-body .admin-role-edit-card .form-actions :where(button, a) {
            min-height: 34px !important;
            border-radius: 9px !important;
            padding: 0 14px !important;
            font-size: 0.8rem !important;
        }

        @media (max-width: 520px) {
            body.dashboard-body.modal-document-body .form-panel .form-grid,
            body.dashboard-body.modal-document-body .admin-role-edit-card .form-grid {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
    <script>
        try {
            if (localStorage.getItem('papertrail_theme') === 'dark') {
                document.documentElement.classList.add('papertrail-dark-mode');
            }
        } catch (error) {
            // Theme preference is optional.
        }
    </script>
</head>
<body class="dashboard-body modal-document-body">
    <main class="modal-document-content">
        @if (session('status'))
            <div class="session-alert" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="session-alert session-alert-error" role="alert">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>

    <script>
        document.addEventListener('submit', (event) => {
            const form = event.target;

            if (!(form instanceof HTMLFormElement) || !form.dataset.confirmStatusChange) {
                return;
            }

            const currentStatus = form.dataset.currentStatus || '';
            const statusField = form.querySelector('[name="status"]');
            const nextStatus = statusField?.value || '';

            if (!nextStatus || nextStatus === currentStatus) {
                return;
            }

            const message = nextStatus === 'inactive' ? 'Deactivate this account?' : 'Activate this account?';

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    </script>
    @stack('scripts')
</body>
</html>
