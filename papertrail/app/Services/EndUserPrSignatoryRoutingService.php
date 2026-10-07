<?php

namespace App\Services;

use App\Models\DocumentRoutingHistory;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\SignatureRequest;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EndUserPrSignatoryRoutingService
{
    private const DOCUMENT_TYPE = 'purchase_request';

    private const SIGNATORY_COLUMNS = [
        'certification' => ['slot' => 'prepared_by', 'label' => 'Prepared By', 'order' => 1],
        'budget' => ['slot' => 'budget_certification', 'label' => 'Municipal Budget Officer', 'order' => 1],
        'requested' => ['slot' => 'requested_by', 'label' => 'Requested By', 'order' => 1],
        'approved' => ['slot' => 'approved_by', 'label' => 'Approved By', 'order' => 1],
    ];

    private const DESIGNATION_OPTIONS = [
        'Head of Office / End User',
        'Municipal Mayor',
        'Municipal Budget Officer',
        'Municipal Treasurer',
        'Accounting Officer',
        'BAC Secretariat',
        'Other Authorized Signatory',
    ];

    public function __construct(private readonly SignatureRequestService $signatureRequests)
    {
    }

    public function designationOptions(): array
    {
        return self::DESIGNATION_OPTIONS;
    }

    public function signatureColumns(): array
    {
        return self::SIGNATORY_COLUMNS;
    }

    public function shouldUseForUser(?User $user): bool
    {
        return $user
            && ! $this->isBacsec002($user)
            && ($user->hasRole('head_office') || $user->hasRole(User::ROLE_HEAD_OFFICE));
    }

    public function isWorkflowDocument(ProcurementDocument $document): bool
    {
        if (! in_array($document->document_type, ['PR', 'Purchase Request'], true)) {
            return false;
        }

        $creator = $document->submittedBy ?: ($document->submitted_by_user_id ? User::find($document->submitted_by_user_id) : null);

        return $creator
            && ! $this->isBacsec002($creator)
            && ($creator->hasRole('head_office') || $creator->hasRole(User::ROLE_HEAD_OFFICE));
    }

    public function hasSignatureRouting(ProcurementDocument $document): bool
    {
        if (! $document->exists || ! $this->isWorkflowDocument($document)) {
            return false;
        }

        return SignatureRequest::query()
            ->forDocument(self::DOCUMENT_TYPE, $document->id)
            ->whereIn('signatory_slot', $this->activeSignatureSlots())
            ->exists();
    }

    public function shouldStartFor(User $user, ProcurementDocument $document): bool
    {
        return $this->shouldUseForUser($user)
            && $this->isWorkflowDocument($document)
            && in_array($document->status, [
                ProcurementDocument::STATUS_PR_DRAFT,
                ProcurementDocument::STATUS_RETURNED_BY_PR_NUMBERING_STAFF,
                ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
                ProcurementDocument::STATUS_RETURNED_BY_BUDGET,
                ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING,
                ProcurementDocument::STATUS_RETURNED_BY_BAC_MEMBER,
                ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR,
                ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY,
                'pr_returned',
            ], true);
    }

    public function submissionError(array $signatories, ProcurementDocument $document): ?string
    {
        foreach (self::SIGNATORY_COLUMNS as $column => $definition) {
            $label = $definition['label'];
            $printedName = trim((string) ($signatories[$column]['printed_name'] ?? ''));
            $designation = trim((string) ($signatories[$column]['designation'] ?? ''));
            $designationWasManuallySelected = filter_var($signatories[$column]['designation_manually_selected'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($printedName === '') {
                return "{$label} printed name is required before generating signature requests.";
            }

            if (! $designationWasManuallySelected || $designation === '') {
                return 'Please select a role/designation before submitting.';
            }

            if (! $this->isSupportedDesignation($designation)) {
                return "{$label} has an unsupported designation. Please select a designation from the dropdown.";
            }

            if (! $this->signerForDesignation($designation, $document)) {
                return "{$label} designation has no active signer account configured.";
            }
        }

        return null;
    }

    public function start(ProcurementDocument $document, User $actor): Collection
    {
        if (! $this->shouldStartFor($actor, $document)) {
            return collect();
        }

        $document->loadMissing(['submittingOffice', 'submittedBy']);
        $oldStatus = $document->status;
        $fromOfficeId = $document->current_office_id;

        if (! $document->tracking_number) {
            $document->tracking_number = $this->generateTrackingNumber(
                (int) $document->fiscal_year,
                $document->submittingOffice ?: $actor->assignedOffice,
            );
        }

        $document->fill([
            'status' => ProcurementDocument::STATUS_PR_PENDING_SIGNATORIES,
            'stage' => ProcurementDocument::STAGE_PR_SIGNATORY_WORKFLOW,
            'submitted_at' => now(),
            'returned_at' => null,
            'current_office_id' => $document->submitting_office_id ?: $actor->office_id,
            'assigned_to_user_id' => null,
            'pr_number_status' => ProcurementDocument::PR_NUMBER_STATUS_NOT_REQUESTED,
            'pr_no_requested_at' => null,
            'pr_number_remarks' => null,
        ])->save();

        $requests = $this->signatureRequests->createRequestsForDocument(
            $document->refresh(),
            self::DOCUMENT_TYPE,
            [
                'signing_mode' => 'parallel',
                'signers' => $this->signersFor($document->refresh()),
            ],
            $actor,
        );

        $this->storeRequestMetadata($document->refresh(), $requests);
        $this->ensureParallelWorkflow($document->refresh());
        $this->refreshSignatoryState($document->refresh());

        $this->recordRouting(
            $document->refresh(),
            $actor,
            'PR Signatory Requests Generated',
            $oldStatus,
            ProcurementDocument::STATUS_PR_PENDING_SIGNATORIES,
            'Signature requests were generated from selected Purchase Request signatory designations.',
            $fromOfficeId,
            $document->current_office_id,
        );

        AuditLogger::log('Purchase Request Signature Workflow', 'pr_signatory_requests_generated', 'End User PR designation-based signature requests were generated.', $document, ['status' => $oldStatus], [
            'status' => ProcurementDocument::STATUS_PR_PENDING_SIGNATORIES,
            'signature_request_count' => $requests->count(),
            'signing_mode' => 'parallel',
        ]);

        app(SvpChainService::class)->findOrCreateFromPr($document->refresh(), $actor, 'PR signatory requests generated');

        return $requests;
    }

    public function handleSigned(ProcurementDocument $document, User $actor, bool $fullySigned): string
    {
        if (! $this->isWorkflowDocument($document) && ! $this->hasSignatureRouting($document)) {
            return 'ignored';
        }

        $this->ensureParallelWorkflow($document->refresh());
        $this->refreshSignatoryState($document->refresh());

        if (! $this->hasCompletedRequiredParallelSignatures($document->refresh())) {
            $this->notifyCreatorOfSignerCompletion($document->refresh(), $actor);

            return 'pending';
        }

        $this->submitToPrNumbering($document->refresh(), $actor);

        return 'submitted_to_pr_numbering';
    }

    public function handleDeclined(ProcurementDocument $document, User $actor, string $reason): void
    {
        if (! $this->isWorkflowDocument($document) && ! $this->hasSignatureRouting($document)) {
            return;
        }

        $document->loadMissing(['submittingOffice', 'submittedBy']);
        $this->refreshSignatoryState($document->refresh());
        $oldStatus = $document->status;
        $creator = $document->submittedBy ?: ($document->submitted_by_user_id ? User::find($document->submitted_by_user_id) : null);

        $document->fill([
            'status' => ProcurementDocument::STATUS_PR_DRAFT,
            'stage' => 'Purchase Request Preparation',
            'returned_at' => now(),
            'current_office_id' => $document->submitting_office_id ?: $creator?->office_id ?: $document->current_office_id,
            'assigned_to_user_id' => $creator?->id ?: $document->submitted_by_user_id,
            'pr_number_status' => ProcurementDocument::PR_NUMBER_STATUS_NOT_REQUESTED,
        ])->save();

        $this->recordRouting(
            $document,
            $actor,
            'PR Signature Request Declined',
            $oldStatus,
            ProcurementDocument::STATUS_PR_DRAFT,
            $reason,
            $actor->office_id,
            $document->current_office_id,
        );

        if ($creator) {
            SystemNotificationService::notify(
                $creator,
                'Purchase Request Signature Declined',
                'A required Purchase Request signature was declined. Review the remarks, update the PR, and submit again.',
                SystemNotification::TYPE_WARNING,
                'Purchase Request Signatures',
                $document,
                route('head-office.pr.show', $document),
            );
        }
    }

    public function signerForDesignation(string $designation, ?ProcurementDocument $document = null): ?User
    {
        $normalized = $this->normalizeDesignation($designation);

        return match ($normalized) {
            'head of office end user', 'other authorized signatory' => $this->requestingOfficeSigner($document),
            'municipal mayor' => $this->activeUserByIds(['MO-001'])
                ?: $this->activeHeadOfficeUserByOffice(['MO'], ['Office of the Municipal Mayor', "Mayor's Office"]),
            'municipal budget officer' => $this->activeUserByIds(['MBO-001'])
                ?: $this->activeHeadOfficeUserByOffice(['MBO'], ['Municipal Budget Office'])
                ?: $this->activeUserByIds(['BUDGET-001'])
                ?: $this->activeUserByOffice(['MBO'], ['Municipal Budget Office']),
            'municipal treasurer' => $this->activeUserByIds(['MTO-001'])
                ?: $this->activeHeadOfficeUserByOffice(['MTO'], ['Municipal Treasurers Office', 'Municipal Treasurer Office']),
            'accounting officer' => $this->activeUserByIds(['ACCOUNTING-001', 'ACC-001'])
                ?: $this->activeHeadOfficeUserByOffice(['Accounting', 'MACCO'], ['Municipal Accounting Office', 'Accounting Office']),
            'bac secretariat' => $this->activeUserByIds(['BACSEC-003', 'BACSEC-001', 'BACSEC-004'])
                ?: $this->activeUserByOffice(['BACSEC'], ['BAC Secretariat']),
            default => null,
        };
    }

    public function statusForColumn(ProcurementDocument $document, string $column): array
    {
        $slot = self::SIGNATORY_COLUMNS[$column]['slot'] ?? null;

        if (! $slot || ! $document->exists) {
            return ['status' => 'not_generated', 'label' => 'Not Generated', 'date' => null];
        }

        $this->ensureParallelWorkflow($document);

        $request = SignatureRequest::query()
            ->forDocument(self::DOCUMENT_TYPE, $document->id)
            ->where('signatory_slot', $slot)
            ->with('requestedTo')
            ->latest('updated_at')
            ->first();

        if (! $request) {
            return ['status' => 'not_generated', 'label' => 'Not Generated', 'date' => null];
        }

        if ($request->status === SignatureRequest::STATUS_SIGNED) {
            return [
                'status' => SignatureRequest::STATUS_SIGNED,
                'label' => 'Signed',
                'date' => $request->signed_at,
                'request' => $request,
                'signer_name' => $request->requestedTo?->name,
                'signer_user_id' => $request->requestedTo?->user_id,
            ];
        }

        if (in_array($request->status, [SignatureRequest::STATUS_DECLINED, SignatureRequest::STATUS_RETURNED], true)) {
            return [
                'status' => 'rejected',
                'label' => 'Rejected',
                'date' => $request->declined_at,
                'request' => $request,
                'signer_name' => $request->requestedTo?->name,
                'signer_user_id' => $request->requestedTo?->user_id,
            ];
        }

        return [
            'status' => 'pending',
            'label' => 'Pending Signature',
            'date' => null,
            'request' => $request,
            'signer_name' => $request->requestedTo?->name,
            'signer_user_id' => $request->requestedTo?->user_id,
        ];
    }

    public function signatureProgress(ProcurementDocument $document): array
    {
        if (! $this->hasSignatureRouting($document)) {
            return [
                'required' => 0,
                'completed' => 0,
                'pending_labels' => [],
            ];
        }

        $this->ensureParallelWorkflow($document);

        $requests = SignatureRequest::query()
            ->forDocument(self::DOCUMENT_TYPE, $document->id)
            ->whereIn('signatory_slot', $this->activeSignatureSlots())
            ->where('is_required', true)
            ->with('requestedTo')
            ->get();

        return [
            'required' => $requests->count(),
            'completed' => $requests->where('status', SignatureRequest::STATUS_SIGNED)->count(),
            'pending_labels' => $requests
                ->reject(fn (SignatureRequest $request) => $request->status === SignatureRequest::STATUS_SIGNED)
                ->map(fn (SignatureRequest $request) => $request->requested_to_role ?: $request->signatory_label)
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ];
    }

    public function activateParallelRequestsForSigner(User $user): void
    {
        $documentIds = SignatureRequest::query()
            ->where('document_type', self::DOCUMENT_TYPE)
            ->where('requested_to_user_id', $user->id)
            ->where('status', SignatureRequest::STATUS_WAITING)
            ->pluck('document_id')
            ->unique()
            ->values();

        if ($documentIds->isEmpty()) {
            return;
        }

        ProcurementDocument::query()
            ->whereKey($documentIds)
            ->with('submittedBy')
            ->get()
            ->each(function (ProcurementDocument $document): void {
                if ($this->isWorkflowDocument($document) || $this->hasSignatureRouting($document)) {
                    $this->ensureParallelWorkflow($document);
                }
            });
    }

    public function ensureParallelWorkflow(ProcurementDocument $document): void
    {
        if (! $this->isWorkflowDocument($document) && ! $this->hasSignatureRouting($document)) {
            return;
        }

        $activeSlots = $this->activeSignatureSlots();
        $this->syncActiveRequestRecipients($document, $activeSlots);

        SignatureRequest::query()
            ->forDocument(self::DOCUMENT_TYPE, $document->id)
            ->whereIn('signatory_slot', $activeSlots)
            ->where(function (Builder $query): void {
                $query->where('status', SignatureRequest::STATUS_WAITING)
                    ->orWhere('status', SignatureRequest::STATUS_SKIPPED)
                    ->orWhere('is_required', false)
                    ->orWhere('signing_mode', '!=', 'parallel')
                    ->orWhere('signing_order', '!=', 1);
            })
            ->get()
            ->each(function (SignatureRequest $request): void {
                $request->forceFill([
                    'status' => in_array($request->status, [SignatureRequest::STATUS_WAITING, SignatureRequest::STATUS_SKIPPED], true)
                        ? SignatureRequest::STATUS_PENDING
                        : $request->status,
                    'is_required' => filled($request->requested_to_user_id) || filled($request->requested_to_office_id),
                    'signing_mode' => 'parallel',
                    'signing_order' => 1,
                    'remarks' => $request->status === SignatureRequest::STATUS_SKIPPED ? null : $request->remarks,
                ])->save();
            });

        SignatureRequest::query()
            ->forDocument(self::DOCUMENT_TYPE, $document->id)
            ->whereIn('signatory_slot', $activeSlots)
            ->whereIn('status', SignatureRequest::OPEN_STATUSES)
            ->whereNotNull('requested_to_user_id')
            ->whereNull('notification_sent_at')
            ->with(['requestedBy', 'requestedTo'])
            ->get()
            ->each(fn (SignatureRequest $request) => $this->signatureRequests->notifySigner($request));
    }

    private function syncActiveRequestRecipients(ProcurementDocument $document, array $activeSlots): void
    {
        $signatories = is_array($document->pr_signatories) ? $document->pr_signatories : [];
        $requests = SignatureRequest::query()
            ->forDocument(self::DOCUMENT_TYPE, $document->id)
            ->whereIn('signatory_slot', $activeSlots)
            ->where('status', '!=', SignatureRequest::STATUS_SIGNED)
            ->get()
            ->keyBy('signatory_slot');

        foreach (self::SIGNATORY_COLUMNS as $column => $definition) {
            $request = $requests->get($definition['slot']);
            $designation = (string) ($signatories[$column]['designation'] ?? '');
            $signer = $this->signerForDesignation($designation, $document);

            if (! $request || ! $signer) {
                continue;
            }

            $recipientChanged = (int) $request->requested_to_user_id !== (int) $signer->id;
            $role = $this->canonicalDesignationLabel($designation) ?: $designation;

            $request->forceFill([
                'signatory_label' => $definition['label'],
                'requested_to_user_id' => $signer->id,
                'requested_to_role' => $role,
                'requested_to_office_id' => $signer->office_id ?: $request->requested_to_office_id,
                'is_required' => true,
                'status' => $recipientChanged
                    ? SignatureRequest::STATUS_PENDING
                    : (in_array($request->status, [SignatureRequest::STATUS_WAITING, SignatureRequest::STATUS_SKIPPED], true)
                        ? SignatureRequest::STATUS_PENDING
                        : $request->status),
                'notification_sent_at' => $recipientChanged ? null : $request->notification_sent_at,
                'email_sent_at' => $recipientChanged ? null : $request->email_sent_at,
                'remarks' => $request->status === SignatureRequest::STATUS_SKIPPED ? null : $request->remarks,
                'metadata' => [
                    ...($request->metadata ?: []),
                    'account_code' => $signer->user_id,
                    'role_name' => $role,
                    'pr_signature_workflow' => 'end_user_designation',
                    'pr_signatory_column' => $column,
                    'printed_name' => $signatories[$column]['printed_name'] ?? null,
                    'designation' => $designation,
                ],
            ])->save();
        }
    }

    private function submitToPrNumbering(ProcurementDocument $document, User $actor): void
    {
        if ($document->status === ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT) {
            return;
        }

        $target = $this->prNumberingTarget();
        $numberingOffice = $target['office'];
        $numberingUser = $target['user'];

        if (! $numberingOffice || ! $numberingUser) {
            $this->completeAndReturnToCreator($document, $actor);

            return;
        }

        $oldStatus = $document->status;
        $oldValues = $document->only(['status', 'pr_number_status', 'stage', 'current_office_id', 'assigned_to_user_id']);
        $fromOfficeId = $document->current_office_id;

        $document->fill([
            'status' => ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT,
            'stage' => ProcurementDocument::STAGE_PR_NUMBER_ASSIGNMENT,
            'pr_status' => null,
            'pr_number_status' => ProcurementDocument::PR_NUMBER_STATUS_PENDING_ASSIGNMENT,
            'pr_no_requested_at' => now(),
            'pr_number_remarks' => null,
            'returned_at' => null,
            'current_office_id' => $numberingOffice->id,
            'assigned_to_user_id' => $numberingUser->id,
            'bac_secretariat_status' => null,
        ])->save();

        $this->recordRouting(
            $document->refresh(),
            $actor,
            'PR Signatories Completed and Submitted for PR Number Assignment',
            $oldStatus,
            ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT,
            'All required Purchase Request signatories have signed. Purchase Request routed to PR Numbering Staff for official PR number assignment.',
            $fromOfficeId,
            $numberingOffice->id,
        );

        SystemNotificationService::notify(
            $numberingUser,
            'New Purchase Request for PR Number Assignment',
            "Purchase Request {$document->tracking_number} has completed signatures and is waiting for official PR number assignment.",
            SystemNotification::TYPE_INFO,
            'PR Number Assignment',
            $document,
            route('pr-numbering.pending.show', $document),
        );

        $creator = $document->submittedBy ?: ($document->submitted_by_user_id ? User::find($document->submitted_by_user_id) : null);

        if ($creator) {
            SystemNotificationService::notify(
                $creator,
                'PR Signatures Completed',
                'All required Purchase Request signatures are complete. The PR was submitted to PR Numbering Staff.',
                SystemNotification::TYPE_SUCCESS,
                'Purchase Request Signatures',
                $document,
                route('head-office.pr.show', $document),
            );
        }

        AuditLogger::log('Purchase Request Signature Workflow', 'pr_signatories_completed_submitted_to_pr_numbering', 'End User Purchase Request signatures were completed and routed to PR Numbering Staff.', $document, $oldValues, [
            'status' => ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT,
            'tracking_number' => $document->tracking_number,
        ]);

        app(SvpChainService::class)->findOrCreateFromPr($document->refresh(), $actor, 'PR signatures completed and submitted to PR Numbering Staff');
    }

    private function completeAndReturnToCreator(ProcurementDocument $document, User $actor): void
    {
        if ($document->status === ProcurementDocument::STATUS_PR_SIGNATORIES_COMPLETED) {
            return;
        }

        $creator = $document->submittedBy ?: ($document->submitted_by_user_id ? User::find($document->submitted_by_user_id) : null);
        $oldStatus = $document->status;
        $fromOfficeId = $document->current_office_id;

        $document->fill([
            'status' => ProcurementDocument::STATUS_PR_SIGNATORIES_COMPLETED,
            'stage' => ProcurementDocument::STAGE_PR_SIGNATORIES_COMPLETED,
            'pr_number_status' => ProcurementDocument::PR_NUMBER_STATUS_NOT_REQUESTED,
            'returned_at' => null,
            'current_office_id' => $document->submitting_office_id ?: $creator?->office_id ?: $document->current_office_id,
            'assigned_to_user_id' => $creator?->id ?: $document->submitted_by_user_id,
            'bac_secretariat_status' => null,
        ])->save();

        $this->recordRouting(
            $document->refresh(),
            $actor,
            'PR Signatories Completed',
            $oldStatus,
            ProcurementDocument::STATUS_PR_SIGNATORIES_COMPLETED,
            'All required Purchase Request signatories have signed. PR Numbering Staff target is not configured.',
            $fromOfficeId,
            $document->current_office_id,
        );

        if ($creator) {
            SystemNotificationService::notify(
                $creator,
                'PR Signatures Completed',
                'All required Purchase Request signatures are complete. PR Numbering Staff routing target is not configured.',
                SystemNotification::TYPE_WARNING,
                'Purchase Request Signatures',
                $document,
                route('head-office.pr.show', $document),
            );
        }
    }

    private function notifyCreatorOfSignerCompletion(ProcurementDocument $document, User $actor): void
    {
        $creator = $document->submittedBy ?: ($document->submitted_by_user_id ? User::find($document->submitted_by_user_id) : null);

        if (! $creator || (int) $creator->id === (int) $actor->id) {
            return;
        }

        $signedRequest = SignatureRequest::query()
            ->forDocument(self::DOCUMENT_TYPE, $document->id)
            ->whereIn('signatory_slot', $this->activeSignatureSlots())
            ->where('requested_to_user_id', $actor->id)
            ->where('status', SignatureRequest::STATUS_SIGNED)
            ->latest('signed_at')
            ->first();

        $requiredRequests = SignatureRequest::query()
            ->forDocument(self::DOCUMENT_TYPE, $document->id)
            ->whereIn('signatory_slot', $this->activeSignatureSlots())
            ->where('is_required', true)
            ->get();

        $tracking = $document->tracking_number ?: $document->pr_no ?: 'Purchase Request #' . $document->id;
        $label = $signedRequest?->requested_to_role ?: $signedRequest?->signatory_label ?: 'assigned signatory';
        $completed = $requiredRequests->where('status', SignatureRequest::STATUS_SIGNED)->count();
        $required = $requiredRequests->count();
        $progress = $required > 0 ? " {$completed}/{$required} required signatures are now complete." : '';

        SystemNotificationService::notify(
            $creator,
            'PR Signature Completed',
            "{$actor->name} signed {$tracking} as {$label}.{$progress}",
            SystemNotification::TYPE_SUCCESS,
            'Purchase Request Signatures',
            $document,
            route('head-office.pr.show', $document),
        );
    }

    private function signersFor(ProcurementDocument $document): array
    {
        $signatories = is_array($document->pr_signatories) ? $document->pr_signatories : [];

        return collect(self::SIGNATORY_COLUMNS)
            ->map(function (array $definition, string $column) use ($document, $signatories) {
                $designation = (string) ($signatories[$column]['designation'] ?? '');
                $signer = $this->signerForDesignation($designation, $document);

                if (! $signer) {
                    return null;
                }

                return [
                    'slot' => $definition['slot'],
                    'label' => $definition['label'],
                    'order' => 1,
                    'signing_mode' => 'parallel',
                    'account_code' => $signer->user_id,
                    'role_name' => $this->canonicalDesignationLabel($designation) ?: $designation,
                    'designation' => $designation,
                    'printed_name' => $signatories[$column]['printed_name'] ?? null,
                    'required' => true,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function storeRequestMetadata(ProcurementDocument $document, Collection $requests): void
    {
        $signatories = is_array($document->pr_signatories) ? $document->pr_signatories : [];

        foreach ($requests as $request) {
            $column = $this->columnForSlot((string) $request->signatory_slot);

            if (! $column) {
                continue;
            }

            $request->forceFill([
                'metadata' => [
                    ...($request->metadata ?: []),
                    'pr_signature_workflow' => 'end_user_designation',
                    'pr_signatory_column' => $column,
                    'printed_name' => $signatories[$column]['printed_name'] ?? null,
                    'designation' => $signatories[$column]['designation'] ?? null,
                ],
            ])->save();
        }
    }

    private function refreshSignatoryState(ProcurementDocument $document): void
    {
        $signatories = is_array($document->pr_signatories) ? $document->pr_signatories : [];
        $requests = SignatureRequest::query()
            ->forDocument(self::DOCUMENT_TYPE, $document->id)
            ->whereIn('signatory_slot', collect(self::SIGNATORY_COLUMNS)->pluck('slot')->all())
            ->get()
            ->keyBy('signatory_slot');

        foreach (self::SIGNATORY_COLUMNS as $column => $definition) {
            $request = $requests->get($definition['slot']);
            $signatories[$column] = $signatories[$column] ?? [];

            if (! $request) {
                $signatories[$column]['signature_status'] = 'not_generated';
                continue;
            }

            $signatories[$column]['signature_status'] = $request->status === SignatureRequest::STATUS_SIGNED
                ? SignatureRequest::STATUS_SIGNED
                : (in_array($request->status, [SignatureRequest::STATUS_DECLINED, SignatureRequest::STATUS_RETURNED], true) ? 'rejected' : 'pending');
            $signatories[$column]['signature_request_id'] = $request->id;

            if ($request->status === SignatureRequest::STATUS_SIGNED && $request->signed_at) {
                $signatories[$column]['date'] = $request->signed_at->format('m/d/Y');
            } elseif ($request->status !== SignatureRequest::STATUS_SIGNED) {
                $signatories[$column]['date'] = '';
            }
        }

        $document->forceFill(['pr_signatories' => $signatories])->save();
    }

    private function hasCompletedRequiredParallelSignatures(ProcurementDocument $document): bool
    {
        $requests = SignatureRequest::query()
            ->forDocument(self::DOCUMENT_TYPE, $document->id)
            ->whereIn('signatory_slot', $this->activeSignatureSlots())
            ->where('is_required', true)
            ->get();

        return $requests->isNotEmpty()
            && $requests->every(fn (SignatureRequest $request) => $request->status === SignatureRequest::STATUS_SIGNED);
    }

    private function activeSignatureSlots(): array
    {
        return collect(self::SIGNATORY_COLUMNS)
            ->pluck('slot')
            ->values()
            ->all();
    }

    private function isSupportedDesignation(string $designation): bool
    {
        return $this->canonicalDesignationLabel($designation) !== null;
    }

    private function canonicalDesignationLabel(string $designation): ?string
    {
        $normalized = $this->normalizeDesignation($designation);

        foreach (self::DESIGNATION_OPTIONS as $option) {
            if ($this->normalizeDesignation($option) === $normalized) {
                return $option;
            }
        }

        return null;
    }

    private function normalizeDesignation(string $designation): string
    {
        $normalized = strtolower(str_replace(['-', ',', '/', '\\'], ' ', $designation));
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?: '';

        return trim(preg_replace('/\s+/', ' ', $normalized) ?: '');
    }

    private function requestingOfficeSigner(?ProcurementDocument $document): ?User
    {
        if (! $document) {
            return null;
        }

        $document->loadMissing(['submittingOffice', 'submittedBy']);
        $officeId = $document->submitting_office_id ?: $document->submittedBy?->office_id;

        if (! $officeId) {
            return null;
        }

        $submittedBy = $document->submittedBy;

        if ($submittedBy
            && (int) $submittedBy->office_id === (int) $officeId
            && $submittedBy->status === User::STATUS_ACTIVE
            && ($submittedBy->hasRole('head_office') || $submittedBy->hasRole(User::ROLE_HEAD_OFFICE))) {
            return $submittedBy;
        }

        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->where('office_id', $officeId)
            ->where(function (Builder $query) {
                $query->whereHas('assignedRole', fn (Builder $role) => $role->where('code', 'head_office')->orWhere('name', User::ROLE_HEAD_OFFICE))
                    ->orWhere('role', User::ROLE_HEAD_OFFICE);
            })
            ->orderBy('id')
            ->first();
    }

    private function activeUserByIds(array $userIds): ?User
    {
        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->whereIn('user_id', $userIds)
            ->orderByRaw($this->userIdOrderingSql($userIds))
            ->first();
    }

    private function activeHeadOfficeUserByOffice(array $officeCodes, array $officeNames): ?User
    {
        return $this->activeUserByOffice($officeCodes, $officeNames, true);
    }

    private function activeUserByOffice(array $officeCodes, array $officeNames, bool $headOfficeOnly = false): ?User
    {
        $officeId = Office::query()
            ->where(function (Builder $query) use ($officeCodes, $officeNames) {
                $query->whereIn('code', $officeCodes)
                    ->orWhereIn('name', $officeNames);
            })
            ->value('id');

        if (! $officeId) {
            return null;
        }

        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->where('office_id', $officeId)
            ->when($headOfficeOnly, function (Builder $query): void {
                $query->where(function (Builder $roleQuery): void {
                    $roleQuery->whereHas('assignedRole', fn (Builder $role) => $role->where('code', 'head_office')->orWhere('name', User::ROLE_HEAD_OFFICE))
                        ->orWhere('role', User::ROLE_HEAD_OFFICE);
                });
            })
            ->orderBy('id')
            ->first();
    }

    private function userIdOrderingSql(array $userIds): string
    {
        $cases = collect(array_values($userIds))
            ->map(fn (string $userId, int $index): string => "WHEN user_id = '{$userId}' THEN {$index}")
            ->implode(' ');

        return "CASE {$cases} ELSE 999 END";
    }

    private function columnForSlot(string $slot): ?string
    {
        foreach (self::SIGNATORY_COLUMNS as $column => $definition) {
            if ($definition['slot'] === $slot) {
                return $column;
            }
        }

        return null;
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

    private function recordRouting(ProcurementDocument $document, User $actor, string $action, ?string $fromStatus, string $toStatus, ?string $comments, ?int $fromOfficeId, ?int $toOfficeId): void
    {
        DocumentRoutingHistory::create([
            'procurement_document_id' => $document->id,
            'action_by_user_id' => $actor->id,
            'from_office_id' => $fromOfficeId,
            'to_office_id' => $toOfficeId,
            'action' => $action,
            'status_from' => $fromStatus,
            'status_to' => $toStatus,
            'comments' => $comments,
            'action_at' => now(),
        ]);
    }

    private function isBacsec002(User $user): bool
    {
        return $user->user_id === 'BACSEC-002';
    }
}
