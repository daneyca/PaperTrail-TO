<?php

namespace App\Http\Controllers\HeadOffice;

use App\Http\Controllers\Controller;
use App\Models\AnnualProcurementPlan;
use App\Models\AnnualProcurementPlanItem;
use App\Models\AppConsolidation;
use App\Models\AppItem;
use App\Models\DocumentRoutingHistory;
use App\Models\Office;
use App\Models\PpmpItem;
use App\Models\ProcurementDocument;
use App\Models\SignatureRequest;
use App\Models\SvpProcurementChain;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Bacsec002PrSignatoryRoutingService;
use App\Services\DocumentDraftService;
use App\Services\EndUserPrSignatoryRoutingService;
use App\Services\PurchaseRequestBudgetService;
use App\Services\SvpChainService;
use App\Services\SystemNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseRequestController extends Controller
{
    private const CERTIFICATION_TEXT = 'Certified that the following items are in accordance with approved Procurement Plan.';

    private const DEFAULT_PR_SIGNATORIES = [
        'certification' => [
            'signature' => '',
            'printed_name' => 'MEAGAN C. MATUTES',
            'designation' => '',
            'date' => '',
        ],
        'budget' => [
            'signature' => '',
            'printed_name' => 'ENGR. AURELIO H. SUNGA, JR.',
            'designation' => '',
            'date' => '',
        ],
        'requested' => [
            'signature' => '',
            'printed_name' => '',
            'designation' => '',
            'date' => '',
        ],
        'approved' => [
            'signature' => '',
            'printed_name' => 'JESSICA MARIE G. ESCAÑO',
            'designation' => '',
            'date' => '',
        ],
    ];

    private const BACSEC002_PR_SIGNATORIES = [
        'certification' => [
            'signature' => '',
            'printed_name' => 'MEAGAN C. MATUTES',
            'designation' => '',
            'date' => '',
        ],
        'budget' => [
            'signature' => '',
            'printed_name' => 'MAVEL D. VISMANOS',
            'designation' => '',
            'date' => '',
        ],
        'requested' => [
            'signature' => '',
            'printed_name' => 'JOSEFINA M. ESCAÑO',
            'designation' => '',
            'date' => '',
        ],
        'approved' => [
            'signature' => '',
            'printed_name' => 'HON. ROD IVAN CUARES PANO',
            'designation' => '',
            'date' => '',
        ],
    ];

    private const MODIFIABLE_STATUSES = [
        ProcurementDocument::STATUS_PR_DRAFT,
        ProcurementDocument::STATUS_RETURNED_BY_PR_NUMBERING_STAFF,
        ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
        ProcurementDocument::STATUS_RETURNED_BY_BUDGET,
        ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING,
        ProcurementDocument::STATUS_RETURNED_BY_BAC_MEMBER,
        ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR,
        ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY,
        'pr_returned',
    ];

    public function index(Request $request): View
    {
        AuditLogger::log('Purchase Request Submission', 'PR List Viewed', 'Head of Office viewed office Purchase Request records.');

        $query = $this->officePrQuery($request->user())
            ->with(['submittingOffice', 'submittedBy', 'currentOffice']);

        $this->applyFilters($query, $request);

        return view('head-office.pr.index', [
            'documents' => $query->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary($request->user()),
            'recentDrafts' => app(DocumentDraftService::class)->getPurchaseRequestDraftsForUser($request->user(), 5),
            'filters' => $request->only(['search', 'document_type', 'fiscal_year', 'status', 'stage', 'date_from', 'date_to']),
            'documentTypes' => $this->officePrQuery($request->user())
                ->select('document_type')
                ->whereNotNull('document_type')
                ->distinct()
                ->orderBy('document_type')
                ->pluck('document_type'),
            'fiscalYears' => $this->officePrQuery($request->user())->select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
            'statuses' => $this->statuses(),
            'stages' => $this->officePrQuery($request->user())
                ->select('stage')
                ->whereNotNull('stage')
                ->distinct()
                ->orderBy('stage')
                ->pluck('stage'),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $office = $this->selectedRequestingOffice($request, $user) ?: $this->assignedOffice($user);

        if (! $office) {
            return redirect()
                ->route('head-office.dashboard')
                ->with('error', 'Your account does not have an assigned office. Please contact the administrator.');
        }

        AuditLogger::log('Purchase Request Submission', 'PR Create Page Viewed', 'Head of Office opened the Submit Purchase Request form.');

        $signatories = $this->defaultPrSignatories(null, $request->user(), $office);
        $appReferenceItems = $this->approvedAppReferenceItems($office, now()->year, $this->canSelectRequestingOffice($user));
        $selectedAppItemId = $this->createSelectedAppReferenceId($request);
        $selectedAppItem = $this->selectedApprovedAppItem($selectedAppItemId, $office);
        $startPrForm = $this->shouldStartPrForm($request, $selectedAppItem);
        $budgetService = app(PurchaseRequestBudgetService::class);

        if (filled($selectedAppItemId) && ! $selectedAppItem) {
            return redirect()
                ->route('head-office.pr.create')
                ->with('error', 'Selected APP project is not available for this requesting office.');
        }

        return view('head-office.pr.create', [
            'document' => new ProcurementDocument([
                'document_type' => 'PR',
                'fiscal_year' => now()->year,
                'department_name' => $office->name,
                'fund_cluster' => 'General Fund',
                'priority' => 'normal',
                'status' => ProcurementDocument::STATUS_PR_DRAFT,
                'purpose' => $selectedAppItem ? $this->purposeFromAppItem($selectedAppItem) : null,
                'requested_by_name' => $signatories['requested']['printed_name'],
                'requested_by_designation' => $signatories['requested']['designation'],
                'approved_by_name' => $signatories['approved']['printed_name'],
                'approved_by_designation' => $signatories['approved']['designation'],
                'certification_text' => self::CERTIFICATION_TEXT,
                'pr_signatories' => $signatories,
                'app_item_id' => $selectedAppItem?->id,
                'app_consolidation_id' => $selectedAppItem?->app_consolidation_id,
            ]),
            'office' => $office,
            'user' => $user,
            'canSelectRequestingOffice' => $this->canSelectRequestingOffice($user),
            'requestingOffices' => $this->canSelectRequestingOffice($user) ? $this->requestingOffices() : collect(),
            'items' => collect([null]),
            'recentDrafts' => app(DocumentDraftService::class)->getPurchaseRequestDraftsForUser($user, 6),
            'appReferenceItems' => $appReferenceItems,
            'selectedAppItem' => $selectedAppItem,
            'startPrForm' => $startPrForm,
            'ppmpSupplyItems' => $this->ppmpSupplyItemsForAppItem($selectedAppItem),
            'appBudgetInfo' => $budgetService->info($selectedAppItem),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $office = $this->assignedOffice($user);

        if (! $office) {
            return redirect()
                ->route('head-office.dashboard')
                ->with('error', 'Your account does not have an assigned office. Please contact the administrator.');
        }

        $formAction = $this->prFormAction($request);
        $submit = $formAction === 'submit_for_pr_number';
        $bacsec002SignatoryRouting = app(Bacsec002PrSignatoryRoutingService::class);
        $endUserSignatoryRouting = app(EndUserPrSignatoryRoutingService::class);
        $useBacsec002SignatoryRouting = $submit && $bacsec002SignatoryRouting->shouldUseForUser($user);
        $useEndUserSignatoryRouting = $submit && ! $useBacsec002SignatoryRouting && $endUserSignatoryRouting->shouldUseForUser($user);
        $useSignatoryRouting = $useBacsec002SignatoryRouting || $useEndUserSignatoryRouting;
        $validated = $this->validatedPayload($request, $submit, $user, $this->canSelectRequestingOffice($user));
        $office = $this->selectedRequestingOffice($request, $user) ?: $office;
        $validated = $this->applySelectedRequestingOfficeToPayload($validated, $office, $user);
        $signatories = $this->normalizePrSignatories($validated['signatories'] ?? [], null, $user, $office);
        $selectedAppItem = $this->selectedApprovedAppItem($this->submittedAppReferenceId($validated), $office);
        $target = ($submit && ! $useSignatoryRouting) ? $this->prNumberingTarget() : ['office' => null, 'user' => null];

        if (filled($validated['app_item_id'] ?? null) && ! $selectedAppItem) {
            return back()->withInput()->with('error', 'Selected APP reference must be from a consolidated, submitted, or approved APP item for the requesting office.');
        }

        if ($submit && $useSignatoryRouting && ! $selectedAppItem) {
            return back()->withInput()->with('error', 'Please select a consolidated, submitted, or approved APP reference before submitting this Purchase Request.');
        }

        if ($submit && ($error = app(PurchaseRequestBudgetService::class)->submissionErrorForItems($selectedAppItem, $validated['items'] ?? []))) {
            return back()->withInput()->with('error', $error);
        }

        if ($submit && ! $useSignatoryRouting && (! $target['office'] || ! $target['user'])) {
            return back()->withInput()->with('error', 'PR Numbering Staff routing target is not configured.');
        }

        if ($submit && $useBacsec002SignatoryRouting && ($error = $bacsec002SignatoryRouting->submissionError($signatories))) {
            return back()->withInput()->with('error', $error);
        }

        if ($submit && $useEndUserSignatoryRouting && ($error = $endUserSignatoryRouting->submissionError($signatories, $this->routingPreviewDocument(null, $user, $office)))) {
            return back()->withInput()->with('error', $error);
        }

        $document = DB::transaction(function () use ($request, $validated, $signatories, $office, $submit, $target, $useBacsec002SignatoryRouting, $useEndUserSignatoryRouting, $selectedAppItem) {
            $document = ProcurementDocument::create([
                'tracking_number' => null,
                'document_type' => 'PR',
                'title' => $this->titleFromPurpose($validated['purpose']),
                'description' => $validated['purpose'],
                'fiscal_year' => $validated['fiscal_year'],
                'submitting_office_id' => $office->id,
                'submitted_by_user_id' => $request->user()->id,
                'prepared_by_user_id' => $request->user()->id,
                'current_office_id' => $office->id,
                'assigned_to_user_id' => $request->user()->id,
                'status' => ProcurementDocument::STATUS_PR_DRAFT,
                'stage' => 'Purchase Request Preparation',
                'priority' => $validated['priority'] ?? 'normal',
                'remarks' => $validated['remarks'] ?? null,
                'total_amount' => 0,
                'department_name' => $validated['department_name'] ?? $office->name,
                'fund_cluster' => $validated['fund_cluster'] ?? null,
                'section' => $validated['section'] ?? null,
                'responsibility_center' => $validated['responsibility_center'] ?? null,
                'purpose' => $validated['purpose'],
                'requested_by_name' => $signatories['requested']['printed_name'],
                'requested_by_designation' => $signatories['requested']['designation'],
                'approved_by_name' => $signatories['approved']['printed_name'],
                'approved_by_designation' => $signatories['approved']['designation'],
                'certification_text' => self::CERTIFICATION_TEXT,
                'pr_signatories' => $signatories,
                'app_item_id' => $selectedAppItem?->id,
                'app_consolidation_id' => $selectedAppItem?->app_consolidation_id,
            ]);

            $this->syncItems($document, $validated['items'] ?? [], $selectedAppItem?->id);

            AuditLogger::log('Purchase Request Submission', 'pr_created', 'Head of Office created a Purchase Request draft.', $document);

            if ($submit) {
                if ($useBacsec002SignatoryRouting) {
                    app(Bacsec002PrSignatoryRoutingService::class)->start($document->refresh(), $request->user());
                } elseif ($useEndUserSignatoryRouting) {
                    app(EndUserPrSignatoryRoutingService::class)->start($document->refresh(), $request->user());
                } else {
                    $this->submitDocument($document->refresh(), $request->user(), $target['office'], $target['user']);
                }
            } else {
                app(SvpChainService::class)->findOrCreateFromPr($document->refresh(), $request->user(), 'PR draft created');
            }

            return $document->refresh();
        });

        return redirect()
            ->route('head-office.pr.show', $document)
            ->with('status', $submit
                ? (($useBacsec002SignatoryRouting || $useEndUserSignatoryRouting)
                    ? ($useBacsec002SignatoryRouting
                        ? 'Purchase Request signature requests generated. After completion, submit the PR for number assignment.'
                        : 'Purchase Request signature requests generated. After all required signatures are completed, it will route to PR Numbering Staff.')
                    : 'Purchase Request submitted for PR number assignment.')
                : 'Purchase Request draft saved.');
    }

    public function show(Request $request, ProcurementDocument $document): View
    {
        if (! $this->canAccess($request->user(), $document)) {
            AuditLogger::denied('unauthorized_access_attempt', ['module' => 'Purchase Request Submission', 'description' => 'Head of Office attempted to access another office Purchase Request.', 'auditable' => $document]);
            abort(403);
        }

        AuditLogger::document('document_viewed', $document, ['module' => 'Purchase Request Submission', 'description' => 'Head of Office viewed Purchase Request detail.']);

        $document->load([
            'submittingOffice',
            'submittedBy',
            'preparedBy',
            'currentOffice',
            'assignedTo',
            'appConsolidation',
            'appItem.appConsolidation',
            'appItem.office',
            'purchaseRequestItems',
            'routingHistories.actionBy',
            'routingHistories.fromOffice',
            'routingHistories.toOffice',
        ]);

        $nextWorkflowAction = $this->nextCompletedPrWorkflowAction($document, $request->user());

        return view('head-office.pr.show', [
            'document' => $document,
            'canModify' => $this->canModify($document),
            'canSubmitToBac' => (bool) $nextWorkflowAction,
            'nextWorkflowAction' => $nextWorkflowAction,
            'canSubmitForPrNumber' => app(Bacsec002PrSignatoryRoutingService::class)->canSubmitCompletedToPrNumbering($request->user(), $document),
        ]);
    }

    public function edit(Request $request, ProcurementDocument $document): View|RedirectResponse
    {
        if (! $this->canAccess($request->user(), $document)) {
            AuditLogger::log('Purchase Request Submission', 'Unauthorized PR Access Attempt', 'Head of Office attempted to edit another office Purchase Request.', $document, null, null, 'warning');
            abort(403);
        }

        if (! $this->canModify($document)) {
            return redirect()
                ->route('head-office.pr.show', $document)
                ->with('error', 'This Purchase Request can no longer be edited.');
        }

        $document->load(['purchaseRequestItems', 'appConsolidation', 'appItem.appConsolidation', 'appItem.office']);
        $office = $this->selectedRequestingOffice($request, $request->user(), $document) ?: $this->assignedOffice($request->user());
        $selectedAppItem = $this->selectedApprovedAppItem($request->old('app_item_id', $document->app_item_id), $office)
            ?: $document->appItem;

        return view('head-office.pr.edit', [
            'document' => $document,
            'office' => $office,
            'user' => $request->user(),
            'canSelectRequestingOffice' => $this->canChangeRequestingOffice($request->user(), $document),
            'requestingOffices' => $this->canSelectRequestingOffice($request->user()) ? $this->requestingOffices() : collect(),
            'items' => $document->purchaseRequestItems->isNotEmpty() ? $document->purchaseRequestItems : collect([null]),
            'appReferenceItems' => $this->approvedAppReferenceItems(
                $office,
                $document->fiscal_year,
                $this->canChangeRequestingOffice($request->user(), $document)
            ),
            'appBudgetInfo' => app(PurchaseRequestBudgetService::class)->info($selectedAppItem, $document),
        ]);
    }

    public function update(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (! $this->canAccess($request->user(), $document)) {
            AuditLogger::log('Purchase Request Submission', 'Unauthorized PR Access Attempt', 'Head of Office attempted to update another office Purchase Request.', $document, null, null, 'warning');
            abort(403);
        }

        if (! $this->canModify($document)) {
            return redirect()
                ->route('head-office.pr.show', $document)
                ->with('error', 'This Purchase Request can no longer be edited.');
        }

        $formAction = $this->prFormAction($request);
        $submit = $formAction === 'submit_for_pr_number';
        $user = $request->user();
        $bacsec002SignatoryRouting = app(Bacsec002PrSignatoryRoutingService::class);
        $endUserSignatoryRouting = app(EndUserPrSignatoryRoutingService::class);
        $useBacsec002SignatoryRouting = $submit && $bacsec002SignatoryRouting->shouldStartFor($user, $document);
        $useEndUserSignatoryRouting = $submit && ! $useBacsec002SignatoryRouting && $endUserSignatoryRouting->shouldStartFor($user, $document);
        $useSignatoryRouting = $useBacsec002SignatoryRouting || $useEndUserSignatoryRouting;
        $validated = $this->validatedPayload($request, $submit, $user, $this->canChangeRequestingOffice($user, $document));
        $office = $this->canChangeRequestingOffice($user, $document)
            ? $this->selectedRequestingOffice($request, $user, $document)
            : ($document->submittingOffice ?: $this->assignedOffice($user));
        $office = $office ?: $this->assignedOffice($user);
        $validated = $this->applySelectedRequestingOfficeToPayload($validated, $office, $user);
        $signatories = $this->normalizePrSignatories($validated['signatories'] ?? [], $document, $user, $office);
        $selectedAppItem = $this->selectedApprovedAppItem($this->submittedAppReferenceId($validated), $office);
        $target = ($submit && ! $useSignatoryRouting) ? $this->prNumberingTarget() : ['office' => null, 'user' => null];

        if (filled($validated['app_item_id'] ?? null) && ! $selectedAppItem) {
            return back()->withInput()->with('error', 'Selected APP reference must be from a consolidated, submitted, or approved APP item for the requesting office.');
        }

        if ($submit && $useSignatoryRouting && ! $selectedAppItem) {
            return back()->withInput()->with('error', 'Please select a consolidated, submitted, or approved APP reference before submitting this Purchase Request.');
        }

        if ($submit && ($error = app(PurchaseRequestBudgetService::class)->submissionErrorForItems($selectedAppItem, $validated['items'] ?? [], $document))) {
            return back()->withInput()->with('error', $error);
        }

        if ($submit && ! $useSignatoryRouting && (! $target['office'] || ! $target['user'])) {
            return back()->withInput()->with('error', 'PR Numbering Staff routing target is not configured.');
        }

        if ($submit && $useBacsec002SignatoryRouting && ($error = $bacsec002SignatoryRouting->submissionError($signatories))) {
            return back()->withInput()->with('error', $error);
        }

        if ($submit && $useEndUserSignatoryRouting && ($error = $endUserSignatoryRouting->submissionError($signatories, $this->routingPreviewDocument($document, $user, $office)))) {
            return back()->withInput()->with('error', $error);
        }

        DB::transaction(function () use ($request, $document, $validated, $signatories, $submit, $target, $office, $user, $useBacsec002SignatoryRouting, $useEndUserSignatoryRouting, $selectedAppItem) {
            $oldValues = $document->only(['purpose', 'department_name', 'fund_cluster', 'section', 'responsibility_center', 'status', 'total_amount', 'pr_signatories', 'app_consolidation_id', 'app_item_id']);

            $updates = [
                'title' => $this->titleFromPurpose($validated['purpose']),
                'description' => $validated['purpose'],
                'fiscal_year' => $validated['fiscal_year'],
                'priority' => $validated['priority'] ?? 'normal',
                'remarks' => $validated['remarks'] ?? null,
                'prepared_by_user_id' => $request->user()->id,
                'department_name' => $validated['department_name'] ?? $document->department_name ?? $document->submittingOffice?->name,
                'fund_cluster' => $validated['fund_cluster'] ?? null,
                'section' => $validated['section'] ?? null,
                'responsibility_center' => $validated['responsibility_center'] ?? null,
                'purpose' => $validated['purpose'],
                'requested_by_name' => $signatories['requested']['printed_name'],
                'requested_by_designation' => $signatories['requested']['designation'],
                'approved_by_name' => $signatories['approved']['printed_name'],
                'approved_by_designation' => $signatories['approved']['designation'],
                'certification_text' => self::CERTIFICATION_TEXT,
                'pr_signatories' => $signatories,
                'app_item_id' => $selectedAppItem?->id,
                'app_consolidation_id' => $selectedAppItem?->app_consolidation_id,
            ];

            if ($this->canChangeRequestingOffice($user, $document) && $office) {
                $updates['submitting_office_id'] = $office->id;
                $updates['current_office_id'] = $office->id;
            }

            $document->update($updates);

            $this->syncItems($document, $validated['items'] ?? [], $selectedAppItem?->id);

            AuditLogger::log('Purchase Request Submission', 'document_updated', 'Head of Office updated a Purchase Request draft.', $document, $oldValues, $document->fresh()->only(['purpose', 'department_name', 'fund_cluster', 'section', 'responsibility_center', 'status', 'total_amount']));

            if ($submit) {
                if ($useBacsec002SignatoryRouting) {
                    app(Bacsec002PrSignatoryRoutingService::class)->start($document->refresh(), $request->user());
                } elseif ($useEndUserSignatoryRouting) {
                    app(EndUserPrSignatoryRoutingService::class)->start($document->refresh(), $request->user());
                } else {
                    $this->submitDocument($document->refresh(), $request->user(), $target['office'], $target['user']);
                }
            } else {
                app(SvpChainService::class)->findOrCreateFromPr($document->refresh(), $request->user(), 'PR draft updated');
            }
        });

        return redirect()
            ->route('head-office.pr.show', $document)
            ->with('status', $submit
                ? (($useBacsec002SignatoryRouting || $useEndUserSignatoryRouting)
                    ? ($useBacsec002SignatoryRouting
                        ? 'Purchase Request signature requests generated. After completion, submit the PR for number assignment.'
                        : 'Purchase Request signature requests generated. After all required signatures are completed, it will route to PR Numbering Staff.')
                    : 'Purchase Request submitted for PR number assignment.')
                : 'Purchase Request draft updated.');
    }

    public function submit(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (! $this->canAccess($request->user(), $document)) {
            AuditLogger::log('Purchase Request Submission', 'Unauthorized PR Access Attempt', 'Head of Office attempted to submit another office Purchase Request.', $document, null, null, 'warning');
            abort(403);
        }

        $submitToBac = $this->canSubmitToBacSecretariat($document, $request->user());
        $signatoryRouting = app(Bacsec002PrSignatoryRoutingService::class);
        $endUserSignatoryRouting = app(EndUserPrSignatoryRoutingService::class);
        $submitSignedPrToNumbering = ! $submitToBac
            && $signatoryRouting->canSubmitCompletedToPrNumbering($request->user(), $document);
        $useBacsec002SignatoryRouting = ! $submitToBac
            && ! $submitSignedPrToNumbering
            && $signatoryRouting->shouldStartFor($request->user(), $document);
        $useEndUserSignatoryRouting = ! $submitToBac
            && ! $submitSignedPrToNumbering
            && ! $useBacsec002SignatoryRouting
            && $endUserSignatoryRouting->shouldStartFor($request->user(), $document);
        $useSignatoryRouting = $useBacsec002SignatoryRouting || $useEndUserSignatoryRouting;

        if (! $submitToBac && ! $submitSignedPrToNumbering && ! $this->canModify($document)) {
            return back()->with('error', 'Only draft, returned, signed, or PR-numbered Purchase Requests can be submitted.');
        }

        $error = $submitToBac ? null : $this->existingSubmissionError($document);

        if ($error) {
            return back()->with('error', $error);
        }

        if ($useBacsec002SignatoryRouting && ($error = $signatoryRouting->submissionError($document->pr_signatories ?: []))) {
            return back()->with('error', $error);
        }

        if ($useEndUserSignatoryRouting && ($error = $endUserSignatoryRouting->submissionError($document->pr_signatories ?: [], $document))) {
            return back()->with('error', $error);
        }

        if ($useSignatoryRouting && ! $this->documentHasApprovedAppReference($document)) {
            return back()->with('error', 'Please select a consolidated, submitted, or approved APP reference before submitting this Purchase Request.');
        }

        if (! $submitToBac && ($error = app(PurchaseRequestBudgetService::class)->submissionErrorForDocument($document))) {
            return back()->with('error', $error);
        }

        $target = $submitToBac
            ? $this->bacsec002ReferenceTarget()
            : ($useSignatoryRouting ? ['office' => null, 'user' => null] : $this->prNumberingTarget());

        if (! $useSignatoryRouting && (! $target['office'] || ! $target['user'])) {
            return back()->with('error', $submitToBac
                ? 'BAC Resolution routing target is not configured.'
                : 'PR Numbering Staff routing target is not configured.');
        }

        DB::transaction(function () use ($request, $document, $target, $submitToBac, $useBacsec002SignatoryRouting, $useEndUserSignatoryRouting) {
            if ($submitToBac) {
                $this->forwardPrReferenceToBacsec002($document->refresh(), $request->user(), $target['user']);

                return;
            }

            if ($useBacsec002SignatoryRouting) {
                app(Bacsec002PrSignatoryRoutingService::class)->start($document->refresh(), $request->user());

                return;
            }

            if ($useEndUserSignatoryRouting) {
                app(EndUserPrSignatoryRoutingService::class)->start($document->refresh(), $request->user());

                return;
            }

            $this->submitDocument($document->refresh(), $request->user(), $target['office'], $target['user']);
        });

        return redirect()
            ->route('head-office.pr.show', $document)
            ->with('status', $submitToBac
                ? 'Purchase Request copy sent to BACSEC-002 for BAC Resolution preparation.'
                : ($submitSignedPrToNumbering
                    ? 'Purchase Request submitted for PR number assignment.'
                    : ($useSignatoryRouting
                        ? ($useBacsec002SignatoryRouting
                            ? 'Purchase Request signature requests generated. After completion, submit the PR for number assignment.'
                            : 'Purchase Request signature requests generated. After all required signatures are completed, it will route to PR Numbering Staff.')
                        : 'Purchase Request submitted for PR number assignment.')));
    }

    public function print(Request $request, ProcurementDocument $document): View
    {
        if (! $this->canAccess($request->user(), $document)) {
            AuditLogger::denied('unauthorized_access_attempt', ['module' => 'Purchase Request Submission', 'description' => 'Head of Office attempted to print another office Purchase Request.', 'auditable' => $document]);
            abort(403);
        }

        AuditLogger::document('document_printed', $document, ['module' => 'Purchase Request Submission', 'description' => 'Head of Office opened the print view for a Purchase Request.']);

        return view('head-office.pr.print', [
            'document' => $document->load(['submittingOffice', 'submittedBy', 'preparedBy', 'purchaseRequestItems']),
        ]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();
            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('document_reference_number', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhere('pr_no', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        });

        foreach (['document_type', 'fiscal_year', 'stage', 'created_year', 'created_month'] as $field) {
            $query->when($request->filled($field), fn (Builder $builder) => $builder->where($field, $request->input($field)));
        }

        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', function (Builder $builder) use ($request) {
            $status = (string) $request->input('status');
            $groups = [
                'draft' => [ProcurementDocument::STATUS_PR_DRAFT],
                'submitted' => [
                    ProcurementDocument::STATUS_PR_PENDING_SIGNATORIES,
                    ProcurementDocument::STATUS_PR_SIGNATORIES_COMPLETED,
                    ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT,
                    ProcurementDocument::STATUS_PR_SUBMITTED,
                    ProcurementDocument::STATUS_SUBMITTED_TO_BAC_SECRETARIAT,
                ],
                'reviewed' => [
                    ProcurementDocument::STATUS_PR_RECEIVED_BY_BAC_SECRETARIAT,
                    ProcurementDocument::STATUS_UNDER_PR_VALIDATION,
                    ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW,
                    ProcurementDocument::STATUS_BUDGET_REVIEWED,
                    ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW,
                    ProcurementDocument::STATUS_ACCOUNTING_REVIEWED,
                ],
                'approved' => [
                    ProcurementDocument::STATUS_APPROVED,
                    ProcurementDocument::STATUS_READY_FOR_PO,
                    ProcurementDocument::STATUS_PO_APPROVED,
                ],
                'returned' => [
                    ProcurementDocument::STATUS_RETURNED_BY_PR_NUMBERING_STAFF,
                    ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
                    ProcurementDocument::STATUS_RETURNED_BY_BUDGET,
                    ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING,
                    ProcurementDocument::STATUS_RETURNED_BY_BAC_MEMBER,
                    ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR,
                    ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY,
                    'pr_returned',
                ],
            ];

            $statuses = $groups[$status] ?? [$status];
            $builder->whereIn('status', $statuses);
        });

        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('updated_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('updated_at', '<=', $request->date('date_to')));
    }

    private function officePrQuery(User $user): Builder
    {
        return ProcurementDocument::query()
            ->whereIn('document_type', ['PR', 'Purchase Request'])
            ->where(function (Builder $query) use ($user) {
                if ($this->canSelectRequestingOffice($user)) {
                    $query->where('submitted_by_user_id', $user->id)
                        ->orWhere('prepared_by_user_id', $user->id)
                        ->orWhere('assigned_to_user_id', $user->id);

                    return;
                }

                if ($user->office_id) {
                    $query->where('submitting_office_id', $user->office_id);
                }

                $query->orWhere('submitted_by_user_id', $user->id)
                    ->orWhere('prepared_by_user_id', $user->id);
            });
    }

    private function canAccess(User $user, ProcurementDocument $document): bool
    {
        return in_array($document->document_type, ['PR', 'Purchase Request'], true)
            && $this->officePrQuery($user)->whereKey($document->id)->exists();
    }

    private function canModify(ProcurementDocument $document): bool
    {
        return in_array($document->status, self::MODIFIABLE_STATUSES, true);
    }

    private function canSubmitToBacSecretariat(ProcurementDocument $document, ?User $user = null): bool
    {
        $readyForBacSubmission = $this->completedPrReadyForBacResolution($document);

        if (! $readyForBacSubmission || ! $user) {
            return $readyForBacSubmission;
        }

        if ($document->assigned_to_user_id) {
            return (int) $document->assigned_to_user_id === (int) $user->id;
        }

        return $document->current_office_id
            && $user->office_id
            && (int) $document->current_office_id === (int) $user->office_id;
    }

    private function completedPrReadyForBacResolution(ProcurementDocument $document): bool
    {
        $amount = (float) $document->total_amount;

        return in_array($document->document_type, ['PR', 'Purchase Request'], true)
            && $document->status === ProcurementDocument::STATUS_PR_NUMBER_ASSIGNED_RETURNED_TO_END_USER
            && $document->pr_number_status === ProcurementDocument::PR_NUMBER_STATUS_ASSIGNED
            && filled($document->pr_no)
            && filled($document->pr_date)
            && $amount > 0
            && $amount <= 200000
            && $this->purchaseRequestSignaturesComplete($document);
    }

    private function purchaseRequestSignaturesComplete(ProcurementDocument $document): bool
    {
        $requiredSignatures = SignatureRequest::query()
            ->forDocument('purchase_request', $document->id)
            ->where('is_required', true);

        if ((clone $requiredSignatures)->count() === 0) {
            return false;
        }

        return ! (clone $requiredSignatures)
            ->where('status', '!=', SignatureRequest::STATUS_SIGNED)
            ->exists();
    }

    private function nextCompletedPrWorkflowAction(ProcurementDocument $document, ?User $user): ?array
    {
        if (! $this->canSubmitToBacSecretariat($document, $user)) {
            return null;
        }

        $amount = (float) $document->total_amount;
        $requiresPosting = app(SvpChainService::class)->requiresPosting($amount);

        return [
            'next_process' => 'BAC Resolution',
            'assigned_office' => 'BAC Secretariat',
            'assigned_account' => 'BACSEC-002',
            'recipient' => 'BACSEC-002',
            'button_label' => 'Send PR Copy to BACSEC-002',
            'amount_path' => $requiresPosting
                ? 'Posting required after BAC Resolution'
                : 'Posting not required at or below PHP 50,000',
            'description' => $requiresPosting
                ? 'Send a copy of this completed PR to BACSEC-002 so the BAC Resolution can be prepared. After that, it will move to BACSEC-004 for posting because the amount is above PHP 50,000 and below PHP 200,000.'
                : 'Send a copy of this completed PR to BACSEC-002 so the BAC Resolution can be prepared.',
        ];
    }

    private function assignedOffice(User $user): ?Office
    {
        return $user->assignedOffice ?: ($user->office_id ? Office::find($user->office_id) : null);
    }

    private function selectedRequestingOffice(Request $request, User $user, ?ProcurementDocument $document = null): ?Office
    {
        if (! $this->canSelectRequestingOffice($user)) {
            return $this->assignedOffice($user);
        }

        if (filled($request->input('requesting_office_id'))) {
            return Office::requesting()->whereKey((int) $request->input('requesting_office_id'))->first();
        }

        if ($document?->submitting_office_id) {
            return $document->submittingOffice ?: Office::find($document->submitting_office_id);
        }

        return $this->assignedOffice($user) ?: $this->requestingOffices()->first();
    }

    private function requestingOffices(): Collection
    {
        return Office::query()
            ->requesting()
            ->orderBy('name')
            ->get();
    }

    private function canSelectRequestingOffice(?User $user): bool
    {
        return $user
            && method_exists($user, 'hasBacsec002PurchaseRequestCapability')
            && $user->hasBacsec002PurchaseRequestCapability();
    }

    private function canChangeRequestingOffice(User $user, ProcurementDocument $document): bool
    {
        return $this->canSelectRequestingOffice($user)
            && $document->status === ProcurementDocument::STATUS_PR_DRAFT;
    }

    private function applySelectedRequestingOfficeToPayload(array $validated, ?Office $office, User $user): array
    {
        if ($office && $this->canSelectRequestingOffice($user)) {
            $validated['department_name'] = $office->name;
        }

        return $validated;
    }

    private function validatedPayload(Request $request, bool $submit, ?User $user = null, bool $requestingOfficeRequired = false): array
    {
        $request->merge([
            'items' => $this->filledItemRows($request->input('items', [])),
        ]);

        $rules = [
            'fiscal_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'department_name' => ['nullable', 'string', 'max:255'],
            'fund_cluster' => ['nullable', 'string', 'max:120'],
            'section' => [$submit ? 'required' : 'nullable', 'string', 'max:120'],
            'responsibility_center' => ['nullable', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:4000'],
            'priority' => ['nullable', 'in:normal,high,urgent'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'requested_by_name' => ['nullable', 'string', 'max:255'],
            'requested_by_designation' => ['nullable', 'string', 'max:255'],
            'signatories' => ['nullable', 'array'],
            'signatories.*.signature' => ['nullable', 'string', 'max:255'],
            'signatories.*.printed_name' => ['nullable', 'string', 'max:255'],
            'signatories.*.designation' => ['nullable', 'string', 'max:255'],
            'signatories.*.designation_manually_selected' => ['nullable', 'boolean'],
            'signatories.*.date' => ['nullable', 'string', 'max:80'],
            'items' => $submit ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
            'items.*.quantity' => [$submit ? 'required' : 'nullable', 'numeric', $submit ? 'gt:0' : 'min:0'],
            'items.*.unit_of_issue' => [$submit ? 'required' : 'nullable', 'string', 'max:100'],
            'items.*.description' => [$submit ? 'required' : 'nullable', 'string'],
            'items.*.stock_no' => ['nullable', 'string', 'max:100'],
            'items.*.estimated_unit_cost' => [$submit ? 'required' : 'nullable', 'numeric', 'min:0'],
            'items.*.estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
            'items.*.app_item_id' => ['nullable', 'integer', 'exists:app_items,id'],
            'app_item_id' => ['nullable', 'integer'],
        ];

        if ($user && $requestingOfficeRequired) {
            $rules['requesting_office_id'] = [
                'required',
                'integer',
                Rule::exists('offices', 'id')->where(fn ($query) => $query
                    ->where('status', Office::STATUS_ACTIVE)
                    ->where('is_requesting_office', true)),
            ];
        }

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request, $submit) {
            if (! $submit) {
                return;
            }

            if ($validator->errors()->any()) {
                return;
            }

            $items = $request->input('items', []);
            $total = 0;
            $validRows = 0;

            foreach ($items as $item) {
                $description = $this->normalizeItemText($item['description'] ?? '');
                $quantity = (float) ($item['quantity'] ?? 0);
                $unit = $this->normalizeItemText($item['unit_of_issue'] ?? '');
                $unitCost = (float) ($item['estimated_unit_cost'] ?? 0);

                if ($description !== '' && $quantity > 0 && $unit !== '') {
                    $validRows++;
                    $total += $quantity * $unitCost;
                }
            }

            if ($validRows === 0) {
                $validator->errors()->add('items', 'At least one Purchase Request item is required before submission.');
            }

            if ($total <= 0) {
                $validator->errors()->add('items', 'Total estimated cost must be greater than zero before submission.');
            }
        });

        return $validator->validate();
    }

    private function prFormAction(Request $request): string
    {
        $action = (string) ($request->input('form_action') ?: $request->input('save_action') ?: 'save_draft');

        return match ($action) {
            'submit', 'submit_for_pr_number' => 'submit_for_pr_number',
            'draft', 'save_draft' => 'save_draft',
            'save_draft_continue_upload' => 'save_draft_continue_upload',
            default => 'save_draft',
        };
    }

    private function filledItemRows(array $items): array
    {
        return collect($items)
            ->filter(function ($item) {
                if (! is_array($item)) {
                    return false;
                }

                return $this->normalizeItemText($item['quantity'] ?? '') !== ''
                    || $this->normalizeItemText($item['unit_of_issue'] ?? '') !== ''
                    || $this->normalizeItemText($item['description'] ?? '') !== ''
                    || $this->normalizeItemText($item['stock_no'] ?? '') !== ''
                    || $this->normalizeItemText($item['estimated_unit_cost'] ?? '') !== '';
            })
            ->values()
            ->all();
    }

    private function normalizeItemText(mixed $value): string
    {
        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', (string) ($value ?? '')));
    }

    private function money(mixed $value): float
    {
        return round(max((float) preg_replace('/[^0-9.\-]/', '', (string) ($value ?? 0)), 0), 2);
    }

    private function defaultPrSignatories(?ProcurementDocument $document = null, ?User $user = null, ?Office $office = null): array
    {
        $defaults = $this->canSelectRequestingOffice($user)
            ? self::BACSEC002_PR_SIGNATORIES
            : self::DEFAULT_PR_SIGNATORIES;
        $officeName = $office?->name
            ?: $user?->assignedOffice?->name
            ?: $document?->submittingOffice?->name
            ?: $document?->department_name
            ?: $user?->office
            ?: '';
        $requestingName = trim((string) ($user?->name ?: $document?->submittedBy?->name ?: $officeName));

        if (! $this->canSelectRequestingOffice($user)) {
            $defaults['requested']['printed_name'] = $requestingName;
            $defaults['requested']['designation'] = '';
        }

        if ($document?->requested_by_name) {
            $defaults['requested']['printed_name'] = $document->requested_by_name;
        }

        if ($document?->requested_by_designation) {
            $defaults['requested']['designation'] = $document->requested_by_designation;
        }

        if ($document?->approved_by_name) {
            $defaults['approved']['printed_name'] = $document->approved_by_name;
        }

        if ($document?->approved_by_designation) {
            $defaults['approved']['designation'] = $document->approved_by_designation;
        }

        if ($document?->approved_at) {
            $defaults['approved']['date'] = $document->approved_at->format('m/d/Y');
        }

        if ($document?->pr_date) {
            $defaults['requested']['date'] = $document->pr_date->format('m/d/Y');
        }

        return array_replace_recursive($defaults, is_array($document?->pr_signatories) ? $document->pr_signatories : []);
    }

    private function routingPreviewDocument(?ProcurementDocument $document, User $user, ?Office $office): ProcurementDocument
    {
        $preview = new ProcurementDocument([
            'document_type' => 'PR',
            'fiscal_year' => $document?->fiscal_year ?: now()->year,
            'submitting_office_id' => $office?->id ?: $document?->submitting_office_id ?: $user->office_id,
            'submitted_by_user_id' => $document?->submitted_by_user_id ?: $user->id,
            'prepared_by_user_id' => $document?->prepared_by_user_id ?: $user->id,
            'department_name' => $office?->name ?: $document?->department_name,
            'status' => $document?->status ?: ProcurementDocument::STATUS_PR_DRAFT,
        ]);

        $preview->setRelation('submittingOffice', $office ?: $document?->submittingOffice ?: $user->assignedOffice);
        $preview->setRelation('submittedBy', $document?->submittedBy ?: $user);

        return $preview;
    }

    private function normalizePrSignatories(array $input = [], ?ProcurementDocument $document = null, ?User $user = null, ?Office $office = null): array
    {
        $defaults = $this->defaultPrSignatories($document, $user, $office);
        $normalized = [];

        foreach ($defaults as $key => $default) {
            $normalized[$key] = [];
            $signatoryInput = $input[$key] ?? [];
            $manualDesignationFlagPresent = array_key_exists('designation_manually_selected', $signatoryInput);
            $designationWasManuallySelected = filter_var($signatoryInput['designation_manually_selected'] ?? false, FILTER_VALIDATE_BOOLEAN);

            foreach (['signature', 'printed_name', 'designation', 'date'] as $field) {
                $value = $input[$key][$field] ?? $default[$field] ?? '';
                $normalized[$key][$field] = trim((string) $value);
            }

            if ($manualDesignationFlagPresent) {
                $normalized[$key]['designation'] = $designationWasManuallySelected
                    ? $normalized[$key]['designation']
                    : '';
                $normalized[$key]['designation_manually_selected'] = $designationWasManuallySelected
                    && $normalized[$key]['designation'] !== '';
            }
        }

        return $normalized;
    }

    private function syncItems(ProcurementDocument $document, array $items, ?int $linkedAppItemId = null): void
    {
        $document->purchaseRequestItems()->delete();
        $total = 0;

        foreach ($this->filledItemRows($items) as $index => $item) {
            $description = $this->normalizeItemText($item['description'] ?? '');
            $quantity = max((float) ($item['quantity'] ?? 0), 0);
            $unitCost = max((float) ($item['estimated_unit_cost'] ?? 0), 0);
            $itemTotal = round($quantity * $unitCost, 2);
            $total += $itemTotal;
            $unit = $this->normalizeItemText($item['unit_of_issue'] ?? '');
            $stockNo = $this->normalizeItemText($item['stock_no'] ?? '');
            $rowAppItemId = filled($item['app_item_id'] ?? null)
                ? (int) $item['app_item_id']
                : ($index === 0 ? $linkedAppItemId : null);

            $document->purchaseRequestItems()->create([
                'app_item_id' => $rowAppItemId,
                'item_no' => (string) ($index + 1),
                'quantity' => $quantity,
                'unit' => $unit !== '' ? $unit : null,
                'unit_of_issue' => $unit !== '' ? $unit : null,
                'item_description' => $description,
                'description' => $description,
                'stock_no' => $stockNo !== '' ? $stockNo : null,
                'estimated_unit_cost' => $unitCost,
                'estimated_total_cost' => $itemTotal,
                'estimated_cost' => $itemTotal,
                'remarks' => $item['remarks'] ?? null,
            ]);
        }

        $document->update(['total_amount' => $total]);
    }

    private function approvedAppReferenceItems(?Office $office, ?int $fiscalYear = null, bool $includeAllOffices = false): Collection
    {
        $this->syncAnnualAppReferenceMirrors($fiscalYear);

        return AppItem::query()
            ->with(['appConsolidation', 'office', 'sourcePpmpDocument'])
            ->whereHas('appConsolidation', function (Builder $query) use ($fiscalYear) {
                $query->whereIn('status', $this->eligibleAppReferenceStatuses())
                    ->when($fiscalYear, fn (Builder $builder) => $builder->where('fiscal_year', $fiscalYear));
            })
            ->when(! $includeAllOffices && $office?->id, function (Builder $query) use ($office) {
                $query->where(function (Builder $officeQuery) use ($office) {
                    $officeQuery->whereNull('office_id')
                        ->orWhere('office_id', $office->id);
                });
            })
            ->latest('updated_at')
            ->limit(100)
            ->get();
    }

    private function createSelectedAppReferenceId(Request $request): mixed
    {
        if (filled($request->query('app_item_id'))) {
            return $request->query('app_item_id');
        }

        if (filled($request->old('app_item_id'))) {
            return $request->old('app_item_id');
        }

        return collect($request->old('items', []))
            ->pluck('app_item_id')
            ->first(fn ($appItemId) => filled($appItemId));
    }

    private function shouldStartPrForm(Request $request, ?AppItem $selectedAppItem): bool
    {
        if (! $selectedAppItem) {
            return false;
        }

        return $request->boolean('start_pr')
            || filled($request->old('form_action'))
            || filled($request->old('app_item_id'))
            || collect($request->old('items', []))->isNotEmpty();
    }

    private function selectedApprovedAppItem(mixed $appItemId, ?Office $office): ?AppItem
    {
        if (! filled($appItemId)) {
            return null;
        }

        return AppItem::query()
            ->with(['appConsolidation', 'office'])
            ->whereKey((int) $appItemId)
            ->whereHas('appConsolidation', fn (Builder $query) => $query->whereIn('status', $this->eligibleAppReferenceStatuses()))
            ->when($office?->id, function (Builder $query) use ($office) {
                $query->where(function (Builder $officeQuery) use ($office) {
                    $officeQuery->whereNull('office_id')
                        ->orWhere('office_id', $office->id);
                });
            })
            ->first();
    }

    private function purposeFromAppItem(AppItem $appItem): string
    {
        return $this->normalizeItemText($appItem->general_description)
            ?: 'Purchase Request based on approved APP project';
    }

    private function ppmpSupplyItemsForAppItem(?AppItem $appItem): Collection
    {
        if (! $appItem || ! $appItem->source_ppmp_document_id) {
            return collect();
        }

        $targetTitle = $this->appProjectTitleFromAppItem($appItem);

        $items = PpmpItem::query()
            ->where('procurement_document_id', $appItem->source_ppmp_document_id)
            ->when($appItem->source_ppmp_item_id, fn (Builder $query) => $query->whereKey($appItem->source_ppmp_item_id))
            ->orderBy('row_order')
            ->orderBy('id')
            ->get();

        $filteredItems = $appItem->source_ppmp_item_id
            ? $items
            : $items->filter(fn (PpmpItem $item) => ! $targetTitle || $this->appProjectTitleForPpmpItem($item) === $targetTitle);

        return $filteredItems
            ->flatMap(fn (PpmpItem $item) => $this->ppmpSupplyRowsForPr($item, $appItem))
            ->values();
    }

    private function appProjectTitleFromAppItem(AppItem $appItem): ?string
    {
        $description = strtolower($this->normalizeItemText($appItem->general_description));
        $searchableDescription = str_replace('&', 'and', $description);

        foreach ($this->appProjectTitleGroups() as $projectTitle => $keywords) {
            if (str_contains($searchableDescription, strtolower($projectTitle))) {
                return $projectTitle;
            }

            foreach ($keywords as $keyword) {
                if (str_contains($description, $keyword)) {
                    return $projectTitle;
                }
            }
        }

        return $description !== ''
            ? trim((string) preg_replace('/^Purchase\s+of\s+/i', '', (string) $appItem->general_description))
            : null;
    }

    private function appProjectTitleForPpmpItem(PpmpItem $item): string
    {
        $description = strtolower($this->normalizeItemText(implode(' ', array_filter([
            $item->general_description,
            $item->quantity_size,
            $item->category,
        ]))));

        foreach ($this->appProjectTitleGroups() as $projectTitle => $keywords) {
            if (str_contains($description, strtolower($projectTitle))) {
                return $projectTitle;
            }

            foreach ($keywords as $keyword) {
                if (str_contains($description, $keyword)) {
                    return $projectTitle;
                }
            }
        }

        return 'General Requirements';
    }

    private function appProjectTitleGroups(): array
    {
        return [
            'Information and Communication Technology' => [
                'computer',
                'desktop',
                'flash drive',
                'hard drive',
                'ict',
                'information & communication technology',
                'information and communication technology',
                'keyboard',
                'laptop',
                'monitor',
                'mouse',
                'printer',
                'router',
                'scanner',
                'software',
                'ups',
            ],
            'Janitorial Supplies' => [
                'alcohol',
                'bleach',
                'broom',
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
                'bond paper',
                'correction tape',
                'curtain',
                'envelope',
                'fastener',
                'folder',
                'glue',
                'ink',
                'marker',
                'paper',
                'pen',
                'pencil',
                'record book',
                'staple',
                'stapler',
                'toner',
                'office supplies',
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

    private function ppmpDescriptionForPr(PpmpItem $item): string
    {
        $description = $this->normalizeItemText($item->general_description);
        $quantitySize = $this->normalizeItemText($item->quantity_size);

        if ($description !== '' && $quantitySize !== '' && strcasecmp($description, $quantitySize) !== 0) {
            return "{$description} - {$quantitySize}";
        }

        return $description ?: $quantitySize;
    }

    private function ppmpSupplyRowsForPr(PpmpItem $item, AppItem $appItem): Collection
    {
        $detailsRows = $this->ppmpQuantitySizeRowsForPr($item, $appItem);

        if ($detailsRows->isNotEmpty()) {
            return $detailsRows;
        }

        $quantity = $this->ppmpQuantityForPr($item);
        $unitCost = $this->ppmpUnitCostForPr($item, $quantity);

        return collect([[
            'id' => (string) $item->id,
            'app_item_id' => $appItem->id,
            'stock_no' => $item->item_no ?: ('PPMP-' . $item->id),
            'unit' => $this->ppmpUnitForPr($item),
            'description' => $this->ppmpDescriptionForPr($item),
            'quantity' => $quantity > 0 ? $this->numberForInput($quantity) : '',
            'unit_cost' => $unitCost > 0 ? $this->numberForInput($unitCost) : '',
            'total' => $this->estimatedPpmpBudget($item),
        ]]);
    }

    private function ppmpQuantitySizeRowsForPr(PpmpItem $item, AppItem $appItem): Collection
    {
        $quantitySize = $this->normalizeItemText($item->quantity_size);

        if ($quantitySize === '') {
            return collect();
        }

        $segments = $this->mergePpmpContinuationSegments(collect($this->splitPpmpQuantitySizeSegments($quantitySize))
            ->map(fn (string $segment) => $this->normalizeItemText($segment))
            ->filter()
            ->values());

        if ($segments->isEmpty()) {
            return collect();
        }

        $projectDescription = $this->normalizeItemText($item->general_description);
        $sourceBudget = $this->estimatedPpmpBudget($item);
        $singleSegment = $segments->count() === 1;

        return $segments
            ->map(function (string $segment, int $index) use ($item, $appItem, $projectDescription, $sourceBudget, $singleSegment): array {
                $parsed = $this->parsePpmpQuantitySizeSegment($segment);
                $quantity = $parsed['quantity'] > 0 ? $parsed['quantity'] : ($singleSegment ? $this->ppmpQuantityForPr($item) : 1);
                $description = $parsed['description'] ?: ($projectDescription ?: $segment);
                $unit = $parsed['unit'] ?: ($singleSegment ? $this->ppmpUnitForPr($item) : 'unit');
                $total = $singleSegment ? $sourceBudget : 0;
                $unitCost = $singleSegment && $quantity > 0 ? round($total / $quantity, 2) : 0;

                if ($singleSegment && $projectDescription !== '' && strcasecmp($projectDescription, $description) !== 0) {
                    $description = "{$projectDescription} - {$description}";
                }

                return [
                    'id' => $singleSegment ? (string) $item->id : "{$item->id}:{$index}",
                    'app_item_id' => $appItem->id,
                    'stock_no' => $item->item_no ?: ('PPMP-' . $item->id . ($singleSegment ? '' : '-' . ($index + 1))),
                    'unit' => $unit,
                    'description' => $description,
                    'quantity' => $quantity > 0 ? $this->numberForInput($quantity) : '',
                    'unit_cost' => $unitCost > 0 ? $this->numberForInput($unitCost) : '',
                    'total' => $total,
                ];
            })
            ->filter(fn (array $row) => $this->normalizeItemText($row['description'] ?? '') !== '')
            ->values();
    }

    private function splitPpmpQuantitySizeSegments(string $text): array
    {
        $segments = [];
        $buffer = '';
        $depth = 0;
        $length = strlen($text);

        for ($index = 0; $index < $length; $index++) {
            $character = $text[$index];

            if ($character === '(') {
                $depth++;
            } elseif ($character === ')' && $depth > 0) {
                $depth--;
            }

            if (($character === ',' || $character === ';') && $depth === 0) {
                $segments[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $character;
        }

        $segments[] = $buffer;

        return $segments;
    }

    private function mergePpmpContinuationSegments(Collection $segments): Collection
    {
        $merged = [];

        foreach ($segments as $segment) {
            if ($merged !== [] && $this->isPpmpQuantitySizeContinuation($segment)) {
                $lastIndex = array_key_last($merged);
                $merged[$lastIndex] .= ', ' . $segment;
                continue;
            }

            $merged[] = $segment;
        }

        return collect($merged);
    }

    private function isPpmpQuantitySizeContinuation(string $segment): bool
    {
        $segment = ltrim($segment);

        return str_starts_with($segment, '(')
            || str_starts_with($segment, '[');
    }

    private function parsePpmpQuantitySizeSegment(string $segment): array
    {
        $segment = $this->normalizeItemText($segment);
        $knownUnits = 'bags?|bottles?|boxes?|bundles?|cartons?|gallons?|packs?|pairs?|pcs?|pieces?|reams?|rolls?|sets?|units?';

        if (preg_match('/^\s*(?<quantity>[0-9]+(?:\.[0-9]+)?)\s+(?<unit>' . $knownUnits . ')\s+(?:of\s+)?(?<description>.+)$/i', $segment, $matches)) {
            return [
                'description' => $this->normalizeItemText($matches['description']),
                'quantity' => (float) $matches['quantity'],
                'unit' => $this->normalizePpmpUnit($matches['unit']),
            ];
        }

        if (preg_match('/^\s*(?<quantity>[0-9]+(?:\.[0-9]+)?)\s+(?<unit>' . $knownUnits . ')\.?$/i', $segment, $matches)) {
            return [
                'description' => '',
                'quantity' => (float) $matches['quantity'],
                'unit' => $this->normalizePpmpUnit($matches['unit']),
            ];
        }

        if (preg_match('/^(?<description>.+?)\s*(?:-|\x{2013})\s*(?<quantity>[0-9]+(?:\.[0-9]+)?)(?:\s*(?<unit>[a-z][a-z0-9.\/ ]*))?$/iu', $segment, $matches)) {
            return [
                'description' => $this->normalizeItemText($matches['description']),
                'quantity' => (float) $matches['quantity'],
                'unit' => $this->normalizePpmpUnit($matches['unit'] ?? ''),
            ];
        }

        if (preg_match('/^(?<description>.+?)\s+(?<quantity>[0-9]+(?:\.[0-9]+)?)\s*(?<unit>' . $knownUnits . ')\.?$/i', $segment, $matches)) {
            return [
                'description' => $this->normalizeItemText($matches['description']),
                'quantity' => (float) $matches['quantity'],
                'unit' => $this->normalizePpmpUnit($matches['unit']),
            ];
        }

        return [
            'description' => $segment,
            'quantity' => 1,
            'unit' => 'unit',
        ];
    }

    private function normalizePpmpUnit(string $unit): string
    {
        $unit = rtrim($this->normalizeItemText($unit), '.');

        if ($unit === '') {
            return 'unit';
        }

        if (preg_match('/^per\s+(.+)$/i', $unit, $matches)) {
            return $this->normalizeItemText($matches[1]);
        }

        return $unit;
    }

    private function ppmpQuantityForPr(PpmpItem $item): float
    {
        $quantity = (float) ($item->quantity ?? 0);

        if ($quantity > 0) {
            return $quantity;
        }

        if (preg_match('/^\s*([0-9]+(?:\.[0-9]+)?)/', (string) $item->quantity_size, $matches)) {
            return (float) $matches[1];
        }

        return 1;
    }

    private function ppmpUnitForPr(PpmpItem $item): string
    {
        $unit = $this->normalizeItemText($item->unit);

        if ($unit !== '') {
            return $unit;
        }

        $quantitySize = $this->normalizeItemText($item->quantity_size);
        $unitFromSize = trim((string) preg_replace('/^\s*[0-9]+(?:\.[0-9]+)?\s*/', '', $quantitySize));

        return $unitFromSize !== '' ? $unitFromSize : 'unit';
    }

    private function ppmpUnitCostForPr(PpmpItem $item, float $quantity): float
    {
        $unitCost = $this->money($item->estimated_unit_cost);

        if ($unitCost > 0) {
            return $unitCost;
        }

        $total = $this->estimatedPpmpBudget($item);

        return $quantity > 0 ? round($total / $quantity, 2) : $total;
    }

    private function estimatedPpmpBudget(PpmpItem $item): float
    {
        return $this->money($item->estimated_total_cost)
            ?: ($this->money($item->quantity) * $this->money($item->estimated_unit_cost));
    }

    private function numberForInput(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function submittedAppReferenceId(array $validated): mixed
    {
        if (filled($validated['app_item_id'] ?? null)) {
            return $validated['app_item_id'];
        }

        return collect($validated['items'] ?? [])
            ->pluck('app_item_id')
            ->first(fn ($appItemId) => filled($appItemId));
    }

    private function documentHasApprovedAppReference(ProcurementDocument $document): bool
    {
        $appItemId = $document->app_item_id
            ?: $document->purchaseRequestItems()->whereNotNull('app_item_id')->value('app_item_id');

        if (! filled($appItemId)) {
            return false;
        }

        return AppItem::query()
            ->whereKey($appItemId)
            ->when($document->app_consolidation_id, fn (Builder $query) => $query->where('app_consolidation_id', $document->app_consolidation_id))
            ->whereHas('appConsolidation', fn (Builder $query) => $query->whereIn('status', $this->eligibleAppReferenceStatuses()))
            ->exists();
    }

    private function eligibleAppReferenceStatuses(): array
    {
        return [
            AppConsolidation::STATUS_CONSOLIDATED,
            AppConsolidation::STATUS_SUBMITTED_FOR_APPROVAL,
            AppConsolidation::STATUS_APPROVED,
        ];
    }

    private function syncAnnualAppReferenceMirrors(?int $fiscalYear = null): void
    {
        AnnualProcurementPlan::query()
            ->with(['items.sourcePpmpDocument'])
            ->whereNotNull('app_number')
            ->when($fiscalYear, fn (Builder $query) => $query->where('fiscal_year', $fiscalYear))
            ->whereIn('status', [
                AnnualProcurementPlan::STATUS_CONSOLIDATED,
                AnnualProcurementPlan::STATUS_SUBMITTED,
                AnnualProcurementPlan::STATUS_APPROVED,
                AnnualProcurementPlan::STATUS_RETURNED,
                AnnualProcurementPlan::STATUS_CANCELLED,
            ])
            ->get()
            ->each(function (AnnualProcurementPlan $annualApp): void {
                $mirror = AppConsolidation::updateOrCreate(
                    ['app_number' => $annualApp->app_number],
                    [
                        'fiscal_year' => $annualApp->fiscal_year ?: now()->year,
                        'title' => $annualApp->title ?: 'Annual Procurement Plan',
                        'description' => $annualApp->remarks,
                        'prepared_by_user_id' => $annualApp->prepared_by_user_id,
                        'status' => $this->mirrorAnnualAppStatus($annualApp->status),
                        'total_amount' => $annualApp->total_estimated_budget ?? 0,
                        'consolidated_at' => $annualApp->status === AnnualProcurementPlan::STATUS_CONSOLIDATED ? $annualApp->updated_at : null,
                        'submitted_for_approval_at' => $annualApp->submitted_at,
                        'approved_at' => $annualApp->approved_at,
                        'cancelled_at' => $annualApp->status === AnnualProcurementPlan::STATUS_CANCELLED ? $annualApp->updated_at : null,
                    ],
                );

                $annualApp->items->each(fn (AnnualProcurementPlanItem $item) => $this->syncAnnualAppItemMirror($mirror, $item));
            });
    }

    private function syncAnnualAppItemMirror(AppConsolidation $mirror, AnnualProcurementPlanItem $item): void
    {
        $appItemQuery = AppItem::query()->where('app_consolidation_id', $mirror->id);

        if (filled($item->source_ppmp_item_id)) {
            $appItemQuery
                ->where('source_ppmp_document_id', $item->source_ppmp_document_id)
                ->where('source_ppmp_item_id', $item->source_ppmp_item_id);
        } else {
            $appItemQuery->where('item_no', 'APPITEM-' . $item->id);
        }

        $description = $item->general_description
            ?: $item->project_title
            ?: $item->procurement_program_project
            ?: 'APP Item #' . $item->id;
        $total = (float) ($item->estimated_budget ?? $item->estimated_total ?? 0);

        $appItem = $appItemQuery->first() ?: new AppItem([
            'app_consolidation_id' => $mirror->id,
            'source_ppmp_document_id' => $item->source_ppmp_document_id,
            'source_ppmp_item_id' => $item->source_ppmp_item_id,
            'item_no' => filled($item->source_ppmp_item_id) ? $item->pap_code : 'APPITEM-' . $item->id,
        ]);

        $appItem->fill([
            'source_ppmp_document_id' => $item->source_ppmp_document_id,
            'source_ppmp_item_id' => $item->source_ppmp_item_id,
            'office_id' => $this->officeIdForAnnualAppItem($item),
            'item_no' => filled($item->source_ppmp_item_id) ? $item->pap_code : 'APPITEM-' . $item->id,
            'general_description' => $description,
            'quantity' => 1,
            'unit' => 'lot',
            'estimated_unit_cost' => $total,
            'estimated_total_cost' => $total,
            'procurement_mode' => $item->mode_of_procurement,
            'schedule_quarter' => trim(implode(' - ', array_filter([
                $item->start_procurement_activity,
                $item->end_procurement_activity,
            ]))) ?: null,
            'category' => $item->category,
            'remarks' => $item->remarks,
        ])->save();
    }

    private function mirrorAnnualAppStatus(string $status): string
    {
        return match ($status) {
            AnnualProcurementPlan::STATUS_CONSOLIDATED => AppConsolidation::STATUS_CONSOLIDATED,
            AnnualProcurementPlan::STATUS_SUBMITTED => AppConsolidation::STATUS_SUBMITTED_FOR_APPROVAL,
            AnnualProcurementPlan::STATUS_APPROVED => AppConsolidation::STATUS_APPROVED,
            AnnualProcurementPlan::STATUS_RETURNED => AppConsolidation::STATUS_RETURNED,
            AnnualProcurementPlan::STATUS_CANCELLED => AppConsolidation::STATUS_CANCELLED,
            default => AppConsolidation::STATUS_DRAFT,
        };
    }

    private function officeIdForAnnualAppItem(AnnualProcurementPlanItem $item): ?int
    {
        if ($item->sourcePpmpDocument?->submitting_office_id) {
            return $item->sourcePpmpDocument->submitting_office_id;
        }

        $officeReference = trim((string) ($item->end_user_unit ?: $item->pmo_end_user));

        if ($officeReference === '') {
            return null;
        }

        return Office::query()
            ->where('code', $officeReference)
            ->orWhere('name', $officeReference)
            ->value('id');
    }

    private function existingSubmissionError(ProcurementDocument $document): ?string
    {
        $document->loadMissing('purchaseRequestItems');

        if (trim((string) $document->section) === '') {
            return 'Section is required before submission.';
        }

        if (trim((string) $document->purpose) === '') {
            return 'Purpose is required before submission.';
        }

        if ($document->purchaseRequestItems->isEmpty()) {
            return 'At least one Purchase Request item is required before submission.';
        }

        foreach ($document->purchaseRequestItems as $item) {
            if (trim((string) ($item->description ?? $item->item_description)) === '' || trim((string) ($item->unit_of_issue ?? $item->unit)) === '' || (float) $item->quantity <= 0) {
                return 'Complete all item descriptions, quantities, and units before submission.';
            }
        }

        if ((float) $document->total_amount <= 0) {
            return 'Total estimated cost must be greater than zero before submission.';
        }

        if ($error = app(PurchaseRequestBudgetService::class)->submissionErrorForDocument($document)) {
            return $error;
        }

        return null;
    }

    private function submitDocument(ProcurementDocument $document, User $user, Office $numberingOffice, ?User $numberingUser): void
    {
        $oldStatus = $document->status;
        $fromOffice = $document->current_office_id;

        if (! $document->tracking_number) {
            $document->tracking_number = $this->generateTrackingNumber((int) $document->fiscal_year, $document->submittingOffice ?: $this->assignedOffice($user));
        }

        $document->fill([
            'status' => ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT,
            'pr_status' => null,
            'pr_number_status' => ProcurementDocument::PR_NUMBER_STATUS_PENDING_ASSIGNMENT,
            'pr_no_requested_at' => now(),
            'pr_number_remarks' => null,
            'stage' => ProcurementDocument::STAGE_PR_NUMBER_ASSIGNMENT,
            'submitted_at' => now(),
            'returned_at' => null,
            'current_office_id' => $numberingOffice->id,
            'assigned_to_user_id' => $numberingUser?->id,
            'bac_secretariat_status' => null,
        ])->save();

        $this->recordRouting(
            $document,
            $user,
            'PR Submitted for PR Number Assignment',
            $oldStatus,
            ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT,
            'Purchase Request routed to PR Numbering Staff for official PR number assignment.',
            $fromOffice,
            $numberingOffice->id,
        );

        SystemNotificationService::notify(
            $numberingUser,
            'New Purchase Request for PR Number Assignment',
            'A Purchase Request has been submitted and is waiting for official PR number assignment.',
            SystemNotification::TYPE_INFO,
            'PR Number Assignment',
            $document,
            route('pr-numbering.pending.show', $document),
        );

        AuditLogger::log('Purchase Request Submission', 'pr_submitted_to_pr_numbering', 'Head of Office submitted a Purchase Request to PR Numbering Staff.', $document, ['status' => $oldStatus], [
            'status' => ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT,
            'tracking_number' => $document->tracking_number,
        ]);

        app(SvpChainService::class)->findOrCreateFromPr($document->refresh(), $user, 'PR submitted to PR Numbering Staff');
    }

    private function forwardPrReferenceToBacsec002(ProcurementDocument $document, User $user, User $bacsec002): void
    {
        $oldStatus = $document->status;
        $oldValues = $document->only(['status', 'pr_status', 'stage', 'current_office_id', 'assigned_to_user_id']);
        $fromOffice = $document->current_office_id;
        $bacsecOfficeId = $bacsec002->office_id;

        $document->fill([
            'status' => ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION,
            'stage' => ProcurementDocument::STAGE_BAC_RESOLUTION_PREPARATION,
            'returned_at' => null,
            'current_office_id' => $bacsecOfficeId ?: $document->current_office_id,
            'assigned_to_user_id' => $bacsec002->id,
            'bac_secretariat_status' => 'pending_action',
            'route_destination_role' => User::ROLE_BAC_SECRETARIAT,
            'route_destination_office_id' => $bacsecOfficeId,
            'route_remarks' => 'Pending Action: PR copy sent to BACSEC-002 for BAC Resolution preparation.',
            'routed_by_user_id' => $user->id,
            'routed_at' => now(),
        ])->save();

        $this->recordRouting(
            $document,
            $user,
            'PR Copy Sent to BACSEC-002',
            $oldStatus,
            ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION,
            'A completed Purchase Request copy was sent to BACSEC-002 for BAC Resolution preparation.',
            $fromOffice,
            $bacsecOfficeId,
        );

        SystemNotificationService::notify(
            $bacsec002,
            'PR Copy Received for BAC Resolution',
            "Purchase Request {$document->pr_no} is pending BAC Resolution preparation.",
            SystemNotification::TYPE_INFO,
            'Purchase Requests',
            $document,
            route('bac-secretariat.pr.show', $document),
        );

        AuditLogger::log('Purchase Request Submission', 'pr_copy_sent_to_bacsec002', 'Requesting Office sent a finalized Purchase Request copy to BACSEC-002.', $document, $oldValues, [
            'status' => ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION,
            'pr_no' => $document->pr_no,
            'current_office_id' => $document->current_office_id,
            'assigned_to_user_id' => $document->assigned_to_user_id,
        ]);

        $chain = app(SvpChainService::class)->findOrCreateFromPr($document->refresh(), $user);
        $chain->update([
            'current_stage' => SvpProcurementChain::STAGE_BAC_RESOLUTION,
            'current_status' => ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION,
            'total_amount' => $document->total_amount,
            'updated_by_user_id' => $user->id,
        ]);

        app(SvpChainService::class)->addEvent($chain->refresh(), [
            'document_type' => 'Purchase Request',
            'document_id' => $document->id,
            'action' => 'PR Copy Sent to BACSEC-002',
            'stage' => SvpProcurementChain::STAGE_BAC_RESOLUTION,
            'status' => ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION,
            'from_office_id' => $fromOffice,
            'to_office_id' => $bacsecOfficeId,
            'performed_by_user_id' => $user->id,
            'remarks' => 'Completed PR copy sent to BACSEC-002. RFQ/posting readiness may proceed while BAC Resolution remains tracked.',
        ]);

        app(SvpChainService::class)->routePrForRfqPreparation(
            $document->refresh(),
            $user,
            'Completed PR copy sent to BACSEC-002. BAC Resolution remains part of the workflow while RFQ/posting readiness proceeds.',
        );
    }

    private function generateTrackingNumber(int $year, ?Office $office): string
    {
        $officeCode = $this->trackingOfficeCode($office);
        $prefix = "PR-{$year}-{$officeCode}-";

        $lastTrackingNumber = ProcurementDocument::query()
            ->whereIn('document_type', ['PR', 'Purchase Request'])
            ->where('tracking_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('tracking_number')
            ->value('tracking_number');

        $sequence = $lastTrackingNumber ? ((int) substr($lastTrackingNumber, -4)) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function trackingOfficeCode(?Office $office): string
    {
        $source = $office?->code ?: $office?->name ?: 'OFFICE';
        $code = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', $source));

        return $code !== '' ? $code : 'OFFICE';
    }

    private function prNumberingTarget(): array
    {
        $user = User::where('status', User::STATUS_ACTIVE)
            ->where(function (Builder $query) {
                $query->whereHas('assignedRole', function (Builder $roleQuery) {
                    $roleQuery->where('code', 'pr_numbering_staff')
                        ->orWhere('name', User::ROLE_PR_NUMBERING);
                })
                    ->orWhere('role', User::ROLE_PR_NUMBERING);
            })
            ->where(function (Builder $query) {
                $query->whereHas('assignedOffice', function (Builder $officeQuery) {
                    $officeQuery->where('code', 'MEO')
                        ->orWhere('name', 'Municipal Engineering Office');
                })
                    ->orWhere('office', 'Municipal Engineering Office');
            })
            ->first()
            ?: User::where('status', User::STATUS_ACTIVE)
                ->where('user_id', 'PRNO-001')
                ->first();

        $office = $user?->assignedOffice ?: Office::where('code', 'MEO')
            ->orWhere('name', 'Municipal Engineering Office')
            ->first();

        return ['office' => $office, 'user' => $user];
    }

    private function bacsec002ReferenceTarget(): array
    {
        $user = User::where('status', User::STATUS_ACTIVE)
            ->where('user_id', 'BACSEC-002')
            ->first();

        return ['office' => $user?->assignedOffice, 'user' => $user];
    }

    private function recordRouting(ProcurementDocument $document, User $user, string $action, ?string $fromStatus, string $toStatus, ?string $comments, ?int $fromOfficeId, ?int $toOfficeId): void
    {
        DocumentRoutingHistory::create([
            'procurement_document_id' => $document->id,
            'action_by_user_id' => $user->id,
            'from_office_id' => $fromOfficeId,
            'to_office_id' => $toOfficeId,
            'action' => $action,
            'status_from' => $fromStatus,
            'status_to' => $toStatus,
            'comments' => $comments,
            'action_at' => now(),
        ]);
    }

    private function summary(User $user): array
    {
        $base = $this->officePrQuery($user);

        return [
            'total' => (clone $base)->count(),
            'drafts' => (clone $base)->where('status', ProcurementDocument::STATUS_PR_DRAFT)->count(),
            'submitted' => (clone $base)->whereIn('status', [
                ProcurementDocument::STATUS_PR_PENDING_SIGNATORIES,
                ProcurementDocument::STATUS_PR_SIGNATORIES_COMPLETED,
                ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT,
                ProcurementDocument::STATUS_PR_SUBMITTED,
                ProcurementDocument::STATUS_SUBMITTED_TO_BAC_SECRETARIAT,
            ])->count(),
            'reviewed' => (clone $base)->whereIn('status', [
                ProcurementDocument::STATUS_PR_RECEIVED_BY_BAC_SECRETARIAT,
                ProcurementDocument::STATUS_UNDER_PR_VALIDATION,
                ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW,
                ProcurementDocument::STATUS_BUDGET_REVIEWED,
                ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW,
                ProcurementDocument::STATUS_ACCOUNTING_REVIEWED,
            ])->count(),
            'approved' => (clone $base)->whereIn('status', [
                ProcurementDocument::STATUS_APPROVED,
                ProcurementDocument::STATUS_READY_FOR_PO,
                ProcurementDocument::STATUS_PO_APPROVED,
            ])->count(),
            'returned' => (clone $base)->whereIn('status', [
                ProcurementDocument::STATUS_RETURNED_BY_PR_NUMBERING_STAFF,
                ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
                ProcurementDocument::STATUS_RETURNED_BY_BUDGET,
                ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING,
                ProcurementDocument::STATUS_RETURNED_BY_BAC_MEMBER,
                ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR,
                ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY,
            ])->count(),
            'routed' => (clone $base)->whereIn('status', [
                ProcurementDocument::STATUS_PR_NUMBER_ASSIGNED,
                ProcurementDocument::STATUS_PR_NUMBER_ASSIGNED_RETURNED_TO_END_USER,
                ProcurementDocument::STATUS_PR_RECEIVED_BY_BAC_SECRETARIAT,
                ProcurementDocument::STATUS_UNDER_PR_VALIDATION,
                ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION,
                ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW,
                ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW,
                ProcurementDocument::STATUS_PENDING_APPROVAL,
                ProcurementDocument::STATUS_APPROVED,
                ProcurementDocument::STATUS_READY_FOR_PO,
            ])->count(),
            'total_amount' => (clone $base)->sum('total_amount'),
        ];
    }

    private function statuses(): array
    {
        return [
            ProcurementDocument::STATUS_PR_DRAFT,
            ProcurementDocument::STATUS_PR_PENDING_SIGNATORIES,
            ProcurementDocument::STATUS_PR_SIGNATORIES_COMPLETED,
            ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT,
            ProcurementDocument::STATUS_PR_NUMBER_ASSIGNED,
            ProcurementDocument::STATUS_PR_NUMBER_ASSIGNED_RETURNED_TO_END_USER,
            ProcurementDocument::STATUS_RETURNED_BY_PR_NUMBERING_STAFF,
            ProcurementDocument::STATUS_PR_SUBMITTED,
            ProcurementDocument::STATUS_PR_RECEIVED_BY_BAC_SECRETARIAT,
            ProcurementDocument::STATUS_UNDER_PR_VALIDATION,
            ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION,
            ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
            ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW,
            ProcurementDocument::STATUS_RETURNED_BY_BUDGET,
            ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW,
            ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING,
            ProcurementDocument::STATUS_PENDING_APPROVAL,
            ProcurementDocument::STATUS_APPROVED,
            ProcurementDocument::STATUS_READY_FOR_PO,
        ];
    }

    private function titleFromPurpose(string $purpose): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $purpose));

        return 'Purchase Request - ' . substr($clean, 0, 80);
    }
}
