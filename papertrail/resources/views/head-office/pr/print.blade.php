<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document->displayNumber() }} | PaperTrail</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #ffffff;
            color: #000000;
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.05;
        }

        .print-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 10px;
            max-width: 210mm;
            margin: 16px auto;
        }

        .print-instruction {
            margin: 0 auto 0 0;
            padding: 8px 10px;
            border: 1px solid #f0b429;
            border-radius: 6px;
            background: #fff7df;
            color: #5d4200;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.35;
        }

        .print-actions a,
        .print-actions button {
            border: 1px solid #0b2341;
            border-radius: 6px;
            background: #0b2341;
            color: #ffffff;
            padding: 8px 12px;
            font: inherit;
            text-decoration: none;
            cursor: pointer;
        }

        .print-actions a {
            background: #ffffff;
            color: #0b2341;
        }

        .pr-page {
            display: flex;
            justify-content: center;
            padding: 24px;
            background: #ffffff;
        }

        .pr-screen-wrap,
        .pr-document-wrap {
            width: 100%;
            overflow-x: auto;
            padding: 24px 0 48px;
            display: flex;
            flex-direction: column;
            align-items: center;
            background: #ffffff;
        }

        .pr-sheet {
            position: relative;
            width: 210mm;
            min-height: 297mm;
            margin: 24px auto;
            flex-shrink: 0;
            box-sizing: border-box;
            background: #ffffff;
            color: #000000;
        }

        .pr-print-preview {
            border: none;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }

        .pr-sheet__content {
            width: 100%;
            min-height: 297mm;
            padding: 1.91cm 1.78cm;
            box-sizing: border-box;
        }

        .pr-document {
            width: 620px;
            max-width: 620px;
            min-height: 880px;
            margin: 0 auto 24px;
            padding: 30px 30px;
            background: #ffffff;
            border: none;
            color: #000000;
            box-sizing: border-box;
            flex-shrink: 0;
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.05;
        }

        .pr-sheet .pr-document {
            width: 100%;
            max-width: none;
            min-height: auto;
            margin: 0;
            padding: 0;
            border: none;
            box-shadow: none;
        }

        .pr-document * {
            box-sizing: border-box;
        }

        .pr-document,
        .pr-document table,
        .pr-document th,
        .pr-document td,
        .pr-document input,
        .pr-document textarea {
            font-family: Arial, sans-serif !important;
            font-size: 11px !important;
            line-height: 1.05 !important;
        }

        .pr-cell-text {
            display: block;
            width: 100%;
            min-height: 14px;
            color: #000000;
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.2;
            white-space: pre-wrap;
            overflow: hidden;
            overflow-wrap: break-word;
            word-break: normal;
        }

        .pr-cell-text.multiline {
            min-height: 26px;
        }

        .pr-document .pr-table {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .pr-document .pr-table th,
        .pr-document .pr-table td {
            border: 1px solid #000000;
            padding: 2px 3px;
            vertical-align: top;
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.05;
            color: #000000;
            overflow-wrap: break-word;
            word-break: normal;
            white-space: normal;
        }

        .pr-table th {
            text-align: center;
            font-weight: 700;
        }

        .pr-title {
            height: 28px;
            text-align: center;
            text-transform: uppercase;
        }

        .pr-title strong {
            display: block;
            font-family: Arial, sans-serif !important;
            font-size: 18px !important;
            font-weight: 700 !important;
            letter-spacing: 0;
            line-height: 1.1 !important;
        }

        .pr-title span {
            display: block;
            margin-top: 2px;
            font-family: Arial, sans-serif !important;
            font-size: 12px !important;
            font-weight: 400 !important;
            line-height: 1.1 !important;
            text-transform: none;
        }

        .pr-form-title {
            font-family: Arial, sans-serif !important;
            font-size: 18px !important;
            font-weight: 700 !important;
            letter-spacing: normal !important;
            line-height: 1.1 !important;
            text-align: center;
        }

        .pr-form-subtitle {
            font-family: Arial, sans-serif !important;
            font-size: 12px !important;
            font-weight: 400 !important;
            line-height: 1.1 !important;
            text-align: center;
        }

        .pr-official-header {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 52mm minmax(0, 1fr);
            align-items: center;
            min-height: 18mm;
            margin-bottom: 4px;
        }

        .pr-logo-slot {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            min-height: 16mm;
            padding-right: 12px;
        }

        .pr-logo-slot img {
            width: 46px;
            height: 46px;
            object-fit: contain;
        }

        .pr-bagong-slot {
            justify-content: flex-start;
            padding-right: 0;
            padding-left: 12px;
        }

        .pr-bagong-slot img {
            width: 62px;
            height: 46px;
        }

        .pr-header-text {
            text-align: center;
            font-family: Arial, sans-serif !important;
            font-size: 11px !important;
            line-height: 1.08 !important;
        }

        .pr-header-text p {
            margin: 0;
        }

        .pr-header-text strong {
            display: block;
            font-size: 13px !important;
            font-weight: 700 !important;
            line-height: 1.08 !important;
        }

        .pr-main-title {
            margin: 4px 0 8px;
            color: #000000;
            font-family: Arial, sans-serif !important;
            font-size: 18px !important;
            font-weight: 700 !important;
            line-height: 1.05 !important;
            text-align: center;
            text-transform: uppercase;
        }

        .pr-label {
            width: 13%;
            background: #ffffff;
            font-weight: 700;
        }

        .pr-meta-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .pr-meta-table td {
            border: 1px solid #000000;
            padding: 2px 3px;
            color: #000000;
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.05;
            vertical-align: top;
            overflow-wrap: break-word;
            white-space: normal;
        }

        .pr-meta-label {
            background: #ffffff;
            font-weight: 700;
            white-space: nowrap;
        }

        .pr-meta-value {
            background: #ffffff;
            font-weight: 400;
            overflow-wrap: break-word;
            word-break: normal;
        }

        .pr-meta-blank {
            background: #ffffff;
        }

        .pr-meta-table input,
        .pr-meta-table textarea,
        .pr-meta-table div {
            width: 100%;
            border: none !important;
            border-bottom: none !important;
            outline: none !important;
            box-shadow: none !important;
            background: transparent !important;
            padding: 0 !important;
            margin: 0 !important;
            color: #000000 !important;
            font-family: Arial, sans-serif !important;
            font-size: 11px !important;
            line-height: 1.05 !important;
        }

        .pr-muted-cell {
            color: #475569;
            line-height: 1.5;
        }

        .pr-blank-cell {
            color: transparent;
        }

        .pr-empty-sheet-row td {
            height: 11px;
        }

        .pr-number-cell,
        .pr-total-label,
        .pr-total-value {
            text-align: right;
            white-space: normal;
        }

        .pr-total-label,
        .pr-total-value {
            font-weight: 700;
        }

        .pr-purpose-table td {
            min-height: 22px;
        }

        .pr-signatory-table {
            margin-top: -1px;
        }

        .pr-signatory-table th {
            width: auto;
        }

        .pr-certification-cell {
            min-height: 30px;
            font-weight: 700;
            text-align: center;
        }

        .pr-certification-cell strong,
        .pr-certification-cell span {
            display: block;
        }

        .pr-certification-cell strong {
            margin-bottom: 2px;
            font-size: 11px;
            font-weight: 700;
        }

        .pr-certification-cell span {
            font-weight: 700;
        }

        .pr-signatory-heading,
        .pr-signatory-row-label {
            font-weight: 700;
        }

        .pr-signatory-heading,
        .pr-signatory-cell {
            text-align: center;
        }

        .pr-signatory-row-label,
        .pr-signatory-cell {
            vertical-align: middle !important;
        }

        .pr-signature-line {
            height: 18px;
            vertical-align: bottom !important;
            color: #475569;
        }

        .pr-signature-cell {
            height: 34px;
        }

        .pr-inline-signature {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 30px;
        }

        .pr-inline-signature img {
            display: block;
            max-width: 100%;
            max-height: 30px;
            object-fit: contain;
        }

        .pr-inline-signature-text {
            display: block;
            color: #0f172a;
            font-family: "Brush Script MT", "Segoe Script", cursive;
            font-size: 14px;
            font-weight: 700;
            line-height: 1;
        }

        .generated {
            max-width: 720px;
            margin: 8px auto 0;
            color: #475569;
            text-align: right;
            font-size: 11px;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 1.91cm 1.78cm 1.91cm 1.78cm;
            }

            html,
            body {
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
                color: #000000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .print-actions,
            .no-print,
            .print-instruction,
            .pr-generated-footer,
            .sidebar,
            .topbar,
            .dashboard-sidebar,
            .dashboard-topbar,
            .pr-action-bar,
            .action-buttons,
            .generated {
                display: none !important;
            }

            .pr-page,
            .pr-screen-wrap,
            .pr-document-wrap {
                display: block !important;
                padding: 0 !important;
                overflow: visible !important;
                width: 100% !important;
                border: none !important;
            }

            .pr-sheet,
            .pr-print-preview {
                width: auto !important;
                min-height: auto !important;
                margin: 0 auto !important;
                border: none !important;
                background: #ffffff !important;
                box-shadow: none !important;
            }

            .pr-sheet__content {
                width: 100% !important;
                min-height: auto !important;
                padding: 0 !important;
            }

            .pr-document {
                width: 620px !important;
                min-height: auto !important;
                max-width: 620px !important;
                margin: 0 auto !important;
                padding: 30px 30px !important;
                border: none !important;
                box-shadow: none !important;
                color: #000000 !important;
                font-family: Arial, sans-serif !important;
                font-size: 11px !important;
                line-height: 1.05 !important;
            }

            .pr-sheet .pr-document {
                width: 100% !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
            }

            .pr-document .pr-table,
            .pr-document table {
                width: 100% !important;
                max-width: 100% !important;
                table-layout: fixed !important;
                border-collapse: collapse !important;
            }

            .pr-document th,
            .pr-document td,
            .pr-document input,
            .pr-document textarea {
                border: 1px solid #000000 !important;
                color: #000000 !important;
                font-family: Arial, sans-serif !important;
                font-size: 11px !important;
                line-height: 1.05 !important;
                padding: 2px 3px !important;
            }

            .pr-document input,
            .pr-document textarea {
                border: none !important;
            }

            .pr-label {
                background: #ffffff !important;
            }

            .generated {
                max-width: none;
                color: #000000 !important;
            }
        }
    </style>
</head>
<body>
    <div class="print-actions no-print">
        <p class="print-instruction">Before printing, open More settings and uncheck Headers and footers.</p>
        <a href="{{ $backUrl ?? route('head-office.pr.show', $document) }}">Back</a>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <main class="pr-document-wrap print-only-layout">
        @include('head-office.pr._preview', ['sheetClass' => 'pr-readonly-sheet', 'mode' => 'print'])
    </main>

    <p class="generated pr-generated-footer no-print">Generated by PaperTrail on {{ now()->format('M d, Y h:i A') }}</p>
</body>
</html>
