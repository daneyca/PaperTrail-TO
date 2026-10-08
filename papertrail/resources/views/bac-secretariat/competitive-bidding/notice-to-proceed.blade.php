@extends('layouts.dashboard')

@section('title', 'Notice to Proceed | PaperTrail')

@section('content')
    <style>
        .notice-proceed-toolbar {
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

        .notice-proceed-toolbar > div {
            display: grid;
            gap: 2px;
            margin-right: auto;
        }

        .notice-proceed-toolbar strong {
            color: #0b2341;
            font-size: 0.98rem;
        }

        .notice-proceed-toolbar a,
        .notice-proceed-toolbar button {
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

        .notice-proceed-toolbar button {
            border-color: #0b2341;
            background: #0b2341;
            color: #fff;
        }

        .notice-proceed-wrap {
            display: grid;
            justify-content: center;
            width: 100%;
            overflow: auto;
            padding: 18px 12px 42px;
            box-sizing: border-box;
        }

        .notice-proceed-page {
            width: 210mm !important;
            min-height: 297mm !important;
            padding: 24mm 30mm 20mm !important;
            border: 1px solid #c8c8c8;
            background: #fff;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14);
            color: #000;
            font-family: Arial, Helvetica, sans-serif !important;
            font-size: 8pt !important;
            line-height: 1.34 !important;
            box-sizing: border-box;
        }

        .notice-proceed-page * {
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif !important;
            color: #000;
        }

        .notice-proceed-page [contenteditable="true"] {
            min-height: 12px;
            outline: none;
        }

        .notice-proceed-page [contenteditable="true"]:focus {
            background: #eff6ff;
            box-shadow: inset 0 0 0 1px #2563eb;
        }

        .notice-proceed-header {
            display: grid;
            grid-template-columns: 46px minmax(0, 1fr);
            gap: 9px;
            align-items: center;
            width: 282px;
            margin: 0 auto 23px;
        }

        .notice-proceed-logo {
            width: 42px;
            height: 42px;
            object-fit: contain;
        }

        .notice-proceed-heading {
            text-align: center;
            font-size: 6.8pt !important;
            line-height: 1.1 !important;
        }

        .notice-proceed-heading strong {
            display: block;
            font-size: 7.5pt !important;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .notice-proceed-title {
            margin: 0 0 32px;
            text-align: center;
            font-size: 11pt !important;
            font-weight: 700;
            text-transform: uppercase;
        }

        .notice-proceed-date {
            margin: 0 0 24px;
        }

        .notice-proceed-addressee {
            margin-bottom: 24px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .notice-proceed-addressee div {
            min-height: 12px;
        }

        .notice-proceed-body {
            display: grid;
            gap: 14px;
        }

        .notice-proceed-body p {
            margin: 0;
            text-align: justify;
        }

        .notice-proceed-closing {
            margin-top: 25px;
        }

        .notice-proceed-signature {
            margin-top: 30px;
            width: 210px;
        }

        .notice-proceed-signature strong {
            display: block;
            font-size: 8pt !important;
            text-decoration: underline;
            text-transform: uppercase;
        }

        .notice-proceed-signature span {
            display: block;
            font-size: 7.2pt !important;
        }

        .notice-proceed-conforme {
            display: grid;
            gap: 8px;
            margin-top: 22px;
            width: 355px;
        }

        .notice-proceed-line {
            display: grid;
            grid-template-columns: max-content minmax(135px, 1fr);
            gap: 6px;
            align-items: end;
        }

        .notice-proceed-line span:last-child {
            display: block;
            min-height: 12px;
            border-bottom: 1px solid #000;
        }

        @media (max-width: 980px) {
            .notice-proceed-wrap {
                justify-content: flex-start;
            }
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }

            .notice-proceed-toolbar,
            .no-print {
                display: none !important;
            }

            .notice-proceed-wrap {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
                background: #fff !important;
            }

            .notice-proceed-page {
                width: 210mm !important;
                min-height: 297mm !important;
                margin: 0 !important;
                padding: 24mm 30mm 20mm !important;
                border: 0 !important;
                box-shadow: none !important;
            }
        }
    </style>

    <section class="notice-proceed-toolbar no-print">
        <div>
            <strong>Notice to Proceed</strong>
        </div>
        <a href="{{ route('bac-secretariat.competitive-bidding.menu', ['task' => 'notice-to-proceed']) }}">Back</a>
        <button type="button" onclick="window.print()">Print</button>
    </section>

    <div class="notice-proceed-wrap">
        <section class="notice-proceed-page">
            <header class="notice-proceed-header">
                <img src="{{ asset('images/logos/lgu-logo.png') }}" alt="LGU Tomas Oppus seal" class="notice-proceed-logo">
                <div class="notice-proceed-heading" contenteditable="true">
                    Republic of the Philippines<br>
                    Province of Southern Leyte<br>
                    <strong>Municipality of Tomas Oppus</strong>
                </div>
            </header>

            <h1 class="notice-proceed-title" contenteditable="true">Notice to Proceed</h1>

            <p class="notice-proceed-date" contenteditable="true">March 17, 2026</p>

            <div class="notice-proceed-addressee" contenteditable="true">
                <div>Beth General Merchandise</div>
                <div>Zone II, Sogod</div>
                <div>Southern Leyte</div>
            </div>

            <div class="notice-proceed-body">
                <p contenteditable="true">Dear Sir/Madam:</p>

                <p contenteditable="true">
                    The attached Contract for Procurement of Office Supplies for the Use of Different Offices having been approved, notice is hereby given that work may proceed effective immediately upon receipt of this Notice but shall not be later than seven (7) calendar days from its issuance.
                </p>

                <p contenteditable="true">
                    Upon receipt of this Notice, you are responsible for performing the services under the terms and conditions of the Contract Agreement and in accordance with the implementation schedule set forth.
                </p>

                <p contenteditable="true">
                    Please acknowledge receipt and acceptance of this Notice by signing both copies in the spaces provided below. Keep one copy and return the other to the Municipality of Tomas Oppus, Southern Leyte with the address written above.
                </p>

                <p class="notice-proceed-closing" contenteditable="true">Very truly yours,</p>
            </div>

            <div class="notice-proceed-signature" contenteditable="true">
                <strong>Hon. Rod Ivan Cuares Pano</strong>
                <span>Municipal Mayor</span>
            </div>

            <div class="notice-proceed-conforme">
                <div contenteditable="true">Conforme:</div>
                <div class="notice-proceed-line">
                    <span>I acknowledge receipt of this Notice on</span>
                    <span contenteditable="true"></span>
                </div>
                <div class="notice-proceed-line">
                    <span>Name of the Representative of the Bidder</span>
                    <span contenteditable="true"></span>
                </div>
                <div class="notice-proceed-line">
                    <span>Authorized Signature</span>
                    <span contenteditable="true"></span>
                </div>
            </div>
        </section>
    </div>
@endsection
