@props([
    'chain',
    'cards' => [],
    'workflowSteps' => [],
    'context' => 'head-office',
    'canCompletePosting' => false,
])

@php
    $chain->loadMissing([
        'sourcePrDocument.assignedTo',
        'sourcePrDocument.currentOffice',
        'sourcePrDocument.submittingOffice',
        'bacResolution',
        'rfq',
        'abstract',
        'purchaseOrder',
        'inspection',
        'latestPostingRecord',
        'updatedBy',
        'events.performedBy',
        'events.fromOffice',
        'events.toOffice',
    ]);

    $pr = $chain->sourcePrDocument;
    $events = $chain->events ?? collect();
    $steps = collect($workflowSteps ?? [])->values();
    $cards = collect($cards ?? []);

    $formatDate = function ($date): ?string {
        if (! $date) {
            return null;
        }

        if ($date instanceof \Carbon\CarbonInterface) {
            return $date->format('M d, Y h:i A');
        }

        return \Illuminate\Support\Carbon::parse($date)->format('M d, Y h:i A');
    };
    $labelize = fn ($value): string => \Illuminate\Support\Str::of((string) ($value ?: 'N/A'))->replace('_', ' ')->title()->toString();
    $containsReturn = fn ($value): bool => \Illuminate\Support\Str::contains(\Illuminate\Support\Str::lower((string) $value), ['return', 'correction']);

    $currentLocation = $pr?->currentOffice?->name
        ?? $chain->office?->name
        ?? $chain->office_name
        ?? 'Not assigned';
    $currentHandler = $pr?->assignedTo?->name
        ?? $chain->updatedBy?->name
        ?? null;
    $currentStatus = $chain->current_status ?? $pr?->status ?? 'pending';
    $currentStage = $chain->current_stage ?? $pr?->stage ?? \App\Models\SvpProcurementChain::STAGE_PURCHASE_REQUEST;
    $chainNumber = $chain->chain_number ?? 'SVP Chain #'.$chain->id;
    $prNumber = $pr?->displayNumber() ?? $chain->tracking_number ?? 'N/A';
    $lastUpdated = $formatDate($events->last()?->created_at ?? $chain->updated_at);
    $currentIndex = $steps->search(fn ($step) => ($step['state'] ?? null) === 'current');
    $nextPendingStep = $currentIndex !== false
        ? $steps->slice($currentIndex + 1)->firstWhere('state', 'pending')
        : $steps->firstWhere('state', 'pending');
    $nextStep = $nextPendingStep['label'] ?? null;

    $documentForStep = function (string $label) use ($chain) {
        return match ($label) {
            \App\Models\SvpProcurementChain::STAGE_PURCHASE_REQUEST, 'AIP', 'PPMP', 'APP', 'PR Signatories', 'PR Number Assignment' => $chain->sourcePrDocument,
            \App\Models\SvpProcurementChain::STAGE_BAC_RESOLUTION => $chain->bacResolution,
            \App\Models\SvpProcurementChain::STAGE_POSTING, \App\Models\SvpProcurementChain::STAGE_POSTING_COMPLETED => $chain->latestPostingRecord,
            \App\Models\SvpProcurementChain::STAGE_RFQ, \App\Models\SvpProcurementChain::STAGE_SUPPLIER_QUOTATIONS => $chain->rfq,
            \App\Models\SvpProcurementChain::STAGE_ABSTRACT => $chain->abstract,
            \App\Models\SvpProcurementChain::STAGE_PURCHASE_ORDER, \App\Models\SvpProcurementChain::STAGE_DELIVERY => $chain->purchaseOrder,
            \App\Models\SvpProcurementChain::STAGE_INSPECTION => $chain->inspection,
            default => null,
        };
    };

    $dateForDocument = function (?\Illuminate\Database\Eloquent\Model $document) {
        if (! $document) {
            return null;
        }

        foreach ([
            'completed_at',
            'approved_at',
            'submitted_at',
            'posted_at',
            'closed_at',
            'issued_at',
            'accepted_at',
            'pr_no_assigned_at',
            'updated_at',
            'created_at',
        ] as $field) {
            if (! empty($document->{$field})) {
                return $document->{$field};
            }
        }

        return null;
    };

    $eventsForStep = function (string $label) use ($events) {
        $needle = \Illuminate\Support\Str::lower($label);

        return $events->filter(function ($event) use ($label, $needle) {
            if ((string) $event->stage === $label) {
                return true;
            }

            $haystack = \Illuminate\Support\Str::lower(implode(' ', array_filter([
                $event->action,
                $event->stage,
                $event->status,
                $event->remarks,
            ])));

            return $needle !== '' && \Illuminate\Support\Str::contains($haystack, $needle);
        })->values();
    };

    $stateForStep = function (array $step) use ($containsReturn): string {
        $raw = $step['state'] ?? 'pending';
        $statusText = ($step['status'] ?? '').' '.($step['description'] ?? '');

        if ($containsReturn($statusText)) {
            return 'returned';
        }

        return match ($raw) {
            'complete', 'completed' => 'completed',
            'current' => 'current',
            'skipped' => 'skipped',
            default => 'upcoming',
        };
    };

    $iconForState = fn (string $state): string => match ($state) {
        'completed' => '&#10003;',
        'current' => '&#9679;',
        'returned' => '&#8617;',
        'skipped' => '&#8211;',
        default => '&#9675;',
    };

    $stateLabel = fn (string $state): string => match ($state) {
        'completed' => 'Completed',
        'current' => 'Current Stage',
        'returned' => 'Returned for Correction',
        'skipped' => 'Skipped',
        default => 'Waiting',
    };

    $aiTypeMap = [
        'Purchase Request' => 'purchase_request',
        'BAC Resolution' => 'bac_resolution',
        'RFQ' => 'rfq',
        'Abstract' => 'abstract',
        'Purchase Order' => 'purchase_order',
        'Inspection / Acceptance' => 'inspection_acceptance',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'document-journey']) }}>
    <section class="document-journey-header" aria-label="Document tracking summary">
        <div class="document-journey-header__identity">
            <p class="eyebrow">SVP / Alternative Procurement</p>
            <h2>{{ $chainNumber }}</h2>
            <p>{{ $prNumber }} &middot; {{ $chain->office_name ?? $pr?->submittingOffice?->name ?? 'Requesting Office' }}</p>
        </div>

        <div class="document-journey-current">
            <span>Current Location</span>
            <strong>{{ $currentLocation }}</strong>
            <p>{{ $currentHandler ? 'Current Handler: '.$currentHandler : 'Current Handler: Not assigned' }}</p>
        </div>

        <div class="document-journey-facts" aria-label="Current tracking facts">
            <div>
                <span>Status</span>
                <strong>{{ $labelize($currentStatus) }}</strong>
            </div>
            <div>
                <span>Current Stage</span>
                <strong>{{ $currentStage }}</strong>
            </div>
            <div>
                <span>Last Updated</span>
                <strong>{{ $lastUpdated ?? 'No timestamp' }}</strong>
            </div>
            <div>
                <span>Next Step</span>
                <strong>{{ $nextStep ?? 'No next step recorded' }}</strong>
            </div>
        </div>
    </section>

    @if ($canCompletePosting)
        <section class="svp-posting-next-card" aria-label="Posting required">
            <div>
                <p class="eyebrow">Posting Required</p>
                <h3>Open BACSEC-004 Posting</h3>
                <p>Record posting dates, evidence, and completion before this PR returns to the requesting office for RFQ preparation.</p>
            </div>
            <a href="{{ route('bac-secretariat.svp-posting.create', $chain) }}" class="dashboard-action">Open Posting</a>
        </section>
    @endif

    <section class="document-journey-panel" aria-label="Hierarchical document journey">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Document Journey</p>
                <h2>Workflow status</h2>
                <p>Completed stages stay visible, the current stage is highlighted, and future steps remain below it.</p>
            </div>
        </div>

        <ol class="document-journey-timeline">
            @forelse ($steps as $step)
                @php
                    $label = (string) ($step['label'] ?? 'Workflow Step');
                    $state = $stateForStep($step);
                    $stepEvents = $eventsForStep($label);
                    $document = $documentForStep($label);
                    $eventDate = $stepEvents->last()?->created_at;
                    $date = $state === 'upcoming' ? null : ($eventDate ?? $dateForDocument($document));
                    $location = $stepEvents->last()?->toOffice?->name
                        ?? $stepEvents->last()?->fromOffice?->name
                        ?? ($state === 'current' ? $currentLocation : null);
                    $handler = $stepEvents->last()?->performedBy?->name
                        ?? ($state === 'current' ? $currentHandler : null);
                    $detailsId = 'tracking-step-'.$chain->id.'-'.\Illuminate\Support\Str::slug($label);
                @endphp

                <li class="document-journey-step is-{{ $state }}">
                    <span class="document-journey-step__marker" aria-hidden="true">{!! $iconForState($state) !!}</span>
                    <article class="document-journey-step__card">
                        <header>
                            <div>
                                <h3>{{ $label }}</h3>
                                <p>{{ $step['description'] ?? $stateLabel($state) }}</p>
                            </div>
                            <span class="document-journey-state">{{ $stateLabel($state) }}</span>
                        </header>

                        <dl class="document-journey-step__meta">
                            @if ($location)
                                <div>
                                    <dt>Office</dt>
                                    <dd>{{ $location }}</dd>
                                </div>
                            @endif
                            @if ($handler)
                                <div>
                                    <dt>Handler</dt>
                                    <dd>{{ $handler }}</dd>
                                </div>
                            @endif
                            @if ($date)
                                <div>
                                    <dt>{{ $state === 'current' ? 'Received / Updated' : 'Completed / Updated' }}</dt>
                                    <dd>{{ $formatDate($date) }}</dd>
                                </div>
                            @elseif ($state === 'upcoming')
                                <div>
                                    <dt>Status</dt>
                                    <dd>Not yet reached</dd>
                                </div>
                            @endif
                        </dl>

                        @if ($stepEvents->isNotEmpty())
                            <details class="document-journey-step__details" id="{{ $detailsId }}">
                                <summary>View recorded movement</summary>
                                <div class="document-journey-events">
                                    @foreach ($stepEvents as $event)
                                        @php
                                            $eventReturned = $containsReturn(($event->action ?? '').' '.($event->status ?? '').' '.($event->remarks ?? ''));
                                        @endphp
                                        <article class="{{ $eventReturned ? 'is-returned-event' : '' }}">
                                            <strong>{{ $event->action ?? 'Workflow update' }}</strong>
                                            <p>
                                                {{ $formatDate($event->created_at) ?? 'No timestamp' }}
                                                @if ($event->performedBy)
                                                    &middot; {{ $event->performedBy->name }}
                                                @endif
                                            </p>
                                            @if ($event->fromOffice || $event->toOffice)
                                                <p>{{ $event->fromOffice?->name ?? 'N/A' }} to {{ $event->toOffice?->name ?? 'N/A' }}</p>
                                            @endif
                                            @if ($event->remarks)
                                                <p class="document-journey-event-remarks">{{ $event->remarks }}</p>
                                            @endif
                                        </article>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    </article>
                </li>
            @empty
                <li class="document-journey-step is-current">
                    <span class="document-journey-step__marker" aria-hidden="true">&#9679;</span>
                    <article class="document-journey-step__card">
                        <header>
                            <div>
                                <h3>{{ $currentStage }}</h3>
                                <p>{{ $labelize($currentStatus) }}</p>
                            </div>
                            <span class="document-journey-state">Current Stage</span>
                        </header>
                    </article>
                </li>
            @endforelse
        </ol>
    </section>

    <section class="document-journey-panel" aria-label="Related SVP documents">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Linked Records</p>
                <h2>Document records</h2>
                <p>Only records already created in this SVP chain are linked.</p>
            </div>
        </div>

        <div class="svp-document-grid document-journey-documents">
            @foreach ($cards as $card)
                <article class="svp-document-card {{ $card['document'] ? 'is-linked' : 'is-pending' }}">
                    <div>
                        <p class="eyebrow">{{ $card['type'] }}</p>
                        <h2>{{ $card['number'] ?? $card['type'] }}</h2>
                        <span class="svp-status-badge {{ \Illuminate\Support\Str::slug($card['status'] ?? 'pending') }}">{{ $labelize($card['status'] ?? 'pending') }}</span>
                    </div>

                    <p>{{ $card['document'] ? 'Updated '.$card['date']?->format('M d, Y h:i A') : 'No document created yet.' }}</p>

                    <div class="svp-card-actions">
                        @if ($card['view_url'])
                            <a href="{{ $card['view_url'] }}">View</a>
                        @else
                            <span>{{ $card['document'] ? 'Linked' : 'Pending' }}</span>
                        @endif

                        @if ($card['print_url'])
                            <a href="{{ $card['print_url'] }}" target="_blank" rel="noopener">Print</a>
                        @endif

                        @if ($card['document'])
                            <x-ai.completeness-check-button
                                :document-type="$aiTypeMap[$card['type']] ?? 'procurement_document'"
                                :document-id="$card['document']->getKey()"
                                :tracking-number="$card['number']"
                                label="AI Check"
                                variant="inline-icon"
                            />
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="document-journey-panel" aria-label="Chronological movement history">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Movement History</p>
                <h2>Recorded actions</h2>
                <p>Chronological events from this procurement chain, including returns and corrections.</p>
            </div>
        </div>

        <ol class="svp-timeline document-journey-history">
            @forelse ($events as $event)
                @php
                    $eventReturned = $containsReturn(($event->action ?? '').' '.($event->status ?? '').' '.($event->remarks ?? ''));
                @endphp
                <li class="{{ $eventReturned ? 'is-returned-event' : '' }}">
                    <time>{{ $formatDate($event->created_at) ?? 'No timestamp' }}</time>
                    <div>
                        <strong>{{ $event->action ?? 'SVP event recorded' }}</strong>
                        <p>
                            {{ $event->stage ?? 'N/A' }}
                            @if ($event->status)
                                <span class="svp-dot">.</span>
                                {{ $labelize($event->status) }}
                            @endif
                        </p>
                        <p>
                            {{ $event->performedBy?->name ?? 'System' }}
                            @if ($event->fromOffice || $event->toOffice)
                                <span class="svp-dot">.</span>
                                {{ $event->fromOffice?->name ?? 'N/A' }} to {{ $event->toOffice?->name ?? 'N/A' }}
                            @endif
                        </p>
                        @if ($event->remarks)
                            <p class="svp-event-remarks">{{ $event->remarks }}</p>
                        @endif
                    </div>
                </li>
            @empty
                <li>
                    <time>Pending</time>
                    <div>
                        <strong>No recorded movement yet</strong>
                        <p>Events will appear as documents are created, submitted, routed, returned, and completed.</p>
                    </div>
                </li>
            @endforelse
        </ol>
    </section>
</div>
