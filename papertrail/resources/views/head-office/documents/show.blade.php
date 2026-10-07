@extends('layouts.dashboard')

@section('title', $document->displayNumber() . ' | PaperTrail')

@section('content')
    @php
        $isPr = in_array($document->document_type, ['PR', 'Purchase Request'], true);
        $isPpmp = $document->document_type === 'PPMP';
        $isReturned = str_starts_with((string) $document->status, 'returned_') || in_array($document->status, ['pr_returned', 'returned'], true);
        $documentStatusLabel = function (?string $status): string {
            return match ($status) {
                \App\Models\ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES => 'Pending Signature',
                \App\Models\ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED => 'Signed',
                \App\Models\ProcurementDocument::STATUS_PENDING_PPMP_REVIEW => 'Submitted to BAC',
                \App\Models\ProcurementDocument::STATUS_UNDER_PPMP_REVIEW => 'Under APP Consolidation',
                \App\Models\ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION => 'Approved',
                default => str($status ?: 'Unknown')->replace('_', ' ')->title()->replace('Ppmp', 'PPMP')->toString(),
            };
        };
        $statusLabel = $documentStatusLabel($document->status);
        $editUrl = null;
        $editLabel = null;
        $printUrl = null;

        if ($canEdit && $isPpmp && Route::has('head-office.ppmp.edit')) {
            $editUrl = route('head-office.ppmp.edit', $document);
            $editLabel = $isReturned ? 'Revise PPMP' : 'Edit PPMP';
        }

        if ($canEdit && $isPr && Route::has('head-office.pr.edit')) {
            $editUrl = route('head-office.pr.edit', $document);
            $editLabel = $isReturned ? 'Revise PR' : 'Edit PR';
        }

        if ($isPr && Route::has('head-office.pr.print')) {
            $printUrl = route('head-office.pr.print', $document);
        }

        if ($isPpmp && Route::has('head-office.ppmp.print')) {
            $printUrl = route('head-office.ppmp.print', $document);
        }
    @endphp

    @if ($isPr)
        <section class="pr-view-page">
            <section class="pr-action-toolbar no-print" aria-label="Purchase Request actions">
                <div class="pr-toolbar-group">
                    <a href="{{ route('head-office.documents.index') }}" class="btn-pr-secondary">Back to My Documents</a>
                </div>

                <div class="pr-toolbar-group">
                    @if ($printUrl)
                        <a href="{{ $printUrl }}" class="btn-pr-secondary">Print PR</a>
                    @endif

                    <x-ai.completeness-check-button
                        document-type="purchase_request"
                        :document-id="$document->id"
                        :tracking-number="$document->displayNumber()"
                        label="AI Check PR"
                    />

                    @if ($editUrl)
                        <a href="{{ $editUrl }}" class="btn-pr-primary">Edit / Revise</a>
                    @endif
                </div>
            </section>

            <div class="pr-preview-area" aria-label="Purchase Request preview">
                @include('head-office.pr._preview', ['sheetClass' => 'pr-readonly-sheet my-documents-pr-sheet', 'mode' => 'show'])
            </div>

            <div id="pr-signatures">
                @include('head-office.pr._signatory-tracking-panel', ['document' => $document])
            </div>

            <x-documents.attachments-panel
                :document="$document"
                document-type="purchase_request"
                :can-upload="$canEdit"
                title="Supporting Documents"
            />

            @include('partials.svp-related-documents', ['source' => $document, 'context' => 'head-office'])

            @if ($document->supplementalApps->isNotEmpty())
                @php
                    $linkedSupplementalApp = $document->supplementalApps->sortByDesc(fn ($app) => $app->accepted_at ?? $app->updated_at)->first();
                @endphp

                <section class="table-panel no-print">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Supplemental APP</p>
                            <h2>Linked Supplemental APP</h2>
                        </div>
                    </div>

                    <div class="budget-review-record">
                        <p><strong>Supplemental APP No.:</strong> {{ $linkedSupplementalApp->supplemental_app_number ?? 'Draft' }}</p>
                        <p><strong>Status:</strong> {{ str($linkedSupplementalApp->status)->replace('_', ' ')->title() }}</p>
                        <p><strong>Total Amount:</strong> PHP {{ number_format((float) $linkedSupplementalApp->total_amount, 2) }}</p>
                        <p><strong>Accepted By:</strong> {{ $linkedSupplementalApp->acceptedBy?->name ?? 'N/A' }}</p>
                        <div class="table-actions">
                            <a href="{{ route('head-office.supplemental-apps.show', $linkedSupplementalApp) }}">View Supplemental APP</a>
                            <a href="{{ route('head-office.supplemental-apps.print', $linkedSupplementalApp) }}" target="_blank">Print</a>
                        </div>
                    </div>
                </section>
            @endif

        </section>
    @else
    @unless ($isPpmp)
        <section class="dashboard-hero admin-users-hero document-preview-hero">
            <div>
                <p class="eyebrow">Head of Office / End User</p>
                <h1>{{ $document->displayNumber() }}</h1>
                <p>{{ $document->title ?? $document->purpose ?? 'Formal procurement document preview' }}</p>
            </div>

            <div class="hero-actions">
                <span class="status-pill status-{{ $document->status }}">{{ $statusLabel }}</span>
            </div>
        </section>
    @endunless

    @if ($isPpmp)
        <div class="document-action-bar head-office-ppmp-toolbar no-print">
            <div class="head-office-ppmp-toolbar__group head-office-ppmp-toolbar__group--navigation">
                <a
                    href="{{ route('head-office.documents.index') }}"
                    class="dashboard-action secondary-action head-office-ppmp-back-action"
                    data-action-icon="false"
                    aria-label="Back to My Documents"
                    title="Back to My Documents"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M15.5 5.5 9 12l6.5 6.5" />
                    </svg>
                    <span class="sr-only">Back to My Documents</span>
                </a>
            </div>

            <div class="head-office-ppmp-toolbar__group head-office-ppmp-toolbar__group--ai">
                @if ($printUrl)
                    <a href="{{ $printUrl }}" class="dashboard-action secondary-action" target="_blank" rel="noopener">Print</a>
                @endif

                <x-ai.completeness-check-button
                    document-type="ppmp"
                    :document-id="$document->id"
                    :tracking-number="$document->displayNumber()"
                    label="AI Check PPMP"
                />
            </div>

            @if ($editUrl)
                <div class="head-office-ppmp-toolbar__group head-office-ppmp-toolbar__group--actions">
                    <a href="{{ $editUrl }}" class="dashboard-action">{{ $editLabel }}</a>
                </div>
            @endif
        </div>
    @else
        <div class="document-action-bar no-print">
            <a href="{{ route('head-office.documents.index') }}" class="dashboard-action secondary-action">Back to My Documents</a>

            @if ($printUrl)
                <a href="{{ $printUrl }}" class="dashboard-action secondary-action">Print</a>
            @endif

            @if ($editUrl)
                <a href="{{ $editUrl }}" class="dashboard-action">{{ $editLabel }}</a>
            @endif
        </div>
    @endif

    <section class="document-preview-wrap" aria-label="Document preview">
        @if ($isPpmp)
            @include('head-office.documents._ppmp-preview')
        @else
            @include('head-office.documents._generic-preview')
        @endif
    </section>

    <x-documents.attachments-panel
        :document="$document"
        :document-type="$isPpmp ? 'ppmp' : 'procurement_document'"
        :can-upload="$canEdit"
        title="Supporting Documents"
    />

    <section class="dashboard-widget-grid my-document-sections document-support-sections no-print">
        <article class="dashboard-widget widget-wide">
            <div class="widget-heading">
                <div>
                    <p class="eyebrow">Audit / Activity</p>
                    <h2>Related Activity</h2>
                </div>
            </div>

            <div class="my-document-activity-list">
                @forelse ($activities as $activity)
                    <div class="my-document-activity-row">
                        <strong>{{ $activity->action }}</strong>
                        <span>{{ $activity->created_at?->format('M d, Y h:i A') }} &middot; {{ $activity->user_name ?? 'System' }}</span>
                        <p>{{ $activity->description ?? 'No activity description provided.' }}</p>
                    </div>
                @empty
                    <div class="empty-state">
                        <strong>No related activity yet</strong>
                        <p>Audit entries tied to this document will appear here.</p>
                    </div>
                @endforelse
            </div>
        </article>
    </section>
    @endif
@endsection
