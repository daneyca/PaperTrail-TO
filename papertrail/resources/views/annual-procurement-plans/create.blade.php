@extends('layouts.dashboard')

@section('title', 'Create Annual Procurement Plan | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Procurement Planning</p>
            <h1>Create Annual Procurement Plan</h1>
            <p>Encode the official APP details and procurement project rows.</p>
        </div>
    </section>

    <form method="POST" action="{{ route('annual-procurement-plans.store') }}">
        @include('annual-procurement-plans.partials.form', [
            'app' => $app,
            'items' => $items,
            'options' => $options,
        ])
    </form>
@endsection
