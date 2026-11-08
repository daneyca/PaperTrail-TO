@extends('layouts.dashboard')

@section('title', 'RFQ Procurement Control | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero no-print">
        <div>
            <p class="eyebrow">Procurement Control</p>
            <h1>Assign RFQ Number and Date</h1>
            <p>{{ $rfq->document_reference_number }} &middot; {{ $rfq->sourcePrDocument?->submittingOffice?->name ?? 'Requesting Office' }}</p>
        </div>
        <div class="hero-actions"><a href="{{ route('pr-numbering.pending.index') }}" class="dashboard-action secondary-action">Back to Control Queue</a></div>
    </section>

    <section class="budget-review-layout pr-numbering-workspace no-print">
        <article class="table-panel budget-detail-panel">
            <div class="panel-heading"><div><p class="eyebrow">RFQ Details</p><h2>{{ $rfq->purpose ?: 'Request for Quotation' }}</h2></div></div>
            <div class="detail-grid budget-detail-grid">
                <div><span>Tracking Number</span><strong>{{ $rfq->document_reference_number }}</strong></div>
                <div><span>Requesting Office</span><strong>{{ $rfq->sourcePrDocument?->submittingOffice?->name ?? 'N/A' }}</strong></div>
                <div><span>Prepared By</span><strong>{{ $rfq->preparedBy?->name ?? 'N/A' }}</strong></div>
                <div><span>Submitted Date</span><strong>{{ $rfq->submitted_at?->format('M d, Y h:i A') ?? 'N/A' }}</strong></div>
            </div>
        </article>

        <aside class="table-panel budget-action-panel pr-numbering-action-panel">
            <div class="pr-numbering-action-grid">
                <form method="POST" action="{{ route('pr-numbering.rfqs.assign', $rfq) }}" class="budget-action-form pr-numbering-action-card" onsubmit="return confirm('Assign these official RFQ details and return the RFQ to the requesting office?');">
                    @csrf
                    @method('PATCH')
                    <div class="pr-numbering-card-heading"><p class="eyebrow">Assign Details</p><h3>Official RFQ Information</h3></div>
                    <div class="pr-numbering-field-grid">
                        <div class="pr-numbering-field"><label for="rfq_number">Quotation Number</label><input id="rfq_number" name="rfq_number" type="text" value="{{ old('rfq_number', $rfq->rfq_number) }}" placeholder="Enter quotation number" required>@error('rfq_number')<span class="field-error">{{ $message }}</span>@enderror</div>
                        <div class="pr-numbering-field"><label for="rfq_date">RFQ Date</label><input id="rfq_date" name="rfq_date" type="date" value="{{ old('rfq_date', $rfq->rfq_date?->format('Y-m-d')) }}" required>@error('rfq_date')<span class="field-error">{{ $message }}</span>@enderror</div>
                        <div class="pr-numbering-field is-wide"><label for="assign-remarks">Remarks</label><textarea id="assign-remarks" name="remarks" rows="3">{{ old('remarks') }}</textarea></div>
                    </div>
                    <button type="submit" class="dashboard-action">Assign and Return to Office</button>
                </form>

                <form method="POST" action="{{ route('pr-numbering.rfqs.return', $rfq) }}" class="budget-action-form pr-numbering-action-card pr-numbering-return-card" onsubmit="return confirm('Return this RFQ for correction?');">
                    @csrf
                    @method('PATCH')
                    <div class="pr-numbering-card-heading"><p class="eyebrow">Return RFQ</p><h3>Correction Required</h3></div>
                    <div class="pr-numbering-field"><label for="return-remarks">Return Reason</label><textarea id="return-remarks" name="remarks" rows="4" required>{{ old('remarks') }}</textarea></div>
                    <button type="submit" class="dashboard-action danger-action">Return for Correction</button>
                </form>
            </div>
        </aside>
    </section>

    @include('head-office.rfqs.partials.rfq-excel-form', ['rfq' => $rfq, 'sourceDocument' => $rfq->sourcePrDocument, 'sourceResolution' => $rfq->sourceBacResolution, 'mode' => 'show'])
@endsection
