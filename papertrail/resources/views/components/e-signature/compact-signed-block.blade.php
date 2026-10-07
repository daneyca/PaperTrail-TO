@php
    $signature = $signature ?? null;
    $metadata = $signature?->metadata ?: [];
    $printedName = $metadata['printed_name'] ?? null;
    $displayName = filled($printedName) ? $printedName : ($signature?->signer_name ?? 'N/A');
    $position = ($metadata['designation'] ?? null)
        ?: $signature?->signer_position
        ?: $signature?->signatory_label
        ?: $signature?->signer_role
        ?: 'Authorized Signatory';
@endphp

@if ($signature)
    <div class="compact-signed-block" aria-label="Electronic signature">
        <div class="compact-signed-block__visual">
            @if ($signature->signature_image_path)
                <img src="{{ route('e-signatures.image', $signature) }}" alt="Electronic signature">
            @else
                <span class="compact-signed-block__typed">{{ $signature->typed_signature_name ?: $displayName }}</span>
            @endif
        </div>

        <div class="compact-signed-block__name">{{ $displayName }}</div>
        <div class="compact-signed-block__position">{{ $position }}</div>
    </div>
@endif
