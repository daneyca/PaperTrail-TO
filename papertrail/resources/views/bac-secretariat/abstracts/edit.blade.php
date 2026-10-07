@extends('layouts.dashboard')

@section('title', 'Edit Abstract | PaperTrail')

@section('content')
    <form method="POST" action="{{ route('bac-secretariat.abstracts.update', $abstract) }}" class="abstract-editor-form">
        @csrf
        @method('PATCH')
        <section class="abstract-toolbar no-print">
            <div><strong>Edit Abstract</strong><span>Edit directly inside the official Abstract form.</span></div>
            <a href="{{ route('bac-secretariat.abstracts.show', $abstract) }}">Back</a>
            <button type="submit" name="save_action" value="draft">Save Draft</button>
            <button type="submit" name="save_action" value="submit" onclick="return confirm('Submit this Abstract?');">Submit</button>
            <button type="button" onclick="addAbstractRow()">Add Row</button>
            <button type="button" onclick="removeEmptyAbstractRows()">Remove Empty Rows</button>
            <button type="button" onclick="printAbstractDocument()">Print</button>
        </section>

        @include('bac-secretariat.abstracts.partials.abstract-excel-form', [
            'abstract' => $abstract,
            'sourceDocument' => $sourceDocument,
            'sourceRfq' => $sourceRfq,
            'sourceResolution' => $sourceResolution,
            'mode' => 'edit',
        ])
    </form>
@endsection
