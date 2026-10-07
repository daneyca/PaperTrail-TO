<?php

namespace App\Http\Controllers\BacSecretariat;

use App\Http\Controllers\Controller;
use App\Models\BacResolution;
use App\Models\DocumentRoutingHistory;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\SignatureRequest;
use App\Models\SupplementalApp;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentDraftService;
use App\Services\ElectronicSignatureService;
use App\Services\SignatureRequestService;
use App\Services\SvpChainService;
use App\Services\SystemNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BacResolutionController extends Controller
{
    private const ELIGIBLE_PR_STATUSES = [
        'pr_validated',
        'bac_secretariat_validated',
        'approved_for_svp_resolution',
        'pending_bac_resolution',
        ProcurementDocument::STATUS_SUBMITTED_TO_BAC_SECRETARIAT,
        ProcurementDocument::STATUS_PR_SUBMITTED,
        ProcurementDocument::STATUS_PR_RECEIVED_BY_BAC_SECRETARIAT,
        ProcurementDocument::STATUS_UNDER_PR_VALIDATION,
        ProcurementDocument::STATUS_SUPPLEMENTAL_APP_CREATED,
        ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION,
        ProcurementDocument::STATUS_BUDGET_REVIEWED,
        ProcurementDocument::STATUS_ACCOUNTING_REVIEWED,
        ProcurementDocument::STATUS_READY_FOR_DOCUMENT_ROUTING,
        ProcurementDocument::STATUS_APPROVED,
        ProcurementDocument::STATUS_READY_FOR_PO,
        ProcurementDocument::STATUS_READY_FOR_RFQ,
    ];

    public function index(Request $request): View
    {
        AuditLogger::log('BAC Resolutions', 'BAC Resolutions Page Viewed', 'BAC Secretariat viewed BAC Resolutions.');

        $query = BacResolution::query()->with(['sourcePrDocument.submittingOffice', 'preparedBy']);
        $this->applyFilters($query, $request);

        return view('bac-secretariat.resolutions.index', [
            'resolutions' => $query->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary(),
            'recentDrafts' => app(DocumentDraftService::class)->getBacResolutionDraftsForBacSecretariat($request->user(), 5),
            'filters' => $request->only(['search', 'fiscal_year', 'status', 'date_from', 'date_to']),
            'fiscalYears' => BacResolution::query()->select('fiscal_year')->whereNotNull('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        AuditLogger::log('BAC Resolutions', 'BAC Resolution Create Page Viewed', 'BAC Secretariat opened the BAC Resolution drafting page.');

        $requestedSourceId = $request->input('source_pr_document_id');
        $sourceDocument = $this->selectedEligiblePr($requestedSourceId);

        if ($requestedSourceId && ! $sourceDocument && $existingResolution = $this->existingResolutionForSourceId($requestedSourceId)) {
            return redirect()
                ->route('bac-secretariat.resolutions.show', $existingResolution)
                ->with('status', 'This PR already has a BAC Resolution.');
        }

        if ($sourceDocument && $existingResolution = $this->existingResolutionForSource($sourceDocument)) {
            return redirect()
                ->route('bac-secretariat.resolutions.show', $existingResolution)
                ->with('status', 'This PR already has a BAC Resolution.');
        }

        $resolution = new BacResolution($this->defaultResolutionData($sourceDocument, $request->user()));

        return view('bac-secretariat.resolutions.create', [
            'resolution' => $resolution,
            'sourceDocument' => $sourceDocument,
            'eligiblePrs' => $this->eligiblePrQuery()->with(['submittingOffice'])->latest('updated_at')->get(),
            'recentDrafts' => app(DocumentDraftService::class)->getBacResolutionDraftsForBacSecretariat($request->user(), 5),
            'signatoryUsers' => $this->activeSignatoryUsers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedResolution($request);
        $sourceDocument = $this->selectedEligiblePr($validated['source_pr_document_id'] ?? null);

        if (($validated['source_pr_document_id'] ?? null) && ! $sourceDocument) {
            if ($existingResolution = $this->existingResolutionForSourceId($validated['source_pr_document_id'])) {
                return redirect()
                    ->route('bac-secretariat.resolutions.show', $existingResolution)
                    ->with('status', 'This PR already has a BAC Resolution.');
            }

            return back()->with('error', 'The selected Purchase Request is not eligible for BAC Resolution preparation.')->withInput();
        }

        if ($sourceDocument && $existingResolution = $this->existingResolutionForSource($sourceDocument)) {
            return redirect()
                ->route('bac-secretariat.resolutions.show', $existingResolution)
                ->with('status', 'This PR already has a BAC Resolution.');
        }

        $resolution = null;

        DB::transaction(function () use ($request, $validated, $sourceDocument, &$resolution) {
            $resolution = BacResolution::create([
                ...$this->resolutionPayload($validated),
                'procurement_document_id' => null,
                'prepared_by_user_id' => $request->user()->id,
                'status' => BacResolution::STATUS_DRAFT,
                'document_version' => 1,
            ]);

            if ($sourceDocument) {
                $this->copySourceItems($resolution, $sourceDocument);
                $this->markSourceResolutionCreated($sourceDocument, $request->user(), $resolution);
            }

            AuditLogger::log('BAC Resolutions', 'BAC Resolution Draft Created', 'BAC Secretariat created a BAC Resolution draft.', $resolution, null, [
                'source_pr' => $sourceDocument?->tracking_number,
                'status' => BacResolution::STATUS_DRAFT,
            ]);

            app(SvpChainService::class)->linkBacResolution($resolution->refresh(), $request->user(), 'BAC Resolution draft created');
        });

        $message = $request->input('save_action') === 'submit'
            ? $this->submitResolutionToChair($request, $resolution)
            : 'BAC Resolution draft created.';

        return redirect()
            ->route('bac-secretariat.resolutions.show', $resolution)
            ->with($this->submissionFlashKey($message), $message);
    }

    public function show(BacResolution $resolution): View
    {
        AuditLogger::log('BAC Resolutions', 'BAC Resolution Detail Viewed', 'BAC Secretariat viewed a BAC Resolution.', $resolution);

        $resolution->load([
            'sourcePrDocument.submittingOffice',
            'sourcePrDocument.submittedBy',
            'sourcePrDocument.routingHistories.actionBy',
            'sourcePrDocument.routingHistories.fromOffice',
            'sourcePrDocument.routingHistories.toOffice',
            'preparedBy',
            'submittedBy',
            'approvedBy',
            'items',
        ]);

        return view('bac-secretariat.resolutions.show', [
            'resolution' => $resolution,
            'signedSignature' => app(ElectronicSignatureService::class)->signedSignatureFor($resolution, 'bac-resolution', 'confirmed'),
            'signatureSlots' => app(SignatureRequestService::class)->signedSignaturesForDocument($resolution, 'bac-resolution'),
            'signatureRequests' => $this->signatureRequestsFor($resolution),
        ]);
    }

    public function edit(Request $request, BacResolution $resolution): View|RedirectResponse
    {
        if (! $resolution->isEditable()) {
            return redirect()
                ->route('bac-secretariat.resolutions.show', $resolution)
                ->with('error', 'Only draft or returned BAC Resolutions can be edited.');
        }

        $resolution->load(['sourcePrDocument.submittingOffice', 'items']);

        return view('bac-secretariat.resolutions.edit', [
            'resolution' => $resolution,
            'sourceDocument' => $resolution->sourcePrDocument,
            'eligiblePrs' => $this->eligiblePrQuery()->with(['submittingOffice'])->latest('updated_at')->get(),
            'signatoryUsers' => $this->activeSignatoryUsers(),
        ]);
    }

    public function update(Request $request, BacResolution $resolution): RedirectResponse
    {
        if (! $resolution->isEditable()) {
            return back()->with('error', 'Only draft or returned BAC Resolutions can be updated.');
        }

        $validated = $this->validatedResolution($request);
        $sourceDocument = $this->selectedEligiblePr($validated['source_pr_document_id'] ?? null);

        if (($validated['source_pr_document_id'] ?? null) && ! $sourceDocument && (int) $validated['source_pr_document_id'] !== (int) $resolution->source_pr_document_id) {
            return back()->with('error', 'The selected Purchase Request is not eligible for BAC Resolution preparation.')->withInput();
        }

        $oldSourceId = $resolution->source_pr_document_id;

        DB::transaction(function () use ($request, $validated, $resolution, $sourceDocument, $oldSourceId) {
            $oldValues = $resolution->only(['resolution_number', 'title', 'status', 'source_pr_document_id']);
            $payload = $this->resolutionPayload($validated);

            if (array_key_exists('document_html', $payload)) {
                $payload['document_version'] = max(1, (int) $resolution->document_version + 1);
            }

            $resolution->update($payload);

            if ($sourceDocument) {
                if ((int) $sourceDocument->id !== (int) $oldSourceId) {
                    $this->copySourceItems($resolution, $sourceDocument);
                }

                $this->markSourceResolutionCreated($sourceDocument, $request->user(), $resolution);
            }

            AuditLogger::log('BAC Resolutions', 'BAC Resolution Updated', 'BAC Secretariat updated a BAC Resolution draft.', $resolution, $oldValues, $resolution->only(['resolution_number', 'title', 'status', 'source_pr_document_id']));

            app(SvpChainService::class)->linkBacResolution($resolution->refresh(), $request->user(), 'BAC Resolution updated');
        });

        if ($request->input('save_action') === 'submit') {
            $message = $this->submitResolutionToChair($request, $resolution->refresh());

            return redirect()
                ->route('bac-secretariat.resolutions.show', $resolution)
                ->with($this->submissionFlashKey($message), $message);
        }

        return redirect()
            ->route('bac-secretariat.resolutions.show', $resolution)
            ->with('status', 'BAC Resolution updated.');
    }

    public function submit(Request $request, BacResolution $resolution): RedirectResponse
    {
        if (! $resolution->canSubmit()) {
            return back()->with('error', 'Only draft or returned BAC Resolutions can be submitted for signatures.');
        }

        $message = $this->submitResolutionToChair($request, $resolution);

        return redirect()
            ->route('bac-secretariat.resolutions.show', $resolution)
            ->with($this->submissionFlashKey($message), $message);
    }

    public function print(BacResolution $resolution): View
    {
        AuditLogger::log('BAC Resolutions', 'BAC Resolution Print Viewed', 'BAC Secretariat opened the BAC Resolution print view.', $resolution);

        $resolution->load(['sourcePrDocument.submittingOffice', 'preparedBy', 'submittedBy', 'approvedBy', 'items']);

        return view('bac-secretariat.resolutions.print', [
            'resolution' => $resolution,
            'signedSignature' => app(ElectronicSignatureService::class)->signedSignatureFor($resolution, 'bac-resolution', 'confirmed'),
            'signatureSlots' => app(SignatureRequestService::class)->signedSignaturesForDocument($resolution, 'bac-resolution'),
        ]);
    }

    public function returnToOffice(Request $request, BacResolution $resolution): RedirectResponse
    {
        if (app(SignatureRequestService::class)->signedSignaturesForDocument($resolution, 'bac-resolution')->isNotEmpty()) {
            return back()->with('error', 'This BAC Resolution has already been electronically signed and returned through the signing workflow.');
        }

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $resolution->load(['sourcePrDocument.submittingOffice', 'sourcePrDocument.submittedBy']);
        $sourceDocument = $resolution->sourcePrDocument;

        if (! $sourceDocument) {
            return back()->with('error', 'This BAC Resolution is not linked to a Purchase Request.');
        }

        if ($resolution->status === BacResolution::STATUS_RETURNED_TO_END_USER) {
            return back()->with('error', 'This BAC Resolution has already been returned to the requesting office.');
        }

        DB::transaction(function () use ($request, $resolution, $sourceDocument, $validated) {
            $oldResolutionStatus = $resolution->status;
            $oldDocumentStatus = $sourceDocument->status;
            $fromOfficeId = $sourceDocument->current_office_id;
            $toOfficeId = $sourceDocument->submitting_office_id;
            $remarks = $validated['remarks'] ?? 'BAC Resolution returned to the requesting office for SVP processing.';

            $resolution->update([
                'status' => BacResolution::STATUS_RETURNED_TO_END_USER,
                'returned_at' => now(),
                'remarks' => $remarks,
            ]);

            $sourceDocument->update([
                'status' => ProcurementDocument::STATUS_BAC_RESOLUTION_RETURNED_TO_END_USER,
                'stage' => ProcurementDocument::STAGE_BAC_RESOLUTION_RETURNED_TO_END_USER,
                'current_office_id' => $toOfficeId,
                'assigned_to_user_id' => $sourceDocument->submitted_by_user_id,
                'route_destination_role' => User::ROLE_HEAD_OFFICE,
                'route_remarks' => $remarks,
                'routed_by_user_id' => $request->user()->id,
                'routed_at' => now(),
            ]);

            DocumentRoutingHistory::create([
                'procurement_document_id' => $sourceDocument->id,
                'action_by_user_id' => $request->user()->id,
                'from_office_id' => $fromOfficeId,
                'to_office_id' => $toOfficeId,
                'action' => 'BAC Resolution Returned to Requesting Office',
                'status_from' => $oldDocumentStatus,
                'status_to' => ProcurementDocument::STATUS_BAC_RESOLUTION_RETURNED_TO_END_USER,
                'comments' => $remarks,
                'action_at' => now(),
            ]);

            SystemNotificationService::notify(
                $sourceDocument->submittedBy,
                'BAC Resolution Returned',
                "BAC Resolution {$resolution->displayNumber()} for {$sourceDocument->tracking_number} has been returned to your office.",
                SystemNotification::TYPE_INFO,
                'BAC Resolution',
                $resolution,
                route('head-office.resolutions.show', $resolution),
            );

            AuditLogger::log('BAC Resolutions', 'BAC Resolution Returned to Requesting Office', 'BAC Secretariat returned a BAC Resolution to the requesting office.', $resolution, ['status' => $oldResolutionStatus], ['status' => BacResolution::STATUS_RETURNED_TO_END_USER]);

            app(SvpChainService::class)->linkBacResolution($resolution->refresh(), $request->user(), 'BAC Resolution returned to requesting office', $remarks);
        });

        return back()->with('status', 'BAC Resolution returned to the requesting office.');
    }

    private function submitResolutionToChair(Request $request, BacResolution $resolution): string
    {
        if (! $resolution->canSubmit()) {
            return 'This BAC Resolution is no longer eligible for submission.';
        }

        if ($this->isResolutionDocumentEmpty($resolution)) {
            return 'BAC Resolution content is empty. Please complete the document before submitting.';
        }

        $signatureConfig = $this->resolutionSignatureRequestConfig($resolution);

        if (! empty($signatureConfig['missing_designations'])) {
            return 'Please select a role/designation before submitting.';
        }

        if (! empty($signatureConfig['missing'])) {
            return 'Assign user accounts for '.implode(', ', $signatureConfig['missing']).' before submitting this BAC Resolution for electronic signatures.';
        }

        $bacChair = $this->userByAccountCode($signatureConfig['chair_account_code'] ?? null)
            ?? $this->activeUserByRoleOrCode(User::ROLE_BAC_CHAIR, 'bac_chair', 'BACCHAIR-001');
        $sourceDocument = $resolution->sourcePrDocument;

        DB::transaction(function () use ($request, $resolution, $sourceDocument, $bacChair, $signatureConfig) {
            $oldStatus = $resolution->status;

            $resolution->update([
                'status' => BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR,
                'signature_status' => 'pending_signatures',
                'submitted_by_user_id' => $request->user()->id,
                'submitted_at' => now(),
                'returned_at' => null,
            ]);

            if ($sourceDocument && $sourceDocument->status !== ProcurementDocument::STATUS_READY_FOR_RFQ) {
                $fromOfficeId = $sourceDocument->current_office_id;
                $toOfficeId = $bacChair?->office_id ?? $this->officeByCodeOrNames('BAC', ['Bids and Awards Committee'])?->id;
                $sourceOldStatus = $sourceDocument->status;

                $sourceDocument->forceFill([
                    'status' => ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW,
                    'stage' => ProcurementDocument::STAGE_BAC_CHAIR_REVIEW,
                    'assigned_to_user_id' => $bacChair?->id,
                    'current_office_id' => $toOfficeId ?? $sourceDocument->current_office_id,
                    'route_destination_role' => User::ROLE_BAC_CHAIR,
                    'routed_by_user_id' => $request->user()->id,
                    'routed_at' => now(),
                    'route_remarks' => 'BAC Resolution submitted for parallel electronic signatures.',
                ])->save();

                DocumentRoutingHistory::create([
                    'procurement_document_id' => $sourceDocument->id,
                    'action_by_user_id' => $request->user()->id,
                    'from_office_id' => $fromOfficeId,
                    'to_office_id' => $toOfficeId,
                    'action' => 'BAC Resolution Submitted for Signatures',
                    'status_from' => $sourceOldStatus,
                    'status_to' => ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW,
                    'comments' => "BAC Resolution {$resolution->displayNumber()} was submitted for parallel electronic signatures.",
                    'action_at' => now(),
                ]);
            }

            $signatureRequests = app(SignatureRequestService::class)->createRequestsForDocument(
                $resolution->refresh(),
                'bac_resolution',
                $signatureConfig,
                $request->user(),
            );

            AuditLogger::log('BAC Resolutions', 'BAC Resolution Submitted for Signatures', 'BAC Secretariat submitted a BAC Resolution for parallel electronic signatures.', $resolution, ['status' => $oldStatus], ['status' => BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR]);

            AuditLogger::signature('signature_requests_created', $resolution, [
                'description' => 'BAC Secretariat routed BAC Resolution for electronic signatures.',
                'metadata' => [
                    'signature_request_count' => $signatureRequests->count(),
                    'assigned_signature_request_count' => $signatureRequests->whereNotNull('requested_to_user_id')->count(),
                ],
            ]);

            app(SvpChainService::class)->linkBacResolution($resolution->refresh(), $request->user(), 'BAC Resolution submitted for signatures');
        });

        return 'BAC Resolution routed for electronic signatures.';
    }

    private function signatureRequestsFor(BacResolution $resolution)
    {
        return SignatureRequest::query()
            ->with(['requestedTo', 'requestedOffice', 'electronicSignature'])
            ->forDocument('bac_resolution', $resolution->id)
            ->orderBy('signing_order')
            ->orderBy('id')
            ->get();
    }

    private function submissionFlashKey(string $message): string
    {
        return str_starts_with($message, 'BAC Resolution routed')
            || str_ends_with($message, 'created.')
            || str_ends_with($message, 'updated.')
            ? 'status'
            : 'error';
    }

    private function markSourceResolutionCreated(ProcurementDocument $sourceDocument, User $user, BacResolution $resolution): void
    {
        if ($sourceDocument->status === ProcurementDocument::STATUS_BAC_RESOLUTION_CREATED) {
            return;
        }

        $oldStatus = $sourceDocument->status;

        if ($sourceDocument->status === ProcurementDocument::STATUS_READY_FOR_RFQ) {
            $sourceDocument->update([
                'route_remarks' => "BAC Resolution {$resolution->displayNumber()} created by BAC Secretariat. PR remains ready for RFQ.",
                'routed_by_user_id' => $user->id,
                'routed_at' => now(),
            ]);

            DocumentRoutingHistory::create([
                'procurement_document_id' => $sourceDocument->id,
                'action_by_user_id' => $user->id,
                'from_office_id' => $sourceDocument->getOriginal('current_office_id'),
                'to_office_id' => $sourceDocument->current_office_id,
                'action' => 'BAC Resolution Created',
                'status_from' => $oldStatus,
                'status_to' => $oldStatus,
                'comments' => "BAC Resolution {$resolution->displayNumber()} was prepared while this Purchase Request remained ready for RFQ.",
                'action_at' => now(),
            ]);

            return;
        }

        $sourceDocument->update([
            'status' => ProcurementDocument::STATUS_BAC_RESOLUTION_CREATED,
            'stage' => ProcurementDocument::STAGE_BAC_RESOLUTION_PREPARATION,
            'assigned_to_user_id' => $user->id,
            'current_office_id' => $user->office_id ?: $sourceDocument->current_office_id,
            'route_destination_role' => User::ROLE_BAC_SECRETARIAT,
            'route_remarks' => "BAC Resolution {$resolution->displayNumber()} created by BAC Secretariat.",
            'routed_by_user_id' => $user->id,
            'routed_at' => now(),
        ]);

        DocumentRoutingHistory::create([
            'procurement_document_id' => $sourceDocument->id,
            'action_by_user_id' => $user->id,
            'from_office_id' => $sourceDocument->getOriginal('current_office_id'),
            'to_office_id' => $sourceDocument->current_office_id,
            'action' => 'BAC Resolution Created',
            'status_from' => $oldStatus,
            'status_to' => ProcurementDocument::STATUS_BAC_RESOLUTION_CREATED,
            'comments' => "BAC Resolution {$resolution->displayNumber()} was prepared for this Purchase Request.",
            'action_at' => now(),
        ]);
    }

    private function validatedResolution(Request $request): array
    {
        $validated = $request->validate([
            'source_pr_document_id' => ['nullable', 'integer', 'exists:procurement_documents,id'],
            'resolution_number' => ['nullable', 'string', 'max:120'],
            'resolution_series' => ['nullable', 'string', 'max:120'],
            'fiscal_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'resolution_date' => ['nullable', 'date'],
            'title' => ['nullable', 'string', 'max:500'],
            'project_title' => ['nullable', 'string', 'max:500'],
            'contractor_name' => ['nullable', 'string', 'max:255'],
            'procurement_mode' => ['nullable', 'string', 'max:160'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'supplier_address' => ['nullable', 'string'],
            'supplier_contact' => ['nullable', 'string', 'max:255'],
            'abc_amount' => ['nullable', 'numeric', 'min:0'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'requesting_office_name' => ['nullable', 'string', 'max:255'],
            'pr_number' => ['nullable', 'string', 'max:120'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'total_amount_words' => ['nullable', 'string', 'max:500'],
            'philgeps_reference_no' => ['nullable', 'string', 'max:120'],
            'solicitation_no' => ['nullable', 'string', 'max:120'],
            'remarks' => ['nullable', 'string'],
            'body_json' => ['nullable', 'array'],
            'body_json.*' => ['nullable', 'string', 'max:3000'],
            'header_lines' => ['nullable', 'array'],
            'header_lines.*' => ['nullable', 'string', 'max:1000'],
            'whereas_clauses' => ['nullable', 'array'],
            'whereas_clauses.*' => ['nullable', 'string', 'max:3000'],
            'resolved_clauses' => ['nullable', 'array'],
            'resolved_clauses.*' => ['nullable', 'string', 'max:3000'],
            'signatories' => ['nullable', 'array'],
            'signatories.*.name' => ['nullable', 'string', 'max:255'],
            'signatories.*.designation' => ['nullable', 'string', 'max:255'],
            'signatories.*.designation_manually_selected' => ['nullable', 'boolean'],
            'signatories.*.account_code' => ['nullable', 'string', 'max:80'],
            'signatories.*.section' => ['nullable', 'string', 'max:80'],
            'signatories.*.display_order' => ['nullable', 'integer', 'min:1', 'max:20'],
            'approval_signatory' => ['nullable', 'array'],
            'approval_signatory.name' => ['nullable', 'string', 'max:255'],
            'approval_signatory.designation' => ['nullable', 'string', 'max:255'],
            'approval_signatory.designation_manually_selected' => ['nullable', 'boolean'],
            'approval_signatory.account_code' => ['nullable', 'string', 'max:80'],
            'approval_signatory.section' => ['nullable', 'string', 'max:80'],
            'approval_signatory.display_order' => ['nullable', 'integer', 'min:1', 'max:20'],
            'approval_signatory.date' => ['nullable', 'string', 'max:120'],
            'approval_details' => ['nullable', 'array'],
            'approval_details.name' => ['nullable', 'string', 'max:255'],
            'approval_details.designation' => ['nullable', 'string', 'max:255'],
            'approval_details.designation_manually_selected' => ['nullable', 'boolean'],
            'approval_details.account_code' => ['nullable', 'string', 'max:80'],
            'approval_details.section' => ['nullable', 'string', 'max:80'],
            'approval_details.display_order' => ['nullable', 'integer', 'min:1', 'max:20'],
            'approval_details.date' => ['nullable', 'string', 'max:120'],
            'document_html' => ['nullable', 'string'],
            'document_text' => ['nullable', 'string'],
        ]);

        $validated['abc_amount'] = $validated['abc_amount'] ?? $validated['total_amount'] ?? null;
        $validated['total_amount'] = $validated['total_amount'] ?? $validated['abc_amount'] ?? 0;

        return $validated;
    }

    private function resolutionPayload(array $validated): array
    {
        $payload = $validated;
        unset($payload['document_html'], $payload['document_text']);

        $body = $this->cleanHeaderLines($validated['body_json'] ?? $validated['header_lines'] ?? []);
        $approval = $this->cleanApprovalSignatory($validated['approval_details'] ?? $validated['approval_signatory'] ?? []);
        $documentHtml = array_key_exists('document_html', $validated)
            ? $this->sanitizeResolutionHtml($validated['document_html'])
            : null;
        $documentText = array_key_exists('document_text', $validated)
            ? $this->cleanResolutionText($validated['document_text'], $documentHtml)
            : null;
        $extractedNumber = $documentText ? $this->extractResolutionNumber($documentText) : null;
        $extractedTitle = $documentText ? $this->extractResolutionTitle($documentText) : null;

        $resolvedPayload = [
            ...$payload,
            'resolution_number' => $extractedNumber ?: ($validated['resolution_number'] ?? null),
            'title' => $extractedTitle ?: ($validated['title'] ?? null),
            'contractor_name' => $validated['contractor_name'] ?? $validated['supplier_name'] ?? null,
            'abc_amount' => $validated['abc_amount'] ?? $validated['total_amount'] ?? null,
            'total_amount' => $validated['total_amount'] ?? $validated['abc_amount'] ?? 0,
            'body_json' => $body,
            'header_lines' => $body,
            'whereas_clauses' => $this->cleanTextList($validated['whereas_clauses'] ?? []),
            'resolved_clauses' => $this->cleanTextList($validated['resolved_clauses'] ?? []),
            'signatories' => $this->cleanSignatories($validated['signatories'] ?? []),
            'approval_details' => $approval,
            'approval_signatory' => $approval,
        ];

        if (array_key_exists('document_html', $validated)) {
            $resolvedPayload['document_html'] = $documentHtml;
            $resolvedPayload['document_text'] = $documentText;
        }

        return $resolvedPayload;
    }

    private function sanitizeResolutionHtml(?string $html): ?string
    {
        if (! filled($html)) {
            return null;
        }

        $html = (string) $html;
        $html = preg_replace('#<(script|iframe|object|embed|form|input|textarea|select|button|link|meta)[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#</?(script|iframe|object|embed|form|input|textarea|select|button|link|meta)[^>]*>#is', '', $html) ?? $html;
        $html = strip_tags($html, '<p><div><span><strong><b><em><i><u><br><ol><ul><li><table><tbody><tr><td><th><thead><tfoot><section><article><header><img>');

        $html = preg_replace_callback('/<([a-z][a-z0-9]*)(\s[^>]*)?>/i', function (array $matches) {
            $tag = strtolower($matches[1]);
            $attributes = $this->sanitizeResolutionAttributes($matches[2] ?? '');

            return "<{$tag}{$attributes}>";
        }, $html) ?? $html;

        return trim($html) ?: null;
    }

    private function sanitizeResolutionAttributes(string $attributes): string
    {
        if ($attributes === '') {
            return '';
        }

        preg_match_all('/\s([a-zA-Z0-9:-]+)(?:\s*=\s*("[^"]*"|\'[^\']*\'|[^\s"\'>]+))?/', $attributes, $matches, PREG_SET_ORDER);

        $allowed = [];

        foreach ($matches as $match) {
            $name = strtolower($match[1]);

            if (str_starts_with($name, 'on')) {
                continue;
            }

            $rawValue = $match[2] ?? '';
            $value = trim($rawValue, "\"' \t\n\r\0\x0B");

            if ($name === 'style') {
                $style = $this->sanitizeResolutionStyle($value);

                if ($style !== '') {
                    $allowed[] = 'style="'.htmlspecialchars($style, ENT_QUOTES, 'UTF-8').'"';
                }

                continue;
            }

            if ($name === 'class') {
                $class = preg_replace('/[^A-Za-z0-9_\-\s]/', '', $value) ?? '';

                if (trim($class) !== '') {
                    $allowed[] = 'class="'.htmlspecialchars(trim($class), ENT_QUOTES, 'UTF-8').'"';
                }

                continue;
            }

            if ($name === 'src') {
                $cleanValue = html_entity_decode($value, ENT_QUOTES, 'UTF-8');

                if (
                    preg_match('#^(?:https?://[A-Za-z0-9.\-:]+/|/|images/)#', $cleanValue)
                    && ! preg_match('/(?:javascript|data):/i', $cleanValue)
                    && preg_match('/^[A-Za-z0-9:\/._?&=%#\-\s]+$/', $cleanValue)
                ) {
                    $allowed[] = 'src="'.htmlspecialchars($cleanValue, ENT_QUOTES, 'UTF-8').'"';
                }

                continue;
            }

            if (in_array($name, ['alt', 'aria-label'], true)) {
                $cleanValue = preg_replace('/[<>"\']/', '', $value) ?? '';

                if (trim($cleanValue) !== '') {
                    $allowed[] = $name.'="'.htmlspecialchars(trim($cleanValue), ENT_QUOTES, 'UTF-8').'"';
                }

                continue;
            }

            if (in_array($name, ['align', 'colspan', 'rowspan'], true)) {
                $cleanValue = preg_replace('/[^A-Za-z0-9_\-]/', '', $value) ?? '';

                if ($cleanValue !== '') {
                    $allowed[] = $name.'="'.htmlspecialchars($cleanValue, ENT_QUOTES, 'UTF-8').'"';
                }
            }
        }

        return $allowed ? ' '.implode(' ', $allowed) : '';
    }

    private function sanitizeResolutionStyle(string $style): string
    {
        $allowedProperties = [
            'font-style',
            'font-weight',
            'list-style-type',
            'margin',
            'margin-bottom',
            'margin-left',
            'margin-right',
            'margin-top',
            'padding-left',
            'text-align',
            'text-decoration',
            'text-indent',
            'text-transform',
            'width',
        ];

        $clean = [];

        foreach (explode(';', html_entity_decode($style, ENT_QUOTES, 'UTF-8')) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);

            if (! in_array($property, $allowedProperties, true)) {
                continue;
            }

            if (preg_match('/(expression|javascript:|url\s*\()/i', $value)) {
                continue;
            }

            if (! preg_match('/^[#A-Za-z0-9\s.,()%\-\/]+$/', $value)) {
                continue;
            }

            $clean[] = "{$property}: {$value}";
        }

        return implode('; ', $clean);
    }

    private function cleanResolutionText(?string $text, ?string $html = null): ?string
    {
        $text = trim((string) $text);

        if ($text === '' && filled($html)) {
            $html = preg_replace('/<br\s*\/?>/i', "\n", (string) $html) ?? (string) $html;
            $html = preg_replace('/<\/(p|div|li|tr)>/i', "\n", $html) ?? $html;
            $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
        }

        $text = preg_replace("/[ \t]+\r?\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text) ?: null;
    }

    private function extractResolutionNumber(string $text): ?string
    {
        if (! preg_match('/BAC\s+RESOLUTION\s+NO\.?\s*[:\-]?\s*([^\r\n]+)/i', $text, $matches)) {
            return null;
        }

        $number = trim($matches[1]);
        $number = trim($number, " _\t\n\r\0\x0B");

        return $number !== '' ? substr($number, 0, 120) : null;
    }

    private function extractResolutionTitle(string $text): ?string
    {
        $lines = collect(preg_split('/\R+/', $text) ?: [])
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->values();

        foreach ($lines as $line) {
            $upper = strtoupper($line);

            if (str_starts_with($upper, 'A RESOLUTION') || str_starts_with($upper, 'RESOLUTION RECOMMENDING')) {
                return substr($line, 0, 500);
            }
        }

        return null;
    }

    private function isResolutionDocumentEmpty(BacResolution $resolution): bool
    {
        $text = $this->cleanResolutionText($resolution->document_text, $resolution->document_html);

        if (filled($text)) {
            return false;
        }

        return blank($resolution->title)
            && empty($resolution->whereas_clauses)
            && empty($resolution->resolved_clauses);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->input('search');

            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('document_reference_number', 'like', "%{$search}%")
                    ->orWhere('resolution_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('project_title', 'like', "%{$search}%")
                    ->orWhere('requesting_office_name', 'like', "%{$search}%")
                    ->orWhere('pr_number', 'like', "%{$search}%")
                    ->orWhereHas('sourcePrDocument', fn (Builder $document) => $document->where('document_reference_number', 'like', "%{$search}%")
                        ->orWhere('tracking_number', 'like', "%{$search}%")
                        ->orWhere('pr_no', 'like', "%{$search}%"));
            });
        });

        $query->when($request->filled('fiscal_year'), fn (Builder $builder) => $builder->where('fiscal_year', $request->integer('fiscal_year')));
        $query->when($request->filled('created_year'), fn (Builder $builder) => $builder->where('created_year', $request->input('created_year')));
        $query->when($request->filled('created_month'), fn (Builder $builder) => $builder->where('created_month', $request->input('created_month')));
        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', fn (Builder $builder) => $builder->where('status', $request->input('status')));
        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('resolution_date', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('resolution_date', '<=', $request->date('date_to')));
    }

    private function selectedEligiblePr(mixed $id): ?ProcurementDocument
    {
        if (! $id) {
            return null;
        }

        return $this->eligiblePrQuery()
            ->with(['submittingOffice', 'submittedBy', 'purchaseRequestItems'])
            ->whereKey($id)
            ->first();
    }

    private function existingResolutionForSource(ProcurementDocument $sourceDocument): ?BacResolution
    {
        return $this->existingResolutionForSourceId($sourceDocument->id);
    }

    private function existingResolutionForSourceId(mixed $sourceDocumentId): ?BacResolution
    {
        if (! $sourceDocumentId) {
            return null;
        }

        return BacResolution::query()
            ->where('source_pr_document_id', $sourceDocumentId)
            ->latest('updated_at')
            ->first();
    }

    private function eligiblePrQuery(): Builder
    {
        $user = request()->user();

        return ProcurementDocument::query()
            ->where(function (Builder $type) {
                $type->where('document_type', 'PR')
                    ->orWhere('document_type', 'Purchase Request');
            })
            ->whereIn('status', self::ELIGIBLE_PR_STATUSES)
            ->where(function (Builder $visibility) use ($user): void {
                $visibility->where('status', '!=', ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION)
                    ->orWhere('assigned_to_user_id', $user?->id);
            })
            ->where(function (Builder $reference): void {
                $reference->whereNotNull('app_consolidation_id')
                    ->orWhereNotNull('app_item_id')
                    ->orWhereHas('purchaseRequestItems', fn (Builder $item): Builder => $item->whereNotNull('app_item_id'))
                    ->orWhereHas('supplementalApps', function (Builder $supplemental): void {
                        $supplemental->whereIn('status', [
                            SupplementalApp::STATUS_ACCEPTED,
                            SupplementalApp::STATUS_LINKED_TO_PR,
                        ]);
                    });
            })
            ->whereDoesntHave('sourcePurchaseOrders');
    }

    private function defaultResolutionData(?ProcurementDocument $sourceDocument, User $user): array
    {
        $totalAmount = (float) ($sourceDocument?->total_amount ?? $sourceDocument?->purchaseRequestItems?->sum(fn ($item) => (float) ($item->estimated_cost ?? $item->estimated_total_cost ?? 0)) ?? 0);
        $projectTitle = $sourceDocument?->title ?? '____________________________';
        $amountNumeric = 'PHP '.number_format($totalAmount, 2);
        $amountWords = $this->amountInWords($totalAmount);
        $amountText = $sourceDocument && $totalAmount > 0 ? "{$amountWords} ({$amountNumeric})" : '________________________';
        $contractor = '________________________';
        $reviewDate = '________________________';
        $philgeps = '________________________';
        $solicitation = '________________________';
        $recommendedAction = '________________________';
        $title = 'A RESOLUTION RECOMMENDING THE APPROVAL OF __________________________ FOR THE PROCUREMENT OF THE PROJECT "'.$projectTitle.'"';

        return [
            'resolution_number' => null,
            'resolution_series' => (string) now()->year,
            'fiscal_year' => $sourceDocument?->fiscal_year ?? now()->year,
            'resolution_date' => null,
            'source_pr_document_id' => $sourceDocument?->id,
            'title' => $title,
            'project_title' => $sourceDocument?->title,
            'contractor_name' => null,
            'procurement_mode' => null,
            'supplier_name' => null,
            'supplier_address' => null,
            'supplier_contact' => null,
            'abc_amount' => $totalAmount > 0 ? $totalAmount : null,
            'refund_amount' => null,
            'requesting_office_name' => $sourceDocument?->submittingOffice?->name,
            'pr_number' => $sourceDocument?->pr_no ?? $sourceDocument?->tracking_number,
            'total_amount' => $totalAmount,
            'total_amount_words' => $amountWords,
            'philgeps_reference_no' => null,
            'solicitation_no' => null,
            'remarks' => null,
            'body_json' => [
                'now_therefore' => 'NOW, THEREFORE, We, the Members of the Bids and Awards Committee, hereby RESOLVE as it is hereby RESOLVED:',
                'recommended_action' => $recommendedAction,
                'review_date_text' => $reviewDate,
                'resolved_day' => '',
                'resolved_month' => '',
                'resolved_year' => (string) ($sourceDocument?->fiscal_year ?? '20____'),
                'resolved_location' => 'at the BAC Office, 2nd Floor, New Municipal Building, LGU Tomas Oppus, Southern Leyte',
            ],
            'header_lines' => [
                'now_therefore' => 'NOW, THEREFORE, We, the Members of the Bids and Awards Committee, hereby RESOLVE as it is hereby RESOLVED:',
                'recommended_action' => $recommendedAction,
                'review_date_text' => $reviewDate,
                'resolved_day' => '',
                'resolved_month' => '',
                'resolved_year' => (string) ($sourceDocument?->fiscal_year ?? '20____'),
                'resolved_location' => 'at the BAC Office, 2nd Floor, New Municipal Building, LGU Tomas Oppus, Southern Leyte',
            ],
            'whereas_clauses' => [
                "WHEREAS, the Local Government Unit of Tomas Oppus, Southern Leyte intended to procure the project titled \"{$projectTitle}\" with an Approved Budget for the Contract (ABC) of {$amountText} under PhilGEPS Reference No. {$philgeps} and Solicitation No. {$solicitation};",
                "WHEREAS, in response to the procurement activity, {$contractor} submitted the required documents for the aforementioned project;",
                "WHEREAS, on {$reviewDate}, the Bids and Awards Committee (BAC) reviewed the procurement proceedings and supporting documents for the said project;",
                'WHEREAS, the BAC found the need to recommend appropriate action in accordance with applicable procurement rules, regulations, and the best interest of the government;',
                'WHEREAS, the action was initiated to serve the best interest of the government and the public, and was strictly made in accordance with applicable laws, rules, and regulations;',
                "WHEREAS, {$contractor} submitted the necessary request and/or supporting documents to the Bids and Awards Committee for proper action;",
                'WHEREAS, guided by the principles of fairness, equity, transparency, accountability, and the applicable rules of the Government Procurement Policy Board (GPPB), the BAC finds the request to be valid, justifiable, and legally sound;',
            ],
            'resolved_clauses' => [
                "To RECOMMEND the approval of {$recommendedAction} in favor of {$contractor} for the project \"{$projectTitle}\" in the amount of {$amountText};",
                'To forward this Resolution to the Local Chief Executive for final approval and appropriate action.',
            ],
            'signatories' => $this->defaultSignatories($user),
            'approval_details' => $this->defaultApprovalSignatory(),
            'approval_signatory' => $this->defaultApprovalSignatory(),
        ];
    }

    private function copySourceItems(BacResolution $resolution, ProcurementDocument $sourceDocument): void
    {
        $resolution->items()->delete();

        $sourceDocument->loadMissing('purchaseRequestItems');

        foreach ($sourceDocument->purchaseRequestItems as $item) {
            $quantity = (float) ($item->quantity ?? 0);
            $unitCost = (float) ($item->estimated_unit_cost ?? 0);
            $totalCost = (float) ($item->estimated_cost ?? $item->estimated_total_cost ?? ($quantity * $unitCost));

            $resolution->items()->create([
                'source_pr_item_id' => $item->id,
                'item_no' => $item->item_no,
                'description' => $item->description ?? $item->item_description ?? 'PR item',
                'quantity' => $quantity,
                'unit' => $item->unit ?? $item->unit_of_issue,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'remarks' => $item->remarks,
            ]);
        }
    }

    private function defaultSignatories(?User $user = null): array
    {
        $members = User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->where(function (Builder $query) {
                $query->where('role', User::ROLE_BAC_MEMBER)
                    ->orWhereHas('assignedRole', fn (Builder $role) => $role->where('code', 'bac_member'));
            })
            ->orderBy('user_id')
            ->limit(3)
            ->get();

        $chair = $this->activeUserByRoleOrCode(User::ROLE_BAC_CHAIR, 'bac_chair', 'BACCHAIR-001');
        $viceChair = $this->activeUserByRoleOrCode(User::ROLE_BAC_VICE_CHAIRPERSON, 'bac_vice_chairperson', 'BACVICE-001');
        $preparedBy = $user && ($user->hasRole('bac_secretariat') || $user->hasRole(User::ROLE_BAC_SECRETARIAT))
            ? $user
            : $this->activeUserByRoleOrCode(User::ROLE_BAC_SECRETARIAT, 'bac_secretariat', 'BACSEC-002');

        return [
            'prepared_by' => $this->defaultSignerPayload($preparedBy, 'BAC Secretariat', 'Prepared By', 1),
            'chairperson' => $this->defaultSignerPayload($chair, 'BAC Chairperson', 'Attested By', 2),
            'vice_chairperson' => $this->defaultSignerPayload($viceChair ?? $members->get(0), 'BAC Vice Chairperson', 'Attested By', 3),
            'member_one' => $this->defaultSignerPayload($members->get(0), 'BAC Member', 'Attested By', 4),
            'member_two' => $this->defaultSignerPayload($members->get(1), 'BAC Member', 'Attested By', 5),
            'provisional_member' => $this->defaultSignerPayload($members->get(2), 'BAC Member', 'Attested By', 6),
        ];
    }

    private function defaultApprovalSignatory(): array
    {
        $hope = $this->activeUserByRoleOrCode(User::ROLE_APPROVING_AUTHORITY, 'approving_authority', 'HOPE-001')
            ?? User::where('user_id', 'MAYOR-001')->first();

        return [
            'name' => '',
            'designation' => '',
            'account_code' => $hope?->user_id ?? '',
            'section' => 'Approved By',
            'display_order' => 7,
            'date' => '',
        ];
    }

    private function defaultSignerPayload(?User $user, string $designation, string $section, int $displayOrder): array
    {
        return [
            'name' => '',
            'designation' => '',
            'account_code' => $user?->user_id ?? '',
            'section' => $section,
            'display_order' => $displayOrder,
        ];
    }

    private function cleanTextList(array $items): array
    {
        return collect($items)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();
    }

    private function cleanHeaderLines(array $headerLines): array
    {
        return collect($headerLines)
            ->map(fn ($value) => trim((string) $value))
            ->all();
    }

    private function cleanSignatories(array $signatories): array
    {
        $display = $this->bacResolutionSignatoryDisplay();

        return collect($signatories)
            ->map(function ($item, $key) use ($display) {
                $designationWasManuallySelected = filter_var($item['designation_manually_selected'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $designation = $designationWasManuallySelected ? trim((string) ($item['designation'] ?? '')) : '';

                return [
                    'name' => trim((string) ($item['name'] ?? '')),
                    'designation' => $designation,
                    'designation_manually_selected' => $designation !== '' && $designationWasManuallySelected,
                    'account_code' => trim((string) ($item['account_code'] ?? '')),
                    'section' => $display[$key]['section'] ?? trim((string) ($item['section'] ?? '')),
                    'display_order' => (int) ($display[$key]['display_order'] ?? $item['display_order'] ?? 99),
                ];
            })
            ->all();
    }

    private function cleanApprovalSignatory(array $approvalSignatory): array
    {
        $designationWasManuallySelected = filter_var($approvalSignatory['designation_manually_selected'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $designation = $designationWasManuallySelected ? trim((string) ($approvalSignatory['designation'] ?? '')) : '';

        return [
            'name' => trim((string) ($approvalSignatory['name'] ?? '')),
            'designation' => $designation,
            'designation_manually_selected' => $designation !== '' && $designationWasManuallySelected,
            'account_code' => trim((string) ($approvalSignatory['account_code'] ?? '')),
            'section' => 'Approved By',
            'display_order' => 7,
            'date' => trim((string) ($approvalSignatory['date'] ?? '')),
        ];
    }

    private function bacResolutionSignatoryDisplay(): array
    {
        return [
            'chairperson' => ['section' => 'Attested By', 'display_order' => 2],
            'vice_chairperson' => ['section' => 'Attested By', 'display_order' => 3],
            'member_one' => ['section' => 'Attested By', 'display_order' => 4],
            'member_two' => ['section' => 'Attested By', 'display_order' => 5],
            'provisional_member' => ['section' => 'Attested By', 'display_order' => 6],
        ];
    }

    private function resolutionSignatureRequestConfig(BacResolution $resolution): array
    {
        $signatories = $resolution->signatories ?: [];
        $approval = $resolution->approval_signatory ?: $resolution->approval_details ?: [];
        $slots = [
            'chairperson' => ['slot' => 'bac_chairperson', 'label' => 'BAC Chairperson', 'section' => 'Attested By', 'display_order' => 2],
            'vice_chairperson' => ['slot' => 'bac_vice_chairperson', 'label' => 'BAC Vice Chairperson', 'section' => 'Attested By', 'display_order' => 3],
            'member_one' => ['slot' => 'bac_member_1', 'label' => 'BAC Member', 'section' => 'Attested By', 'display_order' => 4],
            'member_two' => ['slot' => 'bac_member_2', 'label' => 'BAC Member', 'section' => 'Attested By', 'display_order' => 5],
            'provisional_member' => ['slot' => 'bac_provisional_member', 'label' => 'BAC Member', 'section' => 'Attested By', 'display_order' => 6],
        ];
        $missing = [];
        $missingDesignations = [];
        $signers = [];

        foreach ($slots as $key => $slot) {
            $details = $signatories[$key] ?? [];
            $accountCode = trim((string) ($details['account_code'] ?? ''));
            $designationWasManuallySelected = filter_var($details['designation_manually_selected'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $designation = $designationWasManuallySelected ? trim((string) ($details['designation'] ?? '')) : '';

            if ($designation === '') {
                $missingDesignations[] = $slot['label'];
                continue;
            }

            $signer = $key === 'vice_chairperson'
                ? $this->bacResolutionSignerForDesignation($designation, $key) ?? $this->userByAccountCode($accountCode)
                : $this->userByAccountCode($accountCode) ?? $this->bacResolutionSignerForDesignation($designation, $key);

            if (! $signer) {
                $missing[] = $slot['label'];
                continue;
            }

            $signers[] = [
                'slot' => $slot['slot'],
                'label' => $slot['label'],
                'account_code' => $signer->user_id,
                'role_name' => $designation,
                'designation' => $designation,
                'printed_name' => trim((string) ($details['name'] ?? '')),
                'order' => (int) ($details['display_order'] ?? $slot['display_order']),
                'required' => true,
            ];
        }

        $hopeAccountCode = trim((string) ($approval['account_code'] ?? ''));
        $hopeDesignationWasManuallySelected = filter_var($approval['designation_manually_selected'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $hopeDesignation = $hopeDesignationWasManuallySelected ? trim((string) ($approval['designation'] ?? '')) : '';

        if ($hopeDesignation === '') {
            $missingDesignations[] = 'Approved By';
        }

        $hopeSigner = $this->userByAccountCode($hopeAccountCode) ?? $this->bacResolutionSignerForDesignation($hopeDesignation, 'hope');

        if ($hopeDesignation !== '' && ! $hopeSigner) {
            $missing[] = 'Approved By';
        } elseif ($hopeDesignation !== '') {
            $signers[] = [
                'slot' => 'hope',
                'label' => 'Approved By',
                'account_code' => $hopeSigner->user_id,
                'role_name' => $hopeDesignation,
                'designation' => $hopeDesignation,
                'printed_name' => trim((string) ($approval['name'] ?? '')),
                'order' => (int) ($approval['display_order'] ?? 7),
                'required' => true,
            ];
        }

        return [
            'signing_mode' => 'parallel',
            'signers' => $signers,
            'missing' => $missing,
            'missing_designations' => $missingDesignations,
            'chair_account_code' => $signatories['chairperson']['account_code'] ?? null,
        ];
    }

    private function activeSignatoryUsers()
    {
        return User::query()
            ->with(['assignedOffice', 'assignedRole'])
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('user_id')
            ->get();
    }

    private function userByAccountCode(?string $accountCode): ?User
    {
        if (! filled($accountCode)) {
            return null;
        }

        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->where('user_id', $accountCode)
            ->first();
    }

    private function bacResolutionSignerForDesignation(string $designation, ?string $slotKey = null): ?User
    {
        $normalized = $this->normalizeBacResolutionDesignation($designation);

        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, 'secretariat')) {
            return $this->activeUserByRoleOrCode(User::ROLE_BAC_SECRETARIAT, 'bac_secretariat', 'BACSEC-002');
        }

        if (str_contains($normalized, 'procuring entity')
            || str_contains($normalized, 'local chief executive')
            || str_contains($normalized, 'municipal mayor')
            || str_contains($normalized, 'approving authority')
            || str_contains($normalized, 'hope')) {
            return $this->activeUserByRoleOrCode(User::ROLE_APPROVING_AUTHORITY, 'approving_authority', 'HOPE-001')
                ?? $this->userByAccountCode('MAYOR-001');
        }

        if (str_contains($normalized, 'vice chair')) {
            return $this->activeUserByRoleOrCode(User::ROLE_BAC_VICE_CHAIRPERSON, 'bac_vice_chairperson', 'BACVICE-001')
                ?? $this->bacMemberSignerForSlot($slotKey, $normalized);
        }

        if ((str_contains($normalized, 'chairperson') || str_contains($normalized, 'bac chair')) && ! str_contains($normalized, 'vice')) {
            return $this->activeUserByRoleOrCode(User::ROLE_BAC_CHAIR, 'bac_chair', 'BACCHAIR-001');
        }

        if (str_contains($normalized, 'bac member') || str_contains($normalized, 'vice chair') || str_contains($normalized, 'provisional')) {
            return $this->bacMemberSignerForSlot($slotKey, $normalized);
        }

        return null;
    }

    private function bacMemberSignerForSlot(?string $slotKey, string $normalizedDesignation): ?User
    {
        $baseQuery = User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->where(function (Builder $query) {
                $query->where('role', User::ROLE_BAC_MEMBER)
                    ->orWhereHas('assignedRole', fn (Builder $role) => $role->where('code', 'bac_member')->orWhere('name', User::ROLE_BAC_MEMBER));
            })
            ->orderBy('user_id');

        if (str_contains($normalizedDesignation, 'vice')) {
            $preferred = (clone $baseQuery)
                ->where(function (Builder $query) {
                    $query->where('position', 'like', '%Vice%')
                        ->orWhere('signer_position', 'like', '%Vice%');
                })
                ->first();

            if ($preferred) {
                return $preferred;
            }
        }

        if (str_contains($normalizedDesignation, 'provisional')) {
            $preferred = (clone $baseQuery)
                ->where(function (Builder $query) {
                    $query->where('position', 'like', '%Provisional%')
                        ->orWhere('signer_position', 'like', '%Provisional%');
                })
                ->first();

            if ($preferred) {
                return $preferred;
            }
        }

        $members = $baseQuery->get();
        $offset = [
            'member_one' => 0,
            'member_two' => 1,
            'provisional_member' => 2,
        ][$slotKey] ?? 0;

        return $members->get($offset) ?? $members->first();
    }

    private function normalizeBacResolutionDesignation(string $designation): string
    {
        $normalized = strtolower(str_replace(['-', ',', '/', '\\'], ' ', $designation));
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?: '';

        return trim(preg_replace('/\s+/', ' ', $normalized) ?: '');
    }

    private function statuses(): array
    {
        return [
            BacResolution::STATUS_DRAFT,
            BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR,
            BacResolution::STATUS_RETURNED_BY_BAC_CHAIR,
            BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR,
            BacResolution::STATUS_FORWARDED_TO_HOPE,
            BacResolution::STATUS_APPROVED_BY_HOPE,
            BacResolution::STATUS_RETURNED_BY_HOPE,
            BacResolution::STATUS_RETURNED_TO_END_USER,
            BacResolution::STATUS_RETURNED,
            BacResolution::STATUS_CANCELLED,
        ];
    }

    private function summary(): array
    {
        return [
            'draft' => BacResolution::where('status', BacResolution::STATUS_DRAFT)->count(),
            'submitted' => BacResolution::where('status', BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR)->count(),
            'confirmed' => BacResolution::where('status', BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR)->count(),
            'approved' => BacResolution::where('status', BacResolution::STATUS_APPROVED_BY_HOPE)->count(),
            'returnedToOffice' => BacResolution::where('status', BacResolution::STATUS_RETURNED_TO_END_USER)->count(),
        ];
    }

    private function activeUserByRoleOrCode(string $roleName, string $roleCode, string $fallbackUserId): ?User
    {
        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->where(function (Builder $query) use ($roleName, $roleCode, $fallbackUserId) {
                $query->where('role', $roleName)
                    ->orWhere('user_id', $fallbackUserId)
                    ->orWhereHas('assignedRole', fn (Builder $role) => $role->where('code', $roleCode)->orWhere('name', $roleName));
            })
            ->first();
    }

    private function officeByCodeOrNames(string $code, array $names): ?Office
    {
        return Office::query()
            ->where('code', $code)
            ->orWhereIn('name', $names)
            ->first();
    }

    private function amountInWords(float $amount): string
    {
        $whole = (int) floor($amount);
        $centavos = (int) round(($amount - $whole) * 100);
        $words = $this->numberToWords($whole);
        $result = str($words ?: 'zero')->title()->toString().' Pesos';

        if ($centavos > 0) {
            $result .= ' and '.$this->numberToWords($centavos).' Centavos';
        }

        return $result;
    }

    private function numberToWords(int $number): string
    {
        $units = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
        $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

        if ($number < 20) {
            return $units[$number];
        }

        if ($number < 100) {
            return trim($tens[intdiv($number, 10)].' '.$units[$number % 10]);
        }

        if ($number < 1000) {
            return trim($units[intdiv($number, 100)].' hundred '.$this->numberToWords($number % 100));
        }

        foreach ([1000000000 => 'billion', 1000000 => 'million', 1000 => 'thousand'] as $value => $label) {
            if ($number >= $value) {
                return trim($this->numberToWords(intdiv($number, $value))." {$label} ".$this->numberToWords($number % $value));
            }
        }

        return '';
    }
}
