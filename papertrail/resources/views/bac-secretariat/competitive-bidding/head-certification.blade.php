@extends('layouts.dashboard')

@section('title', 'Certification of the Head of the BAC Secretariat | PaperTrail')

@section('content')
@php
    $saved = $draft?->payload ?? [];
    $recordId = old('record_id', $saved['record_id'] ?? '');
    $selectedRecord = $recordOptions->get((int) $recordId);
    $data = [
        'project' => old('project', $saved['project'] ?? ($selectedRecord['project'] ?? '')),
        'budget' => old('budget', $saved['budget'] ?? ($selectedRecord['budget_raw'] ?? '')),
        'budget_words' => old('budget_words', $saved['budget_words'] ?? ($selectedRecord['budget_words'] ?? '')),
        'published_on' => old('published_on', $saved['published_on'] ?? ''),
        'closing_on' => old('closing_on', $saved['closing_on'] ?? ($selectedRecord['closing_on'] ?? '')),
        'certified_on' => old('certified_on', $saved['certified_on'] ?? now('Asia/Manila')->toDateString()),
        'head_name' => old('head_name', $saved['head_name'] ?? ''),
        'head_title' => old('head_title', $saved['head_title'] ?? ''),
        'chair_name' => old('chair_name', $saved['chair_name'] ?? ''),
        'chair_title' => old('chair_title', $saved['chair_title'] ?? ''),
    ];
@endphp
<style>
    .cert-page { color:#102743; }
    .cert-top { display:flex; justify-content:space-between; align-items:end; flex-wrap:wrap; gap:12px; padding:0 0 14px; }
    .cert-file { display:block; width:min(480px,100%); font-size:.76rem; font-weight:700; color:#40526a; }
    .cert-file select { display:block; width:100%; min-height:38px; margin-top:5px; padding:7px 9px; border:1px solid #bfcee0; border-radius:5px; background:#fff; color:#102743; font:inherit; font-size:.85rem; }
    .cert-actions { display:flex; flex-wrap:wrap; gap:8px; }
    .cert-actions button { display:inline-flex; align-items:center; justify-content:center; gap:7px; min-height:38px; padding:8px 13px; border:1px solid #bfcce0; border-radius:5px; background:#fff; color:#102743; font:inherit; font-weight:700; cursor:pointer; }
    .cert-actions button[type=submit] { background:#102c50; border-color:#102c50; color:#fff; }
    .cert-actions button:disabled { opacity:.5; cursor:not-allowed; }
    .cert-actions svg { width:16px; height:16px; }
    .cert-message { margin:0 0 14px; padding:10px 12px; border:1px solid #bfcfe1; background:#fff; font-size:.82rem; }
    .cert-message--error { border-color:#de9b9b; color:#842d2d; }
    .cert-document-scroll { overflow:auto; padding:18px; background:#e9eef5; }
    .cert-document-scroll .bac-head-certification { box-shadow:0 3px 18px rgba(14,35,64,.12); }
    @media (max-width:560px) { .cert-document-scroll { padding:0; } }
    @media print { .cert-top,.cert-message { display:none !important; } .cert-document-scroll { overflow:visible !important; padding:0 !important; background:#fff !important; } }
</style>

<div class="cert-page">
    <form id="certification-form" method="POST" action="{{ route('bac-secretariat.competitive-bidding.certification.submit') }}">
        @csrf
        <div class="cert-top no-print">
            <label class="cert-file">Bidding File
                <select name="record_id" required>
                    <option value="">Select bidding file</option>
                    @foreach ($records as $record)
                        <option value="{{ $record->id }}" @selected((string) $recordId === (string) $record->id)>{{ $record->reference_number }} - {{ $record->project_title }}</option>
                    @endforeach
                </select>
            </label>
            <div class="cert-actions">
                <button type="button" id="certification-print"><x-papertrail.icon name="print" />Print</button>
                <button type="submit" @disabled($records->isEmpty() || ! $signers['head'] || ! $signers['chair'])><x-papertrail.icon name="signature" />Submit for Signatures</button>
            </div>
        </div>

        @if ($errors->any())
            <div class="cert-message cert-message--error no-print" role="alert">{{ $errors->first() }}</div>
        @endif
        @if ($records->isEmpty())
            <div class="cert-message no-print">BACSEC-001 must create a bidding file before this certification can be submitted.</div>
        @elseif (! $signers['head'] || ! $signers['chair'])
            <div class="cert-message cert-message--error no-print">The BACSEC-002 and BACCHAIR-001 signing accounts must be active before submission.</div>
        @endif
        <div id="certification-save-status" class="cert-message no-print" role="status" hidden></div>

        <div class="cert-document-scroll" id="certification-preview">
            @include('bac-secretariat.competitive-bidding.partials.head-certification-document', ['data' => $data, 'editable' => true, 'signers' => $signers])
        </div>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('certification-form');
    const preview = document.getElementById('certification-preview');
    const status = document.getElementById('certification-save-status');
    const options = @json($recordOptions);
    let revision = {{ (int) ($draft?->revision ?? 0) }};
    let timer;
    let saving = false;
    let dirty = false;
    let submitting = false;

    const field = (name) => form.elements.namedItem(name);
    const show = (name, value) => {
        const node = preview.querySelector(`[data-cert-preview="${name}"]`);
        if (node) node.textContent = value || '________________';
    };
    const dateText = (value) => value
        ? new Date(`${value}T00:00:00`).toLocaleDateString('en-US', { month:'long', day:'numeric', year:'numeric' })
        : '________________';
    const render = () => {
        const budget = Number(field('budget').value);
        show('project', field('project').value);
        show('budget', field('budget').value && Number.isFinite(budget) ? budget.toLocaleString('en-US', { minimumFractionDigits:2, maximumFractionDigits:2 }) : '');
        show('budget_words', field('budget_words').value);
        show('published_on', dateText(field('published_on').value));
        show('closing_on', dateText(field('closing_on').value));
        const certified = field('certified_on').value;
        const day = certified ? Number(certified.slice(-2)) : 0;
        const suffix = day % 100 >= 11 && day % 100 <= 13 ? 'th' : ({ 1:'st', 2:'nd', 3:'rd' }[day % 10] || 'th');
        show('certified_day', certified ? `${day}${suffix}` : '____');
        show('certified_month_year', certified ? new Date(`${certified}T00:00:00`).toLocaleDateString('en-US', { month:'long', year:'numeric' }).replace(' ', ', ') : '________________');
        ['head_name', 'head_title', 'chair_name', 'chair_title'].forEach((name) => show(name, field(name).value));
    };
    const payload = () => Object.fromEntries(['record_id', 'project', 'budget', 'published_on', 'closing_on', 'certified_on', 'budget_words', 'head_name', 'head_title', 'chair_name', 'chair_title'].map((name) => [name, field(name).value || null]));
    const save = async () => {
        if (saving || !dirty || submitting) return;
        saving = true;
        dirty = false;
        try {
            const response = await fetch(@json(route('bac-secretariat.competitive-bidding.certification.draft.store')), {
                method:'POST', credentials:'same-origin', headers:{ 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN':@json(csrf_token()) },
                body:JSON.stringify({ revision, draft:payload() }),
            });
            if (!response.ok) throw new Error(response.status === 409 ? 'Draft changed in another tab. Reload before continuing.' : 'Draft could not be saved.');
            revision = (await response.json()).revision;
            status.hidden = true;
        } catch (error) {
            status.textContent = error.message;
            status.hidden = false;
        } finally {
            saving = false;
            if (dirty) timer = setTimeout(save, 600);
        }
    };
    const changed = () => { render(); dirty = true; clearTimeout(timer); timer = setTimeout(save, 600); };
    form.addEventListener('input', changed);
    form.addEventListener('change', (event) => {
        if (event.target.name === 'record_id') {
            const record = options[field('record_id').value] || {};
            field('project').value = record.project || '';
            field('budget').value = record.budget_raw || '';
            field('budget_words').value = record.budget_words || '';
            field('closing_on').value = record.closing_on || '';
        }
        changed();
    });
    form.addEventListener('submit', () => { submitting = true; clearTimeout(timer); });
    document.getElementById('certification-print').addEventListener('click', () => window.print());
    render();
});
</script>
@endsection
