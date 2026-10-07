@extends('layouts.dashboard')

@section('title', 'Edit Annual Procurement Plan | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Procurement Planning</p>
            <h1>Edit Annual Procurement Plan</h1>
            <p>Update the APP fields and official procurement project rows.</p>
        </div>
    </section>

    <form method="POST" action="{{ route('annual-procurement-plans.update', $app) }}">
        @include('annual-procurement-plans.partials.form', [
            'app' => $app,
            'items' => $items,
            'options' => $options,
        ])
    </form>
@endsection
