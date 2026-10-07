@extends('layouts.dashboard')

@section('title', 'Purchase Request | PaperTrail')

@section('content')
    @php
        $officeName = auth()->user()->assignedOffice?->name ?? auth()->user()->office ?? 'your office';
        $prDocumentTypes = $documentTypes->isNotEmpty() ? $documentTypes : collect(['PR']);
        $activeStatus = $filters['status'] ?? request('status');
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Head of Office / End User</p>
            <h1>Purchase Request</h1>
            <p>Prepare official LGU Purchase Request forms for {{ $officeName }} and submit them for official PR number assignment.</p>
        </div>

        <a href="{{ route('head-office.pr.create') }}" class="dashboard-action">Create PR Draft</a>
    </section>

    <section class="stat-grid stat-grid-modern head-office-summary-grid pr-summary-grid" aria-label="Purchase Request summary">
        <x-dashboard.stat-card href="{{ route('head-office.pr.index') }}" value="{{ $summary['total'] }}" label="Total PRs" accent="navy" class="{{ blank($activeStatus) ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('head-office.pr.index', ['status' => 'draft']) }}" value="{{ $summary['drafts'] }}" label="Drafts" accent="gold" class="{{ $activeStatus === 'draft' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('head-office.pr.index', ['status' => 'submitted']) }}" value="{{ $summary['submitted'] }}" label="Submitted" accent="blue" class="{{ $activeStatus === 'submitted' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('head-office.pr.index', ['status' => 'reviewed']) }}" value="{{ $summary['reviewed'] }}" label="Reviewed" accent="green" class="{{ $activeStatus === 'reviewed' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('head-office.pr.index', ['status' => 'approved']) }}" value="{{ $summary['approved'] }}" label="Approved" accent="green" class="{{ $activeStatus === 'approved' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('head-office.pr.index', ['status' => 'returned']) }}" value="{{ $summary['returned'] }}" label="Returned" accent="red" class="{{ $activeStatus === 'returned' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('head-office.pr.index') }}" value="PHP {{ number_format((float) $summary['total_amount'], 2) }}" label="Total Amount" accent="navy" />
    </section>

    <section class="table-panel" aria-label="Head Office Purchase Request records">
        <x-document-filter-card :action="route('head-office.pr.index')" class="my-documents-filter-toolbar">
            <div class="user-search">
                <label for="pr-search">Search Documents</label>
                <input id="pr-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Tracking number, official PR number, or title">
            </div>

            <div class="user-filter">
                <label for="pr-document-type-filter">Document Type</label>
                <select id="pr-document-type-filter" name="document_type">
                    <option value="">All types</option>
                    @foreach ($prDocumentTypes as $type)
                        <option value="{{ $type }}" @selected(($filters['document_type'] ?? '') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="fiscal-year">Fiscal Year</label>
                <select id="fiscal-year" name="fiscal_year">
                    <option value="">All years</option>
                    @foreach ($fiscalYears as $year)
                        <option value="{{ $year }}" @selected((string) ($filters['fiscal_year'] ?? '') === (string) $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="status-filter">Status</label>
                <select id="status-filter" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="pr-stage-filter">Stage</label>
                <select id="pr-stage-filter" name="stage">
                    <option value="">All stages</option>
                    @foreach ($stages as $stage)
                        <option value="{{ $stage }}" @selected(($filters['stage'] ?? '') === $stage)>{{ $stage }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="date-from">Date From</label>
                <input id="date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}">
            </div>

            <div class="user-filter">
                <label for="date-to">Date To</label>
                <input id="date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}">
            </div>

            <div class="filter-actions">
                <button type="submit">Apply</button>
                <a href="{{ route('head-office.pr.index') }}">Clear</a>
            </div>
        </x-document-filter-card>

        <div class="table-scroll pr-registry-table-scroll">
            <table class="user-management-table head-office-doc-table pr-registry-table">
                <colgroup>
                    <col class="pr-list-col-number">
                    <col class="pr-list-col-reference">
                    <col class="pr-list-col-title">
                    <col class="pr-list-col-status">
                    <col class="pr-list-col-updated">
                    <col class="pr-list-col-actions">
                </colgroup>
                <thead>
                    <tr>
                        <th>Official PR No.</th>
                        <th>Tracking Number</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th class="actions-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        @php
                            $returnedStatuses = [
                                'returned_by_pr_numbering_staff',
                                'returned_by_bac_secretariat',
                                'returned_by_budget',
                                'returned_by_accounting',
                                'returned_by_bac_member',
                                'returned_by_bac_chair',
                                'returned_by_approving_authority',
                                'pr_returned',
                                'returned',
                            ];
                            $statusLabels = [
                                'pr_draft' => 'Draft',
                                'pr_pending_signatories' => 'Pending Signatures',
                                'pr_signatories_completed' => 'Ready for PR Number',
                                'pending_pr_number_assignment' => 'Pending PR Number',
                                'pr_number_assigned' => 'PR Number Assigned',
                                'pr_number_assigned_returned_to_end_user' => 'Returned to Requesting Office',
                                'returned_by_pr_numbering_staff' => 'Returned',
                                'pr_submitted' => 'PR Submitted',
                                'pr_received_by_bac_secretariat' => 'Received',
                                'under_pr_validation' => 'Under Validation',
                                'ready_for_bac_resolution' => 'PR Copy Sent to BACSEC-002',
                                'returned_by_bac_secretariat' => 'Returned',
                                'returned_by_budget' => 'Returned',
                                'returned_by_accounting' => 'Returned',
                                'returned_by_bac_member' => 'Returned',
                                'returned_by_bac_chair' => 'Returned',
                                'returned_by_approving_authority' => 'Returned',
                                'pending_budget_review' => 'Budget Review',
                                'pending_accounting_review' => 'Accounting Review',
                                'pending_approval' => 'Pending Approval',
                                'approved' => 'Approved',
                                'ready_for_po' => 'Ready for PO',
                            ];
                            $canEdit = in_array($document->status, array_merge(['pr_draft'], $returnedStatuses), true);
                            $editLabel = in_array($document->status, $returnedStatuses, true) ? 'Continue Editing' : 'Continue Editing';
                            $rawTitle = $document->title ?: 'Purchase Request';
                            $displayTitle = str_starts_with($rawTitle, 'Purchase Request -') ? 'Purchase Request' : $rawTitle;
                            $statusLabel = $statusLabels[$document->status] ?? str($document->status)->replace('_', ' ')->title();
                            $documentNumber = $document->pr_no ?? 'To be assigned';
                        @endphp
                        <tr>
                            <td class="nowrap">{{ $documentNumber }}</td>
                            <td class="nowrap">{{ $document->displayNumber() }}</td>
                            <td class="pr-list-title">
                                <strong class="pr-list-title-text">{{ str($displayTitle)->limit(82) }}</strong>
                            </td>
                            <td>
                                <span class="status-pill status-badge status-{{ $document->status }}">{{ $statusLabel }}</span>
                                @if ($document->stage || $document->currentOffice)
                                    <span class="pr-status-subtext">
                                        {{ $document->stage ?? 'In workflow' }}
                                        @if ($document->currentOffice)
                                            &middot; {{ $document->currentOffice->name }}
                                        @endif
                                    </span>
                                @endif
                            </td>
                            <td class="nowrap">{{ $document->updated_at?->format('M d, Y h:i A') ?? 'N/A' }}</td>
                            <td class="doc-actions-cell">
                                <div class="table-actions pr-list-actions">
                                    @if ($canEdit)
                                        <a href="{{ route('head-office.pr.edit', $document) }}">{{ $editLabel }}</a>
                                    @endif
                                    <a href="{{ route('head-office.pr.show', $document) }}">View</a>
                                    <a href="{{ route('head-office.pr.print', $document) }}">Print</a>
                                    <a href="{{ route('head-office.pr.show', $document) }}#supporting-files">Supporting Files</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <strong>No Purchase Requests yet</strong>
                                    <p>Create a PR draft using the official LGU Purchase Request form structure.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($documents->hasPages())
            <div class="pagination-wrap">
                {{ $documents->appends(request()->query())->links('vendor.pagination.papertrail') }}
            </div>
        @endif
    </section>
@endsection
