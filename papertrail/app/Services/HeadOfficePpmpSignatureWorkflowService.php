<?php

namespace App\Services;

use App\Models\DocumentRoutingHistory;
use App\Models\ProcurementDocument;
use App\Models\SignatureRequest;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class HeadOfficePpmpSignatureWorkflowService
{
    private const DOCUMENT_TYPE = 'ppmp';
    private const COMBINED_SIGNATURE_SLOT = 'prepared_and_submitted_by';
    private const LEGACY_SPLIT_SIGNATURE_SLOTS = ['prepared_by', 'submitted_by'];
    private const EXCLUDED_USER_IDS = ['BACSEC-002'];

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
            && ($creator->hasRole('head_office') || $creator->hasRole(User::ROLE_HEAD_OFFICE));
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

    public function ensureSingleSignatureRequirement(ProcurementDocument $document, ?User $actor = null): void
    {
        if (! $document->exists || $document->document_type !== 'PPMP') {
            return;
        }

        if (! $this->isWorkflowDocument($document) && ! $this->hasSignatureRouting($document)) {
            return;
        }

        $this->retireLegacySplitRequests($document);

        if ($document->status !== ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES) {
            return;
        }

        $hasActiveRequest = SignatureRequest::query()
            ->forDocument(self::DOCUMENT_TYPE, $document->id)
            ->whereIn('signatory_slot', $this->activeSignatureSlots())
            ->exists();

        if ($hasActiveRequest) {
            return;
        }

        $signer = $this->responsibleSigner($document) ?: $actor;

        if (! $signer) {
            return;
        }

        $this->signatureRequests->createRequestsForDocument(
            $document->refresh(),
            self::DOCUMENT_TYPE,
            [
                'signing_mode' => 'parallel',
                'signers' => [
                    $this->signerPayload(self::SIGNATURE_SLOTS[self::COMBINED_SIGNATURE_SLOT], $signer, $document),
                ],
            ],
            $actor ?: $signer,
        );
    }

    public function canStartFor(User $actor, ProcurementDocument $document): bool
    {
        return $this->isWorkflowDocument($document)
            && ! in_array($actor->user_id, self::EXCLUDED_USER_IDS, true)
            && ($actor->hasRole('head_office') || $actor->hasRole(User::ROLE_HEAD_OFFICE))
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
            && ($actor->hasRole('head_office') || $actor->hasRole(User::ROLE_HEAD_OFFICE))
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
        $oldStatus = $document->status;
        $fromOfficeId = $document->current_office_id;
        $officeId = $document->submitting_office_id ?: $actor->office_id;

        $document->fill([
            'status' => ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES,
            'stage' => ProcurementDocument::STAGE_PPMP_SIGNATURE_WORKFLOW,
            'returned_at' => null,
            'current_office_id' => $officeId,
            'assigned_to_user_id' => $document->submitted_by_user_id ?: $actor->id,
        ])->save();

        $this->retireLegacySplitRequests($document->refresh());

        $requests = $this->signatureRequests->createRequestsForDocument(
            $document->refresh(),
            self::DOCUMENT_TYPE,
            [
                'signing_mode' => 'parallel',
                'signers' => $this->signersFor($document->refresh(), $actor),
            ],
            $actor,
        );

        $this->recordRouting(
            $document->refresh(),
            $actor,
            'PPMP Signature Requests Generated',
            $oldStatus,
            ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES,
            'Prepared and Submitted By electronic signature request was generated.',
            $fromOfficeId,
            $document->current_office_id,
        );

        AuditLogger::log('PPMP Signature Workflow', 'ppmp_signature_requests_generated', 'Head of Office PPMP electronic signature requests were generated.', $document, ['status' => $oldStatus], [
            'status' => ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES,
            'signature_request_count' => $requests->count(),
            'signing_mode' => 'parallel',
        ]);

        return $requests;
    }

    public function handleSigned(ProcurementDocument $document, User $actor, bool $fullySigned): string
    {
        if (! $this->isWorkflowDocument($document) && ! $this->hasSignatureRouting($document)) {
            return 'ignored';
        }

        $this->retireLegacySplitRequests($document);

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
            ->whereIn('signatory_slot', $this->activeSignatureSlots())
            ->with(['requestedTo', 'electronicSignature'])
            ->orderBy('signing_order')
            ->get();
    }

    public function signatureStatusCards(ProcurementDocument $document): Collection
    {
        $requests = $this->signatureRequests($document)->keyBy('signatory_slot');

        return collect(self::SIGNATURE_SLOTS)
            ->map(function (array $definition) use ($requests, $document) {
                $request = $requests->get($definition['slot']);
                $signature = $request ? $this->signatureRequests->signedSignatureForRequest($request) : null;
                $fallbackSigner = $this->responsibleSigner($document);

                return [
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
            ->whereIn('signatory_slot', $this->activeSignatureSlots())
            ->where('is_required', true)
            ->get();

        return $requests->isNotEmpty()
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
            'The required PPMP electronic signature is complete. The PPMP is ready for submission to BACSEC-004 for APP consolidation.',
            $fromOfficeId,
            $ownerOfficeId,
        );

        SystemNotificationService::notify(
            $creator,
            'PPMP Signed',
            "The required signature for {$document->tracking_number} is complete. Submit the PPMP to BACSEC-004 for APP consolidation when ready.",
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
            ->whereIn('signatory_slot', $this->activeSignatureSlots())
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

    private function signersFor(ProcurementDocument $document, User $actor): array
    {
        $signer = $this->responsibleSigner($document) ?: $actor;

        return [
            $this->signerPayload(self::SIGNATURE_SLOTS[self::COMBINED_SIGNATURE_SLOT], $signer, $document),
        ];
    }

    private function signerPayload(array $definition, User $signer, ProcurementDocument $document): array
    {
        $position = $this->positionForUser($signer);

        return [
            'slot' => $definition['slot'],
            'label' => $definition['label'],
            'order' => $definition['order'],
            'signing_mode' => 'parallel',
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
        return $this->submittedSigner($document) ?: $this->preparedSigner($document);
    }

    private function retireLegacySplitRequests(ProcurementDocument $document): void
    {
        if (! $document->exists) {
            return;
        }

        SignatureRequest::query()
            ->forDocument(self::DOCUMENT_TYPE, $document->id)
            ->whereIn('signatory_slot', self::LEGACY_SPLIT_SIGNATURE_SLOTS)
            ->get()
            ->each(function (SignatureRequest $request) {
                $updates = [
                    'is_required' => false,
                    'remarks' => $request->remarks ?: 'Replaced by the single Prepared and Submitted By PPMP signature requirement.',
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
            default => 'Not Generated',
        };
    }

    private function activeSignatureSlots(): array
    {
        return array_keys(self::SIGNATURE_SLOTS);
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
