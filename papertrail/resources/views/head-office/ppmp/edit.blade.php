@extends('layouts.dashboard')

@section('title', 'Edit PPMP | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero ppmp-page-hero">
        <div>
            <p class="eyebrow">Head of Office / End User</p>
            <h1>Edit PPMP</h1>
            <p>{{ $document->tracking_number ?? 'Draft PPMP' }} &middot; Fiscal Year {{ $document->fiscal_year }}</p>
        </div>
        <div class="hero-actions">
            <span class="status-pill status-{{ $document->status }}">{{ str($document->status)->replace('_', ' ')->title()->replace('Ppmp', 'PPMP') }}</span>
        </div>
    </section>

    <form method="POST" action="{{ route('head-office.ppmp.update', $document) }}" class="ppmp-builder-form">
        @include('head-office.ppmp._form')
    </form>

    <x-documents.attachments-panel
        :document="$document"
        document-type="ppmp"
        :can-upload="true"
        title="PPMP Supporting Documents"
    />
@endsection
