@php
    $ppmpSupplyItems = collect($ppmpSupplyItems ?? []);
    $selectedAppItem = $selectedAppItem ?? null;
    $selectedApp = $selectedAppItem?->appConsolidation;
    $projectDescription = trim((string) ($selectedAppItem?->general_description ?? ''));
    $projectTitle = trim((string) preg_replace('/^Purchase\s+of\s+/i', '', $projectDescription));
    $projectTitle = $projectTitle !== '' ? $projectTitle : ($projectDescription ?: 'Selected APP Project');
    $supplyPageSize = 10;
    $supplyPageCount = max(1, (int) ceil($ppmpSupplyItems->count() / $supplyPageSize));
@endphp

<section
    class="pr-app-reference-card pr-ppmp-supply-panel no-print"
    data-pr-ppmp-supply-picker
    data-supply-page-size="{{ $supplyPageSize }}"
    aria-labelledby="pr-ppmp-supply-title"
>
    <div class="pr-app-reference-heading">
        <div>
            <p class="pr-page-kicker">PPMP Supply Details</p>
            <h2 id="pr-ppmp-supply-title">Add Items From {{ $projectTitle }}</h2>
        </div>
        <span class="pr-app-reference-badge is-verified">{{ $ppmpSupplyItems->count() }} Item{{ $ppmpSupplyItems->count() === 1 ? '' : 's' }}</span>
    </div>

    @if ($ppmpSupplyItems->isNotEmpty())
        <div class="pr-app-reference-table-wrap">
            <table class="pr-app-reference-table pr-ppmp-supply-table">
                <thead>
                    <tr>
                        <th>PPMP Item</th>
                        <th>Unit</th>
                        <th>Quantity</th>
                        <th>Unit Cost</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ppmpSupplyItems as $supply)
                        <tr data-pr-ppmp-supply-row>
                            <td>
                                <strong>{{ $supply['description'] }}</strong>
                                <small>{{ $selectedApp?->app_number ?? 'APP Reference' }}</small>
                            </td>
                            <td>{{ $supply['unit'] ?: 'unit' }}</td>
                            <td>{{ $supply['quantity'] ?: '1' }}</td>
                            <td>PHP {{ filled($supply['unit_cost']) ? number_format((float) $supply['unit_cost'], 2) : '0.00' }}</td>
                            <td>PHP {{ number_format((float) ($supply['total'] ?? 0), 2) }}</td>
                            <td>
                                <div class="pr-ppmp-supply-actions">
                                    <button
                                        type="button"
                                        class="btn-pr-secondary pr-ppmp-add-btn"
                                        data-add-ppmp-supply-to-pr
                                        data-supply-id="{{ $supply['id'] }}"
                                        data-app-item-id="{{ $supply['app_item_id'] }}"
                                        data-stock-no="{{ $supply['stock_no'] }}"
                                        data-unit="{{ $supply['unit'] }}"
                                        data-description="{{ $supply['description'] }}"
                                        data-quantity="{{ $supply['quantity'] }}"
                                        data-unit-cost="{{ $supply['unit_cost'] }}"
                                    >
                                        Add
                                    </button>
                                    <button
                                        type="button"
                                        class="btn-pr-secondary pr-ppmp-undo-btn"
                                        data-remove-ppmp-supply-from-pr
                                        data-supply-id="{{ $supply['id'] }}"
                                        hidden
                                    >
                                        Undo
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($supplyPageCount > 1)
            <div class="pr-ppmp-supply-pagination" data-pr-ppmp-supply-pagination>
                <span data-pr-ppmp-supply-page-summary>
                    Showing 1-{{ min($supplyPageSize, $ppmpSupplyItems->count()) }} of {{ $ppmpSupplyItems->count() }}
                </span>
                <div class="pr-ppmp-supply-page-controls">
                    <button type="button" data-pr-ppmp-supply-page-prev>Previous</button>
                    @for ($page = 1; $page <= $supplyPageCount; $page++)
                        <button type="button" data-pr-ppmp-supply-page-button data-page="{{ $page }}">{{ $page }}</button>
                    @endfor
                    <button type="button" data-pr-ppmp-supply-page-next>Next</button>
                </div>
            </div>
        @endif
    @else
        <div class="pr-app-reference-empty">
            <strong>No source PPMP items found</strong>
            <p>This APP project is linked, but the original PPMP supply rows were not found. You can still encode the PR items manually.</p>
        </div>
    @endif
</section>

<script>
    (() => {
        const boot = () => {
            const picker = document.querySelector('[data-pr-ppmp-supply-picker]');

            if (!picker) {
                return;
            }

            const buttons = [...picker.querySelectorAll('[data-add-ppmp-supply-to-pr]')];
            const undoButtons = [...picker.querySelectorAll('[data-remove-ppmp-supply-from-pr]')];
            const supplyRows = [...picker.querySelectorAll('[data-pr-ppmp-supply-row]')];
            const supplyPageSize = Math.max(1, Number.parseInt(picker.dataset.supplyPageSize || '10', 10));
            const pagination = picker.querySelector('[data-pr-ppmp-supply-pagination]');
            const paginationSummary = picker.querySelector('[data-pr-ppmp-supply-page-summary]');
            const previousPageButton = picker.querySelector('[data-pr-ppmp-supply-page-prev]');
            const nextPageButton = picker.querySelector('[data-pr-ppmp-supply-page-next]');
            const pageButtons = [...picker.querySelectorAll('[data-pr-ppmp-supply-page-button]')];
            const supplyPageCount = Math.max(1, Math.ceil(supplyRows.length / supplyPageSize));
            let currentSupplyPage = 1;

            if (!buttons.length) {
                return;
            }

            const renderSupplyPage = (page) => {
                if (!supplyRows.length || !pagination || supplyPageCount <= 1) {
                    supplyRows.forEach((row) => row.hidden = false);
                    return;
                }

                currentSupplyPage = Math.min(Math.max(1, page), supplyPageCount);
                const start = (currentSupplyPage - 1) * supplyPageSize;
                const end = Math.min(start + supplyPageSize, supplyRows.length);

                supplyRows.forEach((row, index) => {
                    row.hidden = index < start || index >= end;
                });

                if (paginationSummary) {
                    paginationSummary.textContent = `Showing ${start + 1}-${end} of ${supplyRows.length}`;
                }

                if (previousPageButton) {
                    previousPageButton.disabled = currentSupplyPage === 1;
                }

                if (nextPageButton) {
                    nextPageButton.disabled = currentSupplyPage === supplyPageCount;
                }

                pageButtons.forEach((button) => {
                    const isActive = Number.parseInt(button.dataset.page || '1', 10) === currentSupplyPage;
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-current', isActive ? 'page' : 'false');
                });
            };

            const prRows = () => [...document.querySelectorAll('.pr-item-row')];
            const addRowButton = () => document.querySelector('[data-add-pr-item]');
            const rowIsEmpty = (row) => {
                if (window.PaperTrailRowTools?.isRowEmpty) {
                    return window.PaperTrailRowTools.isRowEmpty(row, '.pr-excel-cell');
                }

                return [...row.querySelectorAll('.pr-excel-cell')]
                    .every((field) => !(field.value || '').trim());
            };

            const rowKey = (row) => {
                return row.querySelector('[data-pr-row-ppmp-supply]')?.value || '';
            };

            const buttonKey = (button) => button.dataset.supplyId || '';

            const existingKeys = () => new Set(
                prRows()
                    .filter((row) => !rowIsEmpty(row))
                    .map(rowKey)
                    .filter(Boolean)
            );

            const firstEmptyRow = () => prRows().find(rowIsEmpty) || null;

            const ensureTargetRow = () => {
                let targetRow = firstEmptyRow();

                if (targetRow) {
                    return targetRow;
                }

                addRowButton()?.click();
                targetRow = firstEmptyRow();

                return targetRow;
            };

            const setField = (row, field, value) => {
                const input = row.querySelector(`[data-field="${field}"]`);

                if (!input) {
                    return;
                }

                input.value = value || '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
            };

            const updateButtonStates = () => {
                const added = existingKeys();

                buttons.forEach((button) => {
                    const isAdded = added.has(buttonKey(button));
                    const row = button.closest('[data-pr-ppmp-supply-row]');
                    const undoButton = row?.querySelector('[data-remove-ppmp-supply-from-pr]');

                    button.disabled = isAdded;
                    button.textContent = isAdded ? 'Added' : 'Add';
                    button.classList.toggle('is-added', isAdded);
                    undoButton?.toggleAttribute('hidden', !isAdded);
                    row?.classList.toggle('is-selected', isAdded);
                });
            };

            const prRowForSupply = (supplyId) => prRows()
                .find((row) => row.querySelector('[data-pr-row-ppmp-supply]')?.value === supplyId);

            const clearField = (row, field) => {
                const input = row.querySelector(`[data-field="${field}"]`);

                if (!input) {
                    return;
                }

                input.value = '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
            };

            const undoSupplyFromPr = (button) => {
                const targetRow = prRowForSupply(button.dataset.supplyId || '');

                if (!targetRow) {
                    updateButtonStates();
                    return;
                }

                clearField(targetRow, 'app_item_id');
                clearField(targetRow, 'ppmp_item_id');
                clearField(targetRow, 'stock_no');
                clearField(targetRow, 'unit_of_issue');
                clearField(targetRow, 'description');
                clearField(targetRow, 'quantity');
                clearField(targetRow, 'estimated_unit_cost');
                clearField(targetRow, 'estimated_cost');

                targetRow.classList.add('pr-empty-sheet-row');
                document.dispatchEvent(new CustomEvent('papertrail:pr-items-changed'));
                updateButtonStates();
            };

            const addSupplyToPr = (button) => {
                if (button.disabled) {
                    return;
                }

                const targetRow = ensureTargetRow();

                if (!targetRow) {
                    window.PaperTrailDialog?.notice('No PR row is available. Please add a row, then try again.', {
                        title: 'No PR Row Available',
                    });
                    return;
                }

                const appItemInput = targetRow.querySelector('[data-pr-row-app-item]');

                if (appItemInput) {
                    appItemInput.value = button.dataset.appItemId || '';
                }

                const ppmpItemInput = targetRow.querySelector('[data-pr-row-ppmp-supply]');

                if (ppmpItemInput) {
                    ppmpItemInput.value = button.dataset.supplyId || '';
                }

                setField(targetRow, 'stock_no', button.dataset.stockNo || '');
                setField(targetRow, 'unit_of_issue', button.dataset.unit || 'unit');
                setField(targetRow, 'description', button.dataset.description || '');
                setField(targetRow, 'quantity', button.dataset.quantity || '1');
                setField(targetRow, 'estimated_unit_cost', button.dataset.unitCost || '');

                targetRow.classList.remove('pr-empty-sheet-row');
                document.dispatchEvent(new CustomEvent('papertrail:pr-items-changed'));
                updateButtonStates();
            };

            buttons.forEach((button) => button.addEventListener('click', () => addSupplyToPr(button)));
            undoButtons.forEach((button) => button.addEventListener('click', () => undoSupplyFromPr(button)));
            previousPageButton?.addEventListener('click', () => renderSupplyPage(currentSupplyPage - 1));
            nextPageButton?.addEventListener('click', () => renderSupplyPage(currentSupplyPage + 1));
            pageButtons.forEach((button) => {
                button.addEventListener('click', () => renderSupplyPage(Number.parseInt(button.dataset.page || '1', 10)));
            });
            document.addEventListener('papertrail:pr-items-changed', updateButtonStates);
            renderSupplyPage(1);
            updateButtonStates();
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', boot);
        } else {
            boot();
        }
    })();
</script>
