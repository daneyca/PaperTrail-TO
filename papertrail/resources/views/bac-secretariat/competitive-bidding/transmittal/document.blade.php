<style>
    .coa-letter { width:210mm; min-height:297mm; margin:0 auto; padding:18mm 18mm 20mm; box-sizing:border-box; background:#fff; color:#111; font:11pt/1.4 Arial,Helvetica,sans-serif; }
    .coa-letter * { box-sizing:border-box; }
    .coa-letter-header { display:grid; grid-template-columns:24mm 1fr 24mm; align-items:center; gap:4mm; padding-bottom:5mm; border-bottom:2px solid #3d8aa5; text-align:center; }
    .coa-letter-header img { width:23mm; height:23mm; object-fit:contain; }
    .coa-letter-header p { margin:0; }
    .coa-letter-header strong { display:block; margin:1mm 0 2mm; font-size:12pt; }
    .coa-letter-header b { display:block; margin-top:3mm; font-size:11pt; }
    .coa-letter-date { margin:10mm 0 8mm; }
    .coa-letter-recipient { margin:0 0 9mm; line-height:1.35; }
    .coa-letter-recipient strong, .coa-letter-recipient span { display:block; }
    .coa-letter-opening { margin:8mm 0 6mm; text-align:justify; text-indent:10mm; }
    .coa-letter-list { margin:0 0 8mm 10mm; padding-left:7mm; }
    .coa-letter-list li { padding-left:1mm; margin-bottom:2mm; break-inside:avoid; }
    .coa-letter-closing { margin-top:8mm; white-space:pre-line; }
    .coa-letter-signature { margin-top:15mm; break-inside:avoid; }
    .coa-letter-signature p { margin:0 0 10mm; }
    .coa-letter-signature-mark { display:flex; align-items:end; min-height:13mm; }
    .coa-letter-signature-mark img { display:block; max-width:60mm; max-height:13mm; object-fit:contain; }
    .coa-letter-signature-name { display:block; font-weight:700; text-transform:uppercase; }
    @media print {
        @page { size:A4 portrait; margin:0; }
        html, body { width:auto !important; min-width:0 !important; height:auto !important; overflow:visible !important; }
        body.dashboard-body .dashboard-shell, body.dashboard-body .dashboard-main, body.dashboard-body .dashboard-content,
        body.dashboard-body #dashboardContent { display:block !important; width:100% !important; max-width:none !important; height:auto !important; min-height:0 !important; margin:0 !important; padding:0 !important; overflow:visible !important; }
        .signature-competitive-preview-area { display:block !important; width:100% !important; max-width:none !important; overflow:visible !important; }
        .coa-letter, .coa-letter * { visibility:visible !important; opacity:1 !important; }
        .coa-letter { width:210mm !important; min-height:0 !important; margin:0 !important; box-shadow:none !important; }
    }
</style>
<article class="coa-letter">
    <header class="coa-letter-header">
        <img src="{{ asset('images/logos/lgu-logo.png') }}" alt="Municipality of Tomas Oppus seal">
        <div>
            <p>Republic of the Philippines</p>
            <p>Province of Southern Leyte</p>
            <strong>MUNICIPALITY OF TOMAS OPPUS</strong>
            <p>-oOo-</p>
            <b>OFFICE OF THE MUNICIPAL MAYOR</b>
        </div>
        <span></span>
    </header>

    <p class="coa-letter-date">{{ \Carbon\Carbon::parse($data['letter_date'])->format('F j, Y') }}</p>
    <div class="coa-letter-recipient">
        <strong>{{ $data['recipient_name'] }}</strong>
        <span>{{ $data['recipient_title'] }}</span>
        <span>{{ $data['recipient_office'] }}</span>
        @if (filled($data['recipient_address'] ?? null))<span>{{ $data['recipient_address'] }}</span>@endif
    </div>

    <p>{{ $data['salutation'] }}</p>
    <p class="coa-letter-opening">{{ $data['opening'] }} <strong>{{ $data['project_title'] }}</strong>.</p>
    <p>Attached herewith are the following documents, to wit:</p>
    <ol class="coa-letter-list">
        @foreach ($data['attachments'] as $attachment)
            <li>{{ $attachment['name'] }}@if ($attachment['supplier_name']) ({{ $attachment['supplier_name'] }})@endif</li>
        @endforeach
    </ol>
    <p class="coa-letter-closing">{{ $data['closing'] }}</p>

    <div class="coa-letter-signature">
        <p>Very truly yours,</p>
        <div class="coa-letter-signature-mark" data-coa-signature-slot="mayor"></div>
        <strong class="coa-letter-signature-name">{{ $data['mayor_name'] }}</strong>
        <span>Municipal Mayor / Head of the Procuring Entity</span>
    </div>
</article>
