@extends('layouts.dashboard')

@section('title', $document->displayNumber() . ' | PaperTrail')

@section('content')
    @php
        $canModify = auth()->user()->canManageSvpDocuments() && ($canModify ?? false);
        $canSubmitToBac = auth()->user()->canManageSvpDocuments() && ($canSubmitToBac ?? false);
        $canSubmitForPrNumber = auth()->user()->canManageSvpDocuments() && ($canSubmitForPrNumber ?? false);
        $statusLabel = str($document->status ?? 'draft')->replace(['_', '-'], ' ')->title();
    @endphp

    <section class="pr-view-page">
        <section class="pr-action-toolbar no-print" aria-label="Purchase Request actions">
            <div class="pr-toolbar-group">
                <a href="{{ route('head-office.pr.index') }}" class="btn-pr-secondary">Back</a>
                <a href="#supporting-files" class="btn-pr-secondary">Supporting Files</a>
                @if ($document->status === \App\Models\ProcurementDocument::STATUS_PR_PENDING_SIGNATORIES)
                    <a href="#pr-signatures" class="btn-pr-secondary">View Signatures</a>
                @endif
            </div>
            <div class="pr-toolbar-group">
                <x-ai.completeness-check-button
                    document-type="purchase_request"
                    :document-id="$document->id"
                    :tracking-number="$document->displayNumber()"
                />
                <a href="{{ route('head-office.pr.print', $document) }}" class="btn-pr-primary">Print PR</a>
                @if ($canSubmitForPrNumber ?? false)
                    <form method="POST" action="{{ route('head-office.pr.submit', $document) }}" onsubmit="return confirm('Submit this signed Purchase Request to the Procurement Controller?');">
                        @csrf
                        <button type="submit" class="btn-pr-primary">Submit for PR Number</button>
                    </form>
                @endif
                @if ($canModify)
                    <a href="{{ route('head-office.pr.edit', $document) }}" class="btn-pr-primary">Edit / Revise</a>
                @endif
            </div>
        </section>

        @if (! empty($nextWorkflowAction))
            <section class="pr-next-action-card no-print" aria-labelledby="pr-next-action-title">
                <div class="pr-next-action-heading">
                    <div>
                        <p class="pr-page-kicker">Next Action</p>
                        <h2 id="pr-next-action-title">{{ $nextWorkflowAction['next_process'] }}</h2>
                        <p>{{ $nextWorkflowAction['description'] }}</p>
                    </div>
                    <span class="pr-details-status-badge is-pending">Pending Action</span>
                </div>

                <div class="pr-next-action-grid">
                    <div>
                        <span>Next Process</span>
                        <strong>{{ $nextWorkflowAction['next_process'] }}</strong>
                    </div>
                    <div>
                        <span>Assigned Office</span>
                        <strong>{{ $nextWorkflowAction['assigned_office'] }}</strong>
                    </div>
                    <div>
                        <span>Assigned Account</span>
                        <strong>{{ $nextWorkflowAction['assigned_account'] }}</strong>
                    </div>
                    <div>
                        <span>Amount Path</span>
                        <strong>{{ $nextWorkflowAction['amount_path'] }}</strong>
                    </div>
                </div>

                <form method="POST" action="{{ route('head-office.pr.submit', $document) }}" onsubmit="return confirm('Send a copy of this completed Purchase Request to BACSEC-001 for BAC Resolution preparation?');">
                    @csrf
                    <button type="submit" class="btn-pr-primary">{{ $nextWorkflowAction['button_label'] }}</button>
                </form>
            </section>
        @endif

        <section class="pr-document-preview-card" aria-label="Purchase Request document preview">
            <div class="pr-document-preview-heading no-print">
                <div>
                    <p class="pr-page-kicker">Official Form Preview</p>
                    <h2>Purchase Request Form</h2>
                    <p>{{ $document->displayNumber() }} &middot; {{ $statusLabel }}</p>
                </div>
            </div>

            <div class="pr-preview-area">
                @include('head-office.pr._preview', ['sheetClass' => 'pr-readonly-sheet', 'mode' => 'show'])
            </div>
        </section>

        <div id="pr-signatures">
            @include('head-office.pr._signatory-tracking-panel', ['document' => $document])
        </div>

        <x-documents.attachments-panel
            id="supporting-files"
            :document="$document"
            document-type="purchase_request"
            title="Supporting Files"
            read-only-message="Procurement Staff can add files to draft or returned PRs, and while awaiting signatures before the first signature."
        />

        @include('partials.svp-related-documents', ['source' => $document, 'context' => 'head-office'])

        <section class="pr-routing-panel no-print">
            <div class="pr-routing-heading">
                <p class="pr-page-kicker">Routing History</p>
                <h2>Routing History</h2>
            </div>

            <div class="pr-routing-list">
                @forelse ($document->routingHistories->sortByDesc('action_at') as $history)
                    <article class="pr-routing-item">
                        <time>{{ $history->action_at?->format('M d, Y h:i A') ?? 'No timestamp' }}</time>
                        <strong>{{ $history->action }}</strong>
                        <p>{{ $history->comments ?? 'No comments recorded.' }}</p>
                    </article>
                @empty
                    <div class="empty-state">
                        <strong>No routing history yet</strong>
                        <p>Routing history will appear after the PR is submitted.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </section>
@endsection
