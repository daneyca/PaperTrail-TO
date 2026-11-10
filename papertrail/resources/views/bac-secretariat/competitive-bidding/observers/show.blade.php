@extends('layouts.dashboard')
@section('title', 'Observer Invitation | PaperTrail')
@section('content')
<link rel="stylesheet" href="{{ asset('css/observer-invitations.css') }}?v={{ filemtime(public_path('css/observer-invitations.css')) }}">
<div class="observer-toolbar no-print">
    <div><p class="eyebrow">Observer Invitation</p><h1>{{ $invitation->recipient() }}</h1></div>
    <div class="observer-toolbar__actions">
        <a href="{{ route('bac-secretariat.competitive-bidding.observers.index') }}" class="dashboard-action secondary-action" data-action-icon="false">Back</a>
        <a href="{{ route('bac-secretariat.competitive-bidding.observers.edit', $invitation) }}" class="dashboard-action secondary-action" data-action-icon="false">Edit Letter</a>
        <a href="#observer-copies" class="dashboard-action secondary-action" data-action-icon="false">Upload Copies</a>
        <a href="{{ route('bac-secretariat.competitive-bidding.observers.print', $invitation) }}" target="_blank" rel="noopener" class="dashboard-action" data-action-icon="false">Print</a>
    </div>
</div>
<div class="observer-document-scroll">@include('bac-secretariat.competitive-bidding.observers.letter', ['data' => $invitation->payload])</div>
<section class="table-panel observer-copies no-print" id="observer-copies">
    <div class="panel-heading"><div><p class="eyebrow">Physical Signature / Receipt Records</p><h2>Signed / Received Copies</h2><p>Upload the copies after staff collect signatures or acknowledgment in person.</p></div></div>
    @if ($errors->any())<div class="observer-notice observer-notice--error" role="alert">{{ $errors->first() }}</div>@endif
    <form method="POST" enctype="multipart/form-data" action="{{ route('bac-secretariat.competitive-bidding.observers.copies.store', $invitation) }}" class="observer-upload-form">
        @csrf
        <label>Observer / Receiving Office<input name="observer_name" value="{{ old('observer_name', $invitation->payload['recipient_name'] ?? '') }}" required maxlength="255" placeholder="Enter Name or receiving office"></label>
        <label>Copy Type<select name="copy_type"><option value="signed_letter" @selected(old('copy_type') === 'signed_letter')>Signed letter</option><option value="received_copy" @selected(old('copy_type') === 'received_copy')>Received / stamped copy</option><option value="acknowledgment" @selected(old('copy_type') === 'acknowledgment')>Observer acknowledgment</option></select></label>
        <label>Date Signed / Received<input name="received_on" type="date" value="{{ old('received_on') }}"></label>
        <label>Files (PDF, JPG or PNG · up to 10 MB each)<input name="copies[]" type="file" accept=".pdf,.jpg,.jpeg,.png" required multiple></label>
        <label class="observer-upload-form__wide">Notes<textarea name="notes" rows="2" placeholder="Optional notes about collection or delivery">{{ old('notes') }}</textarea></label>
        <div class="observer-upload-form__wide observer-upload-form__actions"><button type="submit" class="dashboard-action" data-action-icon="false">Upload Copies</button></div>
    </form>
    <div class="table-scroll"><table class="user-management-table">
        <thead><tr><th>File</th><th>Observer / Office</th><th>Copy Type</th><th>Signed / Received</th><th>Letter Revision</th><th>Uploaded</th><th>Actions</th></tr></thead>
        <tbody>
            @forelse ($invitation->copies as $copy)
                @php($metadata = $copy->extracted_metadata ?? [])
                <tr>
                    <td>{{ $copy->displayName() }}<small class="observer-copy-note">{{ $copy->description }}</small></td>
                    <td>{{ $metadata['observer_name'] ?? '—' }}</td>
                    <td>{{ ['signed_letter' => 'Signed letter', 'received_copy' => 'Received / stamped copy', 'acknowledgment' => 'Observer acknowledgment'][$metadata['copy_type'] ?? ''] ?? 'Copy' }}</td>
                    <td>{{ $metadata['received_on'] ?? 'Not recorded' }}</td>
                    <td>{{ $metadata['letter_revision'] ?? '—' }}</td>
                    <td>{{ $copy->created_at?->timezone('Asia/Manila')->format('M d, Y h:i A') }}<small class="observer-copy-note">{{ $copy->uploadedBy?->name }}</small></td>
                    <td><div class="table-actions">
                        <a class="dashboard-action secondary-action" data-action-icon="false" href="{{ route('bac-secretariat.competitive-bidding.observers.copies.view', [$invitation, $copy]) }}" target="_blank" rel="noopener">View</a>
                        <a class="dashboard-action secondary-action" data-action-icon="false" href="{{ route('bac-secretariat.competitive-bidding.observers.copies.view', [$invitation, $copy, 'download' => 1]) }}">Download</a>
                        <form method="POST" action="{{ route('bac-secretariat.competitive-bidding.observers.copies.destroy', [$invitation, $copy]) }}" data-confirm="Remove this uploaded copy from the invitation?" data-confirm-type="danger">@csrf @method('DELETE')<button type="submit" class="dashboard-action secondary-action" data-action-icon="false">Remove</button></form>
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-state"><strong>No signed or received copies uploaded yet.</strong><p>Use Upload Copies after collecting the physical documents.</p></div></td></tr>
            @endforelse
        </tbody>
    </table></div>
</section>
@if (session('observer_draft_key'))
    <script>try { sessionStorage.removeItem(@json(session('observer_draft_key'))); } catch (error) {}</script>
@endif
@endsection
