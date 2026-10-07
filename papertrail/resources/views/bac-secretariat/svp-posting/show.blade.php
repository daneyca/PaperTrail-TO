@extends('layouts.dashboard')

@section('title', ($postingRecord->pr_reference ?? $postingRecord->displayNumber()) . ' Posting | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Posting Management</p>
            <h1>{{ $postingRecord->displayNumber() }}</h1>
            <p>{{ $postingRecord->procurement_title ?? 'SVP posting record' }}</p>
        </div>

        <a href="{{ route('bac-secretariat.svp-posting.posted') }}" class="dashboard-action secondary-action">Posted Records</a>
    </section>

    <section class="svp-summary-strip" aria-label="Posting record summary">
        <div>
            <span>PR Reference</span>
            <strong>{{ $postingRecord->pr_reference ?? 'N/A' }}</strong>
        </div>
        <div>
            <span>BAC Resolution</span>
            <strong>{{ $postingRecord->bac_resolution_reference ?? 'N/A' }}</strong>
        </div>
        <div>
            <span>Requesting Office</span>
            <strong>{{ $postingRecord->requesting_office ?? 'N/A' }}</strong>
        </div>
        <div>
            <span>Posting Platform</span>
            <strong>{{ $postingRecord->posting_platform ?? 'PhilGEPS' }}</strong>
        </div>
        <div>
            <span>Approved Budget</span>
            <strong>PHP {{ number_format((float) $postingRecord->approved_budget, 2) }}</strong>
        </div>
        <div>
            <span>Status</span>
            <strong>{{ str($postingRecord->status)->replace('_', ' ')->title() }}</strong>
        </div>
        <div>
            <span>Workflow Continued</span>
            <strong>{{ $postingRecord->completed_at?->format('M d, Y h:i A') ?? 'Pending' }}</strong>
        </div>
    </section>

    <section class="table-panel svp-posting-detail-panel" aria-label="Posting details">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Posting Record</p>
                <h2>{{ $postingRecord->procurement_title ?? 'SVP Posting' }}</h2>
                <p>{{ $postingRecord->remarks ?: 'No posting remarks recorded.' }}</p>
            </div>

            @if ($postingRecord->isEditable() && $chain)
                <a href="{{ route('bac-secretariat.svp-posting.create', $chain) }}" class="dashboard-action">Update Posting</a>
            @endif
        </div>

        <div class="svp-posting-meta-grid">
            <div>
                <span>PhilGEPS Reference</span>
                <strong>{{ $postingRecord->philgeps_reference_number ?? 'Not set' }}</strong>
            </div>
            <div>
                <span>Posting Date</span>
                <strong>{{ $postingRecord->posting_date?->format('M d, Y') ?? 'Not set' }}</strong>
            </div>
            <div>
                <span>Closing Date</span>
                <strong>{{ $postingRecord->closing_date?->format('M d, Y') ?? 'Not set' }}</strong>
            </div>
            <div>
                <span>Created By</span>
                <strong>{{ $postingRecord->createdBy?->name ?? 'BACSEC-004' }}</strong>
            </div>
            <div>
                <span>Posted By</span>
                <strong>{{ $postingRecord->completedBy?->name ?? 'Pending' }}</strong>
            </div>
        </div>
    </section>

    <x-documents.attachments-panel
        :document="$postingRecord"
        document-type="svp_posting"
        title="Posting Evidence"
        :can-upload="$postingRecord->isEditable()"
    />

    @if ($chain)
        <section class="table-panel svp-workflow-panel" aria-label="SVP workflow route">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Workflow Route</p>
                    <h2>SVP Chain Progress</h2>
                    <p>Posted mock records return the PR to the requesting office for RFQ preparation.</p>
                </div>
                <a href="{{ route('bac-secretariat.svp-monitoring.show', $chain) }}" class="dashboard-action secondary-action">View Timeline</a>
            </div>

            <div class="svp-workflow-rail">
                @foreach ($workflowSteps as $step)
                    <article class="svp-workflow-step is-{{ $step['state'] }}">
                        <span class="svp-workflow-step__dot" aria-hidden="true"></span>
                        <strong>{{ $step['label'] }}</strong>
                        <p>{{ $step['description'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
@endsection
