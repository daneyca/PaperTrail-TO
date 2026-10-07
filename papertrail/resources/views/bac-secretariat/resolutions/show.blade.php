@extends('layouts.dashboard')

@section('title', ($resolution->resolution_number ?: 'BAC Resolution') . ' | PaperTrail')

@section('content')
    <section class="resolution-page-toolbar no-print">
        <div>
            <strong>{{ $resolution->resolution_number ? 'BAC Resolution No. '.$resolution->resolution_number : 'BAC Resolution Draft' }}</strong>
            <span>Tracking Number {{ $resolution->document_reference_number ?? 'Pending' }} &middot; {{ str($resolution->status)->replace('_', ' ')->title() }}</span>
        </div>
        <a href="{{ route('bac-secretariat.resolutions.index') }}">Back</a>
        @if ($resolution->isEditable())
            <a href="{{ route('bac-secretariat.resolutions.edit', $resolution) }}">Edit</a>
        @endif
        <x-ai.completeness-check-button
            document-type="bac_resolution"
            :document-id="$resolution->id"
            :tracking-number="$resolution->resolution_number"
        />
        <a href="{{ route('bac-secretariat.resolutions.print', $resolution) }}" target="_blank">Print</a>
        @if ($resolution->canSubmit())
            <form method="POST" action="{{ route('bac-secretariat.resolutions.submit', $resolution) }}" onsubmit="return confirm('Submit this BAC Resolution for electronic signatures?');">
                @csrf
                @method('PATCH')
                <button type="submit">Submit for Signatures</button>
            </form>
        @endif
        @if (($signatureSlots ?? collect())->isEmpty() && $resolution->source_pr_document_id && $resolution->status !== \App\Models\BacResolution::STATUS_RETURNED_TO_END_USER)
            <form method="POST" action="{{ route('bac-secretariat.resolutions.return-office', $resolution) }}" onsubmit="return confirm('Return this BAC Resolution to the requesting office?');">
                @csrf
                @method('PATCH')
                <input type="hidden" name="remarks" value="BAC Resolution returned to the requesting office for SVP processing.">
                <button type="submit">Return to Requesting Office</button>
            </form>
        @endif
    </section>

    @include('bac-secretariat.resolutions.partials.signature-tracking-panel', [
        'resolution' => $resolution,
        'signatureRequests' => $signatureRequests ?? collect(),
    ])

    @include('bac-secretariat.resolutions.partials.resolution-word-editor', [
        'resolution' => $resolution,
        'sourceDocument' => $resolution->sourcePrDocument,
        'mode' => 'show',
        'signatureSlots' => $signatureSlots ?? collect(),
        'bacChairSignature' => $signedSignature ?? null,
    ])

    <x-documents.attachments-panel
        :document="$resolution"
        document-type="bac_resolution"
        :can-upload="$resolution->isEditable()"
        title="BAC Resolution Supporting Documents"
    />

    @include('partials.svp-related-documents', ['source' => $resolution, 'context' => 'bac-secretariat'])
@endsection
