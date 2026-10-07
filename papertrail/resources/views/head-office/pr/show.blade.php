@extends('layouts.dashboard')

@section('title', $document->displayNumber() . ' | PaperTrail')

@section('content')
    @php
        $prNumber = $document->pr_no ?? 'To be assigned';
        $prDate = $document->pr_date?->format('F d, Y') ?? 'Not assigned';
        $requestingOffice = $document->submittingOffice?->name ?? $document->department_name ?? 'N/A';
        $submittedDate = $document->submitted_at?->format('F d, Y h:i A') ?? 'Not submitted';
        $currentHandler = $document->assignedTo?->user_id
            ?? $document->assignedTo?->name
            ?? $document->currentOffice?->name
            ?? 'Unassigned';
        $statusText = strtolower((string) ($document->status ?? 'draft'));
        $statusLabel = str($document->status ?? 'draft')->replace(['_', '-'], ' ')->title();
        $statusTone = match (true) {
            str_contains($statusText, 'return'), str_contains($statusText, 'reject') => 'returned',
            str_contains($statusText, 'complete'), str_contains($statusText, 'signed'), str_contains($statusText, 'assigned'), str_contains($statusText, 'ready'), str_contains($statusText, 'approved') => 'complete',
            str_contains($statusText, 'pending'), str_contains($statusText, 'submitted'), str_contains($statusText, 'signature'), str_contains($statusText, 'number') => 'pending',
            default => 'neutral',
        };
        $appReference = $document->appConsolidation ?? $document->appItem?->appConsolidation;
        $appReferenceNumber = $appReference?->app_number ?? 'Not selected';
        $appItemDescription = $document->appItem?->general_description ?? 'No APP item linked';
        $appReferenceStatus = ($document->app_item_id && $appReference && in_array($appReference->status, [
            \App\Models\AppConsolidation::STATUS_CONSOLIDATED,
            \App\Models\AppConsolidation::STATUS_SUBMITTED_FOR_APPROVAL,
            \App\Models\AppConsolidation::STATUS_APPROVED,
        ], true))
            ? 'Verified'
            : 'Not linked';
        $purchaseRequestInfo = [
            ['label' => 'Official PR Number', 'value' => $prNumber],
            ['label' => 'Tracking Number', 'value' => $document->displayNumber()],
            ['label' => 'PR Date', 'value' => $prDate],
            ['label' => 'Requesting Office', 'value' => $requestingOffice],
            ['label' => 'Total Amount', 'value' => 'PHP ' . number_format((float) $document->total_amount, 2)],
            ['label' => 'Status', 'value' => $statusLabel],
            ['label' => 'Submitted Date', 'value' => $submittedDate],
            ['label' => 'Current Handler', 'value' => $currentHandler],
            ['label' => 'APP Reference', 'value' => $appReferenceNumber],
            ['label' => 'APP Item', 'value' => str($appItemDescription)->limit(90)->toString()],
            ['label' => 'APP Status', 'value' => $appReferenceStatus],
        ];
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
                    <form method="POST" action="{{ route('head-office.pr.submit', $document) }}" onsubmit="return confirm('Submit this signed Purchase Request to PR Numbering Staff?');">
                        @csrf
                        <button type="submit" class="btn-pr-primary">Submit for PR Number</button>
                    </form>
                @endif
                @if ($canModify)
                    <a href="{{ route('head-office.pr.edit', $document) }}" class="btn-pr-primary">Edit / Revise</a>
                @endif
            </div>
        </section>

        <x-ui.document-info
            class="no-print"
            eyebrow="Purchase Request Details"
            :title="$document->displayNumber()"
            description="Tracking number, official PR information, routing state, and current document handler."
            :items="$purchaseRequestInfo"
        >
            <x-slot name="status">
                <span class="pr-details-status-badge is-{{ $statusTone }}">{{ $statusLabel }}</span>
            </x-slot>
        </x-ui.document-info>

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

                <form method="POST" action="{{ route('head-office.pr.submit', $document) }}" onsubmit="return confirm('Send a copy of this completed Purchase Request to BACSEC-002 for BAC Resolution preparation?');">
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
            :can-upload="$canModify"
            title="Supporting Files"
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
