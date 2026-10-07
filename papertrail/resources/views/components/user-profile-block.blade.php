@props([
    'user' => auth()->user(),
    'href' => null,
    'avatarSize' => 'sm',
])

@php
    $officeName = trim((string) ($user?->assignedOffice?->name ?? $user?->office ?? ''));
    $roleName = trim((string) ($user?->assignedRole?->name ?? $user?->role ?? ''));
    $roleCode = trim((string) ($user?->assignedRole?->code ?? ''));
    $roleDisplayLabel = function (string $value): string {
        $normalized = strtolower(trim(preg_replace('/[\s_\-]+/', ' ', $value) ?? $value));

        return $normalized === 'bac chair' ? 'BAC Chairman' : $value;
    };
    $isHeadOfficeEndUser = collect([$roleCode, $roleName, $user?->role])
        ->filter()
        ->contains(fn ($value) => in_array(strtolower(trim((string) $value)), [
            'head_office',
            'head of office / end user',
        ], true));
    $displayRoleName = $isHeadOfficeEndUser ? '' : $roleDisplayLabel($roleName);
    $displayOfficeName = $isHeadOfficeEndUser ? $officeName : '';
    if ($displayOfficeName !== '' && $displayRoleName !== '' && strcasecmp($displayOfficeName, $displayRoleName) === 0) {
        $displayOfficeName = '';
    }
    $displayName = trim((string) ($user?->name ?? '')) ?: ($officeName ?: ($roleName ?: $user?->user_id));
    $profileLabel = collect([$displayName, $displayRoleName, $displayOfficeName])
        ->filter()
        ->unique(fn ($value) => strtolower(trim((string) $value)))
        ->implode(', ');
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class(['pt-user-profile-block']) }}
    aria-label="Signed in as {{ $profileLabel }}"
    title="{{ $profileLabel }}"
>
    <x-user-avatar :user="$user" :size="$avatarSize" />
    <span class="pt-user-profile-block__copy">
        <strong>{{ $displayName }}</strong>
        @if ($displayRoleName !== '')
            <span class="pt-user-profile-block__role">{{ $displayRoleName }}</span>
        @endif
        @if ($displayOfficeName !== '')
            <span class="pt-user-profile-block__office" title="{{ $displayOfficeName }}">{{ $displayOfficeName }}</span>
        @endif
    </span>
</{{ $tag }}>
