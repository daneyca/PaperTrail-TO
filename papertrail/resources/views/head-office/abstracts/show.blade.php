@extends('layouts.dashboard')

@section('title', ($abstract->abstract_number ?? 'Abstract') . ' | PaperTrail')

@section('content')
    @if ($abstract->status === \App\Models\AbstractQuotation::STATUS_SUBMITTED)
        <section class="session-alert no-print" role="status" aria-label="Abstract submission status">
            <strong>Abstract submitted successfully.</strong>
            <p>{{ $abstract->displayNumber() }} has been sent to the selected BAC signatories for e-signatures.</p>
            <span>Submitted {{ $abstract->submitted_at?->format('M d, Y h:i A') }} by {{ $abstract->submittedBy?->name }}.</span>
        </section>
    @endif
    <section class="abstract-toolbar no-print">
        <div><strong>{{ $abstract->abstract_number ? 'Abstract '.$abstract->abstract_number : 'Abstract Draft' }}</strong><span>Tracking Number {{ $abstract->document_reference_number ?? 'Pending' }} &middot; {{ str($abstract->status)->replace('_', ' ')->title() }}</span></div>
        <a href="{{ route('head-office.abstracts.index') }}">Back</a>
        @if (auth()->user()->canManageSvpDocuments() && $abstract->isEditable())
            <a href="{{ route('head-office.abstracts.edit', $abstract) }}">Edit</a>
        @endif
        <a href="{{ route('head-office.abstracts.print', $abstract) }}" target="_blank">Print</a>
        <x-ai.completeness-check-button
            document-type="abstract"
            :document-id="$abstract->id"
            :tracking-number="$abstract->abstract_number"
            label="AI Check Abstract"
        />
        @if (auth()->user()->canManageSvpDocuments() && $abstract->canSubmit())
            <form method="POST" action="{{ route('head-office.abstracts.submit', $abstract) }}" onsubmit="return confirm('Submit this Abstract?');">
                @csrf
                @method('PATCH')
                <button type="submit">Submit</button>
            </form>
        @endif
    </section>

    @include('head-office.abstracts.partials.abstract-excel-form', [
        'abstract' => $abstract,
        'sourceDocument' => $abstract->sourcePrDocument,
        'sourceRfq' => $abstract->sourceRfq,
        'sourceResolution' => $abstract->sourceBacResolution,
        'mode' => 'show',
    ])

    <x-documents.attachments-panel
        :document="$abstract"
        document-type="abstract"
        :can-upload="auth()->user()->canManageSvpDocuments() && $abstract->isEditable()"
        title="Supporting Documents"
    />

    @include('partials.svp-related-documents', ['source' => $abstract, 'context' => 'head-office'])
@endsection
