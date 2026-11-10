@php
    $fieldValue = $value ?? '';
    $fieldClass = $fieldClass ?? '';
    $numeric = $numeric ?? false;
@endphp
<span class="observer-field {{ $fieldClass }}">
    @if ($editable ?? false)
        @if ($numeric)
            <input type="number" min="0" step="0.01" name="letter[{{ str_replace('.', '][', $path) }}]" data-letter-path="{{ $path }}" data-letter-key="{{ last(explode('.', $path)) }}" value="{{ $fieldValue }}" aria-label="{{ $label }}" placeholder="0.00">
        @else
            <textarea rows="1" name="letter[{{ str_replace('.', '][', $path) }}]" data-letter-path="{{ $path }}" data-letter-key="{{ last(explode('.', $path)) }}" aria-label="{{ $label }}" placeholder="{{ $placeholder ?? 'Enter text' }}">{{ $fieldValue }}</textarea>
        @endif
    @endif
    <span class="observer-field__print">{{ $numeric && filled($fieldValue) ? number_format((float) $fieldValue, 2) : $fieldValue }}</span>
</span>
