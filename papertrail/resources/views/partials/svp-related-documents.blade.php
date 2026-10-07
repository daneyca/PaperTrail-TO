@php
    $source = $source ?? null;
    $context = $context ?? auth()->user()?->roleSlug();
    $context = match ($context) {
        'head-office' => 'head-office',
        'bac-secretariat' => 'bac-secretariat',
        default => 'head-office',
    };
    $chain = $chain ?? app(\App\Services\SvpChainService::class)->findBySource($source);
    $cards = $chain ? app(\App\Services\SvpChainService::class)->documentCards($chain, $context) : [];
@endphp

@if ($chain)
    <section class="table-panel svp-related-panel no-print" aria-label="Related SVP documents">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">SVP Tracking</p>
                <h2>Related SVP Documents</h2>
                <p>{{ $chain->chain_number ?? 'SVP Chain #'.$chain->id }}</p>
            </div>
            @if ($context === 'head-office' && Route::has('head-office.svp-tracking.show'))
                <a href="{{ route('head-office.svp-tracking.show', $chain) }}" class="dashboard-action secondary-action">View Timeline</a>
            @elseif ($context === 'bac-secretariat' && Route::has('bac-secretariat.svp-monitoring.show'))
                <a href="{{ route('bac-secretariat.svp-monitoring.show', $chain) }}" class="dashboard-action secondary-action">View Timeline</a>
            @endif
        </div>

        <div class="svp-related-list">
            @foreach ($cards as $card)
                <article>
                    <span class="svp-related-status {{ $card['document'] ? 'is-linked' : 'is-pending' }}"></span>
                    <div>
                        <strong>{{ $card['type'] }}</strong>
                        <p>{{ $card['number'] ?? 'Pending' }}</p>
                    </div>
                    @if ($card['view_url'])
                        <a href="{{ $card['view_url'] }}">View</a>
                    @else
                        <span>{{ $card['document'] ? 'Linked' : 'Pending' }}</span>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@endif
