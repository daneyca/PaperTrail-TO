@props([
    'drafts' => collect(),
    'title' => 'Recent Drafts',
    'documentTypeLabel' => 'Document',
    'sourceTitle' => 'Select Source',
    'sourceEyebrow' => 'Source',
    'maxItems' => 5,
])

@php
    $draftCollection = collect($drafts)->values();
    $visibleDrafts = $draftCollection->take(max(1, (int) $maxItems));
@endphp

<div {{ $attributes->merge(['class' => 'document-create-split-layout']) }}>
    <aside class="document-create-recent-pane" aria-labelledby="{{ \Illuminate\Support\Str::slug($title) }}-title">
        <header class="document-create-split-heading">
            <div>
                <p class="eyebrow">Recent Drafts</p>
                <h3 id="{{ \Illuminate\Support\Str::slug($title) }}-title">{{ $title }}</h3>
            </div>
            <span>{{ $draftCollection->count() }}</span>
        </header>

        <div class="document-create-draft-list">
            @forelse ($visibleDrafts as $draft)
                @php
                    $draftNumber = $draft['number'] ?? 'Draft';
                    $draftStatus = $draft['status_label'] ?? str($draft['status'] ?? 'draft')->replace(['_', '-'], ' ')->title();
                    $draftEditUrl = $draft['edit_url'] ?? null;
                    $draftShowUrl = $draft['show_url'] ?? null;
                @endphp

                <article class="document-create-draft-card document-create-draft-card--compact">
                    <strong class="document-create-draft-number">{{ $draftNumber }}</strong>
                    <span class="document-create-draft-status">{{ $draftStatus }}</span>

                    <div class="document-create-draft-icon-actions">
                        @if ($draftEditUrl)
                            <a href="{{ $draftEditUrl }}" class="document-create-draft-icon-action" aria-label="Edit draft" title="Edit draft">
                                <x-papertrail.icon name="edit" />
                            </a>
                        @endif

                        @if ($draftShowUrl)
                            <a href="{{ $draftShowUrl }}" class="document-create-draft-icon-action" aria-label="View draft" title="View draft">
                                <x-papertrail.icon name="view" />
                            </a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="document-create-drafts-empty">
                    <strong>No draft {{ \Illuminate\Support\Str::plural($documentTypeLabel) }} yet.</strong>
                </div>
            @endforelse
        </div>
    </aside>

    <section class="document-create-picker-pane" aria-labelledby="{{ \Illuminate\Support\Str::slug($sourceTitle) }}-title">
        <header class="document-create-split-heading">
            <div>
                <p class="eyebrow">{{ $sourceEyebrow }}</p>
                <h3 id="{{ \Illuminate\Support\Str::slug($sourceTitle) }}-title">{{ $sourceTitle }}</h3>
            </div>
        </header>

        <div class="document-create-picker-content">
            {{ $slot }}
        </div>
    </section>
</div>
