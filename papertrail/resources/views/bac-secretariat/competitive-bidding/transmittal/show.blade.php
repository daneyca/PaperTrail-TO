@extends('layouts.dashboard')

@section('title', 'COA Cover Letter | PaperTrail')

@section('content')
<style>
    .coa-view-actions { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px; padding:12px 16px; border:1px solid #d8e0eb; background:#fff; }
    .coa-view-actions h1 { margin:0; font-size:1.05rem; color:#0b2341; }
    .coa-view-actions p { margin:2px 0 0; color:#52647d; font-size:.8rem; }
    .coa-view-buttons { display:flex; gap:8px; }
    .coa-view-buttons a, .coa-view-buttons button { min-height:36px; display:inline-flex; align-items:center; padding:0 12px; border:1px solid #cbd5e1; border-radius:6px; background:#fff; color:#0b2341; font:700 .8rem Arial,sans-serif; text-decoration:none; cursor:pointer; }
    .coa-view-buttons button { border-color:#0b2341; background:#0b2341; color:#fff; }
    .coa-view-status { margin:0 0 14px; padding:10px 14px; border:1px solid #d8e0eb; background:#f8fbff; color:#233b59; font-size:.83rem; }
    .coa-view-paper { padding:16px 0 30px; overflow:auto; background:#eef2f6; }
    .coa-view-paper .coa-letter { box-shadow:0 8px 28px rgba(15,23,42,.12); }
    @media print { .coa-view-actions, .coa-view-status { display:none !important; } .coa-view-paper { display:block !important; overflow:visible !important; visibility:visible !important; padding:0 !important; background:#fff !important; } }
</style>

<div class="coa-view-actions no-print">
    <div>
        <h1>COA Cover Letter</h1>
        <p>{{ $record->reference_number }} &middot; {{ $document->statusLabel() }}</p>
    </div>
    <div class="coa-view-buttons">
        <a href="{{ route('bac-secretariat.competitive-bidding.transmittal.create', $record) }}">Back to Bidding File</a>
        @if ($document->status === \App\Models\CompetitiveBiddingSignatureDocument::STATUS_FULLY_SIGNED)
            <button type="button" onclick="window.print()">Print</button>
        @endif
    </div>
</div>

@if (session('status'))<p class="coa-view-status" role="status">{{ session('status') }}</p>@endif
<div class="coa-view-paper">
    @include('bac-secretariat.competitive-bidding.transmittal.preview', compact('document', 'signatureSlots'))
</div>
@endsection
