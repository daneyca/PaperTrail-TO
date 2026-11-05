@extends('layouts.dashboard')

@section('title', $document->document_title.' | PaperTrail')

@section('content')
<style>
    .cert-submitted-bar { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; padding:0 0 16px; }
    .cert-submitted-bar h1 { margin:0; font-size:1.2rem; }
    .cert-submitted-bar p { margin:3px 0 0; color:#52637a; }
    .cert-submitted-bar a,.cert-submitted-bar button { display:inline-block; padding:8px 12px; border:1px solid #c6d2e1; border-radius:5px; background:#fff; color:#102743; font:inherit; font-weight:700; text-decoration:none; cursor:pointer; }
    .cert-submitted-status { display:flex; gap:8px; flex-wrap:wrap; margin:0 0 16px; }
    .cert-submitted-status span { padding:8px 10px; border:1px solid #d8e1ec; background:#fff; font-size:.8rem; }
    .cert-submitted-preview { overflow:auto; padding:18px; background:#e9eef5; }
    @media print { .cert-submitted-bar,.cert-submitted-status { display:none !important; } .cert-submitted-preview { overflow:visible !important; padding:0 !important; background:#fff !important; } }
</style>
<div class="cert-submitted-bar no-print">
    <div><h1>{{ $document->document_title }}</h1><p>{{ $document->displayNumber() }} | {{ $document->statusLabel() }}</p></div>
    <div><a href="{{ route('bac-secretariat.competitive-bidding.menu', ['task' => 'bac-secretariat-head-certification']) }}">Back</a> <button type="button" onclick="window.print()">Print</button></div>
</div>
@if (session('status'))<p role="status">{{ session('status') }}</p>@endif
<div class="cert-submitted-status no-print">
    @foreach ($requests as $signatureRequest)
        <span>{{ $signatureRequest->signatory_label }}: {{ $signatureRequest->requestedTo?->name ?? 'Account unavailable' }} ({{ ucfirst(str_replace('_', ' ', $signatureRequest->status)) }})</span>
    @endforeach
</div>
<div class="cert-submitted-preview">
    @include('bac-secretariat.competitive-bidding.partials.head-certification-signed-preview', compact('document', 'signatureSlots'))
</div>
@endsection
