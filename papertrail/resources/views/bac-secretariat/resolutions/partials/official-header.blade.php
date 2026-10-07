@php
    $lguLogoExists = file_exists(public_path('images/logos/lgu-logo.png'));
    $bagongLogoExists = file_exists(public_path('images/logos/bagongpilipinas.jpg'));
@endphp

<header class="bac-resolution-official-header" aria-label="Official BAC Resolution document header">
    <div class="bac-resolution-official-logo-slot">
        @if ($lguLogoExists)
            <img src="{{ asset('images/logos/lgu-logo.png') }}" alt="LGU Logo" class="bac-resolution-official-logo">
        @endif
    </div>

    <div class="bac-resolution-official-heading">
        <p>Republic of the Philippines</p>
        <p>Province of Southern Leyte</p>
        <p class="bac-resolution-official-municipality">MUNICIPALITY OF TOMAS OPPUS</p>
    </div>

    <div class="bac-resolution-official-logo-slot">
        @if ($bagongLogoExists)
            <img src="{{ asset('images/logos/bagongpilipinas.jpg') }}" alt="Bagong Pilipinas Logo" class="bac-resolution-official-logo bac-resolution-bagong-logo">
        @endif
    </div>
</header>
