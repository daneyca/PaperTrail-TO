@php
    $items = collect();
    $itemMode = null;

    if ($document->ppmpItems->isNotEmpty()) {
        $items = $document->ppmpItems;
        $itemMode = 'ppmp';
    } elseif ($document->purchaseRequestItems->isNotEmpty()) {
        $items = $document->purchaseRequestItems;
        $itemMode = 'pr';
    } elseif ($document->purchaseOrder?->items?->isNotEmpty()) {
        $items = $document->purchaseOrder->items;
        $itemMode = 'po';
    }
@endphp

<div class="a4-page document-preview generic-document-preview">
    <header class="document-preview-header">
        <p>Municipality of Tomas Oppus</p>
        <h2>{{ strtoupper($document->document_type ?? 'PROCUREMENT DOCUMENT') }}</h2>
        <span>{{ $document->tracking_number ?? 'Draft Document' }}</span>
    </header>

    <table class="document-preview-table document-preview-meta">
        <tbody>
            <tr>
                <th>Document Type</th>
                <td>{{ $document->document_type ?? 'N/A' }}</td>
                <th>Fiscal Year</th>
                <td>{{ $document->fiscal_year ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Office</th>
                <td>{{ $document->submittingOffice?->name ?? $document->department_name ?? 'N/A' }}</td>
                <th>Submitted By</th>
                <td>{{ $document->submittedBy?->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Status</th>
                <td>{{ str($document->status)->replace('_', ' ')->title()->replace('Ppmp', 'PPMP') }}</td>
                <th>Stage</th>
                <td>{{ $document->stage ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Current Office</th>
                <td>{{ $document->currentOffice?->name ?? 'N/A' }}</td>
                <th>Total Amount</th>
                <td>PHP {{ number_format((float) $document->total_amount, 2) }}</td>
            </tr>
            <tr>
                <th>Title</th>
                <td colspan="3">{{ $document->title ?? $document->purpose ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Description / Remarks</th>
                <td colspan="3">{{ $document->description ?? $document->remarks ?? 'N/A' }}</td>
            </tr>
        </tbody>
    </table>

    <table class="document-preview-table document-preview-items">
        <thead>
            <tr>
                <th>Item No.</th>
                <th>Description</th>
                <th>Quantity</th>
                <th>Unit</th>
                <th>Unit Cost</th>
                <th>Total Cost</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>{{ $item->item_no ?? '-' }}</td>
                    <td>
                        @if ($itemMode === 'ppmp')
                            {{ $item->general_description }}
                        @elseif ($itemMode === 'po')
                            {{ $item->item_description }}
                        @else
                            {{ $item->description ?? $item->item_description }}
                        @endif
                    </td>
                    <td class="document-preview-number">{{ number_format((float) $item->quantity, 2) }}</td>
                    <td>{{ $item->unit_of_issue ?? $item->unit ?? 'N/A' }}</td>
                    <td class="document-preview-number">PHP {{ number_format((float) ($item->estimated_unit_cost ?? $item->unit_cost), 2) }}</td>
                    <td class="document-preview-number">PHP {{ number_format((float) ($item->estimated_cost ?? $item->estimated_total_cost ?? $item->total_cost), 2) }}</td>
                    <td>{{ $item->remarks ?? 'N/A' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="document-preview-empty">No item details recorded for this document.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
