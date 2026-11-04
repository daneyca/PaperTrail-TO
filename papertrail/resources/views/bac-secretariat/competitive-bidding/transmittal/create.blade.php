@extends('layouts.dashboard')

@section('title', 'Prepare COA Cover Letter | PaperTrail')

@section('content')
<style>
    .coa-editor-bar { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:14px; padding:12px 16px; border:1px solid #d8e0eb; background:#fff; }
    .coa-editor-bar h1 { margin:0; color:#0b2341; font-size:1.1rem; }
    .coa-editor-bar p { margin:2px 0 0; color:#52647d; font-size:.82rem; }
    .coa-editor-bar a, .coa-editor-bar button { display:inline-flex; min-height:38px; align-items:center; gap:7px; border:1px solid #cbd5e1; border-radius:6px; padding:0 13px; background:#fff; color:#0b2341; font:700 .8rem Arial,sans-serif; text-decoration:none; cursor:pointer; }
    .coa-editor-bar button { border-color:#0b2341; background:#0b2341; color:#fff; }
    .coa-editor-bar button:disabled { opacity:.5; cursor:not-allowed; }
    .coa-editor-actions { display:flex; flex-wrap:wrap; gap:8px; }
    .coa-editor-errors { margin:0 0 14px; padding:12px 16px; border:1px solid #fecaca; background:#fff1f2; color:#991b1b; }
    .coa-editor-checklist { margin-bottom:14px; border:1px solid #d8e0eb; background:#fff; }
    .coa-editor-checklist summary { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 16px; cursor:pointer; font-size:.84rem; font-weight:700; color:#0b2341; }
    .coa-editor-checklist summary small { font-size:.75rem; font-weight:600; color:#52647d; }
    .coa-editor-checklist table { width:100%; border-collapse:collapse; font-size:.78rem; }
    .coa-editor-checklist th, .coa-editor-checklist td { border-top:1px solid #e3eaf2; padding:8px 12px; text-align:left; }
    .coa-editor-checklist th:first-child, .coa-editor-checklist td:first-child { width:50px; text-align:center; }
    .coa-editor-checklist th:last-child, .coa-editor-checklist td:last-child { width:140px; }
    .coa-editor-checklist input[type=checkbox] { width:16px; height:16px; accent-color:#18845b; }
    .coa-editor-ready { color:#08744d; font-weight:700; }
    .coa-editor-controls { display:grid; grid-template-columns:minmax(200px,1fr) minmax(280px,2fr); gap:16px; margin-bottom:14px; padding:16px; border:1px solid #d8e0eb; background:#fff; }
    .coa-editor-controls label { display:grid; gap:6px; color:#455c78; font-size:.75rem; font-weight:700; }
    .coa-editor-controls select, .coa-editor-controls textarea { width:100%; border:1px solid #cbd5e1; border-radius:4px; padding:9px 10px; background:#fff; color:#111; font:400 .83rem Arial,sans-serif; }
    .coa-editor-controls textarea { min-height:70px; resize:vertical; }
    .coa-editor-controls .coa-editor-confirm { display:flex; align-items:flex-start; gap:8px; grid-column:1/-1; font-size:.8rem; font-weight:500; color:#0b2341; }
    .coa-editor-confirm[hidden] { display:none !important; }
    .coa-editor-confirm input { margin-top:2px; width:16px; height:16px; flex:0 0 16px; }
    .coa-editor-paper-scroll { overflow:auto; padding:20px 0 30px; background:#eef2f6; }
    .coa-editor-paper { width:210mm; min-height:297mm; margin:auto; padding:18mm 18mm 20mm; box-sizing:border-box; background:#fff; box-shadow:0 8px 28px rgba(15,23,42,.12); color:#111; font:11pt/1.4 Arial,Helvetica,sans-serif; }
    .coa-editor-paper * { box-sizing:border-box; }
    .coa-editor-letterhead { display:grid; grid-template-columns:24mm 1fr 24mm; align-items:center; gap:4mm; padding-bottom:5mm; border-bottom:2px solid #3d8aa5; text-align:center; }
    .coa-editor-letterhead img { width:23mm; height:23mm; object-fit:contain; }
    .coa-editor-letterhead p { margin:0; }
    .coa-editor-letterhead strong { display:block; margin:1mm 0 2mm; font-size:12pt; }
    .coa-editor-letterhead b { display:block; margin-top:3mm; font-size:11pt; }
    .coa-editor-paper input, .coa-editor-paper textarea { display:block; width:100%; border:0; border-bottom:1px solid #aab7c6; border-radius:0; padding:2px 0; background:transparent; color:#111; font:inherit; }
    .coa-editor-paper textarea { min-height:44px; resize:vertical; }
    .coa-editor-paper input:focus, .coa-editor-paper textarea:focus { outline:0; border-color:#1f5eff; background:#f5f9ff; }
    .coa-editor-date { margin:10mm 0 8mm; max-width:180px; }
    .coa-editor-recipient { margin:0 0 9mm; }
    .coa-editor-recipient input { margin-bottom:2mm; }
    .coa-editor-salutation { margin:0 0 8mm; max-width:260px; }
    .coa-editor-opening { margin:0 0 2mm; }
    .coa-editor-project { margin:0 0 5mm; font-weight:700 !important; }
    .coa-editor-paper ol { margin:0 0 8mm 10mm; padding-left:7mm; }
    .coa-editor-paper li { padding-left:1mm; margin-bottom:2mm; }
    .coa-editor-closing { margin-top:7mm; }
    .coa-editor-signature { margin-top:15mm; }
    .coa-editor-signature p { margin:0 0 18mm; }
    .coa-editor-signature strong { display:block; text-transform:uppercase; }
    .coa-editor-previous { margin-top:14px; border:1px solid #d8e0eb; background:#fff; }
    .coa-editor-previous h2 { margin:0; padding:12px 16px; border-bottom:1px solid #d8e0eb; font-size:.9rem; }
    .coa-editor-previous ul { margin:0; padding:0; list-style:none; }
    .coa-editor-previous li { display:flex; justify-content:space-between; gap:12px; padding:9px 16px; border-bottom:1px solid #edf1f6; font-size:.8rem; }
    .coa-editor-previous a { color:#1558a3; }
    @media (max-width:750px) { .coa-editor-controls { grid-template-columns:1fr; } .coa-editor-paper { margin-left:0; } .coa-editor-checklist th:last-child, .coa-editor-checklist td:last-child { width:auto; } }
</style>

<form method="POST" action="{{ route('bac-secretariat.competitive-bidding.transmittal.store', $record) }}">
    @csrf
    <div class="coa-editor-bar">
        <div><h1>COA Cover Letter</h1><p>{{ $record->reference_number }} - {{ $record->project_title }}</p></div>
        <div class="coa-editor-actions">
            <a href="{{ route('bac-secretariat.competitive-bidding.supporting-documents.show', $record) }}">Bidding File</a>
            <button type="submit" @disabled($mayors->isEmpty())>Submit to Mayor</button>
        </div>
    </div>

    @if ($errors->any())
        <div class="coa-editor-errors" role="alert"><strong>Review the cover letter:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <details class="coa-editor-checklist" @if ($errors->has('confirm_packet')) open @endif>
        <summary><span>Attachment Status</span><small>{{ $checklist->where('ready', true)->count() }} in file / {{ $checklist->count() }} listed</small></summary>
        <table>
            <thead><tr><th>In file</th><th>Document</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($checklist as $entry)
                    <tr>
                        <td><input type="checkbox" disabled @checked($entry['ready']) aria-label="{{ $entry['name'] }} in file"></td>
                        <td>{{ $entry['name'] }}@if ($entry['supplier_name']) <small>({{ $entry['supplier_name'] }})</small>@endif</td>
                        <td class="{{ $entry['ready'] ? 'coa-editor-ready' : '' }}">{{ $entry['status'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </details>

    <div class="coa-editor-controls">
        <label>Municipal Mayor / HOPE account
            <select name="mayor_user_id" id="coa-mayor" required>
                <option value="">Select account</option>
                @foreach ($mayors as $mayor)
                    <option value="{{ $mayor->id }}" data-name="{{ $mayor->name }}" @selected(old('mayor_user_id') == $mayor->id)>{{ $mayor->name }} ({{ $mayor->user_id }})</option>
                @endforeach
            </select>
        </label>
        <label>Additional physical attachments, one per line
            <textarea name="additional_attachments" id="coa-additional" maxlength="8000">{{ old('additional_attachments') }}</textarea>
        </label>
        <label class="coa-editor-confirm" id="coa-additional-confirm" @if(blank(old('additional_attachments'))) hidden @endif><input type="checkbox" name="confirm_additional" value="1" @checked(old('confirm_additional'))>I confirm the additional attachments listed above are physically included.</label>
        <label class="coa-editor-confirm"><input type="checkbox" name="confirm_packet" value="1" required @checked(old('confirm_packet'))>I confirm the documents named in this cover letter are included in this bidding file.</label>
    </div>

    <div class="coa-editor-paper-scroll">
        <article class="coa-editor-paper">
            <header class="coa-editor-letterhead">
                <img src="{{ asset('images/logos/lgu-logo.png') }}" alt="Municipality of Tomas Oppus seal">
                <div><p>Republic of the Philippines</p><p>Province of Southern Leyte</p><strong>MUNICIPALITY OF TOMAS OPPUS</strong><p>-oOo-</p><b>OFFICE OF THE MUNICIPAL MAYOR</b></div>
                <span></span>
            </header>
            <label class="coa-editor-date"><span class="sr-only">Letter date</span><input type="date" name="letter_date" value="{{ old('letter_date', now()->toDateString()) }}" required></label>
            <div class="coa-editor-recipient">
                <label><span class="sr-only">Recipient name</span><input name="recipient_name" value="{{ old('recipient_name') }}" placeholder="Recipient name" required maxlength="150"></label>
                <label><span class="sr-only">Recipient title</span><input name="recipient_title" value="{{ old('recipient_title') }}" placeholder="Recipient title" required maxlength="150"></label>
                <label><span class="sr-only">Recipient office</span><input name="recipient_office" value="{{ old('recipient_office', 'Commission on Audit') }}" placeholder="Recipient office" required maxlength="250"></label>
                <label><span class="sr-only">Recipient address</span><input name="recipient_address" value="{{ old('recipient_address') }}" placeholder="Recipient address" maxlength="250"></label>
            </div>
            <label class="coa-editor-salutation"><span class="sr-only">Salutation</span><input name="salutation" value="{{ old('salutation', 'Dear Auditor:') }}" required maxlength="150"></label>
            <label class="coa-editor-opening"><span class="sr-only">Opening sentence</span><textarea name="opening" required maxlength="1200" rows="2">{{ old('opening', 'The undersigned respectfully submits the supporting documents for') }}</textarea></label>
            <label><span class="sr-only">Project title in letter</span><input class="coa-editor-project" name="project_title" value="{{ old('project_title', $record->project_title) }}" required maxlength="255"></label>
            <p>Attached herewith are the following documents, to wit:</p>
            <ol id="coa-letter-list">
                @foreach ($checklist->where('ready', true) as $entry)
                    <li>{{ $entry['name'] }}@if ($entry['supplier_name']) ({{ $entry['supplier_name'] }})@endif</li>
                @endforeach
            </ol>
            <label><span class="sr-only">Closing text</span><textarea class="coa-editor-closing" name="closing" required maxlength="500" rows="4">{{ old('closing', "Anticipating your favorable action.\n\nThank you very much.") }}</textarea></label>
            <div class="coa-editor-signature"><p>Very truly yours,</p><strong id="coa-mayor-name">Municipal Mayor</strong><span>Municipal Mayor / Head of the Procuring Entity</span></div>
        </article>
    </div>
</form>

@if ($letters->isNotEmpty())
    <section class="coa-editor-previous">
        <h2>Previous Cover Letters</h2>
        <ul>
            @foreach ($letters as $letter)
                <li><a href="{{ route('bac-secretariat.competitive-bidding.transmittal.show', [$record, $letter]) }}">{{ $letter->created_at?->format('M d, Y h:i A') }}</a><span>{{ $letter->statusLabel() }}</span></li>
            @endforeach
        </ul>
    </section>
@endif

<script>
document.addEventListener('DOMContentLoaded', () => {
    const mayor = document.getElementById('coa-mayor');
    const mayorName = document.getElementById('coa-mayor-name');
    const additional = document.getElementById('coa-additional');
    const additionalConfirm = document.getElementById('coa-additional-confirm');
    const list = document.getElementById('coa-letter-list');
    const originalCount = list.children.length;
    const refresh = () => {
        mayorName.textContent = mayor.selectedOptions[0]?.dataset.name || 'Municipal Mayor';
        additionalConfirm.hidden = !additional.value.trim();
        while (list.children.length > originalCount) list.lastElementChild.remove();
        additional.value.split(/\r?\n/).map(line => line.trim()).filter(Boolean).slice(0, 50).forEach(line => {
            const item = document.createElement('li');
            item.textContent = line;
            list.appendChild(item);
        });
    };
    mayor.addEventListener('change', refresh);
    additional.addEventListener('input', refresh);
    refresh();
});
</script>
@endsection
