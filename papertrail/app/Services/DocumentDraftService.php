<?php

namespace App\Services;

use App\Models\AbstractQuotation;
use App\Models\AnnualProcurementPlan;
use App\Models\BacResolution;
use App\Models\InspectionAcceptanceRecord;
use App\Models\ProcurementDocument;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\SupplementalApp;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DocumentDraftService
{
    public function getPurchaseRequestDraftsForUser(User $user, ?int $limit = null): Collection
    {
        if (! $this->canCreateHeadOfficeDocuments($user) && ! $this->canCreatePrDocumentsAsBacsec002($user)) {
            return collect();
        }

        return $this->procurementDraftsForUser($user, ProcurementDocument::STATUS_PR_DRAFT, 'Purchase Request', 'head-office.pr', $limit);
    }

    public function getPpmpDraftsForUser(User $user, ?int $limit = null): Collection
    {
        if (! $this->canCreateHeadOfficeDocuments($user)) {
            return collect();
        }

        return $this->procurementDraftsForUser($user, ProcurementDocument::STATUS_PPMP_DRAFT, 'PPMP', 'head-office.ppmp', $limit, false);
    }

    public function getRfqDraftsForUser(User $user, ?int $limit = null): Collection
    {
        if (! $this->canCreateHeadOfficeDocuments($user)) {
            return collect();
        }

        return $this->modelDrafts(Rfq::class, function (Builder $query) use ($user) {
            $query->with(['preparedBy', 'submittedBy', 'sourcePrDocument.submittingOffice', 'sourceBacResolution.sourcePrDocument'])
                ->where('status', Rfq::STATUS_DRAFT);

            $this->scopeLinkedDocumentToUser($query, $user);
        }, fn (Rfq $rfq) => $this->normalizeRfq($rfq, 'head-office.rfqs'), $limit);
    }

    public function getRfqDraftsForBacSecretariat(User $user, ?int $limit = null): Collection
    {
        if (! $this->canCreateBacSecretariatDocuments($user)) {
            return collect();
        }

        return $this->modelDrafts(Rfq::class, function (Builder $query) {
            $query->with(['preparedBy', 'submittedBy', 'sourcePrDocument.submittingOffice', 'sourceBacResolution.sourcePrDocument'])
                ->where('status', Rfq::STATUS_DRAFT);
        }, fn (Rfq $rfq) => $this->normalizeRfq($rfq, 'bac-secretariat.rfqs'), $limit);
    }

    public function getAbstractDraftsForUser(User $user, ?int $limit = null): Collection
    {
        if (! $this->canCreateHeadOfficeDocuments($user)) {
            return collect();
        }

        return $this->modelDrafts(AbstractQuotation::class, function (Builder $query) use ($user) {
            $query->with(['preparedBy', 'submittedBy', 'sourcePrDocument.submittingOffice', 'sourceRfq.sourcePrDocument', 'sourceRfq.sourceBacResolution.sourcePrDocument', 'sourceBacResolution.sourcePrDocument'])
                ->where('status', AbstractQuotation::STATUS_DRAFT);

            $this->scopeLinkedDocumentToUser($query, $user);
        }, fn (AbstractQuotation $abstract) => $this->normalizeAbstract($abstract, 'head-office.abstracts'), $limit);
    }

    public function getAbstractDraftsForBacSecretariat(User $user, ?int $limit = null): Collection
    {
        if (! $this->canCreateBacSecretariatDocuments($user)) {
            return collect();
        }

        return $this->modelDrafts(AbstractQuotation::class, function (Builder $query) {
            $query->with(['preparedBy', 'submittedBy', 'sourcePrDocument.submittingOffice', 'sourceRfq.sourcePrDocument', 'sourceRfq.sourceBacResolution.sourcePrDocument', 'sourceBacResolution.sourcePrDocument'])
                ->where('status', AbstractQuotation::STATUS_DRAFT);
        }, fn (AbstractQuotation $abstract) => $this->normalizeAbstract($abstract, 'bac-secretariat.abstracts'), $limit);
    }

    public function getPurchaseOrderDraftsForUser(User $user, ?int $limit = null): Collection
    {
        if (! $this->canCreateHeadOfficeDocuments($user)) {
            return collect();
        }

        return $this->modelDrafts(PurchaseOrder::class, function (Builder $query) use ($user) {
            $query->with(['preparedBy', 'submittedBy', 'sourcePrDocument.submittingOffice', 'procurementDocument.submittingOffice', 'sourceAbstract.sourcePrDocument', 'sourceAbstract.sourceRfq.sourcePrDocument', 'sourceBacResolution.sourcePrDocument'])
                ->where('status', PurchaseOrder::STATUS_DRAFT);

            $this->scopePurchaseOrderToUser($query, $user);
        }, fn (PurchaseOrder $purchaseOrder) => $this->normalizePurchaseOrder($purchaseOrder, 'head-office.purchase-orders'), $limit);
    }

    public function getPurchaseOrderDraftsForBacSecretariat(User $user, ?int $limit = null): Collection
    {
        if (! $this->canCreateBacSecretariatDocuments($user)) {
            return collect();
        }

        return $this->modelDrafts(PurchaseOrder::class, function (Builder $query) {
            $query->with(['preparedBy', 'submittedBy', 'sourcePrDocument.submittingOffice', 'procurementDocument.submittingOffice', 'sourceAbstract.sourcePrDocument', 'sourceAbstract.sourceRfq.sourcePrDocument', 'sourceBacResolution.sourcePrDocument'])
                ->where('status', PurchaseOrder::STATUS_DRAFT);
        }, fn (PurchaseOrder $purchaseOrder) => $this->normalizePurchaseOrder($purchaseOrder, 'bac-secretariat.purchase-orders'), $limit);
    }

    public function getInspectionDraftsForUser(User $user, ?int $limit = null): Collection
    {
        if (! $this->canCreateHeadOfficeDocuments($user)) {
            return collect();
        }

        return $this->modelDrafts(InspectionAcceptanceRecord::class, function (Builder $query) use ($user) {
            $query->with(['office', 'inspectedBy', 'acceptedBy', 'purchaseOrder.preparedBy', 'purchaseOrder.sourcePrDocument.submittingOffice', 'sourcePrDocument.submittingOffice'])
                ->where('status', InspectionAcceptanceRecord::STATUS_DRAFT)
                ->where(function (Builder $scoped) use ($user) {
                    $scoped->where('inspected_by_user_id', $user->id)
                        ->orWhere('accepted_by_user_id', $user->id)
                        ->orWhereHas('sourcePrDocument', fn (Builder $source) => $this->scopeProcurementToUser($source, $user))
                        ->orWhereHas('purchaseOrder', fn (Builder $purchaseOrder) => $this->scopePurchaseOrderToUser($purchaseOrder, $user));

                    if ($user->office_id) {
                        $scoped->orWhere('office_id', $user->office_id);
                    }
                });
        }, fn (InspectionAcceptanceRecord $record) => $this->normalizeInspection($record), $limit);
    }

    public function getAppDraftsForBacSecretariat(User $user, ?int $limit = null): Collection
    {
        if (! $this->canCreateBacSecretariatDocuments($user)) {
            return collect();
        }

        return $this->modelDrafts(AnnualProcurementPlan::class, function (Builder $query) {
            $query->with(['office', 'preparedBy', 'submittedBy'])
                ->where('status', AnnualProcurementPlan::STATUS_DRAFT);
        }, fn (AnnualProcurementPlan $app) => $this->normalizeApp($app), $limit);
    }

    public function getSupplementalAppDraftsForBacSecretariat(User $user, ?int $limit = null): Collection
    {
        if (! $this->canCreateBacSecretariatDocuments($user)) {
            return collect();
        }

        return $this->modelDrafts(SupplementalApp::class, function (Builder $query) {
            $query->with(['requestingOffice', 'preparedBy', 'submittedBy', 'sourcePrDocument.submittingOffice'])
                ->where('status', SupplementalApp::STATUS_DRAFT);
        }, fn (SupplementalApp $supplementalApp) => $this->normalizeSupplementalApp($supplementalApp), $limit);
    }

    public function getBacResolutionDraftsForBacSecretariat(User $user, ?int $limit = null): Collection
    {
        if (! $this->canCreateBacSecretariatDocuments($user)) {
            return collect();
        }

        return $this->modelDrafts(BacResolution::class, function (Builder $query) {
            $query->with(['preparedBy', 'submittedBy', 'sourcePrDocument.submittingOffice'])
                ->where('status', BacResolution::STATUS_DRAFT);
        }, fn (BacResolution $resolution) => $this->normalizeBacResolution($resolution), $limit);
    }

    private function procurementDraftsForUser(User $user, string $status, string $documentType, string $routeBase, ?int $limit, bool $hasPrint = true): Collection
    {
        return $this->modelDrafts(ProcurementDocument::class, function (Builder $query) use ($user, $status) {
            $query->with(['submittingOffice', 'currentOffice', 'preparedBy', 'submittedBy', 'appConsolidation', 'appItem.appConsolidation'])
                ->where('status', $status);

            $this->scopeProcurementToUser($query, $user);
        }, fn (ProcurementDocument $document) => $this->normalizeProcurementDocument($document, $documentType, $routeBase, $hasPrint), $limit);
    }

    private function modelDrafts(string $modelClass, callable $configureQuery, callable $normalize, ?int $limit): Collection
    {
        /** @var Model $model */
        $model = new $modelClass();

        if (! Schema::hasTable($model->getTable())) {
            return collect();
        }

        $query = $modelClass::query();
        $configureQuery($query);

        $query->latest('updated_at');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get()->map($normalize)->values();
    }

    private function scopeProcurementToUser(Builder $query, User $user): void
    {
        $query->where(function (Builder $scoped) use ($user) {
            $scoped->where('prepared_by_user_id', $user->id)
                ->orWhere('submitted_by_user_id', $user->id)
                ->orWhere('assigned_to_user_id', $user->id);

            if ($this->canCreatePrDocumentsAsBacsec002($user)) {
                return;
            }

            if ($user->office_id) {
                $scoped->orWhere('submitting_office_id', $user->office_id)
                    ->orWhere('current_office_id', $user->office_id);
            }
        });
    }

    private function scopeLinkedDocumentToUser(Builder $query, User $user): void
    {
        $query->where(function (Builder $scoped) use ($user) {
            $scoped->where('prepared_by_user_id', $user->id)
                ->orWhere('submitted_by_user_id', $user->id)
                ->orWhereHas('sourcePrDocument', fn (Builder $source) => $this->scopeProcurementToUser($source, $user));
        });
    }

    private function scopePurchaseOrderToUser(Builder $query, User $user): void
    {
        $query->where(function (Builder $scoped) use ($user) {
            $scoped->where('prepared_by_user_id', $user->id)
                ->orWhere('submitted_by_user_id', $user->id)
                ->orWhereHas('sourcePrDocument', fn (Builder $source) => $this->scopeProcurementToUser($source, $user))
                ->orWhereHas('procurementDocument', fn (Builder $source) => $this->scopeProcurementToUser($source, $user));
        });
    }

    private function normalizeProcurementDocument(ProcurementDocument $document, string $documentType, string $routeBase, bool $hasPrint): array
    {
        return $this->baseDraft($document, [
            'document_type' => $documentType,
            'number' => $this->firstFilled(
                $document->displayNumber(),
                'Document #' . $document->getKey(),
            ),
            'title' => $document->title ?: $document->purpose ?: $document->description ?: $documentType . ' Draft',
            'office_name' => $document->submittingOffice?->name ?? $document->currentOffice?->name ?? 'Unassigned office',
            'prepared_by' => $document->preparedBy?->name ?? $document->submittedBy?->name ?? 'Unassigned',
            'app_reference' => $document->appConsolidation?->app_number ?? $document->appItem?->appConsolidation?->app_number,
            'total_amount' => $document->total_amount,
            'edit_url' => $this->url($routeBase . '.edit', $document),
            'show_url' => $this->url($routeBase . '.show', $document),
            'print_url' => $hasPrint ? $this->url($routeBase . '.print', $document) : null,
            'submit_url' => $this->url($routeBase . '.submit', $document),
            'submit_method' => 'POST',
        ]);
    }

    private function normalizeRfq(Rfq $rfq, string $routeBase): array
    {
        $sourceDocument = $rfq->sourcePrDocument ?: $rfq->sourceBacResolution?->sourcePrDocument;

        return $this->baseDraft($rfq, [
            'document_type' => 'RFQ',
            'number' => $this->firstFilled(
                $rfq->document_reference_number,
                $rfq->rfq_number,
                $this->sourceDocumentNumber($sourceDocument),
                'RFQ #' . $rfq->getKey(),
            ),
            'title' => $rfq->purpose ?: $rfq->sourcePrDocument?->title ?: 'Request for Quotation',
            'office_name' => $rfq->sourcePrDocument?->submittingOffice?->name ?? $rfq->preparedBy?->office ?? 'Unassigned office',
            'prepared_by' => $rfq->preparedBy?->name ?? $rfq->submittedBy?->name ?? 'Unassigned',
            'edit_url' => $this->url($routeBase . '.edit', $rfq),
            'show_url' => $this->url($routeBase . '.show', $rfq),
            'print_url' => $this->url($routeBase . '.print', $rfq),
            'submit_url' => $this->url($routeBase . '.submit', $rfq),
            'submit_method' => 'PATCH',
        ]);
    }

    private function normalizeAbstract(AbstractQuotation $abstract, string $routeBase): array
    {
        $sourceDocument = $abstract->sourcePrDocument
            ?: $abstract->sourceRfq?->sourcePrDocument
            ?: $abstract->sourceRfq?->sourceBacResolution?->sourcePrDocument
            ?: $abstract->sourceBacResolution?->sourcePrDocument;

        return $this->baseDraft($abstract, [
            'document_type' => 'Abstract',
            'number' => $this->firstFilled(
                $abstract->document_reference_number,
                $abstract->abstract_number,
                $this->sourceDocumentNumber($sourceDocument),
                'Abstract #' . $abstract->getKey(),
            ),
            'title' => $abstract->project_name ?: $abstract->purpose ?: 'Abstract of Quotations',
            'office_name' => $abstract->implementing_office ?: $abstract->sourcePrDocument?->submittingOffice?->name ?? $abstract->preparedBy?->office ?? 'Unassigned office',
            'prepared_by' => $abstract->preparedBy?->name ?? $abstract->submittedBy?->name ?? 'Unassigned',
            'edit_url' => $this->url($routeBase . '.edit', $abstract),
            'show_url' => $this->url($routeBase . '.show', $abstract),
            'print_url' => $this->url($routeBase . '.print', $abstract),
            'submit_url' => $this->url($routeBase . '.submit', $abstract),
            'submit_method' => 'PATCH',
        ]);
    }

    private function normalizePurchaseOrder(PurchaseOrder $purchaseOrder, string $routeBase): array
    {
        $sourceDocument = $purchaseOrder->sourcePrDocument
            ?: $purchaseOrder->procurementDocument
            ?: $purchaseOrder->sourceAbstract?->sourcePrDocument
            ?: $purchaseOrder->sourceAbstract?->sourceRfq?->sourcePrDocument
            ?: $purchaseOrder->sourceBacResolution?->sourcePrDocument;

        return $this->baseDraft($purchaseOrder, [
            'document_type' => 'Purchase Order',
            'number' => $this->firstFilled(
                $purchaseOrder->document_reference_number,
                $purchaseOrder->po_number,
                $this->sourceDocumentNumber($sourceDocument),
                'PO #' . $purchaseOrder->getKey(),
            ),
            'title' => $purchaseOrder->supplier_name ? 'Purchase Order - ' . $purchaseOrder->supplier_name : $purchaseOrder->sourcePrDocument?->title ?? 'Purchase Order',
            'office_name' => $purchaseOrder->sourcePrDocument?->submittingOffice?->name ?? $purchaseOrder->procurementDocument?->submittingOffice?->name ?? $purchaseOrder->preparedBy?->office ?? 'Unassigned office',
            'prepared_by' => $purchaseOrder->preparedBy?->name ?? $purchaseOrder->submittedBy?->name ?? 'Unassigned',
            'edit_url' => $this->url($routeBase . '.edit', $purchaseOrder),
            'show_url' => $this->url($routeBase . '.show', $purchaseOrder),
            'print_url' => $this->url($routeBase . '.print', $purchaseOrder),
            'submit_url' => $this->url($routeBase . '.submit', $purchaseOrder),
            'submit_method' => 'PATCH',
        ]);
    }

    private function normalizeInspection(InspectionAcceptanceRecord $record): array
    {
        $purchaseOrder = $record->purchaseOrder;

        return $this->baseDraft($record, [
            'document_type' => 'Inspection / Acceptance',
            'number' => $this->firstFilled(
                $record->document_reference_number,
                $purchaseOrder?->document_reference_number,
                $purchaseOrder?->po_number,
                $this->sourceDocumentNumber($record->sourcePrDocument ?: $purchaseOrder?->sourcePrDocument),
                'Inspection #' . $record->getKey(),
            ),
            'title' => $purchaseOrder?->supplier_name ? 'Inspection for ' . $purchaseOrder->supplier_name : $record->sourcePrDocument?->title ?? 'Inspection and Acceptance',
            'office_name' => $record->office?->name ?? $record->sourcePrDocument?->submittingOffice?->name ?? $purchaseOrder?->sourcePrDocument?->submittingOffice?->name ?? 'Unassigned office',
            'prepared_by' => $record->inspectedBy?->name ?? $record->acceptedBy?->name ?? $purchaseOrder?->preparedBy?->name ?? 'Unassigned',
            'edit_url' => $purchaseOrder ? $this->url('head-office.inspection.show', $purchaseOrder) : null,
            'show_url' => $purchaseOrder ? $this->url('head-office.inspection.show', $purchaseOrder) : null,
        ]);
    }

    private function normalizeApp(AnnualProcurementPlan $app): array
    {
        return $this->baseDraft($app, [
            'document_type' => 'APP',
            'number' => $this->firstFilled(
                $app->document_reference_number,
                $app->app_no,
                $app->app_number,
                'APP #' . $app->getKey(),
            ),
            'title' => $app->title ?: 'Annual Procurement Plan',
            'office_name' => $app->office?->name ?? $app->office_name ?? 'BAC Secretariat',
            'prepared_by' => $app->preparedBy?->name ?? $app->submittedBy?->name ?? 'Unassigned',
            'edit_url' => $this->url('bac-secretariat.app.edit', $app),
            'show_url' => $this->url('bac-secretariat.app.show', $app),
            'print_url' => $this->url('bac-secretariat.app.print', $app),
            'submit_url' => $this->url('bac-secretariat.app.submit', $app),
            'submit_method' => 'PATCH',
        ]);
    }

    private function normalizeSupplementalApp(SupplementalApp $supplementalApp): array
    {
        return $this->baseDraft($supplementalApp, [
            'document_type' => 'Supplemental APP',
            'number' => $this->firstFilled(
                $supplementalApp->supplemental_app_number,
                $this->sourceDocumentNumber($supplementalApp->sourcePrDocument),
                'Supplemental APP #' . $supplementalApp->getKey(),
            ),
            'title' => $supplementalApp->title ?: $supplementalApp->purpose ?: 'Supplemental APP',
            'office_name' => $supplementalApp->requestingOffice?->name ?? $supplementalApp->requesting_office_name ?? $supplementalApp->sourcePrDocument?->submittingOffice?->name ?? 'Unassigned office',
            'prepared_by' => $supplementalApp->preparedBy?->name ?? $supplementalApp->submittedBy?->name ?? 'Unassigned',
            'edit_url' => $this->url('bac-secretariat.supplemental-apps.edit', $supplementalApp),
            'show_url' => $this->url('bac-secretariat.supplemental-apps.show', $supplementalApp),
            'print_url' => $this->url('bac-secretariat.supplemental-apps.print', $supplementalApp),
            'submit_url' => $this->url('bac-secretariat.supplemental-apps.submit', $supplementalApp),
            'submit_method' => 'PATCH',
        ]);
    }

    private function normalizeBacResolution(BacResolution $resolution): array
    {
        return $this->baseDraft($resolution, [
            'document_type' => 'BAC Resolution',
            'number' => $this->firstFilled(
                $resolution->document_reference_number,
                $resolution->resolution_number,
                $this->sourceDocumentNumber($resolution->sourcePrDocument),
                $resolution->pr_number,
                'BAC Resolution #' . $resolution->getKey(),
            ),
            'title' => $resolution->title ?: $resolution->project_title ?: 'BAC Resolution',
            'office_name' => $resolution->requesting_office_name ?? $resolution->sourcePrDocument?->submittingOffice?->name ?? 'BAC Secretariat',
            'prepared_by' => $resolution->preparedBy?->name ?? $resolution->submittedBy?->name ?? 'Unassigned',
            'edit_url' => $this->url('bac-secretariat.resolutions.edit', $resolution),
            'show_url' => $this->url('bac-secretariat.resolutions.show', $resolution),
            'print_url' => $this->url('bac-secretariat.resolutions.print', $resolution),
            'submit_url' => $this->url('bac-secretariat.resolutions.submit', $resolution),
            'submit_method' => 'PATCH',
        ]);
    }

    private function baseDraft(Model $model, array $overrides): array
    {
        return array_merge([
            'document_type' => 'Document',
            'number' => 'Document #' . $model->getKey(),
            'title' => 'Untitled draft',
            'office_name' => 'Unassigned office',
            'prepared_by' => 'Unassigned',
            'status' => $model->status ?? 'draft',
            'status_label' => $this->statusLabel($model->status ?? 'draft'),
            'updated_at' => $model->updated_at,
            'edit_url' => null,
            'show_url' => null,
            'print_url' => null,
            'submit_url' => null,
            'submit_method' => 'POST',
        ], $overrides);
    }

    private function canCreateHeadOfficeDocuments(User $user): bool
    {
        return $user->roleSlug() === 'head-office';
    }

    private function canCreatePrDocumentsAsBacsec002(User $user): bool
    {
        return method_exists($user, 'hasBacsec002PurchaseRequestCapability')
            && $user->hasBacsec002PurchaseRequestCapability();
    }

    private function canCreateBacSecretariatDocuments(User $user): bool
    {
        return $user->roleSlug() === 'bac-secretariat';
    }

    private function url(string $routeName, Model $model): ?string
    {
        return Route::has($routeName) ? route($routeName, $model) : null;
    }

    private function sourceDocumentNumber(?ProcurementDocument $document): ?string
    {
        return $this->firstFilled(
            $document?->tracking_number,
            $document?->document_reference_number,
            $document?->pr_no,
            $document?->ppmp_no,
        );
    }

    private function firstFilled(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function statusLabel(?string $status): string
    {
        $status = trim((string) ($status ?: 'draft'));

        return match (true) {
            str_contains($status, 'draft') => 'Draft',
            str_contains($status, 'submitted') => 'Submitted',
            str_contains($status, 'returned') => 'Returned',
            str_contains($status, 'approved') => 'Approved',
            str_contains($status, 'accepted') => 'Accepted',
            str_contains($status, 'confirmed') => 'Confirmed',
            str_contains($status, 'cancelled') => 'Cancelled',
            default => Str::of($status)->replace(['_', '-'], ' ')->title()->toString(),
        };
    }
}
