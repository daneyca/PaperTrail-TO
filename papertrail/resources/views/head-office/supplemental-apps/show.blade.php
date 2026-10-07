@extends('layouts.dashboard')

@section('title', ($supplementalApp->supplemental_app_number ?? 'Supplemental APP') . ' | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Head of Office / End User</p>
            <h1>{{ $supplementalApp->supplemental_app_number ?? 'Supplemental APP' }}</h1>
            <p>View the Supplemental APP linked to your office Purchase Request.</p>
        </div>

        <div class="hero-actions">
            <span class="status-pill status-{{ $supplementalApp->status }}">{{ str($supplementalApp->status)->replace('_', ' ')->title() }}</span>
            <a href="{{ route('head-office.documents.index') }}" class="dashboard-action secondary-action">Back to My Documents</a>
        </div>
    </section>

    <section class="pr-action-toolbar no-print">
        <div class="pr-toolbar-group">
            <a href="{{ route('head-office.supplemental-apps.print', $supplementalApp) }}" target="_blank" class="btn-pr-secondary">Print Supplemental APP</a>
            <x-ai.completeness-check-button
                document-type="supplemental_app"
                :document-id="$supplementalApp->id"
                :tracking-number="$supplementalApp->supplemental_app_number"
                label="AI Check Supplemental APP"
            />
            @if ($supplementalApp->sourcePrDocument)
                <a href="{{ route('head-office.documents.show', $supplementalApp->sourcePrDocument) }}" class="btn-pr-primary">View Linked PR</a>
            @endif
        </div>
    </section>

    <section class="table-panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Supplemental APP</p>
                <h2>Document Details</h2>
            </div>
        </div>

        <div class="my-document-summary-list supplemental-summary-list">
            <div><span>Supplemental APP No.</span><strong>{{ $supplementalApp->supplemental_app_number ?? 'Draft' }}</strong></div>
            <div><span>Fiscal Year</span><strong>{{ $supplementalApp->fiscal_year ?? 'N/A' }}</strong></div>
            <div><span>Requesting Office</span><strong>{{ $supplementalApp->requestingOffice?->name ?? $supplementalApp->requesting_office_name ?? 'N/A' }}</strong></div>
            <div><span>Source PR</span><strong>{{ $supplementalApp->sourcePrDocument?->pr_no ?? $supplementalApp->sourcePrDocument?->tracking_number ?? 'N/A' }}</strong></div>
            <div><span>Total Amount</span><strong>PHP {{ number_format((float) $supplementalApp->total_amount, 2) }}</strong></div>
            <div><span>Accepted At</span><strong>{{ $supplementalApp->accepted_at?->format('M d, Y h:i A') ?? 'N/A' }}</strong></div>
        </div>
    </section>

    <section class="table-panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Basis</p>
                <h2>Purpose and Justification</h2>
            </div>
        </div>
        <div class="supplemental-text-block">
            <p><strong>Purpose:</strong> {{ $supplementalApp->purpose ?? 'N/A' }}</p>
            <p><strong>Justification:</strong> {{ $supplementalApp->justification ?? 'N/A' }}</p>
        </div>
    </section>

    <section class="table-panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Items</p>
                <h2>Supplemental APP Items</h2>
            </div>
        </div>
        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>Item No.</th>
                        <th>Description</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Estimated Unit Cost</th>
                        <th>Estimated Total Cost</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($supplementalApp->items as $item)
                        <tr>
                            <td>{{ $item->item_no ?? 'N/A' }}</td>
                            <td>{{ $item->description ?? 'N/A' }}</td>
                            <td>{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                            <td>{{ $item->unit ?? 'N/A' }}</td>
                            <td>PHP {{ number_format((float) $item->estimated_unit_cost, 2) }}</td>
                            <td>PHP {{ number_format((float) $item->estimated_total_cost, 2) }}</td>
                            <td>{{ $item->remarks ?? 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty-state"><strong>No items encoded</strong><p>Items will appear once BAC Secretariat encodes the Supplemental APP.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
