@extends('layouts.dashboard')

@section('title', 'Notice of Award | PaperTrail')

@section('content')
    <style>
        .notice-award-toolbar {
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

        .notice-award-toolbar > div {
            display: grid;
            gap: 2px;
            margin-right: auto;
        }

        .notice-award-toolbar strong {
            color: #0b2341;
            font-size: 0.98rem;
        }

        .notice-award-toolbar a,
        .notice-award-toolbar button {
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

        .notice-award-toolbar button {
            border-color: #0b2341;
            background: #0b2341;
            color: #fff;
        }

        .notice-award-wrap {
            display: grid;
            justify-content: center;
            width: 100%;
            overflow: auto;
            padding: 18px 12px 42px;
            box-sizing: border-box;
        }

        .notice-award-page {
            width: 210mm !important;
            min-height: 297mm !important;
            padding: 22mm 27mm 20mm !important;
            border: 1px solid #c8c8c8;
            background: #fff;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14);
            color: #000;
            font-family: Arial, Helvetica, sans-serif !important;
            font-size: 8pt !important;
            line-height: 1.32 !important;
            box-sizing: border-box;
        }

        .notice-award-page * {
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif !important;
            color: #000;
        }

        .notice-award-page [contenteditable="true"] {
            min-height: 12px;
            outline: none;
        }

        .notice-award-page [contenteditable="true"]:focus {
            background: #eff6ff;
            box-shadow: inset 0 0 0 1px #2563eb;
        }

        .notice-award-header {
            display: grid;
            grid-template-columns: 46px minmax(0, 1fr);
            gap: 9px;
            align-items: center;
            width: 275px;
            margin: 0 auto 19px;
        }

        .notice-award-logo {
            width: 42px;
            height: 42px;
            object-fit: contain;
        }

        .notice-award-heading {
            text-align: center;
            font-size: 6.8pt !important;
            line-height: 1.1 !important;
        }

        .notice-award-heading strong {
            display: block;
            font-size: 7.5pt !important;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .notice-award-title {
            margin: 0 0 31px;
            text-align: center;
            font-size: 11pt !important;
            font-weight: 700;
            text-transform: uppercase;
        }

        .notice-award-date {
            margin: 0 0 24px;
        }

        .notice-award-addressee {
            margin-bottom: 23px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .notice-award-addressee div {
            min-height: 12px;
        }

        .notice-award-body {
            display: grid;
            gap: 13px;
        }

        .notice-award-body p {
            margin: 0;
            text-align: justify;
        }

        .notice-award-closing {
            margin-top: 23px;
        }

        .notice-award-signature {
            margin-top: 30px;
            width: 210px;
        }

        .notice-award-signature strong {
            display: block;
            font-size: 8pt !important;
            text-decoration: underline;
            text-transform: uppercase;
        }

        .notice-award-signature span {
            display: block;
            font-size: 7.2pt !important;
        }

        .notice-award-conforme {
            display: grid;
            gap: 7px;
            margin-top: 22px;
            width: 330px;
        }

        .notice-award-line {
            display: grid;
            grid-template-columns: max-content minmax(130px, 1fr);
            gap: 6px;
            align-items: end;
        }

        .notice-award-line span:last-child {
            display: block;
            min-height: 12px;
            border-bottom: 1px solid #000;
        }

        @media (max-width: 980px) {
            .notice-award-wrap {
                justify-content: flex-start;
            }
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }

            .notice-award-toolbar,
            .no-print {
                display: none !important;
            }

            .notice-award-wrap {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
                background: #fff !important;
            }

            .notice-award-page {
                width: 210mm !important;
                min-height: 297mm !important;
                margin: 0 !important;
                padding: 22mm 27mm 20mm !important;
                border: 0 !important;
                box-shadow: none !important;
            }
        }
    </style>

    <section class="notice-award-toolbar no-print">
        <div>
            <strong>Notice of Award</strong>
        </div>
        <a href="{{ route('bac-secretariat.competitive-bidding.menu', ['task' => 'notice-of-award-of-contract']) }}">Back</a>
        <button type="button" onclick="window.print()">Print</button>
    </section>

    <div class="notice-award-wrap">
        <section class="notice-award-page">
            <header class="notice-award-header">
                <img src="{{ asset('images/logos/lgu-logo.png') }}" alt="LGU Tomas Oppus seal" class="notice-award-logo">
                <div class="notice-award-heading" contenteditable="true">
                    Republic of the Philippines<br>
                    Province of Southern Leyte<br>
                    <strong>Municipality of Tomas Oppus</strong>
                </div>
            </header>

            <h1 class="notice-award-title" contenteditable="true">Notice of Award</h1>

            <p class="notice-award-date" contenteditable="true">March 6, 2026</p>

            <div class="notice-award-addressee" contenteditable="true">
                <div>Beth General Merchandise</div>
                <div>Zone II, Sogod</div>
                <div>Southern Leyte</div>
            </div>

            <div class="notice-award-body">
                <p contenteditable="true">Dear Sir/Madam:</p>

                <p contenteditable="true">
                    This is to notify you that your Bid for the Procurement of Office Supplies for the Use of Different Offices for the Contract Price equivalent of Six Hundred Eighty-Four Thousand Nine Hundred Ninety-Two Pesos Only (PHP 684,992.00) is hereby accepted.
                </p>

                <p contenteditable="true">
                    You are therefore required, within ten (10) calendar days from the receipt of this Notice of Award to formally enter into Contract with us, and to submit the Performance Security in the amount and form as specified in the Bid Documents. Failure to enter into Contract or provide the Performance Security shall constitute a sufficient ground for the cancellation of this Award and the forfeiture of your Bid Security.
                </p>

                <p class="notice-award-closing" contenteditable="true">Truly yours,</p>
            </div>

            <div class="notice-award-signature" contenteditable="true">
                <strong>Hon. Rod Ivan Cuares Pano</strong>
                <span>Municipal Mayor</span>
            </div>

            <div class="notice-award-conforme">
                <div contenteditable="true">Conforme:</div>
                <div class="notice-award-line">
                    <span>I acknowledge receipt of this Notice on</span>
                    <span contenteditable="true"></span>
                </div>
                <div class="notice-award-line">
                    <span>Name of the Representative of the Bidder</span>
                    <span contenteditable="true"></span>
                </div>
                <div class="notice-award-line">
                    <span>Authorized Signature</span>
                    <span contenteditable="true"></span>
                </div>
            </div>
        </section>
    </div>
@endsection
