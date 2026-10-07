@extends('layouts.dashboard')

@section('title', $document->tracking_number . ' | Pending Approval')

@section('content')
    @php
        $label = fn ($value) => $value ? str($value)->replace('_', ' ')->title() : 'N/A';
        $money = fn ($amount) => 'PHP ' . number_format((float) $amount, 2);
        $aiDocumentType = strtoupper((string) $document->document_type) === 'PPMP' ? 'ppmp' : 'purchase_request';
        $aiDocumentLabel = strtoupper((string) $document->document_type) === 'PPMP' ? 'AI Check PPMP' : 'AI Check PR';
    @endphp

    <section class="dashboard-hero admin-users-hero pt-smooth-enter" style="--pt-delay: 0ms">
        <div>
            <p class="eyebrow">Head of the Procuring Entity Detail</p>
            <h1>{{ $document->tracking_number }}</h1>
            <p>{{ $document->title }}</p>
        </div>
        <div class="hero-actions no-print">
            <a href="{{ route('approving-authority.pending.index') }}" class="dashboard-action secondary-action">Back to Pending Approval</a>
            <x-ai.completeness-check-button
                :document-type="$aiDocumentType"
                :document-id="$document->id"
                :tracking-number="$document->tracking_number"
                :label="$aiDocumentLabel"
            />
        </div>
    </section>

    <section class="budget-review-layout pt-smooth-enter" style="--pt-delay: 120ms">
        <article class="table-panel budget-detail-panel">
            <div class="panel-heading">
                <div><p class="eyebrow">Document Information</p><h2>{{ $document->document_type }}</h2><p>{{ $document->description ?? $document->purpose ?? 'No description provided.' }}</p></div>
                <span class="status-pill status-{{ $document->status }}">{{ $label($document->status) }}</span>
            </div>
            <div class="detail-grid budget-detail-grid">
                <div><span>Tracking Number</span><strong>{{ $document->tracking_number }}</strong></div>
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

        <aside class="table-panel budget-action-panel">
            <div class="panel-heading"><div><p class="eyebrow">Approval Panel</p><h2>Final Authorization</h2></div></div>

            @if ($document->status === 'pending_approval')
                <form method="POST" action="{{ route('approving-authority.pending.start', $document) }}" onsubmit="return confirm('Start final approval review for this document?');" class="budget-action-form">@csrf @method('PATCH')<button type="submit">Start Review</button></form>
            @endif

            @if ($document->status === 'under_approval')
                <form method="POST" action="{{ route('approving-authority.pending.approve', $document) }}" onsubmit="return confirm('Approve this document?');" class="budget-action-form">
                    @csrf @method('PATCH')
                    <label class="checkbox-line"><input type="checkbox" name="approval_confirmation" value="1" required> Confirm final approval of this procurement document</label>
                    @error('approval_confirmation')<span class="field-error">{{ $message }}</span>@enderror
                    <label for="remarks">Approval Remarks</label>
                    <textarea id="remarks" name="remarks" rows="3" placeholder="Optional approval remarks.">{{ old('remarks') }}</textarea>
                    @error('remarks')<span class="field-error">{{ $message }}</span>@enderror
                    <button type="submit">Approve Document</button>
                </form>
            @endif

            @if (in_array($document->status, ['pending_approval', 'under_approval'], true))
                <form method="POST" action="{{ route('approving-authority.pending.return', $document) }}" onsubmit="return confirm('Return this document for correction?');" class="budget-action-form">
                    @csrf @method('PATCH')
                    <label for="return-target">Return Target</label>
                    <select id="return-target" name="return_target" required>@foreach ($returnTargets as $value => $text)<option value="{{ $value }}" @selected(old('return_target', 'bac_chair') === $value)>{{ $text }}</option>@endforeach</select>
                    @error('return_target')<span class="field-error">{{ $message }}</span>@enderror
                    <label for="comments">Return Reason / Comment</label>
                    <textarea id="comments" name="comments" rows="4" required placeholder="Explain what needs correction or clarification.">{{ old('comments') }}</textarea>
                    @error('comments')<span class="field-error">{{ $message }}</span>@enderror
                    <button type="submit" class="danger-action">Return Document</button>
                </form>
            @endif
        </aside>
    </section>

    <section class="dashboard-widget-grid pt-smooth-enter" style="--pt-delay: 220ms">
        @foreach ([
            ['Budget Review', 'Latest Budget Summary', $latestBudgetReview, ['Status' => $label($latestBudgetReview?->review_status), 'Reviewed By' => $latestBudgetReview?->reviewedBy?->name ?? 'N/A', 'Remarks' => $latestBudgetReview?->remarks ?? 'N/A']],
            ['Accounting Review', 'Latest Accounting Summary', $latestAccountingReview, ['Status' => $label($latestAccountingReview?->review_status), 'Reviewed By' => $latestAccountingReview?->reviewedBy?->name ?? 'N/A', 'Remarks' => $latestAccountingReview?->remarks ?? 'N/A']],
            ['BAC Secretariat', 'Processing Summary', $latestBacSecretariatReview, ['Status' => $label($document->bac_secretariat_status ?? $latestBacSecretariatReview?->review_status), 'Remarks' => $latestBacSecretariatReview?->remarks ?? $document->bac_secretariat_remarks ?? 'N/A']],
            ['BAC Member', 'Review Summary', $latestBacMemberReview, ['Status' => $label($latestBacMemberReview?->review_status), 'Recommendation' => $label($latestBacMemberReview?->recommendation), 'Remarks' => $latestBacMemberReview?->remarks ?? 'N/A']],
            ['BAC Chair', 'Review Summary', $latestBacChairReview, ['Status' => $label($document->bac_chair_status ?? $latestBacChairReview?->review_status), 'Decision' => $label($document->bac_chair_decision ?? $latestBacChairReview?->decision), 'Confirmed At' => $document->bac_chair_confirmed_at?->format('M d, Y h:i A') ?? $document->bac_chair_reviewed_at?->format('M d, Y h:i A') ?? 'N/A', 'Remarks' => $document->bac_chair_confirmation_remarks ?? $document->bac_chair_remarks ?? 'N/A']],
        ] as [$eyebrow, $title, $record, $rows])
            <article class="dashboard-widget">
                <div class="widget-heading"><div><p class="eyebrow">{{ $eyebrow }}</p><h2>{{ $title }}</h2></div></div>
                @if ($record || $eyebrow === 'BAC Chair')
                    <div class="budget-review-record">@foreach ($rows as $name => $value)<p><strong>{{ $name }}:</strong> {{ $value }}</p>@endforeach</div>
                @else
                    <div class="empty-state"><strong>No {{ strtolower($eyebrow) }} summary</strong><p>Records will appear here when available.</p></div>
                @endif
            </article>
        @endforeach

        <article class="dashboard-widget widget-wide">
            <div class="widget-heading"><div><p class="eyebrow">BAC Deliberation</p><h2>Deliberation Summary</h2></div></div>
            @if ($latestDeliberation)
                <div class="detail-grid budget-detail-grid"><div><span>Deliberation Number</span><strong>{{ $latestDeliberation->deliberation_number ?? 'N/A' }}</strong></div><div><span>Status</span><strong>{{ $label($latestDeliberation->status) }}</strong></div><div><span>Participants</span><strong>{{ $latestDeliberation->participants->count() }}</strong></div><div><span>Completed</span><strong>{{ $latestDeliberation->completed_at?->format('M d, Y h:i A') ?? 'N/A' }}</strong></div></div>
            @else
                <div class="empty-state"><strong>No deliberation record linked to this document.</strong><p>BAC deliberation summaries will appear when available.</p></div>
            @endif
        </article>

        <article class="dashboard-widget widget-wide">
            <div class="widget-heading"><div><p class="eyebrow">Routing History</p><h2>Document Movement</h2></div></div>
            <ol class="routing-timeline">@forelse ($document->routingHistories as $history)<li><strong>{{ $history->action }}</strong><span>{{ $history->action_at?->format('M d, Y h:i A') }} by {{ $history->actionBy?->name ?? 'System' }}</span><p>{{ $history->fromOffice?->name ?? 'N/A' }} to {{ $history->toOffice?->name ?? 'N/A' }} - {{ $label($history->status_from ?? 'new') }} to {{ $label($history->status_to) }}</p>@if ($history->comments)<p>{{ $history->comments }}</p>@endif</li>@empty<li><strong>No routing history yet</strong><span>Approval actions will appear here.</span></li>@endforelse</ol>
        </article>

        <article class="dashboard-widget">
            <div class="widget-heading"><div><p class="eyebrow">Items</p><h2>Document Items</h2></div></div>
            @if ($document->purchaseRequestItems->isNotEmpty())
                <div class="table-scroll"><table class="user-management-table"><thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead><tbody>@foreach ($document->purchaseRequestItems as $item)<tr><td>{{ $item->item_description }}</td><td>{{ $item->quantity }}</td><td>{{ $item->unit ?? 'N/A' }}</td><td>{{ $money($item->estimated_total_cost) }}</td></tr>@endforeach</tbody></table></div>
            @else
                <div class="empty-state"><strong>No item records</strong><p>Items will appear here when they exist for this document.</p></div>
            @endif
        </article>
        <x-documents.attachments-panel
            :document="$document"
            :can-upload="false"
            :can-delete="false"
            title="Supporting Files"
        />
    </section>
@endsection
