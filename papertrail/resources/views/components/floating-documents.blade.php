@props([
    'variant' => 'public',
])

<div {{ $attributes->class(['pt-floating-documents', 'pt-floating-documents--' . $variant]) }} aria-hidden="true">
    <span class="pt-float-doc pt-float-doc--pdf">PDF</span>
    <span class="pt-float-doc pt-float-doc--folder"><i></i></span>
    <span class="pt-float-doc pt-float-doc--check"><b></b><b></b><b></b></span>
    <span class="pt-float-doc pt-float-doc--stamp">OK</span>
    <span class="pt-float-doc pt-float-doc--signature">SIGN</span>
    <span class="pt-float-doc pt-float-doc--paper"><b></b><b></b><b></b></span>
</div>
