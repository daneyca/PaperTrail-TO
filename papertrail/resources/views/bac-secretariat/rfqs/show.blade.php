@extends('layouts.dashboard')

@section('title', ($rfq->rfq_number ?? 'RFQ') . ' | PaperTrail')

@section('content')
    <section class="rfq-toolbar no-print">
        <div><strong>{{ $rfq->rfq_number ? 'RFQ '.$rfq->rfq_number : 'RFQ Draft' }}</strong><span>Tracking Number {{ $rfq->document_reference_number ?? 'Pending' }} &middot; {{ str($rfq->status)->replace('_', ' ')->title() }}</span></div>
        <a href="{{ route('bac-secretariat.rfqs.index') }}">Back</a>
        @if ($rfq->isEditable())
            <a href="{{ route('bac-secretariat.rfqs.edit', $rfq) }}">Edit</a>
        @endif
        <a href="{{ route('bac-secretariat.rfqs.print', $rfq) }}" target="_blank">Print</a>
        <x-ai.completeness-check-button
            document-type="rfq"
            :document-id="$rfq->id"
            :tracking-number="$rfq->rfq_number"
            label="AI Check RFQ"
        />
        @if ($rfq->canSubmit())
            <form method="POST" action="{{ route('bac-secretariat.rfqs.submit', $rfq) }}" onsubmit="return confirm('Submit this RFQ?');">
                @csrf
                @method('PATCH')
                <button type="submit">Submit</button>
            </form>
        @endif
    </section>

    @include('bac-secretariat.rfqs.partials.rfq-excel-form', [
        'rfq' => $rfq,
        'sourceDocument' => $rfq->sourcePrDocument,
        'sourceResolution' => $rfq->sourceBacResolution,
        'mode' => 'show',
    ])

    <x-documents.attachments-panel
        :document="$rfq"
        document-type="rfq"
        :can-upload="$rfq->isEditable()"
        title="RFQ Supporting Documents"
    />

    @include('partials.svp-related-documents', ['source' => $rfq, 'context' => 'bac-secretariat'])
@endsection
