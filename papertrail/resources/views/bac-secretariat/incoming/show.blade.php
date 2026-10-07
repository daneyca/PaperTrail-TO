@extends('layouts.dashboard')

@section('title', $document->tracking_number . ' | BAC Secretariat')

@section('content')
    @php
        $isPpmpIncoming = str($document->document_type)->upper()->toString() === 'PPMP';
        $aiDocumentType = $isPpmpIncoming ? 'ppmp' : 'purchase_request';
        $aiDocumentLabel = $isPpmpIncoming ? 'AI Check PPMP' : 'AI Check PR';
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">BAC Secretariat Incoming Detail</p>
            <h1>{{ $document->tracking_number }}</h1>
            <p>{{ $document->title }}</p>
        </div>

        <div class="hero-actions no-print">
            <a href="{{ route('bac-secretariat.incoming.index') }}" class="dashboard-action secondary-action">Back to Incoming</a>
            <x-ai.completeness-check-button
                :document-type="$aiDocumentType"
                :document-id="$document->id"
                :tracking-number="$document->tracking_number"
                :label="$aiDocumentLabel"
            />
        </div>
    </section>

    @if ($isPpmpIncoming)
        <section class="document-review-shell">
            <div class="document-review-main">
                <article class="table-panel bac-incoming-document-preview">
                    <div class="panel-heading no-print">
                        <div>
                            <p class="eyebrow">Official PPMP Preview</p>
                            <h2>{{ $document->tracking_number }}</h2>
                            <p>Read-only view of the submitted Project Procurement Management Plan.</p>
                        </div>
                        <div class="table-actions">
                            @if (Route::has('bac-secretariat.ppmp.show'))
                                <a href="{{ route('bac-secretariat.ppmp.show', $document) }}">View Full PPMP</a>
                            @endif
                            @if (Route::has('bac-secretariat.ppmp.print'))
                                <a href="{{ route('bac-secretariat.ppmp.print', $document) }}" target="_blank" rel="noopener">Print PPMP</a>
                            @endif
                        </div>
                    </div>

                    <div class="document-preview-scroll" aria-label="Official PPMP document preview">
                        @include('head-office.ppmp._official-form', [
                            'mode' => 'show',
                            'document' => $document,
                            'items' => $document->ppmpItems,
                        ])
                    </div>
                </article>

                <article class="dashboard-widget widget-wide routing-history-panel no-print">
                    <div class="widget-heading"><div><p class="eyebrow">Routing History</p><h2>Document Movement</h2></div></div>
                    <ol class="routing-timeline">
                        @forelse ($document->routingHistories as $history)
                            <li>
                                <strong>{{ $history->action }}</strong>
                                <span>{{ $history->action_at?->format('M d, Y h:i A') }} by {{ $history->actionBy?->name ?? 'System' }}</span>
                                <p>{{ $history->fromOffice?->name ?? 'N/A' }} to {{ $history->toOffice?->name ?? 'N/A' }} &middot; {{ str($history->status_from ?? 'new')->replace('_', ' ')->title() }} to {{ str($history->status_to)->replace('_', ' ')->title() }}</p>
                                @if ($history->comments)<p>{{ $history->comments }}</p>@endif
                            </li>
                        @empty
                            <li><strong>No routing history yet</strong><span>Workflow actions will appear here.</span></li>
                        @endforelse
                    </ol>
                </article>
            </div>

            <aside class="document-review-sidebar no-print">
                <article class="table-panel budget-action-panel">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Receiving Controls</p>
                            <h2>PPMP Review</h2>
                        </div>
                    </div>

                    <div class="budget-review-record">
                        <p><strong>Current Status:</strong> {{ str($document->status)->replace('_', ' ')->title() }}</p>
                        <p><strong>Current Stage:</strong> {{ $document->stage ?? 'N/A' }}</p>
                        <p><strong>Submitted By:</strong> {{ $document->submittedBy?->name ?? 'N/A' }}</p>
                        <p><strong>Submitted Date:</strong> {{ $document->submitted_at?->format('M d, Y h:i A') ?? 'N/A' }}</p>
                        <p><strong>Total Amount:</strong> PHP {{ number_format((float) $document->total_amount, 2) }}</p>
                        <p><strong>Latest Remarks:</strong> {{ $document->ppmp_remarks ?? $document->remarks ?? 'None' }}</p>
                    </div>

                    @if ($document->status === \App\Models\ProcurementDocument::STATUS_PENDING_PPMP_REVIEW && Route::has('bac-secretariat.ppmp.start'))
                        <form method="POST" action="{{ route('bac-secretariat.ppmp.start', $document) }}" onsubmit="return confirm('Start BAC Secretariat PPMP review for this document?');" class="budget-action-form">
                            @csrf
                            @method('PATCH')
                            <button type="submit">Start PPMP Review</button>
                        </form>
                    @endif

                    @if (in_array($document->status, [\App\Models\ProcurementDocument::STATUS_PENDING_PPMP_REVIEW, \App\Models\ProcurementDocument::STATUS_UNDER_PPMP_REVIEW], true))
                        @if (Route::has('bac-secretariat.ppmp.return'))
                            <form method="POST" action="{{ route('bac-secretariat.ppmp.return', $document) }}" onsubmit="return confirm('Return this PPMP to the submitting office?');" class="budget-action-form">
                                @csrf
                                @method('PATCH')

                                <label for="ppmp-return-comments">Return Remarks</label>
                                <textarea id="ppmp-return-comments" name="comments" rows="4" required placeholder="State the correction or clarification needed.">{{ old('comments') }}</textarea>
                                @error('comments')<span class="field-error">{{ $message }}</span>@enderror

                                <button type="submit" class="danger-action">Return PPMP</button>
                            </form>
                        @endif

                        @if (Route::has('bac-secretariat.incoming.accept-ppmp'))
                            <form method="POST" action="{{ route('bac-secretariat.incoming.accept-ppmp', $document) }}" onsubmit="return confirm('Accept this PPMP for APP consolidation?');" class="budget-action-form">
                                @csrf
                                @method('PATCH')

                                <label for="ppmp-accept-remarks">Acceptance Remarks</label>
                                <textarea id="ppmp-accept-remarks" name="acceptance_remarks" rows="3" placeholder="Optional remarks for APP consolidation.">{{ old('acceptance_remarks') }}</textarea>
                                @error('acceptance_remarks')<span class="field-error">{{ $message }}</span>@enderror

                                <button type="submit">Accept for APP Consolidation</button>
                            </form>
                        @endif
                    @endif

                    @if (! in_array($document->status, [\App\Models\ProcurementDocument::STATUS_PENDING_PPMP_REVIEW, \App\Models\ProcurementDocument::STATUS_UNDER_PPMP_REVIEW], true))
                        <div class="empty-state">
                            <strong>No incoming action available</strong>
                            <p>This document is no longer in the active incoming review stage.</p>
                        </div>
                    @endif
                </article>

                <x-documents.attachments-panel
                    :document="$document"
                    title="PPMP Supporting Documents"
                />
            </aside>
        </section>
    @else
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
                    <p class="eyebrow">BAC Secretariat Actions</p>
                    <h2>Receiving Controls</h2>
                </div>
            </div>

            @if ($document->status === 'pending_bac_secretariat_review')
                <form method="POST" action="{{ route('bac-secretariat.incoming.acknowledge', $document) }}" onsubmit="return confirm('Acknowledge receipt of this document?');" class="budget-action-form">
                    @csrf
                    @method('PATCH')
                    <button type="submit">Acknowledge Receipt</button>
                </form>
            @endif

            @if ($document->status === 'received_by_bac_secretariat')
                <form method="POST" action="{{ route('bac-secretariat.incoming.start-review', $document) }}" onsubmit="return confirm('Start BAC Secretariat review for this document?');" class="budget-action-form">
                    @csrf
                    @method('PATCH')
                    <button type="submit">Start Review</button>
                </form>
            @endif

            @if (in_array($document->status, ['pending_bac_secretariat_review', 'received_by_bac_secretariat', 'under_bac_secretariat_review'], true))
                <form method="POST" action="{{ route('bac-secretariat.incoming.return', $document) }}" onsubmit="return confirm('Return this document from BAC Secretariat?');" class="budget-action-form">
                    @csrf
                    @method('PATCH')

                    <label for="return_target">Return Target</label>
                    <select id="return_target" name="return_target" required>
                        <option value="accounting" @selected(old('return_target', 'accounting') === 'accounting')>Return to Accounting Office</option>
                        <option value="requesting_office" @selected(old('return_target') === 'requesting_office')>Return to Requesting Office</option>
                    </select>
                    @error('return_target')<span class="field-error">{{ $message }}</span>@enderror

                    <label for="comments">Return Reason</label>
                    <textarea id="comments" name="comments" rows="4" required placeholder="Explain missing information, compliance concerns, or routing issues.">{{ old('comments') }}</textarea>
                    @error('comments')<span class="field-error">{{ $message }}</span>@enderror

                    <button type="submit" class="danger-action">Return Document</button>
                </form>
            @endif

            @if ($document->status === 'under_bac_secretariat_review')
                <form method="POST" action="{{ route('bac-secretariat.incoming.mark-ready', $document) }}" onsubmit="return confirm('Mark this document ready for routing?');" class="budget-action-form">
                    @csrf
                    @method('PATCH')
                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="3">{{ old('remarks', $document->bac_secretariat_remarks) }}</textarea>
                    <button type="submit">Mark Ready for Routing</button>
                </form>
            @endif

            @if (!in_array($document->status, ['pending_bac_secretariat_review', 'received_by_bac_secretariat', 'under_bac_secretariat_review'], true))
                <div class="empty-state">
                    <strong>No incoming action available</strong>
                    <p>This document is no longer in the active incoming review stage.</p>
                </div>
            @endif
        </aside>
    </section>

    <section class="dashboard-widget-grid">
        <article class="dashboard-widget">
            <div class="widget-heading"><div><p class="eyebrow">Budget Review Summary</p><h2>Budget Details</h2></div></div>
            @if ($latestBudgetReview)
                <div class="budget-review-record">
                    <p><strong>Budget Status:</strong> {{ str($document->budget_status ?? $latestBudgetReview->review_status)->replace('_', ' ')->title() }}</p>
                    <p><strong>Reviewed By:</strong> {{ $latestBudgetReview->reviewedBy?->name ?? $document->budgetReviewedBy?->name ?? 'N/A' }}</p>
                    <p><strong>Fund Source:</strong> {{ $latestBudgetReview->fund_source ?? 'N/A' }}</p>
                    <p><strong>Appropriation Code:</strong> {{ $latestBudgetReview->appropriation_code ?? 'N/A' }}</p>
                    <p><strong>Available Amount:</strong> {{ $latestBudgetReview->available_amount !== null ? 'PHP ' . number_format((float) $latestBudgetReview->available_amount, 2) : 'N/A' }}</p>
                    <p><strong>Budget Remarks:</strong> {{ $document->budget_remarks ?? $latestBudgetReview->remarks ?? 'N/A' }}</p>
                    <p><strong>Reviewed Date:</strong> {{ $latestBudgetReview->completed_at?->format('M d, Y h:i A') ?? $document->budget_reviewed_at?->format('M d, Y h:i A') ?? 'N/A' }}</p>
                </div>
            @else
                <div class="empty-state"><strong>No budget review record</strong><p>Budget details will appear once available.</p></div>
            @endif
        </article>

        <article class="dashboard-widget">
            <div class="widget-heading"><div><p class="eyebrow">Accounting Review Summary</p><h2>Accounting Details</h2></div></div>
            @if ($latestAccountingReview)
                <div class="budget-review-record">
                    <p><strong>Accounting Status:</strong> {{ str($document->accounting_status ?? $latestAccountingReview->review_status)->replace('_', ' ')->title() }}</p>
                    <p><strong>Reviewed By:</strong> {{ $latestAccountingReview->reviewedBy?->name ?? $document->accountingReviewedBy?->name ?? 'N/A' }}</p>
                    <p><strong>Reference No.:</strong> {{ $document->accounting_reference_no ?? $latestAccountingReview->accounting_reference_no ?? 'N/A' }}</p>
                    <p><strong>Account Code:</strong> {{ $document->account_code ?? $latestAccountingReview->account_code ?? 'N/A' }}</p>
                    <p><strong>Object Code:</strong> {{ $document->object_code ?? $latestAccountingReview->object_code ?? 'N/A' }}</p>
                    <p><strong>Responsibility Center:</strong> {{ $document->responsibility_center ?? $latestAccountingReview->responsibility_center ?? 'N/A' }}</p>
                    <p><strong>Accounting Remarks:</strong> {{ $document->accounting_remarks ?? $latestAccountingReview->remarks ?? 'N/A' }}</p>
                    <p><strong>Reviewed Date:</strong> {{ $latestAccountingReview->completed_at?->format('M d, Y h:i A') ?? $document->accounting_reviewed_at?->format('M d, Y h:i A') ?? 'N/A' }}</p>
                </div>
            @else
                <div class="empty-state"><strong>No accounting review record</strong><p>Accounting details will appear once available.</p></div>
            @endif
        </article>

        <article class="dashboard-widget">
            <div class="widget-heading"><div><p class="eyebrow">BAC Secretariat</p><h2>Latest Processing Record</h2></div></div>
            @if ($latestBacReview)
                <div class="budget-review-record">
                    <p><strong>Status:</strong> {{ str($latestBacReview->review_status)->replace('_', ' ')->title() }}</p>
                    <p><strong>Processed By:</strong> {{ $latestBacReview->reviewedBy?->name ?? $document->bacSecretariatReceivedBy?->name ?? 'N/A' }}</p>
                    <p><strong>Started:</strong> {{ $latestBacReview->started_at?->format('M d, Y h:i A') ?? $document->bac_secretariat_review_started_at?->format('M d, Y h:i A') ?? 'N/A' }}</p>
                    <p><strong>Completed:</strong> {{ $latestBacReview->completed_at?->format('M d, Y h:i A') ?? $document->bac_secretariat_processed_at?->format('M d, Y h:i A') ?? 'N/A' }}</p>
                    <p><strong>Remarks:</strong> {{ $document->bac_secretariat_remarks ?? $latestBacReview->remarks ?? 'N/A' }}</p>
                </div>
            @else
                <div class="empty-state"><strong>No BAC Secretariat record yet</strong><p>Acknowledge receipt to create the first record.</p></div>
            @endif
        </article>

        <article class="dashboard-widget widget-wide">
            <div class="widget-heading"><div><p class="eyebrow">Routing History</p><h2>Document Movement</h2></div></div>
            <ol class="routing-timeline">
                @forelse ($document->routingHistories as $history)
                    <li>
                        <strong>{{ $history->action }}</strong>
                        <span>{{ $history->action_at?->format('M d, Y h:i A') }} by {{ $history->actionBy?->name ?? 'System' }}</span>
                        <p>{{ $history->fromOffice?->name ?? 'N/A' }} to {{ $history->toOffice?->name ?? 'N/A' }} &middot; {{ str($history->status_from ?? 'new')->replace('_', ' ')->title() }} to {{ str($history->status_to)->replace('_', ' ')->title() }}</p>
                        @if ($history->comments)<p>{{ $history->comments }}</p>@endif
                    </li>
                @empty
                    <li><strong>No routing history yet</strong><span>Workflow actions will appear here.</span></li>
                @endforelse
            </ol>
        </article>

        <x-documents.attachments-panel
            :document="$document"
            title="Document Files"
        />
        <article class="dashboard-widget"><div class="widget-heading"><div><p class="eyebrow">Items</p><h2>Document Items</h2></div></div><div class="empty-state"><strong>No item module yet</strong><p>Line items will appear after procurement item encoding is implemented.</p></div></article>
    </section>
    @endif
@endsection
