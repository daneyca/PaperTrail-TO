@extends('layouts.dashboard')

@section('title', 'Annual Investment Program | PaperTrail')

@section('content')
@php
    $aipDraftData = ($draft ?? null)?->payload ?? [];
    $aipInitialYear = old('year', data_get($aipDraftData, 'year', now()->year));
    $aipSavedAt = ($draft ?? null)?->updated_at?->toIso8601String();
    $aipDraftRevision = ($draft ?? null)?->revision ?? 0;
@endphp
<style>
    .aip-toolbar { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:14px; padding:12px 16px; border:1px solid #d8e0eb; background:#fff; }
    .aip-toolbar strong { margin-right:auto; color:#0b2341; font-size:1rem; }
    .aip-toolbar a, .aip-toolbar button { display:inline-flex; min-height:36px; align-items:center; justify-content:center; gap:6px; padding:0 12px; border:1px solid #cbd5e1; border-radius:6px; background:#fff; color:#0b2341; font:700 .82rem Arial,sans-serif; text-decoration:none; cursor:pointer; }
    .aip-toolbar button[type="submit"] { border-color:#0b2341; background:#0b2341; color:#fff; }
    .aip-toolbar button:disabled { opacity:.45; cursor:not-allowed; }
    .aip-draft-status { font-size:.78rem; font-weight:600; color:#52647d; white-space:nowrap; }
    .aip-draft-status[data-state="saved"] { color:#087443; }
    .aip-draft-status[data-state="error"] { color:#b42318; }
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
    .aip-table .aip-section.aip-subheading th { padding-left:14px; font-size:7.5pt; }
    .aip-section-content { display:flex; align-items:center; gap:8px; }
    body.dashboard-body #aip-form .aip-table .aip-section-title { flex:0 1 320px; min-width:180px; height:24px !important; min-height:24px !important; border:0 !important; border-radius:0 !important; box-shadow:none !important; padding:0 3px !important; background:transparent !important; color:#111; font:700 8pt Arial,sans-serif !important; text-transform:uppercase; outline:none; }
    .aip-section-title:focus { box-shadow:inset 0 -1px #111; }
    .aip-section-tools { display:flex; flex:none; align-items:center; gap:6px; white-space:nowrap; }
    .aip-section-tools button { flex:none; border:1px solid #655b35; border-radius:4px; padding:3px 8px; background:#fff; color:#111; font:700 7pt Arial,sans-serif; white-space:nowrap; cursor:pointer; }
    .aip-section-tools button:disabled { opacity:.45; cursor:not-allowed; }
    body.dashboard-body #aip-form .aip-section-tools input[type="color"] { flex:none; width:25px !important; min-width:25px !important; max-width:25px !important; height:24px !important; min-height:24px !important; border:0 !important; border-radius:2px !important; padding:0 !important; background:transparent !important; cursor:pointer; }
    .aip-section-group[data-section-type="subheading"] .aip-main-only { display:none; }
    .aip-table .aip-entry { min-height:38px; }
    .aip-table .aip-entry.is-selected { background:#e9f2ff; }
    body.dashboard-body #aip-form .aip-table .aip-entry input, body.dashboard-body #aip-form .aip-table .aip-entry textarea { display:block; width:100%; min-width:0; min-height:0 !important; border:0 !important; border-radius:0 !important; box-shadow:none !important; padding:1px !important; background:transparent !important; color:#111; font:inherit !important; line-height:1.2; outline:none; resize:none; }
    .aip-table .aip-entry input:focus, .aip-table .aip-entry textarea:focus { background:#e9f2ff !important; box-shadow:inset 0 -1px #2563eb !important; }
    .aip-table .aip-entry input[type="number"], .aip-table .aip-row-total, .aip-grand-total td:nth-child(n+8) { text-align:right; }
    .aip-table .aip-row-total { display:block; }
    .aip-grand-total td { font-weight:700; }
    .aip-signatures { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:25mm; margin:26mm 5mm 0; break-inside:avoid; }
    .aip-signatures section { min-width:0; }
    .aip-signatures strong { display:block; margin-bottom:14mm; font-size:9pt; }
    .aip-signatures input { display:block; width:100%; border:0; border-bottom:1px solid #333; border-radius:0; padding:3px 2px; color:#111; text-align:center; background:transparent; font:inherit; }
    .aip-signatures .aip-name { font-weight:700; text-transform:uppercase; }
    .aip-signatures input::placeholder { color:#64748b; opacity:1; font-weight:400; text-transform:none; }
    .aip-signatures .aip-date { margin-top:8px; text-align:left; }
    .aip-signatures .aip-signer-account { margin:0 0 10px; border:1px solid #cbd5e1; padding:6px; }
    .aip-signatures .aip-signer-account span { display:block; margin-bottom:4px; font:700 7pt Arial,sans-serif; }
    .aip-signatures .aip-signer-account b { display:block; font:700 7.5pt Arial,sans-serif; }
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
        .aip-table .aip-entry input:focus, .aip-table .aip-entry textarea:focus { background:transparent !important; box-shadow:none !important; }
        .aip-meta input, .aip-signatures input { outline:0; }
        .aip-signatures input::placeholder { color:transparent !important; }
        .aip-print-hidden { display:none !important; }
    }
</style>

<form id="aip-form" method="POST" action="{{ route('bac-secretariat.competitive-bidding.annual-investment-program.export') }}" data-draft-url="{{ route('bac-secretariat.competitive-bidding.annual-investment-program.draft.store') }}">
    @csrf
    <div class="aip-toolbar no-print">
        <strong>Annual Investment Program</strong>
        <span class="aip-draft-status" id="aip-draft-status" role="status" aria-live="polite">{{ $aipSavedAt ? 'Saved draft' : 'Not saved yet' }}</span>
        <a href="{{ route('bac-secretariat.competitive-bidding.menu', ['task' => 'annual-investment-program']) }}">Back</a>
        <button type="button" id="aip-save-draft">Save Draft</button>
        <button type="button" id="aip-new-draft">Start New</button>
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
                <h1>CY <span data-aip-year>{{ $aipInitialYear }}</span> Annual Investment Program (AIP)</h1>
                <p>By Program / Project / Activity by Sector</p>
                <p>As of January 1 to December 31, <span data-aip-year>{{ $aipInitialYear }}</span></p>
            </header>

            <div class="aip-meta">
                <label>Municipality: <input type="text" name="municipality" value="{{ old('municipality', data_get($aipDraftData, 'municipality', 'Tomas Oppus')) }}" required maxlength="120"></label>
                <label>CY <input type="number" name="year" value="{{ $aipInitialYear }}" min="2000" max="2100" required></label>
                <label><input type="checkbox" name="climate_change" value="1" @checked(old('climate_change', data_get($aipDraftData, 'climate_change')))> Climate Change Expenditure (Please check box if your LGU does not have any climate change expenditure)</label>
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
                <tbody id="aip-total-body">
                    <tr class="aip-grand-total">
                        <td></td><td>TOTAL</td><td colspan="5"></td>
                        <td data-total="ps"></td><td data-total="mooe"></td><td data-total="co"></td><td data-total="all"></td>
                        <td colspan="3"></td>
                    </tr>
                </tbody>
            </table>

            <div class="aip-signatures">
                @foreach (['prepared' => 'Prepared by:', 'reviewed' => 'Reviewed by:', 'approved' => 'Approved by:'] as $key => $label)
                    <section>
                        <strong>{{ $label }}</strong>
                        <div class="aip-signer-account no-print"><span>Recipient account</span><b>{{ $signerAccounts[$key]?->name ?? 'Unavailable' }} ({{ \App\Services\AnnualInvestmentProgramService::RECIPIENT_ACCOUNTS[$key]['user_id'] }})</b></div>
                        <input class="aip-name" type="text" name="{{ $key }}_name" value="{{ old($key.'_name', data_get($aipDraftData, $key.'_name')) }}" placeholder="Enter Name" aria-label="{{ $label }} name" maxlength="120">
                        <input type="text" name="{{ $key }}_title" value="{{ old($key.'_title', data_get($aipDraftData, $key.'_title')) }}" placeholder="Enter Position" aria-label="{{ $label }} position" maxlength="120">
                        <input class="aip-date" type="text" name="{{ $key }}_date" value="{{ old($key.'_date', data_get($aipDraftData, $key.'_date')) }}" placeholder="Date: ____________________" aria-label="{{ $label }} date" maxlength="80">
                    </section>
                @endforeach
            </div>
        </article>
    </div>
</form>

<template id="aip-section-template">
        <tr class="aip-section __SUBHEADING__" style="--aip-section-color:__COLOR__"><th colspan="14"><div class="aip-section-content">
            <input type="hidden" name="sections[__SECTION_INDEX__][key]" value="__KEY__">
            <input type="hidden" name="sections[__SECTION_INDEX__][type]" value="__TYPE__">
            <input class="aip-section-title" type="text" name="sections[__SECTION_INDEX__][label]" aria-label="Section name" maxlength="100" required>
            <span class="aip-section-tools no-print">
                <input type="color" name="sections[__SECTION_INDEX__][color]" value="__COLOR__" title="Band color" aria-label="Band color">
                <button type="button" data-aip-action="add-row">Add Row</button>
                <button type="button" data-aip-action="add-subheading" class="aip-main-only">Add Subheading</button>
                <button type="button" data-aip-action="add-section" class="aip-main-only">Add Section</button>
                <button type="button" data-aip-action="move-up">Move Up</button>
                <button type="button" data-aip-action="move-down">Move Down</button>
                <button type="button" data-aip-action="remove-section">Remove</button>
            </span>
        </div></th></tr>
</template>

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

@php
    $aipInitialSections = array_values(old('sections', $aipDraftData['sections'] ?? [
        ['key' => 'general', 'type' => 'section', 'label' => old('section_labels.general', 'GENERAL SERVICES'), 'color' => old('section_colors.general', '#f4df9c')],
        ['key' => 'other', 'type' => 'section', 'label' => old('section_labels.other', 'OTHER SERVICES'), 'color' => old('section_colors.other', '#f4df9c')],
    ]));
    $aipInitialItems = array_values(old('items', $aipDraftData['items'] ?? []));
@endphp
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('aip-form');
    const rowTemplate = document.getElementById('aip-row-template').innerHTML;
    const sectionTemplate = document.getElementById('aip-section-template').innerHTML;
    const totalBody = document.getElementById('aip-total-body');
    const remove = document.getElementById('aip-remove');
    const previousSections = @json($aipInitialSections);
    const previousItems = @json($aipInitialItems);
    const savedAt = @json($aipSavedAt);
    let revision = @json($aipDraftRevision);
    let nextRowIndex = 0;
    let nextSectionIndex = 0;
    let selected = null;
    let initialized = false;
    let dirty = false;
    let blocked = false;
    let leaving = false;
    let saveTimer = null;
    let activeSave = null;
    const draftStatus = document.getElementById('aip-draft-status');
    const saveButton = document.getElementById('aip-save-draft');
    const csrfToken = form.querySelector('input[name="_token"]').value;

    const groups = () => [...form.querySelectorAll('.aip-section-group')];
    const filled = row => [...row.querySelectorAll('input:not([type="hidden"]), textarea')]
        .some(field => String(field.value).trim() !== '');

    function setDraftStatus(message, state = '') {
        draftStatus.textContent = message;
        draftStatus.dataset.state = state;
    }

    function savedLabel(timestamp) {
        if (!timestamp) return 'Draft saved';
        return `Saved at ${new Date(timestamp).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' })}`;
    }

    function collectDraft() {
        const value = name => form.elements.namedItem(name)?.value ?? '';
        const itemFields = ['reference', 'description', 'office', 'start', 'completion', 'output', 'funding', 'ps', 'mooe', 'co', 'adaptation', 'mitigation', 'typology'];
        const draft = {
            year: value('year'),
            municipality: value('municipality'),
            climate_change: form.elements.namedItem('climate_change').checked ? 1 : 0,
            sections: groups().map(group => ({
                key: group.dataset.sectionKey,
                type: group.dataset.sectionType,
                label: group.querySelector('.aip-section-title').value,
                color: group.querySelector('input[type="color"]').value,
            })),
            items: [...form.querySelectorAll('.aip-entry')].map(row => {
                const item = { section: row.querySelector('input[type="hidden"]').value };
                itemFields.forEach(field => { item[field] = row.querySelector(`[name$="[${field}]"]`).value; });
                return item;
            }),
        };
        for (const slot of ['prepared', 'reviewed', 'approved']) {
            for (const field of ['name', 'title', 'date']) draft[`${slot}_${field}`] = value(`${slot}_${field}`);
        }
        return draft;
    }

    function markDirty(delay = 700) {
        if (!initialized || leaving) return;
        dirty = true;
        setDraftStatus(blocked ? 'Draft changed in another tab. Reload to review it.' : 'Unsaved changes', blocked ? 'error' : '');
        clearTimeout(saveTimer);
        if (!blocked) saveTimer = setTimeout(() => { void saveDraft(); }, delay);
    }

    async function saveDraft(force = false) {
        clearTimeout(saveTimer);
        if (blocked || activeSave || (!dirty && !force)) return activeSave;
        const payload = collectDraft();
        dirty = false;
        saveButton.disabled = true;
        setDraftStatus('Saving draft...');
        let saved = false;
        activeSave = (async () => {
            try {
                const response = await fetch(form.dataset.draftUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ revision, draft: payload }),
                });
                const result = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const message = Object.values(result.errors || {}).flat()[0] || result.message || 'The draft could not be saved.';
                    const error = new Error(message);
                    error.status = response.status;
                    throw error;
                }
                revision = result.revision;
                saved = true;
                if (!dirty) setDraftStatus(savedLabel(result.saved_at), 'saved');
            } catch (error) {
                dirty = true;
                blocked = error.status === 409;
                setDraftStatus(blocked ? error.message : `Not saved: ${error.message}`, 'error');
            } finally {
                activeSave = null;
                saveButton.disabled = false;
                if (saved && dirty && !leaving) saveTimer = setTimeout(() => { void saveDraft(); }, 0);
            }
        })();
        return activeSave;
    }

    function addSection(section = {}) {
        let key = /^[a-zA-Z0-9_-]{1,64}$/.test(section.key || '') ? section.key : `section_${nextSectionIndex}`;
        while (groups().some(group => group.dataset.sectionKey === key)) key = `section_${++nextSectionIndex}`;
        const type = section.type === 'subheading' && groups().some(group => group.dataset.sectionType === 'section')
            ? 'subheading' : 'section';
        const color = /^#[0-9a-fA-F]{6}$/.test(section.color || '') ? section.color : '#d9ece8';
        const group = document.createElement('tbody');
        group.className = 'aip-section-group';
        group.dataset.sectionKey = key;
        group.dataset.sectionType = type;
        group.innerHTML = sectionTemplate
            .replaceAll('__SECTION_INDEX__', nextSectionIndex++)
            .replaceAll('__KEY__', key)
            .replaceAll('__TYPE__', type)
            .replaceAll('__SUBHEADING__', type === 'subheading' ? 'aip-subheading' : '')
            .replaceAll('__COLOR__', color);
        group.querySelector('.aip-section-title').value = section.label ?? (type === 'subheading' ? 'NEW SUBHEADING' : 'NEW SECTION');
        totalBody.before(group);
        markDirty();
        return group;
    }

    function addRow(group, focus = true) {
        group.insertAdjacentHTML('beforeend', rowTemplate
            .replaceAll('__INDEX__', nextRowIndex++)
            .replaceAll('__SECTION__', group.dataset.sectionKey));
        const row = group.lastElementChild;
        if (focus) {
            row.querySelector('textarea').focus();
            selectRow(row);
        }
        recalculate();
        markDirty();
        return row;
    }

    function sectionBlock(group) {
        const block = [group];
        if (group.dataset.sectionType === 'section') {
            let next = group.nextElementSibling;
            while (next?.classList.contains('aip-section-group') && next.dataset.sectionType === 'subheading') {
                block.push(next);
                next = next.nextElementSibling;
            }
        }
        return block;
    }

    function moveSection(group, direction) {
        const all = groups();
        if (group.dataset.sectionType === 'section') {
            const mains = all.filter(item => item.dataset.sectionType === 'section');
            const target = mains[mains.indexOf(group) + direction];
            if (!target) return;
            const targetEnd = sectionBlock(target).at(-1);
            const fragment = document.createDocumentFragment();
            sectionBlock(group).forEach(item => fragment.append(item));
            if (direction < 0) target.before(fragment);
            else targetEnd.after(fragment);
        } else {
            if (!canMove(group, direction)) return;
            const neighbor = all[all.indexOf(group) + direction];
            if (direction < 0) neighbor.before(group);
            else neighbor.after(group);
        }
        refreshSections();
        markDirty();
    }

    function canMove(group, direction) {
        const all = groups();
        if (group.dataset.sectionType === 'section') {
            const mains = all.filter(item => item.dataset.sectionType === 'section');
            return Boolean(mains[mains.indexOf(group) + direction]);
        }
        const index = all.indexOf(group);
        const neighbor = all[index + direction];
        if (!neighbor) return false;
        return direction > 0 || neighbor.dataset.sectionType === 'subheading'
            || all.slice(0, index - 1).some(item => item.dataset.sectionType === 'section');
    }

    function refreshMoveControls() {
        groups().forEach(group => {
            for (const [action, direction] of [['move-up', -1], ['move-down', 1]]) {
                const button = group.querySelector(`[data-aip-action="${action}"]`);
                button.disabled = !canMove(group, direction);
                button.title = group.dataset.sectionType === 'subheading'
                    ? `Move subheading ${direction < 0 ? 'up' : 'down'}`
                    : `Move section ${direction < 0 ? 'up' : 'down'}`;
            }
        });
    }

    function removeSection(group) {
        if (group.dataset.sectionType === 'section' && groups().filter(item => item.dataset.sectionType === 'section').length === 1) return;
        const block = sectionBlock(group);
        if (block.some(item => [...item.querySelectorAll('.aip-entry')].some(filled))
            && !window.confirm('Remove this section and its project rows?')) return;
        block.forEach(item => item.remove());
        selectRow(null);
        recalculate();
        markDirty();
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
            if (!filled(row)) return;
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
        const state = groups().map(group => {
            const rows = [...group.querySelectorAll('.aip-entry')];
            rows.forEach(row => row.classList.toggle('aip-print-hidden', !filled(row)));
            return { group, visible: rows.some(filled) };
        });
        let main = null;
        state.forEach(entry => {
            if (entry.group.dataset.sectionType === 'section') main = entry;
            else if (entry.visible && main) main.visible = true;
        });
        state.forEach(entry => entry.group.classList.toggle('aip-print-hidden', !entry.visible));
        refreshMoveControls();
    }

    remove.addEventListener('click', () => {
        if (!selected) return;
        selected?.remove();
        selectRow(null);
        recalculate();
        markDirty();
    });
    form.addEventListener('click', event => {
        const action = event.target.closest('[data-aip-action]');
        if (action) {
            const group = action.closest('.aip-section-group');
            switch (action.dataset.aipAction) {
                case 'add-row': addRow(group); break;
                case 'add-subheading': {
                    const next = sectionBlock(group).at(-1).nextElementSibling;
                    const subheading = addSection({ type: 'subheading' });
                    next.before(subheading);
                    addRow(subheading, false);
                    subheading.querySelector('.aip-section-title').focus();
                    break;
                }
                case 'add-section': {
                    const next = sectionBlock(group).at(-1).nextElementSibling;
                    const section = addSection();
                    next.before(section);
                    addRow(section, false);
                    section.querySelector('.aip-section-title').focus();
                    break;
                }
                case 'move-up': moveSection(group, -1); break;
                case 'move-down': moveSection(group, 1); break;
                case 'remove-section': removeSection(group); break;
            }
            return;
        }
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
        markDirty();
    });
    form.addEventListener('change', () => markDirty());
    previousSections.forEach(section => {
        const group = addSection(section);
        const items = previousItems.filter(item => item.section === group.dataset.sectionKey);
        if (!items.length) addRow(group, false);
        items.forEach(item => {
            const row = addRow(group, false);
            ['reference', 'description', 'office', 'start', 'completion', 'output', 'funding', 'ps', 'mooe', 'co', 'adaptation', 'mitigation', 'typology'].forEach(field => {
                const input = row.querySelector(`[name$="[${field}]"]`);
                input.value = item[field] ?? '';
            });
        });
    });
    recalculate();
    selectRow(null);
    initialized = true;
    if (savedAt) setDraftStatus(savedLabel(savedAt), 'saved');
    if (@json($errors->any())) markDirty();
    saveButton.addEventListener('click', () => { void saveDraft(true); });
    document.getElementById('aip-new-draft').addEventListener('click', async event => {
        if (!window.confirm('Start a new AIP? This will delete your current saved draft. Submitted documents will not be changed.')) return;
        leaving = true;
        clearTimeout(saveTimer);
        if (activeSave) await activeSave;
        event.currentTarget.disabled = true;
        try {
            const response = await fetch(form.dataset.draftUrl, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ revision }),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || 'The draft could not be cleared.');
            window.location.reload();
        } catch (error) {
            leaving = false;
            event.currentTarget.disabled = false;
            setDraftStatus(`Could not start new: ${error.message}`, 'error');
        }
    });
    form.addEventListener('submit', () => { leaving = true; });
    window.addEventListener('beforeunload', event => {
        if (!leaving && (dirty || activeSave)) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    window.addEventListener('beforeprint', refreshSections);
});
</script>
@endsection
