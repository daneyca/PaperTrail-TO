@extends('layouts.dashboard')

@section('title', 'Notice of Post-Qualification | PaperTrail')

@section('content')
    <style>
        .notice-post-qualification-toolbar {
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

        .notice-post-qualification-toolbar > div {
            display: grid;
            gap: 2px;
            margin-right: auto;
        }

        .notice-post-qualification-toolbar strong {
            color: #0b2341;
            font-size: 0.98rem;
        }

        .notice-post-qualification-toolbar a,
        .notice-post-qualification-toolbar button {
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

        .notice-post-qualification-toolbar button {
            border-color: #0b2341;
            background: #0b2341;
            color: #fff;
        }

        .notice-post-qualification-wrap {
            display: grid;
            justify-content: center;
            width: 100%;
            overflow: auto;
            padding: 18px 12px 42px;
            box-sizing: border-box;
        }

        .notice-post-qualification-page {
            width: 210mm !important;
            min-height: 297mm !important;
            padding: 23mm 27mm 20mm !important;
            border: 1px solid #c8c8c8;
            background: #fff;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14);
            color: #000;
            font-family: Arial, Helvetica, sans-serif !important;
            font-size: 8.1pt !important;
            line-height: 1.35 !important;
            box-sizing: border-box;
        }

        .notice-post-qualification-page * {
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif !important;
            color: #000;
        }

        .notice-post-qualification-page [contenteditable="true"] {
            min-height: 12px;
            outline: none;
        }

        .notice-post-qualification-page [contenteditable="true"]:focus {
            background: #eff6ff;
            box-shadow: inset 0 0 0 1px #2563eb;
        }

        .notice-post-qualification-header {
            display: grid;
            grid-template-columns: 46px minmax(0, 1fr);
            gap: 9px;
            align-items: center;
            width: 275px;
            margin: 0 auto 26px;
        }

        .notice-post-qualification-logo {
            width: 42px;
            height: 42px;
            object-fit: contain;
        }

        .notice-post-qualification-heading {
            text-align: center;
            font-size: 6.8pt !important;
            line-height: 1.1 !important;
        }

        .notice-post-qualification-heading strong {
            display: block;
            font-size: 7.5pt !important;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .notice-post-qualification-date {
            margin: 0 0 24px;
        }

        .notice-post-qualification-title {
            margin: 0 0 30px;
            text-align: center;
            font-size: 8pt !important;
            font-weight: 700;
            text-transform: uppercase;
        }

        .notice-post-qualification-addressee {
            margin-bottom: 24px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .notice-post-qualification-addressee div {
            min-height: 12px;
        }

        .notice-post-qualification-body {
            display: grid;
            gap: 14px;
        }

        .notice-post-qualification-body p {
            margin: 0;
            text-align: justify;
        }

        .notice-post-qualification-closing {
            margin-top: 18px;
        }

        .notice-post-qualification-signature {
            margin-top: 30px;
            width: 180px;
        }

        .notice-post-qualification-signature strong {
            display: block;
            font-size: 8pt !important;
            text-transform: uppercase;
        }

        .notice-post-qualification-signature span {
            display: block;
            font-size: 7.2pt !important;
        }

        .notice-post-qualification-conforme {
            display: grid;
            gap: 7px;
            margin-top: 18px;
            width: 300px;
        }

        .notice-post-qualification-line {
            display: grid;
            grid-template-columns: max-content minmax(130px, 1fr);
            gap: 6px;
            align-items: end;
        }

        .notice-post-qualification-line span:last-child {
            display: block;
            min-height: 12px;
            border-bottom: 1px solid #000;
        }

        @media (max-width: 980px) {
            .notice-post-qualification-wrap {
                justify-content: flex-start;
            }
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }

            .notice-post-qualification-toolbar,
            .no-print {
                display: none !important;
            }

            .notice-post-qualification-wrap {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
                background: #fff !important;
            }

            .notice-post-qualification-page {
                width: 210mm !important;
                min-height: 297mm !important;
                margin: 0 !important;
                padding: 23mm 27mm 20mm !important;
                border: 0 !important;
                box-shadow: none !important;
            }
        }
    </style>

    <section class="notice-post-qualification-toolbar no-print">
        <div>
            <strong>Notice of Post Qualification</strong>
        </div>
        <a href="{{ route('bac-secretariat.competitive-bidding.notice-of-post-qualification') }}">Back</a>
        <button type="button" onclick="window.print()">Print</button>
    </section>

    @include('bac-secretariat.competitive-bidding.partials.signature-routing-tools', [
        'templateKey' => 'notice_of_post_qualification',
        'documentTitle' => 'Notice of Post-Qualification',
    ])

    <div class="notice-post-qualification-wrap" data-competitive-document>
        <section class="notice-post-qualification-page">
            <header class="notice-post-qualification-header">
                <img src="{{ asset('images/logos/lgu-logo.png') }}" alt="LGU Tomas Oppus seal" class="notice-post-qualification-logo">
                <div class="notice-post-qualification-heading" contenteditable="true">
                    Republic of the Philippines<br>
                    Province of Southern Leyte<br>
                    <strong>Municipality of Tomas Oppus</strong>
                </div>
            </header>

            <p class="notice-post-qualification-date" contenteditable="true">March 4, 2026</p>

            <h1 class="notice-post-qualification-title" contenteditable="true">Notice of Post-Qualification</h1>

            <div class="notice-post-qualification-addressee" contenteditable="true">
                <div data-cb-supplier-name>Beth General Merchandise</div>
                <div>Zone II, Sogod</div>
                <div>Southern Leyte</div>
            </div>

            <div class="notice-post-qualification-body">
                <p contenteditable="true">Dear Sir/Madam:</p>

                <p contenteditable="true">
                    As a result of the Post-Qualification conducted by the BAC Technical Working Group for the Procurement of Office Supplies for the Use of Different Offices with Contract Ref. PR No. 101-2025-020, we found out that the Bid Proposal you submitted during the Opening of Bids held on February 23, 2026 was the Single Calculated and Responsive Bid.
                </p>

                <p contenteditable="true">
                    As such, please submit three (3) copies of your latest Income and Business Tax Returns filed and paid through BIR Electronic Filing and Payment System (eFPS) within a non-extendable period of three (3) calendar days from the receipt of this Notice.
                </p>

                <p contenteditable="true">
                    Failure to submit the requirements within the prescribed period or any finding against the veracity of such will be a ground for forfeiture of your bid security and your disqualification for the award of the project.
                </p>

                <p contenteditable="true">Thank you.</p>

                <p class="notice-post-qualification-closing" contenteditable="true">Very truly yours,</p>
            </div>

            <div class="notice-post-qualification-signature" data-cb-signatory data-cb-slot="signed_by" data-cb-label="Signed by">
                <strong contenteditable="true" data-cb-signatory-name>Edmar T. Tambis</strong>
                @include('bac-secretariat.competitive-bidding.partials.signatory-role-select', ['selected' => 'bac_chairperson'])
            </div>

            <div class="notice-post-qualification-conforme" data-cb-signatory data-cb-slot="supplier_conforme" data-cb-label="Supplier Conforme">
                <div contenteditable="true">Conformed:</div>
                <div class="notice-post-qualification-line">
                    <span>I acknowledge receipt of this Notice on</span>
                    <span contenteditable="true"></span>
                </div>
                <div class="notice-post-qualification-line">
                    <span>Name of the Representative of the Bidder</span>
                    <span contenteditable="true" data-cb-signatory-name></span>
                </div>
                <div class="notice-post-qualification-line cb-supplier-route-row no-print">
                    <span>Routing Role</span>
                    <span>
                        @include('bac-secretariat.competitive-bidding.partials.signatory-role-select', ['selected' => 'supplier', 'onlySupplier' => true])
                    </span>
                </div>
                <div class="notice-post-qualification-line">
                    <span>Authorized Signature</span>
                    <span contenteditable="true"></span>
                </div>
            </div>
        </section>
    </div>
@endsection
