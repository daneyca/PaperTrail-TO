@extends('layouts.dashboard')

@section('title', 'BAC Resolution on Post Qualification | PaperTrail')

@section('content')
    <style>
        .bac-post-resolution-toolbar {
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

        .bac-post-resolution-toolbar > div {
            display: grid;
            gap: 2px;
            margin-right: auto;
        }

        .bac-post-resolution-toolbar strong {
            color: #0b2341;
            font-size: 0.98rem;
        }

        .bac-post-resolution-toolbar a,
        .bac-post-resolution-toolbar button {
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

        .bac-post-resolution-toolbar button {
            border-color: #0b2341;
            background: #0b2341;
            color: #fff;
        }

        .bac-post-resolution-wrap {
            display: grid;
            justify-content: center;
            gap: 18px;
            width: 100%;
            overflow: auto;
            padding: 18px 12px 42px;
            box-sizing: border-box;
        }

        .bac-post-resolution-page {
            width: 210mm !important;
            min-height: 297mm !important;
            padding: 20mm 27mm 18mm !important;
            border: 1px solid #c8c8c8;
            background: #fff;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14);
            color: #000;
            font-family: "Times New Roman", Times, serif !important;
            font-size: 8pt !important;
            line-height: 1.22 !important;
            box-sizing: border-box;
        }

        .bac-post-resolution-page * {
            box-sizing: border-box;
            font-family: "Times New Roman", Times, serif !important;
            color: #000;
        }

        .bac-post-resolution-page [contenteditable="true"] {
            min-height: 10px;
            outline: none;
        }

        .bac-post-resolution-page [contenteditable="true"]:focus {
            background: #eff6ff;
            box-shadow: inset 0 0 0 1px #2563eb;
        }

        .bac-post-resolution-header {
            margin-bottom: 16px;
            text-align: center;
            font-size: 7.1pt !important;
            line-height: 1.08 !important;
        }

        .bac-post-resolution-header strong {
            display: block;
            font-size: 7.6pt !important;
            text-transform: uppercase;
        }

        .bac-post-resolution-title {
            display: grid;
            gap: 8px;
            margin-bottom: 17px;
            text-align: center;
            font-weight: 700;
        }

        .bac-post-resolution-title h1 {
            margin: 0;
            font-size: 9.3pt !important;
            font-weight: 700;
        }

        .bac-post-resolution-title div {
            font-size: 9pt !important;
            text-transform: uppercase;
        }

        .bac-post-resolution-body {
            display: grid;
            gap: 9px;
        }

        .bac-post-resolution-body p {
            margin: 0;
            text-align: justify;
            text-indent: 28px;
        }

        .bac-post-resolution-body p.no-indent {
            text-indent: 0;
        }

        .bac-post-resolution-table {
            width: 100%;
            margin: 3px 0 6px;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 7.4pt !important;
        }

        .bac-post-resolution-table th,
        .bac-post-resolution-table td {
            border: 1px solid #000;
            padding: 4px 5px;
            text-align: center;
            vertical-align: middle;
        }

        .bac-post-resolution-table th {
            font-weight: 700;
        }

        .bac-post-resolution-table th:first-child,
        .bac-post-resolution-table td:first-child {
            width: 42%;
            text-align: left;
        }

        .bac-post-resolution-list {
            display: grid;
            gap: 7px;
            margin: 0 0 0 29px;
            padding: 0;
            list-style-position: outside;
        }

        .bac-post-resolution-list li {
            padding-left: 5px;
            text-align: justify;
        }

        .bac-post-resolution-closing {
            margin-top: 7px;
            text-align: justify;
            text-indent: 28px;
        }

        .bac-post-resolution-signatures {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 30px 62px;
            margin-top: 46px;
        }

        .bac-post-resolution-signature {
            min-height: 48px;
            text-align: center;
        }

        .bac-post-resolution-signature strong {
            display: block;
            font-size: 7.7pt !important;
            text-transform: uppercase;
        }

        .bac-post-resolution-signature span {
            display: block;
            font-size: 7.2pt !important;
        }

        .bac-post-resolution-signature.is-centered {
            grid-column: 1 / -1;
            justify-self: center;
            width: 190px;
        }

        .bac-post-resolution-approved {
            margin-top: 35px;
        }

        .bac-post-resolution-approved p {
            margin: 0 0 22px;
        }

        .bac-post-resolution-date-line {
            width: 145px;
            margin-top: 16px;
            border-top: 1px solid #000;
            font-size: 7pt !important;
        }

        @media (max-width: 980px) {
            .bac-post-resolution-wrap {
                justify-content: flex-start;
            }
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }

            .bac-post-resolution-toolbar,
            .no-print {
                display: none !important;
            }

            .bac-post-resolution-wrap {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
                background: #fff !important;
            }

            .bac-post-resolution-page {
                width: 210mm !important;
                min-height: 297mm !important;
                margin: 0 !important;
                padding: 20mm 27mm 18mm !important;
                border: 0 !important;
                box-shadow: none !important;
                break-after: page;
            }

            .bac-post-resolution-page:last-child {
                break-after: auto;
            }
        }
    </style>

    <section class="bac-post-resolution-toolbar no-print">
        <div>
            <strong>BAC Resolution on Post Qualification</strong>
        </div>
        <a href="{{ route('bac-secretariat.competitive-bidding.menu', ['task' => 'bac-resolution-on-post-qualification']) }}">Back</a>
        <button type="button" onclick="window.print()">Print</button>
    </section>

    @include('bac-secretariat.competitive-bidding.partials.signature-routing-tools', [
        'templateKey' => 'bac_resolution_on_post_qualification',
        'documentTitle' => 'BAC Resolution on Post Qualification',
    ])

    <div class="bac-post-resolution-wrap" data-competitive-document>
        <section class="bac-post-resolution-page">
            <header class="bac-post-resolution-header" contenteditable="true">
                Republic of the Philippines<br>
                Province of Southern Leyte<br>
                <strong>Municipality of Tomas Oppus</strong>
            </header>

            <div class="bac-post-resolution-title">
                <h1 contenteditable="true">BAC Resolution Declaring SCRB and Recommending Approval</h1>
                <div contenteditable="true">Resolution No. 2026-003</div>
            </div>

            <div class="bac-post-resolution-body">
                <p contenteditable="true">WHEREAS, the Municipality of Tomas Oppus posted the Invitation to Bid for the Procurement of Office Supplies for the Use of Different Offices in the PhilGEPS and at three (3) conspicuous places at the premises of the Municipal Building continuously for 7 days;</p>

                <p contenteditable="true">WHEREAS, in response to the said advertisements, seven (7) bidders purchased bid documents;</p>

                <p contenteditable="true">WHEREAS, on February 23, 2026 at 10:00 A.M. at the Municipal Conference Room, the BAC opened and read the bids submitted by the prospective bidders, as follows:</p>

                <table class="bac-post-resolution-table">
                    <thead>
                        <tr>
                            <th contenteditable="true">Name of Bidder</th>
                            <th contenteditable="true">Bid Amount (As Read)</th>
                            <th contenteditable="true">% Variance from ABC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td contenteditable="true">BETH GENERAL MERCHANDISE</td>
                            <td contenteditable="true">PHP 199,890.00</td>
                            <td contenteditable="true">0.00%</td>
                        </tr>
                    </tbody>
                </table>

                <p contenteditable="true">WHEREAS, the detailed evaluation of bids conducted on February 23, 2026 resulted in the following:</p>

                <table class="bac-post-resolution-table">
                    <thead>
                        <tr>
                            <th contenteditable="true">Name of Bidder</th>
                            <th contenteditable="true">Bid Amount (As Calculated)</th>
                            <th contenteditable="true">% Variance from ABC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td contenteditable="true">BETH GENERAL MERCHANDISE</td>
                            <td contenteditable="true">PHP 199,890.00</td>
                            <td contenteditable="true">0.00%</td>
                        </tr>
                    </tbody>
                </table>

                <p contenteditable="true">WHEREAS, upon post-qualification or careful examination, validation and verification of all the technical and financial requirements submitted by BETH GENERAL MERCHANDISE, the bidder with the Single Calculated Bid, was found to be responsive;</p>

                <p contenteditable="true">NOW, THEREFORE, We, the Members of the Bids and Awards Committee, hereby RESOLVE as it is hereby RESOLVED:</p>

                <ol class="bac-post-resolution-list">
                    <li contenteditable="true">To declare BETH GENERAL MERCHANDISE as the Single Calculated and Responsive Bid for the Procurement of Office Supplies for the Use of Different Offices;</li>
                    <li contenteditable="true">To recommend for approval by the Hon. Rod Ivan Cuares Pano, of the Municipality of Tomas Oppus, the foregoing findings and the Award of Contract to BETH GENERAL MERCHANDISE at the Contract Price of One Hundred Ninety-Nine Thousand Eight Hundred Ninety Pesos Only (PHP 199,890.00).</li>
                </ol>
            </div>
        </section>

        <section class="bac-post-resolution-page">
            <p class="bac-post-resolution-closing" contenteditable="true">RESOLVED, by the Municipality of Tomas Oppus, Southern Leyte, this 4th day of March 2026.</p>

            <div class="bac-post-resolution-signatures">
                <div class="bac-post-resolution-signature is-centered" data-cb-signatory data-cb-slot="bac_chairperson" data-cb-label="BAC Chairperson">
                    <strong contenteditable="true" data-cb-signatory-name>EDMAR T. TAMBIS</strong>
                    @include('bac-secretariat.competitive-bidding.partials.signatory-role-select', ['selected' => 'bac_chairperson'])
                </div>

                <div class="bac-post-resolution-signature" data-cb-signatory data-cb-slot="bac_vice_chairperson" data-cb-label="BAC Vice-Chairperson">
                    <strong contenteditable="true" data-cb-signatory-name>ROEL J. BANO</strong>
                    @include('bac-secretariat.competitive-bidding.partials.signatory-role-select', ['selected' => 'bac_vice_chairperson'])
                </div>

                <div class="bac-post-resolution-signature" data-cb-signatory data-cb-slot="bac_member_1" data-cb-label="BAC Member">
                    <strong contenteditable="true" data-cb-signatory-name>ROGELIO A. LAYO</strong>
                    @include('bac-secretariat.competitive-bidding.partials.signatory-role-select', ['selected' => 'bac_member'])
                </div>

                <div class="bac-post-resolution-signature" data-cb-signatory data-cb-slot="bac_member_2" data-cb-label="BAC Member">
                    <strong contenteditable="true" data-cb-signatory-name>PERPETUA D. SALAN</strong>
                    @include('bac-secretariat.competitive-bidding.partials.signatory-role-select', ['selected' => 'bac_member'])
                </div>

                <div class="bac-post-resolution-signature" data-cb-signatory data-cb-slot="bac_member_3" data-cb-label="BAC Member">
                    <strong contenteditable="true" data-cb-signatory-name>JESSA G. JESUS</strong>
                    @include('bac-secretariat.competitive-bidding.partials.signatory-role-select', ['selected' => 'bac_member'])
                </div>
            </div>

            <div class="bac-post-resolution-approved">
                <p contenteditable="true">Approved by:</p>
                <div class="bac-post-resolution-signature" style="text-align: left;" data-cb-signatory data-cb-slot="approved_by" data-cb-label="Approved by">
                    <strong contenteditable="true" data-cb-signatory-name>HON. ROD IVAN CUARES PANO</strong>
                    @include('bac-secretariat.competitive-bidding.partials.signatory-role-select', ['selected' => 'hope'])
                    <div class="bac-post-resolution-date-line" contenteditable="true">Date</div>
                </div>
            </div>
        </section>
    </div>
@endsection
