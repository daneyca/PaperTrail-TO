@php
    $formItems = collect(old('items', $items ?? []));

    if ($formItems->isEmpty()) {
        $formItems = collect([[
            'category' => 'general_requirements',
            'project_title' => '',
            'end_user_unit' => '',
            'general_description' => '',
            'mode_of_procurement' => 'Small Value Procurement',
            'early_procurement_activity' => 'No',
            'bid_evaluation_criteria' => '',
            'start_procurement_activity' => '',
            'end_procurement_activity' => '',
            'source_of_funds' => 'General Fund',
            'estimated_budget' => '',
            'procurement_strategy_or_tools' => '',
            'remarks' => '',
        ]]);
    }

    $planType = old('plan_type', $app->plan_type ?? 'indicative');
    $status = old('status', $app->status ?? 'draft');
    $logoPath = public_path('images/logos/lgu-logo.png');
    $bagongPath = public_path('images/logos/bagongpilipinas.jpg');
@endphp

@csrf
@if ($app->exists)
    @method('PATCH')
@endif

<section class="official-app-entry-toolbar no-print">
    <div>
        <p class="eyebrow">Annual Procurement Plan</p>
        <strong>Encode directly into the official APP worksheet layout.</strong>
    </div>

    <div class="official-app-entry-controls">
        <label>
            APP No.
            <input name="app_no" type="text" value="{{ old('app_no', $app->app_no ?? $app->app_number) }}" placeholder="Optional">
        </label>

        <label>
            Status
            <select name="status" required>
                @foreach ($options['statuses'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </div>
</section>

<section class="official-app-entry-scroll">
    <article class="official-app-page official-app-entry-page" data-app-sheet-form>
        <div class="official-app-document">
            <header class="official-app-header official-app-entry-header">
                <div class="official-app-logo-slot">
                    @if (file_exists($logoPath))
                        <img src="{{ asset('images/logos/lgu-logo.png') }}" alt="LGU Logo">
                    @endif
                </div>

                <div class="official-app-heading">
                    <p>Republic of the Philippines</p>
                    <p>
                        <input name="province" type="text" value="{{ old('province', $app->province ?? 'Province of Southern Leyte') }}" aria-label="Province">
                    </p>
                    <p class="official-app-municipality">
                        <input name="municipality" type="text" value="{{ old('municipality', $app->municipality ?? 'MUNICIPALITY OF TOMAS OPPUS') }}" aria-label="Municipality">
                    </p>
                    <h1>ANNUAL PROCUREMENT PLAN</h1>
                    <p class="official-app-fiscal-year">for CY <input name="fiscal_year" type="number" min="2020" max="2100" value="{{ old('fiscal_year', $app->fiscal_year ?? now()->year) }}" aria-label="Fiscal Year"></p>
                </div>

                <div class="official-app-logo-slot">
                    @if (file_exists($bagongPath))
                        <img src="{{ asset('images/logos/bagongpilipinas.jpg') }}" alt="Bagong Pilipinas Logo">
                    @endif
                </div>
            </header>

            <div class="official-app-entry-plan-row">
                @foreach ($options['planTypes'] as $value => $label)
                    <label class="official-app-check-option">
                        <input type="radio" name="plan_type" value="{{ $value }}" @checked($planType === $value)>
                        <i class="official-app-checkbox" aria-hidden="true"></i>
                        {{ strtoupper($label) }}
                        @if ($value === 'update')
                            <em>(Version No. <input name="update_version_no" type="text" value="{{ old('update_version_no', $app->update_version_no) }}" aria-label="Update Version Number">)</em>
                        @endif
                    </label>
                @endforeach
            </div>

            @error('items')<p class="field-error official-app-sheet-error">{{ $message }}</p>@enderror

            <table class="official-app-table official-app-entry-table" data-app-items-table>
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
                    @foreach ($options['categories'] as $categoryKey => $categoryLabel)
                        <tr class="official-app-section-row" data-category-section="{{ $categoryKey }}">
                            <td colspan="12">{{ $categoryLabel }}</td>
                        </tr>

                        @foreach ($formItems->filter(fn ($item) => data_get($item, 'category', 'general_requirements') === $categoryKey) as $index => $item)
                            @php
                                $value = fn (string $key, string $fallback = '') => old("items.$index.$key", data_get($item, $key, $fallback));
                            @endphp
                            <tr class="official-app-entry-item-row" data-app-item-row data-category="{{ $categoryKey }}">
                                <td>
                                    <input type="hidden" name="items[{{ $index }}][category]" value="{{ $categoryKey }}" data-category-field>
                                    <textarea name="items[{{ $index }}][project_title]" rows="2">{{ $value('project_title') }}</textarea>
                                    @error("items.$index.project_title")<p class="field-error">{{ $message }}</p>@enderror
                                </td>
                                <td>
                                    <input name="items[{{ $index }}][end_user_unit]" type="text" value="{{ $value('end_user_unit') }}">
                                    @error("items.$index.end_user_unit")<p class="field-error">{{ $message }}</p>@enderror
                                </td>
                                <td>
                                    <textarea name="items[{{ $index }}][general_description]" rows="2">{{ $value('general_description') }}</textarea>
                                </td>
                                <td>
                                    <select name="items[{{ $index }}][mode_of_procurement]">
                                        @foreach ($options['modes'] as $mode)
                                            <option value="{{ $mode }}" @selected($value('mode_of_procurement', 'Small Value Procurement') === $mode)>{{ $mode }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select name="items[{{ $index }}][early_procurement_activity]" data-early-field>
                                        @foreach ($options['earlyProcurementOptions'] as $option)
                                            <option value="{{ $option }}" @selected($value('early_procurement_activity', 'No') === $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><textarea name="items[{{ $index }}][bid_evaluation_criteria]" rows="2">{{ $value('bid_evaluation_criteria') }}</textarea></td>
                                <td>
                                    <input name="items[{{ $index }}][start_procurement_activity]" type="text" value="{{ $value('start_procurement_activity') }}">
                                    @error("items.$index.start_procurement_activity")<p class="field-error">{{ $message }}</p>@enderror
                                </td>
                                <td>
                                    <input name="items[{{ $index }}][end_procurement_activity]" type="text" value="{{ $value('end_procurement_activity') }}">
                                    @error("items.$index.end_procurement_activity")<p class="field-error">{{ $message }}</p>@enderror
                                </td>
                                <td>
                                    <select name="items[{{ $index }}][source_of_funds]">
                                        @foreach ($options['fundSources'] as $source)
                                            <option value="{{ $source }}" @selected($value('source_of_funds', 'General Fund') === $source)>{{ $source }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input name="items[{{ $index }}][estimated_budget]" type="number" step="0.01" min="0" value="{{ $value('estimated_budget') }}" data-budget-field></td>
                                <td><textarea name="items[{{ $index }}][procurement_strategy_or_tools]" rows="2">{{ $value('procurement_strategy_or_tools') }}</textarea></td>
                                <td>
                                    <textarea name="items[{{ $index }}][remarks]" rows="2">{{ $value('remarks') }}</textarea>
                                    <button type="button" class="official-app-cell-remove no-print" data-remove-app-row>Remove</button>
                                </td>
                            </tr>
                        @endforeach

                        <tr class="official-app-empty-row no-print" data-add-after-category="{{ $categoryKey }}">
                            <td colspan="12">
                                <button type="button" data-add-app-row="{{ $categoryKey }}">Add {{ $categoryLabel }} Row</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="official-app-total-row">
                        <td colspan="9">Total Amount of Estimated Budget for EPA</td>
                        <td class="official-app-money"><span data-app-epa-total>0.00</span></td>
                        <td colspan="2"></td>
                    </tr>
                    <tr class="official-app-total-row">
                        <td colspan="9">Total Amount of CSE to be procured from PS-DBM</td>
                        <td class="official-app-money"><span data-app-cse-total>0.00</span></td>
                        <td colspan="2"></td>
                    </tr>
                    <tr class="official-app-total-row">
                        <td colspan="9">Total Amount of Estimated Budget</td>
                        <td class="official-app-money"><span data-app-grand-total>0.00</span></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>

            <p class="official-app-note">Note: Insert additional rows as necessary</p>

            <section class="official-app-signatures official-app-entry-signatures" aria-label="APP signatures">
                @foreach ([
                    'prepared' => 'Prepared By:',
                    'recommended' => 'Recommended by:',
                    'approved' => 'Approved By:',
                ] as $prefix => $label)
                    <div class="official-app-signature">
                        <strong>{{ $label }}</strong>
                        @if ($prefix === 'recommended')
                            <span>By the Authority of the Bids and Awards Com.</span>
                        @endif
                        <input name="{{ $prefix }}_by_name" type="text" value="{{ old($prefix . '_by_name', $app->{$prefix . '_by_name'}) }}" aria-label="{{ $label }} name">
                        <em>Signature over Printed Name</em>
                        <input name="{{ $prefix }}_by_position" type="text" value="{{ old($prefix . '_by_position', $app->{$prefix . '_by_position'}) }}" aria-label="{{ $label }} position">
                        <em>Position/Designation</em>
                        <input name="{{ $prefix }}_by_office" type="text" value="{{ old($prefix . '_by_office', $app->{$prefix . '_by_office'}) }}" aria-label="{{ $label }} office">
                        <span>Date: <input name="{{ $prefix }}_date" type="date" value="{{ old($prefix . '_date', optional($app->{$prefix . '_date'})->format('Y-m-d')) }}" aria-label="{{ $label }} date"></span>
                    </div>
                @endforeach
            </section>
        </div>
    </article>
</section>

<div class="official-app-form-actions no-print">
    <a href="{{ $app->exists ? route('annual-procurement-plans.show', $app) : route('annual-procurement-plans.index') }}">Cancel</a>
    <button type="submit">{{ $app->exists ? 'Save Changes' : 'Save APP Draft' }}</button>
</div>

<template id="officialAppItemRowTemplate">
    <tr class="official-app-entry-item-row" data-app-item-row data-category="__CATEGORY__">
        <td>
            <input type="hidden" name="items[__INDEX__][category]" value="__CATEGORY__" data-category-field>
            <textarea name="items[__INDEX__][project_title]" rows="2"></textarea>
        </td>
        <td><input name="items[__INDEX__][end_user_unit]" type="text"></td>
        <td><textarea name="items[__INDEX__][general_description]" rows="2"></textarea></td>
        <td>
            <select name="items[__INDEX__][mode_of_procurement]">
                @foreach ($options['modes'] as $mode)
                    <option value="{{ $mode }}" @selected($mode === 'Small Value Procurement')>{{ $mode }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <select name="items[__INDEX__][early_procurement_activity]" data-early-field>
                @foreach ($options['earlyProcurementOptions'] as $option)
                    <option value="{{ $option }}" @selected($option === 'No')>{{ $option }}</option>
                @endforeach
            </select>
        </td>
        <td><textarea name="items[__INDEX__][bid_evaluation_criteria]" rows="2"></textarea></td>
        <td><input name="items[__INDEX__][start_procurement_activity]" type="text"></td>
        <td><input name="items[__INDEX__][end_procurement_activity]" type="text"></td>
        <td>
            <select name="items[__INDEX__][source_of_funds]">
                @foreach ($options['fundSources'] as $source)
                    <option value="{{ $source }}" @selected($source === 'General Fund')>{{ $source }}</option>
                @endforeach
            </select>
        </td>
        <td><input name="items[__INDEX__][estimated_budget]" type="number" step="0.01" min="0" data-budget-field></td>
        <td><textarea name="items[__INDEX__][procurement_strategy_or_tools]" rows="2"></textarea></td>
        <td>
            <textarea name="items[__INDEX__][remarks]" rows="2"></textarea>
            <button type="button" class="official-app-cell-remove no-print" data-remove-app-row>Remove</button>
        </td>
    </tr>
</template>

@push('scripts')
    <script>
        (function () {
            const sheet = document.querySelector('[data-app-sheet-form]');
            const table = document.querySelector('[data-app-items-table]');
            const template = document.getElementById('officialAppItemRowTemplate');

            if (!sheet || !table || !template) return;

            const money = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            function rows() {
                return Array.from(table.querySelectorAll('[data-app-item-row]'));
            }

            function renumber() {
                rows().forEach((row, index) => {
                    row.querySelectorAll('[name]').forEach((field) => {
                        field.name = field.name.replace(/items\[[^\]]+\]/, `items[${index}]`);
                    });
                });
            }

            function totals() {
                let epa = 0;
                let cse = 0;
                let grand = 0;

                rows().forEach((row) => {
                    const amount = Number((row.querySelector('[data-budget-field]')?.value || '0').replace(/[^0-9.-]/g, '')) || 0;
                    const category = row.querySelector('[data-category-field]')?.value;
                    const early = row.querySelector('[data-early-field]')?.value;

                    grand += amount;
                    if (category === 'cse') cse += amount;
                    if (early === 'Yes') epa += amount;
                });

                document.querySelector('[data-app-epa-total]').textContent = money.format(epa);
                document.querySelector('[data-app-cse-total]').textContent = money.format(cse);
                document.querySelector('[data-app-grand-total]').textContent = money.format(grand);
            }

            function addRow(category) {
                const marker = table.querySelector(`[data-add-after-category="${category}"]`);
                const rowHtml = template.innerHTML
                    .replaceAll('__INDEX__', String(rows().length))
                    .replaceAll('__CATEGORY__', category);

                marker?.insertAdjacentHTML('beforebegin', rowHtml);
                renumber();
                totals();
            }

            sheet.addEventListener('input', totals);
            sheet.addEventListener('change', totals);
            sheet.addEventListener('click', (event) => {
                const addTarget = event.target.closest('[data-add-app-row]');
                if (addTarget) {
                    addRow(addTarget.getAttribute('data-add-app-row'));
                    return;
                }

                if (!event.target.matches('[data-remove-app-row]')) {
                    return;
                }

                const allRows = rows();
                if (allRows.length <= 1) {
                    allRows[0].querySelectorAll('input:not([type="hidden"]), textarea').forEach((field) => field.value = '');
                    totals();
                    return;
                }

                event.target.closest('tr')?.remove();
                renumber();
                totals();
            });

            totals();
        })();
    </script>
@endpush
