@extends('layouts.dashboard')

@section('title', 'Create SVP Posting | PaperTrail')

@section('content')
    @php
        $pr = $chain?->sourcePrDocument;
        $resolution = $chain?->bacResolution;
        $posting = $postingRecord;
        $selectedStatus = old('status', $posting?->status ?? \App\Models\SvpPostingRecord::STATUS_PENDING_POSTING);
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Posting Management</p>
            <h1>Create Posting</h1>
            <p>Record the posting details and evidence for SVP transactions assigned to BACSEC-004.</p>
        </div>

        <a href="{{ route('bac-secretariat.svp-posting.pending') }}" class="dashboard-action secondary-action">Pending Posting</a>
    </section>

    <x-documents.document-create-flow
        eyebrow="Posting Management"
        title="Select assigned posting task"
        description="Load an SVP posting task first, then proceed to the posting details form."
        proceed-label="Proceed to Posting Details"
        :back-url="route('bac-secretariat.svp-posting.pending')"
        back-label="Pending Posting"
        :autoshow="$errors->any()"
        error-message="Choose and load a posting task before proceeding."
        :workflow-steps="['Select Procurement Record', 'Verify Posting Information', 'Create Posting', 'Confirm']"
        :form-step="3"
    >
        <x-slot:source>
            <form method="GET" action="{{ route('bac-secretariat.svp-posting.create') }}" class="svp-posting-select-form">
                <input type="hidden" value="{{ $chain?->id }}" data-document-create-required>
                <label for="chain_id">
                    Pending SVP Posting
                    <select id="chain_id" name="chain_id">
                        <option value="">Choose a PR for posting</option>
                        @foreach ($pendingChains as $pendingChain)
                            @php
                                $pendingPr = $pendingChain->sourcePrDocument;
                            @endphp
                            <option value="{{ $pendingChain->id }}" @selected((string) ($chain?->id) === (string) $pendingChain->id)>
                                {{ $pendingPr?->pr_no ?? $pendingPr?->tracking_number ?? $pendingChain->tracking_number ?? 'PR Reference' }}
                                - {{ $pendingChain->office_name ?? $pendingPr?->submittingOffice?->name ?? 'Requesting Office' }}
                                - PHP {{ number_format((float) $pendingChain->total_amount, 2) }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="dashboard-action secondary-action">Load Task</button>
            </form>
        </x-slot:source>

        <x-slot:form>
            @if ($chain)
                <section class="svp-summary-strip" aria-label="Posting task summary">
                    <div>
                        <span>PR Reference</span>
                        <strong>{{ $pr?->pr_no ?? $pr?->tracking_number ?? $chain->tracking_number ?? 'N/A' }}</strong>
                    </div>
                    <div>
                        <span>Procurement Title</span>
                        <strong>{{ $pr?->title ?? $pr?->description ?? 'SVP Posting' }}</strong>
                    </div>
                    <div>
                        <span>Requesting Office</span>
                        <strong>{{ $chain->office_name ?? $pr?->submittingOffice?->name ?? 'N/A' }}</strong>
                    </div>
                    <div>
                        <span>Amount</span>
                        <strong>PHP {{ number_format((float) $chain->total_amount, 2) }}</strong>
                    </div>
                    <div>
                        <span>Assigned Account</span>
                        <strong>BACSEC-004</strong>
                    </div>
                </section>

                <section class="table-panel svp-posting-form-panel" aria-label="SVP posting form">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Posting Required</p>
                            <h2>Posting Details</h2>
                            <p>Save the posting information, then mark the record as posted to return the PR for RFQ preparation.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('bac-secretariat.svp-posting.store') }}" enctype="multipart/form-data" class="svp-posting-form">
                        @csrf
                        <input type="hidden" name="svp_procurement_chain_id" value="{{ $chain->id }}">
                        <input type="hidden" name="requesting_office" value="{{ old('requesting_office', $posting?->requesting_office ?? $chain->office_name ?? $pr?->submittingOffice?->name) }}">
                        <input type="hidden" name="procurement_method" value="{{ old('procurement_method', $posting?->procurement_method ?? 'SVP') }}">
                        <input type="hidden" name="approved_budget" value="{{ old('approved_budget', $posting?->approved_budget ?? $chain->total_amount) }}">

                        <div class="svp-posting-grid">
                            <label>
                                <span>PR Number</span>
                                <input name="pr_reference" type="text" value="{{ old('pr_reference', $posting?->pr_reference ?? $pr?->pr_no ?? $pr?->tracking_number ?? $chain->tracking_number) }}">
                            </label>
                            <label>
                                <span>BAC Resolution Reference</span>
                                <input name="bac_resolution_reference" type="text" value="{{ old('bac_resolution_reference', $posting?->bac_resolution_reference ?? $resolution?->displayNumber() ?? $resolution?->resolution_number) }}">
                            </label>
                            <label>
                                <span>Procurement Title</span>
                                <input name="procurement_title" type="text" value="{{ old('procurement_title', $posting?->procurement_title ?? $pr?->title ?? $pr?->description ?? 'SVP Posting') }}">
                            </label>
                            <label>
                                <span>Posting Platform</span>
                                <input name="posting_platform" type="text" value="{{ old('posting_platform', $posting?->posting_platform ?? 'PhilGEPS') }}">
                            </label>
                            <label>
                                <span>PhilGEPS Reference Number</span>
                                <input name="philgeps_reference_number" type="text" value="{{ old('philgeps_reference_number', $posting?->philgeps_reference_number) }}" placeholder="Sample reference number">
                            </label>
                            <label>
                                <span>Posting Date</span>
                                <input name="posting_date" type="date" value="{{ old('posting_date', optional($posting?->posting_date)->format('Y-m-d')) }}">
                            </label>
                            <label>
                                <span>Closing Date</span>
                                <input name="closing_date" type="date" value="{{ old('closing_date', optional($posting?->closing_date)->format('Y-m-d')) }}">
                            </label>
                            <label>
                                <span>Status</span>
                                <select name="status">
                                    @foreach ($statuses as $value => $label)
                                        <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <label class="svp-posting-remarks">
                            <span>Remarks</span>
                            <textarea name="remarks" rows="4" placeholder="Posting notes, publication reference, or completion remarks">{{ old('remarks', $posting?->remarks) }}</textarea>
                        </label>

                        @if ($errors->any())
                            <div class="attachments-readonly-note">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <div class="svp-posting-actions">
                            <a href="{{ route('bac-secretariat.svp-posting.pending') }}" class="dashboard-action secondary-action">Back</a>
                            <button type="submit" name="action" value="save" class="dashboard-action secondary-action">Save Posting Information</button>
                            <button
                                type="submit"
                                name="action"
                                value="mark_posted"
                                class="dashboard-action"
                                data-confirm="This records a sample posting entry and returns the PR to the procurement processor for RFQ preparation."
                                data-confirm-title="Mark Posting as Posted?"
                                data-confirm-label="Mark as Posted"
                                data-confirm-type="submit"
                            >
                                Mark as Posted
                            </button>
                        </div>
                    </form>
                </section>

                @if ($posting)
                    <x-documents.attachments-panel
                        :document="$posting"
                        document-type="svp_posting"
                        title="Posting Evidence"
                        :can-upload="$posting->isEditable()"
                    />
                @endif
            @else
                <section class="table-panel">
                    <div class="empty-state">
                        <strong>No posting task loaded</strong>
                        <p>Choose an assigned SVP posting task and click Load Task before proceeding.</p>
                    </div>
                </section>
            @endif
        </x-slot:form>
    </x-documents.document-create-flow>
@endsection
