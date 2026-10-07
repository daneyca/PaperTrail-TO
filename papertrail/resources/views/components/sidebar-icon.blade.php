@props([
    'name' => 'circle',
])

@php
    $paths = [
        'dashboard' => ['M4 5h6v6H4z', 'M14 5h6v6h-6z', 'M4 15h6v4H4z', 'M14 15h6v4h-6z'],
        'users' => ['M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6z', 'M16 10a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z', 'M3.5 19a4.5 4.5 0 0 1 9 0', 'M13.5 18.5a3.5 3.5 0 0 1 7 0'],
        'planning' => ['M7 3h7l4 4v14H7z', 'M14 3v5h5', 'M9 12h7', 'M9 16h6'],
        'workflow' => ['M5 7h6v4H5z', 'M13 13h6v4h-6z', 'M11 9h3.5a2.5 2.5 0 0 1 2.5 2.5V13', 'M13 15H9.5A2.5 2.5 0 0 1 7 12.5V11'],
        'tracking' => ['M6 19V5', 'M6 5l5 2.5L18 5v14l-7 2.5L6 19z', 'M11 7.5v14'],
        'signature' => ['M4 18h6', 'M5 15l9.5-9.5a2 2 0 0 1 3 3L8 18H5z'],
        'bell' => ['M18 16H6l1.5-2V10a4.5 4.5 0 0 1 9 0v4z', 'M10 19a2 2 0 0 0 4 0'],
        'mail' => ['M4 6h16v12H4z', 'm4.5 7 7.5 5.5L19.5 7'],
        'user' => ['M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z', 'M4.5 20a7.5 7.5 0 0 1 15 0'],
        'reports' => ['M5 19V9', 'M12 19V5', 'M19 19v-7', 'M4 19h16'],
        'settings' => ['M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z', 'M12 3v2', 'M12 19v2', 'M3 12h2', 'M19 12h2', 'M5.6 5.6 7 7', 'M18.4 5.6l-7 7'],
        'budget' => ['M4 7h16v10H4z', 'M7 10h4', 'M16 14h1', 'M6 7V5h12v2'],
        'review' => ['M5 5h14v14H5z', 'M8 12l2.5 2.5L16 9'],
        'approval' => ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z', 'M8.5 12l2.2 2.2 4.8-5'],
        'hash' => ['M9 4L7 20', 'M17 4l-2 16', 'M4 9h16', 'M3 15h16'],
        'logout' => ['M10 5H5v14h5', 'M14 8l4 4-4 4', 'M8 12h10'],
        'circle' => ['M12 19a7 7 0 1 0 0-14 7 7 0 0 0 0 14z'],
    ];

    $iconPaths = $paths[$name] ?? $paths['circle'];
@endphp

<svg {{ $attributes->merge(['class' => 'sidebar-menu-icon', 'viewBox' => '0 0 24 24', 'aria-hidden' => 'true', 'focusable' => 'false']) }}>
    @foreach ($iconPaths as $path)
        <path d="{{ $path }}" />
    @endforeach
</svg>
