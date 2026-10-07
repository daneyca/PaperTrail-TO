@php
    $signature = $signature ?? null;
@endphp

@if ($signature)
    <section class="signed-block" aria-label="Electronic signature details">
        <div class="signed-block__visual">
            @if ($signature->signature_image_path)
                <img src="{{ route('e-signatures.image', $signature) }}" alt="Electronic signature image">
            @else
                <span class="signed-block__typed">{{ $signature->typed_signature_name ?: $signature->signer_name }}</span>
            @endif
        </div>

        <div class="signed-block__details">
            <p>
                <span class="signed-block__label">Electronically Signed By</span>
                <strong>{{ $signature->signer_name ?? 'N/A' }}</strong>
            </p>
            <p>
                <span class="signed-block__label">Role / Position</span>
                <strong>{{ $signature->signer_position ?? $signature->signer_role ?? 'N/A' }}</strong>
            </p>
            <p>
                <span class="signed-block__label">Office</span>
                <strong>{{ $signature->signer_office_name ?? 'N/A' }}</strong>
            </p>
            <p>
                <span class="signed-block__label">Date Signed</span>
                <strong>{{ $signature->signed_at?->format('M d, Y h:i A') ?? 'N/A' }}</strong>
            </p>
            <p>
                <span class="signed-block__label">Signature ID</span>
                <strong>{{ $signature->signature_code ?? 'N/A' }}</strong>
            </p>
            <div class="signed-block__verification">
                Electronically Signed through PaperTrail
            </div>
            <a href="{{ route('e-signatures.certificate', $signature) }}" class="no-print">View Signature Certificate</a>
        </div>
    </section>
@endif
