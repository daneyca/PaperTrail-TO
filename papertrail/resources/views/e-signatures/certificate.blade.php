@extends('layouts.dashboard')

@section('title', 'Signature Certificate | PaperTrail')

@section('content')
    @php
        $status = $signature->isValid() ? 'Valid' : str($signature->signature_status)->replace('_', ' ')->title();
        $isAdmin = auth()->user()?->isAdmin();
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">E-Signature Certificate</p>
            <h1>{{ $signature->signature_code ?? 'Signature Certificate' }}</h1>
            <p>Verification details for an electronic signature recorded in PaperTrail.</p>
        </div>

        <a href="{{ url()->previous() }}" class="dashboard-action secondary-action">Back</a>
    </section>

    <section class="signature-certificate-card">
        <div class="signature-certificate-header">
            <div>
                <p class="eyebrow">Verification Result</p>
                <h1>{{ $status }}</h1>
                <p>This record confirms an internal PaperTrail electronic signature. It is not a PNPKI digital certificate.</p>
            </div>
            <span class="signature-status-badge {{ $signature->isValid() ? 'is-signed' : 'is-declined' }}">{{ $status }}</span>
        </div>

        <div class="signature-certificate-grid">
            <div><span>Signature ID</span><strong>{{ $signature->signature_code ?? 'N/A' }}</strong></div>
            <div><span>Document Type</span><strong>{{ str($signature->document_type)->replace('_', ' ')->title() }}</strong></div>
            <div><span>Document Tracking Number</span><strong>{{ $signature->tracking_number ?? 'N/A' }}</strong></div>
            <div><span>Document Label</span><strong>{{ $signature->document_label ?? 'N/A' }}</strong></div>
            <div><span>Signer Name</span><strong>{{ $signature->signer_name ?? 'N/A' }}</strong></div>
            <div><span>Signer Role / Position</span><strong>{{ $signature->signer_position ?? $signature->signer_role ?? 'N/A' }}</strong></div>
            <div><span>Signer Office</span><strong>{{ $signature->signer_office_name ?? 'N/A' }}</strong></div>
            <div><span>Date / Time Signed</span><strong>{{ $signature->signed_at?->format('M d, Y h:i A') ?? 'N/A' }}</strong></div>
            <div><span>Signature Status</span><strong>{{ str($signature->signature_status)->replace('_', ' ')->title() }}</strong></div>
            <div><span>Document Hash</span><strong>{{ $signature->document_hash_before ?? 'N/A' }}</strong></div>
            <div><span>Snapshot Hash</span><strong>{{ $signature->signed_snapshot_hash ?? $signature->snapshot?->snapshot_hash ?? 'N/A' }}</strong></div>
            <div><span>Verification</span><strong>Electronically Signed through PaperTrail</strong></div>

            @if ($isAdmin)
                <div><span>IP Address</span><strong>{{ $signature->ip_address ?? 'N/A' }}</strong></div>
                <div><span>User Agent</span><strong>{{ $signature->user_agent ?? 'N/A' }}</strong></div>
            @endif
        </div>

        <div class="signature-certificate-actions">
            <a href="{{ url()->previous() }}">Back</a>
            <button type="button" onclick="window.print()">Print Certificate</button>
        </div>
    </section>
@endsection
