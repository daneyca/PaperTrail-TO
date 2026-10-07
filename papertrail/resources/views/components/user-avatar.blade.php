@props([
    'user' => auth()->user(),
    'size' => 'md',
])

@php
    $displayName = trim((string) ($user?->name ?? '')) ?: trim((string) ($user?->office ?? $user?->role ?? 'PaperTrail'));
    $initials = collect(explode(' ', $displayName))
        ->filter()
        ->take(2)
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
        ->implode('');
    $profilePhotoPath = trim((string) ($user?->profile_photo_path ?? ''));
    $profilePhotoUrl = null;

    if ($profilePhotoPath !== '') {
        $normalizedPhotoPath = ltrim($profilePhotoPath, '/');

        if (preg_match('/^https?:\/\//i', $profilePhotoPath) || str_starts_with($profilePhotoPath, '/')) {
            $profilePhotoUrl = $profilePhotoPath;
        } elseif (file_exists(public_path('storage/' . $normalizedPhotoPath))) {
            $profilePhotoUrl = asset('storage/' . $normalizedPhotoPath);
        } elseif (file_exists(public_path($normalizedPhotoPath))) {
            $profilePhotoUrl = asset($normalizedPhotoPath);
        }
    }
@endphp

<span {{ $attributes->class(['pt-user-avatar', 'pt-user-avatar--' . $size]) }}>
    @if ($profilePhotoUrl)
        <img src="{{ $profilePhotoUrl }}" alt="">
    @else
        <span class="pt-user-avatar__fallback">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M20 21a8 8 0 0 0-16 0" />
                <circle cx="12" cy="7" r="4" />
            </svg>
            <small>{{ $initials ?: 'PT' }}</small>
        </span>
    @endif
</span>
