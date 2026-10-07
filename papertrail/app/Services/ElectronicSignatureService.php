<?php

namespace App\Services;

use App\Models\AnnualProcurementPlan;
use App\Models\BacResolution;
use App\Models\AbstractQuotation;
use App\Models\DocumentRoutingHistory;
use App\Models\ElectronicSignature;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\SignatureRequest;
use App\Models\SignedDocumentSnapshot;
use App\Models\SystemNotification;
use App\Models\User;
use App\Notifications\SigningCodeNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ElectronicSignatureService
{
    public function normalizeDocumentType(string $documentType): string
    {
        return match (strtolower(str_replace('-', '_', $documentType))) {
            'app', 'annual_procurement_plan', 'annualprocurementplan' => 'app',
            'bac_resolution', 'bacresolution' => 'bac_resolution',
            'ppmp', 'project_procurement_management_plan' => 'ppmp',
            default => strtolower(str_replace('-', '_', $documentType)),
        };
    }

    public function findDocument(string $documentType, int|string $documentId): ?Model
    {
        return match ($this->normalizeDocumentType($documentType)) {
            'abstract' => AbstractQuotation::find($documentId),
            'app' => AnnualProcurementPlan::find($documentId),
            'bac_resolution' => BacResolution::find($documentId),
            'purchase_request' => ProcurementDocument::query()
                ->whereIn('document_type', ['PR', 'Purchase Request'])
                ->find($documentId),
            'ppmp' => ProcurementDocument::query()
                ->where('document_type', 'PPMP')
                ->find($documentId),
            default => null,
        };
    }

    public function ensureSignatureProfile(User $user): bool
    {
        return filled($user->typed_signature_name) && filled($user->signer_position);
    }

    public function canSign(User $user, Model $document, string $documentType, string $action, ?SignatureRequest $signatureRequest = null): bool
    {
        return $this->canSignWithReason($user, $document, $documentType, $action, $signatureRequest)['ok'];
    }

    public function canSignWithReason(User $user, Model $document, string $documentType, string $action, ?SignatureRequest $signatureRequest = null): array
    {
        $type = $this->normalizeDocumentType($documentType);

        if ($type === 'purchase_request' && $document instanceof ProcurementDocument) {
            return $this->canSignPurchaseRequestWithReason($user, $document, $type, $signatureRequest);
        }

        if ($type === 'ppmp' && $document instanceof ProcurementDocument) {
            return $this->canSignPpmpWithReason($user, $document, $type, $signatureRequest);
        }

        if ($type === 'abstract' && $document instanceof AbstractQuotation) {
            return $this->canSignAbstractWithReason($user, $document, $type, $signatureRequest);
        }

        if ($type === 'app' && $document instanceof AnnualProcurementPlan) {
            return $this->canSignAppWithReason($user, $document, $type, $signatureRequest);
        }

        if ($type !== 'bac_resolution' || ! $document instanceof BacResolution) {
            return ['ok' => false, 'message' => 'This document type is not configured for e-signature yet.'];
        }

        if ($document->status !== BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR) {
            return ['ok' => false, 'message' => 'Only BAC Resolutions submitted for signatures can be signed.'];
        }

        $requestForUser = app(SignatureRequestService::class)->openRequestForUser($document, $type, $user, $signatureRequest);

        if ($requestForUser) {
            if (app(SignatureRequestService::class)->signedSignatureForRequest($requestForUser)) {
                return ['ok' => false, 'message' => 'Your assigned signature request has already been signed.'];
            }

            return ['ok' => true, 'message' => 'This document can be signed.', 'signature_request' => $requestForUser];
        }

        if (! $this->isBacChairSigner($user)) {
            return ['ok' => false, 'message' => 'Only assigned signers can sign this BAC Resolution.'];
        }

        if ($this->signedSignatureFor($document, $type, $action)) {
            return ['ok' => false, 'message' => 'This BAC Resolution has already been electronically signed.'];
        }

        return ['ok' => true, 'message' => 'This document can be signed.'];
    }

    public function sendSigningCode(User $user, Model $document, string $documentType, string $action, ?SignatureRequest $signatureRequest = null): array
    {
        $type = $this->normalizeDocumentType($documentType);
        $check = $this->canSignWithReason($user, $document, $type, $action, $signatureRequest);
        $signatureRequest = $check['signature_request'] ?? $signatureRequest;

        if (! $check['ok']) {
            AuditLogger::signature('unauthorized_signature_attempt', $document, [
                'description' => $check['message'],
                'severity' => 'warning',
            ]);

            return $check;
        }

        if (! $this->ensureSignatureProfile($user)) {
            return [
                'ok' => false,
                'message' => 'Please complete your E-Signature Setup in your Profile before requesting a signing code.',
            ];
        }

        if (! filled($user->email)) {
            return [
                'ok' => false,
                'message' => 'Please add an official email address before requesting a signing code.',
            ];
        }

        $code = (string) random_int(100000, 999999);
        $signature = $this->pendingSignatureFor($document, $type, $action, $user, $signatureRequest) ?: new ElectronicSignature();

        $signature->fill([
            ...$this->signatureIdentityPayload($document, $type, $action, $user),
            ...$this->signatureRequestPayload($signatureRequest),
            'signature_status' => ElectronicSignature::STATUS_PENDING,
            'signing_code_hash' => Hash::make($code),
            'signing_code_sent_at' => now(),
            'signing_code_expires_at' => now()->addMinutes(10),
            'signing_code_verified_at' => null,
            'signing_attempts' => 0,
            'document_hash_before' => $this->computeDocumentHash($document),
            'metadata' => [
                'action_label' => $this->actionLabel($action),
                'signature_request_id' => $signatureRequest?->id,
                'signatory_slot' => $signatureRequest?->signatory_slot,
                'signatory_label' => $signatureRequest?->signatory_label,
            ],
        ]);

        if (! $signature->signature_uuid) {
            $signature->signature_uuid = (string) Str::uuid();
        }

        if (! $signature->signature_code) {
            $signature->signature_code = $this->generateSignatureCode();
        }

        $signature->save();

        try {
            $user->notify(new SigningCodeNotification(
                code: $code,
                documentLabel: $this->documentLabel($document),
                actionLabel: $this->actionLabel($action),
            ));

            AuditLogger::signature('signing_code_sent', $document, [
                'description' => 'Signing code sent to allowed signer.',
                'metadata' => [
                    'signature_id' => $signature->signature_code,
                    'signer_user_id' => $user->user_id,
                ],
            ]);

            return [
                'ok' => true,
                'message' => 'Signing code sent to your official email address.',
                'signature' => $signature,
            ];
        } catch (Throwable $exception) {
            $signature->update([
                'signing_code_hash' => null,
                'signing_code_sent_at' => null,
                'signing_code_expires_at' => null,
                'signing_attempts' => 0,
            ]);

            Log::error('PaperTrail signing code email failed.', [
                'signature_id' => $signature->id,
                'signer_user_id' => $user->id,
                'exception' => $exception->getMessage(),
            ]);

            AuditLogger::signature('signing_code_failed', $document, [
                'description' => 'Signing code email failed.',
                'severity' => 'warning',
                'metadata' => [
                    'signature_id' => $signature->signature_code,
                    'error' => $exception->getMessage(),
                ],
            ]);

            return [
                'ok' => false,
                'message' => 'Unable to send signing code. Please contact the administrator.',
            ];
        }
    }

    public function verifySigningCode(ElectronicSignature $signature, string $inputCode): array
    {
        if (! $signature->signing_code_hash) {
            return ['ok' => false, 'message' => 'Please request a signing code first.'];
        }

        if (! $signature->signing_code_expires_at || $signature->signing_code_expires_at->isPast()) {
            $signature->update([
                'signing_code_hash' => null,
                'signing_code_expires_at' => null,
            ]);

            return ['ok' => false, 'message' => 'Signing code expired. Please request a new code.'];
        }

        if ($signature->signing_attempts >= 5) {
            $signature->update([
                'signing_code_hash' => null,
                'signing_code_expires_at' => null,
            ]);

            return ['ok' => false, 'message' => 'Too many invalid attempts. Please request a new signing code.'];
        }

        if (! Hash::check($inputCode, $signature->signing_code_hash)) {
            $signature->increment('signing_attempts');

            AuditLogger::signature('signing_code_invalid_attempt', $signature, [
                'description' => 'Invalid signing code attempt.',
                'severity' => 'warning',
                'metadata' => ['signature_id' => $signature->signature_code],
            ]);

            return ['ok' => false, 'message' => 'Invalid signing code. Please try again.'];
        }

        $signature->update(['signing_code_verified_at' => now()]);

        AuditLogger::signature('signing_code_verified', $signature, [
            'description' => 'Signing code verified.',
            'metadata' => ['signature_id' => $signature->signature_code],
        ]);

        return ['ok' => true, 'message' => 'Signing code verified.'];
    }

    public function signDocument(Model $document, User $user, string $documentType, string $action, array $input, ?Request $request = null, ?SignatureRequest $signatureRequest = null): array
    {
        $type = $this->normalizeDocumentType($documentType);
        $check = $this->canSignWithReason($user, $document, $type, $action, $signatureRequest);
        $signatureRequest = $check['signature_request'] ?? $signatureRequest;

        if (! $check['ok']) {
            AuditLogger::signature('unauthorized_signature_attempt', $document, [
                'description' => $check['message'],
                'severity' => 'warning',
            ]);

            return $check;
        }

        if (! $this->ensureSignatureProfile($user)) {
            return [
                'ok' => false,
                'message' => 'Please complete your E-Signature Setup in your Profile before signing.',
            ];
        }

        if (! Hash::check((string) ($input['current_password'] ?? ''), $user->password)) {
            AuditLogger::signature('signature_password_failed', $document, [
                'description' => 'Signer entered an incorrect password.',
                'severity' => 'warning',
            ]);

            return ['ok' => false, 'message' => 'The current password is incorrect.'];
        }

        if (empty($input['signature_consent'])) {
            return ['ok' => false, 'message' => 'Please confirm the electronic signature consent before signing.'];
        }

        $signature = $this->pendingSignatureFor($document, $type, $action, $user, $signatureRequest);

        if (! $signature) {
            return ['ok' => false, 'message' => 'Please send a signing code before confirming the signature.'];
        }

        $verification = $this->verifySigningCode($signature, (string) ($input['signing_code'] ?? ''));

        if (! $verification['ok']) {
            return $verification;
        }

        return DB::transaction(function () use ($document, $user, $type, $action, $input, $request, $signature, $signatureRequest) {
            $document->refresh();
            $documentHash = $this->computeDocumentHash($document);
            $consent = $input['consent_text']
                ?? 'I confirm that I have reviewed this document and I approve/sign it electronically in PaperTrail.';
            $isPurchaseRequestSignature = $type === 'purchase_request' && $signatureRequest;
            $isPpmpSignature = $type === 'ppmp' && $signatureRequest;
            $isRoutedDocumentSignature = in_array($type, ['purchase_request', 'bac_resolution', 'ppmp', 'abstract', 'app'], true) && $signatureRequest;
            $signerPosition = $isRoutedDocumentSignature
                ? ($signatureRequest->requested_to_role ?: $signatureRequest->signatory_label ?: ($user->signer_position ?: ($user->position ?: $user->role)))
                : ($user->signer_position ?: ($user->position ?: $user->role));
            $requestMetadata = $signatureRequest?->metadata ?: [];
            $typedSignatureName = $user->typed_signature_name ?: $user->name;

            if ($isPurchaseRequestSignature && filled($requestMetadata['printed_name'] ?? null)) {
                $typedSignatureName = $requestMetadata['printed_name'];
            }

            $signature->refresh();
            $signature->update([
                ...$this->signatureIdentityPayload($document, $type, $action, $user),
                ...$this->signatureRequestPayload($signatureRequest),
                'signature_status' => ElectronicSignature::STATUS_SIGNED,
                'consent_text' => $consent,
                'typed_signature_name' => $typedSignatureName,
                'signer_position' => $signerPosition,
                'signature_image_path' => $user->signature_image_path,
                'password_confirmed_at' => now(),
                'document_hash_before' => $documentHash,
                'signed_at' => now(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'metadata' => [
                    ...($signature->metadata ?: []),
                    'remarks' => $input['remarks'] ?? null,
                    'signature_request_id' => $signatureRequest?->id,
                    'signatory_slot' => $signatureRequest?->signatory_slot,
                    'signatory_label' => $signatureRequest?->signatory_label,
                    'signatory_designation' => $signatureRequest?->requested_to_role,
                    'assigned_account_user_id' => $signatureRequest?->requestedTo?->user_id,
                    'pr_signatory_column' => $requestMetadata['pr_signatory_column'] ?? null,
                    'ppmp_signature_workflow' => $requestMetadata['ppmp_signature_workflow'] ?? null,
                    'printed_name' => $requestMetadata['printed_name'] ?? null,
                ],
            ]);

            $snapshot = $this->createSignedSnapshot($document, $signature, $user);
            $signature->update(['signed_snapshot_hash' => $snapshot->snapshot_hash]);

            $fullySigned = true;

            if ($signatureRequest) {
                app(SignatureRequestService::class)->markSigned($signatureRequest, $signature);
                $fullySigned = app(SignatureRequestService::class)->documentHasRequiredSignatures($document, $type);
            }

            $prSignatureResult = 'ignored';
            $ppmpSignatureResult = 'ignored';

            if ($type === 'purchase_request' && $document instanceof ProcurementDocument) {
                $bacsec002PrRouting = app(Bacsec002PrSignatoryRoutingService::class);
                $prSignatureResult = $bacsec002PrRouting->isPilotDocument($document)
                    ? $bacsec002PrRouting->handleSigned($document, $user, $fullySigned)
                    : app(EndUserPrSignatoryRoutingService::class)->handleSigned($document, $user, $fullySigned);
            } elseif ($type === 'ppmp' && $document instanceof ProcurementDocument) {
                $ppmpSignatureResult = app(HeadOfficePpmpSignatureWorkflowService::class)->handleSigned($document, $user, $fullySigned);
            } elseif ($fullySigned && $type === 'app' && $document instanceof AnnualProcurementPlan) {
                $this->applyAppSignatureOutcome($document, $user, $input['remarks'] ?? null);
            } elseif ($fullySigned && $type === 'bac_resolution') {
                $outcomeSignature = $signature;
                $outcomeUser = $user;

                if ($type === 'bac_resolution') {
                    $chairSignature = app(SignatureRequestService::class)
                        ->signedSignaturesForDocument($document, $type)
                        ->get('chairperson');

                    if ($chairSignature) {
                        $outcomeSignature = $chairSignature;
                        $outcomeUser = $chairSignature->signer ?: (User::find($chairSignature->signer_user_id) ?: $user);
                    }
                }

                $this->applyBacResolutionSignatureOutcome($document, $outcomeUser, $outcomeSignature, $input['remarks'] ?? null);
            }

            $documentTypeLabel = match ($type) {
                'app' => 'Annual Procurement Plan',
                'purchase_request' => 'Purchase Request',
                'ppmp' => 'PPMP',
                'abstract' => 'Abstract',
                default => 'BAC Resolution',
            };

            AuditLogger::signature('signature_confirmed', $document, [
                'description' => $documentTypeLabel . ' electronically signed through PaperTrail.',
                'metadata' => [
                    'signature_id' => $signature->signature_code,
                    'action' => $action,
                    'signature_request_id' => $signatureRequest?->id,
                    'signatory_slot' => $signatureRequest?->signatory_slot,
                    'signatory_label' => $signatureRequest?->signatory_label,
                    'signatory_designation' => $signatureRequest?->requested_to_role,
                    'assigned_account_user_id' => $signatureRequest?->requestedTo?->user_id,
                ],
            ]);

            return [
                'ok' => true,
                'message' => $this->signatureSuccessMessage($type, $fullySigned, $prSignatureResult, $ppmpSignatureResult),
                'signature' => $signature->fresh(),
            ];
        });
    }

    public function declineSignature(Model $document, User $user, string $documentType, string $action, string $reason, ?Request $request = null, ?SignatureRequest $signatureRequest = null): array
    {
        $type = $this->normalizeDocumentType($documentType);
        $check = $this->canSignWithReason($user, $document, $type, $action, $signatureRequest);
        $signatureRequest = $check['signature_request'] ?? $signatureRequest;

        if (! $check['ok']) {
            return $check;
        }

        return DB::transaction(function () use ($document, $user, $type, $action, $reason, $request, $signatureRequest) {
            $signature = $this->pendingSignatureFor($document, $type, $action, $user, $signatureRequest) ?: new ElectronicSignature();
            $signature->fill([
                ...$this->signatureIdentityPayload($document, $type, $action, $user),
                ...$this->signatureRequestPayload($signatureRequest),
                'signature_status' => ElectronicSignature::STATUS_DECLINED,
                'consent_text' => 'Signer declined or returned the document for correction.',
                'declined_at' => now(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'metadata' => ['reason' => $reason],
            ]);

            if (! $signature->signature_uuid) {
                $signature->signature_uuid = (string) Str::uuid();
            }

            if (! $signature->signature_code) {
                $signature->signature_code = $this->generateSignatureCode();
            }

            $signature->save();

            if ($signatureRequest) {
                app(SignatureRequestService::class)->markDeclined($signatureRequest, $reason);
            }

            if ($type === 'purchase_request' && $document instanceof ProcurementDocument) {
                $bacsec002PrRouting = app(Bacsec002PrSignatoryRoutingService::class);

                if ($bacsec002PrRouting->isPilotDocument($document)) {
                    $bacsec002PrRouting->handleDeclined($document, $user, $reason);
                } else {
                    app(EndUserPrSignatoryRoutingService::class)->handleDeclined($document, $user, $reason);
                }
            } elseif ($type === 'ppmp' && $document instanceof ProcurementDocument) {
                app(HeadOfficePpmpSignatureWorkflowService::class)->handleDeclined($document, $user, $reason);
            } elseif ($type === 'app' && $document instanceof AnnualProcurementPlan) {
                $this->applyAppDeclineOutcome($document, $user, $reason);
            } else {
                $this->applyBacResolutionDeclineOutcome($document, $user, $reason);
            }

            $documentTypeLabel = match ($type) {
                'app' => 'Annual Procurement Plan',
                'purchase_request' => 'Purchase Request',
                'ppmp' => 'PPMP',
                'abstract' => 'Abstract',
                default => 'BAC Resolution',
            };

            AuditLogger::signature('signature_declined', $document, [
                'description' => $documentTypeLabel . ' signature request was declined or returned.',
                'severity' => 'warning',
                'metadata' => ['signature_id' => $signature->signature_code],
            ]);

            return [
                'ok' => true,
                'message' => match ($type) {
                    'app' => 'APP signature request declined.',
                    'purchase_request' => 'Purchase Request signature request declined.',
                    'ppmp' => 'PPMP signature request declined.',
                    'abstract' => 'Abstract signature request declined.',
                    default => 'BAC Resolution returned to BAC Secretariat.',
                },
                'signature' => $signature,
            ];
        });
    }

    public function signedSignatureFor(Model $document, string $documentType = 'bac_resolution', string $action = 'confirmed'): ?ElectronicSignature
    {
        $query = ElectronicSignature::query()
            ->forDocument($this->normalizeDocumentType($documentType), $document->getKey())
            ->where('signature_action', $action)
            ->where('signature_status', ElectronicSignature::STATUS_SIGNED);

        if ($this->normalizeDocumentType($documentType) === 'bac_resolution' && $action === 'confirmed') {
            $query->where(function ($signature) {
                $signature->where('signatory_slot', 'chairperson')
                    ->orWhere('signer_role', User::ROLE_BAC_CHAIR)
                    ->orWhere('signer_role', 'BAC Chairperson')
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('signatory_slot')
                            ->where('signer_role', 'like', '%BAC Chair%');
                    });
            });
        }

        return $query
            ->latest('signed_at')
            ->first();
    }

    public function latestSignatureFor(Model $document, string $documentType = 'bac_resolution', string $action = 'confirmed'): ?ElectronicSignature
    {
        return ElectronicSignature::query()
            ->forDocument($this->normalizeDocumentType($documentType), $document->getKey())
            ->where('signature_action', $action)
            ->latest('updated_at')
            ->first();
    }

    public function canViewSignature(User $user, ElectronicSignature $signature): bool
    {
        if ($user->id === $signature->signer_user_id || $user->isAdmin()) {
            return true;
        }

        if ($signature->document_type === 'purchase_request' && $signature->document_id) {
            $document = ProcurementDocument::query()
                ->whereIn('document_type', ['PR', 'Purchase Request'])
                ->find($signature->document_id);

            return $document
                && (
                    (int) $document->submitted_by_user_id === (int) $user->id
                    || (int) $document->prepared_by_user_id === (int) $user->id
                    || (int) $document->assigned_to_user_id === (int) $user->id
                    || ($user->office_id && (int) $document->submitting_office_id === (int) $user->office_id)
                    || ($user->office_id && (int) $document->current_office_id === (int) $user->office_id)
                    || $user->hasRole('pr_numbering_staff')
                    || $user->hasRole(User::ROLE_PR_NUMBERING)
                    || SignatureRequest::query()
                        ->forDocument('purchase_request', $document->id)
                        ->where('requested_to_user_id', $user->id)
                        ->exists()
                );
        }

        if ($signature->document_type === 'ppmp' && $signature->document_id) {
            $document = ProcurementDocument::query()
                ->where('document_type', 'PPMP')
                ->find($signature->document_id);

            return $document
                && (
                    (int) $document->submitted_by_user_id === (int) $user->id
                    || (int) $document->prepared_by_user_id === (int) $user->id
                    || (int) $document->assigned_to_user_id === (int) $user->id
                    || ($user->office_id && (int) $document->submitting_office_id === (int) $user->office_id)
                    || ($user->office_id && (int) $document->current_office_id === (int) $user->office_id)
                    || SignatureRequest::query()
                        ->forDocument('ppmp', $document->id)
                        ->where('requested_to_user_id', $user->id)
                        ->exists()
                );
        }

        if ($signature->document_type === 'abstract' && $signature->document_id) {
            $abstract = AbstractQuotation::with('sourcePrDocument')->find($signature->document_id);

            return $abstract
                && (
                    (int) $abstract->prepared_by_user_id === (int) $user->id
                    || (int) $abstract->submitted_by_user_id === (int) $user->id
                    || SignatureRequest::query()
                        ->forDocument('abstract', $abstract->id)
                        ->where('requested_to_user_id', $user->id)
                        ->exists()
                    || $abstract->sourcePrDocument?->submitted_by_user_id === $user->id
                    || $abstract->sourcePrDocument?->prepared_by_user_id === $user->id
                    || ($user->office_id && $abstract->sourcePrDocument?->submitting_office_id === $user->office_id)
                );
        }

        if ($signature->document_type === 'app' && $signature->document_id) {
            $app = AnnualProcurementPlan::find($signature->document_id);

            return $app
                && (
                    (int) $app->prepared_by_user_id === (int) $user->id
                    || (int) $app->submitted_by_user_id === (int) $user->id
                    || (int) $app->approved_by_user_id === (int) $user->id
                    || SignatureRequest::query()
                        ->forDocument('app', $app->id)
                        ->where('requested_to_user_id', $user->id)
                        ->exists()
                    || $user->hasRole('bac_secretariat')
                    || $user->hasRole(User::ROLE_BAC_SECRETARIAT)
                    || $user->hasRole('budget_officer')
                    || $user->hasRole(User::ROLE_BUDGET)
                    || $user->hasRole('bac_chair')
                    || $user->hasRole(User::ROLE_BAC_CHAIR)
                );
        }

        if ($signature->document_type === 'bac_resolution' && $signature->document_id) {
            $resolution = BacResolution::with('sourcePrDocument')->find($signature->document_id);

            return $resolution
                && (
                    $user->hasRole('bac_secretariat')
                    || $user->hasRole(User::ROLE_BAC_SECRETARIAT)
                    || $user->hasRole('bac_chair')
                    || $user->hasRole(User::ROLE_BAC_CHAIR)
                    || SignatureRequest::query()
                        ->forDocument('bac_resolution', $resolution->id)
                        ->where('requested_to_user_id', $user->id)
                        ->exists()
                    || $resolution->sourcePrDocument?->submitted_by_user_id === $user->id
                    || $resolution->sourcePrDocument?->prepared_by_user_id === $user->id
                    || ($user->office_id && $resolution->sourcePrDocument?->submitting_office_id === $user->office_id)
                );
        }

        return false;
    }

    public function computeDocumentHash(Model $document): string
    {
        $payload = [
            'class' => $document::class,
            'id' => $document->getKey(),
            'status' => $document->status ?? null,
            'label' => $this->documentLabel($document),
        ];

        if ($document instanceof BacResolution) {
            $document->loadMissing('items');
            $payload += [
                'resolution_number' => $document->resolution_number,
                'fiscal_year' => $document->fiscal_year,
                'title' => $document->title,
                'project_title' => $document->project_title,
                'document_text' => $document->document_text,
                'document_html_hash' => $document->document_html ? hash('sha256', $document->document_html) : null,
                'whereas_clauses' => $document->whereas_clauses,
                'resolved_clauses' => $document->resolved_clauses,
                'items' => $document->items->map(fn ($item) => $item->only(['description', 'quantity', 'unit', 'unit_cost', 'total_cost']))->values()->all(),
            ];
        }

        if ($document instanceof AbstractQuotation) {
            $document->loadMissing('items');
            $payload += [
                'abstract_number' => $document->abstract_number,
                'project_name' => $document->project_name,
                'purpose' => $document->purpose,
                'document_text' => $document->document_text,
                'document_html_hash' => $document->document_html ? hash('sha256', $document->document_html) : null,
                'committee' => $document->committee_json,
                'items' => $document->items->map(fn ($item) => $item->only([
                    'item_no',
                    'name_of_goods_services',
                    'quantity',
                    'unit_of_measure',
                    'supplier_1_amount',
                    'supplier_2_amount',
                    'supplier_3_amount',
                    'supplier_4_amount',
                    'supplier_5_amount',
                    'total_lowest_price',
                ]))->values()->all(),
            ];
        }

        if ($document instanceof AnnualProcurementPlan) {
            $document->loadMissing('items');
            $payload += [
                'app_number' => $document->app_number,
                'document_reference_number' => $document->document_reference_number,
                'fiscal_year' => $document->fiscal_year,
                'title' => $document->title,
                'document_text' => $document->document_text,
                'document_html_hash' => $document->document_html ? hash('sha256', $document->document_html) : null,
                'signatories' => $document->signatories_json,
                'items' => $document->items->map(fn ($item) => $item->only([
                    'project_title',
                    'end_user_unit',
                    'general_description',
                    'mode_of_procurement',
                    'estimated_budget',
                ]))->values()->all(),
            ];
        }

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function createSignedSnapshot(Model $document, ElectronicSignature $signature, User $user): SignedDocumentSnapshot
    {
        $text = $this->documentTextSnapshot($document);
        $html = $document instanceof BacResolution || $document instanceof AbstractQuotation || $document instanceof AnnualProcurementPlan ? $document->document_html : null;
        $hash = hash('sha256', trim(($html ?: '') . "\n" . $text . "\n" . $signature->document_hash_before));

        return SignedDocumentSnapshot::create([
            'electronic_signature_id' => $signature->id,
            'document_type' => $signature->document_type,
            'document_id' => $signature->document_id,
            'document_label' => $signature->document_label,
            'tracking_number' => $signature->tracking_number,
            'html_snapshot' => $html,
            'text_snapshot' => $text,
            'snapshot_hash' => $hash,
            'created_by_user_id' => $user->id,
        ]);
    }

    private function applyBacResolutionSignatureOutcome(Model $document, User $user, ElectronicSignature $signature, ?string $remarks): void
    {
        if (! $document instanceof BacResolution) {
            return;
        }

        $document->loadMissing(['sourcePrDocument.submittedBy', 'sourcePrDocument.submittingOffice', 'preparedBy', 'submittedBy']);
        $sourceDocument = $document->sourcePrDocument;
        $oldResolutionStatus = $document->status;
        $routeRemarks = $remarks ?: 'BAC Resolution electronically signed by all required signatories.';

        $document->update([
            'status' => BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR,
            'bac_chair_confirmed_by_user_id' => $user->id,
            'bac_chair_confirmed_at' => now(),
            'bac_chair_remarks' => $remarks,
        ]);

        if ($sourceDocument) {
            $sourceDocument->forceFill([
                'bac_chair_status' => 'confirmed',
                'bac_chair_confirmation_status' => 'confirmed',
                'bac_chair_reviewed_by_user_id' => $user->id,
                'bac_chair_reviewed_at' => now(),
                'bac_chair_confirmed_at' => now(),
                'bac_chair_remarks' => $remarks,
                'bac_chair_confirmation_remarks' => $remarks,
                'routed_by_user_id' => $user->id,
                'routed_at' => now(),
            ])->save();

            app(SvpChainService::class)->routeAfterBacResolutionCompleted($document->refresh(), $user, $routeRemarks);
        }

        SystemNotificationService::notify(
            $document->submittedBy ?: $document->preparedBy,
            'BAC Resolution Fully Signed',
            'A BAC Resolution has been electronically signed by all required signatories.',
            SystemNotification::TYPE_SUCCESS,
            'BAC Resolution',
            $document,
            route('bac-secretariat.resolutions.show', $document),
        );

        AuditLogger::signature('bac_resolution_confirmed_by_bac_chair', $document, [
            'description' => 'BAC Resolution completed the required electronic signature workflow.',
            'old_values' => ['status' => $oldResolutionStatus],
            'new_values' => ['status' => BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR],
            'metadata' => ['signature_id' => $signature->signature_code],
        ]);

    }

    private function applyAppSignatureOutcome(AnnualProcurementPlan $app, User $user, ?string $remarks): void
    {
        $oldStatus = $app->status;

        $app->update([
            'status' => AnnualProcurementPlan::STATUS_APPROVED,
            'approved_by_user_id' => $user->id,
            'approved_at' => now(),
            'returned_at' => null,
            'return_reason' => null,
            'remarks' => $remarks ?: $app->remarks,
        ]);

        SystemNotificationService::notify(
            $app->submittedBy ?: $app->preparedBy,
            'Annual Procurement Plan Fully Signed',
            'The Annual Procurement Plan has been electronically signed by all required signatories.',
            SystemNotification::TYPE_SUCCESS,
            'Annual Procurement Plan',
            $app,
            route('bac-secretariat.app.show', $app),
        );

        AuditLogger::signature('app_fully_signed', $app, [
            'description' => 'APP completed the required electronic signature workflow.',
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => AnnualProcurementPlan::STATUS_APPROVED],
        ]);
    }

    private function applyAppDeclineOutcome(AnnualProcurementPlan $app, User $user, string $reason): void
    {
        $oldStatus = $app->status;

        $app->update([
            'status' => AnnualProcurementPlan::STATUS_RETURNED,
            'returned_at' => now(),
            'return_reason' => $reason,
        ]);

        SystemNotificationService::notify(
            $app->submittedBy ?: $app->preparedBy,
            'Annual Procurement Plan Returned',
            'An APP signatory returned the Annual Procurement Plan for correction.',
            SystemNotification::TYPE_WARNING,
            'Annual Procurement Plan',
            $app,
            route('bac-secretariat.app.show', $app),
        );

        AuditLogger::signature('app_signature_returned', $app, [
            'description' => 'APP signature request was returned for correction.',
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => AnnualProcurementPlan::STATUS_RETURNED],
            'severity' => 'warning',
            'metadata' => ['returned_by_user_id' => $user->user_id],
        ]);
    }

    private function applyBacResolutionDeclineOutcome(Model $document, User $user, string $reason): void
    {
        if (! $document instanceof BacResolution) {
            return;
        }

        $target = $this->bacSecretariatTarget();
        $document->loadMissing('sourcePrDocument');
        $sourceDocument = $document->sourcePrDocument;
        $oldStatus = $document->status;

        $document->update([
            'status' => BacResolution::STATUS_RETURNED_BY_BAC_CHAIR,
            'remarks' => $reason,
            'bac_chair_remarks' => $reason,
            'returned_at' => now(),
        ]);

        if ($sourceDocument) {
            $oldDocumentStatus = $sourceDocument->status;
            $fromOfficeId = $sourceDocument->current_office_id;
            $toOfficeId = $target['office']?->id ?? $target['user']?->office_id;

            $sourceDocument->update([
                'status' => ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR,
                'stage' => ProcurementDocument::STAGE_RETURNED_TO_BAC_SECRETARIAT,
                'current_office_id' => $toOfficeId,
                'assigned_to_user_id' => $target['user']?->id,
                'bac_chair_status' => 'returned',
                'bac_chair_confirmation_status' => 'returned',
                'bac_chair_reviewed_by_user_id' => $user->id,
                'bac_chair_reviewed_at' => now(),
                'bac_chair_remarks' => $reason,
                'bac_chair_confirmation_remarks' => $reason,
                'remarks' => $reason,
                'route_destination_role' => User::ROLE_BAC_SECRETARIAT,
                'route_destination_office_id' => $toOfficeId,
                'route_remarks' => $reason,
            ]);

            DocumentRoutingHistory::create([
                'procurement_document_id' => $sourceDocument->id,
                'action_by_user_id' => $user->id,
                'from_office_id' => $fromOfficeId,
                'to_office_id' => $toOfficeId,
                'action' => 'BAC Resolution Returned by BAC Chair',
                'status_from' => $oldDocumentStatus,
                'status_to' => ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR,
                'comments' => $reason,
                'action_at' => now(),
            ]);
        }

        SystemNotificationService::notify(
            $document->submittedBy ?: $target['user'],
            'BAC Resolution Returned',
            'A BAC Resolution was returned by the BAC Chair and requires correction.',
            SystemNotification::TYPE_WARNING,
            'BAC Resolution',
            $document,
            route('bac-secretariat.resolutions.show', $document),
        );

        AuditLogger::signature('bac_resolution_returned_by_bac_chair', $document, [
            'description' => 'BAC Chair returned BAC Resolution through e-signature panel.',
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => BacResolution::STATUS_RETURNED_BY_BAC_CHAIR],
            'severity' => 'warning',
        ]);

        app(SvpChainService::class)->linkBacResolution($document->refresh(), $user, 'BAC Resolution returned by BAC Chair', $reason);
    }

    private function pendingSignatureFor(Model $document, string $documentType, string $action, User $user, ?SignatureRequest $signatureRequest = null): ?ElectronicSignature
    {
        $query = ElectronicSignature::query()
            ->forDocument($this->normalizeDocumentType($documentType), $document->getKey())
            ->where('signature_action', $action)
            ->where('signature_status', ElectronicSignature::STATUS_PENDING)
            ->where('signer_user_id', $user->id);

        if ($signatureRequest) {
            $query->where('signature_request_id', $signatureRequest->id);
        }

        return $query
            ->latest('updated_at')
            ->first();
    }

    private function signatureIdentityPayload(Model $document, string $documentType, string $action, User $user): array
    {
        $user->loadMissing(['assignedRole', 'assignedOffice']);

        return [
            'document_type' => $this->normalizeDocumentType($documentType),
            'document_id' => $document->getKey(),
            'document_label' => $this->documentLabel($document),
            'tracking_number' => $this->trackingNumber($document),
            'signer_user_id' => $user->id,
            'signer_user_identifier' => $user->user_id,
            'signer_name' => $user->name,
            'signer_role' => $user->assignedRole?->name ?? $user->role,
            'signer_office_id' => $user->office_id,
            'signer_office_name' => $user->assignedOffice?->name ?? $user->office,
            'signer_position' => $user->signer_position ?: ($user->position ?: $user->role),
            'typed_signature_name' => $user->typed_signature_name ?: $user->name,
            'signature_image_path' => $user->signature_image_path,
            'signature_action' => $action,
        ];
    }

    private function signatureRequestPayload(?SignatureRequest $signatureRequest): array
    {
        if (! $signatureRequest) {
            return [];
        }

        return [
            'signature_request_id' => $signatureRequest->id,
            'signatory_slot' => $signatureRequest->signatory_slot,
            'signatory_label' => $signatureRequest->signatory_label,
        ];
    }

    private function documentTextSnapshot(Model $document): string
    {
        if ($document instanceof AbstractQuotation) {
            return trim(implode("\n", array_filter([
                $document->abstract_number,
                $document->project_name,
                $document->purpose,
                $document->document_text,
                $document->document_html ? trim(strip_tags($document->document_html)) : null,
                json_encode($document->items_json ?: []),
                json_encode($document->committee_json ?: []),
            ])));
        }

        if ($document instanceof BacResolution) {
            return trim(implode("\n", array_filter([
                $document->displayNumber(),
                $document->title,
                $document->project_title,
                $document->document_text,
                $document->document_html ? trim(strip_tags($document->document_html)) : null,
                json_encode($document->whereas_clauses ?: []),
                json_encode($document->resolved_clauses ?: []),
            ])));
        }

        if ($document instanceof AnnualProcurementPlan) {
            return trim(implode("\n", array_filter([
                $document->displayNumber(),
                $document->title,
                $document->document_text,
                $document->document_html ? trim(strip_tags($document->document_html)) : null,
                json_encode($document->items_json ?: []),
                json_encode($document->signatories_json ?: []),
            ])));
        }

        return trim(json_encode($document->getAttributes()));
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

        if (method_exists($document, 'displayNumber')) {
            return $document->displayNumber();
        }

        return $document->tracking_number ?? null;
    }

    private function canSignPurchaseRequestWithReason(User $user, ProcurementDocument $document, string $type, ?SignatureRequest $signatureRequest = null): array
    {
        $bacsec002PrRouting = app(Bacsec002PrSignatoryRoutingService::class);
        $endUserPrRouting = app(EndUserPrSignatoryRoutingService::class);
        $isConfiguredPurchaseRequest = $bacsec002PrRouting->isPilotDocument($document)
            || $endUserPrRouting->isWorkflowDocument($document)
            || $endUserPrRouting->hasSignatureRouting($document);

        if (! $isConfiguredPurchaseRequest) {
            return ['ok' => false, 'message' => 'This Purchase Request is not configured for designation-based e-signature routing.'];
        }

        if ($document->status !== ProcurementDocument::STATUS_PR_PENDING_SIGNATORIES) {
            return ['ok' => false, 'message' => 'This Purchase Request is not waiting for signatory completion.'];
        }

        $requestForUser = app(SignatureRequestService::class)->openRequestForUser($document, $type, $user, $signatureRequest);

        if (! $requestForUser) {
            return ['ok' => false, 'message' => 'Only assigned Purchase Request signers can sign this document.'];
        }

        if (app(SignatureRequestService::class)->signedSignatureForRequest($requestForUser)) {
            return ['ok' => false, 'message' => 'Your assigned Purchase Request signature has already been signed.'];
        }

        return ['ok' => true, 'message' => 'This Purchase Request can be signed.', 'signature_request' => $requestForUser];
    }

    private function canSignPpmpWithReason(User $user, ProcurementDocument $document, string $type, ?SignatureRequest $signatureRequest = null): array
    {
        $workflow = app(HeadOfficePpmpSignatureWorkflowService::class);

        if (! $workflow->isWorkflowDocument($document) && ! $workflow->hasSignatureRouting($document)) {
            return ['ok' => false, 'message' => 'This PPMP is not configured for Head Office electronic signature routing.'];
        }

        $workflow->ensureSingleSignatureRequirement($document, $user);
        $document->refresh();

        if ($document->status !== ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES) {
            return ['ok' => false, 'message' => 'This PPMP is not waiting for signatory completion.'];
        }

        $requestForUser = app(SignatureRequestService::class)->openRequestForUser($document, $type, $user, $signatureRequest);

        if (! $requestForUser) {
            return ['ok' => false, 'message' => 'Only assigned PPMP signers can sign this document.'];
        }

        if (app(SignatureRequestService::class)->signedSignatureForRequest($requestForUser)) {
            return ['ok' => false, 'message' => 'Your assigned PPMP signature has already been signed.'];
        }

        return ['ok' => true, 'message' => 'This PPMP can be signed.', 'signature_request' => $requestForUser];
    }

    private function canSignAbstractWithReason(User $user, AbstractQuotation $document, string $type, ?SignatureRequest $signatureRequest = null): array
    {
        if ($document->status !== AbstractQuotation::STATUS_SUBMITTED) {
            return ['ok' => false, 'message' => 'Only submitted Abstracts can be signed.'];
        }

        $requestForUser = app(SignatureRequestService::class)->openRequestForUser($document, $type, $user, $signatureRequest);

        if (! $requestForUser) {
            return ['ok' => false, 'message' => 'Only assigned Abstract signers can sign this document.'];
        }

        if (app(SignatureRequestService::class)->signedSignatureForRequest($requestForUser)) {
            return ['ok' => false, 'message' => 'Your assigned Abstract signature has already been signed.'];
        }

        return ['ok' => true, 'message' => 'This Abstract can be signed.', 'signature_request' => $requestForUser];
    }

    private function canSignAppWithReason(User $user, AnnualProcurementPlan $document, string $type, ?SignatureRequest $signatureRequest = null): array
    {
        if ($document->status !== AnnualProcurementPlan::STATUS_SUBMITTED) {
            return ['ok' => false, 'message' => 'Only submitted APP records can be signed.'];
        }

        $requestForUser = app(SignatureRequestService::class)->openRequestForUser($document, $type, $user, $signatureRequest);

        if (! $requestForUser) {
            return ['ok' => false, 'message' => 'Only assigned APP signers can sign this document.'];
        }

        if (app(SignatureRequestService::class)->signedSignatureForRequest($requestForUser)) {
            return ['ok' => false, 'message' => 'Your assigned APP signature has already been signed.'];
        }

        return ['ok' => true, 'message' => 'This APP can be signed.', 'signature_request' => $requestForUser];
    }

    private function signatureSuccessMessage(string $type, bool $fullySigned, string $prSignatureResult, string $ppmpSignatureResult = 'ignored'): string
    {
        if ($type === 'purchase_request') {
            return match ($prSignatureResult) {
                'completed_signatories' => 'All PR signatures completed. Purchase Request returned to BACSEC-002 for PR number submission.',
                'submitted_to_pr_numbering' => 'All PR signatures completed. Purchase Request submitted to PR Numbering Staff.',
                default => $fullySigned
                    ? 'All PR signatures completed.'
                    : 'PR signature recorded. Other required signatures are still pending.',
            };
        }

        if ($type === 'ppmp') {
            return match ($ppmpSignatureResult) {
                'completed_signatories' => 'PPMP signature completed. Submit the PPMP to BAC Secretariat when ready.',
                default => $fullySigned
                    ? 'PPMP signature completed.'
                    : 'PPMP signature recorded.',
            };
        }

        if ($type === 'abstract') {
            return $fullySigned
                ? 'All Abstract signatures completed.'
                : 'Abstract signature recorded. Other required signatures are still pending.';
        }

        if ($type === 'app') {
            return $fullySigned
                ? 'All APP signatures completed. The APP is now approved.'
                : 'APP signature recorded. Other required signatures are still pending.';
        }

        return $fullySigned
            ? 'BAC Resolution electronically signed by all required signatories and routed to the next SVP stage.'
            : 'Signature recorded. Other required signatures are still pending.';
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            'confirmed' => 'Confirm / Sign',
            default => str($action)->replace('_', ' ')->title()->toString(),
        };
    }

    private function generateSignatureCode(): string
    {
        do {
            $code = 'PTSIG-' . now()->format('Ymd') . '-' . Str::upper(Str::random(8));
        } while (ElectronicSignature::where('signature_code', $code)->exists());

        return $code;
    }

    private function isBacChairSigner(User $user): bool
    {
        return $user->hasRole('bac_chair')
            || $user->hasRole(User::ROLE_BAC_CHAIR)
            || $user->hasPermission('bac.resolution.confirm');
    }

    private function bacSecretariatTarget(): array
    {
        $user = User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->where(function ($query) {
                $query->where('role', User::ROLE_BAC_SECRETARIAT)
                    ->orWhere('user_id', 'BACSEC-001')
                    ->orWhereHas('assignedRole', fn ($role) => $role->where('code', 'bac_secretariat')->orWhere('name', User::ROLE_BAC_SECRETARIAT));
            })
            ->first();

        return [
            'user' => $user,
            'office' => $user?->assignedOffice ?? Office::query()
                ->where('code', 'BACSEC')
                ->orWhere('name', 'BAC Secretariat')
                ->first(),
        ];
    }
}
