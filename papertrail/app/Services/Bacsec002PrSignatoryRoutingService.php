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

class Bacsec002PrSignatoryRoutingService
{
    private const CREATOR_USER_ID = 'BACSEC-002';
    private const DOCUMENT_TYPE = 'purchase_request';

    private const SIGNATORY_COLUMNS = [
        'certification' => ['slot' => 'prepared_by', 'label' => 'Prepared By', 'order' => 1],
        'budget' => ['slot' => 'budget_certification', 'label' => 'Municipal Budget Officer', 'order' => 1],
        'requested' => ['slot' => 'requested_by', 'label' => 'Requested By', 'order' => 1],
        'approved' => ['slot' => 'approved_by', 'label' => 'Approved By', 'order' => 1],
    ];

    private const DESIGNATION_ROUTES = [
        'bac secretariat' => [
            'label' => 'BAC Secretariat',
            'user_ids' => ['BACSEC-003'],
            'names' => ['Meagan C. Matutes', 'Meagan Matutes'],
        ],
        'oic municipal budget officer' => [
            'label' => 'OIC - Municipal Budget Officer',
            'user_ids' => ['MBO-001', 'BUDGET-001'],
            'names' => ['Mavel D. Vismanos', 'Mavel Vismanos'],
            'office_names' => ['Municipal Budget Office'],
        ],
        'municipal budget officer' => [
            'label' => 'Municipal Budget Officer',
            'user_ids' => ['MBO-001', 'BUDGET-001'],
            'names' => ['Mavel D. Vismanos', 'Mavel Vismanos'],
            'office_names' => ['Municipal Budget Office'],
        ],
        'private secretary' => [
            'label' => 'Private Secretary',
            'user_ids' => ['MO-001'],
            'positions' => ['Private Secretary'],
        ],
        'municipal mayor' => [
            'label' => 'Municipal Mayor',
            'user_ids' => ['MO-001'],
            'names' => ['Hon. Rod Ivan Cuares Pano', 'Rod Ivan Cuares Pano'],
            'positions' => ['Municipal Mayor', 'Mayor'],
        ],
    ];

    public function __construct(private readonly SignatureRequestService $signatureRequests)
    {
    }

    public function designationOptions(): array
    {
        return collect(self::DESIGNATION_ROUTES)
            ->pluck('label')
            ->values()
            ->all();
    }

    public function signatureColumns(): array
    {
        return self::SIGNATORY_COLUMNS;
    }

    public function shouldUseForUser(?User $user): bool
    {
        return $user?->user_id === self::CREATOR_USER_ID
            && ($user->hasRole('bac_secretariat') || $user->hasRole(User::ROLE_BAC_SECRETARIAT));
    }

    public function isPilotDocument(ProcurementDocument $document): bool
    {
        if (! in_array($document->document_type, ['PR', 'Purchase Request'], true)) {
            return false;
        }

        $creator = $document->submittedBy ?: User::find($document->submitted_by_user_id);

        return $creator?->user_id === self::CREATOR_USER_ID;
    }

    public function shouldStartFor(User $user, ProcurementDocument $document): bool
    {
        return $this->shouldUseForUser($user)
            && $this->isPilotDocument($document)
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

    public function canSubmitCompletedToPrNumbering(?User $user, ProcurementDocument $document): bool
    {
        return $this->shouldUseForUser($user)
            && $this->isPilotDocument($document)
            && (int) $document->submitted_by_user_id === (int) $user?->id
            && $document->status === ProcurementDocument::STATUS_PR_SIGNATORIES_COMPLETED
            && $document->pr_number_status !== ProcurementDocument::PR_NUMBER_STATUS_PENDING_ASSIGNMENT
            && $document->pr_number_status !== ProcurementDocument::PR_NUMBER_STATUS_ASSIGNED;
    }

    public function submissionError(array $signatories): ?string
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

            if (! $this->routeForDesignation($designation)) {
                return "{$label} has an unsupported designation. Please select a designation from the dropdown.";
            }

            if (! $this->signerForDesignation($designation)) {
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
            'pr_number_status' => ProcurementDocument::PR_NUMBER_STATUS_NOT_REQUESTED,
            'pr_no_requested_at' => null,
            'pr_number_remarks' => null,
        ])->save();

        $requests = $this->signatureRequests->createRequestsForDocument(
            $document->refresh(),
            self::DOCUMENT_TYPE,
            [
                'signing_mode' => 'parallel',
                'signers' => $this->signersFor($document),
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
            'Signature requests were generated from BACSEC-002 selected signatory designations.',
            $fromOfficeId,
            $document->current_office_id,
        );

        AuditLogger::log('Purchase Request Signature Workflow', 'pr_signatory_requests_generated', 'BACSEC-002 PR designation-based signature requests were generated.', $document, ['status' => $oldStatus], [
            'status' => ProcurementDocument::STATUS_PR_PENDING_SIGNATORIES,
            'signature_request_count' => $requests->count(),
            'signing_mode' => 'parallel',
        ]);

        app(SvpChainService::class)->findOrCreateFromPr($document->refresh(), $actor, 'PR signatory requests generated');

        return $requests;
    }

    public function handleSigned(ProcurementDocument $document, User $actor, bool $fullySigned): string
    {
        if (! $this->isPilotDocument($document)) {
            return 'ignored';
        }

        $this->ensureParallelWorkflow($document->refresh());
        $this->refreshSignatoryState($document->refresh());

        if (! $this->hasCompletedRequiredParallelSignatures($document->refresh())) {
            $this->notifyCreatorOfSignerCompletion($document->refresh(), $actor);

            return 'pending';
        }

        $this->completeAndReturnToCreator($document->refresh(), $actor);

        return 'completed_signatories';
    }

    public function handleDeclined(ProcurementDocument $document, User $actor, string $reason): void
    {
        if (! $this->isPilotDocument($document)) {
            return;
        }

        $document->loadMissing(['submittingOffice', 'submittedBy']);
        $this->refreshSignatoryState($document->refresh());
        $oldStatus = $document->status;

        $document->fill([
            'status' => ProcurementDocument::STATUS_PR_DRAFT,
            'stage' => 'Purchase Request Preparation',
            'returned_at' => now(),
            'current_office_id' => $document->submitting_office_id ?: $document->current_office_id,
            'assigned_to_user_id' => $document->submitted_by_user_id,
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

        SystemNotificationService::notify(
            $document->submittedBy,
            'Purchase Request Signature Declined',
            'A required Purchase Request signature was declined. Review the remarks, update the PR, and submit again.',
            SystemNotification::TYPE_WARNING,
            'Purchase Request Signatures',
            $document,
            route('head-office.pr.show', $document),
        );
    }

    public function signerForDesignation(string $designation): ?User
    {
        $route = $this->routeForDesignation($designation);

        if (! $route) {
            return null;
        }

        foreach ($route['user_ids'] ?? [] as $userId) {
            $user = User::query()
                ->where('status', User::STATUS_ACTIVE)
                ->where('user_id', $userId)
                ->first();

            if ($user) {
                return $user;
            }
        }

        foreach ($route['names'] ?? [] as $name) {
            $user = User::query()
                ->where('status', User::STATUS_ACTIVE)
                ->where('name', $name)
                ->first();

            if ($user) {
                return $user;
            }
        }

        foreach ($route['positions'] ?? [] as $position) {
            $user = User::query()
                ->where('status', User::STATUS_ACTIVE)
                ->where('position', $position)
                ->first();

            if ($user) {
                return $user;
            }
        }

        foreach ($route['office_names'] ?? [] as $officeName) {
            $user = User::query()
                ->where('status', User::STATUS_ACTIVE)
                ->whereHas('assignedOffice', fn (Builder $office) => $office->where('name', $officeName))
                ->first();

            if ($user) {
                return $user;
            }
        }

        return null;
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
        if (! $this->isPilotDocument($document)) {
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
                ->map(fn (SignatureRequest $request) => $request->requested_to_role ?: $request->requestedTo?->name ?: $request->signatory_label)
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
                if ($this->isPilotDocument($document)) {
                    $this->ensureParallelWorkflow($document);
                }
            });
    }

    public function ensureParallelWorkflow(ProcurementDocument $document): void
    {
        if (! $this->isPilotDocument($document)) {
            return;
        }

        $activeSlots = $this->activeSignatureSlots();

        $this->syncActiveRequestRecipients($document, $activeSlots);

        SignatureRequest::query()
            ->forDocument(self::DOCUMENT_TYPE, $document->id)
            ->whereNotIn('signatory_slot', $activeSlots)
            ->where('is_required', true)
            ->where('status', '!=', SignatureRequest::STATUS_SIGNED)
            ->get()
            ->each(function (SignatureRequest $request): void {
                $request->forceFill([
                    'is_required' => false,
                    'status' => SignatureRequest::STATUS_SKIPPED,
                    'remarks' => trim(($request->remarks ? $request->remarks . "\n" : '') . 'Skipped by BACSEC-002 parallel PR signature routing.'),
                ])->save();
            });

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
                    'remarks' => $request->status === SignatureRequest::STATUS_SKIPPED
                        ? null
                        : $request->remarks,
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
            $route = $this->routeForDesignation($designation);
            $signer = $this->signerForDesignation($designation);

            if (! $request || ! $route || ! $signer) {
                continue;
            }

            $recipientChanged = (int) $request->requested_to_user_id !== (int) $signer->id;
            $role = $route['label'] ?? $designation;

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
                    'pr_signatory_column' => $column,
                    'printed_name' => $signatories[$column]['printed_name'] ?? null,
                    'designation' => $designation,
                ],
            ])->save();
        }
    }

    private function completeAndReturnToCreator(ProcurementDocument $document, User $actor): void
    {
        if ($document->status === ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT) {
            return;
        }

        if ($document->status === ProcurementDocument::STATUS_PR_SIGNATORIES_COMPLETED) {
            return;
        }

        $document->loadMissing(['submittedBy']);
        $oldStatus = $document->status;
        $fromOfficeId = $document->current_office_id;
        $creator = $document->submittedBy ?: User::find($document->submitted_by_user_id);
        $creatorOfficeId = $creator?->office_id ?: $document->submitting_office_id ?: $document->current_office_id;

        $document->fill([
            'status' => ProcurementDocument::STATUS_PR_SIGNATORIES_COMPLETED,
            'stage' => 'Ready for PR Number Assignment',
            'pr_number_status' => ProcurementDocument::PR_NUMBER_STATUS_NOT_REQUESTED,
            'pr_no_requested_at' => null,
            'pr_number_remarks' => null,
            'returned_at' => null,
            'current_office_id' => $creatorOfficeId,
            'assigned_to_user_id' => $creator?->id ?: $document->submitted_by_user_id,
            'bac_secretariat_status' => null,
        ])->save();

        $this->recordRouting(
            $document->refresh(),
            $actor,
            'PR Signatories Completed and Returned to BACSEC-002',
            $oldStatus,
            ProcurementDocument::STATUS_PR_SIGNATORIES_COMPLETED,
            'All required Purchase Request signatories have signed. Purchase Request returned to BACSEC-002 for PR number submission.',
            $fromOfficeId,
            $creatorOfficeId,
        );

        if ($creator) {
            SystemNotificationService::notify(
                $creator,
                'PR Signatures Completed',
                'All required Purchase Request signatures are complete. Submit the PR for official number assignment when ready.',
                SystemNotification::TYPE_SUCCESS,
                'Purchase Request Signatures',
                $document,
                route('head-office.pr.show', $document),
            );
        }

        AuditLogger::log('Purchase Request Signature Workflow', 'pr_signatories_completed_returned_to_creator', 'BACSEC-002 Purchase Request signatures were completed and returned to BACSEC-002 for PR number submission.', $document, ['status' => $oldStatus], [
            'status' => ProcurementDocument::STATUS_PR_SIGNATORIES_COMPLETED,
            'tracking_number' => $document->tracking_number,
        ]);

        app(SvpChainService::class)->findOrCreateFromPr($document->refresh(), $actor, 'PR signatories completed and returned to BACSEC-002');
    }

    private function notifyCreatorOfSignerCompletion(ProcurementDocument $document, User $actor): void
    {
        $document->loadMissing(['submittedBy']);

        $creator = $document->submittedBy ?: User::find($document->submitted_by_user_id);

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
        $label = $signedRequest?->signatory_label ?: 'assigned signatory';
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

        AuditLogger::log('Purchase Request Signature Workflow', 'pr_signer_completion_notified_creator', 'BACSEC-002 PR creator was notified after a signer completed a signature.', $document, null, [
            'tracking_number' => $tracking,
            'creator_user_id' => $creator->user_id,
            'signer_user_id' => $actor->user_id,
            'signatory_label' => $label,
            'completed_signatures' => $completed,
            'required_signatures' => $required,
        ]);
    }

    private function signersFor(ProcurementDocument $document): array
    {
        $signatories = is_array($document->pr_signatories) ? $document->pr_signatories : [];

        return collect(self::SIGNATORY_COLUMNS)
            ->map(function (array $definition, string $column) use ($signatories) {
                $designation = (string) ($signatories[$column]['designation'] ?? '');
                $signer = $this->signerForDesignation($designation);

                if (! $signer) {
                    return null;
                }

                return [
                    'slot' => $definition['slot'],
                    'label' => $definition['label'],
                    'order' => 1,
                    'signing_mode' => 'parallel',
                    'account_code' => $signer->user_id,
                    'role_name' => $this->routeForDesignation($designation)['label'] ?? $designation,
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
                    'pr_signatory_column' => $column,
                    'printed_name' => $signatories[$column]['printed_name'] ?? null,
                    'designation' => $signatories[$column]['designation'] ?? null,
                ],
            ])->save();
        }
    }

    private function refreshSignatoryState(ProcurementDocument $document): void
    {
        if (! $this->isPilotDocument($document)) {
            return;
        }

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

    private function routeForDesignation(string $designation): ?array
    {
        return self::DESIGNATION_ROUTES[$this->normalizeDesignation($designation)] ?? null;
    }

    private function normalizeDesignation(string $designation): string
    {
        $normalized = strtolower(str_replace(['-', ',', '/', '\\'], ' ', $designation));
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?: '';

        return trim(preg_replace('/\s+/', ' ', $normalized) ?: '');
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
}
