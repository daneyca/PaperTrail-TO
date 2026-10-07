@extends('layouts.dashboard')

@section('title', ($document->tracking_number ?? 'PPMP Review') . ' | PaperTrail')

@section('content')
    @php
        $ppmpStatusLabel = function (?string $status): string {
            return match ($status) {
                \App\Models\ProcurementDocument::STATUS_PENDING_PPMP_REVIEW => 'Submitted to BAC',
                \App\Models\ProcurementDocument::STATUS_UNDER_PPMP_REVIEW => 'Under APP Consolidation',
                \App\Models\ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT => 'Returned',
                \App\Models\ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION => 'Approved',
                default => str($status ?: 'Unknown')->replace('_', ' ')->title()->replace('Ppmp', 'PPMP')->toString(),
            };
        };

    @endphp

    <section class="dashboard-hero admin-users-hero ppmp-page-hero">
        <div>
            <p class="eyebrow">BAC Secretariat</p>
            <h1>{{ $document->tracking_number ?? 'PPMP Review' }}</h1>
            <p>{{ $document->submittingOffice?->name ?? 'Requesting Office' }} &middot; Fiscal Year {{ $document->fiscal_year }}</p>
        </div>
        <div class="hero-actions">
            <span class="status-pill status-{{ $document->status }}">{{ $ppmpStatusLabel($document->status) }}</span>
        </div>
    </section>

    <div class="document-action-bar head-office-ppmp-toolbar document-review-actions-bar no-print">
        <div class="head-office-ppmp-toolbar__group head-office-ppmp-toolbar__group--navigation">
            <a
                href="{{ route('bac-secretariat.ppmp.index') }}"
                class="dashboard-action secondary-action head-office-ppmp-back-action"
                data-action-icon="false"
                aria-label="Back to PPMP Review"
                title="Back to PPMP Review"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M15.5 5.5 9 12l6.5 6.5" />
                </svg>
                <span class="sr-only">Back to PPMP Review</span>
            </a>
        </div>

        <div class="head-office-ppmp-toolbar__group head-office-ppmp-toolbar__group--ai">
            @if (Route::has('bac-secretariat.ppmp.print'))
                <a href="{{ route('bac-secretariat.ppmp.print', $document) }}" class="dashboard-action secondary-action" target="_blank" rel="noopener">Print PPMP</a>
            @endif
            <x-ai.completeness-check-button
                document-type="ppmp"
                :document-id="$document->id"
                :tracking-number="$document->tracking_number ?? $document->ppmp_no"
                label="AI Check PPMP"
            />
        </div>
    </div>

    <section class="document-review-shell">
        <div class="document-review-main">
            <div class="document-preview-scroll" aria-label="Official PPMP document preview">
                @include('head-office.ppmp._official-form', [
                    'mode' => 'show',
                    'document' => $document,
                    'items' => $document->ppmpItems,
                ])
            </div>
        </div>

        <aside class="document-review-sidebar no-print">
            <x-documents.attachments-panel
                :document="$document"
                document-type="ppmp"
                title="PPMP Supporting Documents"
            />
        </aside>
    </section>
@endsection
