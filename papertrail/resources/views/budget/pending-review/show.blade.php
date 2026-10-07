@extends('layouts.dashboard')

@section('title', $document->tracking_number . ' | Budget Review')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Budget Review Detail</p>
            <h1>{{ $document->tracking_number }}</h1>
            <p>{{ $document->title }}</p>
        </div>

        <div class="hero-actions no-print">
            <a href="{{ route('budget.pending-review.index') }}" class="dashboard-action secondary-action">Back to Queue</a>
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
                <div><span>Current Stage</span><strong>{{ $document->stage ?? 'N/A' }}</strong></div>
                <div><span>Current Office</span><strong>{{ $document->currentOffice?->name ?? 'N/A' }}</strong></div>
                <div><span>Priority</span><strong>{{ ucfirst($document->priority) }}</strong></div>
                <div><span>Budget Status</span><strong>{{ $document->budget_status ? str($document->budget_status)->replace('_', ' ')->title() : 'Not started' }}</strong></div>
            </div>
        </article>

        <aside class="table-panel budget-action-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Budget Actions</p>
                    <h2>Review Controls</h2>
                </div>
            </div>

            @if ($document->status === 'pending_budget_review')
                <form method="POST" action="{{ route('budget.pending-review.start', $document) }}" onsubmit="return confirm('Start budget review for this document?');" class="budget-action-form">
                    @csrf
                    @method('PATCH')
                    <button type="submit">Start Review</button>
                </form>
            @endif

            @if (in_array($document->status, ['pending_budget_review', 'under_budget_review'], true))
                <form method="POST" action="{{ route('budget.pending-review.return', $document) }}" onsubmit="return confirm('Return this document to the requesting office?');" class="budget-action-form">
                    @csrf
                    @method('PATCH')
                    <label for="comments">Return Reason</label>
                    <textarea id="comments" name="comments" rows="4" required placeholder="Explain missing or invalid budget requirements.">{{ old('comments') }}</textarea>
                    @error('comments')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                    <button type="submit" class="danger-action">Return Document</button>
                </form>
            @endif

            @if ($document->status === 'under_budget_review')
                <form method="POST" action="{{ route('budget.pending-review.mark-available', $document) }}" onsubmit="return confirm('Mark budget as available and forward this document to Accounting?');" class="budget-action-form">
                    @csrf
                    @method('PATCH')

                    <label for="fund_source">Fund Source</label>
                    <input id="fund_source" name="fund_source" type="text" value="{{ old('fund_source') }}" required>
                    @error('fund_source')
                        <span class="field-error">{{ $message }}</span>
                    @enderror

                    <label for="available_amount">Available Amount</label>
                    <input id="available_amount" name="available_amount" type="number" step="0.01" min="{{ $document->total_amount }}" value="{{ old('available_amount', $document->total_amount) }}" required>
                    @error('available_amount')
                        <span class="field-error">{{ $message }}</span>
                    @enderror

                    <label for="appropriation_code">Appropriation Code</label>
                    <input id="appropriation_code" name="appropriation_code" type="text" value="{{ old('appropriation_code') }}">

                    <label for="responsibility_center">Responsibility Center</label>
                    <input id="responsibility_center" name="responsibility_center" type="text" value="{{ old('responsibility_center') }}">

                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="3">{{ old('remarks') }}</textarea>

                    <button type="submit">Mark Budget Available / Endorse</button>
                </form>
            @endif

            @if (!in_array($document->status, ['pending_budget_review', 'under_budget_review'], true))
                <div class="empty-state">
                    <strong>No budget action available</strong>
                    <p>This document has already moved out of the pending budget review queue.</p>
                </div>
            @endif
        </aside>
    </section>

    <section class="dashboard-widget-grid">
        <article class="dashboard-widget">
            <div class="widget-heading">
                <div>
                    <p class="eyebrow">Budget Review</p>
                    <h2>Latest Review Record</h2>
                </div>
            </div>
            @if ($latestBudgetReview)
                <div class="budget-review-record">
                    <p><strong>Status:</strong> {{ str($latestBudgetReview->review_status)->replace('_', ' ')->title() }}</p>
                    <p><strong>Reviewed By:</strong> {{ $latestBudgetReview->reviewedBy?->name ?? 'N/A' }}</p>
                    <p><strong>Requested:</strong> PHP {{ number_format((float) $latestBudgetReview->requested_amount, 2) }}</p>
                    <p><strong>Available:</strong> {{ $latestBudgetReview->available_amount !== null ? 'PHP ' . number_format((float) $latestBudgetReview->available_amount, 2) : 'N/A' }}</p>
                    <p><strong>Fund Source:</strong> {{ $latestBudgetReview->fund_source ?? 'N/A' }}</p>
                    <p><strong>Remarks:</strong> {{ $latestBudgetReview->remarks ?? 'N/A' }}</p>
                </div>
            @else
                <div class="empty-state"><strong>No review record yet</strong><p>Start review to create the first budget review record.</p></div>
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
                        <p>{{ $history->fromOffice?->name ?? 'N/A' }} to {{ $history->toOffice?->name ?? 'N/A' }} · {{ str($history->status_from ?? 'new')->replace('_', ' ')->title() }} to {{ str($history->status_to)->replace('_', ' ')->title() }}</p>
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
    </section>
@endsection
