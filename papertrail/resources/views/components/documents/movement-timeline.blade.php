@props([
    'document',
    'histories' => null,
])

@php
    $histories = collect($histories ?? $document->routingHistories ?? [])
        ->sortBy(fn ($history) => $history->action_at ?? $history->created_at)
        ->values();
    $latestHistory = $histories->last();
    $currentLocation = $document->currentOffice?->name
        ?? $latestHistory?->toOffice?->name
        ?? $document->submittingOffice?->name
        ?? 'Not assigned';
    $currentHandler = $document->assignedTo?->name ?? null;
    $currentStage = $document->stage ?? 'N/A';
    $formatDate = function ($date): ?string {
        if (! $date) {
            return null;
        }

        if ($date instanceof \Carbon\CarbonInterface) {
            return $date->format('M d, Y h:i A');
        }

        return \Illuminate\Support\Carbon::parse($date)->format('M d, Y h:i A');
    };
    $labelize = fn ($value): string => \Illuminate\Support\Str::of((string) ($value ?: 'N/A'))->replace('_', ' ')->title()->replace('Ppmp', 'PPMP')->toString();
    $containsReturn = fn ($value): bool => \Illuminate\Support\Str::contains(\Illuminate\Support\Str::lower((string) $value), ['return', 'correction']);

    $branches = collect();

    foreach ($histories as $history) {
        $officeName = $history->toOffice?->name ?? $history->fromOffice?->name ?? 'Recorded Movement';
        $lastIndex = $branches->keys()->last();
        $lastBranch = $lastIndex !== null ? $branches->get($lastIndex) : null;

        if ($lastBranch && $lastBranch['office'] === $officeName) {
            $lastBranch['items']->push($history);
            $branches->put($lastIndex, $lastBranch);

            continue;
        }

        $branches->push([
            'office' => $officeName,
            'items' => collect([$history]),
        ]);
    }

    $nodeState = function ($items) use ($latestHistory, $containsReturn): string {
        $items = collect($items);

        if ($items->contains(fn ($history) => $containsReturn(($history->action ?? '').' '.($history->status_to ?? '').' '.($history->comments ?? '')))) {
            return 'returned';
        }

        if ($latestHistory && $items->contains(fn ($history) => $history->is($latestHistory))) {
            return 'current';
        }

        return 'completed';
    };

    $stateLabel = fn (string $state): string => match ($state) {
        'current' => 'Current',
        'returned' => 'Returned',
        'completed' => 'Done',
        default => 'Waiting',
    };
@endphp

<div {{ $attributes->merge(['class' => 'document-movement']) }}>
    <section class="document-movement-current" aria-label="Current document location">
        <div>
            <span>Current Location</span>
            <strong>{{ $currentLocation }}</strong>
        </div>
        <div>
            <span>Current Stage</span>
            <strong>{{ $currentStage }}</strong>
        </div>
        <div>
            <span>Current Handler</span>
            <strong>{{ $currentHandler ?? 'Not assigned' }}</strong>
        </div>
        <div>
            <span>Last Updated</span>
            <strong>{{ $formatDate($latestHistory?->action_at ?? $document->updated_at) ?? 'No timestamp' }}</strong>
        </div>
    </section>

    <section class="document-movement-tree-panel" aria-label="Hierarchical document movement">
        <ul class="document-movement-tree">
            <li class="movement-tree-node movement-tree-node--root is-current">
                <details open>
                    <summary class="movement-tree-row">
                        <span class="movement-tree-toggle" aria-hidden="true"></span>
                        <span class="movement-tree-state" aria-hidden="true"></span>
                        <span class="movement-tree-icon movement-tree-icon--document" aria-hidden="true"></span>
                        <span class="movement-tree-text">
                            <strong>{{ $document->displayNumber() }}</strong>
                            <small>{{ $document->document_type ?? 'Document' }} &middot; {{ $labelize($document->status) }}</small>
                        </span>
                        <span class="movement-tree-badge">Tracking</span>
                    </summary>

                    <ul>
                        @forelse ($branches as $branch)
                            @php
                                $officeName = $branch['office'];
                                $items = collect($branch['items'])->values();
                                $officeState = $nodeState($items);
                            @endphp

                            <li class="movement-tree-node movement-tree-node--office is-{{ $officeState }}">
                                <details open>
                                    <summary class="movement-tree-row">
                                        <span class="movement-tree-toggle" aria-hidden="true"></span>
                                        <span class="movement-tree-state" aria-hidden="true"></span>
                                        <span class="movement-tree-icon movement-tree-icon--office" aria-hidden="true"></span>
                                        <span class="movement-tree-text">
                                            <strong>{{ $officeName }}</strong>
                                            <small>{{ $items->count() }} recorded {{ \Illuminate\Support\Str::plural('movement', $items->count()) }}</small>
                                        </span>
                                        <span class="movement-tree-badge">{{ $stateLabel($officeState) }}</span>
                                    </summary>

                                    <ul>
                                        @foreach ($items as $history)
                                            @php
                                                $isLatest = $latestHistory && $history->is($latestHistory);
                                                $isReturned = $containsReturn(($history->action ?? '').' '.($history->status_to ?? '').' '.($history->comments ?? ''));
                                                $state = $isReturned ? 'returned' : ($isLatest ? 'current' : 'completed');
                                            @endphp

                                            <li class="movement-tree-node movement-tree-node--action is-{{ $state }}">
                                                <div class="movement-tree-row movement-tree-row--leaf">
                                                    <span class="movement-tree-spacer" aria-hidden="true"></span>
                                                    <span class="movement-tree-state" aria-hidden="true"></span>
                                                    <span class="movement-tree-icon movement-tree-icon--action" aria-hidden="true"></span>
                                                    <span class="movement-tree-text">
                                                        <strong>{{ $history->action ?? 'Document movement recorded' }}</strong>
                                                        <small>
                                                            {{ $formatDate($history->action_at) ?? 'No timestamp' }}
                                                            &middot;
                                                            {{ $history->actionBy?->name ?? 'System' }}
                                                        </small>
                                                        <em>{{ $history->fromOffice?->name ?? 'N/A' }} to {{ $history->toOffice?->name ?? 'N/A' }}</em>
                                                        <em>{{ $labelize($history->status_from ?? 'new') }} to {{ $labelize($history->status_to) }}</em>
                                                        @if ($history->comments)
                                                            <em class="movement-tree-comment">{{ $history->comments }}</em>
                                                        @endif
                                                    </span>
                                                    <span class="movement-tree-badge">{{ $stateLabel($state) }}</span>
                                                </div>
                                            </li>
                                        @endforeach

                                        @if ($officeName === $currentLocation && $currentHandler)
                                            <li class="movement-tree-node movement-tree-node--user is-current">
                                                <div class="movement-tree-row movement-tree-row--leaf">
                                                    <span class="movement-tree-spacer" aria-hidden="true"></span>
                                                    <span class="movement-tree-state" aria-hidden="true"></span>
                                                    <span class="movement-tree-icon movement-tree-icon--user" aria-hidden="true"></span>
                                                    <span class="movement-tree-text">
                                                        <strong>{{ $currentHandler }}</strong>
                                                        <small>Current handler</small>
                                                    </span>
                                                    <span class="movement-tree-badge">Current</span>
                                                </div>
                                            </li>
                                        @endif
                                    </ul>
                                </details>
                            </li>
                        @empty
                            <li class="movement-tree-node movement-tree-node--office is-current">
                                <div class="movement-tree-row movement-tree-row--leaf">
                                    <span class="movement-tree-spacer" aria-hidden="true"></span>
                                    <span class="movement-tree-state" aria-hidden="true"></span>
                                    <span class="movement-tree-icon movement-tree-icon--office" aria-hidden="true"></span>
                                    <span class="movement-tree-text">
                                        <strong>{{ $currentLocation }}</strong>
                                        <small>No routing history yet. Document workflow movement will appear here.</small>
                                    </span>
                                    <span class="movement-tree-badge">Current</span>
                                </div>
                            </li>
                        @endforelse
                    </ul>
                </details>
            </li>
        </ul>
    </section>
</div>
