@extends('layouts.dashboard')

@section('title', 'Abstract of Bids as Read | PaperTrail')

@php
    $todayIso = now()->toDateString();
    $todayLabel = now()->format('F j, Y');
    $roles = [
        'BAC Chairperson',
        'BAC Member',
        'BAC Member-Alternate',
        'BAC-TWG',
        'BAC Secretariat',
        'Technical Working Group',
    ];
    $signatoryUsers = collect($signatoryUsers ?? []);
    $signatoryOptions = $signatoryUsers
        ->map(fn ($user) => [
            'value' => (string) ($user->user_id ?? ''),
            'name' => (string) ($user->typed_signature_name ?: $user->name),
            'designation' => (string) ($user->signer_position ?: ($user->assignedRole?->name ?? $user->role ?? 'Signatory')),
            'label' => trim(($user->user_id ? $user->user_id.' - ' : '').($user->typed_signature_name ?: $user->name)),
        ])
        ->filter(fn ($option) => filled($option['value']))
        ->values();
    $signatories = [
        ['slot' => 'bac_chairperson', 'name' => 'EDMAR T. TAMBIS', 'role' => 'BAC Chairperson'],
        ['slot' => 'bac_member_1', 'name' => 'ROGELIO A. LAYO', 'role' => 'BAC Member'],
        ['slot' => 'bac_member_2', 'name' => 'PERPETUA D. SALAN', 'role' => 'BAC Member'],
        ['slot' => 'bac_member_alternate_1', 'name' => 'ROLANDO C. AMORA', 'role' => 'BAC Member-Alternate'],
        ['slot' => 'bac_member_alternate_2', 'name' => 'BREX P. LACIERDA', 'role' => 'BAC Member-Alternate'],
        ['slot' => 'bac_twg', 'name' => 'RICHIE D. SALAN', 'role' => 'BAC-TWG'],
    ];
    $bidRows = [
        ['label' => 'Total Amount of Bid', 'key' => 'total_amount'],
        ['label' => 'Form of Bid Security', 'key' => 'form_security'],
        ['label' => 'Bank / Company', 'key' => 'bank_company'],
        ['label' => 'Number', 'key' => 'security_number'],
        ['label' => 'Validity Period', 'key' => 'validity_period'],
        ['label' => 'Bid Security Amount', 'key' => 'security_amount'],
        ['label' => 'Required Bid Security Amount', 'key' => 'required_security_amount'],
        ['label' => 'Sufficient / Insufficient', 'key' => 'sufficiency'],
        ['label' => 'Remarks', 'key' => 'remarks'],
    ];
@endphp

@section('content')
    <style>
        @media print {
            @page {
                size: A4 landscape;
                margin: 10mm;
            }
        }
    </style>

    <form method="POST" action="{{ route('bac-secretariat.abstracts.store') }}" class="abstract-editor-form aob-as-read-form">
        @csrf
        <input type="hidden" name="abstract_number" value="{{ old('abstract_number', $abstract->abstract_number) }}">
        <input type="hidden" name="abstract_date" id="aob_abstract_date_value" value="{{ old('abstract_date', $todayIso) }}">
        <input type="hidden" name="project_name" id="aob_project_name_value">
        <input type="hidden" name="implementing_office" id="aob_implementing_office_value">
        <input type="hidden" name="abc_amount" id="aob_abc_amount_value">
        <input type="hidden" name="purpose" id="aob_purpose_value" value="Abstract of Bids as Read">
        <input type="hidden" name="supplier_1_name" id="aob_supplier_1_name_value">
        <input type="hidden" name="supplier_2_name" id="aob_supplier_2_name_value">
        <input type="hidden" name="supplier_3_name" id="aob_supplier_3_name_value">
        <input type="hidden" name="lowest_supplier_name" id="aob_lowest_supplier_name_value">
        <input type="hidden" name="lowest_total_amount" id="aob_lowest_total_amount_value">
        <input type="hidden" name="document_html" id="abstract_document_html">
        <input type="hidden" name="document_text" id="abstract_document_text">
        <input type="hidden" name="items_json" id="abstract_items_json" value="[]">
        <input type="hidden" name="suppliers_json" id="abstract_suppliers_json">
        <input type="hidden" name="awards_json" id="abstract_awards_json" value="[]">
        <input type="hidden" name="committee_json" id="abstract_committee_json">

        <section class="abstract-toolbar no-print">
            <div>
                <strong>Abstract of Bids as Read</strong>
                <span>Editable official competitive bidding template.</span>
            </div>
            <a href="{{ route('bac-secretariat.competitive-bidding.menu', ['task' => 'abstract-of-bids-as-read']) }}">Back</a>
            <button type="submit" name="save_action" value="draft">Save Draft</button>
            <button type="submit" name="save_action" value="submit" onclick="return confirm('Submit this Abstract of Bids as Read?');">Submit</button>
            <button type="button" onclick="printAobAsReadDocument()">Print</button>
        </section>

        <div class="abstract-document-wrap aob-as-read-wrap">
            <section id="aobAsReadDocument" class="abstract-a4-page abstract-document aob-as-read-document">
                <h1>Abstract of Bids as Read</h1>

                <div class="aob-meta-grid">
                    <div class="aob-meta-left">
                        <div class="aob-meta-row">
                            <span>Contract Name</span>
                            <strong class="aob-editable aob-line" contenteditable="true" data-field="project_name">Procurement of Materials for the Improvement of Felicity Dream Square</strong>
                        </div>
                        <div class="aob-meta-row">
                            <span>Contract Location</span>
                            <strong class="aob-editable aob-line" contenteditable="true" data-field="contract_location">Tomas Oppus, Southern Leyte</strong>
                        </div>
                        <div class="aob-meta-row aob-meta-spaced">
                            <span>Implementing Unit</span>
                            <strong class="aob-editable aob-line" contenteditable="true" data-field="implementing_office">Local Government Unit (LGU) Tomas Oppus, Southern Leyte</strong>
                        </div>
                        <div class="aob-meta-row">
                            <span>ABC</span>
                            <strong class="aob-editable aob-line aob-abc" contenteditable="true" data-field="abc_amount">PHP 1,718,445.00</strong>
                        </div>
                    </div>

                    <div class="aob-meta-right">
                        <div><span>Sheet</span><strong class="aob-editable" contenteditable="true" data-field="sheet_number">1</strong></div>
                        <div><span>Date</span><strong class="aob-editable" contenteditable="true" data-field="display_date">{{ $todayLabel }}</strong></div>
                        <div><span>Time</span><strong class="aob-editable" contenteditable="true" data-field="display_time">10:00 AM</strong></div>
                    </div>
                </div>

                <table class="aob-bids-table">
                    <thead>
                        <tr>
                            <th class="aob-bid-label">Name of Bidders</th>
                            <th class="aob-bid-symbol"></th>
                            <th class="aob-editable" contenteditable="true" data-field="bidder_1_name">CANLUPAO HARDWARE AND CONSTRUCTION SUPPLIES</th>
                            <th class="aob-editable" contenteditable="true" data-field="bidder_2_name">BIDDER 2</th>
                            <th class="aob-editable" contenteditable="true" data-field="bidder_3_name">BIDDER 3</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bidRows as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td class="aob-bid-symbol">{{ $row['key'] === 'total_amount' ? 'PHP' : '' }}</td>
                                <td class="aob-editable" contenteditable="true" data-bidder="1" data-bid-field="{{ $row['key'] }}">{{ $row['key'] === 'total_amount' ? '1,709,293.00' : ($row['key'] === 'sufficiency' ? 'Sufficient' : ($row['key'] === 'remarks' ? 'Passed' : 'N/A')) }}</td>
                                <td class="aob-editable" contenteditable="true" data-bidder="2" data-bid-field="{{ $row['key'] }}"></td>
                                <td class="aob-editable" contenteditable="true" data-bidder="3" data-bid-field="{{ $row['key'] }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <section class="aob-signature-grid" aria-label="BAC signatories">
                    @foreach ($signatories as $signatory)
                        <div class="aob-signatory" data-signatory-slot="{{ $signatory['slot'] }}">
                            <label class="aob-account-control no-print">
                                <span>Signature account</span>
                                <select data-signatory-account aria-label="{{ $signatory['role'] }} signature account">
                                    <option value="">Select signer account</option>
                                    @foreach ($signatoryOptions as $option)
                                        <option
                                            value="{{ $option['value'] }}"
                                            data-signer-name="{{ $option['name'] }}"
                                            data-signer-designation="{{ $option['designation'] }}"
                                        >
                                            {{ $option['label'] }} - {{ $option['designation'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <div class="aob-signature-space" aria-hidden="true"></div>
                            <input class="aob-signatory-name" type="text" value="{{ $signatory['name'] }}" data-signatory-name aria-label="{{ $signatory['role'] }} name">
                            <select class="aob-signatory-role" data-signatory-role aria-label="{{ $signatory['name'] }} role">
                                @foreach ($roles as $role)
                                    <option value="{{ $role }}" @selected($role === $signatory['role'])>{{ $role }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </section>

                <p class="aob-footnote">The BAC shall sign the "Abstract of Bids as Read" during the Preliminary Examination of Bids.</p>
            </section>
        </div>
    </form>

    <div id="abstractPrintArea" class="abstract-print-area">
        <section id="abstractPrintPaper" class="abstract-a4-page abstract-document aob-as-read-document"></section>
    </div>

    <script>
        (function () {
            const form = document.querySelector('.aob-as-read-form');
            const documentSheet = document.getElementById('aobAsReadDocument');
            const printPaper = document.getElementById('abstractPrintPaper');

            if (!form || !documentSheet) {
                return;
            }

            const text = (selector) => documentSheet.querySelector(selector)?.innerText.trim() || '';
            const money = (value) => String(value || '').replace(/[^0-9.]/g, '');

            function bidderName(index) {
                return text(`[data-field="bidder_${index}_name"]`);
            }

            function bidderValue(index, field) {
                return text(`[data-bidder="${index}"][data-bid-field="${field}"]`);
            }

            function collectSuppliers() {
                const suppliers = {};

                for (let index = 1; index <= 3; index += 1) {
                    suppliers[`supplier_${index}_name`] = bidderName(index);
                    suppliers[`supplier_${index}_total`] = bidderValue(index, 'total_amount');
                }

                return suppliers;
            }

            function collectCommittee() {
                const committee = {};

                documentSheet.querySelectorAll('[data-signatory-slot]').forEach((slot) => {
                    const key = slot.dataset.signatorySlot;
                    const accountSelect = slot.querySelector('[data-signatory-account]');
                    committee[key] = {
                        name: slot.querySelector('[data-signatory-name]')?.value.trim() || '',
                        account_code: accountSelect?.value.trim() || '',
                        designation: slot.querySelector('[data-signatory-role]')?.value.trim() || '',
                    };
                });

                return committee;
            }

            function cloneOfficialDocument() {
                const clone = documentSheet.cloneNode(true);

                clone.querySelectorAll('.no-print, [data-signatory-account]').forEach((field) => {
                    field.remove();
                });

                clone.querySelectorAll('[contenteditable]').forEach((field) => {
                    field.removeAttribute('contenteditable');
                });

                clone.querySelectorAll('input').forEach((input) => {
                    const span = document.createElement('span');
                    span.className = input.className;
                    span.textContent = input.value;
                    input.replaceWith(span);
                });

                clone.querySelectorAll('select').forEach((select) => {
                    const span = document.createElement('span');
                    span.className = select.className;
                    span.textContent = select.options[select.selectedIndex]?.textContent.trim() || select.value;
                    select.replaceWith(span);
                });

                return clone;
            }

            function syncSignerFromAccount(select) {
                const selected = select.options[select.selectedIndex];
                const slot = select.closest('[data-signatory-slot]');
                const name = selected?.dataset.signerName || '';
                const designation = selected?.dataset.signerDesignation || '';

                if (!slot || !selected?.value) {
                    return;
                }

                const nameInput = slot.querySelector('[data-signatory-name]');
                const roleSelect = slot.querySelector('[data-signatory-role]');

                if (nameInput && name) {
                    nameInput.value = name;
                }

                if (roleSelect && designation) {
                    const existing = Array.from(roleSelect.options).find((option) => option.value === designation);

                    if (existing) {
                        roleSelect.value = designation;
                    }
                }
            }

            function syncPayload() {
                const clone = cloneOfficialDocument();
                const supplierOneTotal = bidderValue(1, 'total_amount');

                document.getElementById('aob_project_name_value').value = text('[data-field="project_name"]');
                document.getElementById('aob_implementing_office_value').value = text('[data-field="implementing_office"]');
                document.getElementById('aob_abc_amount_value').value = money(text('[data-field="abc_amount"]'));
                document.getElementById('aob_supplier_1_name_value').value = bidderName(1);
                document.getElementById('aob_supplier_2_name_value').value = bidderName(2);
                document.getElementById('aob_supplier_3_name_value').value = bidderName(3);
                document.getElementById('aob_lowest_supplier_name_value').value = bidderName(1);
                document.getElementById('aob_lowest_total_amount_value').value = money(supplierOneTotal);
                document.getElementById('abstract_document_html').value = clone.outerHTML.trim();
                document.getElementById('abstract_document_text').value = documentSheet.innerText.trim();
                document.getElementById('abstract_suppliers_json').value = JSON.stringify(collectSuppliers());
                document.getElementById('abstract_committee_json').value = JSON.stringify(collectCommittee());
            }

            window.printAobAsReadDocument = function () {
                syncPayload();
                printPaper.innerHTML = '';
                printPaper.appendChild(cloneOfficialDocument());
                setTimeout(() => window.print(), 100);
            };

            form.addEventListener('submit', syncPayload);
            documentSheet.querySelectorAll('[data-signatory-account]').forEach((select) => {
                select.addEventListener('change', () => syncSignerFromAccount(select));
            });
            window.addEventListener('beforeprint', () => {
                syncPayload();
                printPaper.innerHTML = '';
                printPaper.appendChild(cloneOfficialDocument());
            });
        })();
    </script>
@endsection
