@extends('layouts.dashboard')
@section('title', ($invitation->exists ? 'Edit' : 'Create').' Observer Invitation | PaperTrail')
@section('content')
<link rel="stylesheet" href="{{ asset('css/observer-invitations.css') }}?v={{ filemtime(public_path('css/observer-invitations.css')) }}">
@php
    $data = is_array(old('letter')) ? old('letter') : $data;
    $draftKey = 'papertrail:observer:'.auth()->id().':'.($invitation->id ?? 'new').':'.($invitation->revision ?? 0);
    $blankProject = ['description' => '', 'budget_prefix' => 'with an ABC of Php', 'budget' => ''];
    $blankSchedule = ['when' => '', 'projects' => [$blankProject]];
@endphp
<form class="observer-editor" method="POST" action="{{ $invitation->exists ? route('bac-secretariat.competitive-bidding.observers.update', $invitation) : route('bac-secretariat.competitive-bidding.observers.store') }}" data-observer-editor data-draft-key="{{ $draftKey }}" data-has-old-input="{{ old('letter') ? 'true' : 'false' }}">
    @csrf
    @if ($invitation->exists)
        @method('PUT')
        <input type="hidden" name="revision" value="{{ old('revision', $invitation->revision) }}">
    @endif
    <div class="observer-toolbar no-print">
        <div><p class="eyebrow">BACSEC-003 · Competitive Bidding</p><h1>Letter of Invitation to Observer</h1></div>
        <div class="observer-toolbar__actions">
            <a class="dashboard-action secondary-action" data-action-icon="false" href="{{ route('bac-secretariat.competitive-bidding.observers.index') }}">Back</a>
            <button type="button" class="dashboard-action secondary-action" data-action-icon="false" data-observer-print>Print</button>
            <button type="submit" class="dashboard-action" data-action-icon="false">Save Letter</button>
        </div>
    </div>
    <div class="observer-file-picker no-print">
        <label for="observer-record">Related Bidding File <span>(optional)</span></label>
        <select name="record_id" id="observer-record">
            <option value="">No linked bidding file</option>
            @foreach ($records as $record)
                <option value="{{ $record->id }}" @selected((string) old('record_id', $invitation->competitive_bidding_record_id) === (string) $record->id)>{{ $record->reference_number }} — {{ $record->project_title }}</option>
            @endforeach
        </select>
    </div>
    @if ($errors->any())
        <div class="observer-notice observer-notice--error no-print" role="alert">{{ $errors->first() }}</div>
    @endif
    <p class="observer-notice observer-notice--error no-print" data-observer-storage-error role="status" hidden></p>
    <div class="observer-document-scroll">
        @include('bac-secretariat.competitive-bidding.observers.letter', ['editable' => true])
    </div>
</form>
<template id="observer-project-template">@include('bac-secretariat.competitive-bidding.observers.project', ['path' => 'sections.0.schedules.0.projects.0', 'project' => $blankProject, 'editable' => true])</template>
<template id="observer-schedule-template">@include('bac-secretariat.competitive-bidding.observers.schedule', ['path' => 'sections.0.schedules.0', 'schedule' => $blankSchedule, 'editable' => true])</template>
<template id="observer-section-template">@include('bac-secretariat.competitive-bidding.observers.section', ['path' => 'sections.0', 'section' => ['heading' => 'ACTIVITY:', 'schedules' => [$blankSchedule]], 'editable' => true])</template>
<script src="{{ asset('js/observer-invitations.js') }}?v={{ filemtime(public_path('js/observer-invitations.js')) }}" defer></script>
@endsection
