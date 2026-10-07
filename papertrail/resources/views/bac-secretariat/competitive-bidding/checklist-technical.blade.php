@extends('layouts.dashboard')

@section('title', 'Technical Checklist Requirements | PaperTrail')

@php
    $dateLabel = now()->format('F j, Y');
    $requirements = [
        'Registration Certificate from the DTI or SEC or CDA',
        "Mayor's Permit (where the principal place of business of the bidder is located)",
        'Duly signed statement of all Ongoing and Completed Government & Private Contracts including contracts awarded but not yet started',
        'Tax Clearance per Executive Order 398, Series of 2005, as finally reviewed and approved by the Bureau of Internal Revenue (BIR)',
        'PhilGEPS Registration (Platinum)',
        'Duly signed computation of Net Financial Contracting Capacity (NFCC)',
    ];
@endphp

@section('content')
    <style>
        .technical-checklist-toolbar {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 16px;
            padding: 14px 16px;
            border: 1px solid #d8dee8;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 10px 28px rgba(11, 35, 65, 0.06);
        }

        .technical-checklist-toolbar > div {
            display: grid;
            gap: 2px;
            margin-right: auto;
        }

        .technical-checklist-toolbar strong {
            color: #0b2341;
            font-size: 0.98rem;
        }

        .technical-checklist-toolbar span {
            color: #64748b;
            font-size: 0.84rem;
        }

        .technical-checklist-toolbar a,
        .technical-checklist-toolbar button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #0b2341;
            font: 800 0.84rem/1 Figtree, Arial, sans-serif;
            text-decoration: none;
            cursor: pointer;
        }

        .technical-checklist-toolbar button {
            border-color: #0b2341;
            background: #0b2341;
            color: #fff;
        }

        .technical-checklist-wrap {
            display: flex;
            justify-content: center;
            width: 100%;
            overflow: auto;
            padding: 18px 12px 42px;
            box-sizing: border-box;
        }

        .technical-checklist-page {
            flex: 0 0 auto;
            width: 210mm !important;
            max-width: 210mm !important;
            min-height: 297mm !important;
            padding: 14mm 18mm 12mm !important;
            border: 1px solid #000 !important;
            background-color: #fff;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14);
            color: #000;
            font-family: Arial, sans-serif !important;
            font-size: 7.4pt !important;
            line-height: 1.14 !important;
            box-sizing: border-box;
        }

        .technical-checklist-page * {
            box-sizing: border-box;
            font-family: Arial, sans-serif !important;
            color: #000;
        }

        .technical-checklist-page [contenteditable="true"] {
            min-height: 12px;
            outline: none;
        }

        .technical-checklist-page [contenteditable="true"]:focus {
            background: #eff6ff;
            box-shadow: inset 0 0 0 1px #2563eb;
        }

        .technical-checklist-page h1 {
            margin: 0 auto 18px;
            padding: 2px 8px;
            border: 2px solid #107c41;
            width: 93%;
            text-align: center;
            font-size: 9pt !important;
            font-weight: 700;
        }

        .technical-meta {
            display: grid;
            grid-template-columns: 84px minmax(0, 1fr) 48px 150px;
            column-gap: 8px;
            row-gap: 4px;
            align-items: end;
            margin: 0 0 18px;
        }

        .technical-label {
            font-size: 7pt !important;
            font-weight: 700;
            white-space: nowrap;
        }

        .technical-line {
            min-height: 15px;
            padding: 1px 4px;
            border-bottom: 1px solid #000;
            font-size: 7.1pt !important;
            font-weight: 700;
            text-transform: uppercase;
        }

        .technical-date {
            min-height: 15px;
            padding: 1px 4px;
            border-bottom: 1px solid transparent;
            font-size: 7.1pt !important;
        }

        .technical-form-title {
            margin: 8px 0 16px;
            text-align: center;
            font-size: 7.4pt !important;
            font-weight: 700;
        }

        .technical-budget {
            display: grid;
            grid-template-columns: 300px 130px minmax(0, 1fr);
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }

        .technical-budget div {
            font-size: 7.1pt !important;
            font-weight: 700;
        }

        .technical-security {
            display: grid;
            grid-template-columns: 176px minmax(0, 1fr);
            gap: 8px;
            align-items: start;
            margin-bottom: 4px;
        }

        .technical-security-title {
            padding-top: 2px;
            font-size: 7.1pt !important;
            font-weight: 700;
            text-transform: uppercase;
        }

        .technical-security-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 7pt !important;
        }

        .technical-security-table td {
            height: 16px;
            padding: 1px 4px;
            border: 0;
            vertical-align: top;
        }

        .technical-security-table .security-heading {
            font-weight: 700;
            text-transform: uppercase;
        }

        .technical-security-table .security-line {
            border-bottom: 1px solid #000;
        }

        .requirements-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 0;
            background: transparent;
            font-size: 7pt !important;
        }

        .requirements-table th,
        .requirements-table td {
            border: 1px solid #000;
            padding: 2px 4px;
            vertical-align: middle;
        }

        .requirements-table th {
            font-weight: 400;
        }

        .requirements-table .initial-col {
            width: 26px;
            text-align: center;
        }

        .requirements-table .requirement-col {
            width: auto;
        }

        .requirements-table .vertical-head {
            height: 84px;
            padding: 2px 1px;
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            text-align: center;
            font-size: 6pt !important;
            white-space: nowrap;
        }

        .requirements-table .initial-note {
            text-align: left;
            font-size: 6.4pt !important;
            font-weight: 400;
        }

        .requirements-table tbody td {
            height: 18px;
        }

        .technical-documents {
            margin: 14px 0 0 182px;
            font-size: 7pt !important;
        }

        .technical-documents-title {
            margin-bottom: 5px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .technical-doc-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 92px 102px;
            gap: 10px;
            align-items: center;
            min-height: 18px;
        }

        .technical-doc-line {
            min-height: 14px;
            border-bottom: 1px solid #000;
        }

        .technical-note {
            margin-top: 12px;
            font-size: 6.5pt !important;
        }

        .technical-remarks {
            display: grid;
            grid-template-columns: 62px 125px 140px minmax(0, 1fr);
            gap: 8px;
            align-items: center;
            margin-top: 16px;
            font-size: 7pt !important;
        }

        .technical-remarks .technical-line {
            text-transform: none;
            font-weight: 400;
        }

        .technical-footer-note {
            margin-top: 18px;
            font-size: 6.5pt !important;
            font-style: italic;
            line-height: 1.25 !important;
        }

        .technical-page-break {
            margin-top: 18px;
            border-bottom: 2px dashed #b9b9b9;
        }

        @media (max-width: 980px) {
            .technical-checklist-wrap {
                justify-content: flex-start;
            }
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }

            .technical-checklist-toolbar,
            .no-print {
                display: none !important;
            }

            .technical-checklist-wrap,
            .technical-checklist-wrap * {
                visibility: visible !important;
            }

            .technical-checklist-wrap {
                display: flex !important;
                justify-content: center !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
                background: #fff !important;
            }

            .technical-checklist-page {
                width: 210mm !important;
                max-width: 210mm !important;
                min-height: 297mm !important;
                margin: 0 !important;
                padding: 14mm 18mm 12mm !important;
                box-shadow: none !important;
                break-after: page;
            }
        }
    </style>

    <section class="technical-checklist-toolbar no-print">
        <div>
            <strong>Technical Checklist Requirements</strong>
            <span>Editable official technical envelope checklist.</span>
        </div>
        <a href="{{ route('bac-secretariat.competitive-bidding.menu', ['task' => 'technical-requirements-checklist']) }}">Back</a>
        <button type="button" onclick="window.print()">Print</button>
    </section>

    <div class="technical-checklist-wrap">
        <section class="technical-checklist-page">
            <h1 contenteditable="true">Checklist of Technical Envelope Requirements</h1>

            <div class="technical-meta">
                <div class="technical-label" contenteditable="true">PROJECT:</div>
                <div class="technical-line" contenteditable="true">Procurement of Materials for the Improvement of Felicity Dream Square</div>
                <div class="technical-label" contenteditable="true">DATE:</div>
                <div class="technical-date" contenteditable="true">{{ $dateLabel }}</div>

                <div></div>
                <div class="technical-line" contenteditable="true">Felicity Dream Square</div>
                <div></div>
                <div></div>

                <div class="technical-label" contenteditable="true">BIDDER:</div>
                <div class="technical-line" contenteditable="true">Canlupao Hardware and Construction Supplies</div>
                <div></div>
                <div></div>

                <div></div>
                <div class="technical-line" contenteditable="true">Supplies</div>
                <div></div>
                <div></div>
            </div>

            <div class="technical-form-title" contenteditable="true">Checklist of Bid Requirements</div>

            <div class="technical-budget">
                <div contenteditable="true">APPROVED BUDGET for the CONTRACT (ABC)</div>
                <div contenteditable="true">P 1,718,445.00</div>
                <div></div>
            </div>

            <div class="technical-security">
                <div class="technical-security-title" contenteditable="true">Technical Envelope</div>
                <table class="technical-security-table">
                    <colgroup>
                        <col style="width: 40%">
                        <col style="width: 22%">
                        <col style="width: 38%">
                    </colgroup>
                    <tbody>
                        <tr>
                            <td class="security-heading" contenteditable="true">Required Bid Security</td>
                            <td class="security-heading" contenteditable="true">Form</td>
                            <td class="security-heading" contenteditable="true">Amount</td>
                        </tr>
                        <tr>
                            <td contenteditable="true">a. Cash or Cashier's / Manager's Check</td>
                            <td contenteditable="true">2% of ABC</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td contenteditable="true">b. Bank Draft / Guarantee or Irrevocable Letter of Credit</td>
                            <td contenteditable="true">5% of ABC</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td contenteditable="true">c. Surety Bond</td>
                            <td contenteditable="true">120 Calendar days from opening</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td contenteditable="true">Validity Period</td>
                            <td colspan="2"></td>
                        </tr>
                        <tr>
                            <td contenteditable="true">Form of Bid Security</td>
                            <td colspan="2" class="security-line" contenteditable="true"></td>
                        </tr>
                        <tr>
                            <td contenteditable="true">Company</td>
                            <td colspan="2" class="security-line" contenteditable="true"></td>
                        </tr>
                        <tr>
                            <td contenteditable="true">Bid Security Amount</td>
                            <td colspan="2" class="security-line" contenteditable="true"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <table class="requirements-table">
                <colgroup>
                    @for ($column = 0; $column < 6; $column++)
                        <col class="initial-col">
                    @endfor
                    <col class="requirement-col">
                </colgroup>
                <thead>
                    <tr>
                        <th class="vertical-head" contenteditable="true">Bidder</th>
                        <th class="vertical-head" contenteditable="true">Joec Construction</th>
                        <th class="vertical-head" contenteditable="true">Canlupao</th>
                        <th class="vertical-head" contenteditable="true">Hardware</th>
                        <th class="vertical-head" contenteditable="true">Construction</th>
                        <th class="vertical-head" contenteditable="true">Supplies</th>
                        <th class="initial-note" contenteditable="true">Initial of BAC Member if document is included</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requirements as $index => $requirement)
                        <tr>
                            @for ($column = 0; $column < 6; $column++)
                                <td contenteditable="true"></td>
                            @endfor
                            <td contenteditable="true">{{ $index + 1 }}. {{ $requirement }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="technical-documents">
                <div class="technical-documents-title" contenteditable="true">Technical Documents:</div>
                <div class="technical-doc-row">
                    <div contenteditable="true">1. Bid Security Amount</div>
                    <div contenteditable="true">( &nbsp; ) Sufficient</div>
                    <div contenteditable="true">( &nbsp; ) Insufficient</div>
                </div>
                <div class="technical-doc-row">
                    <div contenteditable="true">Validity</div>
                    <div contenteditable="true">( &nbsp; ) Sufficient</div>
                    <div contenteditable="true">( &nbsp; ) Insufficient</div>
                </div>
                <div class="technical-doc-row">
                    <div contenteditable="true">2. Bid Securing Declaration</div>
                    <div class="technical-doc-line" contenteditable="true"></div>
                    <div></div>
                </div>
                <div class="technical-doc-row">
                    <div contenteditable="true">3. Omnibus Sworn Statement</div>
                    <div class="technical-doc-line" contenteditable="true"></div>
                    <div></div>
                </div>
            </div>

            <p class="technical-note" contenteditable="true">Note: Any missing or non-complying document in the above-mentioned checklist is a ground for outright rejection of the bid.</p>

            <div class="technical-remarks">
                <div class="technical-label" contenteditable="true">Remarks:</div>
                <div class="technical-line" contenteditable="true">( &nbsp; ) Complying</div>
                <div class="technical-line" contenteditable="true">( &nbsp; ) Non-Complying</div>
                <div></div>
            </div>

            <p class="technical-footer-note" contenteditable="true">On the Bid Opening day, the BAC may find it useful to use this form to keep track of the results of the preliminary examination of bids. This form, once accomplished, may be used by the BAC Secretariat as a reference in writing up the minutes of the bid opening.</p>

            <div class="technical-page-break"></div>
        </section>
    </div>
@endsection
