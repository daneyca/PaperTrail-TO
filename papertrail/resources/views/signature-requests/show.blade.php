@extends('layouts.dashboard')

@section('title', 'Signature Request | PaperTrail')

@section('content')
    @php
        $label = fn ($value) => $value
            ? str($value)->replace('_', ' ')->title()->replace('Bac', 'BAC')->replace('Ppmp', 'PPMP')
            : 'N/A';
        $isBacResolution = $signatureRequest->document_type === 'bac_resolution';
        $isPurchaseRequest = $signatureRequest->document_type === 'purchase_request';
        $isPpmp = $signatureRequest->document_type === 'ppmp';
        $isAbstract = $signatureRequest->document_type === 'abstract';
        $isApp = $signatureRequest->document_type === 'app';
        $signatureDesignation = filled($signatureRequest->requested_to_role)
            ? $signatureRequest->requested_to_role
            : ($signatureRequest->signatory_label ?? 'assigned signer');
        $documentNumber = $document && method_exists($document, 'displayNumber')
            ? $document->displayNumber()
            : ($signatureRequest->tracking_number
                ?? $document?->tracking_number
                ?? $signatureRequest->document_label
                ?? 'Signature Request');
        $documentFormName = match (true) {
            $isPurchaseRequest => 'PR form',
            $isPpmp => 'PPMP form',
            $isAbstract => 'Abstract form',
            $isApp => 'APP form',
            default => 'document',
        };
        $documentSignLabel = match (true) {
            $isPurchaseRequest => 'Sign PR Form',
            $isPpmp => 'Sign PPMP Form',
            $isAbstract => 'Sign Abstract Form',
            $isApp => 'Sign APP Form',
            default => 'Sign Document',
        };
        $statusText = $label($signatureRequest->status);
        $statusClass = \Illuminate\Support\Str::slug($signatureRequest->status ?? 'pending');
        $isSignedRequest = $signatureRequest->status === \App\Models\SignatureRequest::STATUS_SIGNED || (bool) $signedSignature;
    @endphp

    <section class="dashboard-hero admin-users-hero signature-request-hero pt-smooth-enter" style="--pt-delay: 0ms">
        <div>
            <p class="eyebrow">Document Signature</p>
            <h1>{{ $documentNumber }}</h1>
            <p>Review this document as {{ $signatureDesignation }} before applying your e-signature.</p>
        </div>

        <div class="hero-actions no-print">
            <a href="{{ route('signature-requests.index') }}" class="dashboard-action secondary-action">Back to Signature Requests</a>
            @if ($signatureRequest->document_id)
                <x-ai.completeness-check-button
                    :document-type="$signatureRequest->document_type"
                    :document-id="$signatureRequest->document_id"
                    :tracking-number="$documentNumber"
                    label="AI Check Document"
                />
            @endif
        </div>
    </section>

    @unless ($isSignedRequest)
        <section class="signature-request-action-strip signature-inline-signing-panel no-print pt-smooth-enter" style="--pt-delay: 140ms" aria-label="Signature actions">
            @include('components.e-signature.panel', [
                'document' => $document,
                'documentType' => $signatureRequest->document_type,
                'existingSignature' => $existingSignature,
                'signedSignature' => $signedSignature,
                'canSign' => $canSign,
                'signatureProfileComplete' => $signatureProfileComplete,
                'actionLabel' => 'Confirm Signature',
                'signatureAction' => 'confirmed',
                'sendCodeRoute' => route('signature-requests.send-code', $signatureRequest),
                'signRoute' => route('signature-requests.sign', $signatureRequest),
                'declineRoute' => $signatureRequest->isOpen() ? route('signature-requests.decline', $signatureRequest) : null,
                'panelTitle' => 'Review and Sign',
                'panelDescription' => "Review the {$documentFormName} below, then sign or return it when ready.",
                'compactHelpText' => 'Password and signing code are entered in a secure pop-up window.',
                'openSignLabel' => $documentSignLabel,
                'openDeclineLabel' => 'Return for Correction',
                'showPanelIcon' => true,
                'panelIcon' => 'signature',
            ])
        </section>
    @endunless

    @if ($isPpmp && $isSignedRequest && ($canSubmitPpmpToAppConsolidation ?? false))
        <section class="signature-request-action-strip ppmp-submit-next-step no-print pt-smooth-enter" style="--pt-delay: 140ms" aria-label="PPMP next step">
            <form
                method="POST"
                action="{{ route('head-office.ppmp.submit', $document) }}"
                data-confirm="Submit this signed PPMP to BAC Secretariat for APP consolidation?"
                data-confirm-title="Submit PPMP?"
                data-confirm-label="Submit to BAC Secretariat"
                data-confirm-type="submit"
            >
                @csrf
                <button type="submit" class="dashboard-action">Submit to BAC Secretariat</button>
            </form>
        </section>
    @endif

    <x-e-signature.signable-layout class="signature-request-workspace signature-request-workspace--inline-actions pt-smooth-enter" style="--pt-delay: 220ms" sidebar-label="Signature request actions">
        <x-slot name="document">
            <section class="signature-document-card" aria-label="Document preview">
                <div class="signature-document-card__header no-print">
                    <div>
                        <p class="eyebrow">Document Preview</p>
                        <h2>{{ $isPurchaseRequest ? 'Purchase Request Form' : ($isPpmp ? 'Project Procurement Management Plan' : ($isApp ? 'Annual Procurement Plan' : ($signatureRequest->document_label ?? 'Document'))) }}</h2>
                    </div>
                    <span class="signature-status-badge signature-status-badge--{{ $statusClass }}">{{ $statusText }}</span>
                </div>

                <div class="signature-document-card__body">
                    @if ($isBacResolution)
                        @include('bac-secretariat.resolutions.partials.resolution-document-preview', [
                            'resolution' => $document,
                            'sourceDocument' => $sourceDocument,
                            'mode' => 'show',
                            'signatureSlots' => $signatureSlots,
                            'bacChairSignature' => $signatureSlots->get('chairperson'),
                        ])
                    @elseif ($isPurchaseRequest)
                        <div class="pr-preview-area">
                            @include('head-office.pr._preview', [
                                'document' => $document->loadMissing(['submittingOffice', 'submittedBy', 'preparedBy', 'purchaseRequestItems']),
                                'sheetClass' => 'pr-readonly-sheet',
                                'mode' => 'show',
                            ])
                        </div>
                    @elseif ($isPpmp)
                        <div class="signature-ppmp-preview-area">
                            @include('head-office.ppmp._official-form', [
                                'document' => $document->loadMissing(['submittingOffice', 'submittedBy', 'preparedBy', 'ppmpItems']),
                                'items' => $document->ppmpItems,
                                'mode' => 'show',
                                'signatureSlots' => $signatureSlots,
                            ])
                        </div>
                    @elseif ($isAbstract)
                        <div class="signature-abstract-preview-area">
                            @include('head-office.abstracts.partials.abstract-excel-form', [
                                'abstract' => $document->loadMissing(['sourcePrDocument.submittingOffice', 'sourceRfq', 'sourceBacResolution', 'items']),
                                'sourceDocument' => $document->sourcePrDocument,
                                'sourceRfq' => $document->sourceRfq,
                                'sourceResolution' => $document->sourceBacResolution,
                                'mode' => 'show',
                            ])
                        </div>
                    @elseif ($isApp)
                        <div class="signature-app-preview-area">
                            @include('bac-secretariat.app.partials.app-landscape-form', [
                                'app' => $document->loadMissing(['items', 'preparedBy', 'submittedBy', 'approvedBy', 'office']),
                                'mode' => 'show',
                                'signatureSlots' => $signatureSlots,
                            ])
                        </div>
                    @else
                        <div class="signature-document-placeholder">
                            <strong>{{ $signatureRequest->document_label ?? 'Document' }}</strong>
                            <p>This document type is ready for signature routing. A specialized preview can be configured later.</p>
                        </div>
                    @endif
                </div>
            </section>
        </x-slot>
    </x-e-signature.signable-layout>
@endsection
