@php
    $categories = $categories ?? [
        'general_requirements' => 'General Requirements',
        'miscellaneous_items' => 'Miscellaneous Items (for Direct Acquisition only) Sec. 32.2 of RA No. 12009',
        'cse' => 'Common Use Supplies and Equipment (CSE) to be purchased from PS-DBM',
    ];

    $money = fn ($value) => number_format((float) ($value ?? 0), 2);
    $text = fn ($value, string $fallback = '') => filled($value) ? $value : $fallback;
    $planType = $app->plan_type ?: 'indicative';
    $logoPath = public_path('images/logos/lgu-logo.png');
    $bagongPath = public_path('images/logos/bagongpilipinas.jpg');
    $itemsByCategory = $app->items->groupBy(fn ($item) => $item->category ?: 'general_requirements');
    $boxClass = fn (string $type) => $planType === $type ? ' is-checked' : '';
@endphp

<article class="official-app-document" aria-label="Annual Procurement Plan official document">
    <header class="official-app-header">
        <div class="official-app-logo-slot">
            @if (file_exists($logoPath))
                <img src="{{ asset('images/logos/lgu-logo.png') }}" alt="LGU Logo">
            @endif
        </div>
        <div class="official-app-heading">
            <p>Republic of the Philippines</p>
            <p>{{ $text($app->province, 'Province of Southern Leyte') }}</p>
            <p class="official-app-municipality">{{ $text($app->municipality, 'MUNICIPALITY OF TOMAS OPPUS') }}</p>
            <h1>ANNUAL PROCUREMENT PLAN</h1>
            <p class="official-app-fiscal-year">for CY {{ $app->fiscal_year }}</p>
        </div>
        <div class="official-app-logo-slot">
            @if (file_exists($bagongPath))
                <img src="{{ asset('images/logos/bagongpilipinas.jpg') }}" alt="Bagong Pilipinas Logo">
            @endif
        </div>
    </header>

    <div class="official-app-plan-row">
        <span><i class="official-app-checkbox{{ $boxClass('indicative') }}"></i> INDICATIVE</span>
        <span><i class="official-app-checkbox{{ $boxClass('final') }}"></i> FINAL</span>
        <span><i class="official-app-checkbox{{ $boxClass('update') }}"></i> UPDATE <em>(Version No. {{ $app->update_version_no ?: '_____' }})</em></span>
    </div>

    <table class="official-app-table">
        <colgroup>
            <col style="width: 10%">
            <col style="width: 8%">
            <col style="width: 12%">
            <col style="width: 8%">
            <col style="width: 7%">
            <col style="width: 10%">
            <col style="width: 7%">
            <col style="width: 7%">
            <col style="width: 7%">
            <col style="width: 8%">
            <col style="width: 8%">
            <col style="width: 8%">
        </colgroup>
        <thead>
            <tr class="official-app-group-row">
                <th colspan="4">PROCUREMENT PROJECT DETAILS</th>
                <th colspan="4">PROJECTED TIMELINE (MM/YYYY)</th>
                <th colspan="2">FUNDING DETAILS</th>
                <th rowspan="2">PROCUREMENT<br>STRATEGY OR<br>TOOLS</th>
                <th rowspan="2">REMARKS<br>(Other relevant<br>description<br>procurement<br>project, if any)</th>
            </tr>
            <tr>
                <th>Project Title</th>
                <th>End-User or<br>Implementing<br>Unit</th>
                <th>General Description<br>of the Project</th>
                <th>Mode of<br>Procurement</th>
                <th>To be covered by<br>an Early<br>Procurement<br>Activity (yes/No)</th>
                <th>Criteria for Bid<br>Evaluation (<br>Including<br>Sustainability and<br>Domestic Bidder<br>Preference)</th>
                <th>Start of<br>Procurement<br>Activity</th>
                <th>End of<br>Procurement<br>Activity</th>
                <th>Source of<br>Funds</th>
                <th>Estimated<br>Budget/<br>Authorized<br>Budget Allocation</th>
            </tr>
            <tr class="official-app-number-row">
                @for ($column = 1; $column <= 12; $column++)
                    <th>Column {{ $column }}</th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @foreach ($categories as $categoryKey => $categoryLabel)
                <tr class="official-app-section-row">
                    <td colspan="12">{{ $categoryLabel }}</td>
                </tr>
                @forelse (($itemsByCategory[$categoryKey] ?? collect()) as $item)
                    <tr>
                        <td>{{ $text($item->project_title, $item->procurement_program_project) }}</td>
                        <td>{{ $text($item->end_user_unit, $item->pmo_end_user) }}</td>
                        <td>{{ $item->general_description }}</td>
                        <td>{{ $item->mode_of_procurement }}</td>
                        <td class="official-app-center">{{ $item->early_procurement_activity ?: 'No' }}</td>
                        <td>{{ $item->bid_evaluation_criteria }}</td>
                        <td class="official-app-center">{{ $text($item->start_procurement_activity, $item->ads_post_ib_rei) }}</td>
                        <td class="official-app-center">{{ $text($item->end_procurement_activity, $item->contract_signing) }}</td>
                        <td>{{ $item->source_of_funds }}</td>
                        <td class="official-app-money">{{ $money($item->estimated_budget ?: $item->estimated_total) }}</td>
                        <td>{{ $item->procurement_strategy_or_tools }}</td>
                        <td>{{ $item->remarks }}</td>
                    </tr>
                @empty
                    <tr class="official-app-empty-row">
                        <td colspan="12">&nbsp;</td>
                    </tr>
                @endforelse
            @endforeach
        </tbody>
        <tfoot>
            <tr class="official-app-total-row">
                <td colspan="9">Total Amount of Estimated Budget for EPA</td>
                <td class="official-app-money">{{ $money($app->total_epa_budget) }}</td>
                <td colspan="2"></td>
            </tr>
            <tr class="official-app-total-row">
                <td colspan="9">Total Amount of CSE to be procured from PS-DBM</td>
                <td class="official-app-money">{{ $money($app->total_cse_budget) }}</td>
                <td colspan="2"></td>
            </tr>
            <tr class="official-app-total-row">
                <td colspan="9">Total Amount of Estimated Budget</td>
                <td class="official-app-money">{{ $money($app->total_estimated_budget) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    <p class="official-app-note">Note: Insert additional rows as necessary</p>

    <section class="official-app-signatures" aria-label="APP signatures">
        <div class="official-app-signature">
            <strong>Prepared By:</strong>
            <span class="official-app-sign-line">{{ $app->prepared_by_name }}</span>
            <em>Signature over Printed Name</em>
            <span>{{ $app->prepared_by_position }}</span>
            <em>Position/Designation</em>
            <span>{{ $app->prepared_by_office }}</span>
            <span>Date: {{ $app->prepared_date?->format('m/d/Y') ?? '____________' }}</span>
        </div>
        <div class="official-app-signature">
            <strong>Recommended by:</strong>
            <span>By the Authority of the Bids and Awards Committee</span>
            <span class="official-app-sign-line">{{ $app->recommended_by_name }}</span>
            <em>Signature over Printed Name</em>
            <span>{{ $app->recommended_by_position }}</span>
            <em>Position/Designation</em>
            <span>{{ $app->recommended_by_office }}</span>
            <span>Date: {{ $app->recommended_date?->format('m/d/Y') ?? '____________' }}</span>
        </div>
        <div class="official-app-signature">
            <strong>Approved By:</strong>
            <span class="official-app-sign-line">{{ $app->approved_by_name }}</span>
            <em>Signature over Printed Name</em>
            <span>{{ $app->approved_by_position }}</span>
            <em>Position/Designation</em>
            <span>{{ $app->approved_by_office }}</span>
            <span>Date: {{ $app->approved_date?->format('m/d/Y') ?? '____________' }}</span>
        </div>
    </section>
</article>
