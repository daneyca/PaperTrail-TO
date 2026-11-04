@php
    $mayorSignature = $signatureSlots->get('mayor');
    $stamp = $mayorSignature ? [
        'image' => $mayorSignature->signature_image_path ? route('e-signatures.image', $mayorSignature) : null,
        'text' => $mayorSignature->typed_signature_name ?: $mayorSignature->signer_name,
    ] : null;
@endphp
<style>
    .coa-preview-scroll { max-width:100%; overflow:auto; }
    @media print { .coa-preview-scroll { width:100% !important; max-width:none !important; overflow:visible !important; visibility:visible !important; } }
</style>
<div class="coa-preview-scroll" id="coa-preview-{{ $document->id }}">
    {!! $document->document_html !!}
</div>
@if ($stamp)
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const mark = document.querySelector('#coa-preview-{{ $document->id }} [data-coa-signature-slot="mayor"]');
        if (!mark) return;
        const stamp = @json($stamp);
        if (stamp.image) {
            const image = document.createElement('img');
            image.src = stamp.image;
            image.alt = 'Municipal Mayor electronic signature';
            mark.appendChild(image);
        } else {
            mark.textContent = stamp.text || '';
        }
    });
    </script>
@endif
