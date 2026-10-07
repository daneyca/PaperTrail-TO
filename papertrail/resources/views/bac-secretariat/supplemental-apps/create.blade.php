@extends('layouts.dashboard')

@section('title', 'Create Supplemental APP | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">BAC Secretariat</p>
            <h1>Create Supplemental APP</h1>
        </div>

        <a href="{{ $sourceDocument ? route('bac-secretariat.pr.show', $sourceDocument) : route('bac-secretariat.supplemental-apps.index') }}" class="dashboard-action secondary-action">Back</a>
    </section>

    <form
        method="POST"
        action="{{ route('bac-secretariat.supplemental-apps.store') }}"
        class="supplemental-app-form supplemental-official-form-wrapper document-create-flow"
        data-document-create-flow
        data-document-create-autoshow="{{ $errors->any() ? 'true' : 'false' }}"
    >
        @include('bac-secretariat.supplemental-apps._form', [
            'useDocumentCreateFlow' => true,
            'recentDrafts' => $recentDrafts ?? collect(),
        ])
    </form>
@endsection
