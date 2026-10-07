@extends('layouts.dashboard')

@section('title', 'Edit Supplemental APP | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">BAC Secretariat</p>
            <h1>Edit Supplemental APP</h1>
            <p>Update the Supplemental APP details before submission or acceptance.</p>
        </div>

        <a href="{{ route('bac-secretariat.supplemental-apps.show', $supplementalApp) }}" class="dashboard-action secondary-action">Back to Details</a>
    </section>

    <form method="POST" action="{{ route('bac-secretariat.supplemental-apps.update', $supplementalApp) }}" class="supplemental-app-form supplemental-official-form-wrapper">
        @include('bac-secretariat.supplemental-apps._form')
    </form>
@endsection
