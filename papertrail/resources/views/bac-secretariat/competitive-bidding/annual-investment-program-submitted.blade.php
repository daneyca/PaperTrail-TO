@extends('layouts.dashboard')

@section('title', $document->document_title . ' | PaperTrail')

@section('content')
<style>
    .aip-submitted-header { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; padding:16px; border:1px solid #d8e0eb; background:#fff; color:#0b2341; }
    .aip-submitted-header h1 { margin:0; font-size:1.2rem; }
    .aip-submitted-header p { margin:3px 0 0; color:#52647d; }
    .aip-submitted-header a, .aip-submitted-header button { display:inline-flex; align-items:center; min-height:36px; padding:0 12px; border:1px solid #cbd5e1; border-radius:6px; background:#fff; color:#0b2341; font:700 .82rem Arial,sans-serif; text-decoration:none; cursor:pointer; }
    .aip-submitted-actions { display:flex; gap:8px; }
    .aip-signature-status { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; margin:12px 0; }
    .aip-signature-status div { padding:10px 12px; border:1px solid #d8e0eb; background:#fff; }
    .aip-signature-status strong, .aip-signature-status span { display:block; }
    .aip-signature-status span { margin-top:3px; color:#52647d; }
    .aip-submitted-alert { margin:10px 0; padding:10px 12px; border:1px solid #b7e1c2; background:#e9f7ed; color:#14532d; }
    @media (max-width:700px) { .aip-signature-status { grid-template-columns:1fr; } }
    @media print { .aip-submitted-header, .aip-signature-status, .aip-submitted-alert { display:none !important; } }
</style>

<div class="aip-submitted-header no-print">
    <div><h1>{{ $document->document_title }}</h1><p>{{ $document->displayNumber() }} · {{ $document->statusLabel() }}</p></div>
    <div class="aip-submitted-actions">
        <a href="{{ route('bac-secretariat.competitive-bidding.menu', ['task' => 'annual-investment-program']) }}">Back</a>
        <button type="button" onclick="window.print()">Print</button>
    </div>
</div>

@if (session('status'))<div class="aip-submitted-alert" role="status">{{ session('status') }}</div>@endif

<div class="aip-signature-status no-print">
    @foreach ($requests as $signatureRequest)
        <div>
            <strong>{{ $signatureRequest->signatory_label }}: {{ $signatureRequest->requestedTo?->name ?? 'Account unavailable' }}</strong>
            <span>{{ ucfirst(str_replace('_', ' ', $signatureRequest->status)) }}</span>
        </div>
    @endforeach
</div>

@include('bac-secretariat.competitive-bidding.partials.aip-signed-preview', compact('document', 'signatureSlots'))
@endsection
