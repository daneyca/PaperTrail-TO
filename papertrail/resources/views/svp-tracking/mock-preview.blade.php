@extends('layouts.dashboard')

@section('title', 'Mock Document Movement | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">{{ $eyebrow }}</p>
            <h1>Document Movement</h1>
            <p>Mock hierarchy preview for the Status Tracking page.</p>
        </div>

        <a href="{{ route($backRoute) }}" class="dashboard-action secondary-action">Back to List</a>
    </section>

    <section class="table-panel svp-movement-page" aria-label="Mock document movement hierarchy">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Routing History</p>
                <h2>PPMP-2026-MOCK-0001</h2>
            </div>
            <span class="svp-mock-label">Mock only</span>
        </div>

        <x-documents.mock-movement-tree class="document-movement--status-tracking" />
    </section>
@endsection
