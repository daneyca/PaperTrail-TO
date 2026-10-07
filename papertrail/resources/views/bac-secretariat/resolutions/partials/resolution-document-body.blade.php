@php
    $officialHeaderHtml = trim(view('bac-secretariat.resolutions.partials.official-header')->render());
    $normalizeOfficialHeader = function (?string $html) use ($officialHeaderHtml) {
        if (! filled($html)) {
            return $html;
        }

        $html = preg_replace(
            '/<header\b[^>]*class=(["\'])(?=[^"\']*\bbac-resolution-official-header\b)[^"\']*\1[^>]*>.*?<\/header>/is',
            '',
            $html
        ) ?? $html;

        $html = preg_replace(
            '/<section\b([^>]*class=(["\'])(?=[^"\']*\bresolution-header\b)[^"\']*\2[^>]*)>.*?<\/section>/is',
            '',
            $html
        ) ?? $html;

        $html = preg_replace(
            '/<div\b[^>]*class=(["\'])(?=[^"\']*\bbac-resolution-official-heading\b)[^"\']*\1[^>]*>.*?<\/div>/is',
            '',
            $html
        ) ?? $html;

        $html = preg_replace(
            '/<div\b[^>]*class=(["\'])(?=[^"\']*\bbac-resolution-official-logo-slot\b)[^"\']*\1[^>]*>.*?<\/div>/is',
            '',
            $html
        ) ?? $html;

        $html = preg_replace(
            '/(?:<p\b[^>]*>\s*)?Republic of the Philippines\s*(?:<\/p>)?\s*(?:<p\b[^>]*>\s*)?Province of Southern Leyte\s*(?:<\/p>)?\s*(?:<p\b[^>]*>\s*)?MUNICIPALITY OF TOMAS OPPUS\s*(?:<\/p>)?/i',
            '',
            $html
        ) ?? $html;

        $html = trim($html);

        return $officialHeaderHtml.($html !== '' ? "\n".$html : '');
    };
    $canEditSignatureFields = (bool) ($canEditDocument ?? false);
    $signatureRoleOptions = [
        'BAC Secretariat',
        'BAC Vice Chairperson',
        'BAC Member',
        'BAC Chair',
        'BAC Chairperson',
        'Head of the Procuring Entity',
        'Local Chief Executive / Mayor',
        'Municipal Mayor',
        'HOPE / Municipal Mayor',
    ];
    $inlineSignatureField = function (string $key, ?object $signature = null, array $overrides = []) use (&$signatories, &$approvalDetails, &$signatureDefaults, &$signatureRoleOptions, &$canEditSignatureFields, &$plain) {
        $isApproval = $key === 'hope';
        $fieldPrefix = $isApproval ? 'approval_signatory' : "signatories[{$key}]";
        $oldPrefix = $isApproval ? 'approval_signatory' : "signatories.{$key}";
        $details = $isApproval ? ($approvalDetails ?? []) : ($signatories[$key] ?? []);
        $defaultDesignation = $isApproval ? 'Local Chief Executive / Mayor' : ($signatureDefaults[$key] ?? '');

        return view('bac-secretariat.resolutions.partials.resolution-signatory-inline-field', [
            'slot' => $key,
            'fieldPrefix' => $fieldPrefix,
            'oldPrefix' => $oldPrefix,
            'details' => $details,
            'signature' => $signature,
            'defaultDesignation' => $defaultDesignation,
            'roleOptions' => $signatureRoleOptions,
            'canEditSignatureFields' => $canEditSignatureFields,
            'plain' => $plain,
            ...$overrides,
        ])->render();
    };
@endphp

@if (filled($documentHtml))
    @php
        $renderedDocumentHtml = $normalizeOfficialHeader($documentHtml);
        $signatureSectionHtml = view('bac-secretariat.resolutions.partials.resolution-signature-section', [
            'signatureSlots' => $signatureSlots,
            'bacChairSignature' => $bacChairSignature ?? null,
            'inlineSignatureField' => $inlineSignatureField,
            'approvalDetails' => $approvalDetails ?? [],
        ])->render();

        $renderedDocumentHtml = preg_replace(
            '/<section\b[^>]*class=(["\'])(?=[^"\']*\bbac-resolution-signatures\b)[^"\']*\1[^>]*>.*\z/is',
            '',
            $renderedDocumentHtml
        ) ?? $renderedDocumentHtml;

        $renderedDocumentHtml = preg_replace(
            '/<p\b[^>]*class=(["\'])(?=[^"\']*\bresolution-attested-title\b)[^"\']*\1[^>]*>.*\z/is',
            '',
            $renderedDocumentHtml
        ) ?? $renderedDocumentHtml;

        $orphanSignatureClasses = implode('|', [
            'resolution-prepared',
            'resolution-signatory-prepared_by',
            'resolution-signatory-vice_chairperson',
            'resolution-signatory-bac_vice_chairperson',
            'resolution-signatory-member_one',
            'resolution-signatory-bac_member_1',
            'resolution-signatory-member_two',
            'resolution-signatory-bac_member_2',
            'resolution-signatory-provisional_member',
            'resolution-signatory-bac_provisional_member',
            'resolution-chair',
            'resolution-signatory-chairperson',
            'resolution-signatory-bac_chairperson',
            'resolution-approved',
            'resolution-signatory-hope',
        ]);

        $renderedDocumentHtml = preg_replace(
            '/<(section|div)\b[^>]*class=(["\'])(?=[^"\']*\b(?:'.$orphanSignatureClasses.')\b)[^"\']*\2[^>]*>.*\z/is',
            '',
            $renderedDocumentHtml
        ) ?? $renderedDocumentHtml;

        do {
            $renderedDocumentHtml = preg_replace(
                '/<(section|div)\b([^>]*class=(["\'])(?=[^"\']*\b(?:'.$orphanSignatureClasses.')\b)[^"\']*\3[^>]*)>.*?<\/\1>/is',
                '',
                $renderedDocumentHtml,
                -1,
                $removedOrphanSignatures
            ) ?? $renderedDocumentHtml;
        } while ($removedOrphanSignatures > 0);

        $renderedDocumentHtml = trim($renderedDocumentHtml)."\n".$signatureSectionHtml;
    @endphp

    {!! $renderedDocumentHtml !!}
@else
    @include('bac-secretariat.resolutions.partials.official-header')

    <div class="resolution-committee">BIDS AND AWARDS COMMITTEE</div>

    <div class="resolution-number">
        BAC RESOLUTION NO. {{ $resolutionNumber ?: '________' }}
    </div>

    <div class="resolution-title">{{ $title }}</div>

    @foreach ($whereasClauses as $clause)
        <p class="resolution-paragraph">{{ $clause }}</p>
    @endforeach

    <p class="resolution-now">{{ $body['now_therefore'] ?? 'NOW, THEREFORE, We, the Members of the Bids and Awards Committee, hereby RESOLVE as it is hereby RESOLVED:' }}</p>

    <ol class="resolution-resolved-list">
        @foreach ($resolvedClauses as $clause)
            <li>{{ $clause }}</li>
        @endforeach
    </ol>

    <p class="resolution-resolved-date">
        RESOLVED this
        {{ $body['resolved_day'] ?? '____' }}
        day of
        {{ $body['resolved_month'] ?? '________________' }}
        {{ $body['resolved_year'] ?? '20____' }},
        at
        {{ $body['resolved_location'] ?? 'the BAC Office, 2nd Floor, New Municipal Building, LGU Tomas Oppus, Southern Leyte' }}.
    </p>

    @include('bac-secretariat.resolutions.partials.resolution-signature-section')
@endif
