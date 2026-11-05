@php
    $stamps = [];
    foreach (['head', 'chair'] as $slot) {
        $signature = $signatureSlots->get($slot);
        if ($signature) {
            $stamps[$slot] = [
                'image' => $signature->signature_image_path ? route('e-signatures.image', $signature) : null,
                'text' => $signature->typed_signature_name ?: $signature->signer_name,
            ];
        }
    }
@endphp
<style>@media print { [id^="certification-preview-"] { max-width:none !important; overflow:visible !important; } }</style>
<div id="certification-preview-{{ $document->id }}" style="max-width:100%;overflow:auto">
    {!! $document->document_html !!}
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('certification-preview-{{ $document->id }}');
    Object.entries(@json($stamps)).forEach(([slot, stamp]) => {
        const mark = root?.querySelector(`[data-certification-signature-slot="${slot}"]`);
        if (!mark) return;
        if (stamp.image) {
            const image = document.createElement('img');
            image.src = stamp.image;
            image.alt = `${slot} electronic signature`;
            mark.appendChild(image);
        } else {
            mark.textContent = stamp.text || '';
        }
    });
});
</script>
