@extends('layouts.dashboard')

@section('title', $document->displayNumber() . ' | PaperTrail')

@section('content')
    @php
        $ppmpStatusLabel = function (?string $status): string {
            return match ($status) {
                \App\Models\ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES => 'Pending Signature',
                \App\Models\ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED => 'Signed',
                \App\Models\ProcurementDocument::STATUS_PENDING_PPMP_REVIEW => 'Submitted to BAC',
                \App\Models\ProcurementDocument::STATUS_UNDER_PPMP_REVIEW => 'Under APP Consolidation',
                \App\Models\ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION => 'Approved',
                default => str($status ?: 'Unknown')->replace('_', ' ')->title()->replace('Ppmp', 'PPMP')->toString(),
            };
        };
        $showSignatureStatus = ($ppmpSignatureProgress['required'] ?? 0) > 0
            || in_array($document->status, [
                \App\Models\ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES,
                \App\Models\ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED,
                \App\Models\ProcurementDocument::STATUS_PPMP_SIGNATURE_RETURNED,
            ], true);
        $submitLabel = $canSubmitToAppConsolidation ? 'Submit to BAC Secretariat' : 'Continue to E-Signature';
        $submitConfirm = $canSubmitToAppConsolidation
            ? 'Submit this signed PPMP to BAC Secretariat for APP consolidation?'
            : 'Generate the required Head of Office e-signature request for this PPMP?';
    @endphp

    <div class="document-action-bar head-office-ppmp-toolbar no-print">
        <div class="head-office-ppmp-toolbar__group head-office-ppmp-toolbar__group--navigation">
            <a
                href="{{ route('head-office.ppmp.index') }}"
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
            @if (Route::has('head-office.ppmp.print'))
                <a href="{{ route('head-office.ppmp.print', $document) }}" class="dashboard-action secondary-action" target="_blank" rel="noopener">Print PPMP</a>
            @endif
            <x-ai.completeness-check-button
                document-type="ppmp"
                :document-id="$document->id"
                :tracking-number="$document->displayNumber()"
                label="AI Check PPMP"
            />
        </div>

        @if ($canModify || $canSubmitToAppConsolidation)
            <div class="head-office-ppmp-toolbar__group head-office-ppmp-toolbar__group--actions">
                @if ($canModify)
                    <a href="{{ route('head-office.ppmp.edit', $document) }}" class="dashboard-action">Edit / Revise</a>
                    <form
                        method="POST"
                        action="{{ route('head-office.ppmp.destroy', $document) }}"
                        data-confirm-title="Delete PPMP?"
                        data-confirm="This will permanently remove this draft or returned PPMP record and its saved item rows."
                        data-confirm-label="Delete PPMP"
                        data-confirm-type="danger"
                    >
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="dashboard-action secondary-action">Delete</button>
                    </form>
                @endif
                <form
                    method="POST"
                    action="{{ route('head-office.ppmp.submit', $document) }}"
                    data-confirm="{{ $submitConfirm }}"
                    data-confirm-title="{{ $canSubmitToAppConsolidation ? 'Submit PPMP?' : 'Continue to E-Signature?' }}"
                    data-confirm-label="{{ $submitLabel }}"
                    data-confirm-type="submit"
                >
                    @csrf
                    <button type="submit" class="dashboard-action" data-action-icon="false">{{ $submitLabel }}</button>
                </form>
            </div>
        @endif
    </div>

    <section class="ppmp-readonly-strip head-office-ppmp-summary no-print" aria-label="PPMP summary">
        <div>
            <span class="head-office-ppmp-summary__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false"><path d="M4 7h16M4 12h16M4 17h10" /></svg>
            </span>
            <div>
                <span>Tracking Number</span>
                <strong>{{ $document->displayNumber() }}</strong>
            </div>
        </div>
        <div>
            <span class="head-office-ppmp-summary__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false"><path d="M4 5h16M4 12h10M4 19h16" /><path d="M18 9l2 2-2 2" /></svg>
            </span>
            <div>
                <span>Plan Type</span>
                <strong>{{ str($document->ppmp_plan_type ?: 'indicative')->title() }}</strong>
            </div>
        </div>
        <div>
            <span class="head-office-ppmp-summary__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false"><path d="M12 3v18M7 7h7a3 3 0 0 1 0 6H9a3 3 0 0 0 0 6h8" /></svg>
            </span>
            <div>
                <span>Total Budget</span>
                <strong>PHP {{ number_format((float) $document->total_amount, 2) }}</strong>
            </div>
        </div>
        <div>
            <span class="head-office-ppmp-summary__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false"><path d="M8 6h13M8 12h13M8 18h13" /><path d="M3 6h.01M3 12h.01M3 18h.01" /></svg>
            </span>
            <div>
                <span>Line Items</span>
                <strong>{{ $document->ppmpItems->count() }}</strong>
            </div>
        </div>
    </section>

    <section class="head-office-ppmp-preview-card" aria-label="Official PPMP preview">
        <div class="head-office-ppmp-preview-card__header no-print">
            <div>
                <p class="eyebrow">Official Form Preview</p>
                <h2>Project Procurement Plan</h2>
                <p>Readonly view of the submitted PPMP document for this end-user office.</p>
            </div>
        </div>

        @include('head-office.ppmp._official-form', [
            'mode' => 'show',
            'document' => $document,
            'items' => $document->ppmpItems,
            'signatureSlots' => $signatureSlots,
        ])
    </section>

    <x-documents.attachments-panel
        :document="$document"
        document-type="ppmp"
        :can-upload="$canModify"
        title="PPMP Supporting Documents"
    />

    <section class="dashboard-widget widget-wide head-office-ppmp-routing no-print">
        <div class="widget-heading">
            <div>
                <p class="eyebrow">Routing History</p>
                <h2>Document Movement</h2>
            </div>
        </div>

        <ol class="routing-timeline">
            @forelse ($document->routingHistories as $history)
                <li>
                    <strong>{{ $history->action }}</strong>
                    <span>{{ $history->action_at?->format('M d, Y h:i A') }} by {{ $history->actionBy?->name ?? 'System' }}</span>
                            <p>{{ $history->fromOffice?->name ?? 'N/A' }} to {{ $history->toOffice?->name ?? 'N/A' }} &middot; {{ $ppmpStatusLabel($history->status_from ?? 'new') }} to {{ $ppmpStatusLabel($history->status_to) }}</p>
                    @if ($history->comments)
                        <p>{{ $history->comments }}</p>
                    @endif
                </li>
            @empty
                <li><strong>No routing history yet</strong><span>Routing history will appear after submission.</span></li>
            @endforelse
        </ol>
    </section>
@endsection
