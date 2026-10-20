@extends('layouts.dashboard')

@section('title', 'Technical Envelope Checklist | PaperTrail')

@php
    $slots = [
        ['key' => 'member_3', 'label' => 'BAC Member', 'role' => 'bac_member', 'user_id' => 'BACMEM-003', 'name' => 'Jessa G. Resus', 'account_role' => \App\Models\User::ROLE_BAC_MEMBER],
        ['key' => 'member_1', 'label' => 'BAC Member', 'role' => 'bac_member', 'user_id' => 'BACMEM-001', 'name' => 'Perpetua D. Salan', 'account_role' => \App\Models\User::ROLE_BAC_MEMBER],
        ['key' => 'member_2', 'label' => 'BAC Member', 'role' => 'bac_member', 'user_id' => 'BACMEM-002', 'name' => 'Rogelio A. Layo', 'account_role' => \App\Models\User::ROLE_BAC_MEMBER],
        ['key' => 'vice_chairperson', 'label' => 'BAC Vice-Chairperson', 'role' => 'bac_vice_chairperson', 'user_id' => 'BACVICE-001', 'name' => 'Roel J. Bano', 'account_role' => \App\Models\User::ROLE_BAC_VICE_CHAIRPERSON],
        ['key' => 'chairperson', 'label' => 'BAC Chairperson', 'role' => 'bac_chairperson', 'user_id' => 'BACCHAIR-001', 'name' => 'Edmar T. Tambis', 'account_role' => \App\Models\User::ROLE_BAC_CHAIR],
    ];
    $requirements = [
        'Registration Certificate from the DTI or SEC or CDA',
        'Mayors Permit (wher the principal place of business of the bider is located)',
        'Duly signed statement of all Ongoing and Completed Government & Private Contracts including contracts awarded but not yet started.',
        'Tax Clearance per Executive Order 398, Series of 2005, as finally reviewed and approved by the Bureau of Internal Revenue (BIR)',
        'PHILGEPS Registration (Platinum)',
        'Duly signed computation of Net Financial Contracting Capacity (NFCC)',
    ];
    $isBacSecretariatOne = auth()->user()?->user_id === 'BACSEC-001';
@endphp

@section('content')
    <style>
        .technical-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-bottom:16px; }
        .technical-toolbar h1 { margin:0 auto 0 0; color:#0b2341; font-size:18px; }
        .technical-toolbar button, .technical-reviewer-panel select, .technical-reviewer-panel input[type=text] { min-height:36px; border:1px solid #cbd5e1; border-radius:6px; background:#fff; color:#0b2341; font:600 13px Figtree, Arial, sans-serif; }
        .technical-toolbar button { padding:0 14px; cursor:pointer; }
        .technical-toolbar button:last-child { color:#fff; background:#0b2341; border-color:#0b2341; }
        .technical-reviewer-panel { margin-bottom:18px; padding:14px; border:1px solid #d8dee8; background:#fff; }
        .technical-reviewer-panel h2 { margin:0 0 12px; font-size:15px; color:#0b2341; }
        .technical-reviewer-grid { display:grid; gap:8px; }
        .technical-reviewer-row { display:grid; grid-template-columns:180px 80px minmax(180px,1fr) minmax(160px,1fr); gap:10px; align-items:center; font-size:13px; }
        .technical-reviewer-row > span { font-weight:700; }
        .technical-reviewer-row label { display:flex; align-items:center; gap:5px; }
        .technical-reviewer-panel select, .technical-reviewer-panel input[type=text] { width:100%; padding:0 8px; }
        .technical-sheet-wrap { display:flex; justify-content:center; overflow:auto; padding:8px 8px 36px; }
        .technical-page { width:210mm; min-height:297mm; flex:none; box-sizing:border-box; padding:19mm 18mm 12mm; background:#fff; color:#000; box-shadow:0 12px 30px #0b234126; font:10pt/1.18 Calibri, Arial, sans-serif; }
        .technical-page * { box-sizing:border-box; }
        .technical-page [contenteditable=true] { outline:0; }
        .technical-page [contenteditable=true]:focus { background:#f0f6ff; outline:1px solid #7aa7ed; }
        .technical-title { margin:0 0 24px; text-align:center; font-size:13pt; font-weight:700; }
        .technical-project { display:grid; grid-template-columns:55px 1fr 38px 125px; align-items:end; column-gap:5px; }
        .technical-line { min-height:17px; border-bottom:1px solid #000; }
        .technical-line[contenteditable=true]:empty:before { content:'\00a0'; }
        .technical-project .technical-line:first-of-type { grid-column:2; }
        .technical-bidder { display:grid; grid-template-columns:55px 1fr; gap:5px; margin-top:4px; }
        .technical-bidder-lines { width:64%; }
        .technical-form-title { margin:26px 0 18px; text-align:center; font-size:11pt; font-weight:700; }
        .technical-abc { display:flex; align-items:baseline; gap:5px; margin-bottom:4px; font-weight:700; }
        .technical-abc [contenteditable] { display:inline-block; min-width:90px; min-height:16px; border-bottom:1px solid #000; }
        .technical-main { display:grid; grid-template-columns:151px minmax(0,1fr); column-gap:12px; }
        .technical-envelope-label { font-weight:700; }
        .technical-security { font-size:9pt; }
        .technical-security strong { display:block; margin-bottom:4px; }
        .technical-security-row { display:grid; grid-template-columns:minmax(0,1fr) 142px; gap:8px; min-height:17px; }
        .technical-security-row > :last-child { white-space:nowrap; }
        .technical-security-row [contenteditable] { border-bottom:1px solid #000; }
        .technical-initial-head { display:grid; grid-template-columns:17px repeat(5, 26px); margin-top:8px; height:98px; }
        .technical-initial-head > span { border:1px solid #000; border-right:0; display:flex; align-items:center; justify-content:center; overflow:hidden; }
        .technical-initial-head > span:last-child { border-right:1px solid #000; }
        .technical-initial-head .technical-rotated { writing-mode:vertical-rl; transform:rotate(180deg); font-size:8pt; white-space:nowrap; }
        .technical-initial-caption { margin:3px 0 16px; font-size:8pt; line-height:1.12; }
        .technical-requirements { margin-top:12px; }
        .technical-requirement { display:grid; grid-template-columns:151px minmax(0,1fr); column-gap:12px; align-items:start; min-height:24px; break-inside:avoid; }
        .technical-initial-grid { display:grid; grid-template-columns:17px repeat(5, 26px); height:19px; }
        .technical-initial-grid > span { border:1px solid #000; border-right:0; text-align:center; font-size:8pt; line-height:17px; overflow:hidden; white-space:nowrap; }
        .technical-initial-grid > span:last-child { border-right:1px solid #000; }
        .technical-requirement-text { padding-top:2px; font-size:9pt; }
        .technical-section-label { margin:7px 0 3px 163px; font-weight:700; font-size:9pt; }
        .technical-security-check { margin:0 0 8px 163px; font-size:9pt; }
        .technical-security-check > div { display:flex; align-items:center; gap:12px; min-height:19px; }
        .technical-security-check > div > span:first-child { width:60px; }
        .technical-security-check label { white-space:nowrap; }
        .technical-security-check input, .technical-remarks input { margin:0 3px 0 0; vertical-align:middle; accent-color:#000; }
        .technical-note { margin:14px 0 12px; font-size:8pt; }
        .technical-remarks { display:flex; gap:22px; align-items:center; font-size:9pt; }
        .technical-footer { margin-top:32px; font-size:9pt; line-height:1.25; }
        @media (max-width:800px) { .technical-reviewer-row { grid-template-columns:1fr 80px; } .technical-reviewer-row select, .technical-reviewer-row input[type=text] { grid-column:span 2; } }
        @media print { @page { size:A4 portrait; margin:19mm 18mm 12mm; } body { background:#fff !important; } .technical-toolbar, .technical-reviewer-panel, .cb-routing-actions, .cb-routing-dialog, .no-print { display:none !important; } .technical-sheet-wrap { display:block; padding:0; overflow:visible; } .technical-page { margin:0; padding:0; box-shadow:none; width:auto; min-height:0; } }
    </style>

    <div class="technical-toolbar no-print">
        <h1>Checklist of Technical Envelope Requirements</h1>
        <button type="button" data-technical-print>Print</button>
        <button type="button" data-technical-save>Save Word</button>
    </div>

    @if ($isBacSecretariatOne)
        <div class="technical-reviewer-panel no-print">
            <h2>BAC personnel at bid opening</h2>
            <div class="technical-reviewer-grid">
                @foreach ($slots as $slot)
                    @php
                        $accounts = $bacSigners->where('role', $slot['account_role']);
                        $defaultAccount = $accounts->firstWhere('user_id', $slot['user_id']);
                    @endphp
                    <div class="technical-reviewer-row" data-reviewer-slot="{{ $slot['key'] }}" data-cb-signatory-entry>
                        <span>{{ $slot['label'] }}</span>
                        <label><input type="checkbox" data-cb-participating @checked($defaultAccount)> Present</label>
                        <select aria-label="{{ $slot['label'] }} account" data-reviewer-account>
                            <option value="">Select account</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" data-name="{{ $account->name }}" @selected($defaultAccount?->id === $account->id)>{{ $account->user_id }} - {{ $account->name }}</option>
                            @endforeach
                        </select>
                        <input type="text" aria-label="{{ $slot['label'] }} printed name" data-reviewer-name value="{{ $defaultAccount?->name ?: $slot['name'] }}">
                        <input type="hidden" data-cb-recipient-user-id value="{{ $defaultAccount?->id }}">
                        <span hidden data-cb-signatory data-cb-slot="{{ $slot['key'] }}" data-cb-label="{{ $slot['label'] }}">
                            <span data-cb-signatory-name>{{ $defaultAccount?->name ?: $slot['name'] }}</span>
                            <select data-cb-signatory-role aria-label="Role"><option value="{{ $slot['role'] }}" selected>{{ $slot['label'] }}</option></select>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="technical-sheet-wrap">
        <section class="technical-page" data-competitive-document>
            <h2 class="technical-title">Checklist of Technical Envelope Requirements</h2>
            <div class="technical-project">
                <strong>PROJECT:</strong><span class="technical-line" data-checklist-project contenteditable="true"></span>
                <strong>DATE:</strong><span class="technical-line" contenteditable="true">{{ now()->format('F j, Y') }}</span>
            </div>
            <div class="technical-bidder"><strong>BIDDER:</strong><div class="technical-bidder-lines" data-checklist-bidder><div class="technical-line" contenteditable="true"></div><div class="technical-line" contenteditable="true"></div><div class="technical-line" contenteditable="true"></div></div></div>
            <h3 class="technical-form-title">Checklist of Bid Requirements</h3>
            <div class="technical-abc">APPROVED BUDGET for the CONTRACT (ABC) <span>PHP</span><span data-checklist-abc contenteditable="true"></span></div>
            <div class="technical-main">
                <div>
                    <div class="technical-envelope-label">TECHNICAL ENVELOPE</div>
                    <div class="technical-initial-head"><span></span>@foreach ($slots as $slot)<span><span class="technical-rotated" data-printed-reviewer="{{ $slot['key'] }}">{{ strtoupper(substr($slot['name'], 0, 1).'. '.collect(explode(' ', $slot['name']))->last()) }}</span></span>@endforeach</div>
                    <div class="technical-initial-caption">Initial of BAC Member If<br>document is included</div>
                </div>
                <div class="technical-security">
                    <strong>REQUIRED BID SECURITY</strong>
                    <div class="technical-security-row"><span>Form</span><span>AMOUNT</span></div>
                    <div class="technical-security-row"><span>a. Cash or Cashier's /Manager's Check</span><span>2% of ABC</span></div>
                    <div class="technical-security-row"><span>b. Bank Draft/Guarantee or Irrevocable Letter of Credit</span><span></span></div>
                    <div class="technical-security-row"><span>c. Surety Bond</span><span>5% of ABC</span></div>
                    <div class="technical-security-row"><span>Validity Period</span><span>120 Calendar days from opening</span></div>
                    <div class="technical-security-row"><span>Form of Bid Security</span><span contenteditable="true"></span></div>
                    <div class="technical-security-row"><span>Company</span><span contenteditable="true"></span></div>
                    <div class="technical-security-row"><span>Bid Security Amount</span><span contenteditable="true"></span></div>
                </div>
            </div>
            <div class="technical-requirements">
                @foreach ($requirements as $index => $requirement)
                    <div class="technical-requirement" data-requirement-row>
                        <div class="technical-initial-grid"><span></span>@foreach ($slots as $slot)<span class="initial-cell" data-initial-slot="{{ $slot['key'] }}" contenteditable="true"></span>@endforeach</div>
                        <div class="technical-requirement-text">{{ $index + 1 }}. {{ $requirement }}</div>
                    </div>
                @endforeach
            </div>
            <div class="technical-section-label">TECHNICAL DOCUMENTS:</div>
            <div class="technical-requirement" data-requirement-row><div class="technical-initial-grid"><span></span>@foreach ($slots as $slot)<span class="initial-cell" data-initial-slot="{{ $slot['key'] }}" contenteditable="true"></span>@endforeach</div><div class="technical-requirement-text">1. Bid Security</div></div>
            <div class="technical-security-check"><div><span>Amount</span><label><input type="radio" name="security-amount" value="sufficient">Sufficient</label><label><input type="radio" name="security-amount" value="insufficient">Insufficient</label></div><div><span>Validity</span><label><input type="radio" name="security-validity" value="sufficient">Sufficient</label><label><input type="radio" name="security-validity" value="insufficient">Insufficient</label></div></div>
            <div class="technical-requirement" data-requirement-row><div class="technical-initial-grid"><span></span>@foreach ($slots as $slot)<span class="initial-cell" data-initial-slot="{{ $slot['key'] }}" contenteditable="true"></span>@endforeach</div><div class="technical-requirement-text">2. Bid Securing Declaration</div></div>
            <div class="technical-requirement" data-requirement-row><div class="technical-initial-grid"><span></span>@foreach ($slots as $slot)<span class="initial-cell" data-initial-slot="{{ $slot['key'] }}" contenteditable="true"></span>@endforeach</div><div class="technical-requirement-text">3. Omnibus Sworn Statement</div></div>
            <p class="technical-note">Note : Any missing or non-complying document in the above - mentioned checklist ia a ground for outright rejection<br>of the bid.</p>
            <div class="technical-remarks"><strong>Remarks :</strong><label><input type="radio" name="technical-checklist-result" value="complying">Complying</label><label><input type="radio" name="technical-checklist-result" value="non-complying">Non-Complying</label></div>
            <p class="technical-footer">On the Bid Opening day, the BAC may find it to use this Form to keep track of the results of the preliminary examination of bids. This form, once accomplished, may be used by the BAC Secretariat as a reference in writing up the minutes of the bid opening.</p>
        </section>
    </div>

    @if ($isBacSecretariatOne)
        @include('bac-secretariat.competitive-bidding.partials.signature-routing-tools', ['templateKey' => 'technical_envelope_checklist', 'documentTitle' => 'Checklist of Technical Envelope Requirements'])
    @endif

    <script>
        (() => {
            const paper = document.querySelector('.technical-page');
            document.querySelector('[data-technical-print]')?.addEventListener('click', () => window.print());
            document.querySelector('[data-technical-save]')?.addEventListener('click', () => {
                const clone = paper.cloneNode(true);
                clone.querySelectorAll('[contenteditable]').forEach((node) => node.removeAttribute('contenteditable'));
                clone.querySelectorAll('input[type=radio]').forEach((node) => node.toggleAttribute('checked', paper.querySelector(`input[name="${node.name}"][value="${node.value}"]`)?.checked === true));
                const css = Array.from(document.querySelectorAll('style')).map((node) => node.innerHTML).join('\n');
                const html = `<!doctype html><html><head><meta charset="utf-8"><style>${css} @page { size:A4 portrait; margin:19mm 18mm 12mm; } .technical-page { width:auto !important; min-height:0 !important; padding:0 !important; box-shadow:none !important; }</style></head><body>${clone.outerHTML}</body></html>`;
                const link = document.createElement('a');
                link.href = URL.createObjectURL(new Blob(['\ufeff', html], {type:'application/msword'}));
                link.download = 'technical-envelope-checklist.doc';
                link.click();
                setTimeout(() => URL.revokeObjectURL(link.href), 1000);
            });

            document.querySelectorAll('[data-reviewer-slot]').forEach((row) => {
                const select = row.querySelector('[data-reviewer-account]');
                const name = row.querySelector('[data-reviewer-name]');
                const present = row.querySelector('[data-cb-participating]');
                const sync = () => {
                    row.querySelector('[data-cb-recipient-user-id]').value = select.value;
                    row.querySelector('[data-cb-signatory-name]').textContent = name.value.trim();
                    const parts = name.value.trim().split(/\s+/);
                    const heading = document.querySelector(`[data-printed-reviewer="${row.dataset.reviewerSlot}"]`);
                    heading.textContent = present.checked && parts.length ? `${parts[0].charAt(0)}. ${parts.at(-1)}`.toUpperCase() : '';
                    if (!present.checked) document.querySelectorAll(`[data-initial-slot="${row.dataset.reviewerSlot}"]`).forEach((cell) => cell.textContent = '');
                };
                select.addEventListener('change', () => { name.value = select.selectedOptions[0]?.dataset.name || name.value; sync(); });
                name.addEventListener('input', sync);
                present.addEventListener('change', sync);
                sync();
            });
            document.querySelector('[data-cb-routing-form="technical_envelope_checklist"]')?.addEventListener('submit', (event) => {
                const rows = Array.from(paper.querySelectorAll('[data-requirement-row]'));
                const reviewers = Array.from(document.querySelectorAll('[data-reviewer-slot]')).filter((row) => row.querySelector('[data-cb-participating]').checked);
                const accountIds = reviewers.map((row) => row.querySelector('[data-reviewer-account]').value);
                const fields = ['project', 'bidder', 'abc'].every((field) => paper.querySelector(`[data-checklist-${field}]`)?.textContent.trim());
                const complete = rows.length === 9 && rows.every((row) => Array.from(row.querySelectorAll('.initial-cell')).some((cell) => cell.textContent.trim() && reviewers.some((reviewer) => reviewer.dataset.reviewerSlot === cell.dataset.initialSlot)));
                if (!fields || !complete || !paper.querySelector('[name="technical-checklist-result"][value="complying"]').checked || !reviewers.some((row) => ['chairperson', 'vice_chairperson'].includes(row.dataset.reviewerSlot)) || accountIds.some((id) => !id) || new Set(accountIds).size !== accountIds.length) {
                    event.preventDefault();
                    window.alert('Enter the project, bidder, and budget; initial all nine requirements with present BAC personnel; mark Complying; and select distinct accounts including the chairperson or vice-chairperson.');
                }
            }, true);
        })();
    </script>
@endsection
