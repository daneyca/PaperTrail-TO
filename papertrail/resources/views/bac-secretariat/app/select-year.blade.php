@extends('layouts.dashboard')

@section('title', 'Choose APP Fiscal Year | PaperTrail')

@section('content')
    @php
        $currentPlan = $plansByYear[$currentYear] ?? null;
        $currentApproved = ($currentPlan['status'] ?? null) === \App\Models\AnnualProcurementPlan::STATUS_APPROVED;
    @endphp
    <section class="dashboard-hero admin-users-hero">
        <div><p class="eyebrow">APP Consolidation</p><h1>Create or Open APP</h1><p>Choose the fiscal year you are planning for. Each year has its own main Annual Procurement Plan.</p></div>
        <div class="hero-actions"><a href="{{ route('bac-secretariat.app.index') }}" class="dashboard-action secondary-action">Back to APP Records</a></div>
    </section>

    <section class="table-panel" aria-label="APP fiscal year selection">
        <div class="section-heading"><div><p class="eyebrow">Fiscal Year</p><h2>Which year are you planning for?</h2><p>You can prepare next year's APP in advance.</p></div></div>
        @if ($errors->any())<div role="alert" class="alert alert-danger"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <x-document-filter-card :action="route('bac-secretariat.app.create')" data-app-year-form>
            <div class="user-filter">
                <label for="app-create-year">Fiscal Year</label>
                <select id="app-create-year" name="fiscal_year" required data-app-create-year aria-describedby="app-year-help">
                    @foreach (range(2020, 2100) as $year)
                        <option value="{{ $year }}" @selected($year === $currentYear)>{{ $year }} — {{ isset($plansByYear[$year]) ? 'APP exists' : 'Available to create' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-actions"><button type="submit" data-app-year-action>{{ ! $currentPlan ? 'Create APP for '.$currentYear : ($currentPlan['editable'] ? 'Continue APP for '.$currentYear : 'View APP for '.$currentYear) }}</button></div>
        </x-document-filter-card>

        <div class="section-heading" aria-live="polite" aria-atomic="true">
            <div>
                <p><span class="status-pill {{ $currentPlan ? 'status-submitted' : 'status-approved' }}" data-app-year-status>{{ $currentPlan ? 'APP exists · '.$currentPlan['status_label'] : 'Available to create' }}</span></p>
                <h2 data-app-year-title>{{ $currentPlan ? $currentPlan['number'] : 'No active APP exists for CY '.$currentYear }}</h2>
                <p id="app-year-help" data-app-year-help>
                    @if (! $currentPlan)
                        You can create a new main APP for {{ $currentYear }}.
                    @elseif ($currentPlan['editable'])
                        Continue this year's existing APP. A second main APP for the same year is not needed.
                    @elseif ($currentApproved)
                        This year's APP is already approved. Use Supplemental APP for additional purchases, or select a different fiscal year for a new main APP.
                    @else
                        This year already has an APP. Open it to check its progress, or select a different fiscal year for a new main APP.
                    @endif
                </p>
                <a href="{{ $currentPlan['supplemental_url'] ?? '#' }}" class="dashboard-action secondary-action" data-app-year-supplemental @if (! $currentApproved) hidden style="display: none" @endif>Create Supplemental APP for {{ $currentYear }}</a>
                <noscript><p>Choose a fiscal year and use the button above. The system will open that year's existing APP or a new APP worksheet.</p></noscript>
            </div>
        </div>
    </section>
    <script type="application/json" data-app-year-plans>@json($plansByYear)</script>
    <script src="{{ asset('js/app-year-selection.js') }}?v={{ filemtime(public_path('js/app-year-selection.js')) }}" defer></script>
@endsection
