@extends('layouts.dashboard')

@section('title', $document->tracking_number . ' | Document Routing')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">BAC Secretariat Document Routing</p>
            <h1>{{ $document->tracking_number }}</h1>
            <p>{{ $document->title }}</p>
        </div>

        <a href="{{ route('bac-secretariat.routing.index') }}" class="dashboard-action secondary-action">Back to Routing</a>
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
                <div><span>Tracking Number</span><strong>{{ $document->document_reference_number ?? 'Pending' }}</strong></div>
                @if (in_array($document->document_type, ['PR', 'Purchase Request'], true))
                    <div><span>Official PR Number</span><strong>{{ $document->pr_no ?? 'Not assigned' }}</strong></div>
                @endif
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
                <div><span>Last Routed To</span><strong>{{ $document->route_destination_role ?? 'Not routed yet' }}</strong></div>
                <div><span>Routed Date</span><strong>{{ $document->routed_at?->format('M d, Y h:i A') ?? 'N/A' }}</strong></div>
            </div>
        </article>

        <aside class="table-panel budget-action-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Routing Panel</p>
                    <h2>Next Action</h2>
                </div>
            </div>

            @if ($document->status === 'ready_for_document_routing')
                <form method="POST" action="{{ route('bac-secretariat.routing.route', $document) }}" onsubmit="return confirm('Route this document to the selected destination?');" class="budget-action-form">
                    @csrf
                    @method('PATCH')

                    <label for="destination_role">Destination Role / Route To</label>
                    <select id="destination_role" name="destination_role" required>
                        <option value="">Select destination</option>
                        @foreach ($destinationRoles as $value => $label)
                            <option value="{{ $value }}" @selected(old('destination_role') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('destination_role')<span class="field-error">{{ $message }}</span>@enderror

                    <label for="destination_office_id">Destination Office</label>
                    <select id="destination_office_id" name="destination_office_id">
                        <option value="">Required only for Additional Review</option>
                        @foreach ($offices as $office)
                            <option value="{{ $office->id }}" @selected((string) old('destination_office_id') === (string) $office->id)>{{ $office->name }}</option>
                        @endforeach
                    </select>
                    @error('destination_office_id')<span class="field-error">{{ $message }}</span>@enderror

                    <label for="assigned_to_user_id">Assigned User</label>
                    <select id="assigned_to_user_id" name="assigned_to_user_id">
                        <option value="">Use configured role user</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((string) old('assigned_to_user_id') === (string) $user->id)>{{ $user->name }} - {{ $user->role }}</option>
                        @endforeach
                    </select>
                    @error('assigned_to_user_id')<span class="field-error">{{ $message }}</span>@enderror

                    <label for="priority">Priority</label>
                    <select id="priority" name="priority">
                        @foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('priority', $document->priority) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <label for="route_remarks">Routing Remarks</label>
                    <textarea id="route_remarks" name="route_remarks" rows="3" placeholder="Optional routing context or note.">{{ old('route_remarks') }}</textarea>
                    @error('route_remarks')<span class="field-error">{{ $message }}</span>@enderror

                    <label for="expected_action">Expected Action / Instruction</label>
                    <textarea id="expected_action" name="expected_action" rows="3" placeholder="Optional instruction for the next reviewer.">{{ old('expected_action') }}</textarea>
                    @error('expected_action')<span class="field-error">{{ $message }}</span>@enderror

                    <button type="submit">Route Document</button>
                </form>
            @else
                <div class="empty-state">
                    <strong>Routing already recorded</strong>
                    <p>This document is no longer marked ready for first routing. It remains visible here for monitoring.</p>
                </div>
            @endif

            <form method="POST" action="{{ route('bac-secretariat.routing.return', $document) }}" onsubmit="return confirm('Return this document from routing?');" class="budget-action-form routing-return-form">
                @csrf
                @method('PATCH')

                <label for="return_target">Return Target</label>
                <select id="return_target" name="return_target" required>
                    <option value="accounting" @selected(old('return_target', 'accounting') === 'accounting')>Return to Accounting Office</option>
                    <option value="requesting_office" @selected(old('return_target') === 'requesting_office')>Return to Requesting Office</option>
                </select>
                @error('return_target')<span class="field-error">{{ $message }}</span>@enderror

                <label for="comments">Return Reason</label>
                <textarea id="comments" name="comments" rows="3" required placeholder="Explain why this document cannot be routed.">{{ old('comments') }}</textarea>
                @error('comments')<span class="field-error">{{ $message }}</span>@enderror

                <button type="submit" class="danger-action">Return Document</button>
            </form>
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
            <div class="widget-heading"><div><p class="eyebrow">BAC Secretariat Summary</p><h2>Processing Record</h2></div></div>
            <div class="budget-review-record">
                <p><strong>Received By:</strong> {{ $document->bacSecretariatReceivedBy?->name ?? 'N/A' }}</p>
                <p><strong>Received At:</strong> {{ $document->bac_secretariat_received_at?->format('M d, Y h:i A') ?? 'N/A' }}</p>
                <p><strong>Review Started:</strong> {{ $document->bac_secretariat_review_started_at?->format('M d, Y h:i A') ?? 'N/A' }}</p>
                <p><strong>Processed At:</strong> {{ $document->bac_secretariat_processed_at?->format('M d, Y h:i A') ?? 'N/A' }}</p>
                <p><strong>BAC Status:</strong> {{ $document->bac_secretariat_status ? str($document->bac_secretariat_status)->replace('_', ' ')->title() : 'N/A' }}</p>
                <p><strong>Remarks:</strong> {{ $document->bac_secretariat_remarks ?? $latestBacReview?->remarks ?? 'N/A' }}</p>
            </div>
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
@endsection
