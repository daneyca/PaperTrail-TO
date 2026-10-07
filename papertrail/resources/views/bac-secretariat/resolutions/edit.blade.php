@extends('layouts.dashboard')

@section('title', 'Edit BAC Resolution | PaperTrail')

@section('content')
    <form method="POST" action="{{ route('bac-secretariat.resolutions.update', $resolution) }}" class="resolution-workspace resolution-editor-form">
        @csrf
        @method('PATCH')
        <div class="resolution-page-toolbar no-print">
            <div>
                <strong>Edit BAC Resolution</strong>
                <span>Directly edit the official BAC Resolution document.</span>
            </div>
            <a href="{{ route('bac-secretariat.resolutions.show', $resolution) }}">Back</a>
            <button type="submit" name="save_action" value="draft">Save Draft</button>
            <button type="submit" name="save_action" value="submit" onclick="return confirm('Save and submit this BAC Resolution for electronic signatures?');">Submit for Signatures</button>
            <button type="button" class="secondary-button" onclick="printResolutionDocument()">Print Preview</button>
        </div>

        @include('bac-secretariat.resolutions.partials.resolution-word-editor', [
            'resolution' => $resolution,
            'sourceDocument' => $sourceDocument,
            'mode' => 'edit',
        ])
    </form>
@endsection
