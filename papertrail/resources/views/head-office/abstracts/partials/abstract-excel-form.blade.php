@php
    $mode = $mode ?? 'show';
    $isEditable = in_array($mode, ['create', 'edit'], true);
    $field = fn (string $name, mixed $default = '') => old($name, filled($abstract->{$name} ?? null) ? $abstract->{$name} : $default);
    $money = fn ($value) => filled($value) ? number_format((float) $value, 2, '.', '') : '';
    $text = fn ($value, string $fallback = '') => e(filled($value) ? $value : $fallback);
    $abstractDate = old('abstract_date', $abstract->abstract_date?->format('Y-m-d') ?? now()->toDateString());
    $items = collect(old('items_json') ? json_decode(old('items_json'), true) : null);
    $suppliers = collect(old('suppliers_json') ? json_decode(old('suppliers_json'), true) : ($abstract->suppliers_json ?? []));
    $awards = collect(old('awards_json') ? json_decode(old('awards_json'), true) : ($abstract->awards_json ?? []));
    $committee = collect(old('committee_json') ? json_decode(old('committee_json'), true) : ($abstract->committee_json ?? []));
    $signatoryUsers = collect($signatoryUsers ?? []);
    $committeeDefaults = collect([
        'bac_chairperson' => 'ROEL J. BANO',
        'bac_vice_chairperson' => 'EDMAR T. TAMBIS',
        'bac_member_1' => 'PERPETUA D. SALAN',
        'bac_member_2' => 'ROGELIO A. LAYO',
        'bac_member_alternate' => 'JESSA G. RESUS',
    ]);
    $committeeRoleDefinitions = collect([
        'bac_chairperson' => ['label' => 'BAC Chairperson'],
        'bac_vice_chairperson' => ['label' => 'BAC Vice Chairperson'],
        'bac_member_1' => ['label' => 'BAC Member I'],
        'bac_member_2' => ['label' => 'BAC Member II'],
        'bac_member_alternate' => ['label' => 'BAC Member III'],
    ]);
    $committeeEntry = function (string $role) use ($committee): array {
        $entry = $committee->get($role);

        if (is_array($entry)) {
            return $entry;
        }

        return [
            'name' => (string) ($entry ?? ''),
            'account_code' => (string) ($committee->get($role.'_account_code') ?? ''),
            'designation' => (string) ($committee->get($role.'_designation') ?? ''),
        ];
    };
    $committeeName = function (string $role) use ($committeeEntry, $committeeDefaults): string {
        $entry = $committeeEntry($role);

        return filled($entry['name'] ?? null) ? (string) $entry['name'] : (string) $committeeDefaults->get($role, '');
    };
    $committeeDesignation = function (string $role) use ($committeeEntry, $committeeRoleDefinitions): string {
        $entry = $committeeEntry($role);
        $default = (string) data_get($committeeRoleDefinitions->get($role), 'label', '');
        $designation = filled($entry['designation'] ?? null) ? (string) $entry['designation'] : $default;

        if (in_array($role, ['bac_member_1', 'bac_member_2', 'bac_member_alternate'], true)
            && in_array(strtolower(str_replace(['-', '  '], [' ', ' '], $designation)), ['bac member', 'bac member alternate'], true)) {
            return $default;
        }

        return $designation;
    };
    $committeeAccountCode = function (string $role) use ($committeeEntry): string {
        $entry = $committeeEntry($role);

        return (string) ($entry['account_code'] ?? '');
    };
    $committeeShouldShowDesignation = function (string $role) use ($committeeAccountCode, $committeeDesignation): bool {
        return filled($committeeAccountCode($role)) && filled($committeeDesignation($role));
    };
    $committeeSelectedAccount = function (string $role) use ($committeeAccountCode, $committeeName, $signatoryUsers): string {
        $accountCode = $committeeAccountCode($role);

        if ($accountCode !== '') {
            return $accountCode;
        }

        $name = $committeeName($role);
        $matchedUser = $signatoryUsers->first(function ($user) use ($name) {
            return strcasecmp((string) $user->name, $name) === 0
                || strcasecmp((string) $user->typed_signature_name, $name) === 0;
        });

        return (string) ($matchedUser?->user_id ?? '');
    };

    $romanNumeral = fn (int $number): string => match ($number) {
        1 => 'I',
        2 => 'II',
        3 => 'III',
        4 => 'IV',
        5 => 'V',
        default => (string) $number,
    };
    $signatoryRoleTokens = fn ($user): string => strtolower(implode(' ', array_filter([
        (string) ($user->user_id ?? ''),
        (string) ($user->role ?? ''),
        (string) ($user->assignedRole?->code ?? ''),
        (string) ($user->assignedRole?->name ?? ''),
    ])));
    $isBacMemberAccount = function ($user) use ($signatoryRoleTokens): bool {
        $tokens = $signatoryRoleTokens($user);

        return str_contains($tokens, 'bacmem')
            || str_contains($tokens, 'bac_member')
            || str_contains($tokens, 'bac member');
    };
    $bacMemberOrderByAccount = $signatoryUsers
        ->filter($isBacMemberAccount)
        ->sortBy(fn ($user) => (string) ($user->user_id ?? ''))
        ->values()
        ->mapWithKeys(fn ($user, int $index) => [(string) ($user->user_id ?? $index) => $index + 1]);
    $signatoryRoleLabel = function ($user) use ($bacMemberOrderByAccount, $romanNumeral, $signatoryRoleTokens): string {
        $tokens = $signatoryRoleTokens($user);
        $accountCode = (string) ($user->user_id ?? '');

        if (str_contains($tokens, 'bacvice') || (str_contains($tokens, 'vice') && str_contains($tokens, 'chair'))) {
            return 'BAC Vice Chairperson';
        }

        if (str_contains($tokens, 'bacchair') || str_contains($tokens, 'bac chair')) {
            return 'BAC Chairperson';
        }

        if (str_contains($tokens, 'bacmem') || str_contains($tokens, 'bac_member') || str_contains($tokens, 'bac member')) {
            $memberNumber = (int) ($bacMemberOrderByAccount->get($accountCode) ?: 1);

            if (preg_match('/(\d+)\s*$/', $accountCode, $matches)) {
                $memberNumber = max(1, (int) $matches[1]);
            }

            return 'BAC Member '.$romanNumeral($memberNumber);
        }

        return (string) ($user->assignedRole?->name ?? $user->role ?? 'Signatory');
    };
    $signatoryOptionOrder = function (string $roleLabel, string $accountCode): string {
        return match (true) {
            str_contains($roleLabel, 'Chairperson') && ! str_contains($roleLabel, 'Vice') => '01-'.$accountCode,
            str_contains($roleLabel, 'Vice Chairperson') => '02-'.$accountCode,
            str_contains($roleLabel, 'Member I') => '03-'.$accountCode,
            str_contains($roleLabel, 'Member II') => '04-'.$accountCode,
            str_contains($roleLabel, 'Member III') => '05-'.$accountCode,
            default => '99-'.$roleLabel.'-'.$accountCode,
        };
    };
    $committeeSelectOptions = $signatoryUsers
        ->map(function ($user) use ($signatoryRoleLabel, $signatoryOptionOrder) {
            $accountCode = (string) ($user->user_id ?? '');
            $roleLabel = $signatoryRoleLabel($user);

            return [
                'value' => $accountCode,
                'label' => $roleLabel,
                'name' => $user->typed_signature_name ?: $user->name,
                'designation' => $roleLabel,
                'sort' => $signatoryOptionOrder($roleLabel, $accountCode),
            ];
        })
        ->sortBy('sort')
        ->map(fn (array $option) => collect($option)->except('sort')->all())
        ->values()
        ->all();
    $committeeSelectData = $committeeRoleDefinitions->mapWithKeys(function (array $definition, string $role) use ($committeeName, $committeeDesignation, $committeeSelectedAccount, $committeeSelectOptions) {
        return [$role => [
            'label' => $definition['label'],
            'selected' => $committeeSelectedAccount($role),
            'fallbackName' => $committeeName($role),
            'fallbackDesignation' => $committeeDesignation($role),
            'options' => $committeeSelectOptions,
        ]];
    });
    $committeeBlockHtml = '<tr><td colspan="10" class="abstract-cell abstract-no-border abstract-bold abstract-approval-heading" data-field="approval_heading">APPROVED BY COMMITTEE ON BIDS AND AWARDS:</td></tr><tr><td colspan="10" class="abstract-no-border"><div class="abstract-committee-grid">';

    foreach ($committeeRoleDefinitions as $role => $definition) {
        $committeeBlockHtml .= '<div class="abstract-committee-person">'
            .'<span class="abstract-cell abstract-committee-name abstract-bottom-line" data-committee-role="'.e($role).'">'.e($committeeName($role)).'</span>';

        if ($committeeShouldShowDesignation($role)) {
            $committeeBlockHtml .= '<span class="abstract-cell abstract-committee-designation" data-committee-designation="'.e($role).'">'.e($committeeDesignation($role)).'</span>';
        }

        $committeeBlockHtml .= '</div>';
    }

    $committeeBlockHtml .= '</div></td></tr>';

    if ($items->isEmpty()) {
        if (is_array($abstract->items_json)) {
            $items = collect($abstract->items_json);
        } elseif ($abstract->relationLoaded('items') || $abstract->exists) {
            $items = $abstract->items->map(fn ($item) => [
                'item_no' => $item->item_no,
                'name_of_goods_services' => $item->name_of_goods_services,
                'quantity' => $item->quantity,
                'unit_of_measure' => $item->unit_of_measure,
                'supplier_1_amount' => $item->supplier_1_amount,
                'supplier_2_amount' => $item->supplier_2_amount,
                'supplier_3_amount' => $item->supplier_3_amount,
                'supplier_4_amount' => $item->supplier_4_amount,
                'supplier_5_amount' => $item->supplier_5_amount,
                'total_lowest_price' => $item->total_lowest_price,
            ]);
        }
    }

    $rowCount = max(22, $items->count());
    $savedHtml = old('document_html', $abstract->document_html);
    $officialHeaderRow = trim(view('abstracts.partials.official-header-row', ['colspan' => 10])->render());
    $upgradeOfficialHeader = function (?string $html) use ($officialHeaderRow) {
        if (! filled($html) || str_contains($html, 'abstract-official-header-row')) {
            return $html;
        }

        $updated = preg_replace(
            '/<tr>\s*<td\b[^>]*>\s*Republic of the Philippines\s*<\/td>\s*<\/tr>\s*<tr>\s*<td\b[^>]*>\s*Province of Southern Leyte\s*<\/td>\s*<\/tr>\s*<tr>\s*<td\b[^>]*>\s*MUNICIPALITY OF TOMAS OPPUS\s*<\/td>\s*<\/tr>\s*<tr>\s*<td\b[^>]*>\s*(?:&nbsp;|\s)*<\/td>\s*<\/tr>/i',
            $officialHeaderRow,
            $html,
            1
        );

        return $updated ?? $html;
    };
    $upgradeCommitteeBlock = function (?string $html) use ($committeeBlockHtml): ?string {
        if (! filled($html) || str_contains($html, 'abstract-committee-grid')) {
            return $html;
        }

        $pattern = '/<tr>\s*<td\b[^>]*>\s*APPROVED BY COMMITTEE ON BIDS AND AWARDS:\s*<\/td>\s*<\/tr>\s*(?:<tr>\s*<td\b[^>]*>\s*(?:&nbsp;|\s)*<\/td>\s*<\/tr>\s*)?(?:<tr\b[^>]*class=(["\'])[^"\']*abstract-signature-row[^"\']*\1[\s\S]*?<\/tr>\s*){1,3}/i';
        $updated = preg_replace($pattern, $committeeBlockHtml, $html, 1);

        if ($updated === $html) {
            $updated = preg_replace(
                '/<tr>\s*<td\b[^>]*>\s*APPROVED BY COMMITTEE ON BIDS AND AWARDS:\s*<\/td>\s*<\/tr>[\s\S]*?BAC Member\s*-?\s*Alternate[\s\S]*?<\/tr>/i',
                $committeeBlockHtml,
                $html,
                1
            );
        }

        return $updated ?? $html;
    };
    $applyCommitteeDefaults = function (?string $html) use ($committeeDefaults, $committeeRoleDefinitions): ?string {
        if (! filled($html)) {
            return $html;
        }

        foreach ($committeeDefaults as $role => $name) {
            $html = preg_replace_callback(
                '/(<[^>]+data-committee-role=(["\'])'.preg_quote($role, '/').'\2[^>]*>)([\s\S]*?)(<\/[^>]+>)/i',
                function (array $matches) use ($name): string {
                    $current = trim(html_entity_decode(strip_tags($matches[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                    return $current !== ''
                        ? $matches[0]
                        : $matches[1].e($name).$matches[4];
                },
                $html
            );
        }

        foreach ($committeeRoleDefinitions as $role => $definition) {
            $html = preg_replace_callback(
                '/(<[^>]+data-committee-designation=(["\'])'.preg_quote($role, '/').'\2[^>]*>)([\s\S]*?)(<\/[^>]+>)/i',
                function (array $matches) use ($definition): string {
                    $current = trim(html_entity_decode(strip_tags($matches[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                    return $current !== ''
                        ? $matches[0]
                        : $matches[1].e($definition['label']).$matches[4];
                },
                $html
            );
        }

        return $html;
    };
    $stripCommitteeDesignationLabels = function (?string $html): ?string {
        if (! filled($html)) {
            return $html;
        }

        $html = preg_replace('/\s*<([a-z][a-z0-9]*)\b(?=[^>]*(?:data-committee-designation|abstract-committee-designation))[^>]*>[\s\S]*?<\/\1>/i', '', $html);
        $html = preg_replace('/\s*<([a-z][a-z0-9]*)\b[^>]*>\s*(BAC Chairperson|BAC Vice Chairperson|BAC Member(?:\s+(?:I|II|III|IV|V)|\s*-\s*Alternate|\s+Alternate)?)\s*<\/\1>/i', '', $html ?? '');

        return preg_replace('/>\s*(BAC Chairperson|BAC Vice Chairperson|BAC Member(?:\s+(?:I|II|III|IV|V)|\s*-\s*Alternate|\s+Alternate)?)\s*</i', '><', $html ?? '');
    };
    $ensureCommitteeDesignationLabels = function (?string $html) use ($committeeRoleDefinitions, $committeeDesignation, $committeeShouldShowDesignation, $stripCommitteeDesignationLabels): ?string {
        if (! filled($html)) {
            return $html;
        }

        $html = $stripCommitteeDesignationLabels($html);

        foreach ($committeeRoleDefinitions as $role => $definition) {
            if (! $committeeShouldShowDesignation($role)) {
                continue;
            }

            $designation = e($committeeDesignation($role));
            $html = preg_replace(
                '/(<span\b[^>]*data-committee-role=(["\'])'.preg_quote($role, '/').'\2[^>]*>[\s\S]*?<\/span>)/i',
                '$1<span class="abstract-cell abstract-committee-designation" data-committee-designation="'.e($role).'">'.$designation.'</span>',
                $html,
                1
            );
        }

        return $html;
    };
    $renderedHtml = $applyCommitteeDefaults($upgradeCommitteeBlock($upgradeOfficialHeader($savedHtml)));
    $renderedHtml = $isEditable
        ? $stripCommitteeDesignationLabels($renderedHtml)
        : $ensureCommitteeDesignationLabels($renderedHtml);
    $supplierTotal = fn (int $number) => $suppliers->get("supplier_{$number}_total", '');

    if (filled($renderedHtml) && ! $isEditable) {
        $renderedHtml = preg_replace('/\scontenteditable(=("|\').*?\2|=[^\s>]*)?/i', '', $renderedHtml);
    }
@endphp

@if ($isEditable)
    <input type="hidden" name="source_pr_document_id" value="{{ old('source_pr_document_id', $abstract->source_pr_document_id ?? $sourceDocument?->id) }}">
    <input type="hidden" name="source_rfq_id" value="{{ old('source_rfq_id', $abstract->source_rfq_id ?? $sourceRfq?->id) }}">
    <input type="hidden" name="source_bac_resolution_id" value="{{ old('source_bac_resolution_id', $abstract->source_bac_resolution_id ?? $sourceResolution?->id) }}">
    <input type="hidden" name="document_html" id="abstract_document_html">
    <input type="hidden" name="document_text" id="abstract_document_text">
    <input type="hidden" name="items_json" id="abstract_items_json">
    <input type="hidden" name="suppliers_json" id="abstract_suppliers_json">
    <input type="hidden" name="awards_json" id="abstract_awards_json">
    <input type="hidden" name="committee_json" id="abstract_committee_json">
    @foreach ([
        'abstract_number', 'abstract_date', 'project_name', 'implementing_office', 'abc_amount', 'purpose',
        'supplier_1_name', 'supplier_2_name', 'supplier_3_name', 'supplier_4_name', 'supplier_5_name',
        'lowest_supplier_name', 'lowest_total_amount'
    ] as $hiddenField)
        <input type="hidden" name="{{ $hiddenField }}" data-hidden-field="{{ $hiddenField }}">
    @endforeach
@endif

<div class="abstract-document-wrap">
    <section class="abstract-a4-page">
        <div class="abstract-document" id="abstractEditorContainer">
        @if (filled($renderedHtml))
            {!! $renderedHtml !!}
        @else
            <table id="abstractEditor" class="abstract-excel-form">
                <colgroup>
                    <col style="width:6%">
                    <col style="width:24%">
                    <col style="width:8%">
                    <col style="width:9%">
                    <col style="width:8%">
                    <col style="width:8%">
                    <col style="width:8%">
                    <col style="width:8%">
                    <col style="width:8%">
                    <col style="width:13%">
                </colgroup>
                <tbody>
                    @include('abstracts.partials.official-header-row', ['colspan' => 10])
                    <tr><td colspan="10" class="abstract-no-border abstract-title abstract-main-title">ABSTRACT OF QUOTATIONS/CANVASS</td></tr>
                    <tr>
                        <td colspan="7" class="abstract-no-border"></td>
                        <td colspan="1" class="abstract-no-border abstract-right">No.</td>
                        <td colspan="2" class="abstract-cell abstract-no-border abstract-bottom-line" data-field="abstract_number">{!! $text($field('abstract_number')) !!}</td>
                    </tr>
                    <tr>
                        <td colspan="7" class="abstract-no-border"></td>
                        <td colspan="1" class="abstract-no-border abstract-right">Date</td>
                        <td colspan="2" class="abstract-cell abstract-no-border abstract-bottom-line" data-field="abstract_date">{!! $text($abstractDate) !!}</td>
                    </tr>
                    <tr>
                        <td colspan="2" class="abstract-no-border">Name of the Project:</td>
                        <td colspan="8" class="abstract-cell abstract-no-border abstract-bottom-line" data-field="project_name">{!! $text($field('project_name')) !!}</td>
                    </tr>
                    <tr>
                        <td colspan="2" class="abstract-no-border">Implementing Office:</td>
                        <td colspan="8" class="abstract-cell abstract-no-border abstract-bottom-line" data-field="implementing_office">{!! $text($field('implementing_office')) !!}</td>
                    </tr>
                    <tr>
                        <td colspan="3" class="abstract-no-border">Approved Budget for the Contract:</td>
                        <td colspan="3" class="abstract-cell abstract-no-border abstract-bottom-line abstract-right" data-field="abc_amount">{!! $text($money($field('abc_amount'))) !!}</td>
                        <td colspan="4" class="abstract-no-border"></td>
                    </tr>
                    <tr><td colspan="10" class="abstract-no-border">&nbsp;</td></tr>
                    <tr class="abstract-table-header">
                        <th>ITEM NO.</th>
                        <th>NAME OF GOODS/SERVICES</th>
                        <th>QUANTITY</th>
                        <th>UNIT OF MEASURE</th>
                        <th class="abstract-cell" data-field="supplier_1_name">1<br>AMOUNT / UNIT<br>{!! $text($field('supplier_1_name')) !!}</th>
                        <th class="abstract-cell" data-field="supplier_2_name">2<br>AMOUNT / UNIT<br>{!! $text($field('supplier_2_name')) !!}</th>
                        <th class="abstract-cell" data-field="supplier_3_name">3<br>AMOUNT / UNIT<br>{!! $text($field('supplier_3_name')) !!}</th>
                        <th class="abstract-cell" data-field="supplier_4_name">4<br>AMOUNT / UNIT<br>{!! $text($field('supplier_4_name')) !!}</th>
                        <th class="abstract-cell" data-field="supplier_5_name">5<br>AMOUNT / UNIT<br>{!! $text($field('supplier_5_name')) !!}</th>
                        <th>TOTAL LOWEST PRICE</th>
                    </tr>
                    @for ($index = 0; $index < $rowCount; $index++)
                        @php $item = $items->get($index, []); @endphp
                        <tr class="abstract-item-row" data-row="{{ $index }}">
                            <td class="abstract-cell abstract-item-cell abstract-center" data-column="item_no" data-row="{{ $index }}">{!! $text($item['item_no'] ?? '') !!}</td>
                            <td class="abstract-cell abstract-item-cell abstract-description-cell" data-column="name_of_goods_services" data-row="{{ $index }}">{!! $text($item['name_of_goods_services'] ?? '') !!}</td>
                            <td class="abstract-cell abstract-item-cell abstract-right" data-column="quantity" data-row="{{ $index }}">{!! $text($money($item['quantity'] ?? null)) !!}</td>
                            <td class="abstract-cell abstract-item-cell abstract-center" data-column="unit_of_measure" data-row="{{ $index }}">{!! $text($item['unit_of_measure'] ?? '') !!}</td>
                            <td class="abstract-cell abstract-item-cell abstract-right supplier-amount" data-column="supplier_1_amount" data-row="{{ $index }}">{!! $text($money($item['supplier_1_amount'] ?? null)) !!}</td>
                            <td class="abstract-cell abstract-item-cell abstract-right supplier-amount" data-column="supplier_2_amount" data-row="{{ $index }}">{!! $text($money($item['supplier_2_amount'] ?? null)) !!}</td>
                            <td class="abstract-cell abstract-item-cell abstract-right supplier-amount" data-column="supplier_3_amount" data-row="{{ $index }}">{!! $text($money($item['supplier_3_amount'] ?? null)) !!}</td>
                            <td class="abstract-cell abstract-item-cell abstract-right supplier-amount" data-column="supplier_4_amount" data-row="{{ $index }}">{!! $text($money($item['supplier_4_amount'] ?? null)) !!}</td>
                            <td class="abstract-cell abstract-item-cell abstract-right supplier-amount" data-column="supplier_5_amount" data-row="{{ $index }}">{!! $text($money($item['supplier_5_amount'] ?? null)) !!}</td>
                            <td class="abstract-cell abstract-item-cell abstract-right lowest-price-cell" data-column="total_lowest_price" data-row="{{ $index }}">{!! $text($money($item['total_lowest_price'] ?? null)) !!}</td>
                        </tr>
                    @endfor
                    <tr class="abstract-total-row">
                        <td colspan="4" class="abstract-right abstract-bold">TOTAL</td>
                        <td class="abstract-cell abstract-right abstract-bold" data-field="supplier_1_total">{!! $text($money($supplierTotal(1))) !!}</td>
                        <td class="abstract-cell abstract-right abstract-bold" data-field="supplier_2_total">{!! $text($money($supplierTotal(2))) !!}</td>
                        <td class="abstract-cell abstract-right abstract-bold" data-field="supplier_3_total">{!! $text($money($supplierTotal(3))) !!}</td>
                        <td class="abstract-cell abstract-right abstract-bold" data-field="supplier_4_total">{!! $text($money($supplierTotal(4))) !!}</td>
                        <td class="abstract-cell abstract-right abstract-bold" data-field="supplier_5_total">{!! $text($money($supplierTotal(5))) !!}</td>
                        <td class="abstract-cell abstract-right abstract-bold" data-field="lowest_total_amount">{!! $text($money($field('lowest_total_amount'))) !!}</td>
                    </tr>
                    <tr><td colspan="10" class="abstract-no-border">&nbsp;</td></tr>
                    <tr class="abstract-note-row"><td colspan="10" class="abstract-cell abstract-no-border abstract-bold" data-field="note_title">Note:</td></tr>
                    @for ($index = 1; $index <= 3; $index++)
                        <tr class="abstract-note-row">
                            <td class="abstract-no-border abstract-center">{{ $index }}</td>
                            <td colspan="9" class="abstract-cell abstract-no-border abstract-bottom-line" data-award-row="{{ $index }}">Items No. ____ awarded to ____ being quoted the lowest price.</td>
                        </tr>
                    @endfor
                    <tr><td colspan="10" class="abstract-no-border">&nbsp;</td></tr>
                    <tr>
                        <td colspan="10" class="abstract-cell abstract-no-border" data-field="certification_text">Certification: We hereby certify that the above procurement is in accordance with the approved revised procurement plan for 202__.</td>
                    </tr>
                    <tr><td colspan="10" class="abstract-no-border">&nbsp;</td></tr>
                    <tr><td colspan="10" class="abstract-cell abstract-no-border abstract-bold abstract-approval-heading" data-field="approval_heading">APPROVED BY COMMITTEE ON BIDS AND AWARDS:</td></tr>
                    <tr>
                        <td colspan="10" class="abstract-no-border">
                            <div class="abstract-committee-grid">
                                <div class="abstract-committee-person">
                                    <span class="abstract-cell abstract-committee-name abstract-bottom-line" data-committee-role="bac_chairperson">{{ $committeeName('bac_chairperson') }}</span>
                                    @if ($committeeShouldShowDesignation('bac_chairperson'))
                                    <span class="abstract-cell abstract-committee-designation" data-committee-designation="bac_chairperson">{{ $committeeDesignation('bac_chairperson') }}</span>
                                    @endif
                                </div>
                                <div class="abstract-committee-person">
                                    <span class="abstract-cell abstract-committee-name abstract-bottom-line" data-committee-role="bac_vice_chairperson">{{ $committeeName('bac_vice_chairperson') }}</span>
                                    @if ($committeeShouldShowDesignation('bac_vice_chairperson'))
                                    <span class="abstract-cell abstract-committee-designation" data-committee-designation="bac_vice_chairperson">{{ $committeeDesignation('bac_vice_chairperson') }}</span>
                                    @endif
                                </div>
                                <div class="abstract-committee-person">
                                    <span class="abstract-cell abstract-committee-name abstract-bottom-line" data-committee-role="bac_member_1">{{ $committeeName('bac_member_1') }}</span>
                                    @if ($committeeShouldShowDesignation('bac_member_1'))
                                    <span class="abstract-cell abstract-committee-designation" data-committee-designation="bac_member_1">{{ $committeeDesignation('bac_member_1') }}</span>
                                    @endif
                                </div>
                                <div class="abstract-committee-person">
                                    <span class="abstract-cell abstract-committee-name abstract-bottom-line" data-committee-role="bac_member_2">{{ $committeeName('bac_member_2') }}</span>
                                    @if ($committeeShouldShowDesignation('bac_member_2'))
                                    <span class="abstract-cell abstract-committee-designation" data-committee-designation="bac_member_2">{{ $committeeDesignation('bac_member_2') }}</span>
                                    @endif
                                </div>
                                <div class="abstract-committee-person">
                                    <span class="abstract-cell abstract-committee-name abstract-bottom-line" data-committee-role="bac_member_alternate">{{ $committeeName('bac_member_alternate') }}</span>
                                    @if ($committeeShouldShowDesignation('bac_member_alternate'))
                                    <span class="abstract-cell abstract-committee-designation" data-committee-designation="bac_member_alternate">{{ $committeeDesignation('bac_member_alternate') }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        @endif
        </div>
    </section>
</div>

<div id="abstractPrintArea" class="abstract-print-area">
    <section id="abstractPrintPaper" class="abstract-a4-page">
        <div class="abstract-document" id="abstractPrintContainer"></div>
    </section>
</div>

@if ($isEditable)
    <script>
        (function () {
            window.PaperTrailRowTools = window.PaperTrailRowTools || (() => {
                function normalizeCellText(value) {
                    return (value || '')
                        .replace(/\u00a0/g, ' ')
                        .replace(/\s+/g, ' ')
                        .trim();
                }

                function getCellValue(cell) {
                    if (!cell) return '';

                    if (cell.matches('input, textarea, select')) {
                        return normalizeCellText(cell.value);
                    }

                    return normalizeCellText(cell.innerText || cell.textContent || '');
                }

                function isRowEmpty(row, fieldSelector = 'input:not([type="hidden"]):not([readonly]), textarea:not([readonly]), select:not([disabled]), [contenteditable="true"], .pr-excel-cell, .pr-cell, .rfq-cell, .abstract-cell') {
                    if (!row) return true;

                    const fields = Array.from(row.querySelectorAll(fieldSelector))
                        .filter((field) => !field.matches('input[type="hidden"], [readonly], [disabled]'));

                    if (!fields.length) {
                        return normalizeCellText(row.innerText || row.textContent || '') === '';
                    }

                    return fields.every((field) => getCellValue(field) === '');
                }

                function removeEmptyRows(rowSelector, minimumRows = 1, fieldSelector) {
                    let removed = 0;

                    Array.from(document.querySelectorAll(rowSelector)).forEach((row) => {
                        if (document.querySelectorAll(rowSelector).length <= minimumRows) {
                            return;
                        }

                        if (isRowEmpty(row, fieldSelector)) {
                            row.remove();
                            removed++;
                        }
                    });

                    return removed;
                }

                function removeSelectedEmptyRow(row, rowSelector, minimumRows = 1, fieldSelector) {
                    if (!row) return false;

                    if (document.querySelectorAll(rowSelector).length <= minimumRows) {
                        window.PaperTrailDialog?.notice('At least one row must remain.', {
                            title: 'Row Required',
                        });
                        return false;
                    }

                    if (!isRowEmpty(row, fieldSelector)) {
                        window.PaperTrailDialog?.notice('This row has content and cannot be removed. Please clear the row first before removing it.', {
                            title: 'Row Has Content',
                        });
                        return false;
                    }

                    row.remove();
                    return true;
                }

                return { normalizeCellText, getCellValue, isRowEmpty, removeEmptyRows, removeSelectedEmptyRow };
            })();

            const editor = document.getElementById('abstractEditor');
            const form = document.querySelector('.abstract-editor-form');
            if (!editor) return;
            const signatoryConfig = @json($committeeSelectData);
            let signatorySelects = [];

            const columns = [
                'item_no', 'name_of_goods_services', 'quantity', 'unit_of_measure',
                'supplier_1_amount', 'supplier_2_amount', 'supplier_3_amount',
                'supplier_4_amount', 'supplier_5_amount', 'total_lowest_price'
            ];
            const supplierColumns = ['supplier_1_amount', 'supplier_2_amount', 'supplier_3_amount', 'supplier_4_amount', 'supplier_5_amount'];

            function setEditableState() {
                editor.querySelectorAll('.abstract-cell').forEach((cell) => cell.setAttribute('contenteditable', 'true'));
                reindexRows();
                recalcAll();
            }

            function escapeHtml(value) {
                const wrapper = document.createElement('div');
                wrapper.textContent = value || '';
                return wrapper.innerHTML;
            }

            function matchSelectedAccount(config, currentName) {
                return '';
            }

            function isCommitteeCaptionText(value) {
                const normalized = String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();

                if (normalized === '') {
                    return false;
                }

                return normalized
                    .replace(/bac vice chairperson|bac chairperson|bac member\s*-\s*alternate|bac member alternate|bac member\s+(?:i|ii|iii|iv|v)|bac member/g, '')
                    .replace(/\s+/g, '') === '';
            }

            function removeCommitteeDesignationCaptions(root = editor) {
                root.querySelectorAll('[data-committee-designation], .abstract-committee-designation').forEach((el) => el.remove());
                root.querySelectorAll('.abstract-committee-person, .abstract-committee-grid').forEach((container) => {
                    Array.from(container.childNodes).forEach((node) => {
                        if (node.nodeType === Node.TEXT_NODE) {
                            if (isCommitteeCaptionText(node.textContent)) {
                                node.remove();
                            }

                            return;
                        }

                        if (node.nodeType !== Node.ELEMENT_NODE) {
                            return;
                        }

                        if (
                            node.matches('[data-abstract-signatory-control], [data-committee-role]')
                            || node.closest('[data-abstract-signatory-control]')
                            || node.querySelector('[data-abstract-signatory-control], [data-committee-role]')
                        ) {
                            return;
                        }

                        if (isCommitteeCaptionText(node.textContent)) {
                            node.remove();
                        }
                    });
                });
            }

            function committeeDesignationForRole(role, config) {
                const select = editor.querySelector(`[data-abstract-signatory-select][data-committee-role="${role}"]`);
                const selectedOption = select?.selectedOptions?.[0];

                if (select?.value && selectedOption?.dataset.designation) {
                    return selectedOption.dataset.designation;
                }

                return '';
            }

            function appendCommitteeDesignationCaptions(root) {
                Object.entries(signatoryConfig || {}).forEach(([role, config]) => {
                    const nameCell = root.querySelector(`[data-committee-role="${role}"]`);
                    const person = nameCell?.closest('.abstract-committee-person');
                    const designation = committeeDesignationForRole(role, config);

                    if (!nameCell || !person || designation === '') {
                        return;
                    }

                    const designationCell = document.createElement('span');
                    designationCell.className = 'abstract-cell abstract-committee-designation';
                    designationCell.dataset.committeeDesignation = role;
                    designationCell.textContent = designation;
                    nameCell.insertAdjacentElement('afterend', designationCell);
                });
            }

            function ensureInlineSignatorySelects() {
                Object.entries(signatoryConfig || {}).forEach(([role, config]) => {
                    const nameCell = editor.querySelector(`[data-committee-role="${role}"]`);
                    const person = nameCell?.closest('.abstract-committee-person');

                    if (!nameCell || !person || person.querySelector('[data-abstract-signatory-select]')) {
                        return;
                    }

                    const selectedValue = matchSelectedAccount(config, nameCell.innerText);
                    const designationCell = editor.querySelector(`[data-committee-designation="${role}"]`);
                    const optionHtml = [
                        `<option value="" data-designation="">-- Select role --</option>`,
                        ...(config.options || []).map((option) => {
                            const selected = selectedValue === option.value ? ' selected' : '';
                            const optionLabel = option.label || `${option.designation || config.label} - ${option.value}`;

                            return `<option value="${escapeHtml(option.value)}" data-designation="${escapeHtml(option.designation)}"${selected}>${escapeHtml(optionLabel)}</option>`;
                        }),
                    ].join('');

                    if (designationCell && config.label) {
                        designationCell.remove();
                    }

                    person.classList.add('is-editing-signatory');
                    nameCell.insertAdjacentHTML(
                        'beforebegin',
                        `<div class="abstract-committee-field-stack no-print" data-abstract-signatory-control="${escapeHtml(role)}">
                            <textarea class="abstract-committee-name-input" data-abstract-signatory-name-input data-committee-role="${escapeHtml(role)}" rows="2" aria-label="${escapeHtml(config.label)} printed name">${escapeHtml(nameCell.innerText.trim() || config.fallbackName)}</textarea>
                            <select class="abstract-committee-account-select" data-abstract-signatory-select data-committee-role="${escapeHtml(role)}" aria-label="${escapeHtml(config.label)} account">${optionHtml}</select>
                        </div>`
                    );

                    const insertedSelect = person.querySelector(`[data-abstract-signatory-select][data-committee-role="${role}"]`);

                    if (insertedSelect) {
                        insertedSelect.value = '';
                    }
                });

                removeCommitteeDesignationCaptions();
                signatorySelects = Array.from(editor.querySelectorAll('[data-abstract-signatory-select]'));
                editor.querySelectorAll('[data-abstract-signatory-name-input]').forEach((input) => {
                    input.addEventListener('input', () => {
                        const role = input.dataset.committeeRole;
                        const nameCell = editor.querySelector(`[data-committee-role="${role}"]`);

                        if (nameCell) {
                            nameCell.innerText = input.value.trim();
                        }
                    });
                });
            }

            function cells() {
                return Array.from(editor.querySelectorAll('.abstract-cell[contenteditable="true"]'));
            }

            function focusCell(cell) {
                if (!cell) return;
                cell.focus();
                const range = document.createRange();
                range.selectNodeContents(cell);
                range.collapse(false);
                const selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
            }

            function moveToNextCell(cell, offset) {
                const all = cells();
                focusCell(all[all.indexOf(cell) + offset] || cell);
            }

            function moveBelow(cell) {
                const row = cell.closest('.abstract-item-row');
                const column = cell.dataset.column;
                if (!row || !column) return moveToNextCell(cell, 1);
                focusCell(row.nextElementSibling?.querySelector(`[data-column="${column}"]`) || cell);
            }

            function number(value) {
                const parsed = Number(String(value || '').replace(/[^0-9.-]/g, ''));
                return Number.isFinite(parsed) ? parsed : 0;
            }

            function format(value) {
                return value > 0 ? value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
            }

            function reindexRows() {
                editor.querySelectorAll('.abstract-item-row').forEach((row, rowIndex) => {
                    row.dataset.row = rowIndex;
                    row.querySelectorAll('.abstract-item-cell').forEach((cell) => cell.dataset.row = rowIndex);
                });
            }

            function rowHtml(index) {
                return `<tr class="abstract-item-row" data-row="${index}">
                    <td class="abstract-cell abstract-item-cell abstract-center" contenteditable="true" data-column="item_no" data-row="${index}"></td>
                    <td class="abstract-cell abstract-item-cell abstract-description-cell" contenteditable="true" data-column="name_of_goods_services" data-row="${index}"></td>
                    <td class="abstract-cell abstract-item-cell abstract-right" contenteditable="true" data-column="quantity" data-row="${index}"></td>
                    <td class="abstract-cell abstract-item-cell abstract-center" contenteditable="true" data-column="unit_of_measure" data-row="${index}"></td>
                    <td class="abstract-cell abstract-item-cell abstract-right supplier-amount" contenteditable="true" data-column="supplier_1_amount" data-row="${index}"></td>
                    <td class="abstract-cell abstract-item-cell abstract-right supplier-amount" contenteditable="true" data-column="supplier_2_amount" data-row="${index}"></td>
                    <td class="abstract-cell abstract-item-cell abstract-right supplier-amount" contenteditable="true" data-column="supplier_3_amount" data-row="${index}"></td>
                    <td class="abstract-cell abstract-item-cell abstract-right supplier-amount" contenteditable="true" data-column="supplier_4_amount" data-row="${index}"></td>
                    <td class="abstract-cell abstract-item-cell abstract-right supplier-amount" contenteditable="true" data-column="supplier_5_amount" data-row="${index}"></td>
                    <td class="abstract-cell abstract-item-cell abstract-right lowest-price-cell" contenteditable="true" data-column="total_lowest_price" data-row="${index}"></td>
                </tr>`;
            }

            window.addAbstractRow = function () {
                const rows = editor.querySelectorAll('.abstract-item-row');
                rows[rows.length - 1]?.insertAdjacentHTML('afterend', rowHtml(rows.length));
                reindexRows();
            };

            window.removeEmptyAbstractRows = function () {
                let removed = 0;

                Array.from(editor.querySelectorAll('.abstract-item-row')).forEach((row) => {
                    if (editor.querySelectorAll('.abstract-item-row').length <= 1) {
                        return;
                    }

                    if (window.PaperTrailRowTools.isRowEmpty(row, '.abstract-item-cell')) {
                        row.remove();
                        removed++;
                    }
                });

                if (removed === 0) {
                    window.PaperTrailDialog?.notice('No empty Abstract rows to remove.', {
                        title: 'No Empty Rows',
                    });
                }

                reindexRows();
                recalcAll();
            };

            function recalcRow(row) {
                const amounts = supplierColumns
                    .map((column) => number(row.querySelector(`[data-column="${column}"]`)?.innerText))
                    .filter((value) => value > 0);
                const lowest = row.querySelector('[data-column="total_lowest_price"]');

                if (lowest && !lowest.dataset.manual) {
                    lowest.innerText = amounts.length ? format(Math.min(...amounts)) : '';
                }
            }

            function recalcAll() {
                const supplierTotals = [0, 0, 0, 0, 0];
                let lowestTotal = 0;

                editor.querySelectorAll('.abstract-item-row').forEach((row) => {
                    recalcRow(row);
                    supplierColumns.forEach((column, index) => {
                        supplierTotals[index] += number(row.querySelector(`[data-column="${column}"]`)?.innerText);
                    });
                    lowestTotal += number(row.querySelector('[data-column="total_lowest_price"]')?.innerText);
                });

                supplierTotals.forEach((total, index) => {
                    const cell = editor.querySelector(`[data-field="supplier_${index + 1}_total"]`);
                    if (cell && !cell.dataset.manual) cell.innerText = format(total);
                });

                const lowestTotalCell = editor.querySelector('[data-field="lowest_total_amount"]');
                if (lowestTotalCell && !lowestTotalCell.dataset.manual) lowestTotalCell.innerText = format(lowestTotal);

                const lowestSupplierField = editor.querySelector('[data-field="lowest_supplier_name"]');
                if (lowestSupplierField && !lowestSupplierField.dataset.manual) {
                    const positiveTotals = supplierTotals.map((value, index) => ({ value, index })).filter((entry) => entry.value > 0);
                    if (positiveTotals.length) {
                        const lowest = positiveTotals.sort((a, b) => a.value - b.value)[0];
                        lowestSupplierField.innerText = editor.querySelector(`[data-field="supplier_${lowest.index + 1}_name"]`)?.innerText.trim() || '';
                    }
                }
            }

            function collectItems() {
                return Array.from(editor.querySelectorAll('.abstract-item-row')).filter((row) => {
                    return !window.PaperTrailRowTools.isRowEmpty(row, '.abstract-item-cell');
                }).map((row) => {
                    const item = {};
                    columns.forEach((column) => item[column] = window.PaperTrailRowTools.getCellValue(row.querySelector(`[data-column="${column}"]`)));
                    return item;
                });
            }

            function cleanSupplierHeaderText(value) {
                return String(value || '')
                    .split('\n')
                    .map((line) => line.trim())
                    .filter((line) => line !== '' && !/^[1-5]$/.test(line) && line.toUpperCase() !== 'AMOUNT / UNIT')
                    .join(' ')
                    .trim();
            }

            function fieldValue(fieldName) {
                const fieldCell = editor.querySelector(`[data-field="${fieldName}"]`);
                const value = fieldCell?.innerText.trim() || '';
                return /^supplier_[1-5]_name$/.test(fieldName) ? cleanSupplierHeaderText(value) : value;
            }

            function collectSuppliers() {
                const suppliers = {};
                for (let index = 1; index <= 5; index++) {
                    suppliers[`supplier_${index}_name`] = fieldValue(`supplier_${index}_name`);
                    suppliers[`supplier_${index}_total`] = editor.querySelector(`[data-field="supplier_${index}_total"]`)?.innerText.trim() || '';
                }
                return suppliers;
            }

            function collectAwards() {
                return Array.from(editor.querySelectorAll('[data-award-row]')).map((cell, index) => ({
                    row: index + 1,
                    text: cell.innerText.trim()
                })).filter((award) => award.text !== '');
            }

            function collectCommittee() {
                const committee = {};
                editor.querySelectorAll('[data-committee-role]').forEach((cell) => {
                    const role = cell.dataset.committeeRole;
                    const select = document.querySelector(`[data-abstract-signatory-select][data-committee-role="${role}"]`);
                    const designationCell = editor.querySelector(`[data-committee-designation="${role}"]`);
                    const selectedOption = select?.selectedOptions?.[0];
                    const selectedDesignation = select?.value ? (selectedOption?.dataset.designation || '') : '';
                    const config = signatoryConfig?.[role] || {};

                    committee[role] = {
                        name: cell.innerText.trim(),
                        account_code: select?.value || '',
                        designation: designationCell?.innerText.trim() || selectedDesignation,
                    };
                });
                return committee;
            }

            function applySignatorySelection(select) {
                const role = select.dataset.committeeRole;
                const selectedOption = select.selectedOptions?.[0];
                const designationCell = editor.querySelector(`[data-committee-designation="${role}"]`);
                const selectedDesignation = select.value ? (selectedOption?.dataset.designation || '') : '';

                if (designationCell && selectedDesignation !== '') {
                    designationCell.innerText = selectedDesignation;
                }
            }

            window.prepareAbstractPrint = function () {
                const printContainer = document.getElementById('abstractPrintContainer');
                if (!printContainer) return;
                const clone = cleanDocumentClone();
                printContainer.innerHTML = '';
                printContainer.appendChild(clone);
            };

            window.printAbstractDocument = function () {
                window.prepareAbstractPrint();
                setTimeout(() => window.print(), 100);
            };

            function collectAbstractData() {
                recalcAll();
                const clone = cleanDocumentClone();
                document.getElementById('abstract_document_html').value = clone.outerHTML.trim();
                document.getElementById('abstract_document_text').value = clone.innerText.trim();
                document.getElementById('abstract_items_json').value = JSON.stringify(collectItems());
                document.getElementById('abstract_suppliers_json').value = JSON.stringify(collectSuppliers());
                document.getElementById('abstract_awards_json').value = JSON.stringify(collectAwards());
                document.getElementById('abstract_committee_json').value = JSON.stringify(collectCommittee());

                form.querySelectorAll('[data-hidden-field]').forEach((input) => {
                    input.value = fieldValue(input.dataset.hiddenField);
                });
            }

            function cleanDocumentClone() {
                const clone = editor.cloneNode(true);
                clone.querySelectorAll('[contenteditable]').forEach((el) => el.removeAttribute('contenteditable'));
                clone.querySelectorAll('[data-abstract-signatory-control]').forEach((el) => el.remove());
                clone.querySelectorAll('[data-committee-designation]').forEach((el) => el.remove());
                removeCommitteeDesignationCaptions(clone);
                appendCommitteeDesignationCaptions(clone);
                clone.querySelectorAll('.is-editing-signatory').forEach((el) => el.classList.remove('is-editing-signatory'));

                return clone;
            }

            document.addEventListener('keydown', function (event) {
                const cell = event.target.closest('.abstract-cell');
                if (!cell || !editor.contains(cell)) return;
                if (event.key === 'Tab') {
                    event.preventDefault();
                    moveToNextCell(cell, event.shiftKey ? -1 : 1);
                }
                if (event.key === 'Enter') {
                    event.preventDefault();
                    moveBelow(cell);
                }
            });

            document.addEventListener('paste', function (event) {
                const cell = event.target.closest('.abstract-item-cell');
                if (!cell || !editor.contains(cell)) return;
                const text = event.clipboardData?.getData('text/plain') || '';
                if (!text.includes('\t') && !text.includes('\n')) return;
                event.preventDefault();
                const startRow = Number(cell.dataset.row || 0);
                const startColumn = columns.indexOf(cell.dataset.column);
                text.replace(/\r/g, '').split('\n').filter((line) => line.length).forEach((line, rowOffset) => {
                    while (editor.querySelectorAll('.abstract-item-row').length <= startRow + rowOffset) window.addAbstractRow();
                    const row = editor.querySelectorAll('.abstract-item-row')[startRow + rowOffset];
                    line.split('\t').forEach((value, colOffset) => {
                        const target = row.querySelector(`[data-column="${columns[startColumn + colOffset]}"]`);
                        if (target) target.innerText = value.trim();
                    });
                    recalcRow(row);
                });
                recalcAll();
            });

            document.addEventListener('input', function (event) {
                const cell = event.target.closest('.abstract-cell');
                if (!cell || !editor.contains(cell)) return;
                if (cell.classList.contains('lowest-price-cell') || cell.closest('.abstract-total-row')) cell.dataset.manual = '1';
                if (cell.classList.contains('supplier-amount')) {
                    recalcRow(cell.closest('.abstract-item-row'));
                    recalcAll();
                }
            });

            form?.addEventListener('submit', collectAbstractData);
            document.getElementById('removeEmptyAbstractRowsBtn')?.addEventListener('click', window.removeEmptyAbstractRows);
            ensureInlineSignatorySelects();
            signatorySelects.forEach((select) => select.addEventListener('change', () => applySignatorySelection(select)));
            window.addEventListener('beforeprint', window.prepareAbstractPrint);
            setEditableState();
            removeCommitteeDesignationCaptions();
        })();
    </script>
@else
    <script>
        window.prepareAbstractPrint = function () {
            const editor = document.getElementById('abstractEditor');
            const printContainer = document.getElementById('abstractPrintContainer');
            if (!editor || !printContainer) return;
            const clone = editor.cloneNode(true);
            clone.querySelectorAll('[contenteditable]').forEach((el) => el.removeAttribute('contenteditable'));
            printContainer.innerHTML = '';
            printContainer.appendChild(clone);
        };
        window.printAbstractDocument = function () {
            window.prepareAbstractPrint();
            setTimeout(() => window.print(), 100);
        };
        window.addEventListener('beforeprint', window.prepareAbstractPrint);
    </script>
@endif
