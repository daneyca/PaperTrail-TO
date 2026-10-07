@extends('layouts.dashboard')

@section('title', 'My Documents | PaperTrail')

@section('content')
    @php
        $officeName = $office?->name ?? auth()->user()->office ?? 'your office';
        $activeTab = $activeTab ?? request('tab', 'all');
        $activeStatus = $filters['status'] ?? request('status');
        $activeDocumentType = $filters['document_type'] ?? request('document_type');
        $activeDateFrom = $filters['date_from'] ?? request('date_from');
        $monthStart = now()->startOfMonth()->toDateString();
        $documentStatusLabel = function (?string $status): string {
            return match ($status) {
                \App\Models\ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES => 'Pending Signature',
                \App\Models\ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED => 'Signed',
                \App\Models\ProcurementDocument::STATUS_PENDING_PPMP_REVIEW => 'Submitted to BAC',
                \App\Models\ProcurementDocument::STATUS_UNDER_PPMP_REVIEW => 'Under APP Consolidation',
                \App\Models\ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION => 'Approved',
                default => str($status ?: 'Unknown')->replace('_', ' ')->title()->replace('Ppmp', 'PPMP')->toString(),
            };
        };
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Head of Office / End User</p>
            <h1>My Documents</h1>
            <p>Track all procurement documents submitted by {{ $officeName }}, including records returned for correction.</p>
        </div>
    </section>

    @if ($activeTab === 'returned')
        <section class="stat-grid stat-grid-modern head-office-summary-grid" aria-label="Returned documents summary">
            <x-dashboard.stat-card href="{{ route('head-office.documents.index', ['tab' => 'returned']) }}" value="{{ $returnedSummary['total'] }}" label="Total Returned" accent="red" class="{{ blank($activeDocumentType) && blank($activeDateFrom) ? 'is-filter-active' : '' }}" />
            <x-dashboard.stat-card href="{{ route('head-office.documents.index', ['tab' => 'returned', 'document_type' => 'PPMP']) }}" value="{{ $returnedSummary['ppmp'] }}" label="Returned PPMPs" accent="red" class="{{ $activeDocumentType === 'PPMP' ? 'is-filter-active' : '' }}" />
            <x-dashboard.stat-card href="{{ route('head-office.documents.index', ['tab' => 'returned', 'document_type' => 'pr']) }}" value="{{ $returnedSummary['pr'] }}" label="Returned PRs" accent="red" class="{{ $activeDocumentType === 'pr' ? 'is-filter-active' : '' }}" />
            <x-dashboard.stat-card href="{{ route('head-office.documents.index', ['tab' => 'returned', 'date_from' => $monthStart]) }}" value="{{ $returnedSummary['thisMonth'] }}" label="Returned This Month" accent="red" class="{{ $activeDateFrom === $monthStart ? 'is-filter-active' : '' }}" />
        </section>

        <section class="table-panel" aria-label="Returned office documents">
            <x-document-filter-card :action="route('head-office.documents.index')" class="returned-documents-filter-toolbar">
                <input type="hidden" name="tab" value="returned">

                <div class="user-search">
                    <label for="returned-search">Search Returned Documents</label>
                    <input id="returned-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Reference, PR number, tracking number, or title">
                </div>

                <div class="user-filter">
                    <label for="document-type-filter">Document Type</label>
                    <select id="document-type-filter" name="document_type">
                        <option value="">All types</option>
                        @foreach ($documentTypes as $type)
                            <option value="{{ $type }}" @selected(($filters['document_type'] ?? '') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="user-filter">
                    <label for="fiscal-year-filter">Fiscal Year</label>
                    <select id="fiscal-year-filter" name="fiscal_year">
                        <option value="">All years</option>
                        @foreach ($fiscalYears as $year)
                            <option value="{{ $year }}" @selected((string) ($filters['fiscal_year'] ?? '') === (string) $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="user-filter">
                    <label for="return-source-filter">Returned By</label>
                    <select id="return-source-filter" name="return_source">
                        <option value="">All sources</option>
                        @foreach ($returnSources as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['return_source'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="user-filter">
                    <label for="status-filter">Status</label>
                    <select id="status-filter" name="status">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $documentStatusLabel($status) }}</option>
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
                    <a href="{{ route('head-office.documents.index', ['tab' => 'returned']) }}">Clear</a>
                </div>
            </x-document-filter-card>

            <div class="table-scroll returned-documents-table-scroll">
                <table class="user-management-table returned-documents-table">
                    <thead>
                        <tr>
                            <th>Tracking Number</th>
                            <th>Document Type</th>
                            <th>Title</th>
                            <th>Fiscal Year</th>
                            <th>Returned By</th>
                            <th>Return Reason</th>
                            <th>Current Status</th>
                            <th>Current Stage</th>
                            <th>Returned Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($documents as $document)
                            @php
                                $returnMeta = $document->return_meta;
                                $canRevisePpmp = $document->document_type === 'PPMP'
                                    && $document->status === 'returned_by_bac_secretariat'
                                    && Route::has('head-office.ppmp.edit');
                                $canRevisePr = in_array($document->document_type, ['PR', 'Purchase Request'], true)
                                    && in_array($document->status, ['returned_by_pr_numbering_staff', 'returned_by_bac_secretariat'], true)
                                    && Route::has('head-office.pr.edit');
                            @endphp
                            <tr>
                                <td class="nowrap">{{ $document->displayNumber() }}</td>
                                <td>{{ $document->document_type }}</td>
                                <td><strong>{{ $document->title ?? $document->purpose ?? 'Untitled document' }}</strong></td>
                                <td>{{ $document->fiscal_year ?? 'N/A' }}</td>
                                <td>{{ $returnMeta['returned_by'] ?? $returnMeta['source'] ?? 'N/A' }}</td>
                                <td>{{ str($returnMeta['reason'] ?? 'No return reason recorded.')->limit(90) }}</td>
                                <td><span class="status-pill status-{{ $document->status }}">{{ $documentStatusLabel($document->status) }}</span></td>
                                <td>{{ $document->stage ?? 'N/A' }}</td>
                                <td class="nowrap">{{ isset($returnMeta['date']) && $returnMeta['date'] ? $returnMeta['date']->format('M d, Y') : 'N/A' }}</td>
                                <td>
                                    <div class="table-actions">
                                        <a href="{{ route('head-office.returned.show', $document) }}">View Details</a>
                                        @if ($canRevisePpmp)
                                            <a href="{{ route('head-office.ppmp.edit', $document) }}">Revise PPMP</a>
                                        @elseif ($canRevisePr)
                                            <a href="{{ route('head-office.pr.edit', $document) }}">Revise PR</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state">
                                        <strong>No returned documents</strong>
                                        <p>Documents returned to your office for correction will appear here.</p>
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
    @else
        <section class="stat-grid stat-grid-modern head-office-summary-grid my-documents-summary" aria-label="My documents summary">
            <x-dashboard.stat-card href="{{ route('head-office.documents.index') }}" value="{{ $summary['total'] }}" label="Total Documents" accent="navy" class="{{ blank($activeStatus) ? 'is-filter-active' : '' }}" />
            <x-dashboard.stat-card href="{{ route('head-office.documents.index', ['status' => 'drafts']) }}" value="{{ $summary['drafts'] }}" label="Drafts" accent="gold" class="{{ $activeStatus === 'drafts' ? 'is-filter-active' : '' }}" />
            <x-dashboard.stat-card href="{{ route('head-office.documents.index', ['status' => 'in_process']) }}" value="{{ $summary['inProcess'] }}" label="Submitted / In Process" accent="blue" class="{{ $activeStatus === 'in_process' ? 'is-filter-active' : '' }}" />
            <x-dashboard.stat-card href="{{ route('head-office.documents.index', ['tab' => 'returned']) }}" value="{{ $summary['returned'] }}" label="Returned" accent="red" />
            <x-dashboard.stat-card href="{{ route('head-office.documents.index', ['status' => 'approved']) }}" value="{{ $summary['approved'] }}" label="Approved / Accepted" accent="green" class="{{ $activeStatus === 'approved' ? 'is-filter-active' : '' }}" />
        </section>

        <section class="table-panel" aria-label="Office procurement documents">
            <x-document-filter-card :action="route('head-office.documents.index')" class="my-documents-filter-toolbar">
                <div class="user-search">
                    <label for="document-search">Search Documents</label>
                    <input id="document-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Reference, PR number, tracking number, or title">
                </div>

                <div class="user-filter">
                    <label for="document-type-filter">Document Type</label>
                    <select id="document-type-filter" name="document_type">
                        <option value="">All types</option>
                        @foreach ($documentTypes as $type)
                            <option value="{{ $type }}" @selected(($filters['document_type'] ?? '') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="user-filter">
                    <label for="fiscal-year-filter">Fiscal Year</label>
                    <select id="fiscal-year-filter" name="fiscal_year">
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
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $documentStatusLabel($status) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="user-filter">
                    <label for="stage-filter">Stage</label>
                    <select id="stage-filter" name="stage">
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
                    <a href="{{ route('head-office.documents.index') }}">Clear</a>
                </div>
            </x-document-filter-card>

            <div class="table-scroll my-documents-table-scroll">
                <table class="user-management-table my-documents-table">
                    <colgroup>
                        <col class="my-documents-col-tracking">
                        <col class="my-documents-col-type">
                        <col class="my-documents-col-title">
                        <col class="my-documents-col-year">
                        <col class="my-documents-col-amount">
                        <col class="my-documents-col-status">
                        <col class="my-documents-col-stage">
                        <col class="my-documents-col-office">
                        <col class="my-documents-col-date">
                        <col class="my-documents-col-actions">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Tracking Number</th>
                            <th>Document Type</th>
                            <th>Title</th>
                            <th>Fiscal Year</th>
                            <th>Total Amount</th>
                            <th>Current Status</th>
                            <th>Current Stage</th>
                            <th>Current Office</th>
                            <th>Submitted Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($documents as $document)
                            @php
                                $isPr = in_array($document->document_type, ['PR', 'Purchase Request'], true);
                                $isReturned = str_starts_with((string) $document->status, 'returned_') || in_array($document->status, ['pr_returned', 'returned'], true);
                                $canEdit = $document->document_type === 'PPMP'
                                    && in_array($document->status, ['ppmp_draft', 'returned_by_bac_secretariat'], true);
                                $canEditPr = $isPr
                                    && in_array($document->status, [
                                        'pr_draft',
                                        'returned_by_pr_numbering_staff',
                                        'returned_by_bac_secretariat',
                                        'returned_by_budget',
                                        'returned_by_accounting',
                                        'returned_by_bac_member',
                                        'returned_by_bac_chair',
                                        'returned_by_approving_authority',
                                        'pr_returned',
                                    ], true)
                                    && Route::has('head-office.pr.edit');
                                $editLabel = $isReturned ? 'Revise' : 'Edit';
                            @endphp
                            <tr>
                                <td class="nowrap">
                                    {{ $document->displayNumber() }}
                                    @if ($isPr && $document->pr_no)
                                        <br><span class="muted-text">Official PR: {{ $document->pr_no }}</span>
                                    @endif
                                </td>
                                <td>{{ $document->document_type }}</td>
                                <td class="my-documents-title-cell">
                                    <strong class="document-title-text">{{ str($document->title ?? $document->purpose ?? 'Untitled document')->limit(92) }}</strong>
                                </td>
                                <td>{{ $document->fiscal_year ?? 'N/A' }}</td>
                                <td class="nowrap">PHP {{ number_format((float) $document->total_amount, 2) }}</td>
                                <td><span class="status-pill status-{{ $document->status }}">{{ $documentStatusLabel($document->status) }}</span></td>
                                <td>{{ $document->stage ?? 'N/A' }}</td>
                                <td>{{ $document->currentOffice?->name ?? 'N/A' }}</td>
                                <td class="nowrap">{{ $document->submitted_at?->format('M d, Y') ?? 'Not submitted' }}</td>
                                <td class="my-documents-actions-cell">
                                    <div class="table-actions">
                                        <a href="{{ route('head-office.documents.show', $document) }}" title="View document" aria-label="View document">
                                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <path d="M2.25 12s3.5-6.25 9.75-6.25S21.75 12 21.75 12 18.25 18.25 12 18.25 2.25 12 2.25 12Z" />
                                                <circle cx="12" cy="12" r="2.75" />
                                            </svg>
                                        </a>
                                        @if ($isPr && Route::has('head-office.pr.print'))
                                            <a href="{{ route('head-office.pr.print', $document) }}" title="Print document" aria-label="Print document">
                                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <path d="M7 8V3.75h10V8" />
                                                    <path d="M7 17.5H5.25A2.25 2.25 0 0 1 3 15.25v-4.5A2.25 2.25 0 0 1 5.25 8h13.5A2.25 2.25 0 0 1 21 10.75v4.5a2.25 2.25 0 0 1-2.25 2.25H17" />
                                                    <path d="M7 14h10v6.25H7Z" />
                                                </svg>
                                            </a>
                                        @endif
                                        @if ($canEdit)
                                            <a href="{{ route('head-office.ppmp.edit', $document) }}" title="{{ $editLabel }} document" aria-label="{{ $editLabel }} document">
                                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <path d="M4 20h4.75L19.25 9.5a2.12 2.12 0 0 0-3-3L5.75 17 4 20Z" />
                                                    <path d="m14.75 8 3.25 3.25" />
                                                </svg>
                                            </a>
                                        @elseif ($canEditPr)
                                            <a href="{{ route('head-office.pr.edit', $document) }}" title="{{ $editLabel }} document" aria-label="{{ $editLabel }} document">
                                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <path d="M4 20h4.75L19.25 9.5a2.12 2.12 0 0 0-3-3L5.75 17 4 20Z" />
                                                    <path d="m14.75 8 3.25 3.25" />
                                                </svg>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state">
                                        <strong>No documents yet</strong>
                                        <p>Documents submitted by your office will appear here.</p>
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
    @endif
@endsection
