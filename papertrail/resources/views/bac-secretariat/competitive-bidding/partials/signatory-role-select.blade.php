@php
    $internalSignatoryRoleOptions = [
        'bac_secretariat' => 'BAC Secretariat',
        'twg' => 'Technical Working Group',
        'bac_chairperson' => 'BAC Chairperson',
        'bac_vice_chairperson' => 'BAC Vice-Chairperson',
        'bac_member' => 'BAC Member',
        'hope' => 'Municipal Mayor / HOPE',
    ];
    $supplierSignatoryRoleOption = [
        'supplier' => 'Supplier / Contractor',
    ];

    $onlySupplier = (bool) ($onlySupplier ?? false);
    $includeSupplier = (bool) ($includeSupplier ?? false);

    $signatoryRoleOptions = match (true) {
        $onlySupplier => $supplierSignatoryRoleOption,
        $includeSupplier => $internalSignatoryRoleOptions + $supplierSignatoryRoleOption,
        default => $internalSignatoryRoleOptions,
    };

    $selectedRole = $selected ?? 'bac_secretariat';

    if (! array_key_exists($selectedRole, $signatoryRoleOptions)) {
        $selectedRole = array_key_first($signatoryRoleOptions);
    }
@endphp

<span class="cb-signatory-role-print" data-cb-signatory-role-print>{{ $signatoryRoleOptions[$selectedRole] ?? 'Select role' }}</span>
<select class="cb-signatory-role-select no-print" data-cb-signatory-role aria-label="Signatory role">
    @foreach ($signatoryRoleOptions as $roleKey => $roleLabel)
        <option value="{{ $roleKey }}" @selected($selectedRole === $roleKey)>{{ $roleLabel }}</option>
    @endforeach
</select>
