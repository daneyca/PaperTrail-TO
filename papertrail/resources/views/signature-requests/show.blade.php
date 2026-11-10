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
        $isPurchaseOrder = $signatureRequest->document_type === 'purchase_order';
        $isCompetitiveBiddingDocument = $signatureRequest->document_type === 'competitive_bidding_document';
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
            $isPurchaseOrder => 'Purchase Order',
            $isCompetitiveBiddingDocument && $document?->template_key === 'annual_investment_program' => 'AIP form',
            $isCompetitiveBiddingDocument && $document?->template_key === 'coa_transmittal' => 'COA cover letter',
            $isCompetitiveBiddingDocument && $document?->template_key === 'bac_secretariat_head_certification' => 'BAC Secretariat certification',
            $isCompetitiveBiddingDocument => 'competitive bidding document',
            default => 'document',
        };
        $documentSignLabel = match (true) {
            $isPurchaseRequest => 'Sign PR Form',
            $isPpmp => 'Sign PPMP Form',
            $isAbstract => 'Sign Abstract Form',
            $isApp => 'Sign APP Form',
            $isPurchaseOrder => 'Sign Purchase Order',
            $isCompetitiveBiddingDocument && $document?->template_key === 'annual_investment_program' => 'Sign AIP Form',
            $isCompetitiveBiddingDocument && $document?->template_key === 'coa_transmittal' => 'Sign COA Cover Letter',
            $isCompetitiveBiddingDocument && $document?->template_key === 'bac_secretariat_head_certification' => 'Sign BAC Secretariat Certification',
            $isCompetitiveBiddingDocument => 'Sign Competitive Bidding Document',
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
        </div>

        <div class="hero-actions no-print">
            <a href="{{ route('signature-requests.index') }}" class="dashboard-action secondary-action" data-action-icon="false" aria-label="Back to Signature Requests">Back</a>
            @if ($signatureRequest->document_id && ! $isCompetitiveBiddingDocument)
                <x-ai.completeness-check-button
                    :document-type="$signatureRequest->document_type"
                    :document-id="$signatureRequest->document_id"
                    :tracking-number="$documentNumber"
                    label="AI Check Document"
                />
            @endif
        </div>
    </section>

    @include('components.e-signature.remarks', ['signatures' => $signatureSlots])

    @unless ($isSignedRequest)
        <section class="signature-request-action-strip signature-inline-signing-panel no-print pt-smooth-enter" style="--pt-delay: 140ms" aria-label="Signature actions">
            @include('components.e-signature.panel', [
                'document' => $document,
                'documentType' => $signatureRequest->document_type,
                'existingSignature' => $existingSignature,
                'signedSignature' => $signedSignature,
                'canSign' => $canSign,
                'canSignMessage' => $canSignMessage ?? null,
                'signatureProfileComplete' => $signatureProfileComplete,
                'actionLabel' => 'Confirm Signature',
                'signatureAction' => 'confirmed',
                'signRoute' => route('signature-requests.sign', $signatureRequest),
                'declineRoute' => $signatureRequest->isOpen() ? route('signature-requests.decline', $signatureRequest) : null,
                'panelTitle' => 'Review and Sign',
                'panelDescription' => "Review the {$documentFormName} below, then sign or return it when ready.",
                'compactHelpText' => 'Enter your account password in the secure pop-up window to sign.',
                'openSignLabel' => $documentSignLabel,
                'openDeclineLabel' => 'Return for Correction',
                'showPanelIcon' => true,
                'panelIcon' => 'signature',
            ])
        </section>
    @endunless

    @if ($isPpmp && $isSignedRequest)
        @include('components.e-signature.ppmp-location', ['document' => $document])
    @endif

    <x-e-signature.signable-layout class="signature-request-workspace signature-request-workspace--inline-actions pt-smooth-enter" style="--pt-delay: 220ms" sidebar-label="Signature request actions">
        <x-slot name="document">
            <section class="signature-document-card" aria-label="Document preview">
                <div class="signature-document-card__header no-print">
                    <div>
                        <p class="eyebrow">Document Preview</p>
                        <h2>{{ $isPurchaseRequest ? 'Purchase Request Form' : ($isPpmp ? 'Project Procurement Management Plan' : ($isApp ? 'Annual Procurement Plan' : ($isPurchaseOrder ? 'Purchase Order' : ($signatureRequest->document_label ?? 'Document')))) }}</h2>
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
                    @elseif ($isPurchaseOrder)
                        <div class="signature-purchase-order-preview-area">
                            @include('head-office.purchase-orders.partials.purchase-order-excel-form', [
                                'po' => $document->loadMissing(['sourcePrDocument', 'sourceAbstract', 'items']),
                                'sourceDocument' => $document->sourcePrDocument,
                                'sourceAbstract' => $document->sourceAbstract,
                                'mode' => 'show',
                                'signatureSlots' => $signatureSlots,
                                'supplierSignatureRequest' => $supplierSignatureRequest,
                            ])
                        </div>
                    @elseif ($isCompetitiveBiddingDocument)
                        <div class="signature-competitive-preview-area">
                            @if ($document->template_key === 'annual_investment_program')
                                @include('bac-secretariat.competitive-bidding.partials.aip-signed-preview', compact('document', 'signatureSlots'))
                            @elseif ($document->template_key === 'coa_transmittal')
                                @include('bac-secretariat.competitive-bidding.transmittal.preview', compact('document', 'signatureSlots'))
                            @elseif ($document->template_key === 'bac_secretariat_head_certification')
                                @include('bac-secretariat.competitive-bidding.partials.head-certification-signed-preview', compact('document', 'signatureSlots'))
                            @else
                                {!! $document->document_html !!}
                            @endif
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
