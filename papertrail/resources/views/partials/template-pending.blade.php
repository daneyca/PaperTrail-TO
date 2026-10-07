@php
    $eyebrow = $eyebrow ?? 'Template Pending';
    $title = $title ?? 'PPMP / APP Template Pending';
    $message = $message ?? 'The official PPMP/APP format has not yet been configured. This module will be finalized once the approved LGU form is available.';
    $backRoute = $backRoute ?? 'dashboard';
    $relatedRoute = $relatedRoute ?? null;
    $relatedLabel = $relatedLabel ?? 'View Related Procurement Documents';
    $note = $note ?? 'PaperTrail will continue the SVP workflow using the available official documents while this template is pending.';
@endphp

<section class="dashboard-hero admin-users-hero template-pending-hero">
    <div>
        <p class="eyebrow">{{ $eyebrow }}</p>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
    </div>
</section>

<section class="table-panel template-pending-panel" aria-label="{{ $title }}">
    <div class="template-pending-content">
        <span class="template-pending-badge">For Configuration</span>
        <h2>{{ $title }}</h2>
        <p>{{ $message }}</p>
        <p class="template-pending-note">{{ $note }}</p>

        <div class="template-pending-actions">
            @if ($backRoute && Route::has($backRoute))
                <a href="{{ route($backRoute) }}" class="dashboard-action">Back to Dashboard</a>
            @endif

            @if ($relatedRoute && Route::has($relatedRoute))
                <a href="{{ route($relatedRoute) }}" class="dashboard-action secondary-action">{{ $relatedLabel }}</a>
            @endif
        </div>
    </div>
</section>
