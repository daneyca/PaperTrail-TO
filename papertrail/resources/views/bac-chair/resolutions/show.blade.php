@extends('layouts.dashboard')

@section('title', ($resolution->resolution_number ?: 'BAC Resolution') . ' | BAC Chair')

@section('content')
    @php
        $label = fn ($value) => $value ? str($value)->replace('_', ' ')->title() : 'N/A';
        $money = fn ($amount) => 'PHP ' . number_format((float) $amount, 2);
        $canConfirm = $resolution->status === \App\Models\BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR;
        $isSigned = $signedSignature?->isValid();
        $canReturn = ! $isSigned && in_array($resolution->status, [\App\Models\BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR, \App\Models\BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR], true);
        $canForward = ! $isSigned && $resolution->status === \App\Models\BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR;
    @endphp

    <section class="dashboard-hero admin-users-hero pt-smooth-enter" style="--pt-delay: 0ms">
        <div>
            <p class="eyebrow">BAC Chair Resolution Review</p>
            <h1>{{ $resolution->resolution_number ? 'BAC Resolution No. '.$resolution->resolution_number : 'BAC Resolution' }}</h1>
            <p>{{ $resolution->title ?? 'Review the official BAC Resolution submitted by BAC Secretariat.' }}</p>
        </div>

        <a href="{{ route('bac-chair.resolutions.index') }}" class="dashboard-action secondary-action">Back to Resolutions</a>
    </section>

    <section class="resolution-summary-strip table-panel pt-smooth-enter" style="--pt-delay: 120ms" aria-label="BAC Resolution summary">
        <div><span>Resolution No.</span><strong>{{ $resolution->resolution_number ?: 'Pending No.' }}</strong></div>
        <div><span>Source PR</span><strong>{{ $sourceDocument?->tracking_number ?? $resolution->pr_number ?? 'N/A' }}</strong></div>
        <div><span>Requesting Office</span><strong>{{ $resolution->requesting_office_name ?? $sourceDocument?->submittingOffice?->name ?? 'N/A' }}</strong></div>
        <div><span>Status</span><strong>{{ $label($resolution->status) }}</strong></div>
        <div><span>Total Amount</span><strong>{{ $money($resolution->total_amount ?? $resolution->abc_amount) }}</strong></div>
    </section>

    @include('bac-secretariat.resolutions.partials.signature-tracking-panel', [
        'resolution' => $resolution,
        'signatureRequests' => $signatureRequests ?? collect(),
    ])

    <x-e-signature.signable-layout class="pt-smooth-enter" style="--pt-delay: 220ms" sidebar-label="BAC Resolution actions">
        <x-slot name="document">
            @include('bac-secretariat.resolutions.partials.resolution-word-editor', [
                'resolution' => $resolution,
                'sourceDocument' => $sourceDocument,
                'mode' => 'show',
                'signatureSlots' => $signatureSlots ?? collect(),
                'bacChairSignature' => $signedSignature,
            ])
        </x-slot>

        <x-slot name="sidebar">
        <div class="table-panel resolution-side-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Review Actions</p>
                    <h2>Resolution Controls</h2>
                </div>
            </div>

            <a href="{{ route('bac-chair.resolutions.print', $resolution) }}" target="_blank" class="dashboard-action secondary-action">Print Resolution</a>
            <x-ai.completeness-check-button
                document-type="bac_resolution"
                :document-id="$resolution->id"
                :tracking-number="$resolution->resolution_number"
            />

            @include('components.e-signature.panel', [
                'document' => $resolution,
                'documentType' => 'bac-resolution',
                'existingSignature' => $existingSignature,
                'signedSignature' => $signedSignature,
                'canSign' => $canSignResolution,
                'signatureProfileComplete' => $signatureProfileComplete,
                'actionLabel' => 'Confirm Resolution',
                'signatureAction' => 'confirmed',
                'sendCodeRoute' => route('e-signatures.send-code', ['documentType' => 'bac-resolution', 'documentId' => $resolution->id]),
                'signRoute' => route('e-signatures.sign', ['documentType' => 'bac-resolution', 'documentId' => $resolution->id]),
                'declineRoute' => $canConfirm ? route('e-signatures.decline', ['documentType' => 'bac-resolution', 'documentId' => $resolution->id]) : null,
                'panelTitle' => 'BAC Chair Confirmation',
            ])

            @if ($canForward)
                <form method="POST" action="{{ route('bac-chair.resolutions.forward-to-hope', $resolution) }}" onsubmit="return confirm('Forward this BAC Resolution to HOPE?');" class="budget-action-form">
                    @csrf
                    @method('PATCH')

                    <label for="forward-remarks">Forwarding Remarks</label>
                    <textarea id="forward-remarks" name="remarks" rows="3" placeholder="Optional remarks for HOPE.">{{ old('remarks') }}</textarea>
                    @error('remarks')
                        <span class="field-error">{{ $message }}</span>
                    @enderror

                    <button type="submit">Forward to HOPE</button>
                </form>
            @endif

            @if ($canReturn && ! $canConfirm)
                <form id="return-resolution" method="POST" action="{{ route('bac-chair.resolutions.return', $resolution) }}" onsubmit="return confirm('Return this BAC Resolution to BAC Secretariat?');" class="budget-action-form">
                    @csrf
                    @method('PATCH')

                    <label for="return-remarks">Return Remarks</label>
                    <textarea id="return-remarks" name="remarks" rows="4" required placeholder="Explain what BAC Secretariat must correct or clarify.">{{ old('remarks') }}</textarea>
                    @error('remarks')
                        <span class="field-error">{{ $message }}</span>
                    @enderror

                    <button type="submit" class="danger-action">Return to BAC Secretariat</button>
                </form>
            @endif

            @if (! $signedSignature && ! $canConfirm && ! $canForward && ! $canReturn)
                <div class="empty-state">
                    <strong>No action available</strong>
                    <p>This BAC Resolution has already moved out of the BAC Chair action queue.</p>
                </div>
            @endif

            <div class="resolution-side-info">
                <dl>
                    <div><dt>Submitted By</dt><dd>{{ $resolution->submittedBy?->name ?? 'N/A' }}</dd></div>
                    <div><dt>Submitted Date</dt><dd>{{ $resolution->submitted_at?->format('M d, Y h:i A') ?? 'N/A' }}</dd></div>
                    <div><dt>Confirmed By</dt><dd>{{ $resolution->bacChairConfirmedBy?->name ?? 'N/A' }}</dd></div>
                    <div><dt>Confirmed Date</dt><dd>{{ $resolution->bac_chair_confirmed_at?->format('M d, Y h:i A') ?? 'N/A' }}</dd></div>
                    <div><dt>Forwarded By</dt><dd>{{ $resolution->forwardedToHopeBy?->name ?? 'N/A' }}</dd></div>
                    <div><dt>Forwarded Date</dt><dd>{{ $resolution->forwarded_to_hope_at?->format('M d, Y h:i A') ?? 'N/A' }}</dd></div>
                    @if ($resolution->bac_chair_remarks || $resolution->remarks)
                        <div><dt>Latest Remarks</dt><dd>{{ $resolution->bac_chair_remarks ?? $resolution->remarks }}</dd></div>
                    @endif
                </dl>
            </div>
        </div>
        </x-slot>
    </x-e-signature.signable-layout>

    <x-documents.attachments-panel
        :document="$resolution"
        document-type="bac_resolution"
        :can-upload="false"
        :can-delete="false"
        title="Supporting Files"
    />

    <section class="dashboard-widget-grid pt-smooth-enter" style="--pt-delay: 300ms">
        <article class="dashboard-widget widget-wide">
            <div class="widget-heading">
                <div>
                    <p class="eyebrow">Routing History</p>
                    <h2>Source Document Movement</h2>
                </div>
            </div>

            <ol class="routing-timeline">
                @forelse (($sourceDocument?->routingHistories ?? collect())->sortByDesc('action_at') as $history)
                    <li>
                        <strong>{{ $history->action }}</strong>
                        <span>{{ $history->action_at?->format('M d, Y h:i A') }} by {{ $history->actionBy?->name ?? 'System' }}</span>
                        <p>{{ $history->fromOffice?->name ?? 'N/A' }} to {{ $history->toOffice?->name ?? 'N/A' }} - {{ $label($history->status_from ?? 'new') }} to {{ $label($history->status_to) }}</p>
                        @if ($history->comments)
                            <p>{{ $history->comments }}</p>
                        @endif
                    </li>
                @empty
                    <li><strong>No routing history yet</strong><span>Routing records will appear here when the linked source PR moves through the workflow.</span></li>
                @endforelse
            </ol>
        </article>
    </section>
@endsection
