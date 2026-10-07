<?php

namespace App\Services;

use App\Models\AbstractQuotation;
use App\Models\AnnualProcurementPlan;
use App\Models\BacResolution;
use App\Models\DocumentAttachment;
use App\Models\InspectionAcceptanceRecord;
use App\Models\Ppmp;
use App\Models\ProcurementDocument;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\SupplementalApp;
use App\Models\SvpPostingRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DocumentResolverService
{
    public function resolve(string $documentType, int|string $documentId): Model
    {
        $type = $this->normalizeDocumentType($documentType);

        return match ($type) {
            'ppmp' => ProcurementDocument::query()
                ->where('document_type', 'PPMP')
                ->findOrFail($documentId),
            'ppmp_record', 'standalone_ppmp' => Ppmp::query()
                ->with(['items', 'office', 'creator'])
                ->findOrFail($documentId),
            'purchase_request' => ProcurementDocument::query()
                ->whereIn('document_type', ['PR', 'Purchase Request'])
                ->findOrFail($documentId),
            'procurement_document' => ProcurementDocument::query()->findOrFail($documentId),
            'app', 'annual_procurement_plan' => AnnualProcurementPlan::query()->findOrFail($documentId),
            'supplemental_app' => SupplementalApp::query()->with('sourcePrDocument')->findOrFail($documentId),
            'bac_resolution' => BacResolution::query()->with('sourcePrDocument')->findOrFail($documentId),
            'rfq' => Rfq::query()->with('sourcePrDocument')->findOrFail($documentId),
            'abstract', 'abstract_quotation' => AbstractQuotation::query()->with('sourcePrDocument')->findOrFail($documentId),
            'purchase_order' => PurchaseOrder::query()->with('sourcePrDocument')->findOrFail($documentId),
            'svp_posting', 'svp_posting_record' => SvpPostingRecord::query()
                ->with(['chain.sourcePrDocument', 'sourcePrDocument'])
                ->findOrFail($documentId),
            'inspection_acceptance' => InspectionAcceptanceRecord::query()
                ->with(['sourcePrDocument', 'purchaseOrder.sourcePrDocument'])
                ->findOrFail($documentId),
            default => abort(404),
        };
    }

    public function resolveAttachmentDocument(DocumentAttachment $attachment): ?Model
    {
        $attachment->loadMissing(['attachable', 'procurementDocument']);

        if ($attachment->attachable) {
            return $attachment->attachable;
        }

        if ($attachment->document_type && $attachment->document_id) {
            return $this->resolve($attachment->document_type, $attachment->document_id);
        }

        return $attachment->procurementDocument;
    }

    public function canView(?User $user, ?Model $document): bool
    {
        if (! $user || ! $document) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($this->userParticipates($user, $document) || $this->sameOffice($user, $document)) {
            return true;
        }

        if ($source = $this->sourceProcurementDocument($document)) {
            return $this->canView($user, $source);
        }

        if ($document instanceof ProcurementDocument) {
            return $this->canViewProcurementDocument($user, $document);
        }

        if ($this->hasAnyRole($user, ['bac_secretariat', User::ROLE_BAC_SECRETARIAT])) {
            return $document instanceof Ppmp
                || $document instanceof AnnualProcurementPlan
                || $document instanceof SupplementalApp
                || $document instanceof BacResolution
                || $document instanceof SvpPostingRecord
                || $document instanceof Rfq
                || $document instanceof AbstractQuotation
                || $document instanceof PurchaseOrder
                || $document instanceof InspectionAcceptanceRecord;
        }

        if ($this->hasAnyRole($user, ['bac_chair', User::ROLE_BAC_CHAIR])) {
            return $document instanceof BacResolution
                && in_array($document->status, [
                    BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR,
                    BacResolution::STATUS_RETURNED_BY_BAC_CHAIR,
                    BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR,
                    BacResolution::STATUS_FORWARDED_TO_HOPE,
                    BacResolution::STATUS_APPROVED_BY_HOPE,
                ], true);
        }

        if ($this->hasAnyRole($user, ['approving_authority', User::ROLE_APPROVING_AUTHORITY])) {
            return ($document instanceof AnnualProcurementPlan
                && in_array($document->status, [
                    AnnualProcurementPlan::STATUS_SUBMITTED,
                    AnnualProcurementPlan::STATUS_CONSOLIDATED,
                    AnnualProcurementPlan::STATUS_APPROVED,
                    AnnualProcurementPlan::STATUS_RETURNED,
                ], true))
                || ($document instanceof BacResolution
                && in_array($document->status, [
                    BacResolution::STATUS_FORWARDED_TO_HOPE,
                    BacResolution::STATUS_APPROVED_BY_HOPE,
                    BacResolution::STATUS_RETURNED_BY_HOPE,
                ], true));
        }

        return false;
    }

    public function canUpload(?User $user, ?Model $document): bool
    {
        if (! $this->canView($user, $document)) {
            return false;
        }

        if (! $user || ! $document) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($this->hasAnyRole($user, ['head_office', User::ROLE_HEAD_OFFICE])) {
            return $this->sameOffice($user, $document) && $this->isModifiable($document);
        }

        if ($this->hasAnyRole($user, ['bac_secretariat', User::ROLE_BAC_SECRETARIAT])) {
            if ($document instanceof SvpPostingRecord) {
                return $user->user_id === 'BACSEC-004' && $this->isModifiable($document);
            }

            if ($document instanceof ProcurementDocument) {
                if ($document->status === ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION) {
                    return false;
                }

                return in_array($document->document_type, ['PR', 'Purchase Request', 'PPMP'], true)
                    && ! $this->isFinalDocument($document);
            }

            return ($document instanceof AnnualProcurementPlan
                || $document instanceof SupplementalApp
                || $document instanceof BacResolution
                || $document instanceof Rfq
                || $document instanceof AbstractQuotation
                || $document instanceof PurchaseOrder)
                && $this->isModifiable($document);
        }

        return false;
    }

    public function canDeleteAttachment(?User $user, ?Model $document, DocumentAttachment $attachment): bool
    {
        if (! $user || ! $document || ! $this->canView($user, $document)) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($this->isFinalDocument($document)) {
            return false;
        }

        if ((int) $attachment->uploaded_by_user_id === (int) $user->id) {
            return true;
        }

        $attachment->loadMissing('uploadedBy');

        return $this->canUpload($user, $document)
            && $attachment->uploadedBy?->office_id
            && (int) $attachment->uploadedBy->office_id === (int) $user->office_id;
    }

    public function normalizeDocumentType(string $documentType): string
    {
        return Str::of($documentType)
            ->replace('-', '_')
            ->replace(' ', '_')
            ->lower()
            ->toString();
    }

    public function documentTypeFor(Model $document, ?string $fallback = null): string
    {
        if ($fallback) {
            return $this->normalizeDocumentType($fallback);
        }

        return match (true) {
            $document instanceof ProcurementDocument && $document->document_type === 'PPMP' => 'ppmp',
            $document instanceof Ppmp => 'ppmp_record',
            $document instanceof ProcurementDocument => 'purchase_request',
            $document instanceof AnnualProcurementPlan => 'app',
            $document instanceof SupplementalApp => 'supplemental_app',
            $document instanceof BacResolution => 'bac_resolution',
            $document instanceof SvpPostingRecord => 'svp_posting',
            $document instanceof Rfq => 'rfq',
            $document instanceof AbstractQuotation => 'abstract',
            $document instanceof PurchaseOrder => 'purchase_order',
            $document instanceof InspectionAcceptanceRecord => 'inspection_acceptance',
            default => Str::snake(class_basename($document)),
        };
    }

    public function displayTrackingNumber(Model $document): ?string
    {
        if (method_exists($document, 'displayNumber')) {
            return (string) $document->displayNumber();
        }

        foreach ([
            'tracking_number',
            'pr_no',
            'app_number',
            'supplemental_app_number',
            'resolution_number',
            'rfq_number',
            'abstract_number',
            'po_number',
            'inspection_number',
        ] as $attribute) {
            if (filled($document->{$attribute} ?? null)) {
                return (string) $document->{$attribute};
            }
        }

        return null;
    }

    public function officeIdFor(Model $document): ?int
    {
        return collect($this->officeIdsFor($document))->filter()->first();
    }

    public function officeNameFor(Model $document): ?string
    {
        foreach (['office_name', 'requesting_office_name', 'implementing_office', 'department_name'] as $attribute) {
            if (filled($document->{$attribute} ?? null)) {
                return (string) $document->{$attribute};
            }
        }

        if ($document instanceof ProcurementDocument) {
            $document->loadMissing('submittingOffice', 'currentOffice');

            return $document->submittingOffice?->name ?? $document->currentOffice?->name;
        }

        if ($source = $this->sourceProcurementDocument($document)) {
            return $this->officeNameFor($source);
        }

        return null;
    }

    public function procurementDocumentIdFor(Model $document): ?int
    {
        if ($document instanceof ProcurementDocument) {
            return $document->id;
        }

        foreach (['procurement_document_id', 'source_pr_document_id'] as $attribute) {
            if (filled($document->{$attribute} ?? null)) {
                return (int) $document->{$attribute};
            }
        }

        if ($document instanceof InspectionAcceptanceRecord && $document->purchaseOrder?->source_pr_document_id) {
            return (int) $document->purchaseOrder->source_pr_document_id;
        }

        return null;
    }

    private function canViewProcurementDocument(User $user, ProcurementDocument $document): bool
    {
        if ($this->hasAnyRole($user, ['budget_officer', User::ROLE_BUDGET])) {
            return in_array($document->status, [
                ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW,
                ProcurementDocument::STATUS_UNDER_BUDGET_REVIEW,
                ProcurementDocument::STATUS_RETURNED_BY_BUDGET,
                ProcurementDocument::STATUS_BUDGET_REVIEWED,
                ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW,
            ], true);
        }

        if ($this->hasAnyRole($user, ['accounting_officer', User::ROLE_ACCOUNTING])) {
            return in_array($document->status, [
                ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW,
                ProcurementDocument::STATUS_UNDER_ACCOUNTING_REVIEW,
                ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING,
                ProcurementDocument::STATUS_ACCOUNTING_REVIEWED,
                ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW,
            ], true);
        }

        if ($this->hasAnyRole($user, ['bac_secretariat', User::ROLE_BAC_SECRETARIAT])) {
            if ($document->status === ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION) {
                return (int) $document->assigned_to_user_id === (int) $user->id;
            }

            return $document->document_type === 'PPMP'
                || in_array($document->status, [
                    ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW,
                    ProcurementDocument::STATUS_RECEIVED_BY_BAC_SECRETARIAT,
                    ProcurementDocument::STATUS_UNDER_BAC_SECRETARIAT_REVIEW,
                    ProcurementDocument::STATUS_READY_FOR_DOCUMENT_ROUTING,
                    ProcurementDocument::STATUS_SUBMITTED_TO_BAC_SECRETARIAT,
                    ProcurementDocument::STATUS_PR_SUBMITTED,
                    ProcurementDocument::STATUS_PR_RECEIVED_BY_BAC_SECRETARIAT,
                    ProcurementDocument::STATUS_UNDER_PR_VALIDATION,
                    ProcurementDocument::STATUS_BAC_RESOLUTION_CREATED,
                    ProcurementDocument::STATUS_READY_FOR_RFQ,
                ], true);
        }

        if ($this->hasAnyRole($user, ['bac_member', User::ROLE_BAC_MEMBER])) {
            return in_array($document->status, [
                ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW,
                ProcurementDocument::STATUS_UNDER_BAC_MEMBER_REVIEW,
                ProcurementDocument::STATUS_ENDORSED_BY_BAC_MEMBER,
                ProcurementDocument::STATUS_RETURNED_BY_BAC_MEMBER,
            ], true);
        }

        if ($this->hasAnyRole($user, ['bac_chair', User::ROLE_BAC_CHAIR])) {
            return in_array($document->status, [
                ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW,
                ProcurementDocument::STATUS_UNDER_BAC_CHAIR_REVIEW,
                ProcurementDocument::STATUS_READY_FOR_BAC_CHAIR_CONFIRMATION,
                ProcurementDocument::STATUS_CONFIRMED_BY_BAC_CHAIR,
                ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR,
            ], true);
        }

        if ($this->hasAnyRole($user, ['approving_authority', User::ROLE_APPROVING_AUTHORITY])) {
            return in_array($document->status, [
                ProcurementDocument::STATUS_PENDING_APPROVAL,
                ProcurementDocument::STATUS_UNDER_APPROVAL,
                ProcurementDocument::STATUS_APPROVED,
                ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY,
            ], true);
        }

        if ($this->hasAnyRole($user, ['pr_numbering_staff', User::ROLE_PR_NUMBERING])) {
            return in_array($document->status, [
                ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT,
                ProcurementDocument::STATUS_PR_NUMBER_ASSIGNED,
                ProcurementDocument::STATUS_PR_NUMBER_ASSIGNED_RETURNED_TO_END_USER,
                ProcurementDocument::STATUS_RETURNED_BY_PR_NUMBERING_STAFF,
            ], true);
        }

        return false;
    }

    private function sourceProcurementDocument(Model $document): ?ProcurementDocument
    {
        if ($document instanceof ProcurementDocument) {
            return null;
        }

        foreach (['sourcePrDocument', 'procurementDocument'] as $relation) {
            if (method_exists($document, $relation)) {
                $document->loadMissing($relation);

                if ($document->{$relation} instanceof ProcurementDocument) {
                    return $document->{$relation};
                }
            }
        }

        if ($document instanceof InspectionAcceptanceRecord && $document->purchaseOrder) {
            return $document->purchaseOrder->sourcePrDocument ?: $document->purchaseOrder->procurementDocument;
        }

        return null;
    }

    private function sameOffice(User $user, Model $document): bool
    {
        if (! $user->office_id) {
            return false;
        }

        return in_array((int) $user->office_id, $this->officeIdsFor($document), true);
    }

    private function officeIdsFor(Model $document): array
    {
        $ids = [];

        foreach ([
            'office_id',
            'submitting_office_id',
            'current_office_id',
            'route_destination_office_id',
            'requesting_office_id',
        ] as $attribute) {
            if (filled($document->{$attribute} ?? null)) {
                $ids[] = (int) $document->{$attribute};
            }
        }

        if ($source = $this->sourceProcurementDocument($document)) {
            $ids = array_merge($ids, $this->officeIdsFor($source));
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private function userParticipates(User $user, Model $document): bool
    {
        foreach ([
            'uploaded_by_user_id',
            'submitted_by_user_id',
            'prepared_by_user_id',
            'assigned_to_user_id',
            'created_by',
            'approved_by_user_id',
            'accepted_by_user_id',
            'bac_chair_confirmed_by_user_id',
            'inspected_by_user_id',
            'pr_no_assigned_by_user_id',
            'budget_reviewed_by_user_id',
            'accounting_reviewed_by_user_id',
            'bac_secretariat_received_by_user_id',
            'bac_member_reviewed_by_user_id',
            'bac_chair_reviewed_by_user_id',
        ] as $attribute) {
            if (filled($document->{$attribute} ?? null) && (int) $document->{$attribute} === (int) $user->id) {
                return true;
            }
        }

        if ($source = $this->sourceProcurementDocument($document)) {
            return $this->userParticipates($user, $source);
        }

        return false;
    }

    private function isModifiable(Model $document): bool
    {
        if (method_exists($document, 'isEditable')) {
            return (bool) $document->isEditable();
        }

        if (filled($document->status ?? null)) {
            return in_array($document->status, [
                'draft',
                'created',
                'returned',
                'ppmp_draft',
                'ppmp_signature_returned',
                'pr_draft',
                'po_draft',
                'returned_by_bac_secretariat',
                'returned_by_budget',
                'returned_by_accounting',
                'returned_by_pr_numbering_staff',
                'returned_by_bac_chair',
                'returned_by_hope',
                'returned_to_end_user',
            ], true);
        }

        return true;
    }

    private function isFinalDocument(Model $document): bool
    {
        if (! filled($document->status ?? null)) {
            return false;
        }

        return in_array($document->status, [
            'approved',
            'accepted',
            'completed',
            'issued',
            'app_approved',
            'po_approved',
            'po_completed',
            'confirmed_by_bac_chair',
            'approved_by_hope',
            'accepted_for_app_consolidation',
            'fund_certified',
        ], true);
    }

    private function hasAnyRole(User $user, array $roles): bool
    {
        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }
}
