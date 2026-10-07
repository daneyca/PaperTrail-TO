@extends('layouts.dashboard')

@section('title', 'Create PPMP | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Documents</p>
            <h1>PPMP Draft</h1>
            <p>Encode directly into the official Project Procurement Plan worksheet layout.</p>
        </div>
    </section>

    <form method="POST" action="{{ route('ppmps.store') }}" class="ppmp-builder-form">
        @include('ppmps.partials.form')
    </form>
@endsection
