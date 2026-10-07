@extends('layouts.dashboard')

@section('title', 'Audit Log Details | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Audit Event</p>
            <h1>Event #{{ $auditLog->id }}</h1>
            <p>{{ $auditLog->module }} &middot; {{ $auditLog->action }}</p>
        </div>
        <a href="{{ route('admin.audit.index') }}" class="dashboard-action">Back to Audit Trail</a>
    </section>

    <section class="form-panel audit-detail-grid">
        @foreach ([
            'Event UUID' => $auditLog->event_uuid,
            'Date and time' => $auditLog->created_at?->format('M d, Y h:i:s A'),
            'User ID' => $auditLog->user_identifier,
            'User name' => $auditLog->user_name,
            'Role' => $auditLog->role_name ?? $auditLog->user_role,
            'Office' => $auditLog->office_name ?? $auditLog->user_office,
            'Action' => $auditLog->action,
            'Module' => $auditLog->module,
            'Target type' => $auditLog->target_type ?? $auditLog->auditable_type,
            'Target ID' => $auditLog->target_id ?? $auditLog->auditable_id,
            'Target label' => $auditLog->target_label,
            'Document type' => $auditLog->document_type,
            'Document ID' => $auditLog->document_id,
            'Tracking number' => $auditLog->tracking_number,
            'Tracking number' => $auditLog->document_reference_number,
            'Route' => $auditLog->route_name,
            'URL' => $auditLog->request_url,
            'Method' => $auditLog->request_method,
            'IP address' => $auditLog->ip_address,
            'User agent' => $auditLog->user_agent,
            'Status' => ucfirst($auditLog->status ?? 'success'),
            'Severity' => ucfirst($auditLog->severity),
            'Description' => $auditLog->description,
        ] as $label => $value)
            <div class="audit-detail-item">
                <span>{{ $label }}</span>
                <strong>{{ $value ?? 'N/A' }}</strong>
            </div>
        @endforeach

        @foreach (['Old values' => $auditLog->old_values, 'New values' => $auditLog->new_values, 'Metadata' => $auditLog->metadata] as $label => $value)
            <div class="audit-detail-item audit-json">
                <span>{{ $label }}</span>
                <pre>{{ json_encode($value ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        @endforeach
    </section>
@endsection
