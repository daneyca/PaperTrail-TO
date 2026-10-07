@php
    $lguLogoExists = file_exists(public_path('images/logos/lgu-logo.png'));
    $bagongLogoExists = file_exists(public_path('images/logos/bagongpilipinas.jpg'));
@endphp

<header class="po-official-header" aria-label="Official Purchase Order document header">
    <div class="po-official-logo-slot">
        @if ($lguLogoExists)
            <img src="{{ asset('images/logos/lgu-logo.png') }}" alt="LGU Logo" class="po-official-logo">
        @endif
    </div>

    <div class="po-official-heading">
        <div>Republic of the Philippines</div>
        <div>Province of Southern Leyte</div>
        <div class="po-official-municipality">MUNICIPALITY OF TOMAS OPPUS</div>
    </div>

    <div class="po-official-logo-slot">
        @if ($bagongLogoExists)
            <img src="{{ asset('images/logos/bagongpilipinas.jpg') }}" alt="Bagong Pilipinas Logo" class="po-official-logo po-bagong-logo">
        @endif
    </div>
</header>
