@extends('layouts.dashboard')

@section('title', 'Supplier Signature Request | PaperTrail')

@section('content')
    @php
        $supplierUrl = $supplierRequest->publicUrl();
        $statusClass = $supplierRequest->status;
    @endphp

    <style>
        .supplier-detail-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
            gap: 16px;
        }

        .supplier-detail-card {
            border: 1px solid #d8e2ef;
            border-radius: 8px;
            background: #fff;
            padding: 18px;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
        }

        .supplier-detail-card h2 {
            margin: 0 0 12px;
            color: #0b2341;
            font-size: 1rem;
        }

        .supplier-detail-list {
            display: grid;
            gap: 10px;
        }

        .supplier-detail-list div {
            display: grid;
            gap: 2px;
        }

        .supplier-detail-list dt {
            color: #60708a;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .supplier-detail-list dd {
            margin: 0;
            color: #0b2341;
            font-weight: 700;
        }

        .supplier-public-link {
            width: 100%;
            border: 1px solid #d8e2ef;
            border-radius: 8px;
            padding: 10px 12px;
            color: #52627a;
        }

        .supplier-signature-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        @media (max-width: 980px) {
            .supplier-detail-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <section class="dashboard-hero admin-users-hero pt-smooth-enter">
        <div>
            <p class="eyebrow">Supplier Signature</p>
            <h1>{{ $supplierRequest->document_title }}</h1>
            <p>{{ $supplierRequest->supplier_name }} - {{ $supplierRequest->tracking_number ?: 'No tracking number' }}</p>
        </div>

        <div class="hero-actions">
            <a href="{{ route('supplier-signatures.index') }}" class="dashboard-action secondary-action">Back</a>
            <a href="{{ $supplierUrl }}" target="_blank" rel="noopener" class="dashboard-action">Open Supplier Link</a>
        </div>
    </section>

    <section class="supplier-detail-grid pt-smooth-enter">
        <div class="supplier-detail-card">
            <h2>Request Details</h2>
            <dl class="supplier-detail-list">
                <div>
                    <dt>Status</dt>
                    <dd>{{ $supplierRequest->statusLabel() }}</dd>
                </div>
                <div>
                    <dt>Supplier Link</dt>
                    <dd><input class="supplier-public-link" value="{{ $supplierUrl }}" readonly onclick="this.select()"></dd>
                </div>
                <div>
                    <dt>Supplier</dt>
                    <dd>{{ $supplierRequest->supplier_name }}</dd>
                </div>
                <div>
                    <dt>Authorized Representative</dt>
                    <dd>{{ $supplierRequest->signatory_name ?: 'To be supplied by signer' }}</dd>
                </div>
                <div>
                    <dt>Requested By</dt>
                    <dd>{{ $supplierRequest->requestedBy?->name ?? 'System' }}</dd>
                </div>
                <div>
                    <dt>Viewed / Submitted</dt>
                    <dd>
                        {{ $supplierRequest->viewed_at?->format('M d, Y h:i A') ?? 'Not viewed yet' }}
                        <br>
                        {{ $supplierRequest->submitted_at?->format('M d, Y h:i A') ?? 'No submission yet' }}
                    </dd>
                </div>
                <div>
                    <dt>Audit Hash</dt>
                    <dd>{{ $supplierRequest->signed_document_hash ?: 'No signed file yet' }}</dd>
                </div>
                <div>
                    <dt>Supplier Remarks</dt>
                    <dd>{{ $supplierRequest->supplier_remarks ?: 'None' }}</dd>
                </div>
            </dl>
        </div>

        <div class="supplier-detail-card">
            <h2>Files and BAC Action</h2>
            <div class="supplier-signature-actions" style="margin-bottom: 14px;">
                @if ($supplierRequest->hasSourceDocument())
                    <a class="dashboard-action secondary-action" href="{{ route('supplier-signatures.source-document', $supplierRequest) }}">Download Source</a>
                @endif
                @if ($supplierRequest->hasSignedDocument())
                    <a class="dashboard-action" href="{{ route('supplier-signatures.signed-document', $supplierRequest) }}">Download Signed File</a>
                @endif
            </div>

            @if ($supplierRequest->hasSignedDocument() && $supplierRequest->status !== \App\Models\SupplierSignatureRequest::STATUS_ACCEPTED)
                <form method="POST" action="{{ route('supplier-signatures.accept', $supplierRequest) }}" class="admin-form-fields" data-confirm="Accept this supplier-signed document?">
                    @csrf
                    @method('PATCH')
                    <label>
                        <span>BAC Remarks</span>
                        <textarea name="bac_remarks" rows="3">{{ old('bac_remarks', $supplierRequest->bac_remarks) }}</textarea>
                    </label>
                    <button type="submit" class="dashboard-action">Accept Signed File</button>
                </form>

                <form method="POST" action="{{ route('supplier-signatures.reject', $supplierRequest) }}" class="admin-form-fields" style="margin-top: 16px;" data-confirm="Return this request to the supplier for correction?">
                    @csrf
                    @method('PATCH')
                    <label>
                        <span>Return Reason</span>
                        <textarea name="bac_remarks" rows="3" required>{{ old('bac_remarks') }}</textarea>
                    </label>
                    <button type="submit" class="dashboard-action secondary-action">Return for Correction</button>
                </form>
            @endif

            @if (! $supplierRequest->isTerminal())
                <form method="POST" action="{{ route('supplier-signatures.cancel', $supplierRequest) }}" class="admin-form-fields" style="margin-top: 16px;" data-confirm="Cancel this supplier signing request?">
                    @csrf
                    @method('PATCH')
                    <label>
                        <span>Cancellation Remarks</span>
                        <textarea name="bac_remarks" rows="3">{{ old('bac_remarks') }}</textarea>
                    </label>
                    <button type="submit" class="dashboard-action secondary-action">Cancel Request</button>
                </form>
            @endif
        </div>
    </section>
@endsection
