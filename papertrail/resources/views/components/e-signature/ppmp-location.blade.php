<section class="ppmp-readonly-strip head-office-ppmp-summary no-print" aria-label="PPMP current location">
    <div>
        <span class="head-office-ppmp-summary__icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M4 6h16M4 12h16M4 18h10" /></svg></span>
        <div><span>Current Step</span><strong>{{ $document->stage ?: 'PPMP Preparation' }}</strong></div>
    </div>
    <div>
        <span class="head-office-ppmp-summary__icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M4 21V5h16v16M2 21h20M8 9h2M14 9h2M8 13h2M14 13h2M10 21v-4h4v4" /></svg></span>
        <div><span>Current Office</span><strong>{{ $document->currentOffice?->name ?? 'Not assigned' }}</strong></div>
    </div>
    <div>
        <span class="head-office-ppmp-summary__icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="7" r="4" /><path d="M4 21v-2a8 8 0 0 1 16 0v2" /></svg></span>
        <div><span>Assigned To</span><strong>{{ $document->assignedTo?->name ?? 'Not assigned' }}</strong></div>
    </div>
    <div>
        <span class="head-office-ppmp-summary__icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M3 5h18v16H3zM3 10h18M7 3v4M17 3v4" /></svg></span>
        <div><span>Submitted Date</span><strong>{{ $document->submitted_at?->copy()->timezone('Asia/Manila')->format('M d, Y h:i A')
            ?? ($document->status === \App\Models\ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED ? 'Awaiting automatic routing' : 'Not submitted') }}</strong></div>
    </div>
</section>
