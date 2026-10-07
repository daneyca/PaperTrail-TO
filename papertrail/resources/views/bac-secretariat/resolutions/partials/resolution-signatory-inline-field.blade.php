@php
    $details = $details ?? [];
    $roleOptions = collect($roleOptions ?? [$defaultDesignation ?? ''])
        ->map(fn ($option) => trim((string) $option))
        ->filter()
        ->unique()
        ->values();
    $defaultDesignation = $defaultDesignation ?? $roleOptions->first() ?? '';
    $currentName = old("{$oldPrefix}.name", $details['name'] ?? '');
    $designationWasManuallySelected = filter_var(old("{$oldPrefix}.designation_manually_selected", $details['designation_manually_selected'] ?? false), FILTER_VALIDATE_BOOLEAN);
    $currentDesignation = $designationWasManuallySelected
        ? old("{$oldPrefix}.designation", $details['designation'] ?? '')
        : '';
    $selectedAccount = old("{$oldPrefix}.account_code", $details['account_code'] ?? '');
    $lineClass = $lineClass ?? 'resolution-sign-line';
    $nameClass = $nameClass ?? 'resolution-sign-name';
    $designationClass = $designationClass ?? 'resolution-sign-title';
    $normalizeDesignation = fn ($value) => trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', strtolower(str_replace(['-', ',', '/', '\\'], ' ', (string) $value))) ?: '') ?: '');

    if ($currentDesignation && ! $roleOptions->contains(fn ($option) => $normalizeDesignation($option) === $normalizeDesignation($currentDesignation))) {
        $roleOptions->push($currentDesignation);
    }
@endphp

@if ($signature ?? null)
    @include('components.e-signature.compact-signed-block', ['signature' => $signature])
@elseif ($canEditSignatureFields ?? false)
    <div
        class="resolution-inline-signatory-control"
        data-resolution-signatory-row
        data-slot="{{ $slot }}"
        data-default-designation="{{ $defaultDesignation }}"
        contenteditable="false"
    >
        <input type="hidden" name="{{ $fieldPrefix }}[account_code]" value="{{ $selectedAccount }}" data-signer-account-code>
        <input type="hidden" name="{{ $fieldPrefix }}[designation_manually_selected]" value="{{ $currentDesignation !== '' ? '1' : '0' }}" data-signer-designation-manual>
        @if ($includeDate ?? false)
            <input type="hidden" name="{{ $fieldPrefix }}[date]" value="{{ old("{$oldPrefix}.date", $details['date'] ?? '') }}">
        @endif

        <div class="{{ $lineClass }}" aria-hidden="true"></div>
        <input
            type="text"
            class="{{ $nameClass }} resolution-sign-name-field"
            name="{{ $fieldPrefix }}[name]"
            value="{{ $currentName }}"
            placeholder="Enter name"
            data-signer-name
            data-signatory-name
        >
        <select
            class="{{ $designationClass }} resolution-sign-designation-field"
            name="{{ $fieldPrefix }}[designation]"
            data-signer-designation
            data-signatory-designation
            data-manually-selected="{{ $currentDesignation !== '' ? 'true' : 'false' }}"
        >
            <option value="" @selected($currentDesignation === '')>-- Select role --</option>
            @foreach ($roleOptions as $roleOption)
                <option value="{{ $roleOption }}" @selected($normalizeDesignation($roleOption) === $normalizeDesignation($currentDesignation))>
                    {{ $roleOption }}
                </option>
            @endforeach
        </select>
    </div>
@else
    <div class="{{ $lineClass }}"></div>
    <div class="{{ $nameClass }}" data-signatory-name>{{ $plain($currentName ?? '') }}</div>
    <div class="{{ $designationClass }}" data-signatory-designation>{{ $plain($currentDesignation ?? null) }}</div>
@endif
