@php
    $signatureRequests = collect($signatureRequests ?? []);
    $signatories = $resolution?->signatories ?: [];
    $approval = $resolution?->approval_signatory ?: $resolution?->approval_details ?: [];

    $canonicalSlot = function (?string $slot): ?string {
        if (! $slot) {
            return null;
        }

        return match (strtolower(str_replace('-', '_', $slot))) {
            'prepared', 'prepared_by' => 'prepared_by',
            'vice_chairperson', 'bac_vice', 'bac_vice_chairperson' => 'bac_vice_chairperson',
            'bac_member', 'member_one', 'bac_member_1' => 'bac_member_1',
            'member_two', 'bac_member_2' => 'bac_member_2',
            'provisional_member', 'bac_provisional_member' => 'bac_provisional_member',
            'chairperson', 'bac_chair', 'bac_chairperson' => 'bac_chairperson',
            'approving_authority', 'approved_by', 'hope', 'local_chief_executive' => 'hope',
            default => strtolower(str_replace('-', '_', $slot)),
        };
    };

    $requestsBySlot = $signatureRequests
        ->groupBy(fn ($request) => $canonicalSlot($request->signatory_slot))
        ->map(fn ($requests) => $requests->first());

    $firstFilled = function (...$values): string {
        foreach ($values as $value) {
            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    };

    $slotDefinitions = [
        'chairperson' => ['slot' => 'bac_chairperson', 'section' => 'Attested By', 'label' => 'BAC Chairperson', 'order' => 2],
        'vice_chairperson' => ['slot' => 'bac_vice_chairperson', 'section' => 'Attested By', 'label' => 'BAC Vice Chairperson', 'order' => 3],
        'member_one' => ['slot' => 'bac_member_1', 'section' => 'Attested By', 'label' => 'BAC Member', 'order' => 4],
        'member_two' => ['slot' => 'bac_member_2', 'section' => 'Attested By', 'label' => 'BAC Member', 'order' => 5],
        'provisional_member' => ['slot' => 'bac_provisional_member', 'section' => 'Attested By', 'label' => 'BAC Member', 'order' => 6],
        'approval_signatory' => ['slot' => 'hope', 'section' => 'Approved By', 'label' => 'Approved By', 'order' => 7],
    ];

    $statusClass = function ($request): string {
        if (! $request) {
            return 'not_generated';
        }

        return match ($request->status) {
            \App\Models\SignatureRequest::STATUS_SIGNED => 'signed',
            \App\Models\SignatureRequest::STATUS_DECLINED,
            \App\Models\SignatureRequest::STATUS_RETURNED => 'rejected',
            default => 'pending',
        };
    };

    $statusLabel = function ($request): string {
        if (! $request) {
            return 'Not Generated';
        }

        return match ($request->status) {
            \App\Models\SignatureRequest::STATUS_PENDING,
            \App\Models\SignatureRequest::STATUS_NOTIFIED,
            \App\Models\SignatureRequest::STATUS_VIEWED => 'Pending',
            \App\Models\SignatureRequest::STATUS_SIGNED => 'Signed',
            \App\Models\SignatureRequest::STATUS_DECLINED => 'Declined',
            \App\Models\SignatureRequest::STATUS_RETURNED => 'Returned',
            default => str($request->status)->replace('_', ' ')->title()->toString(),
        };
    };

    $buildRow = function (string $key, array $details, array $definition) use ($requestsBySlot, $canonicalSlot, $statusClass, $statusLabel, $firstFilled): array {
        $slot = $canonicalSlot($details['slot'] ?? $definition['slot'] ?? $key);
        $request = $requestsBySlot->get($slot);
        $metadata = $request?->metadata ?: [];
        $signature = $request?->electronicSignature;
        $section = trim((string) ($details['section'] ?? $definition['section'] ?? 'BAC Resolution'));
        $label = trim((string) ($details['label'] ?? $definition['label'] ?? $section));
        $accountCode = $firstFilled($details['account_code'] ?? null, $metadata['account_code'] ?? null, $request?->requestedTo?->user_id);
        $printedName = $firstFilled($details['name'] ?? null, $metadata['printed_name'] ?? null, $request?->requestedTo?->name);
        $designation = $firstFilled($details['designation'] ?? null, $metadata['designation'] ?? null);

        return [
            'section' => $section,
            'label' => $label,
            'slot' => $slot,
            'order' => (int) ($details['display_order'] ?? $metadata['display_order'] ?? $definition['order'] ?? $request?->signing_order ?? 99),
            'name' => $printedName !== '' ? $printedName : ($request?->requestedTo?->name ?? 'Unassigned'),
            'designation' => $designation,
            'account' => $accountCode !== '' ? $accountCode : 'No account selected',
            'request' => $request,
            'signature' => $signature,
            'state' => $statusClass($request),
            'status' => $statusLabel($request),
        ];
    };

    $savedRows = collect($signatories)
        ->reject(fn ($details, $key) => in_array($canonicalSlot(is_string($key) ? $key : (is_array($details) ? (string) ($details['slot'] ?? '') : '')), ['prepared_by'], true))
        ->map(function ($details, $key) use ($slotDefinitions, $buildRow, $canonicalSlot) {
            $details = is_array($details) ? $details : [];
            $definitionKey = is_string($key) ? $key : (string) ($details['key'] ?? $details['slot'] ?? '');
            $definition = $slotDefinitions[$definitionKey] ?? [
                'slot' => $canonicalSlot($details['slot'] ?? $definitionKey),
                'section' => $details['section'] ?? 'Attested By',
                'label' => $details['designation'] ?? 'Signatory',
                'order' => $details['display_order'] ?? 99,
            ];

            return $buildRow($definitionKey, $details, $definition);
        });

    if (! empty($approval)) {
        $savedRows->push($buildRow('approval_signatory', $approval, $slotDefinitions['approval_signatory']));
    }

    $representedSlots = $savedRows->pluck('slot')->map($canonicalSlot)->filter()->values();
    $fallbackRows = collect($slotDefinitions)
        ->reject(fn ($definition) => $representedSlots->contains($canonicalSlot($definition['slot'])))
        ->map(function (array $definition, string $key) use ($requestsBySlot, $buildRow, $canonicalSlot) {
            $slot = $canonicalSlot($definition['slot']);

            if (! $requestsBySlot->has($slot)) {
                return null;
            }

            $request = $requestsBySlot->get($slot);
            $metadata = $request?->metadata ?: [];

            return $buildRow($key, [
                'account_code' => $metadata['account_code'] ?? $request?->requestedTo?->user_id,
                'name' => $metadata['printed_name'] ?? $request?->requestedTo?->name,
                'designation' => $metadata['designation'] ?? '',
                'section' => $definition['section'],
                'display_order' => $metadata['display_order'] ?? $definition['order'],
            ], $definition);
        })
        ->filter()
        ->values();

    $panelRows = $savedRows
        ->merge($fallbackRows)
        ->sortBy('order')
        ->values();

    $signedCount = $panelRows
        ->filter(fn ($row) => $row['request']?->status === \App\Models\SignatureRequest::STATUS_SIGNED)
        ->count();
    $requiredCount = $panelRows->count();
    $attestedRows = $panelRows->where('section', 'Attested By')->values();
    $approvedRows = $panelRows->where('section', 'Approved By')->values();
    $pendingCount = $panelRows
        ->filter(fn ($row) => $row['request']?->status !== \App\Models\SignatureRequest::STATUS_SIGNED)
        ->count();
    $progressPercent = $requiredCount > 0 ? min(100, round(($signedCount / $requiredCount) * 100)) : 0;

    $summaryState = function ($rows, string $emptyLabel = 'Not Generated'): array {
        $rows = collect($rows);

        if ($rows->isEmpty()) {
            return ['class' => 'not_generated', 'label' => $emptyLabel];
        }

        $signed = $rows->filter(fn ($row) => $row['request']?->status === \App\Models\SignatureRequest::STATUS_SIGNED)->count();
        $total = $rows->count();

        if ($signed >= $total) {
            return ['class' => 'signed', 'label' => $total === 1 ? 'Signed' : "{$signed}/{$total} Signed"];
        }

        return ['class' => 'pending', 'label' => $total === 1 ? 'Pending' : "{$signed}/{$total} Signed"];
    };

    $attestedSummary = $summaryState($attestedRows, '0/5 Signed');
    $approvedSummary = $summaryState($approvedRows);
@endphp

@if (($resolution?->exists ?? false) && ($signatureRequests->isNotEmpty() || ! $resolution->canSubmit()))
    <section class="bac-resolution-signature-tracking pr-signatory-tracking-panel no-print" aria-label="BAC Resolution signature tracking">
        <div class="pr-signatory-tracking-heading">
            <div>
                <p class="pr-page-kicker">BAC Resolution Signature Status</p>
                <h2>Signature Status</h2>
            </div>
            <div class="pr-signatory-tracking-summary">
                <strong>{{ $signedCount }} of {{ $requiredCount }} signed</strong>
                <span>
                    @if ($requiredCount > 0 && $signedCount >= $requiredCount)
                        All required signatures are complete.
                    @elseif ($pendingCount > 0)
                        Waiting for {{ $pendingCount }} remaining {{ $pendingCount === 1 ? 'signature' : 'signatures' }}.
                    @else
                        Signature requests are ready to be generated.
                    @endif
                </span>
            </div>
        </div>

        <div class="bac-resolution-signature-progress" aria-label="{{ $progressPercent }} percent signed">
            <span style="width: {{ $progressPercent }}%"></span>
        </div>

        <div class="bac-resolution-signature-summary-grid">
            <article class="bac-resolution-signature-summary-card is-{{ $attestedSummary['class'] }}">
                <span>Attested By</span>
                <strong>{{ $attestedSummary['label'] }}</strong>
                <small>BAC chairperson, vice chairperson, and members</small>
            </article>
            <article class="bac-resolution-signature-summary-card is-{{ $approvedSummary['class'] }}">
                <span>Approved By</span>
                <strong>{{ $approvedSummary['label'] }}</strong>
                <small>HOPE / Municipal Mayor</small>
            </article>
        </div>

        <div class="pr-signatory-tracking-grid bac-resolution-signature-tracking-grid">
            @foreach ($panelRows as $row)
                <article class="pr-signatory-tracking-card is-{{ $row['state'] }}">
                    <div class="pr-signatory-card-head">
                        <span class="pr-signatory-card-role">{{ $row['section'] }}</span>
                        <em class="pr-signatory-tracking-status is-{{ $row['state'] }}">{{ $row['status'] }}</em>
                    </div>
                    <strong class="pr-signatory-card-name">{{ $row['name'] }}</strong>
                    <dl class="pr-signatory-card-meta">
                        <div>
                            <dt>Role</dt>
                            <dd>{{ $row['designation'] }}</dd>
                        </div>
                        <div>
                            <dt>Account</dt>
                            <dd>{{ $row['account'] }}</dd>
                        </div>
                        @if ($row['request']?->signed_at)
                            <div>
                                <dt>Signed Date</dt>
                                <dd><time datetime="{{ $row['request']->signed_at->toDateString() }}">{{ $row['request']->signed_at->format('M d, Y') }}</time></dd>
                            </div>
                        @elseif ($row['signature']?->signed_at)
                            <div>
                                <dt>Signed Date</dt>
                                <dd><time datetime="{{ $row['signature']->signed_at->toDateString() }}">{{ $row['signature']->signed_at->format('M d, Y') }}</time></dd>
                            </div>
                        @endif
                    </dl>
                </article>
            @endforeach
        </div>
    </section>
@endif
