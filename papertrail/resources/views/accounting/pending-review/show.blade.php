@extends('layouts.dashboard')

@section('title', $document->tracking_number . ' | Accounting Review')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Accounting Review Detail</p>
            <h1>{{ $document->tracking_number }}</h1>
            <p>{{ $document->title }}</p>
        </div>

        <div class="hero-actions no-print">
            <a href="{{ route('accounting.pending-review.index') }}" class="dashboard-action secondary-action">Back to Queue</a>
            <x-ai.completeness-check-button
                document-type="purchase_request"
                :document-id="$document->id"
                :tracking-number="$document->pr_no ?? $document->tracking_number"
                label="AI Check PR"
            />
        </div>
    </section>

    <section class="budget-review-layout">
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
                <div><span>Fiscal Year</span><strong>{{ $document->fiscal_year }}</strong></div>
                <div><span>Requesting Office</span><strong>{{ $document->submittingOffice?->name ?? 'N/A' }}</strong></div>
                <div><span>Submitted By</span><strong>{{ $document->submittedBy?->name ?? 'N/A' }}</strong></div>
                <div><span>Submitted Date</span><strong>{{ $document->submitted_at?->format('M d, Y h:i A') ?? 'N/A' }}</strong></div>
                <div><span>Total Amount</span><strong>PHP {{ number_format((float) $document->total_amount, 2) }}</strong></div>
                <div><span>Current Status</span><strong>{{ str($document->status)->replace('_', ' ')->title() }}</strong></div>
                <div><span>Current Stage</span><strong>{{ $document->stage ?? 'N/A' }}</strong></div>
                <div><span>Current Office</span><strong>{{ $document->currentOffice?->name ?? 'N/A' }}</strong></div>
                <div><span>Priority</span><strong>{{ ucfirst($document->priority) }}</strong></div>
            </div>
        </article>

        <aside class="table-panel budget-action-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Accounting Actions</p>
                    <h2>Review Controls</h2>
                </div>
            </div>

            @if ($document->status === 'pending_accounting_review')
                <form method="POST" action="{{ route('accounting.pending-review.start', $document) }}" onsubmit="return confirm('Start accounting review for this document?');" class="budget-action-form">
                    @csrf
                    @method('PATCH')
                    <button type="submit">Start Review</button>
                </form>
            @endif

            @if (in_array($document->status, ['pending_accounting_review', 'under_accounting_review'], true))
                <form method="POST" action="{{ route('accounting.pending-review.return', $document) }}" onsubmit="return confirm('Return this document from Accounting review?');" class="budget-action-form">
                    @csrf
                    @method('PATCH')

                    <label for="return_target">Return Target</label>
                    <select id="return_target" name="return_target" required>
                        <option value="budget" @selected(old('return_target', 'budget') === 'budget')>Return to Budget Office</option>
                        <option value="requesting_office" @selected(old('return_target') === 'requesting_office')>Return to Requesting Office</option>
                    </select>
                    @error('return_target')
                        <span class="field-error">{{ $message }}</span>
                    @enderror

                    <label for="comments">Return Reason</label>
                    <textarea id="comments" name="comments" rows="4" required placeholder="Explain incomplete requirements, wrong details, or accounting concerns.">{{ old('comments') }}</textarea>
                    @error('comments')
                        <span class="field-error">{{ $message }}</span>
                    @enderror

                    <button type="submit" class="danger-action">Return Document</button>
                </form>
            @endif

            @if ($document->status === 'under_accounting_review')
                <form method="POST" action="{{ route('accounting.pending-review.verify', $document) }}" onsubmit="return confirm('Mark accounting verified and forward this document to BAC Secretariat?');" class="budget-action-form">
                    @csrf
                    @method('PATCH')

                    <label for="accounting_reference_no">Accounting Reference Number</label>
                    <input id="accounting_reference_no" name="accounting_reference_no" type="text" value="{{ old('accounting_reference_no', $document->accounting_reference_no) }}">

                    <label for="account_code">Account Code</label>
                    <input id="account_code" name="account_code" type="text" value="{{ old('account_code', $document->account_code) }}" required>
                    @error('account_code')
                        <span class="field-error">{{ $message }}</span>
                    @enderror

                    <label for="object_code">Object Code</label>
                    <input id="object_code" name="object_code" type="text" value="{{ old('object_code', $document->object_code) }}">

                    <label for="responsibility_center">Responsibility Center</label>
                    <input id="responsibility_center" name="responsibility_center" type="text" value="{{ old('responsibility_center', $document->responsibility_center) }}" required>
                    @error('responsibility_center')
                        <span class="field-error">{{ $message }}</span>
                    @enderror

                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="3">{{ old('remarks', $document->accounting_remarks) }}</textarea>

                    <button type="submit">Mark Accounting Verified / Endorse</button>
                </form>
            @endif

            @if (!in_array($document->status, ['pending_accounting_review', 'under_accounting_review'], true))
                <div class="empty-state">
                    <strong>No accounting action available</strong>
                    <p>This document has already moved out of the pending accounting review queue.</p>
                </div>
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

        <article class="dashboard-widget">
            <div class="widget-heading">
                <div>
                    <p class="eyebrow">Accounting Review</p>
                    <h2>Latest Review Record</h2>
                </div>
            </div>
            @if ($latestAccountingReview)
                <div class="budget-review-record">
                    <p><strong>Status:</strong> {{ str($latestAccountingReview->review_status)->replace('_', ' ')->title() }}</p>
                    <p><strong>Reviewed By:</strong> {{ $latestAccountingReview->reviewedBy?->name ?? 'N/A' }}</p>
                    <p><strong>Reference No.:</strong> {{ $latestAccountingReview->accounting_reference_no ?? 'N/A' }}</p>
                    <p><strong>Account Code:</strong> {{ $latestAccountingReview->account_code ?? 'N/A' }}</p>
                    <p><strong>Object Code:</strong> {{ $latestAccountingReview->object_code ?? 'N/A' }}</p>
                    <p><strong>Responsibility Center:</strong> {{ $latestAccountingReview->responsibility_center ?? 'N/A' }}</p>
                    <p><strong>Remarks:</strong> {{ $latestAccountingReview->remarks ?? 'N/A' }}</p>
                </div>
            @else
                <div class="empty-state"><strong>No accounting review record yet</strong><p>Start review to create the first accounting review record.</p></div>
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
                    <li><strong>No routing history yet</strong><span>Actions taken in this module will appear here.</span></li>
                @endforelse
            </ol>
        </article>

        <x-documents.attachments-panel
            :document="$document"
            :can-upload="false"
            :can-delete="false"
            title="Document Files"
        />

        <article class="dashboard-widget">
            <div class="widget-heading">
                <div>
                    <p class="eyebrow">Items</p>
                    <h2>Document Items</h2>
                </div>
            </div>
            <div class="empty-state"><strong>No item module yet</strong><p>Line items will appear here after procurement item encoding is implemented.</p></div>
        </article>
    </section>
@endsection
