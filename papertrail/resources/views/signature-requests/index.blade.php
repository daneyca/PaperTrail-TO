@extends('layouts.dashboard')

@section('title', 'Documents for Signature | PaperTrail')

@section('content')
    @php
        $activeStatus = $filters['status'] ?? request('status');
        $documentTypeLabel = function (?string $documentType): string {
            return str($documentType ?: 'Document')
                ->replace('_', ' ')
                ->title()
                ->replace('Bac', 'BAC')
                ->replace('Ppmp', 'PPMP')
                ->toString();
        };
    @endphp

    <section class="dashboard-hero admin-users-hero signature-page-hero pt-smooth-enter" style="--pt-delay: 0ms">
        <div>
            <p class="eyebrow">E-Signature</p>
            <h1>Documents for Signature</h1>
            <p>Review documents routed to your account, role, or office for electronic signature.</p>
        </div>

        <a href="{{ route('dashboard') }}" class="dashboard-action secondary-action">Back to Dashboard</a>
    </section>

    <section class="stat-grid stat-grid-modern pt-smooth-enter" style="--pt-delay: 120ms" aria-label="Signature request summary">
        <x-dashboard.stat-card href="{{ route('signature-requests.index', ['status' => 'all']) }}" value="{{ $summary['total'] }}" label="All Assigned" accent="navy" icon="signature" class="{{ $activeStatus === 'all' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('signature-requests.index') }}" value="{{ $summary['pending'] }}" label="Pending Signatures" accent="gold" icon="clock" class="{{ blank($activeStatus) || in_array($activeStatus, ['active', 'pending', 'open'], true) ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('signature-requests.index', ['status' => 'signed']) }}" value="{{ $summary['signed'] }}" label="Signed Documents" accent="green" icon="check" class="{{ $activeStatus === 'signed' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('signature-requests.index', ['status' => 'returned_declined']) }}" value="{{ $summary['declined'] }}" label="Returned / Declined" accent="red" icon="return" class="{{ $activeStatus === 'returned_declined' ? 'is-filter-active' : '' }}" />
    </section>

    <section class="signature-request-list pt-smooth-enter" style="--pt-delay: 220ms">
        <form method="GET" action="{{ route('signature-requests.index') }}" class="signature-filter-card">
            <div class="signature-filter-grid">
                <div class="signature-filter-field">
                    <label for="search">Search</label>
                    <input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Document, tracking number, requester...">
                </div>

                <div class="signature-filter-field">
                    <label for="document_type">Document Type</label>
                    <select id="document_type" name="document_type">
                        <option value="">All types</option>
                        @forelse ($documentTypes as $documentType)
                            <option value="{{ $documentType }}" @selected(($filters['document_type'] ?? '') === $documentType)>
                                {{ $documentTypeLabel($documentType) }}
                            </option>
                        @empty
                            <option value="bac_resolution" @selected(($filters['document_type'] ?? '') === 'bac_resolution')>BAC Resolution</option>
                        @endforelse
                    </select>
                </div>

                <div class="signature-filter-field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">Pending only</option>
                        <option value="all" @selected(($filters['status'] ?? '') === 'all')>All assigned to me</option>
                        @foreach ($statuses as $status => $label)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="signature-filter-field">
                    <label for="date_from">Date From</label>
                    <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}">
                </div>

                <div class="signature-filter-field">
                    <label for="date_to">Date To</label>
                    <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}">
                </div>

                <div class="signature-filter-actions">
                    <button type="submit" class="btn btn-apply">Apply</button>
                    <a href="{{ route('signature-requests.index') }}" class="btn btn-clear">Clear</a>
                </div>
            </div>
        </form>

        <div class="signature-table-card">
            <div class="signature-table-wrap">
                <table class="signature-table">
                    <thead>
                        <tr>
                            <th>Date Requested</th>
                            <th>Document Type</th>
                            <th>Tracking Number</th>
                            <th>Signatory Role</th>
                            <th>Requested By</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($signatureRequests as $requestItem)
                            @php
                                $isSignedRequest = $requestItem->status === \App\Models\SignatureRequest::STATUS_SIGNED;
                                $isWaitingRequest = $requestItem->status === \App\Models\SignatureRequest::STATUS_WAITING;
                                $isDeclinedOrReturned = in_array($requestItem->status, [\App\Models\SignatureRequest::STATUS_DECLINED, \App\Models\SignatureRequest::STATUS_RETURNED], true);
                                $isPendingRequest = $requestItem->isOpen();
                                $canSignNow = (bool) $requestItem->can_sign_now;
                                $signature = $requestItem->signedSignatureForList ?? $requestItem->electronicSignature;
                                $statusClass = str($requestItem->status)->replace('_', '-')->slug();
                                $isPurchaseRequest = $requestItem->document_type === 'purchase_request';
                                $isPpmp = $requestItem->document_type === 'ppmp';
                                $ppmpSignerName = $requestItem->signatory_person_label
                                    ?: $requestItem->requestedTo?->name
                                    ?: data_get($requestItem->metadata, 'printed_name');
                                $signatureDesignation = $isPpmp && filled($ppmpSignerName)
                                    ? $ppmpSignerName
                                    : ($isPurchaseRequest && filled($requestItem->requested_to_role)
                                    ? $requestItem->requested_to_role
                                    : ($requestItem->signatory_label ?? 'Signer'));
                                $signatureTaskLabel = ! $isPpmp && $requestItem->signatory_label && $requestItem->signatory_label !== $signatureDesignation
                                    ? $requestItem->signatory_label
                                    : null;
                                $isBacsec002ResolutionUser = auth()->user()?->user_id === 'BACSEC-002';
                                $isSvpRequest = ! $isBacsec002ResolutionUser && (bool) $requestItem->is_svp_signature_request;
                                $documentContextLabel = $requestItem->document_context_label ?: ($isPurchaseRequest || $isPpmp ? null : $requestItem->document_label);
                                $requestDocumentTypeLabel = $documentTypeLabel($requestItem->document_type);
                                $requestTrackingNumber = $requestItem->display_tracking_number ?? $requestItem->tracking_number ?? 'N/A';
                                $signActionLabel = $canSignNow && ($isPurchaseRequest || $isPpmp)
                                    ? 'Sign as ' . $signatureDesignation
                                    : 'Review & Sign';
                            @endphp
                            <tr class="{{ $isPendingRequest ? 'signature-table-row--pending' : '' }}">
                                <td>{{ $requestItem->created_at?->format('M d, Y') ?? 'N/A' }}</td>
                                <td>
                                    <div class="signature-document-type">
                                        <span>{{ $requestDocumentTypeLabel }}</span>
                                        @if ($isSvpRequest)
                                            <span class="signature-context-badge" title="{{ $requestItem->svp_chain_number ? 'SVP chain ' . $requestItem->svp_chain_number : 'Small Value Procurement' }}">SVP</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    {{ $requestTrackingNumber }}
                                    @if ($isPurchaseRequest && filled($requestItem->official_pr_number))
                                        <br><span class="muted-text">Official PR: {{ $requestItem->official_pr_number }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="signature-role">{{ $signatureDesignation }}</div>
                                    @if ($signatureTaskLabel)
                                        <div class="signature-document-title">{{ $signatureTaskLabel }}</div>
                                    @endif
                                    @if (filled($documentContextLabel))
                                        <div class="signature-document-title">{{ $documentContextLabel }}</div>
                                    @endif
                                </td>
                                <td>{{ $requestItem->requestedBy?->name ?? 'System' }}</td>
                                <td>
                                    <span class="signature-status-badge signature-status-badge--{{ $statusClass }}">
                                        {{ str($requestItem->status)->replace('_', ' ')->title()->replace('Ppmp', 'PPMP') }}
                                    </span>
                                </td>
                                <td>
                                    <div class="signature-actions">
                                        @if ($canSignNow)
                                            <a class="btn btn-primary signature-icon-action" href="{{ route('signature-requests.show', $requestItem) }}" title="{{ $signActionLabel }}" aria-label="{{ $signActionLabel }}">
                                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <path d="M12 20h9" />
                                                    <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" />
                                                </svg>
                                                <span>{{ $signActionLabel }}</span>
                                            </a>
                                        @elseif ($isSignedRequest)
                                            <a class="btn btn-secondary signature-icon-action" href="{{ route('signature-requests.show', $requestItem) }}" title="View Signed Document" aria-label="View Signed Document">
                                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" />
                                                    <circle cx="12" cy="12" r="3" />
                                                </svg>
                                                <span>View Signed Document</span>
                                            </a>
                                            @if ($signature?->isValid())
                                                <a class="btn btn-light signature-icon-action" href="{{ route('e-signatures.certificate', $signature) }}" title="View Certificate" aria-label="View Certificate">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                        <path d="M7 3h10a2 2 0 0 1 2 2v14l-4-2-3 2-3-2-4 2V5a2 2 0 0 1 2-2Z" />
                                                        <path d="M8 8h8M8 12h6" />
                                                    </svg>
                                                    <span>View Certificate</span>
                                                </a>
                                            @endif
                                        @elseif ($isWaitingRequest)
                                            <span class="btn btn-disabled signature-icon-action" aria-disabled="true" title="Waiting">
                                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <circle cx="12" cy="12" r="8" />
                                                    <path d="M12 8v5l3 2" />
                                                </svg>
                                                <span>Waiting</span>
                                            </span>
                                        @elseif ($isDeclinedOrReturned)
                                            <a class="btn btn-secondary signature-icon-action" href="{{ route('signature-requests.show', $requestItem) }}" title="View Details" aria-label="View Details">
                                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" />
                                                    <circle cx="12" cy="12" r="3" />
                                                </svg>
                                                <span>View Details</span>
                                            </a>
                                        @else
                                            <a class="btn btn-secondary signature-icon-action" href="{{ route('signature-requests.show', $requestItem) }}" title="View Details" aria-label="View Details">
                                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" />
                                                    <circle cx="12" cy="12" r="3" />
                                                </svg>
                                                <span>View Details</span>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="signature-empty-state">
                                        <strong>No documents are currently waiting for your signature.</strong>
                                        <p>When a document is routed to your account, role, or office for electronic signature, it will appear here.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="signature-pagination">
            {{ $signatureRequests->links() }}
        </div>
    </section>
@endsection
