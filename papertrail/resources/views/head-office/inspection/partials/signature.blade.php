@if ($signature)
    <div class="iar-electronic-signature" aria-label="Electronic signature by {{ $signature->signer_name }}">
        @if ($signature->signature_image_path)
            <img src="{{ route('e-signatures.image', $signature) }}" alt="Electronic signature by {{ $signature->signer_name }}">
        @else
            <span class="iar-signature-typed">{{ $signature->typed_signature_name ?: $signature->signer_name }}</span>
        @endif
        <small>{{ $signature->signature_code }} · {{ $signature->signed_at?->format('m/d/Y h:i A') }}</small>
    </div>
@elseif ($record->signature_revision)
    <div class="iar-electronic-signature" aria-hidden="true"></div>
@endif
