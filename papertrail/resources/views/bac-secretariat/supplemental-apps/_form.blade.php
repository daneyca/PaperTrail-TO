@php
    $useDocumentCreateFlow = (bool) ($useDocumentCreateFlow ?? false);
    $sourceDocument = $sourceDocument ?? $supplementalApp->sourcePrDocument ?? null;
    $supplementalBackUrl = $sourceDocument
        ? route('bac-secretariat.pr.show', $sourceDocument)
        : route('bac-secretariat.supplemental-apps.index');
@endphp

@csrf
@if ($supplementalApp->exists)
    @method('PATCH')
@endif

@if ($useDocumentCreateFlow)
    <section class="document-create-source-card no-print" data-document-create-source aria-label="Supplemental APP source">
        <div class="document-create-source-card__header">
            <div class="document-create-source-card__step" aria-hidden="true">1</div>
            <div>
                <p class="eyebrow">Supplemental APP Creation</p>
                <h2>Confirm Source Reference</h2>
            </div>
        </div>

        <div class="document-create-source-card__body">
            <x-documents.create-source-split
                title="Recent Supplemental APP Drafts"
                :drafts="$recentDrafts ?? collect()"
                document-type-label="Supplemental APP"
                source-title="Source Reference"
            >
                <div class="detail-grid metadata-grid">
                    <div>
                        <span>Source Purchase Request</span>
                        <strong>{{ $sourceDocument?->pr_no ?? $sourceDocument?->tracking_number ?? 'Manual Supplemental APP' }}</strong>
                    </div>
                    <div>
                        <span>Requesting Office</span>
                        <strong>{{ $sourceDocument?->submittingOffice?->name ?? $supplementalApp->requesting_office_name ?? 'To be entered in form' }}</strong>
                    </div>
                    <div>
                        <span>Fiscal Year</span>
                        <strong>{{ old('fiscal_year', $supplementalApp->fiscal_year ?? now()->year) }}</strong>
                    </div>
                    <div>
                        <span>Estimated Amount</span>
                        <strong>
                            @if (filled($sourceDocument?->total_amount ?? $supplementalApp->total_amount))
                                PHP {{ number_format((float) ($sourceDocument?->total_amount ?? $supplementalApp->total_amount), 2) }}
                            @else
                                To be entered in form
                            @endif
                        </strong>
                    </div>
                </div>
            </x-documents.create-source-split>
        </div>

        <div class="document-create-source-card__actions">
            <a href="{{ $supplementalBackUrl }}" class="document-create-secondary-action">Back</a>
            <button type="button" class="document-create-proceed-button" data-document-create-proceed>Proceed to Supplemental APP</button>
        </div>

        <p class="document-create-source-error" data-document-create-error hidden>Please review the source reference before proceeding.</p>
    </section>

    <div class="document-create-form" data-document-create-form>
@endif

<div class="supplemental-document-actions no-print">
    <a href="{{ $supplementalApp->exists ? route('bac-secretariat.supplemental-apps.show', $supplementalApp) : route('bac-secretariat.supplemental-apps.index') }}">Back</a>
    <button type="submit" name="save_action" value="draft">Save Draft</button>
    <button type="submit" name="save_action" value="created">Save and Link</button>
    <button type="submit" name="save_action" value="submit">Submit</button>
    <button type="button" data-supplemental-add-row>Add Item</button>
    <button type="button" data-supplemental-remove-empty-rows>Remove Empty Rows</button>
    <button type="button" data-supplemental-print>Print</button>
</div>

@include('bac-secretariat.supplemental-apps.partials.official-form-editable')

@if ($useDocumentCreateFlow)
    </div>
@endif

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const table = document.querySelector('[data-supplemental-items-table]');
        const addButton = document.querySelector('[data-supplemental-add-row]');
        const removeEmptyButton = document.querySelector('[data-supplemental-remove-empty-rows]');
        const printButton = document.querySelector('[data-supplemental-print]');

        if (!table) return;

        function money(value) {
            return Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function rowHasValue(row) {
            return Array.from(row.querySelectorAll('input:not([type="hidden"]), textarea'))
                .some((field) => String(field.value || '').trim() !== '');
        }

        function renameRows() {
            table.querySelectorAll('tbody tr').forEach((row, index) => {
                row.querySelectorAll('input, textarea').forEach((field) => {
                    field.name = field.name.replace(/items\[\d+\]/, `items[${index}]`);
                });
            });
        }

        function recalculate() {
            let grandTotal = 0;

            table.querySelectorAll('tbody tr').forEach((row) => {
                const quantity = Number(row.querySelector('[data-supplemental-qty]')?.value || 0);
                const unitCost = Number(row.querySelector('[data-supplemental-unit-cost]')?.value || 0);
                const total = quantity * unitCost;
                grandTotal += total;
                const totalCell = row.querySelector('[data-supplemental-row-total]');
                if (totalCell) totalCell.textContent = money(total);
            });

            const grandTotalCell = table.querySelector('[data-supplemental-grand-total]');
            if (grandTotalCell) grandTotalCell.textContent = `PHP ${money(grandTotal)}`;
        }

        function autoSizeTextarea(textarea) {
            if (!textarea) return;
            textarea.style.height = 'auto';
            textarea.style.height = `${textarea.scrollHeight}px`;
        }

        function autoSizeAllTextareas() {
            document
                .querySelectorAll('.supplemental-official-sheet textarea')
                .forEach(autoSizeTextarea);
        }

        function createRow(index) {
            const row = document.createElement('tr');

            row.innerHTML = `
                <td><input type="hidden" name="items[${index}][source_pr_item_id]" value=""><input name="items[${index}][item_no]" type="text"></td>
                <td><textarea name="items[${index}][description]" rows="2"></textarea></td>
                <td><input name="items[${index}][quantity]" type="number" min="0" step="0.01" data-supplemental-qty></td>
                <td><input name="items[${index}][unit]" type="text"></td>
                <td><input name="items[${index}][estimated_unit_cost]" type="number" min="0" step="0.01" data-supplemental-unit-cost></td>
                <td class="supplemental-money-cell" data-supplemental-row-total>0.00</td>
                <td><input name="items[${index}][remarks]" type="text"></td>
            `;

            return row;
        }

        table.addEventListener('input', (event) => {
            if (event.target.matches('[data-supplemental-qty], [data-supplemental-unit-cost]')) {
                recalculate();
            }

            if (event.target.matches('textarea')) {
                autoSizeTextarea(event.target);
            }
        });

        addButton?.addEventListener('click', () => {
            const tbody = table.querySelector('tbody');
            const row = createRow(tbody.querySelectorAll('tr').length);
            tbody.appendChild(row);
            row.querySelectorAll('textarea').forEach(autoSizeTextarea);
            row.querySelector('input:not([type="hidden"]), textarea')?.focus();
        });

        removeEmptyButton?.addEventListener('click', () => {
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));

            rows.forEach((row) => {
                if (rows.length > 1 && !rowHasValue(row)) {
                    row.remove();
                }
            });

            if (!tbody.querySelector('tr')) {
                const fallbackRow = createRow(0);
                tbody.appendChild(fallbackRow);
                fallbackRow.querySelectorAll('textarea').forEach(autoSizeTextarea);
            }

            renameRows();
            recalculate();
            autoSizeAllTextareas();
        });

        recalculate();
        autoSizeAllTextareas();

        printButton?.addEventListener('click', () => {
            autoSizeAllTextareas();
            window.print();
        });

        window.addEventListener('beforeprint', autoSizeAllTextareas);
    });
</script>
