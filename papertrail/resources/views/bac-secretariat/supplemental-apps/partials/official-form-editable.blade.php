@php
    $rows = old('items', $items ?? []);
    $sourceDocument = $sourceDocument ?? $supplementalApp->sourcePrDocument;
    $selectedOfficeId = old('requesting_office_id', $supplementalApp->requesting_office_id ?? $sourceDocument?->submitting_office_id);
    $requestingOfficeName = old('requesting_office_name', $supplementalApp->requesting_office_name ?? $sourceDocument?->submittingOffice?->name);
    $sourcePrNumber = $sourceDocument?->pr_no ?? $sourceDocument?->tracking_number ?? 'Not linked';
    $preparedBy = $supplementalApp->preparedBy?->name ?? auth()->user()?->name ?? 'BAC Secretariat';
    $preparedRole = $supplementalApp->preparedBy?->role ?? auth()->user()?->role ?? 'BAC Secretariat';
@endphp

<input type="hidden" name="source_pr_document_id" value="{{ old('source_pr_document_id', $supplementalApp->source_pr_document_id ?? $sourceDocument?->id) }}">

<div class="supplemental-sheet-scroll supplemental-app-page">
    <section class="supplemental-official-sheet supplemental-app-official-sheet supplemental-official-sheet-editable" aria-label="Official Supplemental APP form">
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
                <label for="supplemental_app_number">Supplemental APP No.:</label>
                <input id="supplemental_app_number" name="supplemental_app_number" type="text" value="{{ old('supplemental_app_number', $supplementalApp->supplemental_app_number) }}">
            </div>
            @error('supplemental_app_number')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <table class="supplemental-info-table supplemental-app-info-table supplemental-app-official-table">
            <tbody>
                <tr>
                    <th>Fiscal Year</th>
                    <td>
                        <input id="fiscal_year" name="fiscal_year" type="number" min="2000" max="2100" value="{{ old('fiscal_year', $supplementalApp->fiscal_year) }}">
                        @error('fiscal_year')<p class="field-error">{{ $message }}</p>@enderror
                    </td>
                    <th>Source PR No.</th>
                    <td><span class="supplemental-readonly-value">{{ $sourcePrNumber }}</span></td>
                </tr>
                <tr>
                    <th>Requesting Office</th>
                    <td>
                        <select id="requesting_office_id" name="requesting_office_id">
                            <option value="">Select office</option>
                            @foreach ($offices as $office)
                                <option value="{{ $office->id }}" @selected((string) $selectedOfficeId === (string) $office->id)>{{ $office->name }}</option>
                            @endforeach
                        </select>
                        @error('requesting_office_id')<p class="field-error">{{ $message }}</p>@enderror
                    </td>
                    <th>Requesting Office Name</th>
                    <td>
                        <input id="requesting_office_name" name="requesting_office_name" type="text" value="{{ $requestingOfficeName }}">
                        @error('requesting_office_name')<p class="field-error">{{ $message }}</p>@enderror
                    </td>
                </tr>
                <tr>
                    <th>Title</th>
                    <td colspan="3">
                        <input id="title" name="title" type="text" value="{{ old('title', $supplementalApp->title) }}">
                        @error('title')<p class="field-error">{{ $message }}</p>@enderror
                    </td>
                </tr>
            </tbody>
        </table>

        <table class="supplemental-text-table supplemental-app-official-table">
            <tbody>
                <tr>
                    <th>PURPOSE</th>
                    <td>
                        <textarea id="purpose" name="purpose" rows="3">{{ old('purpose', $supplementalApp->purpose) }}</textarea>
                        @error('purpose')<p class="field-error">{{ $message }}</p>@enderror
                    </td>
                </tr>
                <tr>
                    <th>JUSTIFICATION</th>
                    <td>
                        <textarea id="justification" name="justification" rows="4">{{ old('justification', $supplementalApp->justification) }}</textarea>
                        @error('justification')<p class="field-error">{{ $message }}</p>@enderror
                    </td>
                </tr>
            </tbody>
        </table>

        @error('items')<p class="field-error supplemental-table-error">{{ $message }}</p>@enderror

        <table class="supplemental-official-items supplemental-app-items-table supplemental-app-official-table" data-supplemental-items-table>
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
                @foreach ($rows as $index => $item)
                    <tr>
                        <td>
                            <input type="hidden" name="items[{{ $index }}][source_pr_item_id]" value="{{ $item['source_pr_item_id'] ?? '' }}">
                            <input name="items[{{ $index }}][item_no]" type="text" value="{{ $item['item_no'] ?? '' }}">
                            @error("items.$index.item_no")<span class="field-error">{{ $message }}</span>@enderror
                        </td>
                        <td>
                            <textarea name="items[{{ $index }}][description]" rows="2">{{ $item['description'] ?? '' }}</textarea>
                            @error("items.$index.description")<span class="field-error">{{ $message }}</span>@enderror
                        </td>
                        <td>
                            <input name="items[{{ $index }}][quantity]" type="number" min="0" step="0.01" value="{{ $item['quantity'] ?? '' }}" data-supplemental-qty>
                            @error("items.$index.quantity")<span class="field-error">{{ $message }}</span>@enderror
                        </td>
                        <td>
                            <input name="items[{{ $index }}][unit]" type="text" value="{{ $item['unit'] ?? '' }}">
                            @error("items.$index.unit")<span class="field-error">{{ $message }}</span>@enderror
                        </td>
                        <td>
                            <input name="items[{{ $index }}][estimated_unit_cost]" type="number" min="0" step="0.01" value="{{ $item['estimated_unit_cost'] ?? '' }}" data-supplemental-unit-cost>
                            @error("items.$index.estimated_unit_cost")<span class="field-error">{{ $message }}</span>@enderror
                        </td>
                        <td class="supplemental-money-cell" data-supplemental-row-total>{{ number_format((float) ($item['estimated_total_cost'] ?? 0), 2) }}</td>
                        <td>
                            <input name="items[{{ $index }}][remarks]" type="text" value="{{ $item['remarks'] ?? '' }}">
                            @error("items.$index.remarks")<span class="field-error">{{ $message }}</span>@enderror
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5">TOTAL SUPPLEMENTAL APP BUDGET:</th>
                    <th class="supplemental-money-cell" data-supplemental-grand-total>PHP {{ number_format((float) old('total_amount', $supplementalApp->total_amount), 2) }}</th>
                    <th></th>
                </tr>
            </tfoot>
        </table>

        <table class="supplemental-text-table supplemental-remarks-table supplemental-app-official-table">
            <tbody>
                <tr>
                    <th>REMARKS</th>
                    <td>
                        <textarea id="remarks" name="remarks" rows="3">{{ old('remarks', $supplementalApp->remarks) }}</textarea>
                        @error('remarks')<p class="field-error">{{ $message }}</p>@enderror
                    </td>
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
                <small>Date: __________________</small>
            </div>
            <div class="supplemental-signatory">
                <span>Recommended by:</span>
                <strong>____________________________</strong>
                <em>Signature over Printed Name</em>
                <p>By the Authority of the Bids and Awards Committee</p>
                <em>Position/Designation</em>
                <small>Date: __________________</small>
            </div>
            <div class="supplemental-signatory">
                <span>Approved by:</span>
                <strong>____________________________</strong>
                <em>Signature over Printed Name</em>
                <p>Authorized Approving Official</p>
                <em>Position/Designation</em>
                <small>Date: __________________</small>
            </div>
        </section>
    </section>
</div>
