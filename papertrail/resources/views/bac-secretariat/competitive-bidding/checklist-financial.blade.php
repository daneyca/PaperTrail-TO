@extends('layouts.dashboard')

@section('title', 'Financial Checklist Requirements | PaperTrail')

@php
    $dateLabel = now()->format('F j, Y');
    $title = 'Checklist of Financial Envelope Requirements';
    $projectName = 'Procurement of Materials for the Improvement of Felicity Dream Square';
    $projectLocation = 'Dream Square';
    $bidderName = 'Canlupao Hardware and Construction Supplies';
    $envelopeCopy = 'Shall contain the following information / documents and shall comply only if the bidder has complied with the requirements in the Technical Envelope.';
    $requirements = [
        'Duly signed Bid Form as prescribed.',
        'Audited Financial Statement, showing, among others, the prospective total and current assets and liabilities, "stamped" received by the BIR.',
        'Latest Income Tax Return.',
    ];
    $note = 'Note: Any missing or non-complying document in the above-mentioned checklist is a ground for outright rejection of the bid.';
    $footerNote = 'On the Bid Opening day, the BAC may find it useful to use this form to keep track of the results of the preliminary examination of bids. This form, once accomplished, may be used by the BAC Secretariat as a reference in writing up the minutes of the bid opening.';
    $bacMembers = ['EDMAR', 'ROGELIO', 'PERPETUA', 'ROLANDO', 'BREX', 'RICHIE'];
@endphp

@section('content')
    <style>
        .financial-template-toolbar,
        .financial-rich-toolbar {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            padding: 12px 14px;
            border: 1px solid #d8dee8;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 10px 28px rgba(11, 35, 65, 0.06);
        }

        .financial-template-toolbar strong {
            margin-right: auto;
            color: #0b2341;
            font-size: 0.98rem;
        }

        .financial-template-toolbar a,
        .financial-template-toolbar button,
        .financial-rich-toolbar button,
        .financial-rich-toolbar select {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #0b2341;
            font: 800 0.84rem/1 Figtree, Arial, sans-serif;
            text-decoration: none;
            cursor: pointer;
        }

        .financial-template-toolbar a,
        .financial-template-toolbar button {
            padding: 0 14px;
        }

        .financial-template-toolbar button {
            border-color: #0b2341;
            background: #0b2341;
            color: #fff;
        }

        .financial-rich-toolbar {
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .financial-rich-toolbar button {
            width: 36px;
            padding: 0;
        }

        .financial-rich-toolbar select {
            min-width: 112px;
            padding: 0 10px;
        }

        .financial-rich-toolbar .tool-separator {
            width: 1px;
            height: 28px;
            background: #d8dee8;
        }

        .financial-template-wrap {
            display: flex;
            justify-content: center;
            width: 100%;
            overflow: auto;
            padding: 18px 12px 42px;
        }

        .financial-template-page {
            flex: 0 0 auto;
            width: 210mm !important;
            max-width: 210mm !important;
            min-height: 297mm !important;
            padding: 12mm 13mm 10mm !important;
            border: 1px solid #000;
            background: #fff;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14);
            color: #000;
            font-family: Arial, sans-serif;
            font-size: 9pt !important;
            line-height: 1.25 !important;
            box-sizing: border-box;
        }

        .financial-template-page * {
            box-sizing: border-box;
            color: #000;
        }

        .financial-template-form {
            display: flex;
            flex-direction: column;
            width: 100%;
            min-height: 275mm;
        }

        .financial-rich-field {
            min-height: 16px;
            outline: none;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .financial-rich-field:focus {
            background: #eff6ff;
            box-shadow: inset 0 0 0 1px #2563eb;
        }

        .financial-title {
            margin: 0 0 10mm;
            text-align: center;
            font-size: 10.5pt !important;
            font-weight: 700;
        }

        .financial-field,
        .financial-textarea {
            width: 100%;
            border-bottom: 1px solid #000;
            background: transparent;
            font-weight: 700;
            line-height: 1.2;
        }

        .financial-textarea {
            min-height: 20px;
            padding: 2px 2px;
            text-transform: uppercase;
        }

        .financial-meta {
            display: grid;
            grid-template-columns: 100px minmax(0, 1fr) 140px;
            gap: 5px 10px;
            align-items: end;
            margin-bottom: 9mm;
        }

        .financial-label {
            font-size: 8pt !important;
            font-weight: 700;
            white-space: nowrap;
        }

        .financial-date {
            border: 0;
            background: transparent;
            font-size: 8pt !important;
            outline: none;
        }

        .financial-section-title {
            margin: 0 0 6mm;
            text-align: center;
            font-size: 9pt !important;
            font-weight: 700;
        }

        .financial-envelope-row {
            display: grid;
            grid-template-columns: 154px minmax(0, 1fr);
            gap: 10px;
            margin-bottom: 10mm;
        }

        .financial-envelope-copy {
            width: 74%;
            min-height: 44px;
            border-bottom: 1px solid #000;
            font-size: 8pt !important;
            font-weight: 700;
            line-height: 1.25 !important;
        }

        .financial-checklist-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 8.2pt !important;
        }

        .financial-checklist-table th,
        .financial-checklist-table td {
            border: 1px solid #000;
            padding: 3px 5px;
            vertical-align: middle;
        }

        .financial-checklist-table th {
            font-weight: 400;
            text-align: center;
        }

        .financial-checklist-table .initial-col {
            width: 31px;
        }

        .financial-checklist-table .requirement-col {
            width: auto;
        }

        .financial-member-name {
            height: 102px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .financial-member-name-input {
            width: 94px;
            height: 20px;
            border: 0;
            background: transparent;
            font-size: 6.7pt !important;
            line-height: 1 !important;
            text-align: center;
            text-transform: uppercase;
            outline: none;
            transform: rotate(-90deg);
            transform-origin: center;
        }

        .financial-member-name-input:focus,
        .financial-date:focus,
        .financial-initial:focus {
            background: #eff6ff;
            box-shadow: inset 0 0 0 1px #2563eb;
        }

        .financial-initial {
            width: 100%;
            height: 23px;
            border: 0;
            background: transparent;
            text-align: center;
            font-weight: 400;
            outline: none;
        }

        .financial-requirement-input {
            width: 100%;
            min-height: 24px;
            border: 0;
            background: transparent;
            font-size: 8.2pt !important;
        }

        .financial-note {
            margin: 6mm 0 0;
            font-size: 7.4pt !important;
        }

        .financial-note-editor {
            min-height: 24px;
            font-size: 7.4pt !important;
            line-height: 1.2 !important;
        }

        .financial-remarks {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-top: 5mm;
            font-size: 8pt !important;
        }

        .financial-check-option {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            min-width: 142px;
            border-bottom: 1px solid #000;
            padding-bottom: 1px;
        }

        .financial-check-option input {
            width: 10px;
            height: 10px;
            margin: 0;
        }

        .financial-footer-note {
            margin-top: auto;
            padding-top: 12mm;
            font-size: 7.4pt !important;
            font-style: italic;
            line-height: 1.25 !important;
        }

        .financial-footer-editor {
            min-height: 46px;
            font-size: 7.4pt !important;
            font-style: italic;
            line-height: 1.25 !important;
        }

        @media (max-width: 980px) {
            .financial-template-wrap {
                justify-content: flex-start;
            }
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }

            .financial-template-toolbar,
            .financial-rich-toolbar,
            .no-print {
                display: none !important;
            }

            .financial-template-wrap,
            .financial-template-wrap * {
                visibility: visible !important;
            }

            .financial-template-wrap {
                display: flex !important;
                justify-content: center !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
                background: #fff !important;
            }

            .financial-template-page {
                width: 210mm !important;
                max-width: 210mm !important;
                min-height: 297mm !important;
                margin: 0 !important;
                padding: 12mm 13mm 10mm !important;
                box-shadow: none !important;
            }

            .financial-rich-field,
            .financial-date,
            .financial-member-name-input,
            .financial-initial {
                background: transparent !important;
                box-shadow: none !important;
            }
        }
    </style>

    <section class="financial-template-toolbar no-print">
        <strong>Financial Checklist Requirements</strong>
        <a href="{{ route('bac-secretariat.competitive-bidding.menu', ['task' => 'financial-requirements-checklist']) }}">Back</a>
        <button type="button" onclick="window.print()">Print</button>
    </section>

    <section class="financial-rich-toolbar no-print" data-financial-rich-toolbar>
        <button type="button" data-rich-command="bold" title="Bold" aria-label="Bold"><strong>B</strong></button>
        <button type="button" data-rich-command="italic" title="Italic" aria-label="Italic"><em>I</em></button>
        <button type="button" data-rich-command="underline" title="Underline" aria-label="Underline"><u>U</u></button>
        <button type="button" data-rich-command="removeFormat" title="Clear formatting" aria-label="Clear formatting">Tx</button>
        <span class="tool-separator" aria-hidden="true"></span>
        <select data-rich-font-family aria-label="Font family">
            <option value="Arial">Arial</option>
            <option value="Times New Roman">Times</option>
            <option value="Calibri">Calibri</option>
            <option value="Courier New">Courier</option>
        </select>
        <select data-rich-font-size aria-label="Font size">
            <option value="6">6 pt</option>
            <option value="7">7 pt</option>
            <option value="8" selected>8 pt</option>
            <option value="9">9 pt</option>
            <option value="10">10 pt</option>
            <option value="11">11 pt</option>
            <option value="12">12 pt</option>
            <option value="14">14 pt</option>
        </select>
        <span class="tool-separator" aria-hidden="true"></span>
        <button type="button" data-rich-command="justifyLeft" title="Align left" aria-label="Align left">L</button>
        <button type="button" data-rich-command="justifyCenter" title="Align center" aria-label="Align center">C</button>
        <button type="button" data-rich-command="justifyRight" title="Align right" aria-label="Align right">R</button>
    </section>

    <div class="financial-template-wrap">
        <section class="financial-template-page">
            <form class="financial-template-form" onsubmit="return false">
                <div class="financial-rich-field financial-title" contenteditable="true" data-sync-target="financial_title_value">{!! e($title) !!}</div>
                <input id="financial_title_value" type="hidden" name="title" value="{{ $title }}">

                <div class="financial-meta">
                    <label class="financial-label" for="financial_project_name">PROJECT NAME:</label>
                    <div></div>
                    <input id="financial_date" name="financial_date" class="financial-date" type="text" value="{{ $dateLabel }}">

                    <div></div>
                    <div id="financial_project_name" class="financial-rich-field financial-textarea" contenteditable="true" data-sync-target="financial_project_name_value">{!! e($projectName) !!}</div>
                    <input id="financial_project_name_value" type="hidden" name="project_name" value="{{ $projectName }}">
                    <div></div>

                    <div></div>
                    <div class="financial-rich-field financial-field" contenteditable="true" data-sync-target="financial_project_location_value">{!! e($projectLocation) !!}</div>
                    <input id="financial_project_location_value" type="hidden" name="project_location" value="{{ $projectLocation }}">
                    <div></div>

                    <label class="financial-label" for="financial_bidder">BIDDER:</label>
                    <div id="financial_bidder" class="financial-rich-field financial-field" contenteditable="true" data-sync-target="financial_bidder_value">{!! e($bidderName) !!}</div>
                    <input id="financial_bidder_value" type="hidden" name="bidder" value="{{ $bidderName }}">
                    <div></div>
                </div>

                <div class="financial-rich-field financial-section-title" contenteditable="true" data-sync-target="financial_section_title_value">Checklist of Bid Requirements</div>
                <input id="financial_section_title_value" type="hidden" name="section_title" value="Checklist of Bid Requirements">

                <div class="financial-envelope-row">
                    <label class="financial-label" for="financial_envelope_copy">FINANCIAL ENVELOPE:</label>
                    <div id="financial_envelope_copy" class="financial-rich-field financial-envelope-copy" contenteditable="true" data-sync-target="financial_envelope_copy_value">{!! e($envelopeCopy) !!}</div>
                    <input id="financial_envelope_copy_value" type="hidden" name="financial_envelope_copy" value="{{ $envelopeCopy }}">
                </div>

                <table class="financial-checklist-table">
                    <colgroup>
                        @foreach ($bacMembers as $member)
                            <col class="initial-col">
                        @endforeach
                        <col class="requirement-col">
                    </colgroup>
                    <thead>
                        <tr>
                            @foreach ($bacMembers as $member)
                                <th>
                                    <div class="financial-member-name">
                                        <input class="financial-member-name-input" name="bac_members[]" type="text" value="{{ $member }}" aria-label="BAC member name">
                                    </div>
                                </th>
                            @endforeach
                            <th>Initial of BAC Member if document is included</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requirements as $index => $requirement)
                            <tr>
                                @foreach ($bacMembers as $member)
                                    <td>
                                        <input class="financial-initial" name="initials[{{ $index }}][]" type="text" aria-label="{{ $member }} initial for requirement {{ $index + 1 }}">
                                    </td>
                                @endforeach
                                <td>
                                    <div class="financial-rich-field financial-requirement-input" contenteditable="true" data-sync-target="financial_requirement_{{ $index }}_value">{!! e(($index + 1) . '. ' . $requirement) !!}</div>
                                    <input id="financial_requirement_{{ $index }}_value" type="hidden" name="requirements[]" value="{{ ($index + 1) . '. ' . $requirement }}">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="financial-note">
                    <div class="financial-rich-field financial-note-editor" contenteditable="true" data-sync-target="financial_note_value">{!! e($note) !!}</div>
                    <input id="financial_note_value" type="hidden" name="note" value="{{ $note }}">
                </div>

                <div class="financial-remarks">
                    <span class="financial-label">Remarks:</span>
                    <label class="financial-check-option">
                        <input type="radio" name="remarks" value="complying">
                        <span>Complying</span>
                    </label>
                    <label class="financial-check-option">
                        <input type="radio" name="remarks" value="non-complying">
                        <span>Non-Complying</span>
                    </label>
                </div>

                <div class="financial-footer-note">
                    <div class="financial-rich-field financial-footer-editor" contenteditable="true" data-sync-target="financial_footer_note_value">{!! e($footerNote) !!}</div>
                    <input id="financial_footer_note_value" type="hidden" name="footer_note" value="{{ $footerNote }}">
                </div>
            </form>
        </section>
    </div>

    <script>
        (() => {
            const toolbar = document.querySelector('[data-financial-rich-toolbar]');
            const editors = Array.from(document.querySelectorAll('.financial-rich-field[contenteditable="true"]'));
            let activeEditor = editors[0] || null;
            let savedRange = null;

            const syncEditor = (editor) => {
                const targetId = editor.dataset.syncTarget;
                const target = targetId ? document.getElementById(targetId) : null;

                if (target) {
                    target.value = editor.innerHTML.trim();
                }
            };

            const rangeBelongsToEditor = (range, editor) => {
                return editor && editor.contains(range.commonAncestorContainer);
            };

            const rememberSelection = () => {
                const selection = window.getSelection();

                if (!selection || selection.rangeCount === 0) {
                    return;
                }

                const range = selection.getRangeAt(0);
                const editor = editors.find((candidate) => rangeBelongsToEditor(range, candidate));

                if (editor) {
                    activeEditor = editor;
                    savedRange = range.cloneRange();
                }
            };

            const restoreSelection = () => {
                if (!activeEditor) {
                    activeEditor = editors[0] || null;
                }

                if (!activeEditor) {
                    return false;
                }

                activeEditor.focus();

                if (savedRange && rangeBelongsToEditor(savedRange, activeEditor)) {
                    const selection = window.getSelection();
                    selection.removeAllRanges();
                    selection.addRange(savedRange);
                }

                return true;
            };

            const convertFontSizeTags = (editor, size) => {
                editor.querySelectorAll('font[size="7"]').forEach((font) => {
                    const span = document.createElement('span');
                    span.style.fontSize = `${size}pt`;
                    span.innerHTML = font.innerHTML;
                    font.replaceWith(span);
                });
            };

            const runCommand = (command, value = null) => {
                if (!restoreSelection()) {
                    return;
                }

                document.execCommand(command, false, value);
                syncEditor(activeEditor);
                rememberSelection();
            };

            editors.forEach((editor) => {
                editor.addEventListener('focus', () => {
                    activeEditor = editor;
                    rememberSelection();
                });

                editor.addEventListener('keyup', rememberSelection);
                editor.addEventListener('mouseup', rememberSelection);
                editor.addEventListener('input', () => syncEditor(editor));
                syncEditor(editor);
            });

            document.addEventListener('selectionchange', rememberSelection);

            toolbar?.querySelectorAll('button[data-rich-command]').forEach((button) => {
                button.addEventListener('mousedown', (event) => event.preventDefault());
                button.addEventListener('click', () => runCommand(button.dataset.richCommand));
            });

            toolbar?.querySelector('[data-rich-font-family]')?.addEventListener('change', (event) => {
                runCommand('fontName', event.target.value);
            });

            toolbar?.querySelector('[data-rich-font-size]')?.addEventListener('change', (event) => {
                if (!restoreSelection()) {
                    return;
                }

                document.execCommand('fontSize', false, '7');
                convertFontSizeTags(activeEditor, event.target.value);
                syncEditor(activeEditor);
                rememberSelection();
            });
        })();
    </script>
@endsection
