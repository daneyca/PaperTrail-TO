@extends('layouts.dashboard')

@section('title', 'Financial Envelope Checklist | PaperTrail')

@php
    $slots = [
        ['key' => 'member_3', 'label' => 'BAC Member', 'role' => 'bac_member', 'user_id' => 'BACMEM-003', 'name' => 'Jessa G. Resus', 'account_role' => \App\Models\User::ROLE_BAC_MEMBER],
        ['key' => 'member_1', 'label' => 'BAC Member', 'role' => 'bac_member', 'user_id' => 'BACMEM-001', 'name' => 'Perpetua D. Salan', 'account_role' => \App\Models\User::ROLE_BAC_MEMBER],
        ['key' => 'member_2', 'label' => 'BAC Member', 'role' => 'bac_member', 'user_id' => 'BACMEM-002', 'name' => 'Rogelio A. Layo', 'account_role' => \App\Models\User::ROLE_BAC_MEMBER],
        ['key' => 'vice_chairperson', 'label' => 'BAC Vice-Chairperson', 'role' => 'bac_vice_chairperson', 'user_id' => 'BACVICE-001', 'name' => 'Roel J. Bano', 'account_role' => \App\Models\User::ROLE_BAC_VICE_CHAIRPERSON],
        ['key' => 'chairperson', 'label' => 'BAC Chairperson', 'role' => 'bac_chairperson', 'user_id' => 'BACCHAIR-001', 'name' => 'Edmar T. Tambis', 'account_role' => \App\Models\User::ROLE_BAC_CHAIR],
    ];
    $requirements = [
        'Duly signed Bid Form as prescribe.',
        'Audited Financial Statement, showing, among others, the prospective total and current assets and liabilities, "stamped" received by the BIR',
        'Latest Income Tax Return',
    ];
    $isBacSecretariatOne = auth()->user()?->user_id === 'BACSEC-001';
@endphp

@section('content')
    <style>
        .financial-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-bottom:16px; }
        .financial-toolbar h1 { margin:0 auto 0 0; color:#0b2341; font-size:18px; }
        .financial-toolbar a, .financial-toolbar button { display:inline-flex; align-items:center; justify-content:center; min-height:36px; padding:0 14px; border:1px solid #cbd5e1; border-radius:6px; background:#fff; color:#0b2341; font:700 13px Figtree, Arial, sans-serif; text-decoration:none; cursor:pointer; }
        .financial-toolbar button:last-child { background:#0b2341; border-color:#0b2341; color:#fff; }
        .financial-reviewers { margin-bottom:18px; padding:14px; border:1px solid #d8dee8; background:#fff; }
        .financial-reviewers h2 { margin:0 0 12px; color:#0b2341; font-size:15px; }
        .financial-reviewer-grid { display:grid; gap:8px; }
        .financial-reviewer-row { display:grid; grid-template-columns:180px 80px minmax(180px,1fr) minmax(160px,1fr); align-items:center; gap:10px; font-size:13px; }
        .financial-reviewer-row > span:first-child { font-weight:700; }
        .financial-reviewer-row label { display:flex; align-items:center; gap:5px; }
        .financial-reviewer-row select, .financial-reviewer-row input[type=text] { width:100%; min-height:36px; padding:0 8px; border:1px solid #cbd5e1; border-radius:6px; background:#fff; color:#0b2341; font:600 13px Figtree, Arial, sans-serif; }
        .financial-sheet-wrap { display:flex; justify-content:center; overflow:auto; padding:8px 8px 36px; }
        .financial-paper { width:210mm; min-height:297mm; flex:none; display:flex; flex-direction:column; box-sizing:border-box; padding:19mm 18mm 19mm 22mm; background:#fff; color:#000; box-shadow:0 12px 30px #0b234126; font:10pt/1.18 Calibri, Arial, sans-serif; }
        .financial-paper * { box-sizing:border-box; }
        .financial-paper [contenteditable=true] { outline:0; }
        .financial-paper [contenteditable=true]:focus { background:#f0f6ff; outline:1px solid #7aa7ed; }
        .financial-title { margin:0 0 24px; text-align:center; font-size:13pt; font-weight:700; }
        .financial-meta { display:grid; grid-template-columns:80px minmax(0,1fr) 128px; align-items:end; gap:5px; }
        .financial-meta strong { white-space:nowrap; }
        .financial-meta .financial-date { grid-column:3; grid-row:1; min-height:17px; }
        .financial-project-lines { grid-column:2; grid-row:2; width:80%; }
        .financial-meta .financial-project-label { grid-column:1; grid-row:1; }
        .financial-line { min-height:18px; border-bottom:1px solid #000; }
        .financial-line[contenteditable=true]:empty:before { content:'\00a0'; }
        .financial-bidder { display:grid; grid-template-columns:80px minmax(0,1fr); gap:5px; margin-top:5px; }
        .financial-bidder-lines { width:67%; }
        .financial-form-title { margin:26px 0 18px; text-align:center; font-size:11pt; font-weight:700; }
        .financial-envelope { display:grid; grid-template-columns:151px minmax(0,1fr); gap:12px; margin-bottom:8px; }
        .financial-envelope strong { white-space:nowrap; }
        .financial-envelope div { max-width:440px; font-size:9pt; font-weight:700; line-height:1.25; }
        .financial-heading { display:grid; grid-template-columns:17px repeat(5,26px) minmax(0,1fr); min-height:98px; }
        .financial-heading > span { border:1px solid #000; border-right:0; display:flex; justify-content:center; align-items:center; overflow:hidden; }
        .financial-heading > span:nth-child(6) { border-right:1px solid #000; }
        .financial-heading > span:last-child { border:0; }
        .financial-heading .financial-rotated { writing-mode:vertical-rl; transform:rotate(180deg); white-space:nowrap; font-size:8pt; }
        .financial-initial-caption { width:151px; margin:3px 0 20px; font-size:8pt; line-height:1.12; }
        .financial-requirement { display:grid; grid-template-columns:151px minmax(0,1fr); gap:12px; align-items:start; min-height:29px; margin-bottom:5px; break-inside:avoid; }
        .financial-initial-grid { display:grid; grid-template-columns:17px repeat(5,26px); height:19px; }
        .financial-initial-grid > span { border:1px solid #000; border-right:0; overflow:hidden; white-space:nowrap; text-align:center; font-size:8pt; line-height:17px; }
        .financial-initial-grid > span:last-child { border-right:1px solid #000; }
        .financial-requirement-text { padding-top:2px; font-size:9pt; }
        .financial-note { margin:14px 0 12px; font-size:8pt; }
        .financial-remarks { display:flex; align-items:center; gap:22px; font-size:9pt; }
        .financial-remarks input { margin:0 3px 0 0; accent-color:#000; vertical-align:middle; }
        .financial-footer { margin-top:auto; padding-top:24px; font-size:9pt; line-height:1.25; }
        @media (max-width:800px) { .financial-reviewer-row { grid-template-columns:1fr 80px; } .financial-reviewer-row select, .financial-reviewer-row input[type=text] { grid-column:span 2; } }
        @media print { @page { size:A4 portrait; margin:19mm 18mm 19mm 22mm; } body { background:#fff !important; } .financial-toolbar, .financial-reviewers, .cb-routing-actions, .cb-routing-dialog, .no-print { display:none !important; } .financial-sheet-wrap { display:block; padding:0; overflow:visible; } .financial-paper { width:auto; min-height:259mm; padding:0; box-shadow:none; } .financial-paper * { color:#000 !important; } }
    </style>

    <div class="financial-toolbar no-print">
        <h1>Checklist of Financial Envelope Requirements</h1>
        <a href="{{ route('bac-secretariat.competitive-bidding.checklist.financial') }}">Back</a>
        <button type="button" data-financial-print>Print</button>
        <button type="button" data-financial-save>Save Word</button>
    </div>

    @if ($isBacSecretariatOne)
        <div class="financial-reviewers no-print">
            <h2>BAC personnel at bid opening</h2>
            <div class="financial-reviewer-grid">
                @foreach ($slots as $slot)
                    @php
                        $accounts = $bacSigners->where('role', $slot['account_role']);
                        $defaultAccount = $accounts->firstWhere('user_id', $slot['user_id']);
                    @endphp
                    <div class="financial-reviewer-row" data-financial-reviewer="{{ $slot['key'] }}" data-cb-signatory-entry>
                        <span>{{ $slot['label'] }}</span>
                        <label><input type="checkbox" data-cb-participating @checked($defaultAccount)> Present</label>
                        <select aria-label="{{ $slot['label'] }} account" data-financial-account>
                            <option value="">Select account</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" data-name="{{ $account->name }}" @selected($defaultAccount?->id === $account->id)>{{ $account->user_id }} - {{ $account->name }}</option>
                            @endforeach
                        </select>
                        <input type="text" aria-label="{{ $slot['label'] }} printed name" data-financial-name value="{{ $defaultAccount?->name ?: $slot['name'] }}">
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

    <div class="financial-sheet-wrap">
        <section class="financial-paper" data-competitive-document>
            <h2 class="financial-title">Checklist of Financial Envelope Requirements</h2>
            <div class="financial-meta">
                <strong class="financial-project-label">PROJECT NAME:</strong>
                <span class="financial-date" contenteditable="true">{{ now()->format('F j, Y') }}</span>
                <div class="financial-project-lines" data-checklist-project><div class="financial-line" contenteditable="true"></div><div class="financial-line" contenteditable="true"></div></div>
            </div>
            <div class="financial-bidder"><strong>BIDDER:</strong><div class="financial-bidder-lines" data-checklist-bidder><div class="financial-line" contenteditable="true"></div><div class="financial-line" contenteditable="true"></div><div class="financial-line" contenteditable="true"></div></div></div>
            <h3 class="financial-form-title">Checklist of Bid Requirements</h3>
            <div class="financial-envelope"><strong>FINANCIAL ENVELOPE:</strong><div>Shall contain the following information / documents and shall be opened only if the bidder has complied with the requirements in the Technical Envelope.</div></div>
            <div class="financial-heading"><span></span>@foreach ($slots as $slot)<span><span class="financial-rotated" data-financial-printed-name="{{ $slot['key'] }}">{{ strtoupper(substr($slot['name'], 0, 1).'. '.collect(explode(' ', $slot['name']))->last()) }}</span></span>@endforeach<span></span></div>
            <div class="financial-initial-caption">Initial of BAC Member If<br>document is included</div>
            @foreach ($requirements as $index => $requirement)
                <div class="financial-requirement" data-requirement-row>
                    <div class="financial-initial-grid"><span></span>@foreach ($slots as $slot)<span class="initial-cell" data-initial-slot="{{ $slot['key'] }}" contenteditable="true"></span>@endforeach</div>
                    <div class="financial-requirement-text">{{ $index + 1 }}. {{ $requirement }}</div>
                </div>
            @endforeach
            <p class="financial-note">Note : Any missing or non-complying document in the above - mentioned checklist ia a ground for outright rejection of the bid.</p>
            <div class="financial-remarks"><strong>Remarks :</strong><label><input type="radio" name="financial-checklist-result" value="complying">Complying</label><label><input type="radio" name="financial-checklist-result" value="non-complying">Non-Complying</label></div>
            <p class="financial-footer">On the Bid Opening day, the BAC may find it to use this Form to keep track of the results of the preliminary examination of bids. This form, once accomplished, may be used by the BAC Secretariat as a reference in writing up the minutes of the bid opening.</p>
        </section>
    </div>

    @if ($isBacSecretariatOne)
        @include('bac-secretariat.competitive-bidding.partials.signature-routing-tools', ['templateKey' => 'financial_envelope_checklist', 'documentTitle' => 'Checklist of Financial Envelope Requirements'])
    @endif

    <script>
        (() => {
            const paper = document.querySelector('.financial-paper');
            document.querySelector('[data-financial-print]')?.addEventListener('click', () => window.print());
            document.querySelector('[data-financial-save]')?.addEventListener('click', () => {
                const clone = paper.cloneNode(true);
                clone.querySelectorAll('[contenteditable]').forEach((node) => node.removeAttribute('contenteditable'));
                clone.querySelectorAll('input[type=radio]').forEach((node) => node.toggleAttribute('checked', paper.querySelector(`input[name="${node.name}"][value="${node.value}"]`)?.checked === true));
                const css = Array.from(document.querySelectorAll('style')).map((node) => node.innerHTML).join('\n');
                const html = `<!doctype html><html><head><meta charset="utf-8"><style>${css} @page { size:A4 portrait; margin:19mm 18mm 19mm 22mm; } .financial-paper { width:auto !important; min-height:259mm !important; padding:0 !important; box-shadow:none !important; }</style></head><body>${clone.outerHTML}</body></html>`;
                const link = document.createElement('a');
                link.href = URL.createObjectURL(new Blob(['\ufeff', html], {type:'application/msword'}));
                link.download = 'financial-envelope-checklist.doc';
                link.click();
                setTimeout(() => URL.revokeObjectURL(link.href), 1000);
            });

            document.querySelectorAll('[data-financial-reviewer]').forEach((row) => {
                const select = row.querySelector('[data-financial-account]');
                const name = row.querySelector('[data-financial-name]');
                const present = row.querySelector('[data-cb-participating]');
                const sync = () => {
                    row.querySelector('[data-cb-recipient-user-id]').value = select.value;
                    row.querySelector('[data-cb-signatory-name]').textContent = name.value.trim();
                    const parts = name.value.trim().split(/\s+/);
                    document.querySelector(`[data-financial-printed-name="${row.dataset.financialReviewer}"]`).textContent = present.checked && parts.length ? `${parts[0].charAt(0)}. ${parts.at(-1)}`.toUpperCase() : '';
                    if (!present.checked) document.querySelectorAll(`[data-initial-slot="${row.dataset.financialReviewer}"]`).forEach((cell) => cell.textContent = '');
                };
                select.addEventListener('change', () => { name.value = select.selectedOptions[0]?.dataset.name || name.value; sync(); });
                name.addEventListener('input', sync);
                present.addEventListener('change', sync);
                sync();
            });

            document.querySelector('[data-cb-routing-form="financial_envelope_checklist"]')?.addEventListener('submit', (event) => {
                const reviewers = Array.from(document.querySelectorAll('[data-financial-reviewer]')).filter((row) => row.querySelector('[data-cb-participating]').checked);
                const accountIds = reviewers.map((row) => row.querySelector('[data-financial-account]').value);
                const rows = Array.from(paper.querySelectorAll('[data-requirement-row]'));
                const details = ['project', 'bidder'].every((field) => paper.querySelector(`[data-checklist-${field}]`)?.textContent.trim());
                const complete = rows.length === 3 && rows.every((row) => Array.from(row.querySelectorAll('.initial-cell')).some((cell) => cell.textContent.trim() && reviewers.some((reviewer) => reviewer.dataset.financialReviewer === cell.dataset.initialSlot)));
                if (!details || !complete || !paper.querySelector('[name="financial-checklist-result"][value="complying"]').checked || !reviewers.some((row) => ['chairperson', 'vice_chairperson'].includes(row.dataset.financialReviewer)) || accountIds.some((id) => !id) || new Set(accountIds).size !== accountIds.length) {
                    event.preventDefault();
                    window.alert('Enter the project and bidder; initial all three requirements with present BAC personnel; mark Complying; and select distinct accounts including the chairperson or vice-chairperson.');
                }
            }, true);
        })();
    </script>
@endsection
