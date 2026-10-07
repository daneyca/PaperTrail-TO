<?php

namespace App\Http\Controllers\BacSecretariat;

use App\Http\Controllers\Controller;
use App\Models\AnnualProcurementPlan;
use App\Models\AnnualProcurementPlanItem;
use App\Models\AnnualProcurementPlanVersion;
use App\Models\ElectronicSignature;
use App\Models\PpmpItem;
use App\Models\ProcurementDocument;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentDraftService;
use App\Services\NotificationDispatchService;
use App\Services\SignatureRequestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class AnnualProcurementPlanController extends Controller
{
    private const ITEM_COLUMNS = [
        'project_title',
        'end_user_unit',
        'general_description',
        'mode_of_procurement',
        'early_procurement_activity',
        'bid_evaluation_criteria',
        'start_procurement_activity',
        'end_procurement_activity',
        'source_of_funds',
        'estimated_budget',
        'procurement_strategy_or_tools',
        'remarks',
    ];

    private const PENDING_PPMP_CONSOLIDATION_STATUSES = [
        ProcurementDocument::STATUS_PENDING_PPMP_REVIEW,
        ProcurementDocument::STATUS_UNDER_PPMP_REVIEW,
    ];

    private const OFFICIAL_APP_STATUSES = [
        AnnualProcurementPlan::STATUS_CONSOLIDATED,
        AnnualProcurementPlan::STATUS_SUBMITTED,
        AnnualProcurementPlan::STATUS_APPROVED,
        AnnualProcurementPlan::STATUS_RETURNED,
    ];

    private const MAIN_APP_STATUSES = [
        AnnualProcurementPlan::STATUS_DRAFT,
        AnnualProcurementPlan::STATUS_CONSOLIDATED,
        AnnualProcurementPlan::STATUS_SUBMITTED,
        AnnualProcurementPlan::STATUS_APPROVED,
        AnnualProcurementPlan::STATUS_RETURNED,
    ];

    public function index(Request $request): View
    {
        $this->ensureCanView($request);
        AuditLogger::log('Annual Procurement Plan', 'APP Records Viewed', 'User viewed Annual Procurement Plan records.');

        $query = AnnualProcurementPlan::query()->with(['preparedBy', 'submittedBy', 'approvedBy', 'office']);
        $this->applyFilters($query, $request);

        if (! $request->filled('status') || strtolower((string) $request->input('status')) === 'all') {
            $query->where('status', '!=', AnnualProcurementPlan::STATUS_CANCELLED);
        }

        return view('bac-secretariat.app.index', [
            'apps' => $query->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary(),
            'recentDrafts' => app(DocumentDraftService::class)->getAppDraftsForBacSecretariat($request->user(), 5),
            'filters' => $request->only(['search', 'fiscal_year', 'status', 'date_from', 'date_to']),
            'fiscalYears' => AnnualProcurementPlan::query()
                ->whereNotNull('fiscal_year')
                ->distinct()
                ->orderByDesc('fiscal_year')
                ->pluck('fiscal_year'),
            'statuses' => $this->statuses(),
            'canConsolidate' => $this->canConsolidate($request->user()),
            'acceptedPpmpCount' => $this->acceptedPpmpQuery(null, $request->user())->count(),
            'readyPpmps' => $this->acceptedPpmpQuery(null, $request->user())
                ->with(['submittingOffice', 'submittedBy'])
                ->latest('updated_at')
                ->limit(10)
                ->get(),
            'pendingPpmpCount' => $this->pendingPpmpQuery(null, $request->user())->count(),
            'pendingPpmps' => $this->pendingPpmpQuery(null, $request->user())->with(['submittingOffice', 'submittedBy', 'assignedTo'])->latest('updated_at')->limit(5)->get(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->canConsolidate($request->user())) {
            return $this->deny($request, 'APP Create Unauthorized', 'User attempted to create an APP without consolidation access.');
        }

        $selectedPpmpMode = $request->filled('ppmp_document_id');
        $selectedPpmp = null;

        if ($selectedPpmpMode) {
            $selectedPpmpId = $request->integer('ppmp_document_id');

            if ($selectedPpmpId < 1) {
                return redirect()
                    ->route('bac-secretariat.app.index')
                    ->with('error', 'Select a valid PPMP record before creating an APP.');
            }

            $selectedPpmp = $this->acceptedPpmpQuery(null, $request->user())
                ->with(['submittingOffice', 'submittedBy', 'ppmpItems'])
                ->find($selectedPpmpId);

            if (! $selectedPpmp) {
                $existingPpmp = ProcurementDocument::query()
                    ->where('document_type', 'PPMP')
                    ->find($selectedPpmpId);

                if ($existingPpmp) {
                    $existingApp = $this->mainAppForYear((int) ($existingPpmp->fiscal_year ?: now()->year));

                    if ($existingApp?->items()->where('source_ppmp_document_id', $existingPpmp->id)->exists()) {
                        return redirect()
                            ->route($existingApp->isEditable() ? 'bac-secretariat.app.edit' : 'bac-secretariat.app.show', $existingApp)
                            ->with('status', "PPMP {$existingPpmp->tracking_number} is already in {$existingApp->displayNumber()}.");
                    }
                }

                return redirect()
                    ->route('bac-secretariat.app.index')
                    ->with('error', 'The selected PPMP is not available for APP consolidation. It may already be consolidated or may not be approved yet.');
            }
        }

        $fiscalYear = (int) ($selectedPpmp?->fiscal_year ?: ($request->integer('fiscal_year') ?: now()->year));

        if ($mainApp = $this->mainAppForYear($fiscalYear)) {
            AuditLogger::log('Annual Procurement Plan', 'Existing APP Continued', 'BAC Secretariat continued the fiscal-year APP record instead of creating another APP number.', $mainApp);

            $routeParameters = ['app' => $mainApp];

            if ($selectedPpmp && $mainApp->isEditable()) {
                $routeParameters['ppmp_document_id'] = $selectedPpmp->id;
            }

            if ($mainApp->isEditable()) {
                return redirect()
                    ->route('bac-secretariat.app.edit', $routeParameters)
                    ->with('status', "An editable APP for CY {$fiscalYear} already exists. Continue {$mainApp->displayNumber()} to add or edit PPMP rows.");
            }

            return redirect()
                ->route('bac-secretariat.app.show', $mainApp)
                ->with('status', "APP {$mainApp->displayNumber()} already exists for CY {$fiscalYear}. Add approved PPMPs from APP Consolidation to start the next revision under the same APP number.");
        }

        $versioning = $this->automaticPlanVersion($fiscalYear);
        $prefilledItems = $versioning['source_app'] instanceof AnnualProcurementPlan
            ? $this->storedItemsForApp($versioning['source_app'])
            : [];
        $totals = $this->totals($prefilledItems);

        $app = new AnnualProcurementPlan([
            'fiscal_year' => $fiscalYear,
            'title' => 'Annual Procurement Plan (APP) for CY ' . $fiscalYear,
            'province' => 'Province of Southern Leyte',
            'municipality' => 'MUNICIPALITY OF TOMAS OPPUS',
            'plan_type' => $versioning['plan_type'],
            'update_version_no' => $versioning['update_version_no'],
            'office_id' => $request->user()->office_id,
            'office_name' => $request->user()->assignedOffice?->name ?? $request->user()->office,
            'prepared_by_user_id' => $request->user()->id,
            'status' => AnnualProcurementPlan::STATUS_DRAFT,
            'items_json' => $prefilledItems,
            'total_epa_budget' => $totals['total_epa_budget'],
            'total_cse_budget' => $totals['total_cse_budget'],
            'total_estimated_budget' => $totals['total_estimated_budget'],
            'total_personal_outlay' => $totals['total_personal_outlay'],
            'total_mooe' => $totals['total_mooe'],
            'total_co' => $totals['total_co'],
            'signatories_json' => $this->defaultSignatories(),
        ]);

        $app->setRelation('items', collect());

        AuditLogger::log('Annual Procurement Plan', 'APP Create Page Viewed', 'BAC Secretariat opened APP creation.');

        $acceptedPpmps = $selectedPpmp
            ? collect([$selectedPpmp])
            : $this->acceptedPpmpQuery($app->fiscal_year, $request->user())
                ->with(['submittingOffice', 'submittedBy', 'ppmpItems'])
                ->latest('updated_at')
                ->limit(10)
                ->get();
        $additionalPpmps = $selectedPpmp
            ? $this->acceptedPpmpQuery($app->fiscal_year, $request->user())
                ->with(['submittingOffice', 'submittedBy', 'ppmpItems'])
                ->where('id', '!=', $selectedPpmp->id)
                ->latest('updated_at')
                ->limit(10)
                ->get()
            : collect();
        $ppmpImportSources = $acceptedPpmps->concat($additionalPpmps);

        return view('bac-secretariat.app.create', [
            'app' => $app,
            'appVersionContext' => $this->appVersionContext($app, $versioning['source_app'] ?? null),
            'recentDrafts' => app(DocumentDraftService::class)->getAppDraftsForBacSecretariat($request->user(), 5),
            'acceptedPpmps' => $acceptedPpmps,
            'additionalPpmps' => $additionalPpmps,
            'selectedPpmpMode' => $selectedPpmpMode,
            'selectedPpmp' => $selectedPpmp,
            'acceptedPpmpImportItems' => $ppmpImportSources
                ->mapWithKeys(fn (ProcurementDocument $ppmp) => [$ppmp->id => $this->mapAcceptedPpmpItems($ppmp)])
                ->all(),
            'pendingPpmps' => $selectedPpmpMode
                ? collect()
                : $this->pendingPpmpQuery($app->fiscal_year, $request->user())->with(['submittingOffice', 'submittedBy', 'assignedTo'])->latest('updated_at')->limit(5)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->canConsolidate($request->user())) {
            return $this->deny($request, 'APP Create Unauthorized', 'User attempted to store an APP without consolidation access.');
        }

        $isSubmitting = $request->input('save_action') === 'submit';
        $validated = $this->validatedApp($request, $isSubmitting);
        $fiscalYear = (int) ($validated['fiscal_year'] ?? now()->year);

        if (($existingMainApp = $this->mainAppForYear($fiscalYear)) && ! $existingMainApp->isEditable()) {
            return redirect()
                ->route('bac-secretariat.app.show', $existingMainApp)
                ->with('error', "APP {$existingMainApp->displayNumber()} already exists for CY {$fiscalYear}. It cannot be overwritten by creating another APP record.");
        }

        if (($existingMainApp ??= $this->mainAppForYear($fiscalYear)) && $existingMainApp->isEditable()) {
            if (
                $this->missingLockedAppItemKeys($existingMainApp, $validated['items'] ?? [])
                || $this->changedLockedAppItemKeys($existingMainApp, $validated['items'] ?? [])
            ) {
                return redirect()
                    ->route('bac-secretariat.app.edit', $existingMainApp)
                    ->withInput()
                    ->with('error', 'Submitted APP PPMP rows are locked and cannot be edited or removed. Add or edit only the current draft/update rows.');
            }
        }

        $app = null;
        $reusedExistingApp = false;

        DB::transaction(function () use ($request, $validated, $isSubmitting, &$app, &$reusedExistingApp) {
            $payload = $this->payload($validated, $request->user());
            $fiscalYear = (int) ($payload['fiscal_year'] ?? now()->year);

            if ($existingApp = $this->mainAppForYear($fiscalYear, lock: true)) {
                if (! $existingApp->isEditable()) {
                    return;
                }

                $app = $existingApp;
                $reusedExistingApp = true;
                $oldValues = $this->auditSummary($app);
                $finalizeWithExistingSignatures = $isSubmitting && $this->appHasCompletedSignatures($app);
                $payload = $this->withAutomaticPlanVersion($payload, $app);
                $payload = $this->preserveSignedAppSignatories($payload, $app);

                $app->update(array_merge($payload, [
                    'status' => $finalizeWithExistingSignatures
                        ? AnnualProcurementPlan::STATUS_APPROVED
                        : ($isSubmitting ? AnnualProcurementPlan::STATUS_SUBMITTED : AnnualProcurementPlan::STATUS_DRAFT),
                    'prepared_by_user_id' => $app->prepared_by_user_id ?: $request->user()->id,
                    'submitted_by_user_id' => $isSubmitting ? $request->user()->id : $app->submitted_by_user_id,
                    'submitted_at' => $isSubmitting ? now() : $app->submitted_at,
                    'approved_by_user_id' => $finalizeWithExistingSignatures ? $this->appSignatureApproverUserId($app, $request->user()) : $app->approved_by_user_id,
                    'approved_at' => $finalizeWithExistingSignatures ? now() : $app->approved_at,
                    'returned_at' => $isSubmitting ? null : $app->returned_at,
                    'return_reason' => $isSubmitting ? null : $app->return_reason,
                ]));

                if ($isSubmitting) {
                    $this->ensureAppNumber($app);

                    if (! $finalizeWithExistingSignatures) {
                        $this->createAppSignatureRequests($app->refresh(), $request->user());
                    }
                }

                $this->syncItems($app, $validated['items'] ?? []);

                if ($isSubmitting) {
                    $this->snapshotAppVersion(
                        $app->refresh(),
                        $finalizeWithExistingSignatures ? 'APP update finalized with existing signatures.' : 'APP submitted for signatures.',
                        $request->user(),
                        $finalizeWithExistingSignatures,
                    );
                }

                AuditLogger::log(
                    'Annual Procurement Plan',
                    $finalizeWithExistingSignatures
                        ? 'APP Update Finalized'
                        : ($isSubmitting ? 'APP Submitted' : 'Existing APP Draft Updated'),
                    $finalizeWithExistingSignatures
                        ? 'BAC Secretariat finalized an APP update with existing completed signatures.'
                        : ($isSubmitting ? 'BAC Secretariat submitted an Annual Procurement Plan.' : 'BAC Secretariat reused and updated the active APP draft for this fiscal year.'),
                    $app,
                    $oldValues,
                    $this->auditSummary($app->refresh()),
                );

                return;
            }

            $payload = $this->withAutomaticPlanVersion($payload);

            $app = AnnualProcurementPlan::create(array_merge($payload, [
                'status' => $isSubmitting ? AnnualProcurementPlan::STATUS_SUBMITTED : AnnualProcurementPlan::STATUS_DRAFT,
                'prepared_by_user_id' => $request->user()->id,
                'submitted_by_user_id' => $isSubmitting ? $request->user()->id : null,
                'submitted_at' => $isSubmitting ? now() : null,
                'returned_at' => null,
                'return_reason' => null,
            ]));

            if ($isSubmitting) {
                $this->ensureAppNumber($app);
                $this->createAppSignatureRequests($app->refresh(), $request->user());
            }

            $this->syncItems($app, $validated['items'] ?? []);

            if ($isSubmitting) {
                $this->snapshotAppVersion($app->refresh(), 'APP submitted for signatures.', $request->user());
            }

            AuditLogger::log(
                'Annual Procurement Plan',
                $isSubmitting ? 'APP Submitted' : 'APP Draft Created',
                $isSubmitting ? 'BAC Secretariat submitted an Annual Procurement Plan.' : 'BAC Secretariat created an APP draft.',
                $app,
                null,
                $this->auditSummary($app),
            );
        });

        if ($isSubmitting && $app?->status !== AnnualProcurementPlan::STATUS_APPROVED) {
            $this->notifyRole('approving_authority', 'Annual Procurement Plan Submitted', 'An Annual Procurement Plan has been submitted for approval.', route('bac-secretariat.app.show', $app), $app);
        }

        return redirect()
            ->route('bac-secretariat.app.show', $app)
            ->with('status', $isSubmitting
                ? ($app?->status === AnnualProcurementPlan::STATUS_APPROVED ? 'APP update finalized with existing signatures.' : 'APP submitted for approval.')
                : ($reusedExistingApp ? 'Existing APP draft updated.' : 'APP draft saved.'));
    }

    public function addPpmpToApp(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (! $this->canConsolidate($request->user())) {
            return $this->deny($request, 'APP PPMP Add Unauthorized', 'User attempted to add a PPMP to APP without consolidation access.', $document);
        }

        $ppmp = $this->acceptedPpmpQuery(null, $request->user())
            ->with(['ppmpItems', 'submittingOffice', 'submittedBy'])
            ->find($document->id);

        if (! $ppmp) {
            return back()->with('error', 'This PPMP is not available for APP consolidation. It may already be added or may not be approved yet.');
        }

        $mappedItems = $this->mapAcceptedPpmpItems($ppmp);

        if ($mappedItems === []) {
            return back()->with('error', 'This PPMP has no item rows to add to the APP.');
        }

        $app = null;
        $createdDraft = false;

        $addedCount = 0;

        DB::transaction(function () use ($request, $ppmp, $mappedItems, &$app, &$createdDraft, &$addedCount) {
            $app = $this->mainAppForPpmp($ppmp, lock: true)
                ?: $this->createDraftAppForPpmp($ppmp, $request->user());
            $createdDraft = $app->wasRecentlyCreated;
            $oldValues = $this->auditSummary($app);
            $previousVersion = max($this->currentAppVersionNumber($app), 1);
            $startedRevision = $this->startRevisionFromApprovedAppIfNeeded($app, $request->user(), "Added PPMP {$ppmp->tracking_number} to APP consolidation.");

            if (! $startedRevision && ! $app->isEditable()) {
                return;
            }

            $hadExistingRows = $this->appHasWorksheetRows($app);
            $mappedItems = $this->newMappedPpmpItemsForApp($app, $mappedItems);

            if ($mappedItems === []) {
                return;
            }

            $nextOrder = (int) ($app->items()->max('sort_order') ?? $app->items()->max('row_order') ?? -1) + 1;

            foreach ($mappedItems as $item) {
                AnnualProcurementPlanItem::create(array_merge($item, [
                    'annual_procurement_plan_id' => $app->id,
                    'row_order' => $nextOrder,
                    'sort_order' => $nextOrder,
                ], $this->appItemVersionAttributes($app)));

                $nextOrder++;
            }

            $addedCount = count($mappedItems);

            $this->syncStoredItemsFromRows($app);

            if (! $startedRevision && ! $hadExistingRows) {
                $this->refreshAutomaticPlanVersion($app);
            }

            AuditLogger::log(
                'Annual Procurement Plan',
                $createdDraft ? 'APP Draft Created from PPMP' : ($startedRevision ? 'APP Version Updated from Approved APP' : 'Accepted PPMP Added to Existing APP Draft'),
                $createdDraft
                    ? 'BAC Secretariat created an APP draft and added an accepted PPMP.'
                    : ($startedRevision
                        ? "PPMP {$ppmp->tracking_number} added to {$app->displayNumber()}. Version updated from v{$previousVersion} to v" . max($this->currentAppVersionNumber($app), 1) . '. Existing APP signatures retained.'
                        : 'BAC Secretariat added an accepted PPMP to the current APP draft.'),
                $app,
                $oldValues,
                $this->auditSummary($app->refresh()),
            );
        });

        if ($app && $app->status === AnnualProcurementPlan::STATUS_SUBMITTED && $addedCount === 0) {
            return redirect()
                ->route('bac-secretariat.app.show', $app)
                ->with('error', "APP {$app->displayNumber()} is already submitted for approval. Wait until it is returned or approved before adding another PPMP.");
        }

        $redirectRoute = $app?->isEditable()
            ? 'bac-secretariat.app.edit'
            : 'bac-secretariat.app.show';

        if ($addedCount === 0) {
            return redirect()
                ->route($redirectRoute, $app)
                ->with('status', "All project lines from PPMP {$ppmp->tracking_number} are already in {$app->displayNumber()}.");
        }

        return redirect()
            ->route($redirectRoute, $app)
            ->with('status', "PPMP {$ppmp->tracking_number} added to {$app->displayNumber()} ({$addedCount} project line" . ($addedCount === 1 ? '' : 's') . ' imported).');
    }

    public function show(Request $request, AnnualProcurementPlan $app): View|RedirectResponse
    {
        $this->ensureCanView($request);
        $app->load(['items', 'preparedBy', 'submittedBy', 'approvedBy', 'office']);

        AuditLogger::log('Annual Procurement Plan', 'APP Detail Viewed', 'User viewed an Annual Procurement Plan.', $app);

        return view('bac-secretariat.app.show', [
            'app' => $app,
            'appVersionContext' => $this->appVersionContext($app),
            'appVersionHistory' => $this->versionHistoryForApp($app),
            'canConsolidate' => $this->canConsolidate($request->user()),
            'canApprove' => $this->canApprove($request->user()),
            'importedPpmps' => $this->importedPpmpsForApp($app),
            'acceptedPpmps' => collect(),
            'pendingPpmps' => collect(),
            'lockedPpmpIds' => $this->lockedPpmpIdsForApp($app),
            'signatureSlots' => app(SignatureRequestService::class)->signedSignaturesForDocument($app, 'app'),
            'signatureRequestCount' => SignatureRequest::query()->forDocument('app', $app->id)->count(),
            'appSignatureWorkflowComplete' => $this->appHasCompletedSignatures($app),
        ]);
    }

    public function edit(Request $request, AnnualProcurementPlan $app): View|RedirectResponse
    {
        if (! $this->canConsolidate($request->user())) {
            return $this->deny($request, 'APP Edit Unauthorized', 'User attempted to edit an APP without consolidation access.', $app);
        }

        if (! $app->isEditable()) {
            return redirect()
                ->route('bac-secretariat.app.show', $app)
                ->with('error', 'Only draft, under consolidation, or returned APP records can be edited.');
        }

        $app->load(['items', 'preparedBy', 'submittedBy', 'approvedBy', 'office']);
        $selectedPpmpMode = $request->filled('ppmp_document_id');
        $selectedPpmp = null;

        if ($selectedPpmpMode) {
            $selectedPpmpId = $request->integer('ppmp_document_id');

            if ($selectedPpmpId > 0) {
                $selectedPpmp = $this->acceptedPpmpQuery((int) $app->fiscal_year, $request->user())
                    ->with(['submittingOffice', 'submittedBy', 'ppmpItems'])
                    ->find($selectedPpmpId);
            }

            if (! $selectedPpmp) {
                if ($selectedPpmpId > 0 && $app->items()->where('source_ppmp_document_id', $selectedPpmpId)->exists()) {
                    return redirect()
                        ->route('bac-secretariat.app.edit', $app)
                        ->with('status', 'The selected PPMP is already added to this APP worksheet.');
                }

                return redirect()
                    ->route('bac-secretariat.app.edit', $app)
                    ->with('error', 'The selected PPMP is not available for this APP. It may already be added or may not be approved yet.');
            }
        }

        $acceptedPpmps = $selectedPpmp
            ? collect([$selectedPpmp])
            : $this->acceptedPpmpQuery((int) $app->fiscal_year, $request->user())
                ->with(['submittingOffice', 'submittedBy', 'ppmpItems'])
                ->latest('updated_at')
                ->limit(10)
                ->get();

        return view('bac-secretariat.app.edit', [
            'app' => $app,
            'appVersionContext' => $this->appVersionContext($app),
            'importedPpmps' => $this->importedPpmpsForApp($app),
            'acceptedPpmps' => $acceptedPpmps,
            'pendingPpmps' => collect(),
            'selectedPpmpMode' => $selectedPpmpMode,
            'lockedPpmpIds' => $this->lockedPpmpIdsForApp($app),
            'appSignatureWorkflowComplete' => $this->appHasCompletedSignatures($app),
        ]);
    }

    public function update(Request $request, AnnualProcurementPlan $app): RedirectResponse
    {
        if (! $this->canConsolidate($request->user())) {
            return $this->deny($request, 'APP Update Unauthorized', 'User attempted to update an APP without consolidation access.', $app);
        }

        if (! $app->isEditable()) {
            return back()->with('error', 'Only draft, under consolidation, or returned APP records can be updated.');
        }

        $isSubmitting = $request->input('save_action') === 'submit';
        $finalizeWithExistingSignatures = $isSubmitting && $this->appHasCompletedSignatures($app);
        $validated = $this->validatedApp($request, $isSubmitting, $app, ! $finalizeWithExistingSignatures);

        if ($missingLockedItems = $this->missingLockedAppItemKeys($app, $validated['items'] ?? [])) {
            return back()
                ->withInput()
                ->with('error', 'Submitted APP PPMP rows cannot be removed. Keep the locked PPMP rows in the worksheet.');
        }

        if ($changedLockedItems = $this->changedLockedAppItemKeys($app, $validated['items'] ?? [])) {
            return back()
                ->withInput()
                ->with('error', 'Submitted APP PPMP rows are locked and cannot be edited. Add or edit only the current draft/update rows.');
        }

        DB::transaction(function () use ($request, $validated, $app, $isSubmitting, $finalizeWithExistingSignatures) {
            $oldValues = $this->auditSummary($app);
            $payload = $this->payload($validated, $request->user());
            $payload = $this->withAutomaticPlanVersion($payload, $app);
            $payload = $this->preserveSignedAppSignatories($payload, $app);

            $app->update(array_merge($payload, [
                'status' => $finalizeWithExistingSignatures
                    ? AnnualProcurementPlan::STATUS_APPROVED
                    : ($isSubmitting ? AnnualProcurementPlan::STATUS_SUBMITTED : AnnualProcurementPlan::STATUS_DRAFT),
                'submitted_by_user_id' => $isSubmitting ? $request->user()->id : $app->submitted_by_user_id,
                'submitted_at' => $isSubmitting ? now() : $app->submitted_at,
                'approved_by_user_id' => $finalizeWithExistingSignatures ? $this->appSignatureApproverUserId($app, $request->user()) : $app->approved_by_user_id,
                'approved_at' => $finalizeWithExistingSignatures ? now() : $app->approved_at,
                'returned_at' => $isSubmitting ? null : $app->returned_at,
                'return_reason' => $isSubmitting ? null : $app->return_reason,
            ]));

            if ($isSubmitting) {
                $this->ensureAppNumber($app);

                if (! $finalizeWithExistingSignatures) {
                    $this->createAppSignatureRequests($app->refresh(), $request->user());
                }
            }

            $this->syncItems($app, $validated['items'] ?? []);

            if ($isSubmitting) {
                $this->snapshotAppVersion(
                    $app->refresh(),
                    $finalizeWithExistingSignatures ? 'APP update finalized with existing signatures.' : 'APP submitted for signatures.',
                    $request->user(),
                    $finalizeWithExistingSignatures,
                );
            }

            AuditLogger::log(
                'Annual Procurement Plan',
                $finalizeWithExistingSignatures
                    ? 'APP Update Finalized'
                    : ($isSubmitting ? 'APP Submitted' : 'APP Updated'),
                $finalizeWithExistingSignatures
                    ? 'BAC Secretariat finalized an APP update with existing completed signatures.'
                    : ($isSubmitting ? 'BAC Secretariat submitted an APP.' : 'BAC Secretariat updated an APP.'),
                $app,
                $oldValues,
                $this->auditSummary($app->refresh()),
            );
        });

        if ($isSubmitting && $app->status !== AnnualProcurementPlan::STATUS_APPROVED) {
            $this->notifyRole('approving_authority', 'Annual Procurement Plan Submitted', 'An Annual Procurement Plan has been submitted for approval.', route('bac-secretariat.app.show', $app), $app);
        }

        return redirect()
            ->route('bac-secretariat.app.show', $app)
            ->with('status', $isSubmitting
                ? ($app->status === AnnualProcurementPlan::STATUS_APPROVED ? 'APP update finalized with existing signatures.' : 'APP submitted for approval.')
                : 'APP updated.');
    }

    public function print(Request $request, AnnualProcurementPlan $app): View
    {
        $this->ensureCanView($request);
        $app->load(['items', 'preparedBy', 'submittedBy', 'approvedBy', 'office']);

        AuditLogger::log('Annual Procurement Plan', 'APP Printed', 'User opened APP print view.', $app);

        return view('bac-secretariat.app.print', [
            'app' => $app,
            'signatureSlots' => app(SignatureRequestService::class)->signedSignaturesForDocument($app, 'app'),
        ]);
    }

    public function submit(Request $request, AnnualProcurementPlan $app): RedirectResponse
    {
        if (! $this->canConsolidate($request->user())) {
            return $this->deny($request, 'APP Submit Unauthorized', 'User attempted to submit an APP without consolidation access.', $app);
        }

        if (! $app->canSubmit()) {
            return back()->with('error', 'Only draft, under consolidation, or returned APP records can be submitted.');
        }

        if (! filled($app->document_text) || $app->items()->count() === 0) {
            return back()->with('error', 'APP content is empty. Please complete at least one row before submitting.');
        }

        $finalizeWithExistingSignatures = $this->appHasCompletedSignatures($app);

        if (! $finalizeWithExistingSignatures && ($message = $this->appSignatoryDesignationError($this->signatories($app->signatories_json ?? [])))) {
            return back()->with('error', $message);
        }

        $oldValues = $this->auditSummary($app);
        $app->update([
            'status' => $finalizeWithExistingSignatures ? AnnualProcurementPlan::STATUS_APPROVED : AnnualProcurementPlan::STATUS_SUBMITTED,
            'submitted_by_user_id' => $request->user()->id,
            'submitted_at' => now(),
            'approved_by_user_id' => $finalizeWithExistingSignatures ? $this->appSignatureApproverUserId($app, $request->user()) : $app->approved_by_user_id,
            'approved_at' => $finalizeWithExistingSignatures ? now() : $app->approved_at,
            'returned_at' => null,
            'return_reason' => null,
        ]);
        $this->ensureAppNumber($app);

        if (! $finalizeWithExistingSignatures) {
            $this->createAppSignatureRequests($app->refresh(), $request->user());
        }

        $this->snapshotAppVersion(
            $app->refresh(),
            $finalizeWithExistingSignatures ? 'APP update finalized with existing signatures.' : 'APP submitted for signatures.',
            $request->user(),
            $finalizeWithExistingSignatures,
        );

        AuditLogger::log(
            'Annual Procurement Plan',
            $finalizeWithExistingSignatures ? 'APP Update Finalized' : 'APP Submitted',
            $finalizeWithExistingSignatures ? 'BAC Secretariat finalized an APP update with existing completed signatures.' : 'BAC Secretariat submitted an APP.',
            $app,
            $oldValues,
            $this->auditSummary($app->refresh()),
        );

        if (! $finalizeWithExistingSignatures) {
            $this->notifyRole('approving_authority', 'Annual Procurement Plan Submitted', 'An Annual Procurement Plan has been submitted for approval.', route('bac-secretariat.app.show', $app), $app);
        }

        return back()->with('status', $finalizeWithExistingSignatures ? 'APP update finalized with existing signatures.' : 'APP submitted for approval.');
    }

    public function approve(Request $request, AnnualProcurementPlan $app): RedirectResponse
    {
        if (! $this->canApprove($request->user())) {
            return $this->deny($request, 'APP Approval Unauthorized', 'User attempted to approve an APP without approval access.', $app);
        }

        if (! $app->canApprove()) {
            return back()->with('error', 'Only submitted APP records can be approved.');
        }

        $oldValues = $this->auditSummary($app);
        $this->ensureAppNumber($app);
        $app->update([
            'status' => AnnualProcurementPlan::STATUS_APPROVED,
            'approved_by_user_id' => $request->user()->id,
            'approved_at' => now(),
            'returned_at' => null,
            'return_reason' => null,
        ]);

        AuditLogger::log('Annual Procurement Plan', 'APP Approved', 'APP was approved.', $app, $oldValues, $this->auditSummary($app->refresh()));

        $this->notifyRole('bac_secretariat', 'Annual Procurement Plan Approved', 'The submitted Annual Procurement Plan has been approved.', route('bac-secretariat.app.show', $app), $app);

        return back()->with('status', 'APP approved.');
    }

    public function returnApp(Request $request, AnnualProcurementPlan $app): RedirectResponse
    {
        if (! $this->canApprove($request->user())) {
            return $this->deny($request, 'APP Return Unauthorized', 'User attempted to return an APP without approval access.', $app);
        }

        if (! $app->canReturn()) {
            return back()->with('error', 'Only submitted APP records can be returned.');
        }

        $validated = $request->validate([
            'return_reason' => ['required', 'string', 'max:2000'],
        ]);

        $oldValues = $this->auditSummary($app);
        $app->update([
            'status' => AnnualProcurementPlan::STATUS_RETURNED,
            'returned_at' => now(),
            'return_reason' => $validated['return_reason'],
        ]);

        AuditLogger::log('Annual Procurement Plan', 'APP Returned', 'APP was returned for correction.', $app, $oldValues, $this->auditSummary($app->refresh()), 'warning');

        $this->notifyRole('bac_secretariat', 'Annual Procurement Plan Returned', 'An Annual Procurement Plan was returned for correction.', route('bac-secretariat.app.show', $app), $app);

        return back()->with('status', 'APP returned for correction.');
    }

    public function destroy(Request $request, AnnualProcurementPlan $app): RedirectResponse
    {
        if (! $this->canConsolidate($request->user())) {
            return $this->deny($request, 'APP Delete Unauthorized', 'User attempted to delete an APP without consolidation access.', $app);
        }

        if ($app->status !== AnnualProcurementPlan::STATUS_DRAFT) {
            return back()->with('error', 'Only draft APP consolidation records can be deleted.');
        }

        DB::transaction(function () use ($app) {
            $oldValues = array_merge($this->auditSummary($app), [
                'items_count' => $app->items()->count(),
            ]);

            AuditLogger::log(
                'Annual Procurement Plan',
                'APP Draft Deleted',
                'BAC Secretariat deleted an APP draft consolidation record.',
                $app,
                $oldValues,
                null,
                'warning',
            );

            $app->items()->delete();
            $app->delete();
        });

        return redirect()
            ->route('bac-secretariat.app.index')
            ->with('status', 'APP draft deleted. Accepted PPMP records remain available for consolidation.');
    }

    public function importPpmp(Request $request, AnnualProcurementPlan $app): RedirectResponse
    {
        if (! $this->canConsolidate($request->user())) {
            return $this->deny($request, 'APP PPMP Import Unauthorized', 'User attempted to import PPMP data without consolidation access.', $app);
        }

        if (! $app->isEditable()) {
            return back()->with('error', 'Accepted PPMPs can only be added to draft, under consolidation, or returned APP records.');
        }

        $validated = $request->validate([
            'ppmp_document_id' => ['required', 'integer', 'exists:procurement_documents,id'],
        ]);

        $ppmp = $this->acceptedPpmpQuery($app->fiscal_year, $request->user())
            ->with(['ppmpItems', 'submittingOffice', 'submittedBy'])
            ->find($validated['ppmp_document_id']);

        if (! $ppmp) {
            return back()->with('error', 'This PPMP is not available for APP consolidation.');
        }

        $mappedItems = $this->mapAcceptedPpmpItems($ppmp);

        if ($mappedItems === []) {
            return back()->with('error', 'This PPMP has no item rows to add to the APP.');
        }

        $mappedItems = $this->newMappedPpmpItemsForApp($app, $mappedItems);

        if ($mappedItems === []) {
            return back()->with('status', "All project lines from PPMP {$ppmp->tracking_number} are already added to this APP draft.");
        }

        DB::transaction(function () use ($app, $ppmp, $mappedItems) {
            $oldValues = $this->auditSummary($app);
            $hadExistingRows = $this->appHasWorksheetRows($app);

            $nextOrder = (int) ($app->items()->max('sort_order') ?? $app->items()->max('row_order') ?? -1) + 1;

            foreach ($mappedItems as $item) {
                AnnualProcurementPlanItem::create(array_merge($item, [
                    'annual_procurement_plan_id' => $app->id,
                    'row_order' => $nextOrder,
                    'sort_order' => $nextOrder,
                ], $this->appItemVersionAttributes($app)));

                $nextOrder++;
            }

            $this->syncStoredItemsFromRows($app);

            if (! $hadExistingRows) {
                $this->refreshAutomaticPlanVersion($app);
            }

            AuditLogger::log(
                'Annual Procurement Plan',
                'Accepted PPMP Added to APP',
                'BAC Secretariat added an accepted PPMP to an APP draft.',
                $app,
                $oldValues,
                $this->auditSummary($app->refresh()),
            );
        });

        return back()->with('status', "PPMP {$ppmp->tracking_number} added to the APP draft.");
    }

    public function removePpmp(Request $request, AnnualProcurementPlan $app, ProcurementDocument $document): RedirectResponse
    {
        if (! $this->canConsolidate($request->user())) {
            return $this->deny($request, 'APP PPMP Remove Unauthorized', 'User attempted to remove PPMP data without consolidation access.', $app);
        }

        if (! $app->isEditable()) {
            return back()->with('error', 'Accepted PPMPs can only be removed from draft, under consolidation, or returned APP records.');
        }

        $rows = $app->items()
            ->where('source_ppmp_document_id', $document->id)
            ->count();

        if ($rows === 0) {
            return back()->with('error', 'This PPMP is not currently added to this APP draft.');
        }

        if ($this->ppmpBelongsToLockedAppVersion($app, $document)) {
            return back()->with('error', 'This PPMP is already part of a submitted APP version and cannot be removed from APP consolidation.');
        }

        DB::transaction(function () use ($app, $document) {
            $oldValues = $this->auditSummary($app);

            $app->items()
                ->where('source_ppmp_document_id', $document->id)
                ->delete();

            $this->syncStoredItemsFromRows($app);
            $this->refreshAutomaticPlanVersionAfterPpmpRemoval($app);

            AuditLogger::log(
                'Annual Procurement Plan',
                'Accepted PPMP Removed from APP',
                'BAC Secretariat removed an accepted PPMP from an APP draft.',
                $app,
                $oldValues,
                $this->auditSummary($app->refresh()),
                'warning',
            );
        });

        return back()->with('status', "PPMP {$document->tracking_number} removed from the APP draft. The PPMP remains accepted and can be added again.");
    }

    private function mainAppForPpmp(ProcurementDocument $ppmp, bool $lock = false): ?AnnualProcurementPlan
    {
        $fiscalYear = (int) ($ppmp->fiscal_year ?: now()->year);

        return $this->mainAppForYear($fiscalYear, $lock);
    }

    private function mainAppForYear(int $fiscalYear, bool $lock = false): ?AnnualProcurementPlan
    {
        $query = AnnualProcurementPlan::query()
            ->with(['items'])
            ->where('fiscal_year', $fiscalYear)
            ->whereIn('status', self::MAIN_APP_STATUSES)
            ->orderByRaw('CASE WHEN app_number IS NULL THEN 1 ELSE 0 END')
            ->orderBy('app_number')
            ->orderBy('created_at')
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function startRevisionFromApprovedAppIfNeeded(AnnualProcurementPlan $app, User $user, string $changeSummary): bool
    {
        if ($app->status !== AnnualProcurementPlan::STATUS_APPROVED) {
            return false;
        }

        $fiscalYear = (int) ($app->fiscal_year ?: now()->year);
        $this->snapshotAppVersion($app, $changeSummary, $user);
        $nextVersion = $this->maxSubmittedUpdateVersionForYear($fiscalYear, $app) + 1;

        $app->forceFill([
            'status' => AnnualProcurementPlan::STATUS_DRAFT,
            'plan_type' => 'update',
            'update_version_no' => (string) $nextVersion,
            'submitted_by_user_id' => null,
            'approved_by_user_id' => null,
            'submitted_at' => null,
            'approved_at' => null,
            'returned_at' => null,
            'return_reason' => null,
            'recommended_date' => null,
            'approved_date' => null,
        ])->save();

        return true;
    }

    private function snapshotAppVersion(AnnualProcurementPlan $app, string $changeSummary, ?User $user = null, bool $refreshExisting = false): ?AnnualProcurementPlanVersion
    {
        if (! Schema::hasTable('annual_procurement_plan_versions')) {
            return null;
        }

        $app->loadMissing('items');
        $versionNo = $this->nextAppHistoryVersionNumber($app);

        $existing = $app->versions()
            ->where('plan_type', $app->plan_type)
            ->where(function (Builder $query) use ($app): void {
                filled($app->update_version_no)
                    ? $query->where('update_version_no', $app->update_version_no)
                    : $query->whereNull('update_version_no');
            })
            ->first();

        if ($existing) {
            if ($refreshExisting) {
                $existing->update([
                    'app_number' => $app->app_number,
                    'app_no' => $app->app_no,
                    'document_reference_number' => $app->document_reference_number,
                    'status' => $app->status,
                    'submitted_by_user_id' => $app->submitted_by_user_id,
                    'approved_by_user_id' => $app->approved_by_user_id,
                    'submitted_at' => $app->submitted_at,
                    'approved_at' => $app->approved_at,
                    'returned_at' => $app->returned_at,
                    'return_reason' => $app->return_reason,
                    'total_epa_budget' => $app->total_epa_budget,
                    'total_cse_budget' => $app->total_cse_budget,
                    'total_estimated_budget' => $app->total_estimated_budget,
                    'total_personal_outlay' => $app->total_personal_outlay,
                    'total_mooe' => $app->total_mooe,
                    'total_co' => $app->total_co,
                    'document_html' => $app->document_html,
                    'document_text' => $app->document_text,
                    'items_json' => $this->storedItemsForApp($app),
                    'signatories_json' => $app->signatories_json,
                    'metadata' => [
                        'signature_requests' => $this->signatureRequestSnapshotForApp($app),
                        'imported_ppmp_ids' => $app->items
                            ->pluck('source_ppmp_document_id')
                            ->filter()
                            ->unique()
                            ->values()
                            ->all(),
                    ],
                    'remarks' => $app->remarks,
                    'change_summary' => $changeSummary,
                    'created_by_user_id' => $user?->id ?: $existing->created_by_user_id,
                ]);
            }

            return $existing;
        }

        return AnnualProcurementPlanVersion::create([
            'annual_procurement_plan_id' => $app->id,
            'version_no' => $versionNo,
            'app_number' => $app->app_number,
            'app_no' => $app->app_no,
            'document_reference_number' => $app->document_reference_number,
            'fiscal_year' => $app->fiscal_year,
            'plan_type' => $app->plan_type,
            'update_version_no' => $app->update_version_no,
            'title' => $app->title,
            'status' => $app->status,
            'prepared_by_user_id' => $app->prepared_by_user_id,
            'submitted_by_user_id' => $app->submitted_by_user_id,
            'approved_by_user_id' => $app->approved_by_user_id,
            'submitted_at' => $app->submitted_at,
            'approved_at' => $app->approved_at,
            'returned_at' => $app->returned_at,
            'return_reason' => $app->return_reason,
            'total_epa_budget' => $app->total_epa_budget,
            'total_cse_budget' => $app->total_cse_budget,
            'total_estimated_budget' => $app->total_estimated_budget,
            'total_personal_outlay' => $app->total_personal_outlay,
            'total_mooe' => $app->total_mooe,
            'total_co' => $app->total_co,
            'document_html' => $app->document_html,
            'document_text' => $app->document_text,
            'items_json' => $this->storedItemsForApp($app),
            'signatories_json' => $app->signatories_json,
            'metadata' => [
                'signature_requests' => $this->signatureRequestSnapshotForApp($app),
                'imported_ppmp_ids' => $app->items
                    ->pluck('source_ppmp_document_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ],
            'remarks' => $app->remarks,
            'change_summary' => $changeSummary,
            'created_by_user_id' => $user?->id,
        ]);
    }

    private function signatureRequestSnapshotForApp(AnnualProcurementPlan $app): array
    {
        return SignatureRequest::query()
            ->forDocument('app', $app->id)
            ->orderBy('signing_order')
            ->orderBy('id')
            ->get()
            ->map(fn (SignatureRequest $request): array => [
                'id' => $request->id,
                'slot' => $request->signatory_slot,
                'label' => $request->signatory_label,
                'status' => $request->status,
                'requested_to_user_id' => $request->requested_to_user_id,
                'requested_to_role' => $request->requested_to_role,
                'signed_at' => $request->signed_at?->toDateTimeString(),
                'remarks' => $request->remarks,
                'metadata' => $request->metadata,
            ])
            ->values()
            ->all();
    }

    private function appHasCompletedSignatures(AnnualProcurementPlan $app): bool
    {
        if (! $app->exists) {
            return false;
        }

        $requiredRequests = SignatureRequest::query()
            ->forDocument('app', $app->id)
            ->where('is_required', true);

        if ((clone $requiredRequests)->count() === 0) {
            return false;
        }

        return ! (clone $requiredRequests)
            ->where('status', '!=', SignatureRequest::STATUS_SIGNED)
            ->exists();
    }

    private function appSignatureApproverUserId(AnnualProcurementPlan $app, User $fallback): int
    {
        $hopeSignature = ElectronicSignature::query()
            ->forDocument('app', $app->id)
            ->where('signature_status', ElectronicSignature::STATUS_SIGNED)
            ->whereIn('signatory_slot', ['hope', 'approving_authority', 'approved_by'])
            ->latest('signed_at')
            ->first();

        $signature = $hopeSignature ?: ElectronicSignature::query()
            ->forDocument('app', $app->id)
            ->where('signature_status', ElectronicSignature::STATUS_SIGNED)
            ->latest('signed_at')
            ->first();

        return (int) ($signature?->signer_user_id ?: $app->approved_by_user_id ?: $fallback->id);
    }

    private function preserveSignedAppSignatories(array $payload, AnnualProcurementPlan $app): array
    {
        if (! $this->appHasCompletedSignatures($app)) {
            return $payload;
        }

        foreach ([
            'signatories_json',
            'prepared_by_name',
            'prepared_by_position',
            'prepared_by_office',
            'recommended_by_name',
            'recommended_by_position',
            'recommended_by_office',
            'approved_by_name',
            'approved_by_position',
            'approved_by_office',
        ] as $field) {
            $payload[$field] = $app->{$field};
        }

        return $payload;
    }

    private function newMappedPpmpItemsForApp(AnnualProcurementPlan $app, array $mappedItems): array
    {
        $app->loadMissing('items');

        $existingKeys = $app->items
            ->map(fn (AnnualProcurementPlanItem $item) => $this->ppmpItemSourceKey($item->source_ppmp_document_id, $item->source_ppmp_item_id))
            ->filter()
            ->flip()
            ->all();

        return collect($mappedItems)
            ->reject(function (array $item) use ($existingKeys): bool {
                $key = $this->appItemSourceKey($item);

                return $key !== null && array_key_exists($key, $existingKeys);
            })
            ->values()
            ->all();
    }

    private function uniqueAppItems(array $items): array
    {
        $seen = [];

        return collect($items)
            ->filter(function (array $item) use (&$seen): bool {
                $key = $this->appItemSourceKey($item);

                if ($key === null) {
                    return true;
                }

                if (isset($seen[$key])) {
                    return false;
                }

                $seen[$key] = true;

                return true;
            })
            ->values()
            ->all();
    }

    private function appItemSourceKey(array $item): ?string
    {
        return $this->ppmpItemSourceKey($item['source_ppmp_document_id'] ?? null, $item['source_ppmp_item_id'] ?? null);
    }

    private function ppmpItemSourceKey(mixed $documentId, mixed $itemId): ?string
    {
        if (! filled($documentId) || ! filled($itemId)) {
            return null;
        }

        return (int) $documentId . ':' . (int) $itemId;
    }

    private function lockedPpmpIdsForApp(AnnualProcurementPlan $app)
    {
        return $this->lockedSnapshotItemsForApp($app)
            ->pluck('source_ppmp_document_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    private function ppmpBelongsToLockedAppVersion(AnnualProcurementPlan $app, ProcurementDocument $document): bool
    {
        return $this->lockedPpmpIdsForApp($app)->contains((int) $document->id);
    }

    private function missingLockedAppItemKeys(AnnualProcurementPlan $app, array $incomingItems): array
    {
        $lockedKeys = $this->lockedSnapshotItemsForApp($app)
            ->map(fn (array $item): ?string => $this->appItemSourceKey($item))
            ->filter()
            ->unique()
            ->values();

        if ($lockedKeys->isEmpty()) {
            return [];
        }

        $incomingKeys = collect($this->uniqueAppItems($incomingItems))
            ->map(fn (array $item): ?string => $this->appItemSourceKey($item))
            ->filter()
            ->flip();

        return $lockedKeys
            ->reject(fn (string $key): bool => $incomingKeys->has($key))
            ->values()
            ->all();
    }

    private function changedLockedAppItemKeys(AnnualProcurementPlan $app, array $incomingItems): array
    {
        $lockedItemsByKey = [];

        foreach ($this->lockedSnapshotItemsForApp($app) as $item) {
            $key = $this->appItemSourceKey($item);

            if ($key === null) {
                continue;
            }

            $lockedItemsByKey[$key] = $this->appItemVersionSnapshot($item);
        }

        if ($lockedItemsByKey === []) {
            return [];
        }

        $incomingItemsByKey = collect($this->uniqueAppItems($incomingItems))
            ->mapWithKeys(function (array $item): array {
                $key = $this->appItemSourceKey($item);

                return $key === null ? [] : [$key => $this->appItemVersionSnapshot($item)];
            });

        return collect($lockedItemsByKey)
            ->filter(fn (array $lockedItem, string $key): bool => $incomingItemsByKey->has($key) && $incomingItemsByKey->get($key) !== $lockedItem)
            ->keys()
            ->values()
            ->all();
    }

    private function lockedSnapshotItemsForApp(AnnualProcurementPlan $app)
    {
        if (! Schema::hasTable('annual_procurement_plan_versions') || ! $app->exists) {
            return collect();
        }

        return $app->versions()
            ->orderBy('version_no')
            ->get()
            ->flatMap(fn (AnnualProcurementPlanVersion $version) => collect($version->items_json ?? [])
                ->filter(fn ($item): bool => is_array($item))
                ->map(fn (array $item): array => $this->normalizedStoredItem($item)));
    }

    private function createDraftAppForPpmp(ProcurementDocument $ppmp, User $user): AnnualProcurementPlan
    {
        $fiscalYear = (int) ($ppmp->fiscal_year ?: now()->year);
        $totals = $this->totals([]);
        $signatories = $this->defaultSignatories();
        $payload = $this->withAutomaticPlanVersion([
            'fiscal_year' => $fiscalYear,
            'title' => 'Annual Procurement Plan (APP) for CY ' . $fiscalYear,
            'province' => 'Province of Southern Leyte',
            'municipality' => 'MUNICIPALITY OF TOMAS OPPUS',
            'plan_type' => 'indicative',
            'update_version_no' => null,
            'office_id' => $user->office_id,
            'office_name' => $user->assignedOffice?->name ?? $user->office,
            'total_epa_budget' => $totals['total_epa_budget'],
            'total_cse_budget' => $totals['total_cse_budget'],
            'total_estimated_budget' => $totals['total_estimated_budget'],
            'total_personal_outlay' => $totals['total_personal_outlay'],
            'total_mooe' => $totals['total_mooe'],
            'total_co' => $totals['total_co'],
            'items_json' => [],
            'signatories_json' => $signatories,
            'prepared_by_name' => $signatories['prepared_by']['name'] ?? null,
            'prepared_by_position' => $signatories['prepared_by']['title'] ?? null,
            'prepared_by_office' => $signatories['prepared_by']['office'] ?? null,
            'recommended_by_name' => $signatories['recommended_by']['name'] ?? null,
            'recommended_by_position' => $signatories['recommended_by']['title'] ?? null,
            'recommended_by_office' => $signatories['recommended_by']['office'] ?? null,
            'approved_by_name' => $signatories['approved_by']['name'] ?? null,
            'approved_by_position' => $signatories['approved_by']['title'] ?? null,
            'approved_by_office' => $signatories['approved_by']['office'] ?? null,
        ]);

        return AnnualProcurementPlan::create(array_merge($payload, [
            'status' => AnnualProcurementPlan::STATUS_DRAFT,
            'prepared_by_user_id' => $user->id,
        ]));
    }

    private function withAutomaticPlanVersion(array $payload, ?AnnualProcurementPlan $app = null, bool $forceUpdate = false): array
    {
        $fiscalYear = (int) ($payload['fiscal_year'] ?? $app?->fiscal_year ?? now()->year);
        $versioning = $this->automaticPlanVersion($fiscalYear, $app, $forceUpdate);

        $payload['plan_type'] = $versioning['plan_type'];
        $payload['update_version_no'] = $versioning['update_version_no'];

        return $payload;
    }

    private function automaticPlanVersion(int $fiscalYear, ?AnnualProcurementPlan $app = null, bool $forceUpdate = false): array
    {
        if (
            $app
            && (int) $app->fiscal_year === $fiscalYear
            && $app->plan_type === 'update'
            && filled($app->update_version_no)
            && ! $forceUpdate
        ) {
            return [
                'plan_type' => 'update',
                'update_version_no' => (string) $app->update_version_no,
                'source_app' => $this->latestOfficialAppForYear($fiscalYear, $app),
            ];
        }

        $latestOfficial = $this->latestOfficialAppForYear($fiscalYear, $app);

        if (! $latestOfficial && ! $forceUpdate) {
            return [
                'plan_type' => 'indicative',
                'update_version_no' => null,
                'source_app' => null,
            ];
        }

        $nextVersion = $this->maxSubmittedUpdateVersionForYear($fiscalYear, $app) + 1;

        if ($forceUpdate) {
            $nextVersion = max($nextVersion, $this->currentAppVersionNumber($app) + 1);
        }

        return [
            'plan_type' => 'update',
            'update_version_no' => (string) $nextVersion,
            'source_app' => $latestOfficial,
        ];
    }

    private function refreshAutomaticPlanVersion(AnnualProcurementPlan $app, bool $forceUpdate = false): void
    {
        $versioning = $this->automaticPlanVersion((int) ($app->fiscal_year ?: now()->year), $app, $forceUpdate);

        $app->forceFill([
            'plan_type' => $versioning['plan_type'],
            'update_version_no' => $versioning['update_version_no'],
        ])->save();
    }

    private function refreshAutomaticPlanVersionAfterPpmpRemoval(AnnualProcurementPlan $app): void
    {
        if ($snapshot = $this->matchingSnapshotForCurrentItems($app)) {
            $app->forceFill([
                'plan_type' => $snapshot->plan_type ?: 'indicative',
                'update_version_no' => $snapshot->update_version_no,
            ])->save();

            return;
        }

        $versionNo = $this->remainingWorksheetUpdateVersionNumber($app);

        $app->forceFill($this->planVersionPayloadForVersionNumber($app, $versionNo))->save();
    }

    private function remainingWorksheetUpdateVersionNumber(AnnualProcurementPlan $app): int
    {
        $app->load('items');

        if ($app->items->isEmpty()) {
            return 1;
        }

        return max((int) $app->items
            ->map(fn (AnnualProcurementPlanItem $item): int => filled($item->app_version) ? (int) $item->app_version : 1)
            ->max(), 1);
    }

    private function matchingSnapshotForCurrentItems(AnnualProcurementPlan $app): ?AnnualProcurementPlanVersion
    {
        if (! Schema::hasTable('annual_procurement_plan_versions')) {
            return null;
        }

        $currentSignature = $this->worksheetVersionSignature($this->storedItemsFromItemModels($app));

        if ($currentSignature === []) {
            return null;
        }

        return $app->versions()
            ->orderByDesc('version_no')
            ->get()
            ->first(fn (AnnualProcurementPlanVersion $version): bool => $this->worksheetVersionSignature($version->items_json ?? []) === $currentSignature);
    }

    private function worksheetVersionSignature(array $items): array
    {
        return collect($items)
            ->map(function (array $item): array {
                $normalized = $this->normalizedStoredItem($item);
                unset($normalized['app_version']);

                foreach ($normalized as $key => $value) {
                    if ($key === 'estimated_budget') {
                        $normalized[$key] = number_format($this->money($value), 2, '.', '');
                    } elseif (is_string($value)) {
                        $normalized[$key] = $this->cleanText($value);
                    }
                }

                return $normalized;
            })
            ->sortBy(fn (array $item): string => json_encode($item))
            ->values()
            ->all();
    }

    private function planVersionPayloadForVersionNumber(AnnualProcurementPlan $app, int $versionNo): array
    {
        if ($versionNo <= 1) {
            $versionOne = Schema::hasTable('annual_procurement_plan_versions')
                ? $app->versions()->where('version_no', 1)->first()
                : null;

            if (! $versionOne && $app->plan_type === 'update') {
                return [
                    'plan_type' => 'update',
                    'update_version_no' => '1',
                ];
            }

            return [
                'plan_type' => $versionOne?->plan_type ?: 'indicative',
                'update_version_no' => $versionOne?->update_version_no,
            ];
        }

        return [
            'plan_type' => 'update',
            'update_version_no' => (string) $versionNo,
        ];
    }

    private function shouldForceUpdateVersion(AnnualProcurementPlan $app, int $previousItemCount, array $incomingItems, ?array $incomingPayload = null): bool
    {
        if (! $app->exists || ($previousItemCount === 0 && ! $this->appHasWorksheetRows($app))) {
            return false;
        }

        return $this->appItemsChangedForVersion($app, $incomingItems)
            || ($incomingPayload !== null && $this->appDetailsChangedForVersion($app, $incomingPayload));
    }

    private function appDetailsChangedForVersion(AnnualProcurementPlan $app, array $incomingPayload): bool
    {
        return $this->appDetailVersionSnapshot([
            'fiscal_year' => $app->fiscal_year,
            'province' => $app->province,
            'municipality' => $app->municipality,
            'title' => $app->title,
            'office_id' => $app->office_id,
            'office_name' => $app->office_name,
            'document_text' => $app->document_text,
            'signatories_json' => $app->signatories_json,
            'remarks' => $app->remarks,
        ]) !== $this->appDetailVersionSnapshot($incomingPayload);
    }

    private function appDetailVersionSnapshot(array $values): array
    {
        return [
            'fiscal_year' => filled($values['fiscal_year'] ?? null) ? (int) $values['fiscal_year'] : null,
            'province' => $this->cleanText($values['province'] ?? null),
            'municipality' => $this->cleanText($values['municipality'] ?? null),
            'title' => $this->cleanText($values['title'] ?? null),
            'office_id' => filled($values['office_id'] ?? null) ? (int) $values['office_id'] : null,
            'office_name' => $this->cleanText($values['office_name'] ?? null),
            'document_text' => $this->cleanText($values['document_text'] ?? null),
            'signatories_json' => $this->signatories($values['signatories_json'] ?? []),
            'remarks' => $this->cleanText($values['remarks'] ?? null),
        ];
    }

    private function appItemsChangedForVersion(AnnualProcurementPlan $app, array $incomingItems): bool
    {
        $current = collect($this->storedItemsForApp($app))
            ->map(fn (array $item) => $this->appItemVersionSnapshot($item))
            ->values()
            ->all();
        $incoming = collect($this->uniqueAppItems($incomingItems))
            ->map(fn (array $item) => $this->appItemVersionSnapshot($item))
            ->values()
            ->all();

        return $current !== $incoming;
    }

    private function appItemVersionSnapshot(array $item): array
    {
        return [
            'source_ppmp_document_id' => filled($item['source_ppmp_document_id'] ?? null) ? (int) $item['source_ppmp_document_id'] : null,
            'source_ppmp_item_id' => filled($item['source_ppmp_item_id'] ?? null) ? (int) $item['source_ppmp_item_id'] : null,
            'category' => $this->appCategory($item['category'] ?? null),
            'project_title' => $this->cleanText($item['project_title'] ?? ($item['procurement_program_project'] ?? null)),
            'end_user_unit' => $this->cleanText($item['end_user_unit'] ?? ($item['pmo_end_user'] ?? null)),
            'general_description' => $this->cleanText($item['general_description'] ?? null),
            'mode_of_procurement' => $this->cleanText($item['mode_of_procurement'] ?? null),
            'early_procurement_activity' => $this->cleanText($item['early_procurement_activity'] ?? null),
            'bid_evaluation_criteria' => $this->cleanText($item['bid_evaluation_criteria'] ?? null),
            'start_procurement_activity' => $this->cleanText($item['start_procurement_activity'] ?? ($item['ads_post_ib_rei'] ?? null)),
            'end_procurement_activity' => $this->cleanText($item['end_procurement_activity'] ?? ($item['contract_signing'] ?? null)),
            'source_of_funds' => $this->cleanText($item['source_of_funds'] ?? null),
            'estimated_budget' => number_format($this->money($item['estimated_budget'] ?? ($item['estimated_total'] ?? null)), 2, '.', ''),
            'procurement_strategy_or_tools' => $this->cleanText($item['procurement_strategy_or_tools'] ?? null),
            'remarks' => $this->cleanText($item['remarks'] ?? null),
        ];
    }

    private function appHasWorksheetRows(AnnualProcurementPlan $app): bool
    {
        if ($app->items()->exists()) {
            return true;
        }

        return collect($app->items_json ?? [])
            ->contains(fn ($item) => is_array($item) && collect($item)->filter(fn ($value) => filled($value))->isNotEmpty());
    }

    private function latestOfficialAppForYear(int $fiscalYear, ?AnnualProcurementPlan $app = null): ?AnnualProcurementPlan
    {
        return AnnualProcurementPlan::query()
            ->with(['items'])
            ->where('fiscal_year', $fiscalYear)
            ->whereIn('status', self::OFFICIAL_APP_STATUSES)
            ->when($app?->exists, fn (Builder $query) => $query->whereKeyNot($app->getKey()))
            ->latest('submitted_at')
            ->latest('approved_at')
            ->latest('updated_at')
            ->first();
    }

    private function maxSubmittedUpdateVersionForYear(int $fiscalYear, ?AnnualProcurementPlan $app = null): int
    {
        $currentRowsMax = AnnualProcurementPlan::query()
            ->where('fiscal_year', $fiscalYear)
            ->whereIn('status', self::OFFICIAL_APP_STATUSES)
            ->where('plan_type', 'update')
            ->when($app?->exists, fn (Builder $query) => $query->whereKeyNot($app->getKey()))
            ->pluck('update_version_no')
            ->map(fn ($version) => (int) preg_replace('/[^0-9]/', '', (string) $version))
            ->max() ?? 0;

        $snapshotMax = Schema::hasTable('annual_procurement_plan_versions')
            ? (int) AnnualProcurementPlanVersion::query()
                ->where('fiscal_year', $fiscalYear)
                ->where('plan_type', 'update')
                ->pluck('update_version_no')
                ->map(fn ($version) => (int) preg_replace('/[^0-9]/', '', (string) $version))
                ->max()
            : 0;

        return max($currentRowsMax, $snapshotMax);
    }

    private function nextAppHistoryVersionNumber(AnnualProcurementPlan $app): int
    {
        if (! Schema::hasTable('annual_procurement_plan_versions') || ! $app->exists) {
            return 1;
        }

        return ((int) $app->versions()->max('version_no')) + 1;
    }

    private function currentAppVersionNumber(?AnnualProcurementPlan $app): int
    {
        if (! $app?->exists) {
            return 0;
        }

        if ($app->plan_type === 'update' && filled($app->update_version_no)) {
            return max((int) preg_replace('/[^0-9]/', '', (string) $app->update_version_no), 1);
        }

        return 1;
    }

    private function appItemVersionAttributes(AnnualProcurementPlan $app): array
    {
        if (! Schema::hasColumn('annual_procurement_plan_items', 'app_version')) {
            return [];
        }

        return ['app_version' => max($this->currentAppVersionNumber($app), 1)];
    }

    private function storedItemsForApp(AnnualProcurementPlan $app): array
    {
        $app->loadMissing('items');

        if (is_array($app->items_json) && $app->items_json !== []) {
            return collect($app->items_json)
                ->map(fn (array $item) => $this->normalizedStoredItem($item))
                ->values()
                ->all();
        }

        return $this->storedItemsFromItemModels($app);
    }

    private function storedItemsFromItemModels(AnnualProcurementPlan $app): array
    {
        $app->loadMissing('items');

        return $app->items
            ->map(fn (AnnualProcurementPlanItem $item) => $this->normalizedStoredItem([
                'source_ppmp_document_id' => $item->source_ppmp_document_id,
                'source_ppmp_item_id' => $item->source_ppmp_item_id,
                'app_version' => $item->app_version,
                'category' => $item->category,
                'project_title' => $item->project_title,
                'end_user_unit' => $item->end_user_unit,
                'general_description' => $item->general_description,
                'mode_of_procurement' => $item->mode_of_procurement,
                'early_procurement_activity' => $item->early_procurement_activity,
                'bid_evaluation_criteria' => $item->bid_evaluation_criteria,
                'start_procurement_activity' => $item->start_procurement_activity,
                'end_procurement_activity' => $item->end_procurement_activity,
                'source_of_funds' => $item->source_of_funds,
                'estimated_budget' => $item->estimated_budget,
                'procurement_strategy_or_tools' => $item->procurement_strategy_or_tools,
                'remarks' => $item->remarks,
            ]))
            ->values()
            ->all();
    }

    private function normalizedStoredItem(array $item): array
    {
        return [
            'source_ppmp_document_id' => $item['source_ppmp_document_id'] ?? null,
            'source_ppmp_item_id' => $item['source_ppmp_item_id'] ?? null,
            'app_version' => $item['app_version'] ?? null,
            'category' => $this->appCategory($item['category'] ?? null),
            'project_title' => $item['project_title'] ?? ($item['procurement_program_project'] ?? null),
            'end_user_unit' => $item['end_user_unit'] ?? ($item['pmo_end_user'] ?? null),
            'general_description' => $item['general_description'] ?? null,
            'mode_of_procurement' => $item['mode_of_procurement'] ?? null,
            'early_procurement_activity' => $item['early_procurement_activity'] ?? null,
            'bid_evaluation_criteria' => $item['bid_evaluation_criteria'] ?? null,
            'start_procurement_activity' => $item['start_procurement_activity'] ?? ($item['ads_post_ib_rei'] ?? null),
            'end_procurement_activity' => $item['end_procurement_activity'] ?? ($item['contract_signing'] ?? null),
            'source_of_funds' => $item['source_of_funds'] ?? null,
            'estimated_budget' => $item['estimated_budget'] ?? ($item['estimated_total'] ?? null),
            'procurement_strategy_or_tools' => $item['procurement_strategy_or_tools'] ?? null,
            'remarks' => $item['remarks'] ?? null,
        ];
    }

    private function appVersionContext(AnnualProcurementPlan $app, ?AnnualProcurementPlan $sourceApp = null): array
    {
        $sourceApp ??= $app->plan_type === 'update'
            ? $this->latestOfficialAppForYear((int) ($app->fiscal_year ?: now()->year), $app)
            : null;

        return [
            'is_update' => $app->plan_type === 'update',
            'version_no' => max($this->currentAppVersionNumber($app), 1),
            'source_number' => $sourceApp?->displayNumber(),
            'source_updated_at' => $sourceApp?->updated_at,
        ];
    }

    private function versionHistoryForApp(AnnualProcurementPlan $app)
    {
        $versions = Schema::hasTable('annual_procurement_plan_versions')
            ? $app->versions()
                ->with('createdBy')
                ->orderBy('version_no')
                ->get()
                ->map(fn (AnnualProcurementPlanVersion $version): array => [
                    'version_no' => $version->version_no,
                    'status' => $version->status,
                    'total_estimated_budget' => $version->total_estimated_budget,
                    'changed_at' => $version->created_at,
                    'changed_by' => $version->createdBy?->name,
                    'change_summary' => $version->change_summary,
                    'current' => false,
                ])
            : collect();

        $currentVersion = $this->currentAppHistoryVersionNumber($app);

        if (! $versions->contains(fn (array $version): bool => (int) $version['version_no'] === $currentVersion)) {
            $versions->push([
                'version_no' => $currentVersion,
                'status' => $app->status,
                'total_estimated_budget' => $app->total_estimated_budget,
                'changed_at' => $app->updated_at,
                'changed_by' => $app->preparedBy?->name,
                'change_summary' => $app->plan_type === 'update' && $app->status === AnnualProcurementPlan::STATUS_APPROVED
                    ? 'Current APP version; existing signatures were carried forward.'
                    : 'Current APP version',
                'current' => true,
            ]);
        }

        return $versions
            ->sortBy('version_no')
            ->values();
    }

    private function currentAppHistoryVersionNumber(AnnualProcurementPlan $app): int
    {
        if (! Schema::hasTable('annual_procurement_plan_versions') || ! $app->exists) {
            return 1;
        }

        $existing = $app->versions()
            ->where('plan_type', $app->plan_type)
            ->where(function (Builder $query) use ($app): void {
                filled($app->update_version_no)
                    ? $query->where('update_version_no', $app->update_version_no)
                    : $query->whereNull('update_version_no');
            })
            ->first();

        return $existing?->version_no ?: $this->nextAppHistoryVersionNumber($app);
    }

    private function acceptedPpmpQuery(?int $fiscalYear = null, ?User $user = null): Builder
    {
        return ProcurementDocument::query()
            ->where('document_type', 'PPMP')
            ->where('status', ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION)
            ->where(fn (Builder $query) => $this->whereCompletedPpmpSignature($query))
            ->when($fiscalYear, fn (Builder $query) => $query->where('fiscal_year', $fiscalYear))
            ->when($user && ! $user->isAdmin() && ! $this->canApprove($user), function (Builder $query) use ($user) {
                $query->where(function (Builder $assignment) use ($user) {
                    $assignment->where('assigned_to_user_id', $user->id)
                        ->orWhereNull('assigned_to_user_id');
                });
            })
            ->whereNotIn('id', AnnualProcurementPlanItem::query()
                ->whereNotNull('source_ppmp_document_id')
                ->whereHas('annualProcurementPlan', fn (Builder $query) => $query->where('status', '!=', AnnualProcurementPlan::STATUS_CANCELLED))
                ->select('source_ppmp_document_id'));
    }

    private function importedPpmpsForApp(AnnualProcurementPlan $app)
    {
        $ids = $app->items
            ->pluck('source_ppmp_document_id')
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return ProcurementDocument::query()
            ->with(['submittingOffice'])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (ProcurementDocument $document) => $ids->search($document->id))
            ->values();
    }

    private function pendingPpmpQuery(?int $fiscalYear = null, ?User $user = null): Builder
    {
        return ProcurementDocument::query()
            ->where('document_type', 'PPMP')
            ->whereIn('status', self::PENDING_PPMP_CONSOLIDATION_STATUSES)
            ->where(fn (Builder $query) => $this->whereCompletedPpmpSignature($query))
            ->when($fiscalYear, fn (Builder $query) => $query->where('fiscal_year', $fiscalYear))
            ->when($user && ! $user->isAdmin() && ! $this->canApprove($user), function (Builder $query) use ($user) {
                $query->where(function (Builder $assignment) use ($user) {
                    $assignment->where('assigned_to_user_id', $user->id)
                        ->orWhereNull('assigned_to_user_id');
                });
            });
    }

    private function whereCompletedPpmpSignature(Builder $query): void
    {
        $query
            ->whereExists(function ($signature) {
                $signature->selectRaw('1')
                    ->from('signature_requests')
                    ->whereColumn('signature_requests.document_id', 'procurement_documents.id')
                    ->where('signature_requests.document_type', 'ppmp')
                    ->where('signature_requests.is_required', true);
            })
            ->whereNotExists(function ($signature) {
                $signature->selectRaw('1')
                    ->from('signature_requests')
                    ->whereColumn('signature_requests.document_id', 'procurement_documents.id')
                    ->where('signature_requests.document_type', 'ppmp')
                    ->where('signature_requests.is_required', true)
                    ->where('signature_requests.status', '!=', SignatureRequest::STATUS_SIGNED);
            });
    }

    private function mapAcceptedPpmpItems(ProcurementDocument $ppmp): array
    {
        $endUserUnit = $this->cleanText($ppmp->submittingOffice?->code)
            ?: $this->cleanText($ppmp->submittingOffice?->name)
            ?: $this->cleanText($ppmp->submittedBy?->office);

        $items = $ppmp->ppmpItems
            ->reject(fn (PpmpItem $item) => $this->isPpmpSubtotalRow($item))
            ->filter(fn (PpmpItem $item) => $this->estimatedPpmpBudget($item) > 0)
            ->map(function (PpmpItem $item) use ($ppmp, $endUserUnit): array {
                $estimatedBudget = $this->estimatedPpmpBudget($item);
                $projectInfo = $this->appProjectInfoForPpmpItem($item);
                $projectTitle = $projectInfo['title'];
                $modeOfProcurement = $this->normalizePpmpAppText($this->cleanText($item->procurement_mode), 'procurement_mode') ?: 'Small value';
                $sourceOfFunds = $this->normalizePpmpAppText($this->cleanText($item->source_of_funds), 'source_of_funds');

                return [
                    'source_ppmp_document_id' => $ppmp->id,
                    'source_ppmp_item_id' => $item->id,
                    'category' => $this->appCategory($item->category),
                    'project_title' => $projectTitle,
                    'end_user_unit' => $endUserUnit,
                    'general_description' => $projectInfo['description'],
                    'mode_of_procurement' => $modeOfProcurement,
                    'early_procurement_activity' => null,
                    'bid_evaluation_criteria' => null,
                    'start_procurement_activity' => $this->cleanText($item->start_procurement_activity),
                    'end_procurement_activity' => $this->cleanText($item->end_procurement_activity),
                    'source_of_funds' => $sourceOfFunds,
                    'estimated_budget' => $estimatedBudget,
                    'procurement_strategy_or_tools' => $this->cleanText($item->attached_supporting_documents),
                    'procurement_program_project' => $projectTitle,
                    'pmo_end_user' => $endUserUnit,
                    'ads_post_ib_rei' => $this->cleanText($item->start_procurement_activity),
                    'contract_signing' => $this->cleanText($item->end_procurement_activity),
                    'estimated_total' => $estimatedBudget,
                    'remarks' => $this->cleanText($item->remarks),
                    '_order' => $item->row_order ?? $item->item_no ?? $item->id ?? 0,
                ];
            })
            ->values();

        return $items
            ->sortBy('_order')
            ->map(function (array $item): array {
                unset($item['_order']);

                return $item;
            })
            ->values()
            ->all();
    }

    private function estimatedPpmpBudget(PpmpItem $item): float
    {
        return $this->money($item->estimated_total_cost)
            ?: ($this->money($item->quantity) * $this->money($item->estimated_unit_cost));
    }

    private function isPpmpSubtotalRow(PpmpItem $item): bool
    {
        return collect([
            $item->general_description,
            $item->project_type,
            $item->quantity_size,
            $item->procurement_mode,
            $item->pre_procurement_conference,
            $item->start_procurement_activity,
            $item->end_procurement_activity,
            $item->expected_delivery_period,
            $item->source_of_funds,
            $item->attached_supporting_documents,
            $item->remarks,
        ])->contains(fn ($value) => $this->isPpmpSubtotalText($value));
    }

    private function isPpmpSubtotalText(mixed $value): bool
    {
        $normalized = trim(preg_replace('/\s+/', ' ', str_replace(':', '', (string) $value)));

        return preg_match('/^(tot\.?|total)(\s+(budget|amount))?$/i', $normalized) === 1;
    }

    private function appProjectInfoForPpmpItem(PpmpItem $item): array
    {
        $projectTitle = $this->appProjectTitleForPpmpItem($item);

        return [
            'title' => $projectTitle,
            'description' => $this->appDescriptionForPpmpItem($item, $projectTitle),
        ];
    }

    private function appProjectTitleForPpmpItem(PpmpItem $item): string
    {
        $explicitTitle = $this->explicitAppProjectTitleForPpmpItem($item);

        if ($explicitTitle) {
            return $explicitTitle;
        }

        $text = $this->appSearchTextForPpmpItem($item);

        return $this->inferredAppProjectTitleFromText($text) ?: 'General Procurement Requirement';
    }

    private function appSearchTextForPpmpItem(PpmpItem $item): string
    {
        return strtolower(collect([
            $item->general_description,
            $item->quantity_size,
            $item->project_type,
            $item->category,
            $item->remarks,
        ])->map(fn ($value) => $this->cleanText($value))->filter()->implode(' '));
    }

    private function appProjectTitleGroups(): array
    {
        return [
            'Information and Communication Technology' => [
                'computer',
                'desktop',
                'flash drive',
                'hard drive',
                'hdd',
                'ict',
                'information and communication technology',
                'keyboard',
                'laptop',
                'memory',
                'monitor',
                'mouse',
                'network',
                'processor',
                'printer',
                'router',
                'scanner',
                'software',
                'ssd',
                'switch',
                'tablet',
                'ups',
            ],
            'Janitorial Supplies' => [
                'alcohol',
                'bleach',
                'broom',
                'cleaning',
                'detergent',
                'dishwasing',
                'dishwashing',
                'disinfectant',
                'fabcon',
                'mop',
                'soap',
                'tissue',
                'trash bag',
            ],
            'Office Supplies' => [
                'ballpen',
                'bond paper',
                'correction tape',
                'curtain',
                'envelope',
                'fastener',
                'folder',
                'glue',
                'ink',
                'marker',
                'office supplies',
                'office supply',
                'paper',
                'pen',
                'pencil',
                'notebook',
                'record book',
                'staple',
                'stapler',
                'tape',
                'toner',
            ],
            'Construction and Repair Materials' => [
                'cement',
                'electrical wire',
                'gravel',
                'lumber',
                'nails',
                'paint',
                'pipe',
                'pvc',
                'sand',
                'steel',
            ],
            'Fuel, Oil, and Lubricants' => [
                'diesel',
                'fuel',
                'gasoline',
                'lubricant',
                'oil',
            ],
            'Furniture and Fixtures' => [
                'cabinet',
                'chair',
                'desk',
                'filing cabinet',
                'shelf',
                'table',
            ],
            'Meals and Catering Services' => [
                'catering',
                'food',
                'meal',
                'snack',
            ],
            'Training Materials' => [
                'seminar',
                'training',
                'workshop',
            ],
        ];
    }

    private function explicitAppProjectTitleForPpmpItem(PpmpItem $item): ?string
    {
        $candidates = [
            $item->general_description,
            $item->project_type,
            $item->category,
        ];

        foreach ($candidates as $candidate) {
            $candidate = $this->cleanText($candidate);

            if (! $candidate || $this->looksLikeItemDetails($candidate)) {
                continue;
            }

            $title = $this->inferredAppProjectTitleFromText(strtolower($candidate));

            if ($title) {
                return $title;
            }

            $title = $this->cleanExplicitAppProjectTitle($candidate);

            if ($this->isUsableAppProjectTitle($title)) {
                return $title;
            }
        }

        return null;
    }

    private function inferredAppProjectTitleFromText(string $text): ?string
    {
        $text = strtolower($text);
        $scores = [];

        foreach ($this->appProjectTitleGroups() as $projectTitle => $keywords) {
            $score = 0;

            foreach ($keywords as $keyword) {
                if ($this->containsAppKeyword($text, $keyword)) {
                    $score++;
                }
            }

            if ($score > 0) {
                $scores[$projectTitle] = $score;
            }
        }

        if ($scores === []) {
            return null;
        }

        arsort($scores);

        return array_key_first($scores);
    }

    private function containsAppKeyword(string $text, string $keyword): bool
    {
        $keyword = strtolower($keyword);

        if (str_contains($keyword, ' ')) {
            return str_contains($text, $keyword);
        }

        return preg_match('/(?<![a-z0-9])' . preg_quote($keyword, '/') . '(?![a-z0-9])/i', $text) === 1;
    }

    private function cleanExplicitAppProjectTitle(string $value): string
    {
        $value = preg_replace('/^(purchase|procurement|rental|repair|subscription|supply)\s+of\s+/i', '', $value);
        $value = preg_replace('/\s+(equipment|materials|requirements|supplies|services)\s*$/i', ' $1', $value);

        return $this->cleanText($value) ?? '';
    }

    private function isUsableAppProjectTitle(?string $value): bool
    {
        if (! $value || $this->isPpmpSubtotalText($value)) {
            return false;
        }

        $normalized = strtolower($value);

        if (in_array($normalized, [
            'goods',
            'general procurement requirement',
            'general requirements',
            'infrastructure',
            'miscellaneous items',
            'services',
        ], true)) {
            return false;
        }

        return str_word_count($value) <= 6;
    }

    private function appDescriptionForPpmpItem(PpmpItem $item, string $projectTitle): string
    {
        $description = $this->cleanText($item->general_description);

        if ($this->isStandardAppProjectTitle($projectTitle)) {
            return $this->appDescriptionForProjectTitle($projectTitle);
        }

        if ($this->isUsableAppDescription($description) && ! $this->looksLikeItemDetails($description)) {
            return $this->purchaseDescriptionFromText($description);
        }

        return $this->appDescriptionForProjectTitle($projectTitle);
    }

    private function isStandardAppProjectTitle(string $projectTitle): bool
    {
        return array_key_exists($projectTitle, $this->appProjectTitleGroups());
    }

    private function isUsableAppDescription(?string $value): bool
    {
        if (! $value || $this->isPpmpSubtotalText($value)) {
            return false;
        }

        $normalized = strtolower($value);

        return ! in_array($normalized, ['n/a', 'na', 'none', 'not applicable'], true)
            && preg_match('/[a-z]/i', $value) === 1;
    }

    private function purchaseDescriptionFromText(string $value): string
    {
        $value = $this->cleanText($value) ?? '';

        if (preg_match('/^(procurement|purchase|rental|repair|subscription|supply)\b/i', $value)) {
            return $value;
        }

        return 'Purchase of ' . $value;
    }

    private function looksLikeItemDetails(string $value): bool
    {
        $normalized = strtolower($value);

        if (str_contains($normalized, ',') || str_contains($normalized, ';')) {
            return true;
        }

        return str_word_count($value) > 14
            && ! preg_match('/\b(procurement|project|purchase|repair|rental|subscription|supply)\b/i', $value);
    }

    private function appDescriptionForProjectTitle(string $projectTitle): string
    {
        return match ($projectTitle) {
            'Information and Communication Technology' => 'Purchase of Information and Communication Technology Equipment and Supplies',
            'Janitorial Supplies' => 'Purchase of Janitorial Supplies',
            'Construction and Repair Materials' => 'Purchase of Construction and Repair Materials',
            'Fuel, Oil, and Lubricants' => 'Purchase of Fuel, Oil, and Lubricants',
            'Meals and Catering Services' => 'Procurement of Meals and Catering Services',
            'Training Materials' => 'Purchase of Training Materials',
            'General Procurement Requirement' => 'General Procurement Requirement',
            default => 'Purchase of ' . $projectTitle,
        };
    }

    private function normalizePpmpAppText(?string $value, string $field): ?string
    {
        if (! $value) {
            return null;
        }

        if ($field === 'procurement_mode') {
            $normalized = strtolower(str_replace(['-', '_'], ' ', $value));

            if (in_array($normalized, ['small', 'small value', 'small value procurement'], true)) {
                return 'Small value';
            }
        }

        if ($field === 'source_of_funds' && strtolower($value) === 'general fund') {
            return 'General Fund';
        }

        return $value;
    }

    private function syncStoredItemsFromRows(AnnualProcurementPlan $app): void
    {
        $app->load(['items']);

        $items = $this->storedItemsFromItemModels($app);

        $totals = $this->totals($items);

        $app->forceFill(array_merge($totals, [
            'items_json' => $items,
            'document_text' => $this->appDocumentText($app, $items),
        ]))->save();
    }

    private function appDocumentText(AnnualProcurementPlan $app, array $items): string
    {
        $lines = [
            $app->title ?: 'Annual Procurement Plan',
            'Fiscal Year: ' . ($app->fiscal_year ?: now()->year),
        ];

        foreach ($items as $item) {
            $description = $item['project_title'] ?? $item['general_description'] ?? null;
            if (filled($description)) {
                $lines[] = $description . ' - ' . number_format($this->money($item['estimated_budget'] ?? 0), 2);
            }
        }

        return trim(implode(PHP_EOL, $lines));
    }

    private function appCategory(?string $category): string
    {
        return match ($category) {
            'miscellaneous_items', 'miscellaneous' => 'miscellaneous_items',
            'cse', 'common_use' => 'cse',
            default => 'general_requirements',
        };
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();
            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('document_reference_number', 'like', "%{$search}%")
                    ->orWhere('app_number', 'like', "%{$search}%")
                    ->orWhere('app_no', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('office_name', 'like', "%{$search}%")
                    ->orWhereHas('preparedBy', fn (Builder $user) => $user->where('name', 'like', "%{$search}%"));
            });
        });

        $query->when($request->filled('fiscal_year'), fn (Builder $builder) => $builder->where('fiscal_year', $request->input('fiscal_year')));
        $query->when($request->filled('created_year'), fn (Builder $builder) => $builder->where('created_year', $request->input('created_year')));
        $query->when($request->filled('created_month'), fn (Builder $builder) => $builder->where('created_month', $request->input('created_month')));
        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', fn (Builder $builder) => $builder->where('status', $request->input('status')));
        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('updated_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('updated_at', '<=', $request->date('date_to')));
    }

    private function validatedApp(Request $request, bool $submitting, ?AnnualProcurementPlan $app = null, bool $requireSignatoryDesignations = true): array
    {
        $items = $this->uniqueAppItems($this->filteredItems($this->jsonArray($request->input('items_json'))));
        $signatories = $this->signatories($this->jsonArray($request->input('signatories_json')));

        $request->merge([
            'items' => $items,
            'signatories' => $signatories,
        ]);

        $validator = Validator::make($request->all(), [
            'app_number' => ['nullable', 'string', 'max:255', Rule::unique('annual_procurement_plans', 'app_number')->ignore($app?->id)],
            'app_no' => ['nullable', 'string', 'max:255', Rule::unique('annual_procurement_plans', 'app_no')->ignore($app?->id)],
            'fiscal_year' => [$submitting ? 'required' : 'nullable', 'integer', 'min:2020', 'max:2100'],
            'province' => ['nullable', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'plan_type' => ['nullable', Rule::in(['indicative', 'final', 'update'])],
            'update_version_no' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'office_id' => ['nullable', 'exists:offices,id'],
            'office_name' => ['nullable', 'string', 'max:255'],
            'document_html' => ['nullable', 'string'],
            'document_text' => [$submitting ? 'required' : 'nullable', 'string'],
            'items_json' => ['nullable', 'json'],
            'signatories_json' => ['nullable', 'json'],
            'items' => $submitting ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
            'signatories' => ['nullable', 'array'],
            'remarks' => ['nullable', 'string'],
        ]);

        $validator->after(function ($validator) use ($submitting, $signatories, $requireSignatoryDesignations) {
            if ($submitting && $requireSignatoryDesignations && ($message = $this->appSignatoryDesignationError($signatories))) {
                $validator->errors()->add('signatories_json', $message);
            }
        });

        return $validator->validate();
    }

    private function payload(array $validated, User $user): array
    {
        $items = $validated['items'] ?? [];
        $totals = $this->totals($items);
        $signatories = $this->signatories($validated['signatories'] ?? []);

        return [
            'app_number' => $validated['app_number'] ?? null,
            'app_no' => $validated['app_no'] ?? null,
            'fiscal_year' => $validated['fiscal_year'] ?? now()->year,
            'province' => $validated['province'] ?? 'Province of Southern Leyte',
            'municipality' => $validated['municipality'] ?? 'MUNICIPALITY OF TOMAS OPPUS',
            'plan_type' => $validated['plan_type'] ?? 'indicative',
            'update_version_no' => $validated['update_version_no'] ?? null,
            'title' => $validated['title'] ?? null,
            'office_id' => $validated['office_id'] ?? $user->office_id,
            'office_name' => $validated['office_name'] ?? $user->assignedOffice?->name ?? $user->office,
            'total_epa_budget' => $totals['total_epa_budget'],
            'total_cse_budget' => $totals['total_cse_budget'],
            'total_estimated_budget' => $totals['total_estimated_budget'],
            'total_personal_outlay' => $totals['total_personal_outlay'],
            'total_mooe' => $totals['total_mooe'],
            'total_co' => $totals['total_co'],
            'document_html' => $this->sanitizeHtml($validated['document_html'] ?? null),
            'document_text' => $validated['document_text'] ?? null,
            'items_json' => $items,
            'signatories_json' => $signatories,
            'prepared_by_name' => $signatories['prepared_by']['name'] ?? null,
            'prepared_by_position' => $signatories['prepared_by']['title'] ?? null,
            'prepared_by_office' => $signatories['prepared_by']['office'] ?? null,
            'recommended_by_name' => $signatories['recommended_by']['name'] ?? null,
            'recommended_by_position' => $signatories['recommended_by']['title'] ?? null,
            'recommended_by_office' => $signatories['recommended_by']['office'] ?? null,
            'approved_by_name' => $signatories['approved_by']['name'] ?? null,
            'approved_by_position' => $signatories['approved_by']['title'] ?? null,
            'approved_by_office' => $signatories['approved_by']['office'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
        ];
    }

    private function syncItems(AnnualProcurementPlan $app, array $items): void
    {
        $existingAppVersionsBySourceKey = $app->items()
            ->get()
            ->mapWithKeys(function (AnnualProcurementPlanItem $item): array {
                $key = $this->ppmpItemSourceKey($item->source_ppmp_document_id, $item->source_ppmp_item_id);

                return $key === null ? [] : [$key => $item->app_version];
            });

        $app->items()->delete();
        $items = $this->uniqueAppItems($items);

        foreach (array_values($items) as $index => $item) {
            $estimatedBudget = $this->nullableMoney($item['estimated_budget'] ?? null);
            $sourceKey = $this->appItemSourceKey($item);
            $versionAttributes = $this->appItemVersionAttributes($app);

            if (
                $sourceKey !== null
                && $existingAppVersionsBySourceKey->has($sourceKey)
                && Schema::hasColumn('annual_procurement_plan_items', 'app_version')
            ) {
                $versionAttributes['app_version'] = max((int) $existingAppVersionsBySourceKey->get($sourceKey), 1);
            }

            AnnualProcurementPlanItem::create(array_merge([
                'annual_procurement_plan_id' => $app->id,
                'source_ppmp_document_id' => $item['source_ppmp_document_id'] ?? null,
                'source_ppmp_item_id' => $item['source_ppmp_item_id'] ?? null,
                'row_order' => $index,
                'category' => $item['category'] ?? 'general_requirements',
                'project_title' => $item['project_title'] ?? null,
                'end_user_unit' => $item['end_user_unit'] ?? null,
                'general_description' => $item['general_description'] ?? null,
                'mode_of_procurement' => $item['mode_of_procurement'] ?? null,
                'early_procurement_activity' => $item['early_procurement_activity'] ?? null,
                'bid_evaluation_criteria' => $item['bid_evaluation_criteria'] ?? null,
                'start_procurement_activity' => $item['start_procurement_activity'] ?? null,
                'end_procurement_activity' => $item['end_procurement_activity'] ?? null,
                'source_of_funds' => $item['source_of_funds'] ?? null,
                'estimated_budget' => $estimatedBudget,
                'procurement_strategy_or_tools' => $item['procurement_strategy_or_tools'] ?? null,
                'procurement_program_project' => $item['project_title'] ?? null,
                'pmo_end_user' => $item['end_user_unit'] ?? null,
                'ads_post_ib_rei' => $item['start_procurement_activity'] ?? null,
                'contract_signing' => $item['end_procurement_activity'] ?? null,
                'estimated_total' => $estimatedBudget,
                'remarks' => $item['remarks'] ?? null,
                'sort_order' => $index,
            ], $versionAttributes));
        }
    }

    private function filteredItems(array $items): array
    {
        return collect($items)
            ->filter(fn (array $item) => collect(self::ITEM_COLUMNS)
                ->some(fn (string $column) => $this->hasCellValue($item[$column] ?? null)))
            ->map(function (array $item): array {
                return [
                    'source_ppmp_document_id' => $item['source_ppmp_document_id'] ?? null,
                    'source_ppmp_item_id' => $item['source_ppmp_item_id'] ?? null,
                    'category' => $this->cleanText($item['category'] ?? null) ?: 'general_requirements',
                    'project_title' => $this->cleanText($item['project_title'] ?? ($item['procurement_program_project'] ?? null)),
                    'end_user_unit' => $this->cleanText($item['end_user_unit'] ?? ($item['pmo_end_user'] ?? null)),
                    'general_description' => $this->cleanText($item['general_description'] ?? null),
                    'mode_of_procurement' => $this->cleanText($item['mode_of_procurement'] ?? null),
                    'early_procurement_activity' => $this->cleanText($item['early_procurement_activity'] ?? null),
                    'bid_evaluation_criteria' => $this->cleanText($item['bid_evaluation_criteria'] ?? null),
                    'start_procurement_activity' => $this->cleanText($item['start_procurement_activity'] ?? ($item['ads_post_ib_rei'] ?? null)),
                    'end_procurement_activity' => $this->cleanText($item['end_procurement_activity'] ?? ($item['contract_signing'] ?? null)),
                    'source_of_funds' => $this->cleanText($item['source_of_funds'] ?? null),
                    'estimated_budget' => $this->numericString($item['estimated_budget'] ?? ($item['estimated_total'] ?? null)),
                    'procurement_strategy_or_tools' => $this->cleanText($item['procurement_strategy_or_tools'] ?? null),
                    'remarks' => $this->cleanText($item['remarks'] ?? null),
                ];
            })
            ->values()
            ->all();
    }

    private function totals(array $items): array
    {
        $budget = fn (array $item) => $this->money($item['estimated_budget'] ?? ($item['estimated_total'] ?? 0));

        return [
            'total_epa_budget' => collect($items)
                ->filter(fn (array $item) => strcasecmp((string) ($item['early_procurement_activity'] ?? ''), 'yes') === 0)
                ->sum($budget),
            'total_cse_budget' => collect($items)
                ->filter(fn (array $item) => ($item['category'] ?? null) === 'cse')
                ->sum($budget),
            'total_estimated_budget' => collect($items)->sum($budget),
            'total_personal_outlay' => collect($items)->sum(fn (array $item) => $this->money($item['personal_outlay'] ?? 0)),
            'total_mooe' => collect($items)->sum(fn (array $item) => $this->money($item['mooe'] ?? 0)),
            'total_co' => collect($items)->sum(fn (array $item) => $this->money($item['co'] ?? 0)),
        ];
    }

    private function ensureAppNumber(AnnualProcurementPlan $app): void
    {
        if (filled($app->app_number)) {
            return;
        }

        $year = $app->fiscal_year ?: now()->year;
        $sequence = AnnualProcurementPlan::query()
            ->where('fiscal_year', $year)
            ->whereNotNull('app_number')
            ->count() + 1;

        do {
            $number = sprintf('APP-%s-%04d', $year, $sequence++);
        } while (AnnualProcurementPlan::where('app_number', $number)->where('id', '!=', $app->id)->exists());

        $app->forceFill(['app_number' => $number])->save();
    }

    private function summary(): array
    {
        $active = AnnualProcurementPlan::query()
            ->where('status', '!=', AnnualProcurementPlan::STATUS_CANCELLED);

        return [
            'total' => (clone $active)->count(),
            'draft' => AnnualProcurementPlan::where('status', AnnualProcurementPlan::STATUS_DRAFT)->count(),
            'submitted' => AnnualProcurementPlan::where('status', AnnualProcurementPlan::STATUS_SUBMITTED)->count(),
            'approved' => AnnualProcurementPlan::where('status', AnnualProcurementPlan::STATUS_APPROVED)->count(),
            'returned' => AnnualProcurementPlan::where('status', AnnualProcurementPlan::STATUS_RETURNED)->count(),
            'budget' => (clone $active)->sum('total_estimated_budget'),
        ];
    }

    private function statuses(): array
    {
        return [
            AnnualProcurementPlan::STATUS_DRAFT,
            AnnualProcurementPlan::STATUS_CONSOLIDATED,
            AnnualProcurementPlan::STATUS_SUBMITTED,
            AnnualProcurementPlan::STATUS_APPROVED,
            AnnualProcurementPlan::STATUS_RETURNED,
            AnnualProcurementPlan::STATUS_CANCELLED,
        ];
    }

    private function defaultSignatories(): array
    {
        return [
            'prepared_by' => [
                'label' => 'Prepared By:',
                'name' => 'JOBELLE A. SOLER',
                'title' => '',
                'office' => '',
                'order' => 1,
            ],
            'recommended_by' => [
                'label' => 'Recommended By:',
                'authority' => 'By the Authority of the Bids and Awards Com.',
                'name' => 'EDMAR PETER T. TAMBIS',
                'title' => '',
                'office' => '',
                'order' => 2,
            ],
            'approved_by' => [
                'label' => 'Approved By:',
                'name' => 'AURELIO H. SUNGA, JR.',
                'title' => '',
                'office' => '',
                'order' => 3,
            ],
        ];
    }

    private function appDesignationMetadata(?string $designation): array
    {
        $normalized = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', ' ', (string) $designation)));

        return match ($normalized) {
            'bac secretariat' => [
                'title' => 'BAC Secretariat',
                'office' => 'Bids and Awards Committee Secretariat',
                'role_code' => 'bac_secretariat',
                'role_name' => User::ROLE_BAC_SECRETARIAT,
                'account_code' => 'BACSEC-004',
                'office_code' => 'BACSEC',
            ],
            'bac chair' => [
                'title' => 'BAC Chair',
                'office' => 'Bids and Awards Committee',
                'role_code' => 'bac_chair',
                'role_name' => User::ROLE_BAC_CHAIR,
                'account_code' => 'BACCHAIR-001',
                'office_code' => 'BAC',
            ],
            'bac vice chairperson' => [
                'title' => 'BAC Vice Chairperson',
                'office' => 'Bids and Awards Committee',
                'role_code' => 'bac_vice_chairperson',
                'role_name' => User::ROLE_BAC_VICE_CHAIRPERSON,
                'account_code' => 'BACVICE-001',
                'office_code' => 'BAC',
            ],
            'bac member' => [
                'title' => 'BAC Member',
                'office' => 'Bids and Awards Committee',
                'role_code' => 'bac_member',
                'role_name' => User::ROLE_BAC_MEMBER,
                'account_code' => 'BACMEM-001',
                'office_code' => 'BAC',
            ],
            'budget officer', 'municipal budget officer' => [
                'title' => 'Municipal Budget Officer',
                'office' => 'Municipal Budget Office',
                'role_code' => 'budget_officer',
                'role_name' => User::ROLE_BUDGET,
                'account_code' => 'BUDGET-001',
                'office_code' => 'MBO',
            ],
            'accounting officer', 'municipal accountant' => [
                'title' => 'Municipal Accountant',
                'office' => 'Municipal Accounting Office',
                'role_code' => 'accounting_officer',
                'role_name' => User::ROLE_ACCOUNTING,
                'account_code' => 'ACCOUNTING-001',
                'office_code' => 'MACCO',
            ],
            'municipal mayor' => [
                'title' => 'Municipal Mayor',
                'office' => 'Office of the Municipal Mayor',
                'role_code' => 'head_office',
                'role_name' => User::ROLE_HEAD_OFFICE,
                'account_code' => 'MO-001',
                'office_code' => 'MO',
            ],
            'head of office end user' => [
                'title' => 'Head of Office / End User',
                'role_code' => 'head_office',
                'role_name' => User::ROLE_HEAD_OFFICE,
            ],
            'head of the procuring entity' => [
                'title' => 'Head of the Procuring Entity',
                'office' => 'Office of the Municipal Mayor',
                'role_code' => 'approving_authority',
                'role_name' => User::ROLE_APPROVING_AUTHORITY,
                'account_code' => 'HOPE-001',
                'office_code' => 'MO',
            ],
            'pr numbering staff' => [
                'title' => 'PR Numbering Staff',
                'role_code' => 'pr_numbering_staff',
                'role_name' => User::ROLE_PR_NUMBERING,
                'account_code' => 'PRNO-001',
            ],
            default => [],
        };
    }

    private function signatories(array $input): array
    {
        $defaults = $this->defaultSignatories();

        foreach ($defaults as $key => $default) {
            $titleWasManuallySelected = filter_var($input[$key]['title_manually_selected'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $selectedTitle = $titleWasManuallySelected ? $this->cleanText($input[$key]['title'] ?? '') : '';
            $designationMetadata = $this->appDesignationMetadata($selectedTitle);
            $hasDesignationMetadata = $designationMetadata !== [];

            $defaults[$key]['name'] = $this->cleanText($input[$key]['name'] ?? $default['name']);
            $defaults[$key]['title'] = $selectedTitle === '' ? '' : ($designationMetadata['title'] ?? $selectedTitle);
            $defaults[$key]['title_manually_selected'] = $selectedTitle !== '' && $titleWasManuallySelected;
            $defaults[$key]['office'] = $selectedTitle === ''
                ? ''
                : $this->cleanText($designationMetadata['office'] ?? ($input[$key]['office'] ?? ($default['office'] ?? null)));
            $defaults[$key]['label'] = $default['label'];

            foreach (['authority', 'order'] as $metadata) {
                if (isset($default[$metadata])) {
                    $defaults[$key][$metadata] = $default[$metadata];
                }
            }

            foreach (['role_code', 'role_name', 'account_code', 'office_code'] as $metadata) {
                if ($hasDesignationMetadata) {
                    if (array_key_exists($metadata, $designationMetadata)) {
                        $defaults[$key][$metadata] = $designationMetadata[$metadata];
                    } else {
                        unset($defaults[$key][$metadata]);
                    }
                } elseif ($selectedTitle !== '' && isset($default[$metadata])) {
                    $defaults[$key][$metadata] = $default[$metadata];
                } else {
                    unset($defaults[$key][$metadata]);
                }
            }
        }

        return $defaults;
    }

    private function appSignatoryDesignationError(array $signatories): ?string
    {
        foreach (array_keys($this->defaultSignatories()) as $key) {
            if (blank($signatories[$key]['title'] ?? null)) {
                return 'Please select a role/designation before submitting.';
            }
        }

        return null;
    }

    private function createAppSignatureRequests(AnnualProcurementPlan $app, User $requestedBy): void
    {
        app(SignatureRequestService::class)->createRequestsForDocument(
            $app,
            'app',
            [
                'signing_mode' => 'parallel',
                'signers' => collect($this->signatories($app->signatories_json ?? []))
                    ->map(fn (array $signatory, string $slot): array => [
                        'slot' => $slot,
                        'label' => str($slot)->replace('_', ' ')->title()->toString(),
                        'role_code' => $signatory['role_code'] ?? null,
                        'role_name' => $signatory['role_name'] ?? ($signatory['title'] ?? null),
                        'account_code' => $this->accountCodeForSignatory($signatory),
                        'office_code' => $signatory['office_code'] ?? null,
                        'order' => $signatory['order'] ?? 1,
                        'required' => true,
                        'printed_name' => $signatory['name'] ?? null,
                        'designation' => $signatory['title'] ?? null,
                        'signer_position' => $signatory['title'] ?? null,
                    ])
                    ->values()
                    ->all(),
            ],
            $requestedBy,
        );
    }

    private function accountCodeForSignatory(array $signatory): ?string
    {
        $name = $this->cleanText($signatory['name'] ?? null);

        if ($name) {
            $matchedUserId = User::query()
                ->where('status', User::STATUS_ACTIVE)
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->value('user_id');

            if ($matchedUserId) {
                return $matchedUserId;
            }
        }

        return $signatory['account_code'] ?? null;
    }

    private function canConsolidate(?User $user): bool
    {
        if (! $user || $user->isAdmin()) {
            return false;
        }

        return (bool) ($user?->hasRole('bac_secretariat')
            || $user?->hasRole(User::ROLE_BAC_SECRETARIAT)
            || $user?->hasPermission('app.consolidate'));
    }

    private function canApprove(?User $user): bool
    {
        if (! $user || $user->isAdmin()) {
            return false;
        }

        return (bool) ($user?->hasRole('approving_authority')
            || $user?->hasRole(User::ROLE_APPROVING_AUTHORITY)
            || $user?->hasRole('bac_chair')
            || $user?->hasRole(User::ROLE_BAC_CHAIR)
            || $user?->hasPermission('app.approve'));
    }

    private function ensureCanView(Request $request): void
    {
        $user = $request->user();

        if ($this->canConsolidate($user) || $this->canApprove($user) || $user?->isAdmin() || $user?->hasPermission('app.view')) {
            return;
        }

        AuditLogger::log('Annual Procurement Plan', 'APP View Unauthorized', 'User attempted to view APP records without access.', null, null, null, 'warning');
        abort(403);
    }

    private function deny(Request $request, string $action, string $description, ?AnnualProcurementPlan $app = null): RedirectResponse
    {
        AuditLogger::log('Annual Procurement Plan', $action, $description, $app, null, null, 'warning');

        return redirect()
            ->route('dashboard')
            ->with('error', 'Unauthorized access.');
    }

    private function notifyRole(string $role, string $subject, string $message, string $url, AnnualProcurementPlan $app): void
    {
        try {
            app(NotificationDispatchService::class)->notifyRole($role, $subject, $message, $url, [
                'document_type' => 'Annual Procurement Plan',
                'tracking_number' => $app->displayNumber(),
                'status' => $app->status,
            ]);
        } catch (Throwable $exception) {
            Log::warning('PaperTrail APP notification failed.', [
                'role' => $role,
                'app_id' => $app->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function auditSummary(AnnualProcurementPlan $app): array
    {
        return [
            'app_number' => $app->app_number,
            'document_reference_number' => $app->document_reference_number,
            'fiscal_year' => $app->fiscal_year,
            'plan_type' => $app->plan_type,
            'update_version_no' => $app->update_version_no,
            'status' => $app->status,
            'total_estimated_budget' => $app->total_estimated_budget,
            'items_count' => $app->items()->count(),
        ];
    }

    private function jsonArray(mixed $json): array
    {
        $decoded = filled($json) ? json_decode((string) $json, true) : [];

        return is_array($decoded) ? $decoded : [];
    }

    private function cleanText(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return trim(preg_replace('/\s+/u', ' ', (string) $value));
    }

    private function hasCellValue(mixed $value): bool
    {
        if ($value === 0 || $value === '0') {
            return true;
        }

        return filled($value);
    }

    private function truthy(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }

    private function numericString(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $cleaned = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return filled($cleaned) ? $cleaned : null;
    }

    private function nullableMoney(mixed $value): ?float
    {
        return filled($value) ? $this->money($value) : null;
    }

    private function money(mixed $value): float
    {
        return filled($value) ? max((float) $this->numericString($value), 0) : 0.0;
    }

    private function sanitizeHtml(?string $html): ?string
    {
        if (! filled($html)) {
            return null;
        }

        $html = preg_replace('/<\s*(script|style|iframe|object|embed)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $html);
        $html = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/is', '', $html);
        $html = preg_replace('/\s+(href|src)\s*=\s*("|\')\s*javascript:.*?\2/is', '', $html);

        return trim($html);
    }
}
