@php
    $sheetMode = $mode ?? 'readonly';
    $isEditable = in_array($sheetMode, ['create', 'edit'], true);
    $isPrint = $sheetMode === 'print';
    $currentUser = $user ?? auth()->user();
    $currentOffice = $office ?? $document->submittingOffice ?? $currentUser?->assignedOffice;
    $minimumPrRows = 18;

    if ($isEditable) {
        $oldItems = old('items');
        $formItems = is_array($oldItems) ? collect($oldItems) : collect($items ?? collect());
    } else {
        $formItems = collect($items ?? $document->purchaseRequestItems ?? collect());
    }

    $formItems = $formItems
        ->filter(function ($item) use ($isEditable) {
            if ($item === null) {
                return false;
            }

            $itemData = is_array($item) ? $item : [
                'quantity' => $item?->quantity ?? null,
                'unit_of_issue' => $item?->unit_of_issue ?? $item?->unit ?? null,
                'description' => $item?->description ?? $item?->item_description ?? null,
                'stock_no' => $item?->stock_no ?? null,
                'estimated_unit_cost' => $item?->estimated_unit_cost ?? null,
                'estimated_cost' => $item?->estimated_cost ?? $item?->estimated_total_cost ?? null,
                'app_item_id' => $item?->app_item_id ?? null,
            ];

            return $isEditable
                ? filled($itemData['quantity'] ?? null)
                    || filled($itemData['unit_of_issue'] ?? null)
                    || filled($itemData['description'] ?? null)
                    || filled($itemData['stock_no'] ?? null)
                    || filled($itemData['estimated_unit_cost'] ?? null)
                : true;
        })
        ->values();

    $formItems = $formItems->isNotEmpty() ? $formItems : collect([null]);

    while ($formItems->count() < $minimumPrRows) {
        $formItems->push(null);
    }

    $department = $isEditable
        ? old('department_name', $document->department_name ?? $currentOffice?->name ?? $currentUser?->office ?? 'No assigned office')
        : ($document->department_name ?? $document->submittingOffice?->name ?? '');

    $displayTotal = (float) ($document->total_amount ?: $formItems->sum(function ($item) {
        if ($item === null) {
            return 0;
        }

        $itemData = is_array($item) ? (object) $item : $item;
        $quantity = (float) ($itemData->quantity ?? 0);
        $unitCost = (float) ($itemData->estimated_unit_cost ?? 0);

        return (float) ($itemData->estimated_cost ?? $itemData->estimated_total_cost ?? ($quantity * $unitCost));
    }));

    $logoPath = public_path('images/logos/lgu-logo.png');
    $bagongPath = public_path('images/logos/bagongpilipinas.jpg');
    $fundCluster = $isEditable
        ? old('fund_cluster', $document->fund_cluster ?? 'General Fund')
        : ($document->fund_cluster ?? '');
    $responsibilityCenter = $isEditable
        ? old('responsibility_center', $document->responsibility_center ?? '')
        : ($document->responsibility_center ?? '');
    $sectionValue = $isEditable
        ? old('section', $document->section)
        : ($document->section ?? '');
    $prDateDisplay = $document->pr_date?->format('M d, Y') ?? '';
@endphp

<div class="pr-document {{ $isEditable ? 'pr-editable-sheet' : 'pr-readonly-document' }} {{ $isPrint ? 'pr-print-document' : '' }}" @if ($isEditable) data-pr-form @endif>
    <header class="pr-official-header">
        <div class="pr-logo-slot">
            @if (file_exists($logoPath))
                <img src="{{ asset('images/logos/lgu-logo.png') }}" alt="LGU Logo">
            @endif
        </div>

        <div class="pr-header-text">
            <p>Republic of the Philippines</p>
            <p>Province of Southern Leyte</p>
            <strong>MUNICIPALITY OF TOMAS OPPUS</strong>
        </div>

        <div class="pr-logo-slot pr-bagong-slot">
            @if (file_exists($bagongPath))
                <img src="{{ asset('images/logos/bagongpilipinas.jpg') }}" alt="Bagong Pilipinas Logo">
            @endif
        </div>
    </header>

    <h1 class="pr-main-title">PURCHASE REQUEST</h1>

    <table class="pr-table pr-meta-table">
        <colgroup>
            <col style="width: 17%">
            <col style="width: 31%">
            <col style="width: 15%">
            <col style="width: 17%">
            <col style="width: 8%">
            <col style="width: 12%">
        </colgroup>
        <tbody>
            <tr>
                <td class="pr-meta-label">Entity Name / Office</td>
                <td class="pr-meta-value">
                    @if ($isEditable)
                        <input class="pr-input pr-cell-input" name="department_name" type="text" value="{{ $department }}" data-pr-department-name>
                        @error('department_name')<p class="field-error">{{ $message }}</p>@enderror
                    @else
                        {{ $department }}
                    @endif
                </td>
                <td class="pr-meta-label">Fund Cluster</td>
                <td class="pr-meta-value">
                    @if ($isEditable)
                        <input class="pr-input" name="fund_cluster" type="text" value="{{ $fundCluster }}">
                        @error('fund_cluster')<p class="field-error">{{ $message }}</p>@enderror
                    @else
                        {{ $fundCluster }}
                    @endif
                </td>
                <td class="pr-meta-label">Date</td>
                <td class="pr-meta-value">{{ $prDateDisplay }}</td>
            </tr>
            <tr>
                <td class="pr-meta-label">Office / Section</td>
                <td class="pr-meta-value">
                    @if ($isEditable)
                        <input class="pr-input" name="section" type="text" value="{{ $sectionValue }}">
                        @error('section')<p class="field-error">{{ $message }}</p>@enderror
                    @else
                        {{ $sectionValue }}
                    @endif
                </td>
                <td class="pr-meta-label">PR No.</td>
                <td class="pr-meta-value">
                    @if ($isEditable)
                        <input class="pr-input" type="text" value="{{ $document->pr_no ?? 'To be assigned' }}" readonly>
                    @else
                        {{ $document->pr_no ?? '' }}
                    @endif
                </td>
                <td class="pr-meta-label">Responsibility Center Code</td>
                <td class="pr-meta-value">
                    @if ($isEditable)
                        <input class="pr-input" name="responsibility_center" type="text" value="{{ $responsibilityCenter }}">
                        @error('responsibility_center')<p class="field-error">{{ $message }}</p>@enderror
                    @else
                        {{ $responsibilityCenter }}
                    @endif
                </td>
            </tr>
            <tr>
                <td class="pr-meta-label">SAI No.</td>
                <td class="pr-meta-value">{{ $document->sai_no ?? '' }}</td>
                <td class="pr-meta-label">ALOBS No.</td>
                <td class="pr-meta-value">{{ $document->alobs_no ?? '' }}</td>
                <td class="pr-meta-label">Date</td>
                <td class="pr-meta-value">{{ $document->alobs_date?->format('M d, Y') ?? $document->sai_date?->format('M d, Y') ?? '' }}</td>
            </tr>
        </tbody>
    </table>

    <table class="pr-table pr-items-table">
        <colgroup>
            <col style="width: 13%">
            <col style="width: 12%">
            <col style="width: 39%">
            <col style="width: 10%">
            <col style="width: 12%">
            <col style="width: 14%">
        </colgroup>
        <thead>
            <tr>
                <th>Stock / Property No.</th>
                <th>Unit</th>
                <th>Item Description</th>
                <th>Quantity</th>
                <th>Unit Cost</th>
                <th>Total Cost</th>
            </tr>
        </thead>
        <tbody @if ($isEditable) data-pr-items @endif>
            @foreach ($formItems as $index => $item)
                @php
                    $itemData = is_array($item) ? (object) $item : $item;
                    $quantity = $isEditable
                        ? old("items.$index.quantity", $itemData?->quantity ?? '')
                        : ($itemData?->quantity ?? '');
                    $unitCost = $isEditable
                        ? old("items.$index.estimated_unit_cost", $itemData?->estimated_unit_cost ?? '')
                        : ($itemData?->estimated_unit_cost ?? '');
                    $rowTotal = (float) ($itemData?->estimated_cost ?? $itemData?->estimated_total_cost ?? (((float) $quantity) * ((float) $unitCost)));
                    $unit = $isEditable
                        ? old("items.$index.unit_of_issue", $itemData?->unit_of_issue ?? $itemData?->unit ?? '')
                        : ($itemData?->unit_of_issue ?? $itemData?->unit ?? '');
                    $description = $isEditable
                        ? old("items.$index.description", $itemData?->description ?? $itemData?->item_description ?? '')
                        : ($itemData?->description ?? $itemData?->item_description ?? '');
                    $stockNo = $isEditable
                        ? old("items.$index.stock_no", $itemData?->stock_no ?? '')
                        : ($itemData?->stock_no ?? '');
                    $rowAppItemId = $isEditable
                        ? old("items.$index.app_item_id", $itemData?->app_item_id ?? '')
                        : ($itemData?->app_item_id ?? '');
                    $rowPpmpItemId = $isEditable
                        ? old("items.$index.ppmp_item_id", $itemData?->ppmp_item_id ?? '')
                        : ($itemData?->ppmp_item_id ?? '');
                    $isBlankRow = blank($quantity) && blank($unit) && blank($description) && blank($stockNo) && blank($unitCost);
                @endphp
                <tr class="pr-item-row {{ $isBlankRow ? 'pr-empty-sheet-row' : '' }}">
                    @if ($isEditable)
                        <td>
                            <input type="hidden" name="items[{{ $index }}][app_item_id]" value="{{ $rowAppItemId }}" data-field="app_item_id" data-pr-row-app-item>
                            <input type="hidden" name="items[{{ $index }}][ppmp_item_id]" value="{{ $rowPpmpItemId }}" data-field="ppmp_item_id" data-pr-row-ppmp-supply>
                            <input class="pr-input pr-excel-cell" name="items[{{ $index }}][stock_no]" type="text" value="{{ $stockNo }}" data-field="stock_no" data-row="{{ $index }}" data-col="0">
                        </td>
                        <td><input class="pr-input pr-excel-cell" name="items[{{ $index }}][unit_of_issue]" type="text" value="{{ $unit }}" data-field="unit_of_issue" data-row="{{ $index }}" data-col="1">@error("items.$index.unit_of_issue")<p class="field-error">{{ $message }}</p>@enderror</td>
                        <td><textarea class="pr-textarea pr-excel-cell" name="items[{{ $index }}][description]" rows="2" data-field="description" data-row="{{ $index }}" data-col="2">{{ $description }}</textarea>@error("items.$index.description")<p class="field-error">{{ $message }}</p>@enderror</td>
                        <td><input class="pr-input pr-number-input pr-excel-cell" name="items[{{ $index }}][quantity]" type="number" min="0" step="0.01" value="{{ $quantity }}" data-field="quantity" data-row="{{ $index }}" data-col="3" data-quantity>@error("items.$index.quantity")<p class="field-error">{{ $message }}</p>@enderror</td>
                        <td><input class="pr-input pr-number-input pr-excel-cell" name="items[{{ $index }}][estimated_unit_cost]" type="number" min="0" step="0.01" value="{{ $unitCost }}" data-field="estimated_unit_cost" data-row="{{ $index }}" data-col="4" data-unit-cost></td>
                        <td><input class="pr-input pr-number-input pr-row-total" name="items[{{ $index }}][estimated_cost]" type="text" value="{{ $rowTotal > 0 ? number_format($rowTotal, 2, '.', '') : '' }}" data-field="estimated_cost" data-row="{{ $index }}" data-row-total readonly tabindex="-1"></td>
                    @elseif ($isPrint)
                        <td><div class="pr-cell-text">{{ $stockNo }}</div></td>
                        <td><div class="pr-cell-text">{{ $unit }}</div></td>
                        <td><div class="pr-cell-text multiline">{{ $description }}</div></td>
                        <td class="pr-number-cell"><div class="pr-cell-text">{{ filled($quantity) ? number_format((float) $quantity, 2, '.', '') : '' }}</div></td>
                        <td class="pr-number-cell"><div class="pr-cell-text">{{ filled($unitCost) ? number_format((float) $unitCost, 2, '.', '') : '' }}</div></td>
                        <td class="pr-number-cell"><div class="pr-cell-text">{{ $rowTotal > 0 ? number_format($rowTotal, 2, '.', '') : '' }}</div></td>
                    @else
                        <td><div class="pr-cell-text">{{ $stockNo }}</div></td>
                        <td><div class="pr-cell-text">{{ $unit }}</div></td>
                        <td><div class="pr-cell-text multiline">{{ $description }}</div></td>
                        <td class="pr-number-cell"><div class="pr-cell-text">{{ filled($quantity) ? number_format((float) $quantity, 2, '.', '') : '' }}</div></td>
                        <td class="pr-number-cell"><div class="pr-cell-text">{{ filled($unitCost) ? number_format((float) $unitCost, 2, '.', '') : '' }}</div></td>
                        <td class="pr-number-cell"><div class="pr-cell-text">{{ $rowTotal > 0 ? number_format($rowTotal, 2, '.', '') : '' }}</div></td>
                    @endif
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" class="pr-total-label">TOTAL ESTIMATED COST</td>
                <td class="pr-total-value">PHP <b @if ($isEditable) data-pr-total @endif>{{ $isEditable ? '0.00' : number_format($displayTotal, 2) }}</b></td>
            </tr>
        </tfoot>
    </table>

    @if ($isEditable)
        @error('items')<p class="field-error pr-sheet-error">{{ $message }}</p>@enderror
    @endif

    @include('head-office.pr._lower-section', ['mode' => $isEditable ? 'edit' : 'readonly'])
</div>
