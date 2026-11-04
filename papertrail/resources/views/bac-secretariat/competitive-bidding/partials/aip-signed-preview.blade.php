@php
    $stamps = [];
    foreach (\App\Services\AnnualInvestmentProgramService::SLOTS as $slot => $label) {
        $signature = $signatureSlots->get($slot);
        if ($signature) {
            $stamps[$slot] = [
                'image' => $signature->signature_image_path ? route('e-signatures.image', $signature) : null,
                'text' => $signature->typed_signature_name ?: $signature->signer_name,
                'date' => $signature->signed_at?->format('M d, Y'),
            ];
        }
    }
@endphp
<style>
@media print {
    html, body { width:auto !important; min-width:0 !important; }
    [id^="aip-signed-preview-"], [id^="aip-signed-preview-"] .aip-snapshot,
    [id^="aip-signed-preview-"] .aip-snapshot * { visibility:visible !important; }
    [id^="aip-signed-preview-"] { display:block !important; width:100% !important; max-width:none !important; overflow:visible !important; margin:0 !important; padding:0 !important; }
    [id^="aip-signed-preview-"] .aip-snapshot { width:285mm !important; min-height:0 !important; margin:0 !important; padding:0 !important; }
}
</style>
<div id="aip-signed-preview-{{ $document->id }}" style="max-width:100%;overflow:auto">
    {!! $document->document_html !!}
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('aip-signed-preview-{{ $document->id }}');
    const stamps = @json($stamps);
    if (!root) return;

    Object.entries(stamps).forEach(([slot, stamp]) => {
        const signatureLine = root.querySelector(`[data-aip-signature-slot="${slot}"]`);
        if (!signatureLine) return;

        if (stamp.image) {
            const image = document.createElement('img');
            image.src = stamp.image;
            image.alt = `${slot} electronic signature`;
            signatureLine.appendChild(image);
        } else {
            signatureLine.textContent = stamp.text || '';
        }

        const dateLine = signatureLine.closest('section')?.querySelector('.aip-snapshot-date');
        if (dateLine && stamp.date) dateLine.textContent = `Date: ${stamp.date}`;
    });
});
</script>
