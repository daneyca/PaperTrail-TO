@extends('layouts.dashboard')

@section('title', $document->tracking_number . ' | Reviewed Accounting Document')

@section('content')
    <section class="dashboard-hero admin-users-hero budget-reviewed-header">
        <div>
            <p class="eyebrow">Reviewed Accounting Document</p>
            <h1>{{ $document->tracking_number }}</h1>
            <p>{{ $document->title }}</p>
        </div>

        <div class="hero-actions no-print">
            <a href="{{ route('accounting.reviewed.index') }}" class="dashboard-action secondary-action">Back to Reviewed Documents</a>
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
                <div><span>Submitted Date</span><strong>{{ $document->submitted_at?->format('M d, Y h:i A') ?? 'N/A' }}</strong></div>
                <div><span>Total Amount</span><strong>PHP {{ number_format((float) $document->total_amount, 2) }}</strong></div>
                <div><span>Current Status</span><strong>{{ str($document->status)->replace('_', ' ')->title() }}</strong></div>
                <div><span>Current Stage</span><strong>{{ $document->stage ?? 'N/A' }}</strong></div>
                <div><span>Current Office</span><strong>{{ $document->currentOffice?->name ?? 'N/A' }}</strong></div>
            </div>
        </article>

        <aside class="table-panel budget-action-panel reviewed-summary-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Accounting Review Summary</p>
                    <h2>Read-only Record</h2>
                </div>
            </div>

            @if ($latestAccountingReview)
                <div class="budget-review-record reviewed-record-list">
                    <p><strong>Reviewed By:</strong> {{ $latestAccountingReview->reviewedBy?->name ?? $document->accountingReviewedBy?->name ?? 'N/A' }}</p>
                    <p><strong>Review Started:</strong> {{ $latestAccountingReview->started_at?->format('M d, Y h:i A') ?? $document->accounting_review_started_at?->format('M d, Y h:i A') ?? 'N/A' }}</p>
                    <p><strong>Reviewed At:</strong> {{ $latestAccountingReview->completed_at?->format('M d, Y h:i A') ?? $document->accounting_reviewed_at?->format('M d, Y h:i A') ?? 'N/A' }}</p>
                    <p><strong>Accounting Status:</strong> {{ str($document->accounting_status ?? $latestAccountingReview->review_status)->replace('_', ' ')->title() }}</p>
                    <p><strong>Reference No.:</strong> {{ $document->accounting_reference_no ?? $latestAccountingReview->accounting_reference_no ?? 'N/A' }}</p>
                    <p><strong>Account Code:</strong> {{ $document->account_code ?? $latestAccountingReview->account_code ?? 'N/A' }}</p>
                    <p><strong>Object Code:</strong> {{ $document->object_code ?? $latestAccountingReview->object_code ?? 'N/A' }}</p>
                    <p><strong>Responsibility Center:</strong> {{ $document->responsibility_center ?? $latestAccountingReview->responsibility_center ?? 'N/A' }}</p>
                    <p><strong>Accounting Remarks:</strong> {{ $document->accounting_remarks ?? $latestAccountingReview->remarks ?? 'N/A' }}</p>
                </div>
            @else
                <div class="empty-state"><strong>No accounting review record found</strong><p>The document was marked reviewed, but no accounting review entry is available.</p></div>
            @endif
        </aside>
    </section>

    <section class="dashboard-widget-grid">
        <article class="dashboard-widget">
            <div class="widget-heading">
                <div>
                    <p class="eyebrow">Budget Review Summary</p>
                    <h2>Budget Details</h2>
                </div>
            </div>
            @if ($latestBudgetReview)
                <div class="budget-review-record">
                    <p><strong>Budget Status:</strong> {{ str($document->budget_status ?? $latestBudgetReview->review_status)->replace('_', ' ')->title() }}</p>
                    <p><strong>Reviewed By:</strong> {{ $latestBudgetReview->reviewedBy?->name ?? $document->budgetReviewedBy?->name ?? 'N/A' }}</p>
                    <p><strong>Fund Source:</strong> {{ $latestBudgetReview->fund_source ?? 'N/A' }}</p>
                    <p><strong>Appropriation Code:</strong> {{ $latestBudgetReview->appropriation_code ?? 'N/A' }}</p>
                    <p><strong>Responsibility Center:</strong> {{ $latestBudgetReview->responsibility_center ?? 'N/A' }}</p>
                    <p><strong>Available Amount:</strong> {{ $latestBudgetReview->available_amount !== null ? 'PHP ' . number_format((float) $latestBudgetReview->available_amount, 2) : 'N/A' }}</p>
                    <p><strong>Budget Remarks:</strong> {{ $document->budget_remarks ?? $latestBudgetReview->remarks ?? 'N/A' }}</p>
                    <p><strong>Budget Reviewed Date:</strong> {{ $latestBudgetReview->completed_at?->format('M d, Y h:i A') ?? $document->budget_reviewed_at?->format('M d, Y h:i A') ?? 'N/A' }}</p>
                </div>
            @else
                <div class="empty-state"><strong>No budget review record</strong><p>Budget review details will appear here once available.</p></div>
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
                        <p>{{ $history->fromOffice?->name ?? 'N/A' }} to {{ $history->toOffice?->name ?? 'N/A' }} &middot; {{ str($history->status_from ?? 'new')->replace('_', ' ')->title() }} to {{ str($history->status_to)->replace('_', ' ')->title() }}</p>
                        @if ($history->comments)
                            <p>{{ $history->comments }}</p>
                        @endif
                    </li>
                @empty
                    <li><strong>No routing history yet</strong><span>Routing actions will appear here after workflow movement is recorded.</span></li>
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
