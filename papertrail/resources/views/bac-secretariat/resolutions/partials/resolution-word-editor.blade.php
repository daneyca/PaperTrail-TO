@php
    $mode = $mode ?? 'show';
    $sourceDocument = $sourceDocument ?? $resolution->sourcePrDocument;
    $isEditMode = in_array($mode, ['create', 'edit'], true);
    $canEditDocument = $isEditMode && (! $resolution->exists || $resolution->isEditable());
    $documentHtml = old('document_html', $resolution->document_html);
    $body = old('body_json', $resolution->body_json ?: $resolution->header_lines ?: []);
    $whereasClauses = array_values(old('whereas_clauses', $resolution->whereas_clauses ?: []));
    $resolvedClauses = array_values(old('resolved_clauses', $resolution->resolved_clauses ?: []));
    $signatories = old('signatories', $resolution->signatories ?: []);
    $approvalDetails = old('approval_signatory', old('approval_details', $resolution->approval_details ?: $resolution->approval_signatory ?: []));
    $signatoryUsers = collect($signatoryUsers ?? []);
    $signatureSlots = collect($signatureSlots ?? []);
    $bacChairSignature = $bacChairSignature ?? $signatureSlots->get('chairperson');
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

@if ($canEditDocument)
    <textarea name="document_html" id="document_html" hidden>{{ $documentHtml }}</textarea>
    <textarea name="document_text" id="document_text" hidden>{{ old('document_text', $resolution->document_text) }}</textarea>
    <input type="hidden" name="source_pr_document_id" value="{{ old('source_pr_document_id', $resolution->source_pr_document_id ?? $sourceDocument?->id) }}">
    <input type="hidden" name="fiscal_year" value="{{ old('fiscal_year', $resolution->fiscal_year ?: now()->year) }}">
    <input type="hidden" name="resolution_series" value="{{ old('resolution_series', $resolution->resolution_series ?: now()->year) }}">
    <input type="hidden" name="requesting_office_name" value="{{ old('requesting_office_name', $resolution->requesting_office_name ?? $sourceDocument?->submittingOffice?->name) }}">
    <input type="hidden" name="pr_number" value="{{ old('pr_number', $resolution->pr_number ?? $sourceDocument?->pr_no ?? $sourceDocument?->tracking_number) }}">
    <input type="hidden" name="supplier_name" value="{{ old('supplier_name', $resolution->supplier_name) }}">
    <input type="hidden" name="supplier_address" value="{{ old('supplier_address', $resolution->supplier_address) }}">
    <input type="hidden" name="supplier_contact" value="{{ old('supplier_contact', $resolution->supplier_contact) }}">
    <input type="hidden" name="project_title" value="{{ $projectTitle }}">
    <input type="hidden" name="contractor_name" value="{{ $contractorName }}">
    <input type="hidden" name="abc_amount" value="{{ $abcAmount }}">
    <input type="hidden" name="philgeps_reference_no" value="{{ $philgeps }}">
    <input type="hidden" name="solicitation_no" value="{{ $solicitation }}">
    <input type="hidden" name="resolution_date" value="{{ $resolutionDate }}">
    <input type="hidden" name="total_amount" value="{{ old('total_amount', $resolution->total_amount ?? $abcAmount ?? 0) }}">
    <input type="hidden" name="total_amount_words" value="{{ $amountWords }}">
    <input type="hidden" name="refund_amount" value="{{ old('refund_amount', $resolution->refund_amount) }}">

    <div class="resolution-word-toolbar no-print" data-resolution-toolbar aria-label="BAC Resolution formatting toolbar">
        <button type="button" data-editor-command="bold" title="Bold"><strong>B</strong></button>
        <button type="button" data-editor-command="italic" title="Italic"><em>I</em></button>
        <button type="button" data-editor-command="underline" title="Underline"><u>U</u></button>
        <span class="toolbar-divider" aria-hidden="true"></span>
        <button type="button" data-editor-command="justifyLeft" title="Align left">Left</button>
        <button type="button" data-editor-command="justifyCenter" title="Align center">Center</button>
        <button type="button" data-editor-command="justifyFull" title="Justify">Justify</button>
        <span class="toolbar-divider" aria-hidden="true"></span>
        <button type="button" data-editor-command="insertOrderedList" title="Numbered list">1.</button>
        <button type="button" data-editor-command="outdent" title="Outdent">Outdent</button>
        <button type="button" data-editor-command="indent" title="Indent">Indent</button>
    </div>
@endif

<div class="resolution-screen-area">
    <div class="resolution-document-wrap">
        <article
            id="resolutionEditor"
            class="resolution-paper resolution-document bac-resolution-official-sheet bac-resolution-official-body"
            data-resolution-editor
            contenteditable="{{ $canEditDocument ? 'true' : 'false' }}"
            spellcheck="{{ $canEditDocument ? 'true' : 'false' }}"
            aria-label="BAC Resolution document"
        >
            @include('bac-secretariat.resolutions.partials.resolution-document-body')
        </article>
    </div>
</div>

<div id="resolutionPrintArea" class="resolution-print-area" aria-hidden="true">
    <article id="resolutionPrintPaper" class="resolution-paper resolution-document bac-resolution-official-sheet bac-resolution-official-body bac-resolution-print-sheet">
        @include('bac-secretariat.resolutions.partials.resolution-document-body')
    </article>
</div>

@if ($canEditDocument)
    <script>
        (() => {
            const script = document.currentScript;
            const form = script.closest('form');

            if (!form) {
                return;
            }

            const editor = form.querySelector('[data-resolution-editor]');
            const htmlField = form.querySelector('#document_html');
            const textField = form.querySelector('#document_text');
            const toolbar = form.querySelector('[data-resolution-toolbar]');

            if (!editor || !htmlField || !textField) {
                return;
            }

            const syncDocumentFields = () => {
                htmlField.value = editor.innerHTML.trim();
                textField.value = editor.innerText.trim();
            };

            const updateSignatoryDisplay = (row, shouldAutofill = false) => {
                const slot = row.dataset.slot;
                const accountField = row.querySelector('[data-signer-account]');
                const accountCodeField = row.querySelector('[data-signer-account-code]');
                const nameField = row.querySelector('[data-signer-name]');
                const designationField = row.querySelector('[data-signer-designation]');
                const selectedOption = accountField?.selectedOptions?.[0] || designationField?.selectedOptions?.[0];
                const targetSelectors = {
                    prepared_by: ['[data-signatory-display="prepared_by"]', '.resolution-signatory-prepared_by', '.resolution-prepared'],
                    vice_chairperson: ['[data-signatory-display="vice_chairperson"]', '.resolution-signatory-vice_chairperson', '.resolution-signatory-bac_vice_chairperson'],
                    member_one: ['[data-signatory-display="member_one"]', '.resolution-signatory-member_one', '.resolution-signatory-bac_member_1'],
                    member_two: ['[data-signatory-display="member_two"]', '.resolution-signatory-member_two', '.resolution-signatory-bac_member_2'],
                    chairperson: ['[data-signatory-display="chairperson"]', '.resolution-signatory-chairperson', '.resolution-signatory-bac_chairperson'],
                    hope: ['[data-signatory-display="hope"]', '.resolution-signatory-hope', '.resolution-approved'],
                };

                if (shouldAutofill && selectedOption && (selectedOption.value || '').trim() !== '') {
                    const optionName = selectedOption.dataset.userName || '';
                    const optionAccountCode = selectedOption.dataset.accountCode || (accountField ? selectedOption.value : '');
                    const previousName = row.dataset.autoName || '';

                    if (accountCodeField && optionAccountCode) {
                        accountCodeField.value = optionAccountCode;
                    }

                    if (nameField && (!nameField.value.trim() || nameField.value.trim() === previousName)) {
                        nameField.value = optionName;
                        row.dataset.autoName = optionName;
                    }
                }

                if (!slot) {
                    return;
                }

                const targets = new Set();
                (targetSelectors[slot] || [`[data-signatory-display="${slot}"]`]).forEach((selector) => {
                    editor.querySelectorAll(selector).forEach((target) => targets.add(target));
                });

                targets.forEach((target) => {
                    const nameTarget = target.querySelector('[data-signatory-name], .resolution-sign-name, .resolution-approved-name');
                    const designationTarget = target.querySelector('[data-signatory-designation], .resolution-sign-title, .resolution-approved-label');
                    const setValueOrText = (element, value) => {
                        if (!element) {
                            return;
                        }

                        if (element.matches('input, textarea, select')) {
                            element.value = value;
                            return;
                        }

                        element.textContent = value;
                    };

                    if (nameTarget && nameField) {
                        setValueOrText(nameTarget, nameField.value);
                    }

                    if (designationTarget && designationField) {
                        setValueOrText(designationTarget, designationField.value);
                    }
                });

                syncDocumentFields();
            };

            const escapeHtml = (value) => value.replace(/[&<>"']/g, (character) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[character]));

            toolbar?.addEventListener('click', (event) => {
                const button = event.target.closest('[data-editor-command]');

                if (!button) {
                    return;
                }

                event.preventDefault();
                editor.focus();
                document.execCommand(button.dataset.editorCommand, false, null);
                syncDocumentFields();
            });

            editor.addEventListener('paste', (event) => {
                event.preventDefault();

                const text = event.clipboardData?.getData('text/plain') || '';

                if (!text.trim()) {
                    return;
                }

                const html = text
                    .replace(/\r\n/g, '\n')
                    .split(/\n{2,}/)
                    .map((block) => `<p>${block.split('\n').map((line) => escapeHtml(line)).join('<br>')}</p>`)
                    .join('');

                document.execCommand('insertHTML', false, html);
                window.setTimeout(syncDocumentFields, 0);
            });

            editor.addEventListener('input', syncDocumentFields);

            form.querySelectorAll('[data-resolution-signatory-row]').forEach((row) => {
                updateSignatoryDisplay(row, false);

                row.querySelector('[data-signer-account]')?.addEventListener('change', () => updateSignatoryDisplay(row, true));
                row.querySelector('[data-signer-name]')?.addEventListener('input', () => updateSignatoryDisplay(row, false));
                row.querySelector('[data-signer-designation]')?.addEventListener('input', () => updateSignatoryDisplay(row, false));
                row.querySelector('[data-signer-designation]')?.addEventListener('change', (event) => {
                    const manualFlag = row.querySelector('[data-signer-designation-manual]');

                    if (manualFlag) {
                        manualFlag.value = event.target.value ? '1' : '0';
                    }

                    event.target.dataset.manuallySelected = event.target.value ? 'true' : 'false';
                    updateSignatoryDisplay(row, true);
                });
            });

            form.addEventListener('submit', syncDocumentFields);
            syncDocumentFields();
        })();
    </script>
@endif

<script>
    (() => {
        const prepareResolutionPrint = () => {
            const editor = document.getElementById('resolutionEditor')
                || document.querySelector('.resolution-screen-area .resolution-paper')
                || document.querySelector('.resolution-document-wrap .resolution-paper');
            const printArea = document.getElementById('resolutionPrintArea');
            const printPaper = document.getElementById('resolutionPrintPaper');

            if (!editor || !printPaper) {
                console.warn('BAC Resolution print source or print target not found.');
                return;
            }

            printPaper.innerHTML = editor.innerHTML;
            printPaper.removeAttribute('contenteditable');
            printPaper.removeAttribute('spellcheck');
            printArea?.setAttribute('aria-hidden', 'false');

            printPaper.querySelectorAll('[contenteditable]').forEach((element) => {
                element.removeAttribute('contenteditable');
                element.removeAttribute('spellcheck');
            });

            printPaper.querySelectorAll('input, textarea, select').forEach((field) => {
                if (field.type === 'hidden') {
                    field.remove();
                    return;
                }

                const span = document.createElement('span');
                span.textContent = field.tagName === 'SELECT'
                    ? (field.value ? (field.selectedOptions?.[0]?.textContent?.trim() || field.value || '') : '')
                    : (field.value || field.textContent || '');
                field.replaceWith(span);
            });
        };

        window.prepareResolutionPrint = prepareResolutionPrint;
        window.printResolutionDocument = () => {
            prepareResolutionPrint();
            window.setTimeout(() => window.print(), 100);
        };

        window.addEventListener('beforeprint', prepareResolutionPrint);
        document.addEventListener('DOMContentLoaded', prepareResolutionPrint);
        prepareResolutionPrint();
    })();
</script>
