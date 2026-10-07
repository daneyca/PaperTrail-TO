@extends('layouts.dashboard')

@section('title', 'Edit PPMP | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Documents</p>
            <h1>Edit PPMP</h1>
            <p>Update the encoded PPMP details before final submission or review.</p>
        </div>
    </section>

    <form method="POST" action="{{ route('ppmps.update', $ppmp) }}" class="ppmp-builder-form">
        @include('ppmps.partials.form')
    </form>
@endsection
