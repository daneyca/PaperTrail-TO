<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'PaperTrail | AI-Assisted Procurement Management System')</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ file_exists(public_path('css/landing.css')) ? filemtime(public_path('css/landing.css')) : time() }}">
    @stack('styles')
</head>
<body class="pt-public-body">
    <x-navbar />

    <main>
        @yield('content')
    </main>

    <x-footer />

    <script>
        document.querySelector('[data-public-nav-toggle]')?.addEventListener('click', function () {
            const nav = document.querySelector('[data-public-nav]');
            const isOpen = nav?.classList.toggle('is-open');
            this.setAttribute('aria-expanded', String(Boolean(isOpen)));
        });
    </script>
    @stack('scripts')
</body>
</html>
