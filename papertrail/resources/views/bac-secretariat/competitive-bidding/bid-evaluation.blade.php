@extends('layouts.dashboard')

@section('title', 'Bid Evaluation Report | PaperTrail')

@php
    $openingDate = now()->format('F j, Y');
@endphp

@section('content')
    <style>
        .bid-evaluation-toolbar {
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

        .bid-evaluation-toolbar > div {
            display: grid;
            gap: 2px;
            margin-right: auto;
        }

        .bid-evaluation-toolbar strong {
            color: #0b2341;
            font-size: 0.98rem;
        }

        .bid-evaluation-toolbar a,
        .bid-evaluation-toolbar button {
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

        .bid-evaluation-toolbar button {
            border-color: #0b2341;
            background: #0b2341;
            color: #fff;
        }

        .bid-evaluation-wrap {
            display: grid;
            justify-content: center;
            gap: 18px;
            width: 100%;
            overflow: auto;
            padding: 18px 12px 42px;
            box-sizing: border-box;
        }

        .bid-evaluation-page {
            width: 210mm !important;
            min-height: 297mm !important;
            padding: 18mm 22mm 16mm !important;
            border: 1px solid #c8c8c8;
            background: #fff;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14);
            color: #000;
            font-family: "Times New Roman", Times, serif !important;
            font-size: 8.5pt !important;
            line-height: 1.23 !important;
            box-sizing: border-box;
        }

        .bid-evaluation-page * {
            box-sizing: border-box;
            font-family: "Times New Roman", Times, serif !important;
            color: #000;
        }

        .bid-evaluation-page [contenteditable="true"] {
            min-height: 10px;
            outline: none;
        }

        .bid-evaluation-page [contenteditable="true"]:focus {
            background: #eff6ff;
            box-shadow: inset 0 0 0 1px #2563eb;
        }

        .bid-evaluation-page h1 {
            margin: 0 0 22px;
            text-align: center;
            font-size: 12pt !important;
            font-weight: 700;
            text-transform: uppercase;
        }

        .bid-evaluation-section {
            display: grid;
            grid-template-columns: 34px minmax(0, 1fr);
            gap: 8px;
            margin-top: 13px;
        }

        .bid-evaluation-section-number,
        .bid-evaluation-section-title {
            font-size: 8pt !important;
            font-weight: 700;
            text-transform: uppercase;
        }

        .bid-evaluation-section-body {
            display: grid;
            gap: 8px;
        }

        .bid-evaluation-section-body p {
            margin: 0;
            text-align: justify;
        }

        .bid-evaluation-table-title {
            margin: 3px 0 2px;
            text-align: center;
            font-size: 7.4pt !important;
        }

        .bid-evaluation-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 7.8pt !important;
        }

        .bid-evaluation-table th,
        .bid-evaluation-table td {
            border: 1px solid #000;
            padding: 4px 6px;
            vertical-align: top;
        }

        .bid-evaluation-table th {
            text-align: center;
            font-weight: 700;
        }

        .bid-evaluation-table td:first-child {
            width: 20%;
            text-align: center;
        }

        .bid-evaluation-plain-table td:first-child {
            text-align: left;
        }

        .bid-evaluation-bid-table th:first-child,
        .bid-evaluation-bid-table td:first-child {
            width: 72%;
            text-align: left;
        }

        .bid-evaluation-bid-table th:last-child,
        .bid-evaluation-bid-table td:last-child {
            width: 28%;
            text-align: center;
        }

        .bid-evaluation-signatures {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 34px 54px;
            margin-top: 20px;
        }

        .bid-evaluation-signature {
            min-height: 46px;
        }

        .bid-evaluation-signature strong {
            display: block;
            margin-top: 22px;
            font-size: 8pt !important;
            text-transform: uppercase;
        }

        .bid-evaluation-signature span {
            display: block;
            font-size: 7.6pt !important;
        }

        .bid-evaluation-prepared {
            margin-top: 22px;
        }

        .bid-evaluation-prepared p {
            margin: 0 0 22px;
        }

        @media (max-width: 980px) {
            .bid-evaluation-wrap {
                justify-content: flex-start;
            }
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }

            .bid-evaluation-toolbar,
            .no-print {
                display: none !important;
            }

            .bid-evaluation-wrap {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
                background: #fff !important;
            }

            .bid-evaluation-page {
                width: 210mm !important;
                min-height: 297mm !important;
                margin: 0 !important;
                padding: 18mm 22mm 16mm !important;
                border: 0 !important;
                box-shadow: none !important;
                break-after: page;
            }

            .bid-evaluation-page:last-child {
                break-after: auto;
            }
        }
    </style>

    <section class="bid-evaluation-toolbar no-print">
        <div>
            <strong>Bid Evaluation Report</strong>
        </div>
        <a href="{{ route('bac-secretariat.competitive-bidding.menu', ['task' => 'bid-evaluation']) }}">Back</a>
        <button type="button" onclick="window.print()">Print</button>
    </section>

    <div class="bid-evaluation-wrap">
        <section class="bid-evaluation-page">
            <h1 contenteditable="true">Bid Evaluation Report</h1>

            <div class="bid-evaluation-section">
                <div class="bid-evaluation-section-number">1.0</div>
                <div class="bid-evaluation-section-body">
                    <div class="bid-evaluation-section-title" contenteditable="true">Project Identification</div>
                    <p contenteditable="true">The Local Government of Tomas Oppus through the General Fund CY 2026 allocated the amount of One Million Seven Hundred Eighteen Thousand Four Hundred Forty Five Pesos Only (PHP 1,718,445.00) being the approved budget for the contract.</p>

                    <div class="bid-evaluation-table-title" contenteditable="true">Table 1. Project Identification</div>
                    <table class="bid-evaluation-table bid-evaluation-plain-table">
                        <tbody>
                            <tr>
                                <td contenteditable="true">1.1 Procuring Entity</td>
                                <td contenteditable="true">Municipality of Tomas Oppus</td>
                            </tr>
                            <tr>
                                <td contenteditable="true">1.2 Name of Procurement Project</td>
                                <td contenteditable="true">Procurement of Materials for the Improvement of Felicity Dream Square</td>
                            </tr>
                            <tr>
                                <td contenteditable="true">1.3 Source of Funding</td>
                                <td contenteditable="true">20% CY 2026</td>
                            </tr>
                            <tr>
                                <td contenteditable="true">1.4 Approved Budget to the Contract</td>
                                <td contenteditable="true">PHP 1,718,445.00</td>
                            </tr>
                            <tr>
                                <td contenteditable="true">1.5 Method of Procurement</td>
                                <td contenteditable="true">Competitive Bidding</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bid-evaluation-section">
                <div class="bid-evaluation-section-number">2.0</div>
                <div class="bid-evaluation-section-body">
                    <div class="bid-evaluation-section-title" contenteditable="true">Initial Steps in the Bidding Process</div>
                    <p contenteditable="true">As prescribed in the revised IRR of RA 12009, the Procurement of Materials for the Improvement of Felicity Dream Square was posted in the PhilGEPS, and in three conspicuous places within the LGU. One prospective bidder submitted its Letter of Intent and secured bidding documents on the opening of bids.</p>

                    <div class="bid-evaluation-table-title" contenteditable="true">Table 2. Initial Steps in the Bidding Process</div>
                    <table class="bid-evaluation-table bid-evaluation-plain-table">
                        <tbody>
                            <tr>
                                <td contenteditable="true">2.1 Invitation to Bid</td>
                                <td contenteditable="true">Date of Posting<br>Newspaper of General Circulation / PhilGEPS Posting<br>Number of Bidding Documents Issued</td>
                                <td contenteditable="true">June 18, 2026<br>June 18, 2026 to July 8, 2026<br>1</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bid-evaluation-section">
                <div class="bid-evaluation-section-number">3.0</div>
                <div class="bid-evaluation-section-body">
                    <div class="bid-evaluation-section-title" contenteditable="true">Receipt and Opening of Bids and Preliminary Examination</div>
                    <p contenteditable="true">The CANLUPAO HARDWARE AND CONSTRUCTION SUPPLIES secured the bid documents and submitted its proposal on the specified deadline of submission/receipt of bids. Bid opening was immediately conducted as scheduled.</p>
                    <p contenteditable="true">The lone bidder passed the preliminary examination of the submitted proposal based on the Checklist attached as Annex A.</p>
                </div>
            </div>
        </section>

        <section class="bid-evaluation-page">
            <div class="bid-evaluation-table-title" contenteditable="true">Table 3. Bid Receipt and Opening</div>
            <table class="bid-evaluation-table bid-evaluation-plain-table">
                <tbody>
                    <tr>
                        <td contenteditable="true">3.1 Bid Receipt Deadline</td>
                        <td contenteditable="true">{{ $openingDate }}</td>
                    </tr>
                    <tr>
                        <td contenteditable="true">Original date and time</td>
                        <td contenteditable="true">{{ $openingDate }}</td>
                    </tr>
                    <tr>
                        <td contenteditable="true">3.2 Bid Opening date and time</td>
                        <td contenteditable="true">{{ $openingDate }}, 10:00 AM</td>
                    </tr>
                    <tr>
                        <td contenteditable="true">3.3 Number of bids received</td>
                        <td contenteditable="true">1</td>
                    </tr>
                </tbody>
            </table>

            <div class="bid-evaluation-table-title" contenteditable="true">Table 4. Bid Prices As Read Out</div>
            <table class="bid-evaluation-table bid-evaluation-bid-table">
                <thead>
                    <tr>
                        <th contenteditable="true">Bidder Identification/Name</th>
                        <th contenteditable="true">Bid as Read Amount (PHP)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td contenteditable="true">CANLUPAO HARDWARE AND CONSTRUCTION SUPPLIES</td>
                        <td contenteditable="true">1,709,293.00</td>
                    </tr>
                </tbody>
            </table>

            <div class="bid-evaluation-section">
                <div class="bid-evaluation-section-number">4.0</div>
                <div class="bid-evaluation-section-body">
                    <div class="bid-evaluation-section-title" contenteditable="true">Bid Evaluation</div>
                    <div class="bid-evaluation-section-title" contenteditable="true">4.1 Technical Capability</div>
                    <p contenteditable="true">The bidder submitted its Notarized Bid Securing Declaration.</p>
                    <div class="bid-evaluation-section-title" contenteditable="true">4.2 Financial Capability</div>
                    <p contenteditable="true">Submitted Net Financial Contracting Capacity (NFCC) computation of the eligible bidder has been assessed based on the submitted audited financial statement for the immediate preceding calendar year. Based on the evaluation, the bidder has the capacity to finance the project.</p>
                </div>
            </div>

            <div class="bid-evaluation-table-title" contenteditable="true">Table 5. Correction of Bids</div>
            <table class="bid-evaluation-table bid-evaluation-bid-table">
                <thead>
                    <tr>
                        <th contenteditable="true">Bidder Identification/Name</th>
                        <th contenteditable="true">Bid as Calculated Amount (PHP)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td contenteditable="true">CANLUPAO HARDWARE AND CONSTRUCTION SUPPLIES</td>
                        <td contenteditable="true">1,709,293.00</td>
                    </tr>
                </tbody>
            </table>

            <div class="bid-evaluation-section">
                <div class="bid-evaluation-section-number">5.0</div>
                <div class="bid-evaluation-section-body">
                    <div class="bid-evaluation-section-title" contenteditable="true">Lowest Calculated Bid</div>
                    <p contenteditable="true">After the arithmetical corrections, the bid submitted by CANLUPAO HARDWARE AND CONSTRUCTION SUPPLIES was considered as the Single Calculated Bid (SCB). As a result, CANLUPAO HARDWARE AND CONSTRUCTION SUPPLIES will be subjected to Post Qualification.</p>
                </div>
            </div>

            <div class="bid-evaluation-section">
                <div class="bid-evaluation-section-number">6.0</div>
                <div class="bid-evaluation-section-body">
                    <div class="bid-evaluation-section-title" contenteditable="true">Post Qualification</div>
                    <p contenteditable="true">Upon submission of the required documents by the SCB, the BAC conducted post qualification on July 20, 2026 to determine in a satisfactory manner whether the SCB complies with and is responsive to all requirements as specified in the bidding documents. Attached as Annex B is the Post-Qualification Evaluation Report.</p>
                </div>
            </div>
        </section>

        <section class="bid-evaluation-page">
            <div class="bid-evaluation-section">
                <div class="bid-evaluation-section-number">7.0</div>
                <div class="bid-evaluation-section-body">
                    <div class="bid-evaluation-section-title" contenteditable="true">Recommendation</div>
                    <p contenteditable="true">Based on the findings, the BAC declared CANLUPAO HARDWARE AND CONSTRUCTION SUPPLIES with the Single Calculated and Responsive Bid (SCRB) through BAC Resolution No. 2026-001 dated July 24, 2026 recommending to the Honorable Ruelito Arreza, the Head of the Procuring Entity, the award of contract to CANLUPAO HARDWARE AND CONSTRUCTION SUPPLIES in the amount of One Million Seven Hundred Nine Thousand Two Hundred Ninety-Three Pesos Only (PHP 1,709,293.00).</p>
                </div>
            </div>

            <div class="bid-evaluation-prepared">
                <p contenteditable="true">Prepared by:</p>
                <div class="bid-evaluation-signature">
                    <strong contenteditable="true">ROSE F. ALVEN</strong>
                    <span contenteditable="true">BAC Secretariat</span>
                </div>
            </div>

            <div class="bid-evaluation-prepared">
                <p contenteditable="true">Noted by:</p>
                <div class="bid-evaluation-signatures">
                    <div class="bid-evaluation-signature">
                        <strong contenteditable="true">EDMAR T. TAMBIS</strong>
                        <span contenteditable="true">BAC Chairperson</span>
                    </div>
                    <div></div>
                    <div class="bid-evaluation-signature">
                        <strong contenteditable="true">MELCHORA P. LACIERDA</strong>
                        <span contenteditable="true">BAC Vice-Chairperson</span>
                    </div>
                    <div class="bid-evaluation-signature">
                        <strong contenteditable="true">ROGELIO A. LAYO</strong>
                        <span contenteditable="true">BAC Member</span>
                    </div>
                    <div class="bid-evaluation-signature">
                        <strong contenteditable="true">PERPETUA D. SALAN</strong>
                        <span contenteditable="true">BAC Member</span>
                    </div>
                    <div class="bid-evaluation-signature">
                        <strong contenteditable="true">JESSA G. JESUS</strong>
                        <span contenteditable="true">BAC Member</span>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
