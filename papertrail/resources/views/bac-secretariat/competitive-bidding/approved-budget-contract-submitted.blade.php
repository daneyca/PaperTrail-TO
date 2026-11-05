@extends('layouts.dashboard')

@section('title', $document->document_title.' | PaperTrail')

@section('content')
<style>
    .abc-submitted-bar { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; padding:14px; border:1px solid #d8e0eb; background:#fff; }
    .abc-submitted-bar h1 { margin:0; font-size:1.2rem; }
    .abc-submitted-bar a,.abc-submitted-bar button { border:1px solid #cbd5e1; border-radius:5px; background:#fff; padding:8px 12px; text-decoration:none; color:#0b2341; cursor:pointer; }
    .abc-submitted-status { display:flex; gap:8px; flex-wrap:wrap; margin:12px 0; }
    .abc-submitted-status span { padding:8px 10px; border:1px solid #d8e0eb; background:#fff; }
    .abc-submitted-scroll { overflow:auto; background:#fff; }
    @media print { .abc-submitted-bar,.abc-submitted-status { display:none !important; } .abc-submitted-scroll { width:100% !important; overflow:visible !important; } }
</style>
<div class="abc-submitted-bar no-print"><div><h1>{{ $document->document_title }}</h1><div>{{ $document->displayNumber() }} · {{ $document->statusLabel() }}</div></div><div><a href="{{ route('bac-secretariat.competitive-bidding.menu', ['task' => 'approved-budget-for-the-contract']) }}">Back</a> <button type="button" onclick="window.print()">Print</button></div></div>
@if (session('status'))<p role="status">{{ session('status') }}</p>@endif
<div class="abc-submitted-status no-print"><span>Prepared / Submitted by: name and position only; no account assigned yet.</span>@foreach ($requests as $signatureRequest)<span>{{ $signatureRequest->signatory_label }}: {{ $signatureRequest->requestedTo?->name ?? 'Account unavailable' }} ({{ ucfirst(str_replace('_', ' ', $signatureRequest->status)) }})</span>@endforeach</div>
<div class="abc-submitted-scroll" id="abc-submitted-{{ $document->id }}">{!! $document->document_html !!}</div>
@php
    $stamps = [];
    foreach (\App\Services\CompetitiveBiddingAbcService::RECIPIENT_ACCOUNTS as $slot => $account) {
        $signature = $signatureSlots->get($slot);
        if ($signature) {
            $stamps[$slot] = [
                'image' => $signature->signature_image_path ? route('e-signatures.image', $signature) : null,
                'text' => $signature->typed_signature_name ?: $signature->signer_name,
            ];
        }
    }
@endphp
<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('abc-submitted-{{ $document->id }}');
    Object.entries(@json($stamps)).forEach(([slot, stamp]) => {
        const line = root?.querySelector(`[data-abc-signature-slot="${slot}"]`);
        if (!line) return;
        if (stamp.image) { const img = document.createElement('img'); img.src = stamp.image; img.alt = `${slot} electronic signature`; line.appendChild(img); }
        else line.textContent = stamp.text || '';
    });
});
</script>
@endsection
