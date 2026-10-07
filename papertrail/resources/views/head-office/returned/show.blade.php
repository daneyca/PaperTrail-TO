@extends('layouts.dashboard')

@section('title', $document->displayNumber() . ' | PaperTrail')

@section('content')
    @php
        $isPr = in_array($document->document_type, ['PR', 'Purchase Request'], true);
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Head of Office / End User</p>
            <h1>{{ $document->displayNumber() }}</h1>
            <p>{{ $document->title ?? $document->purpose ?? 'Returned document details' }}</p>
        </div>

        <div class="hero-actions">
            <a href="{{ route('head-office.documents.index', ['tab' => 'returned']) }}" class="dashboard-action secondary-action">Back to Returned Documents</a>
            @if ($isPr)
                <x-ai.completeness-check-button
                    document-type="purchase_request"
                    :document-id="$document->id"
                    :tracking-number="$document->displayNumber()"
                    label="AI Check PR"
                />
            @endif
            @if ($revision)
                <a href="{{ $revision['url'] }}" class="dashboard-action">{{ $revision['label'] }}</a>
            @endif
        </div>
    </section>

    @if ($isPr)
        <section class="returned-pr-document-preview" aria-label="Returned Purchase Request preview">
            <div class="pr-preview-area">
                @include('head-office.pr._preview', ['sheetClass' => 'pr-readonly-sheet returned-pr-sheet', 'mode' => 'show'])
            </div>
        </section>

        <div id="pr-signatures">
            @include('head-office.pr._signatory-tracking-panel', ['document' => $document])
        </div>
    @endif

    <x-documents.attachments-panel
        :document="$document"
        :document-type="$isPr ? 'purchase_request' : 'procurement_document'"
        :can-upload="(bool) $revision"
        title="Supporting Documents"
    />

    <section class="budget-review-layout returned-document-detail-layout">
        <article class="table-panel budget-detail-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Document Information</p>
                    <h2>{{ $document->document_type }}</h2>
                    <p>{{ $document->description ?? $document->purpose ?? 'No description or purpose provided.' }}</p>
                </div>
                <span class="status-pill status-{{ $document->status }}">{{ str($document->status)->replace('_', ' ')->title() }}</span>
            </div>

            <div class="detail-grid budget-detail-grid">
                <div><span>Tracking Number</span><strong>{{ $document->displayNumber() }}</strong></div>
                @if ($isPr)
                    <div><span>Official PR Number</span><strong>{{ $document->pr_no ?? 'Not assigned' }}</strong></div>
                @endif
                <div><span>Document Type</span><strong>{{ $document->document_type }}</strong></div>
                <div><span>Fiscal Year</span><strong>{{ $document->fiscal_year ?? 'N/A' }}</strong></div>
                <div><span>Submitting Office</span><strong>{{ $document->submittingOffice?->name ?? 'N/A' }}</strong></div>
                <div><span>Submitted By</span><strong>{{ $document->submittedBy?->name ?? 'N/A' }}</strong></div>
                <div><span>Prepared By</span><strong>{{ $document->preparedBy?->name ?? 'N/A' }}</strong></div>
                <div><span>Submitted Date</span><strong>{{ $document->submitted_at?->format('M d, Y h:i A') ?? 'Not submitted' }}</strong></div>
                <div><span>Total Amount</span><strong>PHP {{ number_format((float) $document->total_amount, 2) }}</strong></div>
                <div><span>Current Status</span><strong>{{ str($document->status)->replace('_', ' ')->title() }}</strong></div>
                <div><span>Current Stage</span><strong>{{ $document->stage ?? 'N/A' }}</strong></div>
                <div><span>Current Office</span><strong>{{ $document->currentOffice?->name ?? 'N/A' }}</strong></div>
                <div><span>Assigned User</span><strong>{{ $document->assignedTo?->name ?? 'N/A' }}</strong></div>
                <div class="audit-json"><span>Title</span><strong>{{ $document->title ?? $document->purpose ?? 'N/A' }}</strong></div>
            </div>
        </article>

        <aside class="table-panel returned-revision-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Revision</p>
                    <h2>Correction Access</h2>
                </div>
            </div>

            @if ($revision)
                <div class="budget-action-form">
                    <p class="returned-note">Use the existing edit workflow to revise this returned document. This page remains read-only.</p>
                    <a href="{{ $revision['url'] }}" class="dashboard-action">{{ $revision['label'] }}</a>
                </div>
            @else
                <div class="empty-state">
                    <strong>Revision not configured</strong>
                    <p>Revision will be available once the document edit workflow is configured.</p>
                </div>
            @endif
        </aside>
    </section>

    <section class="table-panel returned-detail-panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Return Details</p>
                <h2>Reason and Routing Source</h2>
                <p>This section summarizes why the document was returned and where it came from.</p>
            </div>
        </div>

        <div class="detail-grid budget-detail-grid returned-detail-grid">
            <div><span>Returned By</span><strong>{{ $returnMeta['returned_by'] ?? 'N/A' }}</strong></div>
            <div><span>Returned From Office</span><strong>{{ $returnMeta['from_office'] ?? 'N/A' }}</strong></div>
            <div><span>Returned To Office</span><strong>{{ $returnMeta['to_office'] ?? 'N/A' }}</strong></div>
            <div><span>Returned Date</span><strong>{{ $returnMeta['date'] ? $returnMeta['date']->format('M d, Y h:i A') : 'N/A' }}</strong></div>
            <div><span>Status Before Return</span><strong>{{ $returnMeta['status_from'] ? str($returnMeta['status_from'])->replace('_', ' ')->title() : 'N/A' }}</strong></div>
            <div><span>Status After Return</span><strong>{{ str($returnMeta['status_to'] ?? $document->status)->replace('_', ' ')->title() }}</strong></div>
            <div class="audit-json"><span>Return Reason / Comments</span><strong>{{ $returnMeta['reason'] ?? 'No return reason recorded.' }}</strong></div>
            <div class="audit-json"><span>Required Correction</span><strong>{{ $returnMeta['required_correction'] ?? 'No required correction recorded.' }}</strong></div>
        </div>
    </section>

    <section class="dashboard-widget-grid returned-document-sections">
        <article class="dashboard-widget widget-wide">
            <div class="widget-heading">
                <div>
                    <p class="eyebrow">Document Items</p>
                    <h2>Item Details</h2>
                </div>
            </div>

            @if ($document->ppmpItems->isNotEmpty())
                <div class="table-scroll compact-table-scroll">
                    <table class="user-management-table my-document-items-table">
                        <thead>
                            <tr>
                                <th>Item No.</th>
                                <th>General Description</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Unit Cost</th>
                                <th>Total Cost</th>
                                <th>Mode</th>
                                <th>Quarter</th>
                                <th>Category</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($document->ppmpItems as $item)
                                <tr>
                                    <td>{{ $item->item_no ?? '-' }}</td>
                                    <td>{{ $item->general_description }}</td>
                                    <td>{{ number_format((float) $item->quantity, 2) }}</td>
                                    <td>{{ $item->unit ?? 'N/A' }}</td>
                                    <td class="nowrap">PHP {{ number_format((float) $item->estimated_unit_cost, 2) }}</td>
                                    <td class="nowrap">PHP {{ number_format((float) $item->estimated_total_cost, 2) }}</td>
                                    <td>{{ $item->procurement_mode ?? 'N/A' }}</td>
                                    <td>{{ $item->schedule_quarter ?? 'N/A' }}</td>
                                    <td>{{ $item->category ?? 'N/A' }}</td>
                                    <td>{{ $item->remarks ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif ($document->purchaseRequestItems->isNotEmpty())
                <div class="table-scroll compact-table-scroll">
                    <table class="user-management-table my-document-items-table">
                        <thead>
                            <tr>
                                <th>Item No.</th>
                                <th>Description</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Unit Cost</th>
                                <th>Total Cost</th>
                                <th>APP Reference</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($document->purchaseRequestItems as $item)
                                <tr>
                                    <td>{{ $item->item_no ?? '-' }}</td>
                                    <td>{{ $item->item_description }}</td>
                                    <td>{{ number_format((float) $item->quantity, 2) }}</td>
                                    <td>{{ $item->unit ?? 'N/A' }}</td>
                                    <td class="nowrap">PHP {{ number_format((float) $item->estimated_unit_cost, 2) }}</td>
                                    <td class="nowrap">PHP {{ number_format((float) $item->estimated_total_cost, 2) }}</td>
                                    <td>{{ $item->appItem?->general_description ? str($item->appItem->general_description)->limit(60) : 'N/A' }}</td>
                                    <td>{{ $item->remarks ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif ($document->purchaseOrder?->items?->isNotEmpty())
                <div class="table-scroll compact-table-scroll">
                    <table class="user-management-table my-document-items-table">
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
                            @foreach ($document->purchaseOrder->items as $item)
                                <tr>
                                    <td>{{ $item->item_no ?? '-' }}</td>
                                    <td>{{ $item->item_description }}</td>
                                    <td>{{ number_format((float) $item->quantity, 2) }}</td>
                                    <td>{{ $item->unit ?? 'N/A' }}</td>
                                    <td class="nowrap">PHP {{ number_format((float) $item->unit_cost, 2) }}</td>
                                    <td class="nowrap">PHP {{ number_format((float) $item->total_cost, 2) }}</td>
                                    <td>{{ $item->remarks ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <strong>No document items recorded</strong>
                    <p>Line items will appear here when this document type includes item details.</p>
                </div>
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
                    <li><strong>No routing history yet</strong><span>Return routing actions will appear here.</span></li>
                @endforelse
            </ol>
        </article>

        <article class="dashboard-widget">
            <div class="widget-heading"><div><p class="eyebrow">Audit / Activity</p><h2>Related Activity</h2></div></div>

            <div class="my-document-activity-list">
                @forelse ($activities as $activity)
                    <div class="my-document-activity-row">
                        <strong>{{ $activity->action }}</strong>
                        <span>{{ $activity->created_at?->format('M d, Y h:i A') }} &middot; {{ $activity->user_name ?? 'System' }}</span>
                        <p>{{ $activity->description ?? 'No activity description provided.' }}</p>
                    </div>
                @empty
                    <div class="empty-state">
                        <strong>No related activity yet</strong>
                        <p>Audit entries tied to this document will appear here.</p>
                    </div>
                @endforelse
            </div>
        </article>
    </section>
@endsection
