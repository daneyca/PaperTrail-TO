@extends('layouts.dashboard')

@section('title', 'Post Qualification Evaluation Summary Report | PaperTrail')

@php
    $reportDate = now()->format('F j, Y');
@endphp

@section('content')
    <style>
        .post-qualification-toolbar {
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

        .post-qualification-toolbar > div {
            display: grid;
            gap: 2px;
            margin-right: auto;
        }

        .post-qualification-toolbar strong {
            color: #0b2341;
            font-size: 0.98rem;
        }

        .post-qualification-toolbar a,
        .post-qualification-toolbar button {
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

        .post-qualification-toolbar button {
            border-color: #0b2341;
            background: #0b2341;
            color: #fff;
        }

        .post-qualification-wrap {
            display: grid;
            justify-content: center;
            gap: 18px;
            width: 100%;
            overflow: auto;
            padding: 18px 12px 42px;
            box-sizing: border-box;
        }

        .post-qualification-page {
            width: 210mm !important;
            min-height: 297mm !important;
            padding: 17mm 22mm 16mm !important;
            border: 1px solid #c8c8c8;
            background: #fff;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14);
            color: #000;
            font-family: "Times New Roman", Times, serif !important;
            font-size: 7.3pt !important;
            line-height: 1.14 !important;
            box-sizing: border-box;
        }

        .post-qualification-page * {
            box-sizing: border-box;
            font-family: "Times New Roman", Times, serif !important;
            color: #000;
        }

        .post-qualification-page [contenteditable="true"] {
            min-height: 9px;
            outline: none;
        }

        .post-qualification-page [contenteditable="true"]:focus {
            background: #eff6ff;
            box-shadow: inset 0 0 0 1px #2563eb;
        }

        .post-qualification-page h1 {
            margin: 0 0 15px;
            text-align: center;
            font-size: 9.5pt !important;
            font-weight: 700;
            text-decoration: underline;
        }

        .post-qualification-section {
            margin-top: 10px;
        }

        .post-qualification-section-title {
            margin: 0 0 4px;
            font-size: 7.4pt !important;
            font-weight: 700;
            text-transform: uppercase;
        }

        .post-qualification-table-title {
            margin: 2px 0;
            text-align: center;
            font-size: 6.7pt !important;
        }

        .post-qualification-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 6.9pt !important;
        }

        .post-qualification-table th,
        .post-qualification-table td {
            border: 1px solid #000;
            padding: 2px 4px;
            vertical-align: top;
        }

        .post-qualification-table th {
            text-align: center;
            font-weight: 700;
        }

        .post-qualification-table td:first-child {
            width: 18%;
            text-align: left;
        }

        .post-qualification-project-table td:first-child {
            width: 9%;
            text-align: center;
        }

        .post-qualification-project-table td:nth-child(2) {
            width: 31%;
        }

        .post-qualification-bid-table th:first-child,
        .post-qualification-bid-table td:first-child {
            width: 72%;
            text-align: left;
        }

        .post-qualification-bid-table th:last-child,
        .post-qualification-bid-table td:last-child {
            width: 28%;
            text-align: center;
        }

        .post-qualification-result-table th,
        .post-qualification-result-table td {
            text-align: center;
        }

        .post-qualification-result-table td:first-child {
            width: 36%;
            text-align: left;
        }

        .post-qualification-result-table td:last-child {
            text-align: left;
        }

        .post-qualification-signatures {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 30px 56px;
            margin-top: 18px;
        }

        .post-qualification-signature strong {
            display: block;
            margin-top: 18px;
            font-size: 7pt !important;
            text-transform: uppercase;
        }

        .post-qualification-signature span {
            display: block;
            font-size: 6.7pt !important;
        }

        .post-qualification-submitted {
            margin-top: 24px;
        }

        .post-qualification-submitted p {
            margin: 0 0 18px;
        }

        @media (max-width: 980px) {
            .post-qualification-wrap {
                justify-content: flex-start;
            }
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }

            .post-qualification-toolbar,
            .no-print {
                display: none !important;
            }

            .post-qualification-wrap {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
                background: #fff !important;
            }

            .post-qualification-page {
                width: 210mm !important;
                min-height: 297mm !important;
                margin: 0 !important;
                padding: 17mm 22mm 16mm !important;
                border: 0 !important;
                box-shadow: none !important;
                break-after: page;
            }

            .post-qualification-page:last-child {
                break-after: auto;
            }
        }
    </style>

    <section class="post-qualification-toolbar no-print">
        <div>
            <strong>Post Qualification Evaluation Summary Report</strong>
        </div>
        <a href="{{ route('bac-secretariat.competitive-bidding.menu', ['task' => 'post-qualification-evaluation']) }}">Back</a>
        <button type="button" onclick="window.print()">Print</button>
    </section>

    <div class="post-qualification-wrap">
        <section class="post-qualification-page">
            <h1 contenteditable="true">Post Qualification Evaluation Summary Report</h1>

            <div class="post-qualification-section">
                <div class="post-qualification-section-title" contenteditable="true">1.0 Project Identification</div>
                <div class="post-qualification-table-title" contenteditable="true">Table 1. Identification</div>
                <table class="post-qualification-table post-qualification-project-table">
                    <tbody>
                        <tr>
                            <td contenteditable="true">1.1</td>
                            <td contenteditable="true">Purchaser (or Employer)</td>
                            <td contenteditable="true"></td>
                        </tr>
                        <tr>
                            <td contenteditable="true">(A)</td>
                            <td contenteditable="true">Name</td>
                            <td contenteditable="true">Local Government Unit of Tomas Oppus</td>
                        </tr>
                        <tr>
                            <td contenteditable="true">(B)</td>
                            <td contenteditable="true">Address</td>
                            <td contenteditable="true">Tomas Oppus, Southern Leyte</td>
                        </tr>
                        <tr>
                            <td contenteditable="true">1.2</td>
                            <td contenteditable="true">Name of the Project</td>
                            <td contenteditable="true">Procurement of Materials for the Improvement of Felicity Dream Square</td>
                        </tr>
                        <tr>
                            <td contenteditable="true">1.3</td>
                            <td contenteditable="true">Location of the Project</td>
                            <td contenteditable="true">Felicity Dream Square</td>
                        </tr>
                        <tr>
                            <td contenteditable="true">1.4</td>
                            <td contenteditable="true">Approved Budget of Contract</td>
                            <td contenteditable="true">PHP 1,718,445.00</td>
                        </tr>
                        <tr>
                            <td contenteditable="true">1.5</td>
                            <td contenteditable="true">Method of Procurement</td>
                            <td contenteditable="true">Competitive Bidding</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="post-qualification-section">
                <div class="post-qualification-section-title" contenteditable="true">2.0 Initial Steps in the Bidding Process</div>
                <div class="post-qualification-table-title" contenteditable="true">Table 2. Initial Steps in the Bidding Process</div>
                <table class="post-qualification-table">
                    <tbody>
                        <tr>
                            <td contenteditable="true">2.1</td>
                            <td contenteditable="true">Pre-Procurement Conference<br>(A) Date of Conference</td>
                            <td contenteditable="true">N/A</td>
                        </tr>
                        <tr>
                            <td contenteditable="true">2.2</td>
                            <td contenteditable="true">Invitation to Apply for Eligibility and to Bid<br>(A) Date of first publication<br>(B) Name of newspaper<br>(C) Date of final publication<br>(D) Name of newspaper</td>
                            <td contenteditable="true">June 18, 2026<br>N/A<br>N/A<br>N/A</td>
                        </tr>
                        <tr>
                            <td contenteditable="true">2.3</td>
                            <td contenteditable="true">Eligibility Check<br>(A) Date of eligibility check received in digital/newspaper<br>(B) Date of notices sent to bidders<br>(C) Motion for Reconsideration, if any</td>
                            <td contenteditable="true">One (1)<br>N/A<br>N/A</td>
                        </tr>
                        <tr>
                            <td contenteditable="true">2.4</td>
                            <td contenteditable="true">Issuance of Bidding Documents<br>(A) Period of availability of Bid Docs</td>
                            <td contenteditable="true">June 18, 2026 - July 8, 2026</td>
                        </tr>
                        <tr>
                            <td contenteditable="true">2.5</td>
                            <td contenteditable="true">(B) Number of Bid Docs issued<br>Amendments to Bidding Docs, if any<br>(A) List all issue dates</td>
                            <td contenteditable="true">One (1)<br>N/A</td>
                        </tr>
                        <tr>
                            <td contenteditable="true">2.6</td>
                            <td contenteditable="true">Pre-bid Conference, if any<br>(A) Date of Conference<br>(B) Date of Minutes sent to bidders</td>
                            <td contenteditable="true">June 26, 2026<br>N/A</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="post-qualification-section">
                <div class="post-qualification-section-title" contenteditable="true">3.0 Submission and Opening of Bids and Preliminary Examination</div>
                <div class="post-qualification-table-title" contenteditable="true">Table 3. Bid Submission and Opening</div>
                <table class="post-qualification-table">
                    <tbody>
                        <tr>
                            <td contenteditable="true">3.1</td>
                            <td contenteditable="true">Bid Submission Deadline<br>(A) Original date, time<br>(B) Extension, if any</td>
                            <td contenteditable="true">July 8, 2026<br>N/A</td>
                        </tr>
                        <tr>
                            <td contenteditable="true">3.2</td>
                            <td contenteditable="true">Bid Opening date, time<br>Minutes of bid opening, date sent to bidders</td>
                            <td contenteditable="true">July 8, 2026, 10:00 A.M.<br>N/A</td>
                        </tr>
                        <tr>
                            <td contenteditable="true">3.4</td>
                            <td contenteditable="true">Numbers of bids submitted</td>
                            <td contenteditable="true">One (1)</td>
                        </tr>
                        <tr>
                            <td contenteditable="true">3.5</td>
                            <td contenteditable="true">Bid validity period (days or weeks)<br>(A) Originally specified<br>(B) Extensions/Revisions, if any</td>
                            <td contenteditable="true">120 calendar days</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="post-qualification-page">
            <div class="post-qualification-table-title" contenteditable="true">Table 4. Bid Prices (As Read Out)</div>
            <table class="post-qualification-table post-qualification-bid-table">
                <thead>
                    <tr>
                        <th contenteditable="true">Bidder Identification/Name</th>
                        <th contenteditable="true">Bid as Read (PHP)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td contenteditable="true">CANLUPAO HARDWARE AND CONSTRUCTION SUPPLIES</td>
                        <td contenteditable="true">1,709,293.00</td>
                    </tr>
                </tbody>
            </table>

            <div class="post-qualification-section">
                <div class="post-qualification-section-title" contenteditable="true">4.0 Bid Evaluation</div>
                <div class="post-qualification-table-title" contenteditable="true">Table 5. Correction of Bids</div>
                <table class="post-qualification-table post-qualification-bid-table">
                    <thead>
                        <tr>
                            <th contenteditable="true">Bidder Identification/Name</th>
                            <th contenteditable="true">Bid as Calculated (PHP)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td contenteditable="true">CANLUPAO HARDWARE AND CONSTRUCTION SUPPLIES</td>
                            <td contenteditable="true">1,709,293.00</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="post-qualification-section">
                <div class="post-qualification-section-title" contenteditable="true">5.0 Post Qualification</div>
                <div class="post-qualification-table-title" contenteditable="true">Table 6. Post-Qualification Report</div>
                <table class="post-qualification-table post-qualification-result-table">
                    <thead>
                        <tr>
                            <th contenteditable="true">Bidder Identification/Name</th>
                            <th contenteditable="true">Post-Qualified / Post-Disqualified</th>
                            <th contenteditable="true">Grounds</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td contenteditable="true">CANLUPAO HARDWARE AND CONSTRUCTION SUPPLIES</td>
                            <td contenteditable="true">Post-Qualified</td>
                            <td contenteditable="true">Complied with the eligibility, technical and financial requirements.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="post-qualification-signatures">
                <div class="post-qualification-signature">
                    <span contenteditable="true">Prepared by:</span>
                    <strong contenteditable="true">DARLY V. LIGTAS</strong>
                    <span contenteditable="true">TWG</span>
                </div>
                <div class="post-qualification-signature">
                    <span contenteditable="true">&nbsp;</span>
                    <strong contenteditable="true">RICHIE D. SALAN</strong>
                    <span contenteditable="true">TWG</span>
                </div>
            </div>

            <div class="post-qualification-submitted">
                <p contenteditable="true">Submitted by:</p>
                <div class="post-qualification-signature">
                    <strong contenteditable="true">ROEL J. BANO</strong>
                    <span contenteditable="true">BAC Secretariat</span>
                </div>
            </div>
        </section>
    </div>
@endsection
