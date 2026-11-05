@extends('layouts.dashboard')

@section('title', $document->document_title.' | PaperTrail')

@section('content')
<style>
    .cb-app-submitted-bar { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; padding:14px; border:1px solid #d8e0eb; background:#fff; }
    .cb-app-submitted-bar h1 { margin:0; font-size:1.2rem; }
    .cb-app-submitted-bar a,.cb-app-submitted-bar button { border:1px solid #cbd5e1; border-radius:5px; background:#fff; padding:8px 12px; text-decoration:none; color:#0b2341; cursor:pointer; }
    .cb-app-submitted-status { display:flex; gap:8px; flex-wrap:wrap; margin:12px 0; }
    .cb-app-submitted-status span { padding:8px 10px; border:1px solid #d8e0eb; background:#fff; }
    .cb-app-submitted-scroll { overflow:auto; background:#fff; }
    @media print { .cb-app-submitted-bar,.cb-app-submitted-status { display:none !important; } .cb-app-submitted-scroll { width:100% !important; overflow:visible !important; } }
</style>
<div class="cb-app-submitted-bar no-print"><div><h1>{{ $document->document_title }}</h1><div>{{ $document->displayNumber() }} · {{ $document->statusLabel() }}</div></div><div><a href="{{ route('bac-secretariat.competitive-bidding.menu', ['task' => 'annual-procurement-plan']) }}">Back</a> <button type="button" onclick="window.print()">Print</button></div></div>
@if (session('status'))<p role="status">{{ session('status') }}</p>@endif
<div class="cb-app-submitted-status no-print">@foreach ($requests as $signatureRequest)<span>{{ $signatureRequest->signatory_label }}: {{ $signatureRequest->requestedTo?->name ?? 'Account unavailable' }} ({{ ucfirst(str_replace('_', ' ', $signatureRequest->status)) }})</span>@endforeach</div>
<div class="cb-app-submitted-scroll" id="cb-app-submitted-{{ $document->id }}">{!! $document->document_html !!}</div>
@php
    $stamps = [];
    foreach (\App\Services\CompetitiveBiddingAppService::SLOTS as $slot => $label) {
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
    const root = document.getElementById('cb-app-submitted-{{ $document->id }}');
    Object.entries(@json($stamps)).forEach(([slot, stamp]) => {
        const line = root?.querySelector(`[data-app-signature-slot="${slot}"]`);
        if (!line) return;
        if (stamp.image) { const img = document.createElement('img'); img.src = stamp.image; img.alt = `${slot} electronic signature`; line.appendChild(img); }
        else line.textContent = stamp.text || '';
    });
});
</script>
@endsection
