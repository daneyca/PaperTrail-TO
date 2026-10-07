@csrf
@if ($document->exists)
    @method('PATCH')
@endif

<input type="hidden" name="fiscal_year" value="{{ old('fiscal_year', $document->fiscal_year ?: now()->year) }}">
<input type="hidden" name="priority" value="{{ old('priority', $document->priority ?: 'normal') }}">
<input type="hidden" name="remarks" value="{{ old('remarks', $document->remarks) }}">

@include('head-office.pr._sheet', ['mode' => 'edit'])

<template id="prItemTemplate">
    <tr class="pr-item-row pr-empty-sheet-row">
        <td>
            <input type="hidden" name="__NAME__[app_item_id]" data-field="app_item_id" data-pr-row-app-item>
            <input type="hidden" name="__NAME__[ppmp_item_id]" data-field="ppmp_item_id" data-pr-row-ppmp-supply>
            <input class="pr-input pr-excel-cell" name="__NAME__[stock_no]" type="text" data-field="stock_no" data-row="__ROW__" data-col="0">
        </td>
        <td><input class="pr-input pr-excel-cell" name="__NAME__[unit_of_issue]" type="text" data-field="unit_of_issue" data-row="__ROW__" data-col="1"></td>
        <td><textarea class="pr-textarea pr-excel-cell" name="__NAME__[description]" rows="2" data-field="description" data-row="__ROW__" data-col="2"></textarea></td>
        <td><input class="pr-input pr-number-input pr-excel-cell" name="__NAME__[quantity]" type="number" min="0" step="0.01" data-field="quantity" data-row="__ROW__" data-col="3" data-quantity></td>
        <td><input class="pr-input pr-number-input pr-excel-cell" name="__NAME__[estimated_unit_cost]" type="number" min="0" step="0.01" data-field="estimated_unit_cost" data-row="__ROW__" data-col="4" data-unit-cost></td>
        <td><input class="pr-input pr-number-input pr-row-total" name="__NAME__[estimated_cost]" type="text" data-field="estimated_cost" data-row="__ROW__" data-row-total readonly tabindex="-1"></td>
    </tr>
</template>

<script>
    (() => {
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

        const form = document.querySelector('[data-pr-form]');
        if (!form) return;

        const body = form.querySelector('[data-pr-items]');
        const template = document.querySelector('#prItemTemplate');
        const total = form.querySelector('[data-pr-total]');
        const ownerForm = form.closest('form') || document;
        const actionInput = ownerForm.querySelector('#purchaseRequestFormAction');
        const submitForPrNumberButton = ownerForm.querySelector('[data-submit-pr-number]');
        const budgetGuard = ownerForm.querySelector('[data-pr-budget-guard]');
        const minimumRows = 18;
        const editableColumns = 5;
        let nextIndex = body.querySelectorAll('tr').length;
        const money = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        function rows() {
            return [...body.querySelectorAll('tr')];
        }

        function rowIsEmpty(row) {
            return window.PaperTrailRowTools.isRowEmpty(row, '.pr-excel-cell');
        }

        function reindexRows() {
            rows().forEach((row, rowIndex) => {
                row.classList.toggle('pr-empty-sheet-row', rowIsEmpty(row));

                row.querySelectorAll('[data-field]').forEach((field) => {
                    field.dataset.row = rowIndex;
                    field.name = `items[${rowIndex}][${field.dataset.field}]`;
                });
            });

            nextIndex = rows().length;
            document.dispatchEvent(new CustomEvent('papertrail:pr-items-changed'));
        }

        function appendRow() {
            const row = template.content.firstElementChild.cloneNode(true);
            row.innerHTML = row.innerHTML
                .replaceAll('__NAME__', `items[${nextIndex}]`)
                .replaceAll('__ROW__', `${nextIndex}`);
            body.appendChild(row);
            reindexRows();
            recalculate();
            return row;
        }

        function ensureRow(rowIndex) {
            while (rows().length <= rowIndex) {
                appendRow();
            }
        }

        function cellAt(rowIndex, colIndex) {
            ensureRow(rowIndex);
            return body.querySelector(`.pr-excel-cell[data-row="${rowIndex}"][data-col="${colIndex}"]`);
        }

        function focusCell(rowIndex, colIndex) {
            const target = cellAt(rowIndex, colIndex);

            if (target) {
                target.focus();
                target.select?.();
            }
        }

        function updateBudgetGuard(grandTotal) {
            if (!budgetGuard) {
                return;
            }

            const remaining = parseFloat(budgetGuard.dataset.remainingBudget || '0') || 0;
            const after = remaining - grandTotal;
            const isOverBudget = after < -0.009;
            const currentTarget = budgetGuard.querySelector('[data-pr-budget-current]');
            const afterTarget = budgetGuard.querySelector('[data-pr-budget-after]');
            const statusTarget = budgetGuard.querySelector('[data-pr-budget-status]');
            const messageTarget = budgetGuard.querySelector('[data-pr-budget-message]');

            if (currentTarget) {
                currentTarget.textContent = money.format(grandTotal);
            }

            if (afterTarget) {
                afterTarget.textContent = money.format(Math.max(after, 0));
            }

            budgetGuard.classList.toggle('is-over-budget', isOverBudget);

            if (statusTarget) {
                statusTarget.textContent = isOverBudget ? 'Over budget' : 'Within budget';
            }

            if (messageTarget) {
                messageTarget.textContent = isOverBudget
                    ? `Reduce this PR by PHP ${money.format(Math.abs(after))} before submitting. Save Draft is still allowed.`
                    : 'Unit costs may be encoded manually, but the submitted PR total must stay within the remaining APP/PPMP budget.';
            }
        }

        function recalculate() {
            let grandTotal = 0;

            rows().forEach((row) => {
                const quantity = parseFloat(row.querySelector('[data-quantity]')?.value || '0') || 0;
                const unitCost = parseFloat(row.querySelector('[data-unit-cost]')?.value || '0') || 0;
                const rowTotal = quantity * unitCost;
                const rowTotalInput = row.querySelector('[data-row-total]');
                const hasEnteredData = [...row.querySelectorAll('.pr-excel-cell')]
                    .some((field) => field.value.trim() !== '');

                if (rowTotalInput) {
                    rowTotalInput.value = hasEnteredData && rowTotal > 0 ? rowTotal.toFixed(2) : '';
                }

                if (hasEnteredData) {
                    grandTotal += rowTotal;
                }

                row.classList.toggle('pr-empty-sheet-row', !hasEnteredData);
            });

            if (total) {
                total.textContent = money.format(grandTotal);
            }

            updateBudgetGuard(grandTotal);
            document.dispatchEvent(new CustomEvent('papertrail:pr-items-changed'));
        }

        form.addEventListener('input', (event) => {
            if (event.target.matches('.pr-excel-cell')) {
                recalculate();
            }
        });

        form.addEventListener('keydown', (event) => {
            const current = event.target.closest('.pr-excel-cell');
            if (!current) return;

            const rowIndex = Number(current.dataset.row);
            const colIndex = Number(current.dataset.col);

            if (event.key === 'Enter') {
                if (current.tagName === 'TEXTAREA' && event.shiftKey) {
                    return;
                }

                event.preventDefault();
                focusCell(rowIndex + 1, colIndex);
                return;
            }

            if (!['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) {
                return;
            }

            event.preventDefault();

            if (event.key === 'ArrowUp' && rowIndex > 0) {
                focusCell(rowIndex - 1, colIndex);
            }

            if (event.key === 'ArrowDown') {
                focusCell(rowIndex + 1, colIndex);
            }

            if (event.key === 'ArrowLeft') {
                if (colIndex > 0) {
                    focusCell(rowIndex, colIndex - 1);
                } else if (rowIndex > 0) {
                    focusCell(rowIndex - 1, editableColumns - 1);
                }
            }

            if (event.key === 'ArrowRight') {
                if (colIndex < editableColumns - 1) {
                    focusCell(rowIndex, colIndex + 1);
                } else {
                    focusCell(rowIndex + 1, 0);
                }
            }
        });

        form.addEventListener('paste', (event) => {
            const current = event.target.closest('.pr-excel-cell');
            if (!current) return;

            const text = event.clipboardData?.getData('text/plain') ?? '';

            if (!text.includes('\t') && !text.includes('\n')) {
                return;
            }

            event.preventDefault();

            const startRow = Number(current.dataset.row);
            const startCol = Number(current.dataset.col);
            const pastedRows = text
                .replace(/\r/g, '')
                .split('\n')
                .filter((line, index, list) => line !== '' || index < list.length - 1);

            pastedRows.forEach((line, rowOffset) => {
                const targetRow = startRow + rowOffset;
                ensureRow(targetRow);

                line.split('\t').forEach((value, colOffset) => {
                    const targetCol = startCol + colOffset;

                    if (targetCol >= editableColumns) {
                        return;
                    }

                    const target = cellAt(targetRow, targetCol);
                    if (target) {
                        target.value = value.trim();
                    }
                });
            });

            reindexRows();
            recalculate();

            if (pastedRows.length > 0) {
                const lastPastedRow = pastedRows[pastedRows.length - 1];
                const lastPastedColumnCount = lastPastedRow.split('\t').length || 1;
                focusCell(startRow + pastedRows.length - 1, Math.min(editableColumns - 1, startCol + lastPastedColumnCount - 1));
            }
        });

        ownerForm.querySelector('[data-add-pr-item]')?.addEventListener('click', () => {
            const row = appendRow();
            row.querySelector('.pr-excel-cell')?.focus();
        });

        ownerForm.querySelector('[data-remove-last-pr-item]')?.addEventListener('click', () => {
            const currentRows = rows();

            if (window.PaperTrailRowTools.removeSelectedEmptyRow(currentRows[currentRows.length - 1], '.pr-item-row', 1, '.pr-excel-cell')) {
                reindexRows();
                recalculate();
                document.dispatchEvent(new CustomEvent('papertrail:pr-items-changed'));
            }
        });

        ownerForm.querySelector('[data-clear-empty-pr-items]')?.addEventListener('click', () => {
            const removed = window.PaperTrailRowTools.removeEmptyRows('.pr-item-row', 1, '.pr-excel-cell');

            if (removed === 0) {
                window.PaperTrailDialog?.notice('No empty PR rows to remove.', {
                    title: 'No Empty Rows',
                });
            }

            reindexRows();
            recalculate();
            document.dispatchEvent(new CustomEvent('papertrail:pr-items-changed'));
        });

        ownerForm.querySelector('[data-pr-draft-action]')?.addEventListener('click', () => {
            if (actionInput) {
                actionInput.value = 'save_draft';
            }
        });

        ownerForm.querySelectorAll('.pr-signatory-select').forEach((select) => {
            select.addEventListener('change', () => {
                const manualFlag = select.closest('.pr-signatory-cell')?.querySelector('[data-pr-signatory-designation-manual]');

                if (manualFlag) {
                    manualFlag.value = select.value ? '1' : '0';
                }
            });
        });

        submitForPrNumberButton?.addEventListener('click', async (event) => {
            event.preventDefault();

            if (actionInput) {
                actionInput.value = 'submit_for_pr_number';
            }

            const confirmed = await window.PaperTrailDialog.confirm(
                submitForPrNumberButton.dataset.submitConfirm || 'Submit this Purchase Request to PR Numbering Staff?',
                {
                    title: 'Submit Purchase Request?',
                    confirmLabel: 'Confirm Submission',
                    type: 'submit',
                }
            );

            if (!confirmed) {
                return false;
            }

            ownerForm.dataset.ptConfirmApproved = 'true';
            window.setTimeout(() => {
                delete ownerForm.dataset.ptConfirmApproved;
            }, 800);

            if (typeof ownerForm.requestSubmit === 'function') {
                ownerForm.requestSubmit(submitForPrNumberButton);
                return false;
            }

            ownerForm.submit();
        });

        ownerForm.addEventListener('submit', () => {
            reindexRows();
            recalculate();

            if (actionInput?.value === 'submit_for_pr_number' && submitForPrNumberButton) {
                submitForPrNumberButton.dataset.originalText = submitForPrNumberButton.textContent;

                requestAnimationFrame(() => {
                    submitForPrNumberButton.disabled = true;
                    submitForPrNumberButton.textContent = 'Submitting...';
                });
            }
        });

        reindexRows();
        recalculate();
    })();
</script>
