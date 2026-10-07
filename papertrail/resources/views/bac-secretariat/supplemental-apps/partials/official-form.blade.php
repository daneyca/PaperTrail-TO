@php
    $sourceDocument = $supplementalApp->sourcePrDocument;
    $sourcePrNumber = $sourceDocument?->pr_no ?? $sourceDocument?->tracking_number ?? 'Not linked';
    $requestingOffice = $supplementalApp->requestingOffice?->name ?? $supplementalApp->requesting_office_name ?? 'N/A';
    $preparedBy = $supplementalApp->preparedBy?->name ?? '____________________________';
    $preparedRole = $supplementalApp->preparedBy?->role ?? 'BAC Secretariat';
    $recommendedBy = $supplementalApp->submittedBy?->name ?? '____________________________';
    $recommendedRole = $supplementalApp->submittedBy?->role ?? 'By the Authority of the Bids and Awards Committee';
    $approvedBy = $supplementalApp->acceptedBy?->name ?? '____________________________';
    $approvedRole = $supplementalApp->acceptedBy?->role ?? 'Authorized Approving Official';
@endphp

<div class="supplemental-sheet-scroll supplemental-app-page">
    <section class="supplemental-official-sheet supplemental-app-official-sheet supplemental-app-print-sheet" aria-label="Official Supplemental APP form">
        <header class="supplemental-official-header">
            <div class="supplemental-logo-slot">
                @if (file_exists(public_path('images/logos/lgu-logo.png')))
                    <img src="{{ asset('images/logos/lgu-logo.png') }}" alt="LGU Logo">
                @endif
            </div>
            <div class="supplemental-header-copy">
                <p>Republic of the Philippines</p>
                <p>Province of Southern Leyte</p>
                <strong>MUNICIPALITY OF TOMAS OPPUS</strong>
            </div>
            <div class="supplemental-logo-slot supplemental-bagong-slot">
                @if (file_exists(public_path('images/logos/bagongpilipinas.jpg')))
                    <img src="{{ asset('images/logos/bagongpilipinas.jpg') }}" alt="Bagong Pilipinas Logo">
                @endif
            </div>
        </header>

        <div class="supplemental-title-block">
            <h2>SUPPLEMENTAL ANNUAL PROCUREMENT PLAN</h2>
            <div class="supplemental-title-meta">
                <span>Supplemental APP No.:</span>
                <strong>{{ $supplementalApp->supplemental_app_number ?? 'Draft' }}</strong>
            </div>
        </div>

        <table class="supplemental-info-table supplemental-app-info-table supplemental-app-official-table">
            <tbody>
                <tr>
                    <th>Fiscal Year</th>
                    <td>{{ $supplementalApp->fiscal_year ?? 'N/A' }}</td>
                    <th>Source PR No.</th>
                    <td>{{ $sourcePrNumber }}</td>
                </tr>
                <tr>
                    <th>Requesting Office</th>
                    <td>{{ $requestingOffice }}</td>
                    <th>Requesting Office Name</th>
                    <td>{{ $supplementalApp->requesting_office_name ?? $requestingOffice }}</td>
                </tr>
                <tr>
                    <th>Title</th>
                    <td colspan="3">{{ $supplementalApp->title ?? 'N/A' }}</td>
                </tr>
            </tbody>
        </table>

        <table class="supplemental-text-table supplemental-app-official-table">
            <tbody>
                <tr>
                    <th>PURPOSE</th>
                    <td>{!! nl2br(e($supplementalApp->purpose ?? 'N/A')) !!}</td>
                </tr>
                <tr>
                    <th>JUSTIFICATION</th>
                    <td>{!! nl2br(e($supplementalApp->justification ?? 'N/A')) !!}</td>
                </tr>
            </tbody>
        </table>

        <table class="supplemental-official-items supplemental-app-items-table supplemental-app-official-table">
            <colgroup>
                <col class="col-item-no">
                <col class="col-description">
                <col class="col-quantity">
                <col class="col-unit">
                <col class="col-unit-cost">
                <col class="col-total-cost">
                <col class="col-remarks">
            </colgroup>
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
                        <td>{{ $item->item_no ?: '' }}</td>
                        <td>{!! nl2br(e($item->description ?: '')) !!}</td>
                        <td class="supplemental-money-cell">{{ $item->quantity !== null ? rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') : '' }}</td>
                        <td>{{ $item->unit ?: '' }}</td>
                        <td class="supplemental-money-cell">{{ $item->estimated_unit_cost !== null ? number_format((float) $item->estimated_unit_cost, 2) : '' }}</td>
                        <td class="supplemental-money-cell">{{ number_format((float) $item->estimated_total_cost, 2) }}</td>
                        <td>{{ $item->remarks ?: '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="supplemental-empty-row">No items encoded.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5">TOTAL SUPPLEMENTAL APP BUDGET:</th>
                    <th class="supplemental-money-cell">PHP {{ number_format((float) $supplementalApp->total_amount, 2) }}</th>
                    <th></th>
                </tr>
            </tfoot>
        </table>

        <table class="supplemental-text-table supplemental-remarks-table supplemental-app-official-table">
            <tbody>
                <tr>
                    <th>REMARKS</th>
                    <td>{!! nl2br(e($supplementalApp->remarks ?? '')) !!}</td>
                </tr>
            </tbody>
        </table>

        <section class="supplemental-signatory-section" aria-label="Supplemental APP signatories">
            <div class="supplemental-signatory">
                <span>Prepared by:</span>
                <strong>{{ $preparedBy }}</strong>
                <em>Signature over Printed Name</em>
                <p>{{ $preparedRole }}</p>
                <em>Position/Designation</em>
                <small>Date: {{ $supplementalApp->created_at?->format('m/d/Y') ?? '__________________' }}</small>
            </div>
            <div class="supplemental-signatory">
                <span>Recommended by:</span>
                <strong>{{ $recommendedBy }}</strong>
                <em>Signature over Printed Name</em>
                <p>{{ $recommendedRole }}</p>
                <em>Position/Designation</em>
                <small>Date: {{ $supplementalApp->submitted_at?->format('m/d/Y') ?? '__________________' }}</small>
            </div>
            <div class="supplemental-signatory">
                <span>Approved by:</span>
                <strong>{{ $approvedBy }}</strong>
                <em>Signature over Printed Name</em>
                <p>{{ $approvedRole }}</p>
                <em>Position/Designation</em>
                <small>Date: {{ $supplementalApp->accepted_at?->format('m/d/Y') ?? '__________________' }}</small>
            </div>
        </section>
    </section>
</div>
