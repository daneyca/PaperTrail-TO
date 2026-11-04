@extends('layouts.dashboard')

@section('title', 'Annual Investment Program | PaperTrail')

@section('content')
<style>
    .aip-toolbar { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:14px; padding:12px 16px; border:1px solid #d8e0eb; background:#fff; }
    .aip-toolbar strong { margin-right:auto; color:#0b2341; font-size:1rem; }
    .aip-toolbar a, .aip-toolbar button { display:inline-flex; min-height:36px; align-items:center; justify-content:center; gap:6px; padding:0 12px; border:1px solid #cbd5e1; border-radius:6px; background:#fff; color:#0b2341; font:700 .82rem Arial,sans-serif; text-decoration:none; cursor:pointer; }
    .aip-toolbar button[type="submit"] { border-color:#0b2341; background:#0b2341; color:#fff; }
    .aip-toolbar button:disabled { opacity:.45; cursor:not-allowed; }
    .aip-scroll { max-width:100%; overflow:auto; padding:12px 0 28px; }
    .aip-sheet { width:400mm; min-height:274mm; margin:auto; padding:10mm 8mm 12mm; border:1px solid #c8d1dd; background:#fff; box-shadow:0 12px 32px rgba(15,23,42,.12); box-sizing:border-box; color:#111; font:7.2pt/1.2 Arial,Helvetica,sans-serif; }
    .aip-sheet * { box-sizing:border-box; }
    .aip-heading { text-align:center; margin-bottom:10px; }
    .aip-heading h1 { margin:0 0 3px; color:#111; font:700 12pt/1.15 Arial,sans-serif; }
    .aip-heading p { margin:2px 0; }
    .aip-meta { display:flex; align-items:center; justify-content:space-between; gap:16px; margin:0 0 8px; font-size:9pt; }
    .aip-meta label { display:flex; align-items:center; gap:4px; }
    .aip-meta input[type="text"], .aip-meta input[type="number"] { border:0; border-bottom:1px solid #555; border-radius:0; background:transparent; color:#111; font:inherit; padding:2px 3px; }
    .aip-meta input[name="year"] { width:56px; }
    .aip-meta input[name="municipality"] { width:180px; }
    .aip-meta input[type="checkbox"] { width:16px; height:16px; accent-color:#111; }
    .aip-table { width:100%; border-collapse:collapse; table-layout:fixed; color:#111; }
    .aip-table col:nth-child(1) { width:8%; }
    .aip-table col:nth-child(2) { width:15%; }
    .aip-table col:nth-child(3) { width:8%; }
    .aip-table col:nth-child(4), .aip-table col:nth-child(5) { width:6.5%; }
    .aip-table col:nth-child(6) { width:13%; }
    .aip-table col:nth-child(7) { width:6%; }
    .aip-table col:nth-child(8), .aip-table col:nth-child(9), .aip-table col:nth-child(10), .aip-table col:nth-child(11) { width:5.5%; }
    .aip-table col:nth-child(12), .aip-table col:nth-child(13) { width:5%; }
    .aip-table col:nth-child(14) { width:5%; }
    .aip-table th, .aip-table td { border:1px solid #333; padding:3px 4px; vertical-align:middle; overflow-wrap:anywhere; }
    .aip-table thead th { font-weight:700; text-align:center; }
    .aip-table thead tr:first-child th { height:30px; }
    .aip-table thead tr:nth-child(2) th { height:38px; }
    .aip-table .aip-number th { height:20px; font-weight:400; }
    .aip-table .aip-section th { padding:5px 6px; background:var(--aip-section-color, #f4df9c) !important; text-align:left; font-size:8pt; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .aip-section-content { display:flex; align-items:center; gap:8px; }
    .aip-section-title { flex:1; min-width:0; border:0; border-radius:0; background:transparent; color:#111; font:700 8pt Arial,sans-serif; text-transform:uppercase; outline:none; }
    .aip-section-title:focus { box-shadow:inset 0 -1px #111; }
    .aip-section-tools { display:flex; align-items:center; gap:6px; }
    .aip-section-tools button { border:1px solid #655b35; border-radius:4px; padding:3px 8px; background:#fff; color:#111; font:700 7pt Arial,sans-serif; cursor:pointer; }
    .aip-section-tools input[type="color"] { width:25px; height:23px; border:0; padding:0; background:transparent; cursor:pointer; }
    .aip-table .aip-entry { min-height:38px; }
    .aip-table .aip-entry.is-selected { background:#e9f2ff; }
    .aip-table .aip-entry input, .aip-table .aip-entry textarea { display:block; width:100%; min-width:0; border:0; border-radius:0; padding:1px; background:transparent; color:#111; font:inherit; line-height:1.2; outline:none; resize:none; }
    .aip-table .aip-entry input:focus, .aip-table .aip-entry textarea:focus { background:#e9f2ff; box-shadow:inset 0 -1px #2563eb; }
    .aip-table .aip-entry input[type="number"], .aip-table .aip-row-total, .aip-grand-total td:nth-child(n+8) { text-align:right; }
    .aip-table .aip-row-total { display:block; }
    .aip-grand-total td { font-weight:700; }
    .aip-signatures { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:25mm; margin:26mm 5mm 0; break-inside:avoid; }
    .aip-signatures section { min-width:0; }
    .aip-signatures strong { display:block; margin-bottom:14mm; font-size:9pt; }
    .aip-signatures input { display:block; width:100%; border:0; border-bottom:1px solid #333; border-radius:0; padding:3px 2px; color:#111; text-align:center; background:transparent; font:inherit; }
    .aip-signatures .aip-name { font-weight:700; text-transform:uppercase; }
    .aip-signatures .aip-date { margin-top:8px; text-align:left; }
    .aip-signatures .aip-signer-account { margin:0 0 10px; border:1px solid #cbd5e1; padding:6px; }
    .aip-signatures .aip-signer-account span { display:block; margin-bottom:4px; font:700 7pt Arial,sans-serif; }
    .aip-signatures .aip-signer-account select { width:100%; min-height:30px; border:1px solid #cbd5e1; border-radius:4px; background:#fff; color:#111; font:7.5pt Arial,sans-serif; }
    .aip-toolbar button.aip-submit-signatures { border-color:#0b2341; background:#0b2341; color:#fff; }
    .aip-errors { margin:0 0 14px; padding:10px 14px; border:1px solid #fecaca; background:#fff1f2; color:#991b1b; }
    @media (max-width:900px) { .aip-sheet { margin-left:0; } }
    @media print {
        @page { size:A4 landscape; margin:6mm; }
        html, body { width:auto !important; min-width:0 !important; }
        .aip-toolbar, .aip-errors, .no-print { display:none !important; }
        body.dashboard-body .dashboard-shell, body.dashboard-body .dashboard-main, body.dashboard-body .dashboard-content,
        body.dashboard-body #dashboardContent, #aip-form, .aip-scroll { display:block !important; visibility:visible !important; width:100% !important; max-width:none !important; height:auto !important; overflow:visible !important; margin:0 !important; padding:0 !important; box-shadow:none !important; }
        .aip-sheet { width:285mm !important; min-height:0 !important; margin:0 !important; padding:0 !important; border:0 !important; box-shadow:none !important; font-size:5.5pt !important; }
        .aip-sheet, .aip-sheet * { visibility:visible !important; }
        .aip-heading h1 { font-size:10pt !important; }
        .aip-meta { font-size:7pt !important; }
        .aip-table th, .aip-table td { padding:2px !important; }
        .aip-table .aip-section th, .aip-section-title { font-size:6pt !important; }
        .aip-signatures { margin-top:15mm !important; gap:12mm !important; }
        .aip-table thead { display:table-header-group; }
        .aip-table tr { break-inside:avoid; }
        .aip-table .aip-entry.is-selected { background:transparent; }
        .aip-table .aip-entry input:focus, .aip-table .aip-entry textarea:focus { background:transparent; box-shadow:none; }
        .aip-meta input, .aip-signatures input { outline:0; }
        .aip-print-hidden { display:none !important; }
    }
</style>

<form id="aip-form" method="POST" action="{{ route('bac-secretariat.competitive-bidding.annual-investment-program.export') }}">
    @csrf
    <div class="aip-toolbar no-print">
        <strong>Annual Investment Program</strong>
        <a href="{{ route('bac-secretariat.competitive-bidding.menu', ['task' => 'annual-investment-program']) }}">Back</a>
        <button type="button" id="aip-remove" disabled>Remove Row</button>
        <button type="button" onclick="window.print()">Print</button>
        <button type="submit">Save Excel</button>
        <button class="aip-submit-signatures" type="submit" formaction="{{ route('bac-secretariat.competitive-bidding.annual-investment-program.submit') }}">Submit for Signatories</button>
    </div>

    @if ($errors->any())
        <div class="aip-errors" role="alert"><strong>Please check the AIP entries:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="aip-scroll">
        <article class="aip-sheet">
            <header class="aip-heading">
                <h1>CY <span data-aip-year>{{ now()->year }}</span> Annual Investment Program (AIP)</h1>
                <p>By Program / Project / Activity by Sector</p>
                <p>As of January 1 to December 31, <span data-aip-year>{{ now()->year }}</span></p>
            </header>

            <div class="aip-meta">
                <label>Municipality: <input type="text" name="municipality" value="{{ old('municipality', 'Tomas Oppus') }}" required maxlength="120"></label>
                <label>CY <input type="number" name="year" value="{{ old('year', now()->year) }}" min="2000" max="2100" required></label>
                <label><input type="checkbox" name="climate_change" value="1" @checked(old('climate_change'))> Climate Change Expenditure (Please check box if your LGU does not have any climate change expenditure)</label>
            </div>

            <table class="aip-table">
                <colgroup>@for ($column = 1; $column <= 14; $column++)<col>@endfor</colgroup>
                <thead>
                    <tr>
                        <th rowspan="2">AIP<br>REFERENCE<br>CODE</th>
                        <th rowspan="2">PROGRAM / PROJECT / ACTIVITY<br>DESCRIPTION</th>
                        <th rowspan="2">Implementing<br>Office / Department</th>
                        <th colspan="2">Schedule of Implementation</th>
                        <th rowspan="2">Expected Output</th>
                        <th rowspan="2">FUNDING<br>SOURCE</th>
                        <th colspan="4">AMOUNT (in thousand pesos)</th>
                        <th colspan="2">AMOUNT OF Climate Change Expenditures (in thousand pesos)</th>
                        <th rowspan="2">CC Typology<br>Code</th>
                    </tr>
                    <tr>
                        <th>Starting<br>Date</th><th>Completion<br>Date</th>
                        <th>Personal Services<br>(PS)</th><th>Maintenance and Other Operating Expenses<br>(MOOE)</th><th>Capital Outlay<br>(CO)</th><th>TOTAL</th>
                        <th>Climate Change<br>Adaptation</th><th>Climate Change<br>Mitigation</th>
                    </tr>
                    <tr class="aip-number">@for ($column = 1; $column <= 14; $column++)<th>({{ $column }})</th>@endfor</tr>
                </thead>
                <tbody id="aip-general">
                    <tr class="aip-section" style="--aip-section-color:#f4df9c"><th colspan="14"><div class="aip-section-content">
                        <input class="aip-section-title" type="text" name="section_labels[general]" value="{{ old('section_labels.general', 'GENERAL SERVICES') }}" aria-label="General Services section name" maxlength="100" required>
                        <span class="aip-section-tools no-print"><input type="color" name="section_colors[general]" value="{{ old('section_colors.general', '#f4df9c') }}" title="General Services band color" aria-label="General Services band color"><button type="button" data-add-aip-row="general">Add Row</button></span>
                    </div></th></tr>
                </tbody>
                <tbody id="aip-other">
                    <tr class="aip-section" style="--aip-section-color:#f4df9c"><th colspan="14"><div class="aip-section-content">
                        <input class="aip-section-title" type="text" name="section_labels[other]" value="{{ old('section_labels.other', 'OTHER SERVICES') }}" aria-label="Other Services section name" maxlength="100" required>
                        <span class="aip-section-tools no-print"><input type="color" name="section_colors[other]" value="{{ old('section_colors.other', '#f4df9c') }}" title="Other Services band color" aria-label="Other Services band color"><button type="button" data-add-aip-row="other">Add Row</button></span>
                    </div></th></tr>
                </tbody>
                <tbody>
                    <tr class="aip-grand-total">
                        <td></td><td>TOTAL</td><td colspan="5"></td>
                        <td data-total="ps"></td><td data-total="mooe"></td><td data-total="co"></td><td data-total="all"></td>
                        <td colspan="3"></td>
                    </tr>
                </tbody>
            </table>

            <div class="aip-signatures">
                @foreach (['prepared' => ['Prepared by:', 'Local Planning and Development Coordinator'], 'reviewed' => ['Reviewed by:', 'Local Budget Officer'], 'approved' => ['Approved by:', 'Municipal Mayor']] as $key => [$label, $title])
                    <section>
                        <strong>{{ $label }}</strong>
                        <label class="aip-signer-account no-print"><span>Send to account</span><select name="signers[{{ $key }}]" data-aip-signer="{{ $key }}" aria-label="{{ $label }} account">
                            <option value="">Select account</option>
                            @foreach ($signerCandidates[$key] as $candidate)
                                <option value="{{ $candidate->id }}" data-name="{{ $candidate->name }}" @selected(old('signers.'.$key) == $candidate->id)>{{ $candidate->name }} ({{ $candidate->user_id }})</option>
                            @endforeach
                        </select></label>
                        <input class="aip-name" type="text" name="{{ $key }}_name" value="{{ old($key.'_name') }}" aria-label="{{ $label }} name" maxlength="120">
                        <input type="text" name="{{ $key }}_title" value="{{ old($key.'_title', $title) }}" aria-label="{{ $label }} position" maxlength="120">
                        <input class="aip-date" type="text" name="{{ $key }}_date" value="{{ old($key.'_date') }}" placeholder="Date: ____________________" aria-label="{{ $label }} date" maxlength="80">
                    </section>
                @endforeach
            </div>
        </article>
    </div>
</form>

<template id="aip-row-template">
    <tr class="aip-entry" tabindex="0">
        <td><input type="hidden" name="items[__INDEX__][section]" value="__SECTION__"><textarea name="items[__INDEX__][reference]" rows="2" aria-label="AIP reference code"></textarea></td>
        <td><textarea name="items[__INDEX__][description]" rows="2" aria-label="Program, project, or activity description"></textarea></td>
        <td><textarea name="items[__INDEX__][office]" rows="2" aria-label="Implementing office or department"></textarea></td>
        <td><textarea name="items[__INDEX__][start]" rows="2" aria-label="Starting date"></textarea></td>
        <td><textarea name="items[__INDEX__][completion]" rows="2" aria-label="Completion date"></textarea></td>
        <td><textarea name="items[__INDEX__][output]" rows="2" aria-label="Expected output"></textarea></td>
        <td><textarea name="items[__INDEX__][funding]" rows="2" aria-label="Funding source"></textarea></td>
        <td><input type="number" name="items[__INDEX__][ps]" min="0" step="0.01" aria-label="Personal Services amount"></td>
        <td><input type="number" name="items[__INDEX__][mooe]" min="0" step="0.01" aria-label="MOOE amount"></td>
        <td><input type="number" name="items[__INDEX__][co]" min="0" step="0.01" aria-label="Capital Outlay amount"></td>
        <td><span class="aip-row-total"></span></td>
        <td><input type="number" name="items[__INDEX__][adaptation]" min="0" step="0.01" aria-label="Climate change adaptation amount"></td>
        <td><input type="number" name="items[__INDEX__][mitigation]" min="0" step="0.01" aria-label="Climate change mitigation amount"></td>
        <td><textarea name="items[__INDEX__][typology]" rows="2" aria-label="CC typology code"></textarea></td>
    </tr>
</template>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('aip-form');
    const template = document.getElementById('aip-row-template').innerHTML;
    const remove = document.getElementById('aip-remove');
    const previousItems = @json(array_values(old('items', [])));
    let nextIndex = 0;
    let selected = null;

    function addRow(section) {
        const tbody = document.getElementById(section === 'general' ? 'aip-general' : 'aip-other');
        tbody.insertAdjacentHTML('beforeend', template.replaceAll('__INDEX__', nextIndex++).replaceAll('__SECTION__', section));
        const row = tbody.lastElementChild;
        row.querySelector('textarea').focus();
        selectRow(row);
        recalculate();
    }

    function selectRow(row) {
        selected?.classList.remove('is-selected');
        selected = row;
        selected?.classList.add('is-selected');
        remove.disabled = !selected;
    }

    function recalculate() {
        const sums = { ps: 0, mooe: 0, co: 0, all: 0 };
        form.querySelectorAll('.aip-entry').forEach(row => {
            let total = 0;
            ['ps', 'mooe', 'co'].forEach(key => {
                const value = Number(row.querySelector(`[name$="[${key}]"]`).value) || 0;
                sums[key] += value;
                total += value;
            });
            sums.all += total;
            row.querySelector('.aip-row-total').textContent = total ? total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
        });
        Object.entries(sums).forEach(([key, value]) => {
            form.querySelector(`[data-total="${key}"]`).textContent = value.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        });
        refreshSections();
    }

    function refreshSections() {
        ['general', 'other'].forEach(section => {
            const body = document.getElementById(`aip-${section}`);
            const hasEntries = [...body.querySelectorAll('.aip-entry')].some(row =>
                [...row.querySelectorAll('input:not([type="hidden"]), textarea')].some(field => String(field.value).trim() !== '')
            );
            body.classList.toggle('aip-print-hidden', !hasEntries);
        });
    }

    form.querySelectorAll('[data-add-aip-row]').forEach(button => {
        button.addEventListener('click', () => addRow(button.dataset.addAipRow));
    });
    remove.addEventListener('click', () => {
        selected?.remove();
        selectRow(null);
        recalculate();
    });
    form.addEventListener('click', event => {
        const row = event.target.closest('.aip-entry');
        if (row) selectRow(row);
    });
    form.addEventListener('focusin', event => {
        const row = event.target.closest('.aip-entry');
        if (row) selectRow(row);
    });
    form.addEventListener('input', event => {
        if (event.target.name === 'year') {
            document.querySelectorAll('[data-aip-year]').forEach(el => { el.textContent = event.target.value; });
        }
        if (event.target.type === 'color') {
            event.target.closest('.aip-section').style.setProperty('--aip-section-color', event.target.value);
        }
        if (event.target.matches('textarea')) {
            event.target.style.height = 'auto';
            event.target.style.height = `${event.target.scrollHeight}px`;
        }
        if (event.target.type === 'number') recalculate();
        else if (event.target.closest('.aip-entry')) refreshSections();
    });
    form.querySelectorAll('[name^="section_colors["]').forEach(color => {
        color.closest('.aip-section').style.setProperty('--aip-section-color', color.value);
    });
    form.querySelectorAll('[data-aip-signer]').forEach(select => {
        select.addEventListener('change', () => {
            const selectedName = select.selectedOptions[0]?.dataset.name || '';
            form.elements[`${select.dataset.aipSigner}_name`].value = selectedName;
        });
    });
    if (previousItems.length) {
        previousItems.forEach(item => {
            if (!['general', 'other'].includes(item.section)) return;
            addRow(item.section);
            const row = document.getElementById(`aip-${item.section}`).lastElementChild;
            Object.entries(item).forEach(([field, value]) => {
                const input = row.querySelector(`[name$="[${field}]"]`);
                if (input) input.value = value ?? '';
            });
        });
    } else {
        addRow('general');
        addRow('other');
    }
    recalculate();
    selectRow(null);
    window.addEventListener('beforeprint', refreshSections);
});
</script>
@endsection
