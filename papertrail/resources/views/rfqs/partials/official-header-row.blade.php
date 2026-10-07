@php
    $lguLogoExists = file_exists(public_path('images/logos/lgu-logo.png'));
    $bagongLogoExists = file_exists(public_path('images/logos/bagongpilipinas.jpg'));
@endphp

<tr class="rfq-official-header-row">
    <td colspan="6" class="rfq-no-border rfq-official-header-cell">
        <div class="rfq-official-header" aria-label="Official RFQ document header">
            <div class="rfq-official-logo-slot rfq-official-logo-left">
                @if ($lguLogoExists)
                    <img src="{{ asset('images/logos/lgu-logo.png') }}" alt="LGU Logo" class="rfq-official-logo">
                @endif
            </div>

            <div class="rfq-official-heading">
                <div>Republic of the Philippines</div>
                <div>Province of Southern Leyte</div>
                <div class="rfq-official-municipality">MUNICIPALITY OF TOMAS OPPUS</div>
            </div>

            <div class="rfq-official-logo-slot rfq-official-logo-right">
                @if ($bagongLogoExists)
                    <img src="{{ asset('images/logos/bagongpilipinas.jpg') }}" alt="Bagong Pilipinas Logo" class="rfq-official-logo bagong-pilipinas-logo">
                @endif
            </div>
        </div>
    </td>
</tr>
