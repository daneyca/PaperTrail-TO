@extends('layouts.dashboard')

@section('title', 'Annual Procurement Plan | PaperTrail')

@section('content')
@php
    $saved = $draft?->payload ?? [];
    $items = old('items', $saved['items'] ?? [array_fill_keys(\App\Services\CompetitiveBiddingAppService::FIELDS, '')]);
    if ($items === []) $items = [array_fill_keys(\App\Services\CompetitiveBiddingAppService::FIELDS, '')];
    $slots = \App\Services\CompetitiveBiddingAppService::SLOTS;
@endphp
<style>
    .cb-app-toolbar { display:flex; align-items:center; gap:8px; flex-wrap:wrap; padding:12px 16px; margin-bottom:12px; border:1px solid #d8e0eb; background:#fff; }
    .cb-app-toolbar strong { margin-right:auto; color:#0b2341; }
    .cb-app-toolbar button,.cb-app-toolbar a { display:inline-flex; min-height:36px; align-items:center; padding:0 12px; border:1px solid #cbd5e1; border-radius:6px; background:#fff; color:#0b2341; text-decoration:none; cursor:pointer; font:700 .82rem Arial,sans-serif; }
    .cb-app-toolbar .cb-app-submit { background:#0b2341; border-color:#0b2341; color:white; }
    .cb-app-status { font-size:.78rem; color:#52647d; }
    .cb-app-status[data-state="error"] { color:#b42318; }
    .cb-app-errors { padding:10px 14px; margin-bottom:12px; color:#991b1b; background:#fff1f2; border:1px solid #fecaca; }
    .cb-app-scroll { max-width:100%; overflow:auto; padding:8px 0 24px; }
    .cb-app-sheet { width:390mm; min-height:260mm; margin:auto; padding:10mm 8mm; box-sizing:border-box; background:white; border:1px solid #c8d1dd; box-shadow:0 12px 32px rgba(15,23,42,.12); color:#111; font:7pt/1.2 Arial,sans-serif; }
    .cb-app-sheet * { box-sizing:border-box; }
    .cb-app-heading { text-align:center; margin-bottom:8mm; }
    .cb-app-heading p { margin:1px; }
    .cb-app-heading h1 { margin:3px 0; font-size:12pt; }
    .cb-app-heading input { width:52px; border:0; border-bottom:1px solid #333; text-align:center; font:inherit; }
    .cb-app-table { width:100%; border-collapse:collapse; table-layout:fixed; }
    .cb-app-table col:nth-child(1) { width:5%; }
    .cb-app-table col:nth-child(2) { width:14%; }
    .cb-app-table col:nth-child(3) { width:8%; }
    .cb-app-table col:nth-child(4) { width:8%; }
    .cb-app-table col:nth-child(n+5):nth-child(-n+8) { width:5%; }
    .cb-app-table col:nth-child(9) { width:7%; }
    .cb-app-table col:nth-child(n+10):nth-child(-n+13) { width:6%; }
    .cb-app-table col:nth-child(14) { width:14%; }
    .cb-app-table th,.cb-app-table td { border:1px solid #333; padding:4px; vertical-align:middle; overflow-wrap:anywhere; }
    .cb-app-table thead th { text-align:center; font-weight:700; }
    .cb-app-table thead tr:first-child th { height:30px; }
    .cb-app-table tbody tr { min-height:40px; }
    body.dashboard-body .cb-app-table textarea,body.dashboard-body .cb-app-table input { width:100% !important; min-height:0 !important; padding:1px !important; border:0 !important; border-radius:0 !important; box-shadow:none !important; background:transparent !important; color:#111; font:inherit !important; resize:vertical; }
    .cb-app-table input[type="number"] { text-align:right; }
    .cb-app-table .cb-app-amount { text-align:right; }
    .cb-app-total td { font-weight:700; text-align:right; }
    .cb-app-row-controls td { background:#e5f1ef; }
    .cb-app-row-controls-inner { position:sticky; left:8px; z-index:2; display:flex; align-items:center; gap:6px; width:max-content; max-width:calc(100vw - 48px); }
    .cb-app-row-controls-inner strong { margin-right:8px; font-size:7pt; }
    .cb-app-row-controls button { border:1px solid #9bbab4; background:white; border-radius:4px; padding:5px 9px; color:#0b2341; font:700 7pt Arial,sans-serif; cursor:pointer; }
    .cb-app-row-controls button:disabled { opacity:.45; cursor:not-allowed; }
    .cb-app-signatures { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:8mm; margin-top:20mm; }
    .cb-app-signatures section { min-width:0; }
    .cb-app-signatures strong { display:block; min-height:12mm; font-size:8pt; }
    .cb-app-signatures input { display:block; width:100%; border:0; border-bottom:1px solid #555; border-radius:0; padding:4px 2px; text-align:center; background:white; color:#111; font:inherit; }
    .cb-app-signatures input::placeholder { color:#64748b; }
    .cb-app-account { margin-bottom:5mm; color:#52647d; font-size:7pt; }
    .cb-app-print { display:none; }
    @media(max-width:900px) { .cb-app-sheet { margin-left:0; } }
    @media print {
        @page { size:A4 landscape; margin:8mm; }
        html,body { width:auto !important; min-width:0 !important; }
        body.dashboard-body .dashboard-shell,body.dashboard-body .dashboard-main,body.dashboard-body .dashboard-content,body.dashboard-body #dashboardContent,#cb-app-form,.cb-app-scroll { display:block !important; visibility:visible !important; width:100% !important; max-width:none !important; height:auto !important; overflow:visible !important; margin:0 !important; padding:0 !important; box-shadow:none !important; }
        .cb-app-sheet,.cb-app-sheet * { visibility:visible !important; }
        .cb-app-sheet { width:100% !important; min-height:0 !important; margin:0 !important; padding:0 !important; border:0 !important; box-shadow:none !important; font-size:6.4pt !important; }
        .cb-app-toolbar,.cb-app-errors,.cb-app-row-controls,.cb-app-account { display:none !important; }
        .cb-app-table thead { display:table-header-group; }
        .cb-app-table tr,.cb-app-signatures { break-inside:avoid; }
        .cb-app-table textarea,.cb-app-table input,.cb-app-signatures input,.cb-app-heading input { display:none !important; }
        .cb-app-print { display:block !important; white-space:pre-wrap; overflow-wrap:anywhere; }
        .cb-app-amount .cb-app-print { text-align:right; }
        .cb-app-signatures .cb-app-print { text-align:center; min-height:5mm; }
        .cb-app-signatures .cb-app-name-print { border-bottom:1px solid #333; font-weight:700; text-transform:uppercase; }
        .cb-app-heading .cb-app-print { display:inline !important; }
    }
</style>

<form id="cb-app-form" method="POST" action="{{ route('bac-secretariat.competitive-bidding.annual-procurement-plan.submit') }}" data-draft-url="{{ route('bac-secretariat.competitive-bidding.annual-procurement-plan.draft.store') }}">
    @csrf
    <div class="cb-app-toolbar no-print">
        <strong>Annual Procurement Plan</strong>
        <span class="cb-app-status" id="cb-app-status" role="status" aria-live="polite">{{ $draft ? 'Draft saved' : 'Not saved yet' }}</span>
        <a href="{{ route('bac-secretariat.competitive-bidding.menu', ['task' => 'annual-procurement-plan']) }}">Back</a>
        <button type="button" id="cb-app-save">Save Draft</button>
        <button type="button" id="cb-app-print">Print</button>
        <button type="submit" class="cb-app-submit">Submit for Signatories</button>
    </div>
    @if ($errors->any())<div class="cb-app-errors" role="alert"><strong>Please check the APP entries:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="cb-app-scroll"><article class="cb-app-sheet">
        <header class="cb-app-heading">
            <p>Republic of the Philippines</p><p>Province of Southern Leyte</p><p><strong>MUNICIPALITY OF TOMAS OPPUS</strong></p>
            <h1>Annual Procurement Plan (APP) for CY <input name="year" type="number" min="2000" max="2100" value="{{ old('year', $saved['year'] ?? now()->year) }}" required><span class="cb-app-print" data-print-year></span></h1>
        </header>
        <table class="cb-app-table">
            <colgroup>@for ($i=0; $i<14; $i++)<col>@endfor</colgroup>
            <thead>
                <tr><th rowspan="2">Code<br>(PAP)</th><th rowspan="2">Procurement Program / Project</th><th rowspan="2">PMO / End-User</th><th rowspan="2">Mode of Procurement</th><th colspan="4">Schedule for Each Procurement Activity</th><th rowspan="2">Source of Funds</th><th colspan="4">Estimated Budget (PhP)</th><th rowspan="2">Remarks<br>(Brief description of Program/Project)</th></tr>
                <tr><th>Ads/Post of IB/REI</th><th>Sub/Open of Bids</th><th>Notice of Award</th><th>Contract Signing</th><th>Total</th><th>Personnel Outlay</th><th>MOOE</th><th>CO</th></tr>
            </thead>
            <tbody id="cb-app-rows">
                @foreach ($items as $index => $item)
                    <tr class="cb-app-row">@foreach (\App\Services\CompetitiveBiddingAppService::FIELDS as $field)
                        <td @class(['cb-app-amount' => in_array($field, ['budget','personnel','mooe','co'])])>
                            @if (in_array($field, ['budget','personnel','mooe','co']))
                                <input type="number" name="items[{{ $index }}][{{ $field }}]" min="0" step="0.01" value="{{ $item[$field] ?? '' }}" aria-label="{{ ucfirst($field) }}"><span class="cb-app-print"></span>
                            @else
                                <textarea name="items[{{ $index }}][{{ $field }}]" rows="2" aria-label="{{ ucfirst(str_replace('_',' ',$field)) }}">{{ $item[$field] ?? '' }}</textarea><span class="cb-app-print"></span>
                            @endif
                        </td>
                    @endforeach</tr>
                @endforeach
            </tbody>
            <tbody class="cb-app-row-controls no-print"><tr><td colspan="14"><div class="cb-app-row-controls-inner"><strong>Procurement Items</strong><button type="button" id="cb-app-add">Add Row</button><button type="button" id="cb-app-remove">Remove Last Row</button></div></td></tr></tbody>
            <tfoot><tr class="cb-app-total"><td colspan="9">TOTAL</td><td id="cb-app-grand-total">0.00</td><td colspan="4"></td></tr></tfoot>
        </table>
        <div class="cb-app-signatures">
            @foreach ($slots as $slot => $label)
                <section><strong>{{ $label }}:</strong><div class="cb-app-account no-print">Recipient: {{ $signerAccounts[$slot]?->name ?? 'Account unavailable' }} ({{ \App\Services\CompetitiveBiddingAppService::ACCOUNTS[$slot] }})</div>
                    <input name="{{ $slot }}_name" value="{{ old($slot.'_name', $saved[$slot.'_name'] ?? '') }}" placeholder="Enter name" aria-label="{{ $label }} name"><span class="cb-app-print cb-app-name-print"></span>
                    <input name="{{ $slot }}_title" value="{{ old($slot.'_title', $saved[$slot.'_title'] ?? '') }}" placeholder="Enter Position" aria-label="{{ $label }} position"><span class="cb-app-print"></span>
                </section>
            @endforeach
        </div>
    </article></div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('cb-app-form');
    const rows = document.getElementById('cb-app-rows');
    const status = document.getElementById('cb-app-status');
    const fields = @json(\App\Services\CompetitiveBiddingAppService::FIELDS);
    const slots = @json(array_keys($slots));
    let revision = {{ (int) ($draft?->revision ?? 0) }};
    let timer, saving = false, again = false, dirty = false;

    function rowData(row) {
        return Object.fromEntries(fields.map(field => [field, row.querySelector(`[name$="[${field}]"]`)?.value ?? '']));
    }
    function reindex() {
        [...rows.querySelectorAll('tr')].forEach((row, index) => {
            row.querySelectorAll('input,textarea').forEach(input => {
                input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`);
            });
        });
    }
    function collect() {
        const result = {year: form.elements.year.value, items: [...rows.querySelectorAll('tr')].map(rowData)};
        slots.forEach(slot => { result[`${slot}_name`] = form.elements[`${slot}_name`].value; result[`${slot}_title`] = form.elements[`${slot}_title`].value; });
        return result;
    }
    function syncPrint() {
        form.querySelector('[data-print-year]').textContent = form.elements.year.value;
        rows.querySelectorAll('td').forEach(td => {
            const input = td.querySelector('input,textarea');
            const output = td.querySelector('.cb-app-print');
            if (input && output) output.textContent = input.type === 'number' && input.value !== '' ? Number(input.value).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}) : (input?.value || '');
        });
        slots.forEach(slot => {
            ['name','title'].forEach(part => {
                const input = form.elements[`${slot}_${part}`];
                input.nextElementSibling.textContent = input.value;
            });
        });
        const total = [...rows.querySelectorAll('input[name$="[budget]"]')].reduce((sum, el) => sum + (Number(el.value) || 0), 0);
        document.getElementById('cb-app-grand-total').textContent = total.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
    }
    async function save() {
        if (saving) { again = true; return; }
        saving = true; status.textContent = 'Saving...'; status.dataset.state = '';
        try {
            const response = await fetch(form.dataset.draftUrl, {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':form.querySelector('[name="_token"]').value}, body:JSON.stringify({revision,draft:collect()})});
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Could not save this draft.');
            revision = result.revision; dirty = false; status.textContent = 'Draft saved'; status.dataset.state = 'saved';
        } catch (error) { status.textContent = error.message; status.dataset.state = 'error'; }
        finally { saving = false; if (again) { again = false; save(); } }
    }
    function changed() { dirty = true; status.textContent = 'Unsaved changes'; status.dataset.state = ''; clearTimeout(timer); timer = setTimeout(save, 1200); syncPrint(); }
    form.addEventListener('input', changed);
    form.addEventListener('change', changed);
    document.getElementById('cb-app-save').addEventListener('click', () => { clearTimeout(timer); save(); });
    document.getElementById('cb-app-print').addEventListener('click', () => { syncPrint(); window.print(); });
    function updateRowActions() {
        const atLimit = rows.children.length >= 500;
        document.getElementById('cb-app-add').disabled = atLimit;
        document.getElementById('cb-app-remove').disabled = rows.children.length <= 1;
    }
    function addRow() {
        if (rows.children.length >= 500) return;
        const row = rows.querySelector('tr').cloneNode(true);
        row.querySelectorAll('input,textarea').forEach(input => input.value = '');
        row.querySelectorAll('.cb-app-print').forEach(span => span.textContent = '');
        rows.appendChild(row); reindex(); updateRowActions(); changed(); row.querySelector('textarea,input').focus();
    }
    document.getElementById('cb-app-add').addEventListener('click', addRow);
    document.getElementById('cb-app-remove').addEventListener('click', () => { if (rows.children.length > 1) { rows.lastElementChild.remove(); reindex(); updateRowActions(); changed(); } });
    form.addEventListener('submit', () => { clearTimeout(timer); syncPrint(); });
    window.addEventListener('beforeprint', syncPrint);
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    updateRowActions();
    syncPrint();
});
</script>
@endsection
