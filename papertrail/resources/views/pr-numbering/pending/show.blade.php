@extends('layouts.dashboard')

@section('title', ($document->tracking_number ?? 'Pending PR Number') . ' | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">PR Number Assignment</p>
            <h1>{{ $document->tracking_number ?? 'Pending PR Number' }}</h1>
            <p>{{ $document->title ?? $document->purpose ?? 'Review this Purchase Request and assign its official LGU PR number.' }}</p>
        </div>

        <div class="hero-actions no-print">
            <a href="{{ route('pr-numbering.pending.index') }}" class="dashboard-action secondary-action">Back to Pending Requests</a>
            <x-ai.completeness-check-button
                document-type="purchase_request"
                :document-id="$document->id"
                :tracking-number="$document->pr_no ?? $document->tracking_number"
                label="AI Check PR"
            />
        </div>
    </section>

    <section class="budget-review-layout pr-numbering-workspace">
        <article class="table-panel budget-detail-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Purchase Request Details</p>
                    <h2>{{ $document->title ?? $document->purpose }}</h2>
                    <p>Official PR No. is not yet assigned.</p>
                </div>
                <span class="status-pill status-{{ $document->status }}">Pending Assignment</span>
            </div>

            <div class="detail-grid budget-detail-grid">
                <div><span>Tracking Number</span><strong>{{ $document->tracking_number ?? 'N/A' }}</strong></div>
                <div><span>Fiscal Year</span><strong>{{ $document->fiscal_year }}</strong></div>
                <div><span>Requesting Office</span><strong>{{ $document->submittingOffice?->name ?? 'N/A' }}</strong></div>
                <div><span>Submitted By</span><strong>{{ $document->submittedBy?->name ?? 'N/A' }}</strong></div>
                <div><span>Requested At</span><strong>{{ $document->pr_no_requested_at?->format('M d, Y h:i A') ?? 'N/A' }}</strong></div>
                <div><span>Total Amount</span><strong>PHP {{ number_format((float) $document->total_amount, 2) }}</strong></div>
                <div><span>Current Stage</span><strong>{{ $document->stage ?? 'N/A' }}</strong></div>
                <div><span>Current Office</span><strong>{{ $document->currentOffice?->name ?? 'N/A' }}</strong></div>
            </div>
        </article>

        <aside class="table-panel budget-action-panel pr-numbering-action-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">PR Number Controls</p>
                    <h2>Assign Official PR No.</h2>
                </div>
            </div>

            <div class="pr-numbering-action-grid">
                <form method="POST" action="{{ route('pr-numbering.pending.assign', $document) }}" class="budget-action-form pr-numbering-action-card" onsubmit="return confirm('Assign this official PR number and return the PR to the requesting office?');">
                    @csrf
                    @method('PATCH')

                    <div class="pr-numbering-card-heading">
                        <p class="eyebrow">Assign Number</p>
                        <h3>Official PR Details</h3>
                    </div>

                    <div class="pr-numbering-field-grid">
                        <div class="pr-numbering-field">
                            <label for="pr_no">Official PR No.</label>
                            <input id="pr_no" name="pr_no" type="text" value="{{ old('pr_no', $document->pr_no ?? $suggestedPrNo) }}" required>
                            @error('pr_no')<span class="field-error">{{ $message }}</span>@enderror
                        </div>

                        <div class="pr-numbering-field">
                            <label for="pr_date">Official PR Date</label>
                            <input id="pr_date" name="pr_date" type="date" value="{{ old('pr_date', $document->pr_date?->format('Y-m-d') ?? '') }}" required>
                            @error('pr_date')<span class="field-error">{{ $message }}</span>@enderror
                        </div>

                        <div class="pr-numbering-field is-wide">
                            <label for="assign-remarks">Remarks</label>
                            <textarea id="assign-remarks" name="remarks" rows="3">{{ old('remarks') }}</textarea>
                            @error('remarks')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <button type="submit" class="dashboard-action">Assign PR Number</button>
                </form>

                <form method="POST" action="{{ route('pr-numbering.pending.return', $document) }}" class="budget-action-form pr-numbering-action-card pr-numbering-return-card" onsubmit="return confirm('Return this Purchase Request to the requesting office?');">
                    @csrf
                    @method('PATCH')

                    <div class="pr-numbering-card-heading">
                        <p class="eyebrow">Return PR</p>
                        <h3>Return to Requesting Office</h3>
                    </div>

                    <div class="pr-numbering-field">
                        <label for="return-remarks">Return Reason</label>
                        <textarea id="return-remarks" name="remarks" rows="4" required>{{ old('remarks') }}</textarea>
                        @error('remarks')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <button type="submit" class="dashboard-action danger-action">Return PR</button>
                </form>
            </div>
        </aside>
    </section>

    <section class="returned-pr-document-preview" aria-label="Purchase Request preview">
        <div class="pr-preview-area">
            @include('head-office.pr._preview', ['sheetClass' => 'pr-readonly-sheet pr-numbering-pr-sheet', 'mode' => 'show'])
        </div>
    </section>

    <section class="dashboard-widget-grid">
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
                    <li><strong>No routing history yet</strong><span>PR number assignment actions will appear here.</span></li>
                @endforelse
            </ol>
        </article>
    </section>
@endsection
