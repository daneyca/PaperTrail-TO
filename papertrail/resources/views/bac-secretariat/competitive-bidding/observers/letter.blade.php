@php
    $data = array_replace(\App\Models\ObserverInvitation::defaults(), $data ?? []);
    $editable = (bool) ($editable ?? false);
@endphp
<article class="observer-letter {{ $editable ? 'observer-letter--editable' : '' }}" aria-label="Official LGU Tomas Oppus observer invitation">
    <header class="observer-letterhead">
        <img class="observer-lgu-logo" src="{{ asset('images/logos/lgu-logo.png') }}" alt="Municipality of Tomas Oppus seal">
        <div class="observer-letterhead__text">
            @foreach (['republic' => 'Republic heading', 'province' => 'Province heading', 'municipality' => 'Municipality heading', 'separator' => 'Letterhead separator'] as $key => $label)
                @include('bac-secretariat.competitive-bidding.observers.field', ['path' => $key, 'value' => $data[$key], 'fieldClass' => 'observer-letterhead__'.$key])
            @endforeach
        </div>
        <img class="observer-landscape" src="{{ asset('images/landing/municipality-landscape.jpg') }}" alt="Municipality of Tomas Oppus">
    </header>
    <div class="observer-office-heading">
        @include('bac-secretariat.competitive-bidding.observers.field', ['path' => 'office_heading', 'value' => $data['office_heading'], 'label' => 'Issuing office heading'])
    </div>
    <div class="observer-letter-date">
        @include('bac-secretariat.competitive-bidding.observers.field', ['path' => 'letter_date', 'value' => $data['letter_date'], 'label' => 'Letter date'])
    </div>
    <div class="observer-recipient">
        @include('bac-secretariat.competitive-bidding.observers.field', ['path' => 'recipient_name', 'value' => $data['recipient_name'], 'label' => 'Recipient name', 'placeholder' => 'Enter Name', 'fieldClass' => 'observer-recipient__name'])
        @include('bac-secretariat.competitive-bidding.observers.field', ['path' => 'recipient_details', 'value' => $data['recipient_details'], 'label' => 'Recipient position, organization and address', 'placeholder' => "Enter position, organization and address\nUse a new line for each detail"])
    </div>
    @foreach (['salutation' => 'Salutation', 'greeting' => 'Greeting', 'introduction' => 'Invitation introduction and venue'] as $key => $label)
        <div class="observer-paragraph observer-paragraph--{{ $key }}">
            @include('bac-secretariat.competitive-bidding.observers.field', ['path' => $key, 'value' => $data[$key]])
        </div>
    @endforeach
    <div data-observer-sections>
        @foreach ($data['sections'] as $sectionIndex => $section)
            @include('bac-secretariat.competitive-bidding.observers.section', ['path' => 'sections.'.$sectionIndex])
        @endforeach
    </div>
    @if ($editable)
        <button type="button" class="observer-row-add no-print" data-action-icon="false" data-observer-add="section">Add Activity</button>
    @endif
    @foreach (['request_paragraph' => 'Request for observer attendance', 'thank_you' => 'Thank-you paragraph', 'closing' => 'Letter closing'] as $key => $label)
        <div class="observer-paragraph observer-paragraph--{{ $key }}">
            @include('bac-secretariat.competitive-bidding.observers.field', ['path' => $key, 'value' => $data[$key]])
        </div>
    @endforeach
    <div class="observer-signatory">
        <div class="observer-physical-signature-space" aria-label="Space for physical signature"></div>
        @include('bac-secretariat.competitive-bidding.observers.field', ['path' => 'signatory_name', 'value' => $data['signatory_name'], 'label' => 'Printed name of signatory', 'placeholder' => 'Enter Name', 'fieldClass' => 'observer-signatory__name'])
        @include('bac-secretariat.competitive-bidding.observers.field', ['path' => 'signatory_position', 'value' => $data['signatory_position'], 'label' => 'Signatory position'])
    </div>
</article>
