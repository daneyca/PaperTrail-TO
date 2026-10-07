@extends('layouts.dashboard')

@section('title', 'Create Purchase Request | PaperTrail')

@section('content')
    @php
        $officeName = $office?->name ?? $user->assignedOffice?->name ?? $user->office ?? 'your office';
        $canChooseRequestingOffice = $canSelectRequestingOffice ?? false;
        $selectedRequestingOfficeId = old('requesting_office_id', $office?->id);
        $hasBacsec002PrCapability = method_exists($user, 'hasBacsec002PurchaseRequestCapability') && $user->hasBacsec002PurchaseRequestCapability();
        $usesPrSignatureRouting = $hasBacsec002PrCapability || $user->roleSlug() === 'head-office';
        $roleLabel = $hasBacsec002PrCapability ? 'BAC Secretariat' : 'Head of Office / End User';
        $status = $document->status ?: 'pr_draft';
        $submitButtonLabel = $usesPrSignatureRouting ? 'Submit for Signatures' : 'Submit for PR Number';
        $submitConfirmMessage = $usesPrSignatureRouting
            ? 'Generate signature requests for this Purchase Request?'
            : 'Submit this Purchase Request to PR Numbering Staff?';
        $selectedAppItem = $selectedAppItem ?? null;
        $appReferenceItems = collect($appReferenceItems ?? []);
        $ppmpSupplyItems = collect($ppmpSupplyItems ?? []);
        $recentDraftCollection = collect($recentDrafts ?? [])->values();
        $recentDraftLimit = 5;
        $visibleRecentDrafts = $recentDraftCollection->take($recentDraftLimit);
        $recentDraftTotal = $recentDraftCollection->count();
        $hasMoreRecentDrafts = $recentDraftTotal > $recentDraftLimit;
        $recentDraftVisibleCount = min($recentDraftTotal, $recentDraftLimit);
        $statusLabels = [
            'pr_draft' => 'Draft',
            'pr_pending_signatories' => 'Pending Signatures',
            'pr_signatories_completed' => 'Ready for PR Number',
            'pending_pr_number_assignment' => 'Pending PR Number',
            'returned_by_pr_numbering_staff' => 'Returned',
            'pr_submitted' => 'Submitted',
            'returned_by_bac_secretariat' => 'Returned',
            'returned_by_budget' => 'Returned',
            'returned_by_accounting' => 'Returned',
            'returned_by_bac_member' => 'Returned',
            'returned_by_bac_chair' => 'Returned',
            'returned_by_approving_authority' => 'Returned',
            'pr_returned' => 'Returned',
        ];
        $statusLabel = $statusLabels[$status] ?? str($status)->replace('_', ' ')->title();
    @endphp

    @if (! $selectedAppItem)
        <section class="pr-workspace pr-create-split-workspace no-print">
            <section class="pr-page-header pr-create-split-header no-print">
                <div>
                    <h1 class="pr-page-title">Create Purchase Request</h1>
                    <p class="pr-page-subtitle">Create a PR from an approved APP project.</p>
                </div>
                <a href="{{ route('head-office.pr.index') }}" class="btn-pr-secondary pr-create-header-action">Back to PR List</a>
            </section>

            @include('head-office.pr._workflow-steps', ['activeStep' => 1])

            @if ($errors->any())
                <div class="session-alert session-alert-error pr-form-errors no-print" role="alert">
                    <strong>Please correct the following errors:</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="pr-create-split-layout">
                <aside class="pr-create-drafts-pane" aria-labelledby="recent-pr-drafts-title">
                    <header class="pr-create-pane-heading">
                        <div>
                            <p class="pr-page-kicker">Recent Drafts</p>
                            <h2 id="recent-pr-drafts-title">Recent PR Drafts</h2>
                        </div>
                        <span>{{ $recentDraftVisibleCount }}</span>
                    </header>

                    <div class="pr-create-draft-list">
                        @forelse ($visibleRecentDrafts as $draft)
                            @php
                                $draftNumber = $draft['number'] ?? 'PR Draft';
                                $draftStatus = $draft['status_label'] ?? str($draft['status'] ?? 'draft')->replace(['_', '-'], ' ')->title();
                                $draftEditUrl = $draft['edit_url'] ?? null;
                                $draftShowUrl = $draft['show_url'] ?? null;
                            @endphp

                            <article class="pr-create-draft-card pr-create-draft-card--compact">
                                <strong class="pr-create-draft-number">{{ $draftNumber }}</strong>
                                <span class="document-create-draft-status">{{ $draftStatus }}</span>

                                <div class="document-create-draft-icon-actions">
                                    @if ($draftEditUrl)
                                        <a href="{{ $draftEditUrl }}" class="pr-create-draft-icon-action" aria-label="Edit draft" title="Edit draft">
                                            <x-papertrail.icon name="edit" />
                                        </a>
                                    @endif

                                    @if ($draftShowUrl)
                                        <a href="{{ $draftShowUrl }}" class="pr-create-draft-icon-action" aria-label="View draft" title="View draft">
                                            <x-papertrail.icon name="view" />
                                        </a>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="pr-create-drafts-empty">
                                <strong>No draft Purchase Requests yet.</strong>
                                <p>Your unfinished PRs will appear here.</p>
                            </div>
                        @endforelse
                    </div>

                    @if ($hasMoreRecentDrafts)
                        <a href="{{ route('head-office.pr.index', ['status' => 'draft']) }}" class="pr-create-view-drafts">
                            View All Drafts
                        </a>
                    @endif
                </aside>

                <section class="pr-create-app-pane" aria-labelledby="pr-app-picker-title">
                    <div class="pr-create-pane-heading pr-create-app-heading">
                        <div>
                            <p class="pr-page-kicker">Approved APP Items</p>
                            <h2 id="pr-app-picker-title">Select an Approved APP Item</h2>
                        </div>
                        <div class="pr-create-context-chips" aria-label="Current PR context">
                            <span>FY {{ now()->year }}</span>
                            <span>{{ $appReferenceItems->count() }} available</span>
                        </div>
                    </div>

                    <div class="pr-app-reference-filter pr-create-app-filter">
                        <label for="appProjectPickerSearch">
                            <span class="sr-only">Search approved APP items</span>
                            <input id="appProjectPickerSearch" type="search" placeholder="Search APP number, project, office, or description..." data-pr-app-picker-search>
                        </label>

                        @if ($canChooseRequestingOffice)
                            <label for="requestingOfficeId">
                                Requesting Office
                                <select id="requestingOfficeId" class="pr-context-select" data-pr-picker-office-select>
                                    @foreach (($requestingOffices ?? collect()) as $requestingOffice)
                                        <option
                                            value="{{ $requestingOffice->id }}"
                                            @selected((string) $selectedRequestingOfficeId === (string) $requestingOffice->id)
                                        >
                                            {{ $requestingOffice->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                    </div>

                    <div class="pr-app-reference-table-wrap pr-create-app-table-wrap">
                        <table class="pr-app-reference-table pr-app-picker-table pr-create-app-table">
                            <thead>
                                <tr>
                                    <th>Project / Description</th>
                                    <th>APP Reference</th>
                                    <th>Office</th>
                                    <th>Approved Budget</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($appReferenceItems as $appItem)
                                    @php
                                        $app = $appItem->appConsolidation;
                                        $officeNameForRow = $appItem->office?->name ?? 'All offices';
                                        $description = trim((string) $appItem->general_description);
                                        $projectTitle = trim((string) preg_replace('/^Purchase\s+of\s+/i', '', $description));
                                        $projectTitle = $projectTitle !== '' ? $projectTitle : ($description ?: 'APP Project');
                                        $quantity = (float) ($appItem->quantity ?? 0);
                                        $unitCost = (float) ($appItem->estimated_unit_cost ?? 0);
                                        $totalCost = (float) ($appItem->estimated_total_cost ?? ($quantity * $unitCost));
                                        $appStatusLabel = str($app?->status ?? 'verified')->replace(['_', '-'], ' ')->title();
                                        $rowRequestingOfficeId = $canChooseRequestingOffice
                                            ? ($appItem->office_id ?: $selectedRequestingOfficeId)
                                            : null;
                                        $createUrl = route('head-office.pr.create', array_filter([
                                            'app_item_id' => $appItem->id,
                                            'requesting_office_id' => $rowRequestingOfficeId,
                                            'start_pr' => 1,
                                        ], fn ($value) => filled($value)));
                                        $budgetLabel = 'PHP ' . number_format($totalCost, 2);
                                        $searchText = strtolower(implode(' ', [
                                            $app?->app_number,
                                            $projectTitle,
                                            $description,
                                            $officeNameForRow,
                                            $appItem->procurement_mode,
                                            $appItem->category,
                                        ]));
                                    @endphp
                                    <tr
                                        data-pr-app-picker-row
                                        data-office-id="{{ $appItem->office_id }}"
                                        data-app-item-id="{{ $appItem->id }}"
                                        data-base-url="{{ route('head-office.pr.create', ['app_item_id' => $appItem->id, 'start_pr' => 1]) }}"
                                        data-search="{{ $searchText }}"
                                    >
                                        <td>
                                            <strong>{{ $projectTitle }}</strong>
                                            @if ($description !== '' && strcasecmp($description, $projectTitle) !== 0)
                                                <small>{{ $description }}</small>
                                            @endif
                                        </td>
                                        <td><strong>{{ $app?->app_number ?? 'APP Reference' }}</strong></td>
                                        <td>{{ $officeNameForRow }}</td>
                                        <td>{{ $budgetLabel }}</td>
                                        <td><span class="pr-app-reference-badge is-verified">{{ $appStatusLabel }}</span></td>
                                        <td>
                                            <a class="btn-pr-primary pr-app-select-btn" href="{{ $createUrl }}" data-pr-app-select-link>
                                                Select
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6">
                                            <div class="empty-state">
                                                <strong>No APP projects available</strong>
                                                <p>APP project titles will appear here after APP consolidation is saved and made available for PR creation.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="pr-app-reference-empty" data-pr-app-picker-empty hidden>
                        <strong>No matching APP projects</strong>
                        <p>Adjust the search or requesting office filter.</p>
                    </div>
                </section>
            </div>
        </section>
    @elseif (! $startPrForm)
        @php
            $selectedApp = $selectedAppItem->appConsolidation;
            $selectedDescription = trim((string) $selectedAppItem->general_description);
            $selectedProjectTitle = trim((string) preg_replace('/^Purchase\s+of\s+/i', '', $selectedDescription));
            $selectedProjectTitle = $selectedProjectTitle !== '' ? $selectedProjectTitle : ($selectedDescription ?: 'APP Project');
            $selectedQuantity = (float) ($selectedAppItem->quantity ?? 0);
            $selectedUnitCost = (float) ($selectedAppItem->estimated_unit_cost ?? 0);
            $selectedTotal = (float) ($selectedAppItem->estimated_total_cost ?? ($selectedQuantity * $selectedUnitCost));
            $selectedStatusLabel = str($selectedApp?->status ?? 'verified')->replace(['_', '-'], ' ')->title();
            $selectedOfficeName = $selectedAppItem->office?->name ?? $officeName;
            $selectedRequestingOfficeForUrl = $canChooseRequestingOffice
                ? ($selectedAppItem->office_id ?: $selectedRequestingOfficeId)
                : null;
            $appListUrl = route('head-office.pr.create', array_filter([
                'requesting_office_id' => $canChooseRequestingOffice ? $selectedRequestingOfficeForUrl : null,
            ], fn ($value) => filled($value)));
            $startPrUrl = route('head-office.pr.create', array_filter([
                'app_item_id' => $selectedAppItem->id,
                'requesting_office_id' => $selectedRequestingOfficeForUrl,
                'start_pr' => 1,
            ], fn ($value) => filled($value)));
        @endphp

        <section class="pr-workspace pr-app-confirm-workspace no-print">
            <section class="pr-page-header no-print">
                <div>
                    <p class="pr-page-kicker">Head Office Purchase Request</p>
                    <h1 class="pr-page-title">Create Purchase Request</h1>
                    <p class="pr-page-subtitle">Review the selected APP before opening the official PR form.</p>
                </div>
            </section>

            @include('head-office.pr._workflow-steps', ['activeStep' => 2])

            <section class="pr-app-confirm-card" aria-labelledby="pr-confirm-app-title">
                <div class="pr-app-create-panel-heading">
                    <p class="pr-page-kicker">Step 2</p>
                    <h2 id="pr-confirm-app-title">Select APP, Then Create PR</h2>
                    <p>Please confirm the APP item below before creating the Purchase Request.</p>
                </div>

                <div class="pr-confirm-info-banner">
                    <strong>Selected APP</strong>
                    <span>{{ $selectedApp?->app_number ?? 'APP Reference' }} - {{ $selectedProjectTitle }}</span>
                </div>

                <dl class="pr-selected-app-details">
                    <div>
                        <dt>APP No.</dt>
                        <dd>{{ $selectedApp?->app_number ?? 'APP Reference' }}</dd>
                    </div>
                    <div>
                        <dt>Project Title</dt>
                        <dd>{{ $selectedProjectTitle }}</dd>
                    </div>
                    <div>
                        <dt>Description</dt>
                        <dd>{{ $selectedDescription ?: $selectedProjectTitle }}</dd>
                    </div>
                    <div>
                        <dt>Office</dt>
                        <dd>{{ $selectedOfficeName }}</dd>
                    </div>
                    <div>
                        <dt>Approved Budget</dt>
                        <dd>PHP {{ number_format($selectedTotal, 2) }}</dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd><span class="pr-app-reference-badge is-verified">{{ $selectedStatusLabel }}</span></dd>
                    </div>
                </dl>

                <div class="pr-confirm-actions">
                    <a href="{{ $startPrUrl }}" class="btn-pr-primary pr-selected-create-btn">
                        Create PR
                    </a>
                    <a href="{{ $appListUrl }}" class="btn-pr-secondary pr-selected-back-btn">
                        Back to APP List
                    </a>
                </div>
            </section>
        </section>
    @else
    @php
        $initialPrStage = ($errors->any() || filled(old('form_action'))) ? 'form' : 'source';
    @endphp

    <form
        id="purchaseRequestForm"
        method="POST"
        action="{{ route('head-office.pr.store') }}"
        class="pr-workspace pr-workspace-form pr-create-flow is-{{ $initialPrStage }}-stage"
        data-purchase-request-form
        data-pr-create-flow
        data-pr-initial-stage="{{ $initialPrStage }}"
    >
        <input type="hidden" name="form_action" id="purchaseRequestFormAction" value="{{ old('form_action', 'save_draft') }}">

        <section class="pr-page-header no-print">
            <div>
                <p class="pr-page-kicker">Head Office Purchase Request</p>
                <h1 class="pr-page-title">Create Purchase Request Draft</h1>
                <p class="pr-page-subtitle">Add the needed source items first, then proceed to the official LGU Purchase Request form.</p>
            </div>
        </section>

        @if ($canChooseRequestingOffice)
            <input type="hidden" name="requesting_office_id" value="{{ $selectedRequestingOfficeId }}">
        @endif

        @include('head-office.pr._workflow-steps', [
            'activeStep' => $initialPrStage === 'form' ? 3 : 2,
            'flowAware' => true,
        ])

        @if ($errors->any())
            <div class="session-alert session-alert-error pr-form-errors no-print" role="alert">
                <strong>Please correct the following errors:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="pr-create-stage-shell" data-pr-create-stage-shell>
            <section
                class="pr-create-stage-panel pr-create-source-stage {{ $initialPrStage === 'source' ? 'is-active' : '' }}"
                data-pr-create-stage-panel="source"
                aria-hidden="{{ $initialPrStage === 'source' ? 'false' : 'true' }}"
            >
                <div class="pr-create-source-split">
                    <aside class="pr-create-source-context-pane">
                        @include('head-office.pr._budget-guard', ['appBudgetInfo' => $appBudgetInfo ?? null])

                        @include('head-office.pr._app-reference-selector', [
                            'document' => $document,
                            'appReferenceItems' => $appReferenceItems ?? collect(),
                            'selectedAppItem' => $selectedAppItem,
                            'referenceMode' => 'summary',
                            'changeAppUrl' => route('head-office.pr.create', array_filter([
                                'requesting_office_id' => $canChooseRequestingOffice ? $selectedRequestingOfficeId : null,
                            ], fn ($value) => filled($value))),
                        ])
                    </aside>

                    <section class="pr-create-source-items-pane" aria-label="Select APP items">
                        <section class="pr-source-step-footer no-print" aria-label="Proceed to Purchase Request form">
                            <div>
                                <p class="pr-page-kicker">Ready for PR Form</p>
                                <strong data-pr-selected-item-count>0 items selected</strong>
                                <span>Added source items are copied into the official PR worksheet.</span>
                            </div>
                            <div class="pr-source-step-actions">
                                <a
                                    href="{{ route('head-office.pr.create', array_filter([
                                        'requesting_office_id' => $canChooseRequestingOffice ? $selectedRequestingOfficeId : null,
                                    ], fn ($value) => filled($value))) }}"
                                    class="btn-pr-secondary"
                                >
                                    Change APP
                                </a>
                                <button type="button" class="btn-pr-primary" data-pr-stage-proceed>
                                    Proceed to PR Form
                                </button>
                            </div>
                        </section>

                        @include('head-office.pr._ppmp-supply-picker', [
                            'selectedAppItem' => $selectedAppItem,
                            'ppmpSupplyItems' => $ppmpSupplyItems,
                        ])
                    </section>
                </div>
            </section>

            <section
                class="pr-create-stage-panel pr-create-form-stage {{ $initialPrStage === 'form' ? 'is-active' : '' }}"
                data-pr-create-stage-panel="form"
                aria-hidden="{{ $initialPrStage === 'form' ? 'false' : 'true' }}"
            >
                <section class="pr-action-toolbar no-print" aria-label="Purchase Request actions">
                    <div class="pr-toolbar-group">
                        <button type="button" class="btn-pr-secondary" data-pr-stage-back>Back to Items</button>
                        <a href="{{ route('head-office.pr.index') }}" class="btn-pr-secondary">Back</a>
                    </div>
                    <div class="pr-toolbar-group">
                        <button type="button" class="btn-pr-secondary" data-add-pr-item>Add Row</button>
                        <button type="button" class="btn-pr-secondary" data-remove-last-pr-item>Remove Last Row</button>
                        <button type="button" id="removeEmptyPrRowsBtn" class="btn-pr-secondary" data-clear-empty-pr-items>Remove Empty Rows</button>
                        <button type="submit" form="purchaseRequestForm" name="form_action" value="save_draft" class="btn-pr-dark" data-pr-draft-action>Save Draft</button>
                        <button type="submit" form="purchaseRequestForm" name="form_action" value="submit_for_pr_number" class="btn-pr-primary" id="submitForPrNumberBtn" data-submit-pr-number data-submit-confirm="{{ $submitConfirmMessage }}">{{ $submitButtonLabel }}</button>
                    </div>
                </section>

                <div class="pr-preview-area">
                    @include('head-office.pr._form')
                </div>
            </section>
        </div>
    </form>
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const pickerOfficeSelect = document.querySelector('[data-pr-picker-office-select]');
            const pickerSearch = document.querySelector('[data-pr-app-picker-search]');
            const pickerRows = [...document.querySelectorAll('[data-pr-app-picker-row]')];
            const pickerEmpty = document.querySelector('[data-pr-app-picker-empty]');

            if (pickerRows.length) {
                const updatePickerLinks = () => {
                    const selectedOfficeId = pickerOfficeSelect?.value || '';

                    pickerRows.forEach((row) => {
                        const link = row.querySelector('[data-pr-app-select-link]');
                        const rowOfficeId = row.dataset.officeId || selectedOfficeId;
                        const url = new URL(row.dataset.baseUrl, window.location.origin);

                        if (rowOfficeId) {
                            url.searchParams.set('requesting_office_id', rowOfficeId);
                        }

                        if (link) {
                            link.href = `${url.pathname}${url.search}`;
                        }
                    });
                };

                const filterPickerRows = () => {
                    const query = (pickerSearch?.value || '').toLowerCase().trim();
                    const officeId = pickerOfficeSelect?.value || '';
                    let visibleCount = 0;

                    pickerRows.forEach((row) => {
                        const rowOfficeId = row.dataset.officeId || '';
                        const matchesOffice = !officeId || !rowOfficeId || rowOfficeId === officeId;
                        const matchesSearch = !query || (row.dataset.search || '').includes(query);
                        const visible = matchesOffice && matchesSearch;

                        row.hidden = !visible;

                        if (visible) {
                            visibleCount++;
                        }
                    });

                    if (pickerEmpty) {
                        pickerEmpty.hidden = visibleCount > 0;
                    }

                    updatePickerLinks();
                };

                pickerSearch?.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                    }
                });
                pickerSearch?.addEventListener('input', filterPickerRows);
                pickerOfficeSelect?.addEventListener('change', filterPickerRows);
                filterPickerRows();
            }

            const officeSelect = document.querySelector('[data-requesting-office-select]');
            const departmentInput = document.querySelector('[data-pr-department-name]');
            const createFlow = document.querySelector('[data-pr-create-flow]');

            if (createFlow) {
                const panels = [...createFlow.querySelectorAll('[data-pr-create-stage-panel]')];
                const selectedCount = createFlow.querySelector('[data-pr-selected-item-count]');
                const proceedButton = createFlow.querySelector('[data-pr-stage-proceed]');
                const backButton = createFlow.querySelector('[data-pr-stage-back]');
                const flowSteps = [...createFlow.querySelectorAll('[data-pr-flow-step]')];

                const rowIsEmpty = (row) => {
                    if (window.PaperTrailRowTools?.isRowEmpty) {
                        return window.PaperTrailRowTools.isRowEmpty(row, '.pr-excel-cell');
                    }

                    return [...row.querySelectorAll('.pr-excel-cell')]
                        .every((field) => !(field.value || '').trim());
                };

                const selectedItemCount = () => [...createFlow.querySelectorAll('.pr-item-row')]
                    .filter((row) => !rowIsEmpty(row))
                    .length;

                const updateSelectedCount = () => {
                    if (!selectedCount) {
                        return;
                    }

                    const count = selectedItemCount();
                    selectedCount.textContent = `${count} item${count === 1 ? '' : 's'} selected`;
                };

                const setPrStage = (stage) => {
                    createFlow.dataset.prCurrentStage = stage;
                    createFlow.classList.toggle('is-source-stage', stage === 'source');
                    createFlow.classList.toggle('is-form-stage', stage === 'form');
                    const activeStep = stage === 'form' ? 3 : 2;

                    panels.forEach((panel) => {
                        const active = panel.dataset.prCreateStagePanel === stage;

                        panel.classList.toggle('is-active', active);
                        panel.setAttribute('aria-hidden', active ? 'false' : 'true');
                    });

                    flowSteps.forEach((step) => {
                        const stepNumber = Number.parseInt(step.dataset.prFlowStep || '0', 10);

                        step.classList.toggle('is-complete', stepNumber < activeStep);
                        step.classList.toggle('is-active', stepNumber === activeStep);
                        step.classList.toggle('is-upcoming', stepNumber > activeStep);
                    });

                    updateSelectedCount();
                    createFlow.scrollIntoView({ behavior: 'smooth', block: 'start' });
                };

                proceedButton?.addEventListener('click', () => setPrStage('form'));
                backButton?.addEventListener('click', () => setPrStage('source'));
                document.addEventListener('papertrail:pr-items-changed', updateSelectedCount);
                createFlow.addEventListener('input', updateSelectedCount);
                updateSelectedCount();
            }

            if (!officeSelect || !departmentInput) {
                return;
            }

            const syncDepartmentName = () => {
                const selectedOption = officeSelect.options[officeSelect.selectedIndex];
                departmentInput.value = selectedOption?.dataset.officeName || departmentInput.value;
            };

            officeSelect.addEventListener('change', syncDepartmentName);
            syncDepartmentName();
        });
    </script>
@endpush
