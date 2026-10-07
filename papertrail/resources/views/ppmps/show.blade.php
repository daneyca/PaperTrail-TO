@extends('layouts.dashboard')

@section('title', $ppmp->displayNumber() . ' | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">PPMP Preview</p>
            <h1>{{ $ppmp->displayNumber() }}</h1>
            <p>{{ $ppmp->end_user_unit }} &middot; Fiscal Year {{ $ppmp->fiscal_year }}</p>
        </div>
        <div class="hero-actions">
            <span class="status-pill status-{{ $ppmp->status }}">{{ str($ppmp->status)->replace('_', ' ')->title()->replace('Ppmp', 'PPMP') }}</span>
        </div>
    </section>

    <div class="document-action-bar head-office-ppmp-toolbar no-print">
        <div class="head-office-ppmp-toolbar__group head-office-ppmp-toolbar__group--navigation">
            <a
                href="{{ route('ppmps.index') }}"
                class="dashboard-action secondary-action head-office-ppmp-back-action"
                data-action-icon="false"
                aria-label="Back to PPMP"
                title="Back to PPMP"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M15.5 5.5 9 12l6.5 6.5" />
                </svg>
                <span class="sr-only">Back to PPMP</span>
            </a>
        </div>

        <div class="head-office-ppmp-toolbar__group head-office-ppmp-toolbar__group--ai">
            <a href="{{ route('ppmps.print', $ppmp) }}" class="dashboard-action secondary-action" target="_blank" rel="noopener">Print</a>
            <x-ai.completeness-check-button
                document-type="ppmp_record"
                :document-id="$ppmp->id"
                :tracking-number="$ppmp->displayNumber()"
                label="AI Check PPMP"
            />
        </div>

        @if ($ppmp->isEditable())
            <div class="head-office-ppmp-toolbar__group head-office-ppmp-toolbar__group--actions">
                <form
                    method="POST"
                    action="{{ route('ppmps.submit', $ppmp) }}"
                    data-confirm="PPMP requires Head of Office e-signature before submission to BAC Secretariat for APP consolidation. Continue?"
                    data-confirm-title="Submit PPMP?"
                    data-confirm-label="Submit"
                    data-confirm-type="submit"
                >
                    @csrf
                    <button type="submit" class="dashboard-action" data-action-icon="false">Submit</button>
                </form>
                <a href="{{ route('ppmps.edit', $ppmp) }}" class="dashboard-action">Edit</a>
                <form method="POST" action="{{ route('ppmps.destroy', $ppmp) }}" onsubmit="return confirm('Delete this PPMP record?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="dashboard-action secondary-action">Delete</button>
                </form>
            </div>
        @endif
    </div>

    <section class="ppmp-readonly-strip" aria-label="PPMP summary">
        <div>
            <span>Plan Type</span>
            <strong>{{ str($ppmp->plan_type)->title() }}</strong>
        </div>
        <div>
            <span>Total Budget</span>
            <strong>PHP {{ number_format((float) $ppmp->total_budget, 2) }}</strong>
        </div>
        <div>
            <span>Line Items</span>
            <strong>{{ $ppmp->items->count() }}</strong>
        </div>
    </section>

    <section class="ppmp-preview-scroll" aria-label="Official PPMP preview">
        @include('ppmps.partials.official-form', ['ppmp' => $ppmp, 'items' => $ppmp->items, 'mode' => 'show'])
    </section>
@endsection
