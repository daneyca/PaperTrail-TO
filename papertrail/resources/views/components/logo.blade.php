@props([
    'showMunicipality' => true,
    'href' => null,
])

@php
    $target = $href ?: url('/');
    $logoPath = 'images/logos/papertrail-logo.jpg';
    $hasLogo = file_exists(public_path($logoPath));
@endphp

<a {{ $attributes->merge(['class' => 'pt-brand-lockup']) }} href="{{ $target }}" aria-label="PaperTrail home">
    <span class="pt-brand-mark {{ $hasLogo ? 'pt-brand-mark--image' : '' }}">
        @if ($hasLogo)
            <img src="{{ asset($logoPath) }}" alt="" aria-hidden="true">
        @else
            PT
        @endif
    </span>
    <span class="pt-brand-copy">
        <strong>PaperTrail</strong>
        @if ($showMunicipality)
            <small>Municipality of Tomas Oppus</small>
        @endif
    </span>
</a>
