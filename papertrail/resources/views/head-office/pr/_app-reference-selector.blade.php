@php
    $appReferenceItems = collect($appReferenceItems ?? []);
    $referenceMode = $referenceMode ?? 'interactive';
    $isSummaryOnly = $referenceMode === 'summary';
    $passedSelectedAppItem = $selectedAppItem ?? null;
    $selectedAppItemId = (string) old('app_item_id', $document->app_item_id);
    $selectedAppItem = $selectedAppItemId !== ''
        ? $appReferenceItems->first(fn ($item) => (string) $item->id === $selectedAppItemId)
        : null;
    $selectedAppItem = $selectedAppItem ?: $passedSelectedAppItem ?: ($document->appItem ?? null);
    $selectedApp = $selectedAppItem?->appConsolidation ?? $document->appConsolidation ?? null;
    $changeAppUrl = $changeAppUrl ?? route('head-office.pr.create');
    $showReferenceDescription = $showReferenceDescription ?? true;
@endphp

<section class="pr-app-reference-card no-print" data-pr-app-reference aria-labelledby="pr-app-reference-title">
    <input
        type="hidden"
        name="app_item_id"
        value="{{ $selectedAppItemId }}"
        data-selected-app-item
    >

    <div class="pr-app-reference-heading">
        <div>
            <p class="pr-page-kicker">APP Reference</p>
            <h2 id="pr-app-reference-title">{{ $isSummaryOnly ? 'Selected APP Project' : 'APP Linkage' }}</h2>
            @if ($showReferenceDescription)
                <p>{{ $isSummaryOnly ? 'This Purchase Request is being prepared from the selected APP project.' : 'Select the consolidated, submitted, or approved APP item that matches this Purchase Request before submitting for signatures.' }}</p>
            @endif
        </div>
        <span class="pr-app-reference-badge {{ $selectedAppItem ? 'is-verified' : 'is-required' }}" data-pr-app-selected-badge>
            {{ $selectedAppItem ? 'Verified' : 'Required' }}
        </span>
    </div>

    <div class="pr-app-selected-reference {{ $selectedAppItem ? '' : 'is-empty' }}" data-pr-app-selected-summary>
        <div>
            <span>APP Reference</span>
            <strong data-selected-app-number>{{ $selectedApp?->app_number ?? 'No APP selected' }}</strong>
        </div>
        <div>
            <span>APP Item</span>
            <strong data-selected-app-description>{{ $selectedAppItem?->general_description ?? 'Select a consolidated, submitted, or approved APP item below' }}</strong>
        </div>
        <div>
            <span>Status</span>
            <strong data-selected-app-status>{{ $selectedAppItem ? 'Verified' : 'Not linked' }}</strong>
        </div>
    </div>

    @error('app_item_id')<p class="field-error">{{ $message }}</p>@enderror

    @if ($isSummaryOnly)
        <div class="pr-app-summary-actions">
            <a href="{{ $changeAppUrl }}" class="btn-pr-secondary">Change APP Project</a>
        </div>
    @endif

    @unless ($isSummaryOnly)
    <div class="pr-app-reference-filter">
        <label for="appReferenceSearch">
            Search APP Records
            <input id="appReferenceSearch" type="search" placeholder="Search APP number, office, activity, or item..." data-app-reference-search>
        </label>
    </div>

    <div class="pr-app-reference-table-wrap">
        <table class="pr-app-reference-table">
            <thead>
                <tr>
                    <th>APP Number</th>
                    <th>Office</th>
                    <th>Procurement Activity</th>
                    <th>Item Description</th>
                    <th>Approved Amount</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($appReferenceItems as $appItem)
                    @php
                        $app = $appItem->appConsolidation;
                        $officeName = $appItem->office?->name ?? 'All offices';
                        $activity = $appItem->sourcePpmpDocument?->title
                            ?: $appItem->sourcePpmpDocument?->description
                            ?: $appItem->general_description;
                        $description = $appItem->general_description;
                        $quantity = (float) ($appItem->quantity ?? 0);
                        $unitCost = (float) ($appItem->estimated_unit_cost ?? 0);
                        $totalCost = (float) ($appItem->estimated_total_cost ?? ($quantity * $unitCost));
                        $isSelected = (string) $appItem->id === $selectedAppItemId;
                        $appStatusLabel = str($app?->status ?? 'verified')->replace(['_', '-'], ' ')->title();
                        $searchText = strtolower(implode(' ', [
                            $app?->app_number,
                            $officeName,
                            $activity,
                            $description,
                            $appItem->procurement_mode,
                            $appItem->category,
                        ]));
                    @endphp
                    <tr
                        class="{{ $isSelected ? 'is-selected' : '' }}"
                        data-app-reference-row
                        data-app-item-id="{{ $appItem->id }}"
                        data-app-id="{{ $app?->id }}"
                        data-office-id="{{ $appItem->office_id }}"
                        data-app-number="{{ $app?->app_number ?? 'APP Reference' }}"
                        data-office-name="{{ $officeName }}"
                        data-activity="{{ $activity }}"
                        data-description="{{ $description }}"
                        data-item-no="{{ $appItem->item_no }}"
                        data-unit="{{ $appItem->unit }}"
                        data-quantity="{{ $quantity > 0 ? rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.') : '' }}"
                        data-unit-cost="{{ $unitCost > 0 ? number_format($unitCost, 2, '.', '') : '' }}"
                        data-status-label="{{ $appStatusLabel }}"
                        data-search="{{ $searchText }}"
                    >
                        <td><strong>{{ $app?->app_number ?? 'APP Reference' }}</strong></td>
                        <td>{{ $officeName }}</td>
                        <td>{{ str($activity)->limit(80) }}</td>
                        <td>{{ str($description)->limit(80) }}</td>
                        <td>PHP {{ number_format($totalCost, 2) }}</td>
                        <td><span class="pr-app-reference-badge is-verified">{{ $appStatusLabel }}</span></td>
                        <td>
                            <button type="button" class="btn-pr-secondary pr-app-add-btn" data-add-app-to-pr>
                                {{ $isSelected ? 'Added' : 'Add to PR' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <strong>No consolidated APP items available</strong>
                                <p>APP items for this fiscal year will appear here after BACSEC-004 submits or approves the APP. Draft APP worksheets are not shown.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pr-app-reference-empty" data-app-reference-empty hidden>
        <strong>No matching APP items</strong>
        <p>Adjust the search or requesting office selection.</p>
    </div>
    @endunless
</section>

@unless ($isSummaryOnly)
<script>
    (() => {
        const boot = () => {
            const card = document.querySelector('[data-pr-app-reference]');

            if (!card) {
                return;
            }

            const selectedInput = card.querySelector('[data-selected-app-item]');
            const selectedSummary = card.querySelector('[data-pr-app-selected-summary]');
            const selectedBadge = card.querySelector('[data-pr-app-selected-badge]');
            const searchInput = card.querySelector('[data-app-reference-search]');
            const emptyState = card.querySelector('[data-app-reference-empty]');
            const rows = [...card.querySelectorAll('[data-app-reference-row]')];
            const officeSelect = document.querySelector('[data-requesting-office-select]');
            const prRows = () => [...document.querySelectorAll('.pr-item-row')];

            const setSummaryValue = (selector, value) => {
                const target = card.querySelector(selector);

                if (target) {
                    target.textContent = value || '';
                }
            };

            const rowIsEmpty = (row) => [...row.querySelectorAll('.pr-excel-cell')]
                .every((field) => !(field.value || '').trim());

            const linkedAppInputs = () => prRows()
                .filter((row) => !rowIsEmpty(row))
                .map((row) => row.querySelector('[data-pr-row-app-item]'))
                .filter((input) => input && input.value);

            const linkedAppIds = () => new Set(linkedAppInputs().map((input) => input.value));

            const syncPrimarySelection = () => {
                const firstLinkedInput = linkedAppInputs()[0] || null;

                if (firstLinkedInput) {
                    selectedInput.value = firstLinkedInput.value;
                    return;
                }

                selectedInput.value = '';
            };

            const updateActionStates = () => {
                const addedIds = linkedAppIds();

                rows.forEach((row) => {
                    const added = addedIds.has(row.dataset.appItemId || '');
                    const button = row.querySelector('[data-add-app-to-pr]');

                    row.classList.toggle('is-selected', added);

                    if (button) {
                        button.textContent = added ? 'Added' : 'Add to PR';
                        button.disabled = added;
                        button.classList.toggle('is-added', added);
                    }
                });
            };

            const fillFirstEmptyPrRow = (row) => {
                const targetRow = [...document.querySelectorAll('.pr-item-row')].find(rowIsEmpty);

                if (!targetRow) {
                    return false;
                }

                const appItemInput = targetRow.querySelector('[data-pr-row-app-item]');
                const fields = {
                    stock_no: row.dataset.itemNo || '',
                    unit_of_issue: row.dataset.unit || '',
                    description: row.dataset.description || '',
                    quantity: row.dataset.quantity || '',
                    estimated_unit_cost: row.dataset.unitCost || '',
                };

                Object.entries(fields).forEach(([field, value]) => {
                    const input = targetRow.querySelector(`[data-field="${field}"]`);

                    if (input && value && !(input.value || '').trim()) {
                        input.value = value;
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                });

                if (appItemInput) {
                    appItemInput.value = row.dataset.appItemId || '';
                }

                document.dispatchEvent(new CustomEvent('papertrail:pr-items-changed'));

                return true;
            };

            const clearSelection = () => {
                selectedInput.value = '';

                selectedSummary?.classList.add('is-empty');
                selectedBadge?.classList.remove('is-verified');
                selectedBadge?.classList.add('is-required');

                if (selectedBadge) {
                    selectedBadge.textContent = 'Required';
                }

                setSummaryValue('[data-selected-app-number]', 'No APP selected');
                setSummaryValue('[data-selected-app-description]', 'Select a consolidated, submitted, or approved APP item below');
                setSummaryValue('[data-selected-app-status]', 'Not linked');
                updateActionStates();
            };

            const selectRow = (row) => {
                if (linkedAppIds().has(row.dataset.appItemId || '')) {
                    return;
                }

                if (!fillFirstEmptyPrRow(row)) {
                    window.PaperTrailDialog?.notice('No empty PR row is available. Add a new row before adding another APP item.', {
                        title: 'No Empty PR Row',
                    });
                    return;
                }

                syncPrimarySelection();
                updateActionStates();

                selectedSummary?.classList.remove('is-empty');
                selectedBadge?.classList.remove('is-required');
                selectedBadge?.classList.add('is-verified');

                if (selectedBadge) {
                    selectedBadge.textContent = 'Verified';
                }

                setSummaryValue('[data-selected-app-number]', row.dataset.appNumber || 'APP Reference');
                setSummaryValue('[data-selected-app-description]', row.dataset.description || 'Selected APP item');
                setSummaryValue('[data-selected-app-status]', row.dataset.statusLabel || 'Verified');
            };

            const filterRows = () => {
                const query = (searchInput?.value || '').toLowerCase().trim();
                const officeId = officeSelect?.value || '';
                let visibleCount = 0;

                rows.forEach((row) => {
                    const rowOfficeId = row.dataset.officeId || '';
                    const matchesOffice = !officeId || !rowOfficeId || rowOfficeId === officeId;
                    const matchesSearch = !query || (row.dataset.search || '').includes(query);
                    const visible = matchesOffice && matchesSearch;

                    row.hidden = !visible;

                    if (visible) {
                        visibleCount++;
                    }
                });

                syncPrimarySelection();
                updateActionStates();

                if (emptyState) {
                    emptyState.hidden = visibleCount > 0 || rows.length === 0;
                }
            };

            searchInput?.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                }
            });
            searchInput?.addEventListener('input', filterRows);
            officeSelect?.addEventListener('change', filterRows);
            rows.forEach((row) => row.querySelector('[data-add-app-to-pr]')?.addEventListener('click', () => selectRow(row)));
            document.addEventListener('papertrail:pr-items-changed', () => {
                syncPrimarySelection();
                updateActionStates();
            });
            filterRows();
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', boot);
        } else {
            boot();
        }
    })();
</script>
@endunless
