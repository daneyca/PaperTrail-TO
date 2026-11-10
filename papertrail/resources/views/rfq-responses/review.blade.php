@extends('layouts.dashboard')
@section('title', 'Supplier Quotation | PaperTrail')
@section('content')
    <link rel="stylesheet" href="{{ asset('css/rfq-responses.css') }}?v={{ filemtime(public_path('css/rfq-responses.css')) }}">
    <section class="dashboard-hero admin-users-hero no-print"><div><p class="eyebrow">RFQ {{ $rfq->rfq_number }}</p><h1>Supplier Quotation</h1><p>{{ $quotation->supplier_name }} · {{ str($quotation->status)->title() }}</p></div><div class="hero-actions"><a class="dashboard-action secondary-action" href="{{ route('head-office.rfqs.show', $rfq) }}">Back to RFQ</a><button type="button" class="dashboard-action" onclick="window.print()">Print / Save PDF</button></div></section>
    @if (session('status'))<p class="rfq-response-alert no-print">{{ session('status') }}</p>@endif
    @if ($errors->any())<div class="rfq-response-errors no-print">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if ($quotation->submitted_at && ! $quotation->responseIsIntact())<p class="rfq-response-errors">The saved quotation failed its integrity check. Do not use its prices.</p>@endif
    <section class="table-panel rfq-response-management">@include('rfq-responses.document', ['snapshot' => $quotation->issued_snapshot, 'response' => $quotation->response_snapshot ?? []])</section>
    @if ($quotation->status === 'submitted')
        <section class="table-panel rfq-response-management no-print"><h2>Verify supplier quotation</h2><p>Review the prices, supplier details, and signature before confirming that this response can be used for the Abstract.</p>
            <form method="POST" action="{{ route('head-office.rfqs.quotations.review', [$rfq, $quotation]) }}">@csrf @method('PATCH')
                <label>Review remarks<textarea name="review_remarks" maxlength="2000">{{ old('review_remarks') }}</textarea></label>
                <div class="rfq-response-actions"><button type="submit" name="decision" value="verified" class="dashboard-action">Verify Quotation</button><button type="submit" name="decision" value="rejected" class="dashboard-action secondary-action">Reject Quotation</button></div>
            </form>
        </section>
    @elseif ($quotation->reviewed_at)
        <p>Reviewed by {{ $quotation->reviewedBy?->name }} on {{ $quotation->reviewed_at->timezone('Asia/Manila')->format('M d, Y h:i A') }} PHT. {{ $quotation->review_remarks }}</p>
    @endif
@endsection
