@php
    $documentCards = $dashboard['documentCards'] ?? [];
    $tabs = $dashboard['tabs'] ?? [];
    $searchAction = $dashboard['searchAction'] ?? null;
    $createAction = $dashboard['createAction'] ?? $primaryActionUrl;
    $officeName = $dashboard['officeName'] ?? 'Your office';
    $statusOptions = [
        '' => 'All Status',
        'drafts' => 'Drafts',
        'in_process' => 'In Process',
        'returned' => 'Returned',
        'approved' => 'Approved / Accepted',
    ];
@endphp

<div class="head-office-doc-dashboard">
    <section class="head-office-doc-hero pt-smooth-enter rounded-2xl border border-slate-200 bg-white p-6 shadow-sm md:p-7" style="--pt-delay: 0ms">
        <div>
            <p class="eyebrow text-blue-600">{{ $heroLabel ?? 'End-User Office Portal' }}</p>
            <h1 class="text-3xl font-extrabold tracking-normal text-slate-950 md:text-4xl">Procurement Document Management</h1>
            <p class="mt-2 max-w-3xl text-base text-slate-600">Prepare PPMPs, create Purchase Requests, and monitor related procurement records for {{ $officeName }}.</p>
        </div>

        <div class="head-office-doc-hero-actions">
            @if ($searchAction)
                <a href="{{ $searchAction }}" class="pt-tw-button-secondary">
                    <x-papertrail.icon name="view" class="h-4 w-4" />
                    View My Documents
                </a>
            @endif

            @if ($createAction)
                <a href="{{ $createAction }}" class="pt-tw-button">
                    <x-papertrail.icon name="ppmp" class="h-4 w-4" />
                    {{ $primaryAction ?? 'Submit PPMP' }}
                </a>
            @endif
        </div>
    </section>

    <section class="document-type-card-grid pt-smooth-enter" style="--pt-delay: 80ms" aria-label="Document type summary">
        @foreach ($documentCards as $card)
            <a class="document-type-card document-type-card-{{ $card['accent'] }} rounded-2xl no-underline" href="{{ $card['href'] ?? '#' }}">
                <span class="document-type-mark">
                    <x-papertrail.icon :name="$card['icon'] ?? strtolower($card['title'])" class="h-6 w-6" />
                </span>
                <span class="document-type-copy">
                    <strong>{{ $card['title'] }}</strong>
                    <small>{{ $card['description'] }}</small>
                </span>
                <span class="document-type-count">
                    <strong>{{ number_format((int) ($card['count'] ?? 0)) }}</strong>
                    <small>{{ (int) ($card['count'] ?? 0) === 1 ? 'document' : 'documents' }}</small>
                </span>
                <span class="document-type-meter" aria-hidden="true"></span>
                <span class="document-type-link">{{ $card['actionLabel'] ?? 'View ' . $card['title'] }}</span>
            </a>
        @endforeach
    </section>

    @if ($searchAction)
        <form method="GET" action="{{ $searchAction }}" class="head-office-doc-filter pt-smooth-enter rounded-2xl border border-slate-200 bg-white p-3 shadow-sm" style="--pt-delay: 120ms">
            <label class="pt-sr-only" for="dashboard-search-documents">Search documents</label>
            <div class="relative">
                <x-papertrail.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input id="dashboard-search-documents" class="pl-10" type="search" name="search" placeholder="Search by document no., title, or office...">
            </div>

            <label class="pt-sr-only" for="dashboard-document-type">Document type</label>
            <select id="dashboard-document-type" name="document_type">
                <option value="">All Head Office Types (PPMP, PR)</option>
                <option value="PPMP">PPMP</option>
                <option value="PR">PR</option>
            </select>

            <label class="pt-sr-only" for="dashboard-document-status">Status</label>
            <select id="dashboard-document-status" name="status">
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            <label class="pt-sr-only" for="dashboard-office-scope">Office scope</label>
            <select id="dashboard-office-scope" disabled>
                <option>{{ $officeName }}</option>
            </select>

            <button type="submit">
                <x-papertrail.icon name="filter" class="h-4 w-4" />
                Filter
            </button>
        </form>
    @endif

    <nav class="document-filter-tabs pt-smooth-enter" style="--pt-delay: 150ms" aria-label="Document type filters">
        @foreach ($tabs as $tab)
            <a href="{{ $tab['href'] ?? '#' }}" class="{{ ! empty($tab['active']) ? 'is-active' : '' }}">
                <span>{{ $tab['label'] }}</span>
                <strong>{{ number_format((int) ($tab['count'] ?? 0)) }}</strong>
            </a>
        @endforeach
    </nav>

    @if (! empty($aiVerification))
        @include('dashboard.partials.ai-verification-quick-view', ['aiVerification' => $aiVerification])
    @endif
</div>
