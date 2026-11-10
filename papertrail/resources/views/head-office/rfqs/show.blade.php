@extends('layouts.dashboard')

@section('title', ($rfq->rfq_number ?? 'RFQ') . ' | PaperTrail')

@section('content')
    <section class="rfq-toolbar no-print">
        <div><strong>{{ $rfq->rfq_number ? 'RFQ '.$rfq->rfq_number : 'RFQ Draft' }}</strong><span>Tracking Number {{ $rfq->document_reference_number ?? 'Pending' }} &middot; {{ str($rfq->status)->replace('_', ' ')->title() }}</span></div>
        <a href="{{ route('head-office.rfqs.index') }}">Back</a>
        @if (auth()->user()->canManageSvpDocuments() && $rfq->isEditable())
            <a href="{{ route('head-office.rfqs.edit', $rfq) }}">Edit</a>
        @endif
        <a href="{{ route('head-office.rfqs.print', $rfq) }}" target="_blank">Print</a>
        <x-ai.completeness-check-button
            document-type="rfq"
            :document-id="$rfq->id"
            :tracking-number="$rfq->rfq_number"
            label="AI Check RFQ"
        />
        @if (auth()->user()->canManageSvpDocuments() && $rfq->canSubmit())
            <form method="POST" action="{{ route('head-office.rfqs.submit', $rfq) }}" onsubmit="return confirm('Submit this RFQ?');">
                @csrf
                @method('PATCH')
                <button type="submit">Submit</button>
            </form>
        @endif
    </section>

    @include('rfq-responses.manage', ['rfq' => $rfq])

    @if ($rfq->supplier_response_enabled && $rfq->procurement_officer_user_id)
        <link rel="stylesheet" href="{{ asset('css/rfq-responses.css') }}">
        <section class="table-panel rfq-response-management">
            @include('rfq-responses.document', ['snapshot' => app(\App\Services\RfqSupplierWorkflowService::class)->issuedSnapshot($rfq)])
        </section>
    @else
    @include('head-office.rfqs.partials.rfq-excel-form', [
        'rfq' => $rfq,
        'sourceDocument' => $rfq->sourcePrDocument,
        'sourceResolution' => $rfq->sourceBacResolution,
        'mode' => 'show',
    ])
    @endif

    <x-documents.attachments-panel
        :document="$rfq"
        document-type="rfq"
        :can-upload="auth()->user()->canManageSvpDocuments() && $rfq->isEditable()"
        title="Supporting Documents"
    />

    @include('partials.svp-related-documents', ['source' => $rfq, 'context' => 'head-office'])
@endsection
