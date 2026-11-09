@php
    $publishedDate = filled($data['published_on'] ?? null) ? \Illuminate\Support\Carbon::parse($data['published_on'])->format('F j, Y') : '________________';
    $closingDate = filled($data['closing_on'] ?? null) ? \Illuminate\Support\Carbon::parse($data['closing_on'])->format('F j, Y') : '________________';
    $certifiedDate = filled($data['certified_on'] ?? null) ? \Illuminate\Support\Carbon::parse($data['certified_on'])->format('jS') : '____';
    $certifiedMonthYear = filled($data['certified_on'] ?? null) ? \Illuminate\Support\Carbon::parse($data['certified_on'])->format('F, Y') : '________________';
@endphp
<style>
    .bac-head-certification { box-sizing:border-box; width:210mm; min-height:297mm; margin:0 auto; padding:18mm 22mm 20mm; background:#fff; color:#171717; font:11.5pt/1.55 Arial, sans-serif; }
    .bac-head-certification * { box-sizing:border-box; }
    .bac-head-certification .letterhead { display:grid; grid-template-columns:29mm 1fr 29mm; align-items:center; gap:4mm; min-height:29mm; text-align:center; }
    .bac-head-certification .letterhead img { max-width:29mm; max-height:27mm; object-fit:contain; }
    .bac-head-certification .letterhead img:last-child { width:29mm; height:23mm; object-fit:cover; }
    .bac-head-certification .letterhead p { margin:0; line-height:1.18; }
    .bac-head-certification .letterhead strong { font-size:12pt; }
    .bac-head-certification .office-name { margin:13mm 0 2mm; text-align:center; font-weight:700; font-size:12pt; }
    .bac-head-certification .office-rule { height:1px; background:#6ea3b5; }
    .bac-head-certification h1 { margin:24mm 0 15mm; text-align:center; font-size:16pt; font-weight:700; }
    .bac-head-certification .statement { margin:0; line-height:2.12; text-align:justify; text-indent:10mm; overflow-wrap:anywhere; }
    .bac-head-certification .statement strong { font-weight:700; }
    .bac-head-certification .done { margin:17mm 0 0; text-indent:10mm; line-height:2.1; }
    .bac-head-certification .document-field { display:inline-block; min-width:31mm; max-width:100%; margin:0 1mm; padding:1mm; border:0; border-bottom:1px solid #8198ae; border-radius:0; background:#f7faff; color:#171717; font:10.5pt Arial, sans-serif; vertical-align:baseline; }
    .bac-head-certification .document-field:focus { outline:2px solid #2a65b7; outline-offset:1px; }
    .bac-head-certification .document-field--long { width:100%; margin:1mm 0; resize:vertical; vertical-align:middle; }
    .bac-head-certification .document-field--amount { width:36mm; }
    .bac-head-certification .document-field--date { width:38mm; }
    .bac-head-certification .document-print-value { display:none; }
    .bac-head-certification .head-signature { width:72mm; margin:26mm 9mm 0 auto; text-align:center; }
    .bac-head-certification .chair-signature { width:72mm; margin:24mm 0 0 9mm; text-align:center; }
    .bac-head-certification .chair-signature .noted-label { margin:0 0 10mm; text-align:left; }
    .bac-head-certification .signature-mark { display:block; height:15mm; }
    .bac-head-certification .signature-mark img { display:block; max-height:14mm; max-width:100%; margin:auto; }
    .bac-head-certification .signer-name { display:block; font-weight:700; text-transform:uppercase; line-height:1.2; }
    .bac-head-certification .signer-title { display:block; line-height:1.3; }
    .bac-head-certification .signer-account { margin:0 0 3mm; padding:2mm; border:1px solid #c8d4e2; text-align:left; font:8pt/1.3 Arial, sans-serif; }
    .bac-head-certification .signer-account span { display:block; color:#51647a; }
    .bac-head-certification .signer-fields input { display:block; width:100%; height:9mm; margin:1mm 0; padding:1.5mm 2mm; border:1px solid #c8d4e2; border-radius:3px; background:#fff; color:#171717; text-align:center; font:10pt Arial, sans-serif; }
    .bac-head-certification .signer-fields .signer-name-print,.bac-head-certification .signer-fields .signer-title-print { display:none; }
    @media print {
        @page { size:A4 portrait; margin:0; }
        html,body { width:auto !important; min-width:0 !important; }
        .bac-head-certification,.bac-head-certification * { visibility:visible !important; }
        .bac-head-certification { margin:0 !important; width:210mm !important; min-height:297mm !important; box-shadow:none !important; break-after:avoid; }
        .bac-head-certification .signer-account,.bac-head-certification .signer-fields input { display:none !important; }
        .bac-head-certification .signer-fields .signer-name-print,.bac-head-certification .signer-fields .signer-title-print { display:block !important; }
        .bac-head-certification .document-field { display:none !important; }
        .bac-head-certification .document-print-value { display:inline !important; }
    }
</style>
<article class="bac-head-certification">
    <header>
        <div class="letterhead">
            <img src="{{ asset('images/logos/lgu-logo.png') }}" alt="Municipality of Tomas Oppus seal">
            <div>
                <p>Republic of the Philippines</p>
                <p>Province of Southern Leyte</p>
                <p><strong>MUNICIPALITY OF TOMAS OPPUS</strong></p>
                <p>-oOo-</p>
            </div>
            <img src="{{ asset('images/landing/municipality-landscape.jpg') }}" alt="Tomas Oppus municipal hall">
        </div>
        <p class="office-name">OFFICE OF THE BIDS AND AWARDS COMMITTEE</p>
        <div class="office-rule"></div>
    </header>

    <h1>CERTIFICATION</h1>

    <p class="statement">THIS IS TO CERTIFY that this office posted the Invitation to Bid for the
        @if ($editable ?? false)<textarea class="document-field document-field--long" name="project" rows="2" placeholder="Enter project" aria-label="Procurement project" maxlength="255" required>{{ $data['project'] }}</textarea><strong class="document-print-value" data-cert-preview="project"></strong>
        @else<strong>{{ $data['project'] ?? '________________' }}</strong>@endif
        with an Approved Budget for the Contract in the amount of
        @if ($editable ?? false)<textarea class="document-field document-field--long" name="budget_words" rows="2" placeholder="Enter amount in words" aria-label="Approved budget in words" maxlength="500" required>{{ $data['budget_words'] }}</textarea><strong class="document-print-value" data-cert-preview="budget_words"></strong>
        @else<strong>{{ $data['budget_words'] ?? '________________' }}</strong>@endif
        <strong>(Php @if ($editable ?? false)<input class="document-field document-field--amount" type="number" step="0.01" min="0.01" name="budget" value="{{ $data['budget'] }}" placeholder="Amount" aria-label="Approved budget amount" required><span class="document-print-value" data-cert-preview="budget"></span>@else{{ $data['budget'] ?? '________' }}@endif)</strong>
        respectively at three (3) conspicuous places reserved for posting in the premises of the municipality of Tomas Oppus, Southern Leyte, date published on
        @if ($editable ?? false)<input class="document-field document-field--date" type="date" name="published_on" value="{{ $data['published_on'] }}" aria-label="Date published" required><strong class="document-print-value" data-cert-preview="published_on"></strong>@else<strong>{{ $publishedDate }}</strong>@endif
        and closing date on
        @if ($editable ?? false)<input class="document-field document-field--date" type="date" name="closing_on" value="{{ $data['closing_on'] }}" aria-label="Closing date" required><strong class="document-print-value" data-cert-preview="closing_on"></strong>@else<strong>{{ $closingDate }}</strong>@endif
        (Bid Opening).</p>

    <p class="done">DONE this
        @if ($editable ?? false)<input class="document-field document-field--date" type="date" name="certified_on" value="{{ $data['certified_on'] }}" aria-label="Certification date" required><span class="document-print-value"><span data-cert-preview="certified_day"></span> day of <span data-cert-preview="certified_month_year"></span></span>@else<span>{{ $certifiedDate }}</span> day of <span>{{ $certifiedMonthYear }}</span>@endif
        at Tomas Oppus, Southern Leyte, Philippines.</p>

    <section class="head-signature">
        <span class="signature-mark" data-certification-signature-slot="head"></span>
        @if ($editable ?? false)
            <div class="signer-account"><span>Recipient account</span><b>{{ $signers['head']?->name ?? 'Unavailable' }} (BACSEC-002)</b></div>
            <div class="signer-fields">
                <input type="text" name="head_name" value="{{ $data['head_name'] }}" placeholder="Enter Name" aria-label="Certified by name" maxlength="120" required>
                <strong class="signer-name signer-name-print" data-cert-preview="head_name"></strong>
                <input type="text" name="head_title" value="{{ $data['head_title'] }}" placeholder="Enter position" aria-label="Certified by position" maxlength="120" required>
                <span class="signer-title signer-title-print" data-cert-preview="head_title"></span>
            </div>
        @else
            <strong class="signer-name">{{ $data['head_name'] ?? '________________' }}</strong>
            <span class="signer-title">{{ $data['head_title'] ?? 'BAC Secretariat' }}</span>
        @endif
    </section>

    <section class="chair-signature">
        <p class="noted-label">Noted by:</p>
        <span class="signature-mark" data-certification-signature-slot="chair"></span>
        @if ($editable ?? false)
            <div class="signer-account"><span>Recipient account</span><b>{{ $signers['chair']?->name ?? 'Unavailable' }} (BACCHAIR-001)</b></div>
            <div class="signer-fields">
                <input type="text" name="chair_name" value="{{ $data['chair_name'] }}" placeholder="Enter Name" aria-label="Noted by name" maxlength="120" required>
                <strong class="signer-name signer-name-print" data-cert-preview="chair_name"></strong>
                <input type="text" name="chair_title" value="{{ $data['chair_title'] }}" placeholder="Enter position" aria-label="Noted by position" maxlength="120" required>
                <span class="signer-title signer-title-print" data-cert-preview="chair_title"></span>
            </div>
        @else
            <strong class="signer-name">{{ $data['chair_name'] ?? '________________' }}</strong>
            <span class="signer-title">{{ $data['chair_title'] ?? 'BAC Chairperson' }}</span>
        @endif
    </section>
</article>
