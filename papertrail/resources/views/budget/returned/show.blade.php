@extends('layouts.dashboard')

@section('title', $document->tracking_number . ' | Returned Document')

@section('content')
    @php
        $returnedDate = $document->budget_reviewed_at ?? $latestBudgetReview?->completed_at ?? $returnHistory?->action_at;
        $returnedBy = $document->budgetReviewedBy?->name ?? $latestBudgetReview?->reviewedBy?->name ?? $returnHistory?->actionBy?->name ?? 'N/A';
        $returnReason = $document->budget_remarks ?? $latestBudgetReview?->remarks ?? $returnHistory?->comments ?? $document->remarks ?? 'N/A';
    @endphp

    <section class="dashboard-hero admin-users-hero budget-returned-header">
        <div>
            <p class="eyebrow">Returned Document</p>
            <h1>{{ $document->tracking_number }}</h1>
            <p>{{ $document->title }}</p>
        </div>

        <div class="hero-actions no-print">
            <a href="{{ route('budget.returned.index') }}" class="dashboard-action secondary-action">Back to Returned Documents</a>
            <x-ai.completeness-check-button
                document-type="purchase_request"
                :document-id="$document->id"
                :tracking-number="$document->pr_no ?? $document->tracking_number"
                label="AI Check PR"
            />
        </div>
    </section>

    <section class="budget-review-layout reviewed-detail-layout">
        <article class="table-panel budget-detail-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Document Information</p>
                    <h2>{{ $document->document_type }}</h2>
                    <p>{{ $document->description ?? 'No description provided.' }}</p>
                </div>
                <span class="status-pill status-{{ $document->status }}">{{ str($document->status)->replace('_', ' ')->title() }}</span>
            </div>

            <div class="detail-grid budget-detail-grid">
                <div><span>Tracking Number</span><strong>{{ $document->tracking_number }}</strong></div>
                <div><span>Document Type</span><strong>{{ $document->document_type }}</strong></div>
                <div><span>Fiscal Year</span><strong>{{ $document->fiscal_year }}</strong></div>
                <div><span>Requesting Office</span><strong>{{ $document->submittingOffice?->name ?? 'N/A' }}</strong></div>
                <div><span>Submitted By</span><strong>{{ $document->submittedBy?->name ?? 'N/A' }}</strong></div>
                <div><span>Total Amount</span><strong>PHP {{ number_format((float) $document->total_amount, 2) }}</strong></div>
                <div><span>Current Status</span><strong>{{ str($document->status)->replace('_', ' ')->title() }}</strong></div>
                <div><span>Current Office</span><strong>{{ $document->currentOffice?->name ?? 'N/A' }}</strong></div>
                <div><span>Returned Date</span><strong>{{ $returnedDate?->format('M d, Y h:i A') ?? 'N/A' }}</strong></div>
                <div><span>Returned By</span><strong>{{ $returnedBy }}</strong></div>
            </div>
        </article>

        <aside class="table-panel budget-action-panel reviewed-summary-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Return Reason</p>
                    <h2>Budget Remarks</h2>
                </div>
            </div>

            <div class="budget-review-record reviewed-record-list">
                <p><strong>Budget Status:</strong> {{ str($document->budget_status ?? $latestBudgetReview?->review_status ?? 'returned')->replace('_', ' ')->title() }}</p>
                <p><strong>Reason / Remarks:</strong> {{ $returnReason }}</p>
            </div>
        </aside>
    </section>

    <section class="dashboard-widget-grid">
        <article class="dashboard-widget">
            <div class="widget-heading">
                <div>
                    <p class="eyebrow">Budget Review Summary</p>
                    <h2>Latest Return Record</h2>
                </div>
            </div>
            @if ($latestBudgetReview)
                <div class="budget-review-record reviewed-record-list">
                    <p><strong>Status:</strong> {{ str($latestBudgetReview->review_status)->replace('_', ' ')->title() }}</p>
                    <p><strong>Reviewed By:</strong> {{ $latestBudgetReview->reviewedBy?->name ?? 'N/A' }}</p>
                    <p><strong>Review Started:</strong> {{ $latestBudgetReview->started_at?->format('M d, Y h:i A') ?? $document->budget_review_started_at?->format('M d, Y h:i A') ?? 'N/A' }}</p>
                    <p><strong>Returned At:</strong> {{ $latestBudgetReview->completed_at?->format('M d, Y h:i A') ?? $document->budget_reviewed_at?->format('M d, Y h:i A') ?? 'N/A' }}</p>
                    <p><strong>Requested Amount:</strong> PHP {{ number_format((float) $latestBudgetReview->requested_amount, 2) }}</p>
                    <p><strong>Remarks:</strong> {{ $latestBudgetReview->remarks ?? 'N/A' }}</p>
                </div>
            @else
                <div class="empty-state"><strong>No return review record found</strong><p>Routing history and document remarks are shown where available.</p></div>
            @endif
        </article>

        <article class="dashboard-widget widget-wide">
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
                        <p>{{ $history->fromOffice?->name ?? 'N/A' }} to {{ $history->toOffice?->name ?? 'N/A' }} - {{ str($history->status_from ?? 'new')->replace('_', ' ')->title() }} to {{ str($history->status_to)->replace('_', ' ')->title() }}</p>
                        @if ($history->comments)
                            <p>{{ $history->comments }}</p>
                        @endif
                    </li>
                @empty
                    <li><strong>No routing history yet</strong><span>Return routing actions will appear here after workflow movement is recorded.</span></li>
                @endforelse
            </ol>
        </article>

        <article class="dashboard-widget">
            <div class="widget-heading">
                <div>
                    <p class="eyebrow">Items</p>
                    <h2>Procurement Items</h2>
                </div>
            </div>
            <div class="empty-state"><strong>No item module yet</strong><p>Procurement document items will appear here after the itemization module is implemented.</p></div>
        </article>

        <x-documents.attachments-panel
            :document="$document"
            :can-upload="false"
            :can-delete="false"
            title="Document Files"
        />
    </section>
@endsection
