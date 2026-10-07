@extends('layouts.dashboard')

@section('title', ($po->po_number ?? 'Purchase Order') . ' | PaperTrail')

@section('content')
    <section class="po-toolbar no-print">
        <div>
            <strong>{{ $po->po_number ? 'Purchase Order '.$po->po_number : 'Purchase Order Draft' }}</strong>
            <span>Tracking Number {{ $po->document_reference_number ?? 'Pending' }} &middot; {{ str($po->status)->replace('_', ' ')->title() }}{{ $po->sourcePrDocument?->tracking_number ? ' - Source PR '.$po->sourcePrDocument->tracking_number : '' }}</span>
        </div>
        <a href="{{ route('bac-secretariat.purchase-orders.index') }}">Back</a>
        @if ($po->isEditable())
            <a href="{{ route('bac-secretariat.purchase-orders.edit', $po) }}">Edit</a>
        @endif
        <x-ai.completeness-check-button
            document-type="purchase_order"
            :document-id="$po->id"
            :tracking-number="$po->po_number"
        />
        <a href="{{ route('bac-secretariat.purchase-orders.print', $po) }}" target="_blank">Print</a>
        @if ($po->canSubmit())
            <form method="POST" action="{{ route('bac-secretariat.purchase-orders.submit', $po) }}" onsubmit="return confirm('Submit this Purchase Order?');">
                @csrf
                @method('PATCH')
                <button type="submit">Submit</button>
            </form>
        @endif
    </section>

    @include('bac-secretariat.purchase-orders.partials.purchase-order-excel-form', [
        'po' => $po,
        'sourceDocument' => $po->sourcePrDocument,
        'mode' => 'show',
    ])

    <x-documents.attachments-panel
        :document="$po"
        document-type="purchase_order"
        :can-upload="$po->isEditable()"
        title="Purchase Order Supporting Documents"
    />

    @include('partials.svp-related-documents', ['source' => $po, 'context' => 'bac-secretariat'])

    <section class="dashboard-widget widget-wide no-print">
        <div class="widget-heading"><div><p class="eyebrow">Routing History</p><h2>PO Movement</h2></div></div>
        <ol class="routing-timeline">
            @forelse ($po->procurementDocument?->routingHistories ?? [] as $history)
                <li>
                    <strong>{{ $history->action }}</strong>
                    <span>{{ $history->action_at?->format('M d, Y h:i A') }} by {{ $history->actionBy?->name ?? 'System' }}</span>
                    <p>{{ $history->fromOffice?->name ?? 'N/A' }} to {{ $history->toOffice?->name ?? 'N/A' }} &middot; {{ str($history->status_from ?? 'new')->replace('_', ' ')->title() }} to {{ str($history->status_to)->replace('_', ' ')->title() }}</p>
                    @if ($history->comments)<p>{{ $history->comments }}</p>@endif
                </li>
            @empty
                <li><strong>No PO routing history yet</strong><span>Submit this Purchase Order to record movement.</span></li>
            @endforelse
        </ol>
    </section>
@endsection
