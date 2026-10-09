@extends('layouts.dashboard')

@section('title', 'Post-Qualification Report | PaperTrail')

@section('content')
    <style>
        .twg-report-toolbar {
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

        .twg-report-toolbar > div {
            display: grid;
            gap: 2px;
            margin-right: auto;
        }

        .twg-report-toolbar strong {
            color: #0b2341;
            font-size: 0.98rem;
        }

        .twg-report-toolbar a,
        .twg-report-toolbar button {
            display: inline-flex;
            min-height: 38px;
            align-items: center;
            justify-content: center;
            padding: 0 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #0b2341;
            font: 800 0.84rem/1 Figtree, Arial, sans-serif;
            text-decoration: none;
            cursor: pointer;
        }

        .twg-report-toolbar button {
            border-color: #0b2341;
            background: #0b2341;
            color: #fff;
        }

        .twg-report-wrap {
            display: grid;
            justify-content: center;
            gap: 18px;
            width: 100%;
            overflow: auto;
            padding: 18px 12px 42px;
            box-sizing: border-box;
        }

        .twg-report-page {
            width: 210mm !important;
            min-height: 297mm !important;
            padding: 18mm 24mm 16mm !important;
            border: 1px solid #c8c8c8;
            background: #fff;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14);
            color: #000;
            font-family: "Times New Roman", Times, serif !important;
            font-size: 7pt !important;
            line-height: 1.12 !important;
            box-sizing: border-box;
        }

        .twg-report-page * {
            box-sizing: border-box;
            font-family: "Times New Roman", Times, serif !important;
            color: #000;
        }

        .twg-report-page [contenteditable="true"] {
            min-height: 9px;
            outline: none;
        }

        .twg-report-page [contenteditable="true"]:focus {
            background: #eff6ff;
            box-shadow: inset 0 0 0 1px #2563eb;
        }

        .twg-report-heading {
            margin: 0 0 9px;
            text-align: center;
            font-size: 6.7pt !important;
            line-height: 1.05 !important;
        }

        .twg-report-title-box {
            margin: 0 auto 12px;
            width: 92%;
            border: 2px solid #000;
            padding: 5px 8px;
            text-align: center;
            font-size: 8.2pt !important;
            font-weight: 700;
            line-height: 1.12 !important;
            text-transform: uppercase;
        }

        .twg-report-meta {
            display: grid;
            grid-template-columns: 22px 132px minmax(0, 1fr);
            gap: 3px 8px;
            margin-bottom: 8px;
            align-items: baseline;
            font-size: 7pt !important;
        }

        .twg-report-meta strong {
            font-weight: 700;
        }

        .twg-report-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 6.35pt !important;
        }

        .twg-report-table th,
        .twg-report-table td {
            border: 1px solid #000;
            padding: 2px 3px;
            vertical-align: top;
        }

        .twg-report-table th {
            text-align: center;
            font-weight: 700;
        }

        .twg-report-table .twg-section-row td {
            font-weight: 700;
            background: #f7f7f7;
        }

        .twg-report-table .twg-requirement {
            width: 31%;
        }

        .twg-report-table .twg-party {
            width: 25%;
        }

        .twg-report-table .twg-finding {
            width: 44%;
        }

        .twg-findings {
            margin-top: 12px;
            font-size: 7pt !important;
        }

        .twg-findings-title {
            margin-bottom: 8px;
        }

        .twg-findings-options {
            display: flex;
            gap: 80px;
            margin-left: 82px;
        }

        .twg-signature-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 28px 76px;
            margin-top: 16px;
        }

        .twg-signature-block {
            min-height: 68px;
        }

        .twg-signature-block p {
            margin: 0 0 18px;
        }

        .twg-signature-line {
            display: block;
            width: 112px;
            margin-top: 10px;
            border-top: 1px solid #000;
            font-size: 6.4pt !important;
        }

        .twg-signature-block strong {
            display: block;
            font-size: 7pt !important;
            font-weight: 700;
            text-transform: uppercase;
        }

        .twg-signature-block span {
            display: block;
            font-size: 6.5pt !important;
        }

        @media (max-width: 980px) {
            .twg-report-wrap {
                justify-content: flex-start;
            }
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }

            .twg-report-toolbar,
            .no-print {
                display: none !important;
            }

            .twg-report-wrap {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
                background: #fff !important;
            }

            .twg-report-page {
                width: 210mm !important;
                min-height: 297mm !important;
                margin: 0 !important;
                padding: 18mm 24mm 16mm !important;
                border: 0 !important;
                box-shadow: none !important;
                break-after: page;
            }

            .twg-report-page:last-child {
                break-after: auto;
            }
        }
    </style>

    <section class="twg-report-toolbar no-print">
        <div>
            <strong>Post-Qualification Report</strong>
        </div>
        <a href="{{ route('bac-secretariat.competitive-bidding.menu', ['task' => 'post-qualification-report-twg']) }}">Back</a>
        <button type="button" onclick="window.print()">Print</button>
    </section>

    @include('bac-secretariat.competitive-bidding.partials.signature-routing-tools', [
        'templateKey' => 'post_qualification_report_twg',
        'documentTitle' => 'Post-Qualification Report of Technical Working Group',
    ])

    <div class="twg-report-wrap" data-competitive-document>
        <section class="twg-report-page">
            <div class="twg-report-heading" contenteditable="true">
                Republic of the Philippines<br>
                Province of Southern Leyte<br>
                MUNICIPALITY OF TOMAS OPPUS
            </div>

            <div class="twg-report-title-box" contenteditable="true">
                Procurement of Materials for the Improvement of<br>
                Felicity Dream Square<br>
                Post-Qualification Report
            </div>

            <div class="twg-report-meta">
                <span contenteditable="true">1.</span>
                <span contenteditable="true">Name of Bidder:</span>
                <strong contenteditable="true">CANLUPAO HARDWARE AND CONSTRUCTION SUPPLIES</strong>

                <span contenteditable="true">2.</span>
                <span contenteditable="true">Rank in the List of Bids:</span>
                <strong contenteditable="true">1st Rank</strong>

                <span contenteditable="true">3.</span>
                <span contenteditable="true">Bid Price:</span>
                <strong contenteditable="true">PHP 1,709,293.00</strong>

                <span contenteditable="true">4.</span>
                <span contenteditable="true">Period of Post-Qualification:</span>
                <strong contenteditable="true">July 20, 2026</strong>

                <span contenteditable="true">5.</span>
                <span contenteditable="true">Results of Post-Qualification:</span>
                <strong contenteditable="true">Single Calculated and Responsive Bid</strong>
            </div>

            <table class="twg-report-table">
                <thead>
                    <tr>
                        <th class="twg-requirement" contenteditable="true">Requirements</th>
                        <th class="twg-party" contenteditable="true">Parties consulted</th>
                        <th class="twg-finding" contenteditable="true">Findings</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="twg-section-row">
                        <td colspan="3" contenteditable="true">Eligibility Envelope</td>
                    </tr>
                    <tr>
                        <td contenteditable="true">DTI Registration / SEC Registration</td>
                        <td contenteditable="true">Department of Trade and Industry</td>
                        <td contenteditable="true">Submitted from Canlupao Hardware and Construction Supplies with registration valid until September 29, 2027 under Certificate No. 4198960.</td>
                    </tr>
                    <tr>
                        <td contenteditable="true">Business Permit</td>
                        <td contenteditable="true">Tomas Oppus, Southern Leyte</td>
                        <td contenteditable="true">Mayor's Business Permit valid until December 31, 2026.</td>
                    </tr>
                    <tr>
                        <td contenteditable="true">Ongoing and Awarded Projects</td>
                        <td contenteditable="true">Purchaser / suppliers</td>
                        <td contenteditable="true">Submitted list of current and completed contracts, including procurement references and amounts.</td>
                    </tr>
                    <tr>
                        <td contenteditable="true">Audited financial statements</td>
                        <td contenteditable="true">BIR - Revenue City</td>
                        <td contenteditable="true">Submitted audited financial statement for the immediate preceding calendar year.</td>
                    </tr>
                    <tr>
                        <td contenteditable="true">NFCC or Credit Line Certificate</td>
                        <td contenteditable="true">Bidder / financial institution</td>
                        <td contenteditable="true">Submitted NFCC computation sufficient for the approved budget for the contract.</td>
                    </tr>

                    <tr class="twg-section-row">
                        <td colspan="3" contenteditable="true">Technical Envelope</td>
                    </tr>
                    <tr>
                        <td contenteditable="true">Bid Security</td>
                        <td contenteditable="true">Bidder</td>
                        <td contenteditable="true">Submitted Bid Securing Declaration subscribed and sworn to before the authorized officer.</td>
                    </tr>
                    <tr>
                        <td contenteditable="true">Omnibus Sworn Statement</td>
                        <td contenteditable="true">Bidder</td>
                        <td contenteditable="true">Submitted and notarized Omnibus Sworn Statement with required authority and undertaking.</td>
                    </tr>

                    <tr class="twg-section-row">
                        <td colspan="3" contenteditable="true">Financial Envelope</td>
                    </tr>
                    <tr>
                        <td contenteditable="true">Financial Bid Form</td>
                        <td contenteditable="true">Bidder</td>
                        <td contenteditable="true">Submitted Financial Bid Form with quoted amount of PHP 1,709,293.00.</td>
                    </tr>
                    <tr>
                        <td contenteditable="true">Bill of Quantities / For Goods Offered Within the Philippines</td>
                        <td contenteditable="true">Bidder</td>
                        <td contenteditable="true">Submitted Bill of Quantities and schedule of requirements consistent with the bid price.</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="twg-report-page">
            <table class="twg-report-table">
                <tbody>
                    <tr class="twg-section-row">
                        <td colspan="3" contenteditable="true">Additional Requirements</td>
                    </tr>
                    <tr>
                        <td class="twg-requirement" contenteditable="true">Tax Clearance</td>
                        <td class="twg-party" contenteditable="true">BIR - Revenue Region Office No. 14, Eastern Visayas</td>
                        <td class="twg-finding" contenteditable="true">Submitted valid Tax Clearance Certificate covering the required period.</td>
                    </tr>
                    <tr>
                        <td contenteditable="true">Latest Income and Business Tax Returns</td>
                        <td contenteditable="true">BIR</td>
                        <td contenteditable="true">Submitted latest income and business tax returns for CY 2025.</td>
                    </tr>
                    <tr>
                        <td contenteditable="true">Certificate of PhilGEPS Registration</td>
                        <td contenteditable="true">Department of Budget and Management - Procurement Service / PhilGEPS</td>
                        <td contenteditable="true">Submitted PhilGEPS Registration Certificate with platinum membership status and valid posting reference.</td>
                    </tr>
                </tbody>
            </table>

            <div class="twg-findings">
                <div class="twg-findings-title" contenteditable="true">1. Findings:</div>
                <div class="twg-findings-options">
                    <span contenteditable="true">(x) Responsive</span>
                    <span contenteditable="true">( ) Non-Responsive</span>
                </div>
            </div>

            <div class="twg-signature-grid">
                <div class="twg-signature-block" data-cb-signatory data-cb-slot="prepared_by" data-cb-label="Prepared by">
                    <p contenteditable="true">Prepared by:</p>
                    <strong contenteditable="true" data-cb-signatory-name>DARLY A. LIGTAS</strong>
                    @include('bac-secretariat.competitive-bidding.partials.signatory-role-select', ['selected' => 'twg'])
                    <span class="twg-signature-line" contenteditable="true">Date</span>
                </div>

                <div class="twg-signature-block" data-cb-signatory data-cb-slot="submitted_by" data-cb-label="Submitted by">
                    <p contenteditable="true">Submitted by:</p>
                    <strong contenteditable="true" data-cb-signatory-name>ROEL J. BANO</strong>
                    @include('bac-secretariat.competitive-bidding.partials.signatory-role-select', ['selected' => 'bac_secretariat'])
                    <span class="twg-signature-line" contenteditable="true">Date</span>
                </div>

                <div class="twg-signature-block" data-cb-signatory data-cb-slot="noted_by" data-cb-label="Noted by">
                    <p contenteditable="true">Noted by:</p>
                    <strong contenteditable="true" data-cb-signatory-name>EDMAR T. TAMBIS</strong>
                    @include('bac-secretariat.competitive-bidding.partials.signatory-role-select', ['selected' => 'bac_chairperson'])
                    <span class="twg-signature-line" contenteditable="true">Date</span>
                </div>
            </div>
        </section>
    </div>
@endsection
