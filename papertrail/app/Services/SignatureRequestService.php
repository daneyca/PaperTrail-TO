<?php

namespace App\Services;

use App\Models\AnnualProcurementPlan;
use App\Models\BacResolution;
use App\Models\AbstractQuotation;
use App\Models\ElectronicSignature;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\SignatureRequest;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SignatureRequestService
{
    public function __construct(private readonly NotificationDispatchService $notifications)
    {
    }

    public function normalizeDocumentType(string $documentType): string
    {
        return match (strtolower(str_replace('-', '_', $documentType))) {
            'app', 'annual_procurement_plan', 'annualprocurementplan' => 'app',
            'bac_resolution', 'bacresolution' => 'bac_resolution',
            'ppmp', 'project_procurement_management_plan' => 'ppmp',
            default => strtolower(str_replace('-', '_', $documentType)),
        };
    }

    public function createRequestsForDocument(Model $document, string $documentType, array $signers, ?User $requestedBy): Collection
    {
        $type = $this->normalizeDocumentType($documentType);
        $signingMode = $signers['signing_mode'] ?? config("papertrail_signatures.{$type}.signing_mode", 'parallel');
        $configuredSigners = $signers['signers'] ?? $signers;

        return collect($configuredSigners)
            ->filter(fn ($signer) => is_array($signer))
            ->map(function (array $signer) use ($document, $type, $requestedBy, $signingMode) {
                $signer['signing_mode'] = $signer['signing_mode'] ?? $signingMode;

                return $this->createOrUpdateRequest($document, $type, $signer, $requestedBy);
            })
            ->filter()
            ->values();
    }

    public function createOrUpdateRequest(Model $document, string $documentType, array $signerData, ?User $requestedBy): ?SignatureRequest
    {
        $type = $this->normalizeDocumentType($documentType);
        $slot = $this->canonicalSignatureSlot($signerData['slot'] ?? $signerData['signatory_slot'] ?? null);

        if (! $slot) {
            return null;
        }

        $recipient = $this->resolveSigner($signerData);
        $role = $signerData['role_code']
            ?? $signerData['role_name']
            ?? $signerData['label']
            ?? null;

        $query = SignatureRequest::query()
            ->forDocument($type, $document->getKey())
            ->whereIn('signatory_slot', $this->slotAliases($slot));

        if ($recipient) {
            $query->where(function (Builder $builder) use ($recipient) {
                $builder->where('requested_to_user_id', $recipient->id)
                    ->orWhereIn('status', SignatureRequest::OPEN_STATUSES);
            });
        }

        $signatureRequest = $query->latest('updated_at')->first() ?: new SignatureRequest([
            'request_uuid' => (string) Str::uuid(),
            'status' => SignatureRequest::STATUS_PENDING,
        ]);

        if ($signatureRequest->status === SignatureRequest::STATUS_SIGNED) {
            return $signatureRequest;
        }

        $signatureRequest->fill([
            'document_type' => $type,
            'document_id' => $document->getKey(),
            'document_label' => $this->documentLabel($document),
            'tracking_number' => $this->trackingNumber($document),
            'signatory_slot' => $slot,
            'signatory_label' => $signerData['label'] ?? $signerData['signatory_label'] ?? $this->defaultSlotLabel($slot),
            'signing_order' => (int) ($signerData['order'] ?? $signerData['signing_order'] ?? 1),
            'signing_mode' => $signerData['signing_mode'] ?? 'parallel',
            'is_required' => (bool) ($signerData['required'] ?? $signerData['is_required'] ?? true) && (bool) $recipient,
            'requested_by_user_id' => $requestedBy?->id,
            'requested_to_user_id' => $recipient?->id,
            'requested_to_role' => $role,
            'requested_to_office_id' => $recipient?->office_id ?? ($signerData['office_id'] ?? null),
            'status' => in_array($signatureRequest->status, SignatureRequest::OPEN_STATUSES, true)
                ? $signatureRequest->status
                : SignatureRequest::STATUS_PENDING,
            'metadata' => [
                ...($signatureRequest->metadata ?: []),
                'account_code' => $signerData['account_code'] ?? null,
                'office_code' => $signerData['office_code'] ?? null,
                'office_names' => $signerData['office_names'] ?? null,
                'role_code' => $signerData['role_code'] ?? null,
                'role_name' => $signerData['role_name'] ?? null,
                'printed_name' => $signerData['printed_name'] ?? null,
                'designation' => $signerData['designation'] ?? null,
                'signer_position' => $signerData['signer_position'] ?? null,
                'ppmp_signature_workflow' => $signerData['ppmp_signature_workflow'] ?? null,
                'submitting_office_id' => $signerData['submitting_office_id'] ?? null,
            ],
        ]);

        $signatureRequest->save();

        AuditLogger::signature('signature_request_created', $document, [
            'description' => 'Signature request created or refreshed.',
            'metadata' => [
                'signature_request_id' => $signatureRequest->id,
                'signatory_slot' => $slot,
                'signatory_label' => $signatureRequest->signatory_label,
                'requested_to_user_id' => $recipient?->user_id,
            ],
        ]);

        if (! $signatureRequest->notification_sent_at && $signatureRequest->requested_to_user_id) {
            $this->notifySigner($signatureRequest);
        } elseif (! $signatureRequest->requested_to_user_id) {
            AuditLogger::signature('signature_request_notification_skipped', $document, [
                'description' => 'No active user account is assigned to this signature request.',
                'severity' => $signatureRequest->is_required ? 'warning' : 'info',
                'metadata' => [
                    'signature_request_id' => $signatureRequest->id,
                    'signatory_slot' => $slot,
                    'requested_to_role' => $role,
                ],
            ]);
        }

        $this->setDocumentSignatureStatus($document, 'pending_signatures');

        return $signatureRequest->fresh(['requestedBy', 'requestedTo', 'requestedOffice']);
    }

    public function notifySigner(SignatureRequest $signatureRequest): void
    {
        $signatureRequest->loadMissing(['requestedTo', 'requestedBy']);
        $recipient = $signatureRequest->requestedTo;

        if (! $recipient) {
            return;
        }

        try {
            $url = Route::has('signature-requests.show')
                ? route('signature-requests.show', $signatureRequest)
                : null;

            $this->notifications->notifyUser(
                $recipient,
                'Document Requires Your Signature',
                "{$signatureRequest->document_label} has been routed to you as {$signatureRequest->signatory_label} for electronic signature.",
                $url,
                [
                    'event' => 'signature_request_created',
                    'action_text' => 'Review & Sign',
                    'document_type' => $this->humanDocumentType($signatureRequest->document_type),
                    'document_id' => $signatureRequest->document_id,
                    'tracking_number' => $signatureRequest->tracking_number,
                    'status' => $signatureRequest->status,
                    'signature_request_id' => $signatureRequest->id,
                    'signatory_slot' => $signatureRequest->signatory_slot,
                    'signatory_label' => $signatureRequest->signatory_label,
                ],
                SystemNotification::TYPE_INFO,
                'E-Signature',
                $signatureRequest,
                'Review & Sign',
            );

            $signatureRequest->forceFill([
                'status' => $signatureRequest->status === SignatureRequest::STATUS_PENDING
                    ? SignatureRequest::STATUS_NOTIFIED
                    : $signatureRequest->status,
                'notification_sent_at' => now(),
                'email_sent_at' => filled($recipient->email) ? now() : null,
            ])->save();

            AuditLogger::signature('signature_request_notification_sent', $signatureRequest, [
                'description' => 'Signature request notification dispatched.',
                'metadata' => [
                    'recipient_user_id' => $recipient->user_id,
                    'signature_request_id' => $signatureRequest->id,
                ],
            ]);
        } catch (Throwable $exception) {
            AuditLogger::signature('signature_request_notification_failed', $signatureRequest, [
                'description' => 'Signature request notification failed.',
                'severity' => 'warning',
                'metadata' => [
                    'signature_request_id' => $signatureRequest->id,
                    'error' => $exception->getMessage(),
                ],
            ]);
        }
    }

    public function getPendingRequestsForUser(User $user): Builder
    {
        return $this->requestsForUser($user)->open();
    }

    public function requestsForUser(User $user): Builder
    {
        $roleTokens = $this->roleTokens($user);
        $canUseOfficeFallback = $user->office_id && ($user->hasPermission('esignature.use') || $user->hasSignatureProfile());

        return SignatureRequest::query()
            ->with(['requestedBy', 'requestedTo', 'requestedOffice', 'electronicSignature'])
            ->where(function (Builder $query) use ($user, $roleTokens, $canUseOfficeFallback) {
                $query->where('requested_to_user_id', $user->id);

                if ($roleTokens !== [] || $canUseOfficeFallback) {
                    $query->orWhere(function (Builder $fallback) use ($user, $roleTokens, $canUseOfficeFallback) {
                        $fallback->whereNull('requested_to_user_id')
                            ->whereIn('status', SignatureRequest::OPEN_STATUSES)
                            ->where(function (Builder $assignment) use ($user, $roleTokens, $canUseOfficeFallback) {
                                if ($roleTokens !== []) {
                                    $assignment->whereIn('requested_to_role', $roleTokens);
                                }

                                if ($canUseOfficeFallback) {
                                    $assignment->orWhere(function (Builder $officeQuery) use ($user, $roleTokens) {
                                        $officeQuery->where('requested_to_office_id', $user->office_id)
                                            ->where(function (Builder $roleQuery) use ($roleTokens) {
                                                $roleQuery->whereNull('requested_to_role');

                                                if ($roleTokens !== []) {
                                                    $roleQuery->orWhereIn('requested_to_role', $roleTokens);
                                                }
                                            });
                                    });
                                }
                            });
                    });
                }
            });
    }

    public function userCanAccess(SignatureRequest $signatureRequest, User $user): bool
    {
        if ((int) $signatureRequest->requested_to_user_id === (int) $user->id) {
            return true;
        }

        if ($signatureRequest->requested_to_user_id) {
            return false;
        }

        if (! in_array($signatureRequest->status, SignatureRequest::OPEN_STATUSES, true)) {
            return false;
        }

        $roleTokens = $this->roleTokens($user);

        if ($signatureRequest->requested_to_role && in_array($signatureRequest->requested_to_role, $roleTokens, true)) {
            return true;
        }

        return $user->office_id
            && (int) $signatureRequest->requested_to_office_id === (int) $user->office_id
            && ($user->hasPermission('esignature.use') || $user->hasSignatureProfile())
            && (! $signatureRequest->requested_to_role || in_array($signatureRequest->requested_to_role, $roleTokens, true));
    }

    public function markViewed(SignatureRequest $signatureRequest, User $user): void
    {
        if (! $signatureRequest->viewed_at) {
            $signatureRequest->forceFill([
                'status' => in_array($signatureRequest->status, [SignatureRequest::STATUS_PENDING, SignatureRequest::STATUS_NOTIFIED], true)
                    ? SignatureRequest::STATUS_VIEWED
                    : $signatureRequest->status,
                'viewed_at' => now(),
            ])->save();

            AuditLogger::signature('signature_request_viewed', $signatureRequest, [
                'description' => 'Signature request viewed.',
                'metadata' => [
                    'signature_request_id' => $signatureRequest->id,
                    'viewer_user_id' => $user->user_id,
                ],
            ]);
        }
    }

    public function markSigned(SignatureRequest $signatureRequest, ElectronicSignature $electronicSignature): void
    {
        $signatureRequest->forceFill([
            'status' => SignatureRequest::STATUS_SIGNED,
            'signed_at' => $electronicSignature->signed_at ?: now(),
            'remarks' => $electronicSignature->metadata['remarks'] ?? $signatureRequest->remarks,
        ])->save();
    }

    public function markDeclined(SignatureRequest $signatureRequest, string $remarks): void
    {
        $signatureRequest->forceFill([
            'status' => SignatureRequest::STATUS_DECLINED,
            'declined_at' => now(),
            'remarks' => $remarks,
        ])->save();
    }

    public function openRequestForUser(Model $document, string $documentType, User $user, ?SignatureRequest $explicitRequest = null): ?SignatureRequest
    {
        $type = $this->normalizeDocumentType($documentType);

        if ($explicitRequest
            && $this->userCanAccess($explicitRequest, $user)
            && $explicitRequest->document_type === $type
            && (int) $explicitRequest->document_id === (int) $document->getKey()
            && $explicitRequest->isOpen()) {
            return $explicitRequest;
        }

        return $this->getPendingRequestsForUser($user)
            ->forDocument($type, $document->getKey())
            ->orderBy('signing_order')
            ->first();
    }

    public function signatureForRequest(SignatureRequest $signatureRequest): ?ElectronicSignature
    {
        return ElectronicSignature::query()
            ->where('signature_request_id', $signatureRequest->id)
            ->latest('updated_at')
            ->first();
    }

    public function signedSignatureForRequest(SignatureRequest $signatureRequest): ?ElectronicSignature
    {
        return ElectronicSignature::query()
            ->where('signature_request_id', $signatureRequest->id)
            ->where('signature_status', ElectronicSignature::STATUS_SIGNED)
            ->latest('signed_at')
            ->first();
    }

    public function signedSignaturesForDocument(Model $document, string $documentType): Collection
    {
        $type = $this->normalizeDocumentType($documentType);
        $signatures = ElectronicSignature::query()
            ->forDocument($type, $document->getKey())
            ->where('signature_status', ElectronicSignature::STATUS_SIGNED)
            ->latest('signed_at')
            ->get();

        $bySlot = collect();

        foreach ($signatures as $signature) {
            if (! filled($signature->signatory_slot)) {
                continue;
            }

            foreach ($this->slotAliases($signature->signatory_slot) as $slot) {
                if (! $bySlot->has($slot)) {
                    $bySlot->put($slot, $signature);
                }
            }
        }

        if (! $bySlot->has('chairperson')) {
            $chairSignature = $signatures->first(fn (ElectronicSignature $signature) => $this->roleLooksLike($signature->signer_role, ['BAC Chair', 'bac_chair']));

            if ($chairSignature) {
                $bySlot->put('chairperson', $chairSignature);
            }
        }

        return $bySlot;
    }

    public function documentHasRequiredSignatures(Model $document, string $documentType): bool
    {
        $type = $this->normalizeDocumentType($documentType);
        $requiredCount = SignatureRequest::query()
            ->forDocument($type, $document->getKey())
            ->where('is_required', true)
            ->count();

        if ($requiredCount === 0) {
            return false;
        }

        $unsignedCount = SignatureRequest::query()
            ->forDocument($type, $document->getKey())
            ->where('is_required', true)
            ->where('status', '!=', SignatureRequest::STATUS_SIGNED)
            ->count();

        if ($unsignedCount === 0) {
            $this->setDocumentSignatureStatus($document, 'fully_signed');

            AuditLogger::signature('document_fully_signed', $document, [
                'description' => 'All required signature requests have been signed.',
                'metadata' => [
                    'document_type' => $type,
                    'document_id' => $document->getKey(),
                ],
            ]);
        }

        return $unsignedCount === 0;
    }

    private function resolveSigner(array $signerData): ?User
    {
        $accountCode = $signerData['account_code'] ?? $signerData['user_id'] ?? null;
        $roleCode = $signerData['role_code'] ?? null;
        $roleName = $signerData['role_name'] ?? $signerData['label'] ?? null;
        $officeCode = $signerData['office_code'] ?? null;
        $officeNames = collect($signerData['office_names'] ?? [])->filter()->values()->all();
        $officeId = null;

        if ($officeCode || $officeNames !== []) {
            $officeId = Office::query()
                ->where(function (Builder $query) use ($officeCode, $officeNames) {
                    if ($officeCode) {
                        $query->orWhere('code', $officeCode);
                    }

                    if ($officeNames !== []) {
                        $query->orWhereIn('name', $officeNames);
                    }
                })
                ->value('id');
        }

        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->where(function (Builder $query) use ($accountCode, $roleCode, $roleName, $officeId) {
                if ($accountCode) {
                    $query->orWhere('user_id', $accountCode);
                }

                if ($roleCode) {
                    $query->orWhereHas('assignedRole', fn (Builder $role) => $role->where('code', $roleCode))
                        ->orWhere('role', $roleCode);
                }

                if ($roleName) {
                    $query->orWhereHas('assignedRole', fn (Builder $role) => $role->where('name', $roleName))
                        ->orWhere('role', $roleName);
                }

                if ($officeId) {
                    $query->orWhere('office_id', $officeId);
                }
            })
            ->orderByRaw($accountCode ? 'CASE WHEN user_id = ? THEN 0 ELSE 1 END' : 'id', $accountCode ? [$accountCode] : [])
            ->first();
    }

    private function roleTokens(User $user): array
    {
        $user->loadMissing('assignedRole');

        return collect([
            $user->role,
            $user->assignedRole?->name,
            $user->assignedRole?->code,
            $user->roleSlug(),
            str_replace('-', '_', $user->roleSlug()),
        ])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function setDocumentSignatureStatus(Model $document, string $status): void
    {
        if ($document instanceof BacResolution && Schema::hasColumn('bac_resolutions', 'signature_status')) {
            $document->forceFill(['signature_status' => $status])->save();
        }
    }

    private function documentLabel(Model $document): string
    {
        if ($document instanceof AbstractQuotation) {
            return 'Abstract ' . $document->displayNumber();
        }

        if ($document instanceof BacResolution) {
            return 'BAC Resolution ' . $document->displayNumber();
        }

        if ($document instanceof AnnualProcurementPlan) {
            return 'APP ' . $document->displayNumber();
        }

        if ($document instanceof ProcurementDocument) {
            $type = match (true) {
                in_array($document->document_type, ['PR', 'Purchase Request'], true) => 'Purchase Request',
                $document->document_type === 'PPMP' => 'PPMP',
                default => $document->document_type ?: 'Procurement Document',
            };

            return $type . ' ' . $document->displayNumber();
        }

        return class_basename($document) . ' #' . $document->getKey();
    }

    private function trackingNumber(Model $document): ?string
    {
        if ($document instanceof AbstractQuotation) {
            return $document->displayNumber();
        }

        if ($document instanceof BacResolution) {
            return $document->displayNumber();
        }

        if ($document instanceof AnnualProcurementPlan) {
            return $document->displayNumber();
        }

        if ($document instanceof ProcurementDocument) {
            return $document->displayNumber();
        }

        return $document->tracking_number ?? $document->pr_no ?? null;
    }

    private function humanDocumentType(?string $documentType): ?string
    {
        return $documentType ? str($documentType)->replace('_', ' ')->title()->replace('Ppmp', 'PPMP')->toString() : null;
    }

    private function defaultSlotLabel(string $slot): string
    {
        return str($slot)->replace('_', ' ')->title()->toString();
    }

    private function canonicalSignatureSlot(?string $slot): ?string
    {
        if (! $slot) {
            return null;
        }

        return match (strtolower(str_replace('-', '_', $slot))) {
            'vice_chairperson', 'bac_vice', 'bac_vice_chairperson' => 'bac_vice_chairperson',
            'bac_member', 'member_one', 'bac_member_1' => 'bac_member_1',
            'member_two', 'bac_member_2' => 'bac_member_2',
            'provisional_member', 'bac_provisional_member' => 'bac_provisional_member',
            'chairperson', 'bac_chair', 'bac_chairperson' => 'bac_chairperson',
            'approving_authority', 'hope', 'local_chief_executive' => 'hope',
            default => strtolower(str_replace('-', '_', $slot)),
        };
    }

    private function documentSlotFor(?string $slot): ?string
    {
        return match ($this->canonicalSignatureSlot($slot)) {
            'bac_vice_chairperson' => 'vice_chairperson',
            'bac_member_1' => 'member_one',
            'bac_member_2' => 'member_two',
            'bac_provisional_member' => 'provisional_member',
            'bac_chairperson' => 'chairperson',
            'hope' => 'hope',
            default => $slot,
        };
    }

    private function slotAliases(?string $slot): array
    {
        $canonical = $this->canonicalSignatureSlot($slot);
        $documentSlot = $this->documentSlotFor($canonical);

        return collect([$slot, $canonical, $documentSlot])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function roleLooksLike(?string $role, array $needles): bool
    {
        $role = strtolower((string) $role);

        foreach ($needles as $needle) {
            if (str_contains($role, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}
