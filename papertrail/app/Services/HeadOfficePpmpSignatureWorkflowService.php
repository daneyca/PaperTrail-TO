<?php

namespace App\Services;

use App\Models\DocumentRoutingHistory;
use App\Models\ElectronicSignature;
use App\Models\ProcurementDocument;
use App\Models\SignatureRequest;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class HeadOfficePpmpSignatureWorkflowService
{
    private const DOCUMENT_TYPE = 'ppmp';
    private const COMBINED_SIGNATURE_SLOT = 'prepared_and_submitted_by';
    private const LEGACY_SPLIT_SIGNATURE_SLOTS = ['prepared_by', 'submitted_by'];
    private const EXCLUDED_USER_IDS = ['BACSEC-001'];

    private const SIGNATURE_SLOTS = [
        self::COMBINED_SIGNATURE_SLOT => [
            'slot' => self::COMBINED_SIGNATURE_SLOT,
            'label' => 'Prepared and Submitted By',
            'order' => 1,
        ],
    ];

    public function __construct(private readonly SignatureRequestService $signatureRequests)
    {
    }

    public function isWorkflowDocument(ProcurementDocument $document): bool
    {
        if ($document->document_type !== 'PPMP') {
            return false;
        }

        $document->loadMissing('submittedBy');
        $creator = $document->submittedBy ?: ($document->submitted_by_user_id ? User::find($document->submitted_by_user_id) : null);

        return $creator
            && ! in_array($creator->user_id, self::EXCLUDED_USER_IDS, true)
            && $creator->isOfficeDocumentActor();
    }

    public function hasSignatureRouting(ProcurementDocument $document): bool
    {
        if (! $document->exists || $document->document_type !== 'PPMP') {
            return false;
        }

        return SignatureRequest::query()
            ->forDocument(self::DOCUMENT_TYPE, $document->id)
            ->whereIn('signatory_slot', array_merge($this->activeSignatureSlots(), self::LEGACY_SPLIT_SIGNATURE_SLOTS))
            ->exists();
    }

    public function ensureRequiredSignatures(ProcurementDocument $document, ?User $actor = null): void
    {
        if (! $document->exists || $document->document_type !== 'PPMP') {
            return;
        }

        if (! $this->isWorkflowDocument($document) && ! $this->hasSignatureRouting($document)) {
            return;
        }

        if ($document->status !== ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES) {
            return;
        }
        $this->retireObsoleteRequests($document);

        $signer = $this->responsibleSigner($document);

        if (! $signer) {
            return;
        }

        foreach ($this->requiredSigners($document) as $payload) {
            $exists = SignatureRequest::query()->forDocument(self::DOCUMENT_TYPE, $document->id)
                ->where('signatory_slot', $payload['slot'])->where('is_required', true)
                ->where('requested_to_user_id', $payload['slot'] === 'prepared_by' ? $this->preparedSigner($document)?->id : $signer->id)
                ->whereIn('status', array_merge(SignatureRequest::OPEN_STATUSES, [SignatureRequest::STATUS_WAITING, SignatureRequest::STATUS_SIGNED]))
                ->exists();
            if (! $exists) {
                $this->signatureRequests->createOrUpdateRequest($document, self::DOCUMENT_TYPE, $payload, $actor ?: $signer);
            }
        }
        $this->activateHeadAfterPreparation($document);
    }

    public function canStartFor(User $actor, ProcurementDocument $document): bool
    {
        return $this->isWorkflowDocument($document)
            && ! in_array($actor->user_id, self::EXCLUDED_USER_IDS, true)
            && $actor->canManageSvpDocuments()
            && (int) $document->submitting_office_id === (int) $actor->office_id
            && in_array($document->status, [
                ProcurementDocument::STATUS_PPMP_DRAFT,
                ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
                ProcurementDocument::STATUS_PPMP_SIGNATURE_RETURNED,
            ], true);
    }

    public function canSubmitToAppConsolidation(User $actor, ProcurementDocument $document): bool
    {
        return $this->isWorkflowDocument($document)
            && ! in_array($actor->user_id, self::EXCLUDED_USER_IDS, true)
            && $actor->canManageSvpDocuments()
            && (int) $document->submitting_office_id === (int) $actor->office_id
            && $document->status === ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED
            && $this->hasCompletedRequiredSignatures($document);
    }

    public function start(ProcurementDocument $document, User $actor): Collection
    {
        if (! $this->canStartFor($actor, $document)) {
            return collect();
        }

        $document->loadMissing(['submittingOffice', 'submittedBy', 'preparedBy']);
        $signer = $this->responsibleSigner($document);

        if (! $signer) {
            throw ValidationException::withMessages([
                'signatory' => 'No active Head of Office account is configured for this office. Ask the administrator to assign one before submitting.',
            ]);
        }

        $oldStatus = $document->status;
        if (in_array($oldStatus, [ProcurementDocument::STATUS_PPMP_SIGNATURE_RETURNED, ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT], true)) {
            $oldRequests = SignatureRequest::query()->forDocument(self::DOCUMENT_TYPE, $document->id)->where('is_required', true)->get();
            ElectronicSignature::query()->whereIn('signature_request_id', $oldRequests->pluck('id'))
                ->where('signature_status', ElectronicSignature::STATUS_SIGNED)
                ->update(['signature_status' => ElectronicSignature::STATUS_INVALIDATED]);
            foreach ($oldRequests as $oldRequest) {
                $oldRequest->forceFill(['status' => SignatureRequest::STATUS_CANCELLED, 'is_required' => false, 'signed_at' => null, 'declined_at' => null, 'cancelled_at' => now()])->save();
            }
        }
        $fromOfficeId = $document->current_office_id;
        $officeId = $document->submitting_office_id ?: $actor->office_id;

        $document->fill([
            'submitted_by_name' => $actor->isOfficeDocumentPreparer()
                ? $signer->name
                : ($document->submitted_by_name ?: $signer->name),
            'status' => ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES,
            'stage' => ProcurementDocument::STAGE_PPMP_SIGNATURE_WORKFLOW,
            'returned_at' => null,
            'current_office_id' => $officeId,
            'assigned_to_user_id' => $this->isStaffPrepared($document) ? $this->preparedSigner($document)->id : $signer->id,
        ])->save();

        $this->retireObsoleteRequests($document->refresh());

        $requests = $this->signatureRequests->createRequestsForDocument(
            $document->refresh(),
            self::DOCUMENT_TYPE,
            [
                'signing_mode' => $this->isStaffPrepared($document) ? 'sequential' : 'parallel',
                'signers' => $this->requiredSigners($document),
            ],
            $actor,
        );

        $this->recordRouting(
            $document->refresh(),
            $actor,
            'PPMP Signature Requests Generated',
            $oldStatus,
            ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES,
            $actor->isOfficeDocumentPreparer()
                ? 'The PPMP requires the Procurement Staff Prepared By signature, followed by the Head of Office Submitted By signature.'
                : 'Prepared and Submitted By electronic signature request was generated.',
            $fromOfficeId,
            $document->current_office_id,
        );

        AuditLogger::log('PPMP Signature Workflow', 'ppmp_signature_requests_generated', 'Head of Office PPMP electronic signature requests were generated.', $document, ['status' => $oldStatus], [
            'status' => ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES,
            'signature_request_count' => $requests->count(),
            'signing_mode' => $this->isStaffPrepared($document) ? 'sequential' : 'parallel',
        ]);

        return $requests;
    }

    public function handleSigned(ProcurementDocument $document, User $actor, bool $fullySigned): string
    {
        if (! $this->isWorkflowDocument($document) && ! $this->hasSignatureRouting($document)) {
            return 'ignored';
        }

        $this->retireObsoleteRequests($document);
        $this->activateHeadAfterPreparation($document);

        if (! $this->hasCompletedRequiredSignatures($document->refresh())) {
            $this->notifyCreatorOfSignerCompletion($document->refresh(), $actor);

            return 'pending';
        }

        $this->completeSignatories($document->refresh(), $actor);

        return 'completed_signatories';
    }

    public function handleDeclined(ProcurementDocument $document, User $actor, string $reason): void
    {
        if (! $this->isWorkflowDocument($document) && ! $this->hasSignatureRouting($document)) {
            return;
        }

        $document->loadMissing(['submittingOffice', 'submittedBy']);
        $creator = $document->submittedBy ?: ($document->submitted_by_user_id ? User::find($document->submitted_by_user_id) : null);
        $oldStatus = $document->status;
        $fromOfficeId = $document->current_office_id;
        $ownerOfficeId = $document->submitting_office_id ?: $creator?->office_id ?: $document->current_office_id;

        $document->fill([
            'status' => ProcurementDocument::STATUS_PPMP_SIGNATURE_RETURNED,
            'stage' => ProcurementDocument::STAGE_PPMP_PREPARATION,
            'returned_at' => now(),
            'remarks' => $reason,
            'current_office_id' => $ownerOfficeId,
            'assigned_to_user_id' => $creator?->id ?: $document->submitted_by_user_id,
        ])->save();

        $this->recordRouting(
            $document->refresh(),
            $actor,
            'PPMP Signature Request Declined',
            $oldStatus,
            ProcurementDocument::STATUS_PPMP_SIGNATURE_RETURNED,
            $reason,
            $fromOfficeId,
            $ownerOfficeId,
        );

        SystemNotificationService::notify(
            $creator,
            'PPMP Signature Declined',
            'A required PPMP signature was declined. Review the remarks, update the PPMP, and submit for e-signature again.',
            SystemNotification::TYPE_WARNING,
            'PPMP Signatures',
            $document,
            route('head-office.ppmp.show', $document),
        );

        AuditLogger::log('PPMP Signature Workflow', 'ppmp_signature_request_declined', 'A PPMP signature request was declined.', $document, ['status' => $oldStatus], [
            'status' => ProcurementDocument::STATUS_PPMP_SIGNATURE_RETURNED,
            'signer_user_id' => $actor->user_id,
        ], 'warning');
    }

    public function signatureRequests(ProcurementDocument $document): Collection
    {
        if (! $document->exists) {
            return collect();
        }

        return SignatureRequest::query()
            ->forDocument(self::DOCUMENT_TYPE, $document->id)
            ->whereIn('signatory_slot', $this->activeSignatureSlots($document))
            ->with(['requestedTo', 'electronicSignature'])
            ->orderBy('signing_order')
            ->get();
    }

    public function signatureStatusCards(ProcurementDocument $document): Collection
    {
        $requests = $this->signatureRequests($document)->keyBy('signatory_slot');

        return collect($this->signatureDefinitions($document))
            ->map(function (array $definition) use ($requests, $document) {
                $request = $requests->get($definition['slot']);
                $signature = $request ? $this->signatureRequests->signedSignatureForRequest($request) : null;
                $fallbackSigner = $definition['slot'] === 'prepared_by' ? $this->preparedSigner($document) : $this->responsibleSigner($document);

                return [
                    'request' => $request,
                    'slot' => $definition['slot'],
                    'label' => $definition['label'],
                    'signer_name' => $request?->requestedTo?->name ?? $fallbackSigner?->name ?? 'Unassigned',
                    'signer_user_id' => $request?->requestedTo?->user_id ?? $fallbackSigner?->user_id,
                    'designation' => $request?->requested_to_role ?? $this->positionForUser($fallbackSigner),
                    'status' => $this->requestStatus($request),
                    'status_label' => $this->requestStatusLabel($request),
                    'signed_at' => $signature?->signed_at ?? $request?->signed_at,
                ];
            })
            ->values();
    }

    public function signatureProgress(ProcurementDocument $document): array
    {
        $requests = $this->signatureRequests($document)->where('is_required', true);

        return [
            'required' => $requests->count(),
            'completed' => $requests->where('status', SignatureRequest::STATUS_SIGNED)->count(),
            'pending_labels' => $requests
                ->reject(fn (SignatureRequest $request) => $request->status === SignatureRequest::STATUS_SIGNED)
                ->map(fn (SignatureRequest $request) => $request->signatory_label)
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ];
    }

    public function signedSlots(ProcurementDocument $document): Collection
    {
        return $this->signatureRequests->signedSignaturesForDocument($document, self::DOCUMENT_TYPE);
    }

    public function hasCompletedRequiredSignatures(ProcurementDocument $document): bool
    {
        $requests = SignatureRequest::query()
            ->forDocument(self::DOCUMENT_TYPE, $document->id)
            ->whereIn('signatory_slot', $this->activeSignatureSlots($document))
            ->where('is_required', true)
            ->get();

        return $requests->count() === count($this->signatureDefinitions($document))
            && $requests->every(fn (SignatureRequest $request) => $request->status === SignatureRequest::STATUS_SIGNED);
    }

    private function completeSignatories(ProcurementDocument $document, User $actor): void
    {
        if ($document->status === ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED
            || $document->status === ProcurementDocument::STATUS_PENDING_PPMP_REVIEW) {
            return;
        }

        $document->loadMissing('submittedBy');
        $creator = $document->submittedBy ?: ($document->submitted_by_user_id ? User::find($document->submitted_by_user_id) : null);
        $oldStatus = $document->status;
        $fromOfficeId = $document->current_office_id;
        $ownerOfficeId = $document->submitting_office_id ?: $creator?->office_id ?: $document->current_office_id;

        $document->fill([
            'status' => ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED,
            'stage' => ProcurementDocument::STAGE_PPMP_SIGNATORIES_COMPLETED,
            'current_office_id' => $ownerOfficeId,
            'assigned_to_user_id' => $creator?->id ?: $document->submitted_by_user_id,
            'returned_at' => null,
        ])->save();

        $this->recordRouting(
            $document->refresh(),
            $actor,
            'PPMP Signed',
            $oldStatus,
            ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED,
            'The required PPMP electronic signature is complete. The PPMP is ready for submission to BACSEC-003 for APP consolidation.',
            $fromOfficeId,
            $ownerOfficeId,
        );

        SystemNotificationService::notify(
            $creator,
            'PPMP Signed',
            "The required signature for {$document->tracking_number} is complete. Submit the PPMP to BACSEC-003 for APP consolidation when ready.",
            SystemNotification::TYPE_SUCCESS,
            'PPMP Signatures',
            $document,
            route('head-office.ppmp.show', $document),
        );

        AuditLogger::log('PPMP Signature Workflow', 'ppmp_ready_for_submission', 'Head of Office PPMP completed the required electronic signature and is ready for submission.', $document, ['status' => $oldStatus], [
            'status' => ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED,
            'tracking_number' => $document->tracking_number,
        ]);
    }

    private function notifyCreatorOfSignerCompletion(ProcurementDocument $document, User $actor): void
    {
        $document->loadMissing('submittedBy');
        $creator = $document->submittedBy ?: ($document->submitted_by_user_id ? User::find($document->submitted_by_user_id) : null);

        if (! $creator || (int) $creator->id === (int) $actor->id) {
            return;
        }

        $signedRequest = SignatureRequest::query()
            ->forDocument(self::DOCUMENT_TYPE, $document->id)
            ->whereIn('signatory_slot', $this->activeSignatureSlots($document))
            ->where('requested_to_user_id', $actor->id)
            ->where('status', SignatureRequest::STATUS_SIGNED)
            ->latest('signed_at')
            ->first();

        $progress = $this->signatureProgress($document);
        $tracking = $document->tracking_number ?: 'PPMP #' . $document->id;
        $label = $signedRequest?->signatory_label ?: 'assigned signatory';
        $summary = $progress['required'] > 0
            ? " {$progress['completed']}/{$progress['required']} required signatures are now complete."
            : '';

        SystemNotificationService::notify(
            $creator,
            'PPMP Signature Completed',
            "{$actor->name} signed {$tracking} as {$label}.{$summary}",
            SystemNotification::TYPE_SUCCESS,
            'PPMP Signatures',
            $document,
            route('head-office.ppmp.show', $document),
        );
    }

    private function signerPayload(array $definition, User $signer, ProcurementDocument $document): array
    {
        $position = $this->positionForUser($signer);

        return [
            'slot' => $definition['slot'],
            'label' => $definition['label'],
            'order' => $definition['order'],
            'signing_mode' => $definition['mode'] ?? 'parallel',
            'account_code' => $signer->user_id,
            'role_name' => $position,
            'office_id' => $signer->office_id,
            'required' => true,
            'printed_name' => $signer->name,
            'designation' => $position,
            'ppmp_signature_workflow' => 'head_office',
            'submitting_office_id' => $document->submitting_office_id,
        ];
    }

    private function responsibleSigner(ProcurementDocument $document): ?User
    {
        $submitted = $this->submittedSigner($document);
        $prepared = $this->preparedSigner($document);
        $creator = $submitted ?: $prepared;

        if ($creator && ! $creator->isOfficeDocumentPreparer() && $creator->isOfficeDocumentActor() && $creator->isActive()) {
            return $creator;
        }

        return $this->headSignerForOffice(
            $document->submitting_office_id ?: $creator?->office_id,
        );
    }

    private function signatureDefinitions(ProcurementDocument $document): array
    {
        if ($this->isStaffPrepared($document)) {
            if (in_array($document->status, [ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED, ProcurementDocument::STATUS_PENDING_PPMP_REVIEW, ProcurementDocument::STATUS_UNDER_PPMP_REVIEW, ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION], true)
                && SignatureRequest::query()->forDocument(self::DOCUMENT_TYPE, $document->id)->where('signatory_slot', self::COMBINED_SIGNATURE_SLOT)->where('is_required', true)->where('status', SignatureRequest::STATUS_SIGNED)->exists()
                && ! SignatureRequest::query()->forDocument(self::DOCUMENT_TYPE, $document->id)->whereIn('signatory_slot', self::LEGACY_SPLIT_SIGNATURE_SLOTS)->where('is_required', true)->exists()) {
                return self::SIGNATURE_SLOTS;
            }
            return [
                'prepared_by' => ['slot' => 'prepared_by', 'label' => 'Prepared By', 'order' => 1, 'mode' => 'sequential'],
                'submitted_by' => ['slot' => 'submitted_by', 'label' => 'Submitted By (Head of Office)', 'order' => 2, 'mode' => 'sequential'],
            ];
        }
        return self::SIGNATURE_SLOTS;
    }

    private function isStaffPrepared(ProcurementDocument $document): bool
    {
        return ($this->preparedSigner($document) ?: $this->submittedSigner($document))?->isOfficeDocumentPreparer() ?? false;
    }

    private function requiredSigners(ProcurementDocument $document): array
    {
        $preparedSigned = SignatureRequest::query()->forDocument(self::DOCUMENT_TYPE, $document->id)
            ->where('signatory_slot', 'prepared_by')->where('status', SignatureRequest::STATUS_SIGNED)->exists();
        $signers = [];
        foreach ($this->signatureDefinitions($document) as $definition) {
            $signer = $definition['slot'] === 'prepared_by' ? $this->preparedSigner($document) : $this->responsibleSigner($document);
            if ($signer) {
                $payload = $this->signerPayload($definition, $signer, $document);
                if ($definition['slot'] === 'submitted_by' && ! $preparedSigned) {
                    $payload['initial_status'] = SignatureRequest::STATUS_WAITING;
                }
                $signers[] = $payload;
            }
        }
        return $signers;
    }

    private function activateHeadAfterPreparation(ProcurementDocument $document): void
    {
        if (! $this->isStaffPrepared($document) || ! SignatureRequest::query()->forDocument(self::DOCUMENT_TYPE, $document->id)
            ->where('signatory_slot', 'prepared_by')->where('status', SignatureRequest::STATUS_SIGNED)->where('is_required', true)->exists()) {
            return;
        }
        $requests = SignatureRequest::query()->forDocument(self::DOCUMENT_TYPE, $document->id)
            ->where('signatory_slot', 'submitted_by')->where('status', SignatureRequest::STATUS_WAITING)->get();
        foreach ($requests as $request) {
            $request->forceFill(['status' => SignatureRequest::STATUS_PENDING])->save();
            $this->signatureRequests->notifySigner($request);
            $document->forceFill(['assigned_to_user_id' => $request->requested_to_user_id])->save();
        }
    }

    private function retireObsoleteRequests(ProcurementDocument $document): void
    {
        if (! $document->exists) {
            return;
        }

        SignatureRequest::query()
            ->forDocument(self::DOCUMENT_TYPE, $document->id)
            ->whereIn('signatory_slot', $this->isStaffPrepared($document) ? [self::COMBINED_SIGNATURE_SLOT] : self::LEGACY_SPLIT_SIGNATURE_SLOTS)
            ->where('is_required', true)
            ->get()
            ->each(function (SignatureRequest $request) {
                $updates = [
                    'is_required' => false,
                    'remarks' => $request->remarks ?: 'Replaced by the current PPMP signature requirements.',
                ];

                if ($request->status !== SignatureRequest::STATUS_SIGNED) {
                    $updates['status'] = SignatureRequest::STATUS_SKIPPED;
                    $updates['cancelled_at'] = $request->cancelled_at ?: now();
                }

                $request->forceFill($updates)->save();
            });
    }

    private function preparedSigner(ProcurementDocument $document): ?User
    {
        $document->loadMissing('preparedBy');

        return $document->preparedBy ?: ($document->prepared_by_user_id ? User::find($document->prepared_by_user_id) : null);
    }

    private function submittedSigner(ProcurementDocument $document): ?User
    {
        $document->loadMissing('submittedBy');

        $submitted = $document->submittedBy ?: ($document->submitted_by_user_id ? User::find($document->submitted_by_user_id) : null);

        if ($submitted) {
            return $submitted;
        }

        $officeId = $document->submitting_office_id;

        if (! $officeId) {
            return null;
        }

        return $this->headSignerForOffice($officeId);
    }

    public function headSignerForOffice(?int $officeId): ?User
    {
        if (! $officeId) {
            return null;
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

    private function positionForUser(?User $user): string
    {
        return $user?->signer_position
            ?: $user?->position
            ?: $user?->assignedRole?->name
            ?: $user?->role
            ?: User::ROLE_HEAD_OFFICE;
    }

    private function requestStatus(?SignatureRequest $request): string
    {
        if (! $request) {
            return 'not_generated';
        }

        if ($request->status === SignatureRequest::STATUS_SIGNED) {
            return SignatureRequest::STATUS_SIGNED;
        }

        if ($request->status === SignatureRequest::STATUS_WAITING) {
            return 'waiting';
        }

        if (in_array($request->status, [SignatureRequest::STATUS_DECLINED, SignatureRequest::STATUS_RETURNED], true)) {
            return 'rejected';
        }

        return 'pending';
    }

    private function requestStatusLabel(?SignatureRequest $request): string
    {
        return match ($this->requestStatus($request)) {
            SignatureRequest::STATUS_SIGNED => 'Signed',
            'rejected' => 'Rejected',
            'pending' => 'Pending Signature',
            'waiting' => 'Waiting for Prepared By',
            default => 'Not Generated',
        };
    }

    private function activeSignatureSlots(?ProcurementDocument $document = null): array
    {
        return $document ? array_keys($this->signatureDefinitions($document)) : array_keys(self::SIGNATURE_SLOTS);
    }

    private function generateTrackingNumber(int $year, ?\App\Models\Office $office): string
    {
        $officeCode = $this->trackingOfficeCode($office);
        $prefix = "PPMP-{$year}-{$officeCode}-";

        $lastTrackingNumber = ProcurementDocument::query()
            ->where('document_type', 'PPMP')
            ->where('tracking_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('tracking_number')
            ->value('tracking_number');

        $sequence = $lastTrackingNumber ? ((int) substr($lastTrackingNumber, -4)) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function trackingOfficeCode(?\App\Models\Office $office): string
    {
        $source = $office?->code ?: $office?->name ?: 'OFFICE';
        $code = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', $source));

        return $code !== '' ? $code : 'OFFICE';
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
