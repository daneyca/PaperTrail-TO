@php
    $value = fn (string $key, string $fallback = '') => data_get($item, $key, $fallback);
@endphp

<tr>
    <td>
        <select name="items[{{ $index }}][category]" data-category-field required>
            @foreach ($options['categories'] as $optionValue => $label)
                <option value="{{ $optionValue }}" @selected($value('category', 'general_requirements') === $optionValue)>{{ $label }}</option>
            @endforeach
        </select>
        @error("items.$index.category")<p class="field-error">{{ $message }}</p>@enderror
    </td>
    <td>
        <textarea name="items[{{ $index }}][project_title]" rows="2" required>{{ $value('project_title') }}</textarea>
        @error("items.$index.project_title")<p class="field-error">{{ $message }}</p>@enderror
    </td>
    <td>
        <input name="items[{{ $index }}][end_user_unit]" type="text" value="{{ $value('end_user_unit') }}" required>
        @error("items.$index.end_user_unit")<p class="field-error">{{ $message }}</p>@enderror
    </td>
    <td>
        <textarea name="items[{{ $index }}][general_description]" rows="2">{{ $value('general_description') }}</textarea>
        @error("items.$index.general_description")<p class="field-error">{{ $message }}</p>@enderror
    </td>
    <td>
        <select name="items[{{ $index }}][mode_of_procurement]" required>
            @foreach ($options['modes'] as $mode)
                <option value="{{ $mode }}" @selected($value('mode_of_procurement', 'Small Value Procurement') === $mode)>{{ $mode }}</option>
            @endforeach
        </select>
        @error("items.$index.mode_of_procurement")<p class="field-error">{{ $message }}</p>@enderror
    </td>
    <td>
        <select name="items[{{ $index }}][early_procurement_activity]" data-early-field required>
            @foreach ($options['earlyProcurementOptions'] as $option)
                <option value="{{ $option }}" @selected($value('early_procurement_activity', 'No') === $option)>{{ $option }}</option>
            @endforeach
        </select>
        @error("items.$index.early_procurement_activity")<p class="field-error">{{ $message }}</p>@enderror
    </td>
    <td>
        <textarea name="items[{{ $index }}][bid_evaluation_criteria]" rows="2">{{ $value('bid_evaluation_criteria') }}</textarea>
        @error("items.$index.bid_evaluation_criteria")<p class="field-error">{{ $message }}</p>@enderror
    </td>
    <td>
        <input name="items[{{ $index }}][start_procurement_activity]" type="text" value="{{ $value('start_procurement_activity') }}" placeholder="MM/YYYY" required>
        @error("items.$index.start_procurement_activity")<p class="field-error">{{ $message }}</p>@enderror
    </td>
    <td>
        <input name="items[{{ $index }}][end_procurement_activity]" type="text" value="{{ $value('end_procurement_activity') }}" placeholder="MM/YYYY" required>
        @error("items.$index.end_procurement_activity")<p class="field-error">{{ $message }}</p>@enderror
    </td>
    <td>
        <select name="items[{{ $index }}][source_of_funds]" required>
            @foreach ($options['fundSources'] as $source)
                <option value="{{ $source }}" @selected($value('source_of_funds', 'General Fund') === $source)>{{ $source }}</option>
            @endforeach
        </select>
        @error("items.$index.source_of_funds")<p class="field-error">{{ $message }}</p>@enderror
    </td>
    <td>
        <input name="items[{{ $index }}][estimated_budget]" type="number" step="0.01" min="0" value="{{ $value('estimated_budget') }}" data-budget-field required>
        @error("items.$index.estimated_budget")<p class="field-error">{{ $message }}</p>@enderror
    </td>
    <td>
        <textarea name="items[{{ $index }}][procurement_strategy_or_tools]" rows="2">{{ $value('procurement_strategy_or_tools') }}</textarea>
        @error("items.$index.procurement_strategy_or_tools")<p class="field-error">{{ $message }}</p>@enderror
    </td>
    <td>
        <textarea name="items[{{ $index }}][remarks]" rows="2">{{ $value('remarks') }}</textarea>
        @error("items.$index.remarks")<p class="field-error">{{ $message }}</p>@enderror
    </td>
    <td>
        <button type="button" data-remove-app-row>Remove</button>
    </td>
</tr>
