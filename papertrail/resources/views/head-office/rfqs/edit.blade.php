@extends('layouts.dashboard')

@section('title', 'Edit RFQ | PaperTrail')

@section('content')
    <form method="POST" action="{{ route('head-office.rfqs.update', $rfq) }}" class="rfq-editor-form">
        @csrf
        @method('PATCH')
        <section class="rfq-toolbar no-print">
            <div><strong>Edit RFQ</strong><span>Edit directly inside the official RFQ form.</span></div>
            <a href="{{ route('head-office.rfqs.show', $rfq) }}">Back</a>
            <button type="submit" name="save_action" value="draft">Update</button>
            <button type="submit" name="save_action" value="submit" onclick="return confirm('Submit this RFQ?');">Submit</button>
            <button type="button" onclick="addRfqRow()">Add Row</button>
            <button type="button" id="removeEmptyRfqRowsBtn">Remove Empty Rows</button>
            <button type="button" onclick="printRfqDocument()">Print</button>
        </section>

        @include('head-office.rfqs.partials.rfq-excel-form', [
            'rfq' => $rfq,
            'sourceDocument' => $sourceDocument,
            'sourceResolution' => $sourceResolution,
            'mode' => 'edit',
        ])
    </form>
@endsection
