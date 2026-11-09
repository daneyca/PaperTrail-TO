@php
    $signaturesWithRemarks = collect($signatures ?? [])->filter(fn ($signature) => filled(
        data_get($signature->metadata, 'remarks') ?? $signature->signatureRequest?->remarks
    ));
@endphp

@if ($signaturesWithRemarks->isNotEmpty())
    <section class="dashboard-widget widget-wide signature-remarks-panel no-print" aria-label="Signature remarks">
        <div class="widget-heading">
            <div>
                <p class="eyebrow">E-Signature</p>
                <h2>Signature Remarks</h2>
            </div>
        </div>
        <div class="signature-remarks-list">
            @foreach ($signaturesWithRemarks as $signature)
                <article class="signature-remarks-entry">
                    <header>
                        <strong>{{ $signature->signer_name }}</strong>
                        <span>{{ $signature->signatory_label ?: $signature->signer_position }}</span>
                        <span>{{ $signature->signed_at?->copy()->timezone('Asia/Manila')->format('M d, Y h:i A') }}</span>
                    </header>
                    <p class="signature-remarks-text">{{ data_get($signature->metadata, 'remarks') ?? $signature->signatureRequest?->remarks }}</p>
                </article>
            @endforeach
        </div>
    </section>
@endif
