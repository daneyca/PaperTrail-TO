@php
    $trackingDocument = $document ?? null;
    $submittedUserId = $trackingDocument?->submittedBy?->user_id;

    if (! $submittedUserId && ($trackingDocument?->exists ?? false) && $trackingDocument->submitted_by_user_id) {
        $submittedUserId = \App\Models\User::query()->whereKey($trackingDocument->submitted_by_user_id)->value('user_id');
    }

    $endUserSignatoryTrackingService = app(\App\Services\EndUserPrSignatoryRoutingService::class);
    $signatoryTrackingService = $submittedUserId === 'BACSEC-002'
        ? app(\App\Services\Bacsec002PrSignatoryRoutingService::class)
        : (($trackingDocument?->exists ?? false) && $endUserSignatoryTrackingService->hasSignatureRouting($trackingDocument)
            ? $endUserSignatoryTrackingService
            : null);
    $trackingColumns = $signatoryTrackingService?->signatureColumns() ?? [];
    $trackingSignatories = is_array($trackingDocument?->pr_signatories ?? null)
        ? $trackingDocument->pr_signatories
        : [];
    $trackingProgress = $signatoryTrackingService
        ? $signatoryTrackingService->signatureProgress($trackingDocument)
        : ['required' => 0, 'completed' => 0, 'pending_labels' => []];
    $requiredCount = (int) ($trackingProgress['required'] ?: count($trackingColumns));
    $completedCount = (int) ($trackingProgress['completed'] ?? 0);
    $pendingLabels = $trackingProgress['pending_labels'] ?? [];
    $showSignatureTracking = ($trackingDocument?->exists ?? false)
        && $signatoryTrackingService
        && count($trackingColumns)
        && $requiredCount > 0
        && $completedCount < $requiredCount
        && $trackingDocument?->status === \App\Models\ProcurementDocument::STATUS_PR_PENDING_SIGNATORIES;
@endphp

@if ($showSignatureTracking)
    <section class="pr-signatory-tracking-panel no-print" aria-label="Purchase Request signature tracking">
        <div class="pr-signatory-tracking-heading">
            <div>
                <p class="pr-page-kicker">Purchase Request Signature Status</p>
                <h2>Parallel Signature Status</h2>
            </div>
            <div class="pr-signatory-tracking-summary">
                <strong>{{ $completedCount }}/{{ $requiredCount }} signed</strong>
                <span>
                    @if ($requiredCount > 0 && $completedCount >= $requiredCount)
                        All required signatures are complete.
                    @elseif (! empty($pendingLabels))
                        Waiting for {{ implode(', ', $pendingLabels) }}.
                    @else
                        Signature requests are ready to be generated.
                    @endif
                </span>
            </div>
        </div>

        <div class="pr-signatory-tracking-grid">
            @foreach ($trackingColumns as $column => $definition)
                @php
                    $state = $signatoryTrackingService->statusForColumn($trackingDocument, $column);
                    $date = $state['date'] ?? null;
                    $printedName = $trackingSignatories[$column]['printed_name'] ?? 'No printed name';
                    $designation = $trackingSignatories[$column]['designation'] ?? 'No designation';
                    $request = $state['request'] ?? null;
                    $signer = $request?->requestedTo ?? (
                        $signatoryTrackingService instanceof \App\Services\EndUserPrSignatoryRoutingService
                            ? $signatoryTrackingService->signerForDesignation($designation, $trackingDocument)
                            : $signatoryTrackingService->signerForDesignation($designation)
                    );
                    $signerName = $state['signer_name'] ?? $signer?->name ?? $printedName;
                    $signerUserId = $state['signer_user_id'] ?? $signer?->user_id;
                @endphp
                <article class="pr-signatory-tracking-card is-{{ $state['status'] }}">
                    <div class="pr-signatory-card-head">
                        <span class="pr-signatory-card-role">{{ $definition['label'] }}</span>
                        <em class="pr-signatory-tracking-status is-{{ $state['status'] }}">{{ $state['label'] }}</em>
                    </div>

                    <strong class="pr-signatory-card-designation">{{ $designation }}</strong>

                    <dl class="pr-signatory-card-meta">
                        <div>
                            <dt>Signer Name</dt>
                            <dd>{{ $signerName }}</dd>
                        </div>
                        @if ($signerUserId)
                            <div>
                                <dt>Account</dt>
                                <dd>{{ $signerUserId }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt>Printed Name</dt>
                            <dd>{{ $printedName }}</dd>
                        </div>
                        <div>
                            <dt>Signed Date</dt>
                            <dd>
                                @if ($date)
                                    <time datetime="{{ $date->toDateString() }}">{{ $date->format('M d, Y') }}</time>
                                @else
                                    Not yet signed
                                @endif
                            </dd>
                        </div>
                    </dl>
                </article>
            @endforeach
        </div>
    </section>
@endif
