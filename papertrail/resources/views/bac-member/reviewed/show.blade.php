@extends('layouts.dashboard')

@section('title', $document->tracking_number . ' | BAC Member Reviewed Document')

@section('content')
    @php
        $label = fn ($value) => $value ? str($value)->replace('_', ' ')->title() : 'N/A';
        $money = fn ($amount) => 'PHP ' . number_format((float) $amount, 2);
        $isReturned = $document->status === \App\Models\ProcurementDocument::STATUS_RETURNED_BY_BAC_MEMBER;
        $reviewedAt = $document->bac_member_reviewed_at ?? $latestBacMemberReview?->completed_at;
        $aiDocumentType = strtoupper((string) $document->document_type) === 'PPMP' ? 'ppmp' : 'purchase_request';
        $aiDocumentLabel = strtoupper((string) $document->document_type) === 'PPMP' ? 'AI Check PPMP' : 'AI Check PR';
    @endphp

    <section class="dashboard-hero admin-users-hero pt-smooth-enter" style="--pt-delay: 0ms">
        <div>
            <p class="eyebrow">BAC Member Reviewed Document</p>
            <h1>{{ $document->tracking_number }}</h1>
            <p>{{ $document->title }}</p>
        </div>

        <div class="hero-actions no-print">
            <a href="{{ route('bac-member.reviewed.index') }}" class="dashboard-action secondary-action">Back to Reviewed Documents</a>
            <x-ai.completeness-check-button
                :document-type="$aiDocumentType"
                :document-id="$document->id"
                :tracking-number="$document->tracking_number"
                :label="$aiDocumentLabel"
            />
        </div>
    </section>

    <section class="budget-review-layout reviewed-detail-layout pt-smooth-enter" style="--pt-delay: 120ms">
        <article class="table-panel budget-detail-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Document Information</p>
                    <h2>{{ $document->document_type }}</h2>
                    <p>{{ $document->description ?? $document->purpose ?? 'No description provided.' }}</p>
                </div>
                <span class="status-pill status-{{ $document->status }}">{{ $label($document->status) }}</span>
            </div>

            <div class="detail-grid budget-detail-grid">
                <div><span>Tracking Number</span><strong>{{ $document->tracking_number }}</strong></div>
                <div><span>Document Type</span><strong>{{ $document->document_type }}</strong></div>
                <div><span>Fiscal Year</span><strong>{{ $document->fiscal_year }}</strong></div>
                <div><span>Requesting Office</span><strong>{{ $document->submittingOffice?->name ?? 'N/A' }}</strong></div>
                <div><span>Submitted By</span><strong>{{ $document->submittedBy?->name ?? 'N/A' }}</strong></div>
                <div><span>Submitted Date</span><strong>{{ $document->submitted_at?->format('M d, Y h:i A') ?? 'N/A' }}</strong></div>
                <div><span>Total Amount</span><strong>{{ $money($document->total_amount) }}</strong></div>
                <div><span>Current Status</span><strong>{{ $label($document->status) }}</strong></div>
                <div><span>Current Stage</span><strong>{{ $document->stage ?? 'N/A' }}</strong></div>
                <div><span>Current Office</span><strong>{{ $document->currentOffice?->name ?? 'N/A' }}</strong></div>
                <div><span>Priority</span><strong>{{ ucfirst($document->priority ?? 'normal') }}</strong></div>
            </div>
        </article>

        <aside class="table-panel budget-action-panel reviewed-summary-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">BAC Member Review Summary</p>
                    <h2>Read-only Record</h2>
                </div>
            </div>

            <div class="budget-review-record reviewed-record-list">
                <p><strong>Reviewed By:</strong> {{ $latestBacMemberReview?->reviewedBy?->name ?? $document->bacMemberReviewedBy?->name ?? 'N/A' }}</p>
                <p><strong>Review Started:</strong> {{ $latestBacMemberReview?->started_at?->format('M d, Y h:i A') ?? $document->bac_member_review_started_at?->format('M d, Y h:i A') ?? 'N/A' }}</p>
                <p><strong>Reviewed / Acted On:</strong> {{ $reviewedAt?->format('M d, Y h:i A') ?? 'N/A' }}</p>
                <p><strong>BAC Member Status:</strong> {{ $label($document->bac_member_status ?? $latestBacMemberReview?->review_status) }}</p>
                <p><strong>Outcome:</strong> {{ $outcome }}</p>
                <p><strong>Recommendation:</strong> {{ $label($latestBacMemberReview?->recommendation) }}</p>
                <p><strong>{{ $isReturned ? 'Returned To' : 'Endorsed To' }}:</strong> {{ $destination }}</p>
                <p><strong>{{ $isReturned ? 'Return Reason' : 'Remarks' }}:</strong> {{ $document->bac_member_remarks ?? $latestBacMemberReview?->remarks ?? 'N/A' }}</p>
            </div>
        </aside>
    </section>

    <section class="dashboard-widget-grid pt-smooth-enter" style="--pt-delay: 220ms">
        <article class="dashboard-widget">
            <div class="widget-heading"><div><p class="eyebrow">Budget Review</p><h2>Latest Budget Summary</h2></div></div>
            @if ($latestBudgetReview)
                <div class="budget-review-record">
                    <p><strong>Status:</strong> {{ $label($latestBudgetReview->review_status) }}</p>
                    <p><strong>Reviewed By:</strong> {{ $latestBudgetReview->reviewedBy?->name ?? 'N/A' }}</p>
                    <p><strong>Requested:</strong> {{ $money($latestBudgetReview->requested_amount) }}</p>
                    <p><strong>Available:</strong> {{ $latestBudgetReview->available_amount !== null ? $money($latestBudgetReview->available_amount) : 'N/A' }}</p>
                    <p><strong>Fund Source:</strong> {{ $latestBudgetReview->fund_source ?? 'N/A' }}</p>
                    <p><strong>Remarks:</strong> {{ $latestBudgetReview->remarks ?? 'N/A' }}</p>
                </div>
            @else
                <div class="empty-state"><strong>No budget review summary</strong><p>Budget review records will appear here when available.</p></div>
            @endif
        </article>

        <article class="dashboard-widget">
            <div class="widget-heading"><div><p class="eyebrow">Accounting Review</p><h2>Latest Accounting Summary</h2></div></div>
            @if ($latestAccountingReview)
                <div class="budget-review-record">
                    <p><strong>Status:</strong> {{ $label($latestAccountingReview->review_status) }}</p>
                    <p><strong>Reviewed By:</strong> {{ $latestAccountingReview->reviewedBy?->name ?? 'N/A' }}</p>
                    <p><strong>Reference No.:</strong> {{ $latestAccountingReview->accounting_reference_no ?? 'N/A' }}</p>
                    <p><strong>Account Code:</strong> {{ $latestAccountingReview->account_code ?? 'N/A' }}</p>
                    <p><strong>Responsibility Center:</strong> {{ $latestAccountingReview->responsibility_center ?? 'N/A' }}</p>
                    <p><strong>Remarks:</strong> {{ $latestAccountingReview->remarks ?? 'N/A' }}</p>
                </div>
            @else
                <div class="empty-state"><strong>No accounting review summary</strong><p>Accounting review records will appear here when available.</p></div>
            @endif
        </article>

        <article class="dashboard-widget">
            <div class="widget-heading"><div><p class="eyebrow">BAC Secretariat</p><h2>Processing Summary</h2></div></div>
            @if ($latestBacSecretariatReview || $document->bac_secretariat_status || $document->route_remarks)
                <div class="budget-review-record">
                    <p><strong>Status:</strong> {{ $label($document->bac_secretariat_status ?? $latestBacSecretariatReview?->review_status) }}</p>
                    <p><strong>Received By:</strong> {{ $document->bacSecretariatReceivedBy?->name ?? 'N/A' }}</p>
                    <p><strong>Received At:</strong> {{ $document->bac_secretariat_received_at?->format('M d, Y h:i A') ?? 'N/A' }}</p>
                    <p><strong>Route Remarks:</strong> {{ $document->route_remarks ?? 'N/A' }}</p>
                    <p><strong>Remarks:</strong> {{ $latestBacSecretariatReview?->remarks ?? $document->bac_secretariat_remarks ?? 'N/A' }}</p>
                </div>
            @else
                <div class="empty-state"><strong>No BAC Secretariat summary</strong><p>BAC Secretariat records will appear here when available.</p></div>
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
                        <p>{{ $history->fromOffice?->name ?? 'N/A' }} to {{ $history->toOffice?->name ?? 'N/A' }} - {{ $label($history->status_from ?? 'new') }} to {{ $label($history->status_to) }}</p>
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
            <div class="widget-heading"><div><p class="eyebrow">Items</p><h2>Document Items</h2></div></div>
            @if ($document->purchaseRequestItems->isNotEmpty())
                <div class="table-scroll">
                    <table class="user-management-table">
                        <thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>Unit Cost</th><th>Total</th></tr></thead>
                        <tbody>
                            @foreach ($document->purchaseRequestItems as $item)
                                <tr>
                                    <td>{{ $item->item_description }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>{{ $item->unit ?? 'N/A' }}</td>
                                    <td>{{ $money($item->estimated_unit_cost) }}</td>
                                    <td>{{ $money($item->estimated_total_cost) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state"><strong>No item records</strong><p>Items will appear here when they exist for this document.</p></div>
            @endif
        </article>

        <x-documents.attachments-panel
            :document="$document"
            :can-upload="false"
            :can-delete="false"
            title="Document Files"
        />
    </section>
@endsection
