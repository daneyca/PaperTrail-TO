@php
    $slotAliases = [
        'vice_chairperson' => 'bac_vice_chairperson',
        'member_one' => 'bac_member_1',
        'member_two' => 'bac_member_2',
        'provisional_member' => 'bac_provisional_member',
    ];
@endphp

<section class="resolution-signature-section bac-resolution-signatures">
    <p class="resolution-attested-title">ATTESTED BY:</p>

    <section class="bac-signature-grid resolution-signature-grid">
        @foreach (['vice_chairperson', 'member_one', 'member_two', 'provisional_member'] as $key)
            @php
                $slotSignature = $signatureSlots->get($key) ?? $signatureSlots->get($slotAliases[$key]);
            @endphp
            <div class="bac-signature-block resolution-signature-block resolution-signatory-{{ $key }} resolution-signatory-{{ $slotAliases[$key] }}" data-signatory-display="{{ $key }}">
                @if ($slotSignature)
                    @include('components.e-signature.compact-signed-block', ['signature' => $slotSignature])
                @else
                    {!! $inlineSignatureField($key, null, ['lineClass' => 'bac-signature-line resolution-sign-line']) !!}
                @endif
            </div>
        @endforeach
    </section>

    <section class="resolution-chair resolution-signatory-chairperson resolution-signatory-bac_chairperson" data-signatory-display="chairperson">
        @php
            $chairSignature = $signatureSlots->get('chairperson') ?? $signatureSlots->get('bac_chairperson') ?? ($bacChairSignature ?? null);
        @endphp
        @if ($chairSignature)
            @include('components.e-signature.compact-signed-block', ['signature' => $chairSignature])
        @else
            {!! $inlineSignatureField('chairperson') !!}
        @endif
    </section>

    <section class="resolution-approved resolution-signatory-hope" data-signatory-display="hope">
        @php
            $hopeSignature = $signatureSlots->get('hope');
        @endphp
        <p class="resolution-approved-title">APPROVED BY:</p>
        @if ($hopeSignature)
            @include('components.e-signature.compact-signed-block', ['signature' => $hopeSignature])
        @else
            {!! $inlineSignatureField('hope', null, [
                'lineClass' => 'resolution-approved-line',
                'nameClass' => 'resolution-approved-name',
                'designationClass' => 'resolution-approved-label',
                'includeDate' => true,
            ]) !!}
        @endif
        <p class="resolution-date-approved">
            Date Approved:
            {{ $approvalDetails['date'] ?? '________________' }}
        </p>
    </section>
</section>
