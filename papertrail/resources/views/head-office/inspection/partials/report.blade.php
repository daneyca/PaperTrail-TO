@php
    $editable = $editable ?? false;
    $formatQuantity = fn ($quantity) => rtrim(rtrim(number_format((float) $quantity, 2, '.', ','), '0'), '.');
    $isInspected = $record->exists && in_array($record->status, ['inspected', 'accepted', 'completed'], true);
    $isAccepted = $record->exists && in_array($record->status, ['accepted', 'completed'], true);
@endphp
<article class="iar-report" aria-label="Official Inspection and Acceptance Report">
    <header class="iar-heading">
        <h2>INSPECTION AND ACCEPTANCE REPORT</h2>
        <p>Municipality of Tomas Oppus</p>
    </header>
    <div class="iar-metadata">
        <div class="iar-meta-row">
            <div><span>Supplier:</span><strong>{{ $purchaseOrder->supplier_name }}</strong></div>
            <div><span>IAR No.:</span><strong>{{ $record->document_reference_number }}</strong></div>
        </div>
        <div class="iar-meta-row">
            <div><span>P.O. No.:</span><strong>{{ $purchaseOrder->po_number }}</strong><span>Date</span><strong>{{ $purchaseOrder->po_date?->format('m/d/Y') }}</strong></div>
            <div><span>Invoice No.</span><strong>@if ($editable) @include('head-office.inspection.partials.field', ['name' => 'invoice_number', 'label' => 'Invoice number', 'value' => $record->invoice_number, 'maxlength' => 120]) @else{{ $record->invoice_number }}@endif</strong><span>Date</span><strong>@if ($editable) @include('head-office.inspection.partials.field', ['name' => 'invoice_date', 'label' => 'Invoice date', 'type' => 'date', 'value' => $record->invoice_date?->format('Y-m-d')]) @else{{ $record->invoice_date?->format('m/d/Y') }}@endif</strong></div>
        </div>
        <div class="iar-meta-row">
            <div class="iar-office"><span>Requisitioning Office:</span><strong>{{ $purchaseOrder->sourcePrDocument?->submittingOffice?->name }}</strong></div>
            <div><span>Date:</span><strong>@if ($editable) @include('head-office.inspection.partials.field', ['name' => 'report_date', 'label' => 'IAR date', 'type' => 'date', 'value' => $record->report_date?->format('Y-m-d')]) @else{{ $record->report_date?->format('m/d/Y') }}@endif</strong></div>
        </div>
    </div>
    <table class="iar-items">
        <colgroup><col style="width:10.5%"><col style="width:11%"><col style="width:60%"><col style="width:18.5%"></colgroup>
        <thead><tr><th>Item No.</th><th>Unit</th><th class="iar-description-heading">DESCRIPTION</th><th>Quantity</th></tr></thead>
        <tbody>
            @foreach ($purchaseOrder->items as $item)
                <tr><td>{{ $item->item_no }}</td><td>{{ $item->unit }}</td><td class="iar-description">{{ $item->description ?: $item->item_description }}</td><td>{{ $formatQuantity($item->quantity) }}</td></tr>
            @endforeach
            @for ($row = $purchaseOrder->items->count(); $row < 28; $row++)
                <tr class="iar-blank-row"><td>&nbsp;</td><td></td><td></td><td></td></tr>
            @endfor
        </tbody>
    </table>
    <div class="iar-certifications">
        <section class="iar-inspection">
            <h3>INSPECTION</h3>
            <div class="iar-certification-body">
                <p>Date Inspected: <span class="iar-date">@if ($editable) @include('head-office.inspection.partials.field', ['name' => 'inspection_date', 'label' => 'Date inspected', 'type' => 'date', 'value' => $record->inspection_date?->format('Y-m-d'), 'required' => true]) @else{{ $record->inspection_date?->format('m/d/Y') }}@endif</span></p>
                <p class="iar-check-line">@if ($editable)<input type="hidden" name="quality_condition" value="Non-conforming"><input class="iar-edit-checkbox" type="checkbox" name="quality_condition" value="Conforming" aria-label="Inspected and found OK" @checked(old('quality_condition', $record->quality_condition) === 'Conforming')>@else<span class="iar-checkbox">{{ $isInspected && strcasecmp((string) $record->quality_condition, 'Conforming') === 0 ? '✓' : '' }}</span>@endif<span>Inspected, verified and found OK<br>as to quantity and specifications</span></p>
                <div class="iar-inspection-signatures">
                    <div>
                        <strong>@if ($editable) @include('head-office.inspection.partials.field', ['name' => 'inspector_name', 'label' => 'Inspection officer name', 'value' => $record->inspector_name]) @else{{ $record->inspector_name }}@endif</strong>
                        <span>@if ($editable) @include('head-office.inspection.partials.field', ['name' => 'inspector_designation', 'label' => 'Inspection officer designation', 'value' => $record->inspector_designation ?? 'Inspection Officer - Des.']) @else{{ $record->inspector_designation ?? 'Inspection Officer - Des.' }}@endif</span>
                    </div>
                    <div>
                        <strong>@if ($editable) @include('head-office.inspection.partials.field', ['name' => 'inspection_chair_name', 'label' => 'Municipal assessor name', 'value' => $record->inspection_chair_name]) @else{{ $record->inspection_chair_name }}@endif</strong>
                        <span>@if ($editable) @include('head-office.inspection.partials.field', ['name' => 'inspection_chair_designation', 'label' => 'Inspection chair designation', 'value' => $record->inspection_chair_designation ?? 'HRMO/Municipal Assessor Designate']) @else{{ $record->inspection_chair_designation ?? 'HRMO/Municipal Assessor Designate' }}@endif</span>
                    </div>
                </div>
            </div>
        </section>
        <section class="iar-acceptance">
            <h3>ACCEPTANCE</h3>
            <div class="iar-certification-body">
                <p>Date Received: <span class="iar-date">@if ($editable) @include('head-office.inspection.partials.field', ['name' => 'acceptance_date', 'label' => 'Date received', 'type' => 'date', 'value' => $record->acceptance_date?->format('Y-m-d')]) @else{{ $record->acceptance_date?->format('m/d/Y') }}@endif</span></p>
                <p class="iar-check-line">@if ($editable)<input class="iar-edit-checkbox" type="radio" name="quantity_condition" value="Complete" aria-label="Complete delivery" @checked(old('quantity_condition', $record->quantity_condition ?? 'Complete') === 'Complete') required>@else<span class="iar-checkbox">{{ $isAccepted && strcasecmp((string) $record->quantity_condition, 'Complete') === 0 ? '✓' : '' }}</span>@endif<span>Complete</span></p>
                <p class="iar-check-line">@if ($editable)<input class="iar-edit-checkbox" type="radio" name="quantity_condition" value="Partial" aria-label="Partial delivery" @checked(old('quantity_condition', $record->quantity_condition) === 'Partial') required>@else<span class="iar-checkbox">{{ $isAccepted && strcasecmp((string) $record->quantity_condition, 'Partial') === 0 ? '✓' : '' }}</span>@endif<span>Partial</span></p>
                <div class="iar-acceptance-signature">
                    <strong>@if ($editable) @include('head-office.inspection.partials.field', ['name' => 'acceptance_officer_name', 'label' => 'Acceptance officer name', 'value' => $record->acceptance_officer_name]) @else{{ $record->acceptance_officer_name }}@endif</strong>
                    <span>@if ($editable) @include('head-office.inspection.partials.field', ['name' => 'acceptance_officer_designation', 'label' => 'Acceptance officer designation', 'value' => $record->acceptance_officer_designation ?? 'Admin Aide I']) @else{{ $record->acceptance_officer_designation ?? 'Admin Aide I' }}@endif</span>
                </div>
            </div>
        </section>
    </div>
</article>
