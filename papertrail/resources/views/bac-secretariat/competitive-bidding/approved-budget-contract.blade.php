@extends('layouts.dashboard')

@section('title', 'Approved Budget for the Contract | PaperTrail')

@section('content')
@php
    $saved = $draft?->payload ?? [];
    $items = old('items', $saved['items'] ?? [array_fill_keys(\App\Services\CompetitiveBiddingAbcService::FIELDS, '')]);
    if ($items === []) $items = [array_fill_keys(\App\Services\CompetitiveBiddingAbcService::FIELDS, '')];
    $slots = \App\Services\CompetitiveBiddingAbcService::SLOTS;
    $titles = ['prepared' => '', 'checked' => 'BAC Secretariat', 'recommended' => 'BAC Chairperson', 'approved' => 'Municipal Mayor'];
@endphp
<style>
    .abc-toolbar { display:flex; align-items:center; gap:8px; flex-wrap:wrap; padding:12px 16px; margin-bottom:12px; border:1px solid #d8e0eb; background:#fff; }
    .abc-toolbar strong { margin-right:auto; color:#0b2341; }
    .abc-toolbar button,.abc-toolbar a { display:inline-flex; min-height:36px; align-items:center; padding:0 12px; border:1px solid #cbd5e1; border-radius:6px; background:#fff; color:#0b2341; text-decoration:none; cursor:pointer; font:700 .82rem Arial,sans-serif; }
    .abc-toolbar .abc-submit { background:#0b2341; border-color:#0b2341; color:#fff; }
    .abc-status { color:#52647d; font-size:.78rem; }
    .abc-status[data-state="error"] { color:#b42318; }
    .abc-errors { margin-bottom:12px; padding:10px 14px; border:1px solid #fecaca; background:#fff1f2; color:#991b1b; }
    .abc-scroll { max-width:100%; overflow:auto; padding:8px 0 24px; }
    .abc-sheet { width:390mm; min-height:265mm; margin:auto; padding:9mm 8mm; box-sizing:border-box; border:1px solid #c8d1dd; background:#fff; box-shadow:0 12px 32px rgba(15,23,42,.12); color:#111; font:7pt/1.2 Arial,sans-serif; }
    .abc-sheet * { box-sizing:border-box; }
    .abc-table { width:100%; border-collapse:collapse; table-layout:fixed; }
    .abc-table col:nth-child(1) { width:4%; }
    .abc-table col:nth-child(2) { width:20%; }
    .abc-table col:nth-child(3),.abc-table col:nth-child(4) { width:5%; }
    .abc-table col:nth-child(5) { width:9%; }
    .abc-table col:nth-child(n+6):nth-child(-n+9) { width:5%; }
    .abc-table col:nth-child(10) { width:7%; }
    .abc-table col:nth-child(11) { width:6%; }
    .abc-table col:nth-child(12),.abc-table col:nth-child(13) { width:7%; }
    .abc-table col:nth-child(14) { width:10%; }
    .abc-table th,.abc-table td { border:1px solid #333; padding:3px; vertical-align:middle; overflow-wrap:anywhere; }
    .abc-table thead th { text-align:center; font-weight:700; }
    .abc-table .abc-heading th { border:0; padding:0 0 6mm; }
    .abc-heading p { margin:1px; font-weight:400; }
    .abc-heading h1 { margin:4mm 0 3mm; font-size:12pt; }
    .abc-heading .abc-project { width:80%; margin:auto; font:700 10pt Arial,sans-serif !important; text-align:center; }
    .abc-heading .abc-location { width:65%; margin:auto; text-align:center; }
    .abc-table .abc-numbers th { font-weight:400; }
    body.dashboard-body #abc-form .abc-table input,body.dashboard-body #abc-form .abc-table textarea { display:block; width:100% !important; min-height:0 !important; padding:1px !important; border:0 !important; border-radius:0 !important; box-shadow:none !important; background:transparent !important; color:#111; font:inherit !important; resize:vertical; }
    .abc-table input[type="number"],.abc-table .abc-amount { text-align:right; }
    .abc-table .abc-total td { font-weight:700; }
    .abc-row-controls td { background:#e5f1ef; }
    .abc-row-controls-inner { position:sticky; left:8px; z-index:2; display:flex; align-items:center; gap:6px; width:max-content; max-width:calc(100vw - 48px); }
    .abc-row-controls-inner strong { margin-right:8px; font-size:7pt; }
    .abc-row-controls button { border:1px solid #9bbab4; border-radius:4px; padding:5px 9px; background:#fff; color:#0b2341; font:700 7pt Arial,sans-serif; cursor:pointer; }
    .abc-row-controls button:disabled { opacity:.45; cursor:not-allowed; }
    .abc-signatures { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:9mm; margin-top:18mm; }
    .abc-signatures section { min-width:0; }
    .abc-signatures strong { display:block; min-height:13mm; font-size:8pt; }
    .abc-signatures input { display:block; width:100%; border:0; border-bottom:1px solid #333; border-radius:0; padding:4px 2px; background:transparent; color:#111; text-align:center; font:inherit; }
    .abc-signatures input::placeholder { color:#64748b; }
    .abc-account { margin-bottom:4mm; color:#52647d; font-size:7pt; }
    .abc-print { display:none; }
    @media(max-width:900px) { .abc-sheet { margin-left:0; } }
    @media print {
        @page { size:A4 landscape; margin:8mm; }
        html,body { width:auto !important; min-width:0 !important; }
        body.dashboard-body .dashboard-shell,body.dashboard-body .dashboard-main,body.dashboard-body .dashboard-content,body.dashboard-body #dashboardContent,#abc-form,.abc-scroll { display:block !important; visibility:visible !important; width:100% !important; max-width:none !important; height:auto !important; overflow:visible !important; margin:0 !important; padding:0 !important; box-shadow:none !important; }
        .abc-sheet,.abc-sheet * { visibility:visible !important; }
        .abc-sheet { width:100% !important; min-height:0 !important; margin:0 !important; padding:0 !important; border:0 !important; box-shadow:none !important; font-size:6.5pt !important; }
        .abc-toolbar,.abc-errors,.abc-row-controls,.abc-account { display:none !important; }
        .abc-table thead { display:table-header-group; }
        .abc-table tr,.abc-signatures { break-inside:avoid; }
        .abc-table input,.abc-table textarea,.abc-signatures input { display:none !important; }
        .abc-print { display:block !important; white-space:pre-wrap; overflow-wrap:anywhere; }
        .abc-amount .abc-print { text-align:right; }
        .abc-heading .abc-print { text-align:center; }
        .abc-signatures .abc-print { min-height:4mm; text-align:center; }
        .abc-signatures .abc-name-print { border-bottom:1px solid #333; font-weight:700; text-transform:uppercase; }
    }
</style>

<form id="abc-form" method="POST" action="{{ route('bac-secretariat.competitive-bidding.abc.submit') }}" data-draft-url="{{ route('bac-secretariat.competitive-bidding.abc.draft.store') }}">
    @csrf
    <div class="abc-toolbar no-print">
        <strong>Approved Budget for the Contract</strong>
        <span class="abc-status" id="abc-status" role="status" aria-live="polite">{{ $draft ? 'Draft saved' : 'Not saved yet' }}</span>
        <a href="{{ route('bac-secretariat.competitive-bidding.menu', ['task' => 'approved-budget-for-the-contract']) }}">Back</a>
        <button type="button" id="abc-save">Save Draft</button>
        <button type="button" id="abc-print">Print</button>
        <button type="submit" class="abc-submit">Submit for Signatories</button>
    </div>
    @if ($errors->any())<div class="abc-errors" role="alert"><strong>Please check the ABC entries:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="abc-scroll"><article class="abc-sheet"><table class="abc-table">
        <colgroup>@for ($i=0; $i<14; $i++)<col>@endfor</colgroup>
        <thead>
            <tr class="abc-heading"><th colspan="14">
                <p>Republic of the Philippines</p><p>Province of Southern Leyte</p><p><strong>MUNICIPALITY OF TOMAS OPPUS</strong></p>
                <h1>APPROVED BUDGET FOR THE CONTRACT</h1>
                <input class="abc-project" name="project" value="{{ old('project', $saved['project'] ?? '') }}" placeholder="Enter procurement project" maxlength="300" required><span class="abc-print abc-project-print"></span>
                <input class="abc-location" name="location" value="{{ old('location', $saved['location'] ?? 'Tomas Oppus, Southern Leyte') }}" placeholder="Enter location" maxlength="200" required><span class="abc-print abc-location-print"></span>
            </th></tr>
            <tr><th rowspan="2">ITEM<br>NO.</th><th rowspan="2">DESCRIPTION</th><th rowspan="2">QUANTITY</th><th rowspan="2">UNIT</th><th rowspan="2">ESTIMATED<br>DIRECT COST</th><th colspan="3">MARK-UPS<br>IN PERCENT</th><th colspan="2">TOTAL MARK-UP</th><th rowspan="2">VAT</th><th rowspan="2">TOTAL<br>INDIRECT</th><th rowspan="2">UNIT COST</th><th rowspan="2">TOTAL COST</th></tr>
            <tr><th>OCM</th><th>PROFIT</th><th>MOB. /<br>DEMOB.</th><th>%</th><th>VALUE</th></tr>
            <tr class="abc-numbers">@for ($i=1; $i<=14; $i++)<th>({{ $i }})</th>@endfor</tr>
        </thead>
        <tbody id="abc-rows">
            @foreach ($items as $index => $item)<tr class="abc-row">
                @foreach (\App\Services\CompetitiveBiddingAbcService::FIELDS as $field)
                    <td @class(['abc-amount' => in_array($field, ['direct_cost','markup_value','vat','total_indirect','unit_cost','total_cost'])])>
                        @if ($field === 'total_cost')
                            <input type="number" name="items[{{ $index }}][total_cost]" value="{{ $item['total_cost'] ?? '' }}" readonly aria-label="Total cost"><span class="abc-print"></span>
                        @elseif (in_array($field, ['quantity','direct_cost','ocm_percent','profit_percent','mob_demob_percent','markup_percent','markup_value','vat','total_indirect','unit_cost']))
                            <input type="number" name="items[{{ $index }}][{{ $field }}]" min="0" step="0.01" value="{{ $item[$field] ?? '' }}" aria-label="{{ ucfirst(str_replace('_',' ',$field)) }}"><span class="abc-print"></span>
                        @else
                            <textarea name="items[{{ $index }}][{{ $field }}]" rows="2" aria-label="{{ ucfirst(str_replace('_',' ',$field)) }}">{{ $item[$field] ?? '' }}</textarea><span class="abc-print"></span>
                        @endif
                    </td>
                @endforeach
            </tr>@endforeach
        </tbody>
        <tbody class="abc-row-controls no-print"><tr><td colspan="14"><div class="abc-row-controls-inner"><strong>ABC Items</strong><button type="button" id="abc-add">Add Row</button><button type="button" id="abc-remove">Remove Last Row</button></div></td></tr></tbody>
        <tfoot><tr class="abc-total"><td colspan="13">TOTAL</td><td class="abc-amount" id="abc-grand-total">0.00</td></tr></tfoot>
    </table>
    <div class="abc-signatures">
        @foreach ($slots as $slot => $label)<section><strong>{{ $label }}:</strong>
            @if ($slot !== 'prepared')<div class="abc-account no-print">Recipient: {{ $signerAccounts[$slot]?->name ?? 'Account unavailable' }} ({{ \App\Services\CompetitiveBiddingAbcService::RECIPIENT_ACCOUNTS[$slot] }})</div>@endif
            <input name="{{ $slot }}_name" value="{{ old($slot.'_name', $saved[$slot.'_name'] ?? '') }}" placeholder="Enter name" maxlength="120" aria-label="{{ $label }} name" @required($slot === 'prepared')><span class="abc-print abc-name-print"></span>
            <input name="{{ $slot }}_title" value="{{ old($slot.'_title', $saved[$slot.'_title'] ?? $titles[$slot]) }}" placeholder="Enter position" maxlength="120" aria-label="{{ $label }} position" @required($slot === 'prepared')><span class="abc-print"></span>
        </section>@endforeach
    </div>
    </article></div>
</form>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('abc-form');
    const rows = document.getElementById('abc-rows');
    const status = document.getElementById('abc-status');
    const fields = @json(\App\Services\CompetitiveBiddingAbcService::FIELDS);
    const slots = @json(array_keys($slots));
    let revision = {{ (int) ($draft?->revision ?? 0) }};
    let timer, saving = false, again = false, dirty = false;

    function reindex() { [...rows.querySelectorAll('tr')].forEach((row, index) => row.querySelectorAll('input,textarea').forEach(input => input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`))); }
    function recalculate() {
        let grand = 0;
        rows.querySelectorAll('tr').forEach(row => {
            const qty = row.querySelector('[name$="[quantity]"]').value;
            const unit = row.querySelector('[name$="[unit_cost]"]').value;
            const total = row.querySelector('[name$="[total_cost]"]');
            total.value = qty !== '' && unit !== '' ? (Number(qty) * Number(unit)).toFixed(2) : '';
            grand += Number(total.value) || 0;
        });
        document.getElementById('abc-grand-total').textContent = grand.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
    }
    function collect() {
        recalculate();
        const data = {project:form.elements.project.value, location:form.elements.location.value, items:[...rows.querySelectorAll('tr')].map(row => Object.fromEntries(fields.map(field => [field,row.querySelector(`[name$="[${field}]"]`)?.value ?? ''])))};
        slots.forEach(slot => { data[`${slot}_name`] = form.elements[`${slot}_name`].value; data[`${slot}_title`] = form.elements[`${slot}_title`].value; });
        return data;
    }
    function syncPrint() {
        recalculate();
        form.querySelector('.abc-project-print').textContent = form.elements.project.value;
        form.querySelector('.abc-location-print').textContent = form.elements.location.value;
        rows.querySelectorAll('td').forEach(td => {
            const input = td.querySelector('input,textarea');
            const print = td.querySelector('.abc-print');
            if (input && print) print.textContent = input.type === 'number' && input.value !== '' ? Number(input.value).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}) : (input.value || '');
        });
        slots.forEach(slot => ['name','title'].forEach(part => {
            const input = form.elements[`${slot}_${part}`];
            input.nextElementSibling.textContent = input.value;
        }));
    }
    async function save() {
        if (saving) { again = true; return; }
        saving = true; status.textContent = 'Saving...'; status.dataset.state = '';
        try {
            const response = await fetch(form.dataset.draftUrl, {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':form.querySelector('[name="_token"]').value}, body:JSON.stringify({revision,draft:collect()})});
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Could not save this draft.');
            revision = data.revision; dirty = false; status.textContent = 'Draft saved'; status.dataset.state = 'saved';
        } catch (error) { status.textContent = error.message; status.dataset.state = 'error'; }
        finally { saving = false; if (again) { again = false; save(); } }
    }
    function changed() { dirty = true; status.textContent = 'Unsaved changes'; status.dataset.state = ''; clearTimeout(timer); timer = setTimeout(save, 1200); syncPrint(); }
    function updateActions() { document.getElementById('abc-add').disabled = rows.children.length >= 500; document.getElementById('abc-remove').disabled = rows.children.length <= 1; }
    form.addEventListener('input', changed); form.addEventListener('change', changed);
    document.getElementById('abc-save').addEventListener('click', () => { clearTimeout(timer); save(); });
    document.getElementById('abc-print').addEventListener('click', () => { syncPrint(); window.print(); });
    document.getElementById('abc-add').addEventListener('click', () => {
        if (rows.children.length >= 500) return;
        const row = rows.querySelector('tr').cloneNode(true);
        row.querySelectorAll('input,textarea').forEach(input => input.value = '');
        row.querySelectorAll('.abc-print').forEach(span => span.textContent = '');
        rows.appendChild(row); reindex(); updateActions(); changed(); row.querySelector('textarea,input').focus();
    });
    document.getElementById('abc-remove').addEventListener('click', () => { if (rows.children.length > 1) { rows.lastElementChild.remove(); reindex(); updateActions(); changed(); } });
    form.addEventListener('submit', () => { clearTimeout(timer); syncPrint(); });
    window.addEventListener('beforeprint', syncPrint);
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    updateActions(); syncPrint();
});
</script>
@endsection
