@php
    $colspan = $colspan ?? 10;
    $lguLogoExists = file_exists(public_path('images/logos/lgu-logo.png'));
    $bagongLogoExists = file_exists(public_path('images/logos/bagongpilipinas.jpg'));
@endphp

<tr class="abstract-official-header-row">
    <td colspan="{{ $colspan }}" class="abstract-no-border abstract-official-header-cell">
        <div class="abstract-official-header" aria-label="Official Abstract document header">
            <div class="abstract-official-logo-slot">
                @if ($lguLogoExists)
                    <img src="{{ asset('images/logos/lgu-logo.png') }}" alt="LGU Logo" class="abstract-official-logo">
                @endif
            </div>

            <div class="abstract-official-heading">
                <div>Republic of the Philippines</div>
                <div>Province of Southern Leyte</div>
                <div class="abstract-official-municipality">MUNICIPALITY OF TOMAS OPPUS</div>
            </div>

            <div class="abstract-official-logo-slot">
                @if ($bagongLogoExists)
                    <img src="{{ asset('images/logos/bagongpilipinas.jpg') }}" alt="Bagong Pilipinas Logo" class="abstract-official-logo abstract-bagong-logo">
                @endif
            </div>
        </div>
    </td>
</tr>
