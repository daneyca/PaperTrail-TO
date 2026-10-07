@extends('layouts.dashboard')

@section('title', ($document->pr_no ?? $document->tracking_number) . ' | Purchase Request')

@section('content')
    @php
        $isIncomingReference = $document->status === \App\Models\ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION
            && (int) $document->assigned_to_user_id === (int) auth()->id();
        $statusLabel = $isIncomingReference
            ? 'Incoming PR Reference'
            : str($document->status)->replace('_', ' ')->title();
    @endphp

    <section class="dashboard-hero admin-users-hero bac-pr-detail-hero">
        <div>
            <p class="eyebrow">{{ $isIncomingReference ? 'BAC Resolution Preparation' : 'BAC Secretariat Purchase Request' }}</p>
            <h1>{{ $document->pr_no ?? $document->tracking_number }}</h1>
            <p>{{ $isIncomingReference ? 'Review the finalized PR reference forwarded by the requesting office.' : 'Validate the official LGU Purchase Request form submitted by '.($document->submittingOffice?->name ?? 'the requesting office').'.' }}</p>
        </div>

        <a href="{{ route('bac-secretariat.pr.index') }}" class="dashboard-action secondary-action">Back to Purchase Requests</a>
    </section>

    <section class="pr-action-toolbar bac-pr-toolbar no-print" aria-label="Purchase Request page actions">
        <div class="pr-toolbar-group">
            <a href="{{ route('bac-secretariat.pr.index') }}" class="btn-pr-secondary">Back</a>
            <a href="#supporting-files" class="btn-pr-secondary">Supporting Files</a>
        </div>
        <div class="pr-toolbar-group">
            <x-ai.completeness-check-button
                document-type="purchase_request"
                :document-id="$document->id"
                :tracking-number="$document->pr_no ?? $document->tracking_number"
            />
            <a href="{{ route('bac-secretariat.pr.print', $document) }}" class="btn-pr-primary">Print PR</a>
        </div>
    </section>

    <section class="budget-review-layout bac-pr-detail-layout">
        <article class="bac-pr-main-column">
            <div class="section-heading bac-pr-section-heading">
                <div>
                    <p class="eyebrow">Official Form Preview</p>
                    <h2>Purchase Request Form</h2>
                </div>
            </div>

            <div class="pr-preview-area bac-secretariat-pr-preview" aria-label="Official Purchase Request form preview">
                @include('head-office.pr._preview', ['sheetClass' => 'pr-readonly-sheet bac-secretariat-pr-sheet', 'mode' => 'show'])
            </div>
        </article>

        <aside class="bac-pr-side-column">
            <article class="table-panel budget-action-panel bac-pr-side-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">PR Validation Actions</p>
                        <h2>Validation Controls</h2>
                    </div>
                </div>

                @if (in_array($document->status, ['pr_submitted', 'submitted_to_bac_secretariat'], true))
                    <form method="POST" action="{{ route('bac-secretariat.pr.acknowledge', $document) }}" onsubmit="return confirm('Acknowledge receipt of this Purchase Request?');" class="budget-action-form">
                        @csrf
                        @method('PATCH')
                        <button type="submit">Acknowledge Receipt</button>
                    </form>
                @endif

                @if ($document->status === 'pr_received_by_bac_secretariat')
                    <form method="POST" action="{{ route('bac-secretariat.pr.start-validation', $document) }}" onsubmit="return confirm('Start validation for this Purchase Request?');" class="budget-action-form">
                        @csrf
                        @method('PATCH')
                        <button type="submit">Start Validation</button>
                    </form>
                @endif

                @if ($document->status === 'ready_for_bac_resolution')
                    @if ((int) $document->assigned_to_user_id === (int) auth()->id())
                        <div class="table-actions">
                            <a href="{{ route('bac-secretariat.resolutions.create', ['source_pr_document_id' => $document->id]) }}">Prepare BAC Resolution</a>
                        </div>
                    @else
                        <div class="empty-state success-state">
                            <strong>Assigned for BAC Resolution</strong>
                            <p>Current handler: {{ $document->assignedTo?->user_id ?? 'Unassigned' }} {{ $document->assignedTo?->name ? '- '.$document->assignedTo->name : '' }}</p>
                        </div>
                    @endif
                @endif

                @if (in_array($document->status, ['pr_submitted', 'submitted_to_bac_secretariat', 'pr_received_by_bac_secretariat', 'under_pr_validation'], true))
                    <form method="POST" action="{{ route('bac-secretariat.pr.return', $document) }}" onsubmit="return confirm('Return this Purchase Request?');" class="budget-action-form">
                        @csrf
                        @method('PATCH')
                        <label for="comments">Return Reason</label>
                        <textarea id="comments" name="comments" rows="3" required>{{ old('comments') }}</textarea>
                        @error('comments')<span class="field-error">{{ $message }}</span>@enderror
                        <button type="submit" class="danger-action">Return PR</button>
                    </form>
                @endif

                @if ($document->status === 'under_pr_validation')
                    <form method="POST" action="{{ route('bac-secretariat.pr.route-budget', $document) }}" onsubmit="return confirm('Route this Purchase Request to Budget Office?');" class="budget-action-form">
                        @csrf
                        @method('PATCH')
                        <label class="checkbox-row"><input type="checkbox" name="app_reference_checked" value="1" required> APP / Supplemental APP reference checked</label>
                        <label class="checkbox-row"><input type="checkbox" name="item_details_checked" value="1" required> Item details checked</label>
                        <label class="checkbox-row"><input type="checkbox" name="attachments_checked" value="1" required> Attachments checked</label>
                        <label for="remarks">Validation Remarks</label>
                        <textarea id="remarks" name="remarks" rows="3">{{ old('remarks') }}</textarea>
                        <button type="submit">Route to Budget Office</button>
                    </form>
                @endif

                @if (!in_array($document->status, ['pr_submitted', 'submitted_to_bac_secretariat', 'pr_received_by_bac_secretariat', 'under_pr_validation', 'ready_for_bac_resolution'], true))
                    <div class="empty-state">
                        <strong>No PR action available</strong>
                        <p>This Purchase Request has already moved beyond BAC Secretariat PR validation.</p>
                    </div>
                @endif
            </article>

            <article class="table-panel bac-pr-side-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">APP Reference</p>
                        <h2>Reference Status</h2>
                    </div>
                </div>

                <div class="budget-review-record bac-pr-compact-record">
                    @if ($hasPpmpAppReference)
                        <p><strong>APP Number:</strong> {{ $document->appConsolidation?->app_number ?? 'N/A' }}</p>
                        <p><strong>Fiscal Year:</strong> {{ $document->appConsolidation?->fiscal_year ?? 'N/A' }}</p>
                        <p><strong>APP Item:</strong> {{ $document->appItem?->general_description ?? 'N/A' }}</p>
                        <p><strong>Source Office:</strong> {{ $document->appItem?->office?->name ?? $document->submittingOffice?->name ?? 'N/A' }}</p>
                    @elseif ($supplementalApp)
                        <div class="empty-state success-state">
                            <strong>Supplemental APP linked</strong>
                            <p>Supplemental APP No.: {{ $supplementalApp->supplemental_app_number ?? 'Draft' }}</p>
                            <p>Status: {{ str($supplementalApp->status)->replace('_', ' ')->title() }}</p>
                            <p>Total Amount: PHP {{ number_format((float) $supplementalApp->total_amount, 2) }}</p>
                            <div class="table-actions">
                                <a href="{{ route('bac-secretariat.supplemental-apps.show', $supplementalApp) }}">View Supplemental APP</a>
                            </div>
                        </div>
                    @else
                        <div class="empty-state warning-state">
                            <strong>No PPMP/APP Record Found</strong>
                            <p>This Purchase Request has no matching PPMP/APP record. Create a Supplemental APP before proceeding.</p>
                            <div class="table-actions">
                                <a href="{{ route('bac-secretariat.supplemental-apps.create-from-pr', $document) }}">Create Supplemental APP</a>
                            </div>
                        </div>
                    @endif
                </div>
            </article>

            <article class="table-panel bac-pr-side-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Validation Summary</p>
                        <h2>Latest Validation</h2>
                    </div>
                </div>

                <div class="budget-review-record bac-pr-compact-record">
                    @if ($latestValidation)
                        <p><strong>Status:</strong> {{ str($latestValidation->validation_status)->replace('_', ' ')->title() }}</p>
                        <p><strong>Validated By:</strong> {{ $latestValidation->validatedBy?->name ?? 'N/A' }}</p>
                        <p><strong>APP Checked:</strong> {{ $latestValidation->app_reference_checked ? 'Yes' : 'No' }}</p>
                        <p><strong>Items Checked:</strong> {{ $latestValidation->item_details_checked ? 'Yes' : 'No' }}</p>
                        <p><strong>Attachments Checked:</strong> {{ $latestValidation->attachments_checked ? 'Yes' : 'No' }}</p>
                        <p><strong>Remarks:</strong> {{ $latestValidation->remarks ?? 'N/A' }}</p>
                    @else
                        <div class="empty-state">
                            <strong>No validation record yet</strong>
                            <p>Start validation to create the first PR validation record.</p>
                        </div>
                    @endif
                </div>
            </article>

        </aside>
    </section>

    <x-documents.attachments-panel
        id="supporting-files"
        :document="$document"
        document-type="purchase_request"
        :can-upload="false"
        :can-delete="false"
        title="Supporting Files"
    />

    @include('partials.svp-related-documents', ['source' => $document, 'context' => 'bac-secretariat'])

    <section class="dashboard-widget-grid bac-pr-support-sections">
        <article class="dashboard-widget widget-wide">
            <div class="widget-heading">
                <div>
                    <p class="eyebrow">Routing History</p>
                    <h2>Document Movement</h2>
                </div>
            </div>

            <ol class="routing-timeline bac-pr-routing-timeline">
                @forelse ($document->routingHistories as $history)
                    <li>
                        <strong>{{ $history->action }}</strong>
                        <span>{{ $history->action_at?->format('M d, Y h:i A') }} by {{ $history->actionBy?->name ?? 'System' }}</span>
                        <p>{{ $history->fromOffice?->name ?? 'N/A' }} to {{ $history->toOffice?->name ?? 'N/A' }} &middot; {{ str($history->status_from ?? 'new')->replace('_', ' ')->title() }} to {{ str($history->status_to)->replace('_', ' ')->title() }}</p>
                        @if ($history->comments)<p>{{ $history->comments }}</p>@endif
                    </li>
                @empty
                    <li><strong>No routing history yet</strong><span>PR workflow actions will appear here.</span></li>
                @endforelse
            </ol>
        </article>

        <article class="dashboard-widget widget-wide">
            <div class="widget-heading">
                <div>
                    <p class="eyebrow">Audit / Activity</p>
                    <h2>Related Activity</h2>
                </div>
            </div>

            <div class="my-document-activity-list">
                @forelse ($activities as $activity)
                    <div class="my-document-activity-row">
                        <strong>{{ $activity->action }}</strong>
                        <span>{{ $activity->created_at?->format('M d, Y h:i A') }} &middot; {{ $activity->user_name ?? 'System' }}</span>
                        <p>{{ $activity->description ?? 'No activity description provided.' }}</p>
                    </div>
                @empty
                    <div class="empty-state">
                        <strong>No related activity yet</strong>
                        <p>Audit entries tied to this Purchase Request will appear here.</p>
                    </div>
                @endforelse
            </div>
        </article>
    </section>
@endsection
