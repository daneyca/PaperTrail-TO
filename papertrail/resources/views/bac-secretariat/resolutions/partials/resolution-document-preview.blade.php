@php
    $mode = $mode ?? 'show';
    $sourceDocument = $sourceDocument ?? $resolution->sourcePrDocument;
    $documentHtml = old('document_html', $resolution->document_html);
    $body = old('body_json', $resolution->body_json ?: $resolution->header_lines ?: []);
    $whereasClauses = array_values(old('whereas_clauses', $resolution->whereas_clauses ?: []));
    $resolvedClauses = array_values(old('resolved_clauses', $resolution->resolved_clauses ?: []));
    $signatories = old('signatories', $resolution->signatories ?: []);
    $approvalDetails = old('approval_signatory', old('approval_details', $resolution->approval_details ?: $resolution->approval_signatory ?: []));
    $signatureSlots = collect($signatureSlots ?? []);
    $bacChairSignature = $bacChairSignature ?? $signatureSlots->get('chairperson') ?? $signatureSlots->get('bac_chairperson');
    $projectTitle = old('project_title', $resolution->project_title ?: ($sourceDocument?->title ?? '____________________________'));
    $resolutionNumber = old('resolution_number', $resolution->resolution_number);
    $resolutionDate = old('resolution_date', $resolution->resolution_date?->format('Y-m-d'));
    $contractorName = old('contractor_name', $resolution->contractor_name ?: $resolution->supplier_name);
    $abcAmount = old('abc_amount', $resolution->abc_amount ?? $resolution->total_amount);
    $abcDisplay = $abcAmount ? 'PHP '.number_format((float) $abcAmount, 2) : '________________________';
    $philgeps = old('philgeps_reference_no', $resolution->philgeps_reference_no);
    $solicitation = old('solicitation_no', $resolution->solicitation_no);
    $actionText = $body['recommended_action'] ?? '________________________';
    $reviewDateText = $body['review_date_text'] ?? '________________________';
    $amountWords = old('total_amount_words', $resolution->total_amount_words);
    $amountText = $amountWords && $abcAmount ? $amountWords.' ('.$abcDisplay.')' : $abcDisplay;
    $title = old('title', $resolution->title ?: 'A RESOLUTION RECOMMENDING THE APPROVAL OF __________________________ FOR THE PROCUREMENT OF THE PROJECT "'.$projectTitle.'"');

    $whereasClauses = $whereasClauses ?: [
        'WHEREAS, the Local Government Unit of Tomas Oppus, Southern Leyte intended to procure the project titled "'.$projectTitle.'" with an Approved Budget for the Contract (ABC) of '.$abcDisplay.' under PhilGEPS Reference No. '.($philgeps ?: '________________________').' and Solicitation No. '.($solicitation ?: '________________________').';',
        'WHEREAS, in response to the procurement activity, '.($contractorName ?: '________________________').' submitted the required documents for the aforementioned project;',
        'WHEREAS, on '.$reviewDateText.', the Bids and Awards Committee (BAC) reviewed the procurement proceedings and supporting documents for the said project;',
        'WHEREAS, the BAC found the need to recommend appropriate action in accordance with applicable procurement rules, regulations, and the best interest of the government;',
        'WHEREAS, the action was initiated to serve the best interest of the government and the public, and was strictly made in accordance with applicable laws, rules, and regulations;',
        'WHEREAS, '.($contractorName ?: '________________________').' submitted the necessary request and/or supporting documents to the Bids and Awards Committee for proper action;',
        'WHEREAS, guided by the principles of fairness, equity, transparency, accountability, and the applicable rules of the Government Procurement Policy Board (GPPB), the BAC finds the request to be valid, justifiable, and legally sound;',
    ];

    $resolvedClauses = $resolvedClauses ?: [
        'To RECOMMEND the approval of '.$actionText.' in favor of '.($contractorName ?: '________________________').' for the project "'.$projectTitle.'" in the amount of '.$amountText.';',
        'To forward this Resolution to the Local Chief Executive for final approval and appropriate action.',
    ];

    $signatureDefaults = [
        'prepared_by' => 'BAC Secretariat',
        'vice_chairperson' => 'BAC Vice Chairperson',
        'member_one' => 'BAC Member',
        'member_two' => 'BAC Member',
        'provisional_member' => 'BAC Member',
        'chairperson' => 'BAC Chairperson',
    ];

    $plain = fn ($value, string $fallback = '') => filled($value) ? $value : $fallback;
@endphp

<div class="resolution-screen-area signature-request-preview">
    <div class="resolution-document-wrap">
        <article class="resolution-paper resolution-document bac-resolution-official-sheet bac-resolution-official-body" aria-label="BAC Resolution document preview">
            @include('bac-secretariat.resolutions.partials.resolution-document-body')
        </article>
    </div>
</div>
