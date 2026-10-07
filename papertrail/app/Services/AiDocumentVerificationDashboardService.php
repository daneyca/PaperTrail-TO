<?php

namespace App\Services;

use App\Models\AnnualProcurementPlan;
use App\Models\DocumentAttachment;
use App\Models\ProcurementDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class AiDocumentVerificationDashboardService
{
    private const AI_PENDING_STATUSES = [
        DocumentAttachment::AI_PENDING,
        DocumentAttachment::AI_QUEUED,
        null,
    ];

    private const AI_PROCESSING_STATUSES = [
        DocumentAttachment::AI_QUEUED,
    ];

    public function quickViewFor(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $variant = $this->variantFor($user);

        if (! $variant) {
            return null;
        }

        $base = $this->attachmentQueryFor($user);
        $stats = $this->statsFor($user, $variant, $base);
        $recentUploads = $this->recentUploadsFor($user, $base);
        $documentRows = $this->documentRowsFor($user, $variant);

        $indexUrl = $this->routeUrl('ai-document-verification.index', $this->defaultIndexParamsFor($variant));

        return [
            'variant' => $variant,
            'title' => 'AI Document Verification',
            'description' => 'Upload and review scanned procurement files for OCR, metadata extraction, and completeness checking.',
            'stats' => $stats,
            'recentUploads' => $recentUploads,
            'documentRows' => $documentRows,
            'indexUrl' => $indexUrl,
            'uploadUrl' => $this->canUpload($user) && $indexUrl
                ? $indexUrl . '#upload-document'
                : null,
            'primaryActionLabel' => $this->primaryActionLabelFor($variant),
            'secondaryActionLabel' => $variant === 'admin' ? 'View All Uploads' : 'View Pending Verification',
            'canUpload' => $this->canUpload($user),
            'canManageRules' => $user->isAdmin() && Route::has('admin.settings.ai-rules.index'),
            'rulesUrl' => $user->isAdmin() && Route::has('admin.settings.ai-rules.index')
                ? route('admin.settings.ai-rules.index')
                : null,
            'listTitle' => $variant === 'admin' || $variant === 'end_user'
                ? 'Recent AI Document Uploads'
                : ($variant === 'bac_secretariat' ? 'Documents Needing Verification' : 'Documents for Approval Review'),
        ];
    }

    public function indexData(User $user, array $filters = []): array
    {
        $variant = $this->variantFor($user) ?? 'end_user';
        $base = $this->attachmentQueryFor($user);
        $query = clone $base;

        $this->applyFilters($query, $filters);

        /** @var LengthAwarePaginator $uploads */
        $uploads = $query
            ->with(['uploadedBy.assignedOffice', 'office', 'procurementDocument.submittingOffice', 'procurementDocument.currentOffice'])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $uploads->setCollection(
            $uploads->getCollection()->map(fn (DocumentAttachment $attachment) => $this->formatUpload($attachment, $user))
        );

        return [
            'variant' => $variant,
            'stats' => $this->statsFor($user, $variant, $base),
            'uploads' => $uploads,
            'filters' => [
                'search' => $filters['search'] ?? null,
                'ai_status' => $filters['ai_status'] ?? null,
                'category' => $filters['category'] ?? null,
                'scope' => $filters['scope'] ?? $this->defaultScopeFor($variant),
            ],
            'canUpload' => $this->canUpload($user),
            'categories' => $this->categoryOptions(),
            'statusOptions' => [
                'pending' => 'Pending AI Review',
                'processing' => 'Processing',
                'completed' => 'Completed',
                'failed' => 'Failed',
            ],
        ];
    }

    public function attachmentQueryFor(User $user): Builder
    {
        $query = DocumentAttachment::query()->active();

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->hasRole('head_office') || $user->hasRole(User::ROLE_HEAD_OFFICE)) {
            return $query->where('uploaded_by_user_id', $user->id);
        }

        if ($user->hasRole('bac_secretariat') || $user->hasRole(User::ROLE_BAC_SECRETARIAT)) {
            return $query->where(function (Builder $builder) use ($user) {
                $builder->where('uploaded_by_user_id', $user->id)
                    ->orWhere('attachment_category', DocumentAttachment::CATEGORY_BAC_DOCUMENT)
                    ->orWhereHas('procurementDocument', function (Builder $document) use ($user) {
                        $document->where(function (Builder $visible) use ($user) {
                            $visible->visibleToBacSecretariat($user);
                        })->orWhereIn('status', $this->bacRelevantStatuses());
                    });

                if ($user->office_id) {
                    $builder->orWhere('office_id', $user->office_id);
                }
            });
        }

        if ($user->hasRole('approving_authority') || $user->hasRole(User::ROLE_APPROVING_AUTHORITY)) {
            $appIds = AnnualProcurementPlan::query()
                ->whereIn('status', [AnnualProcurementPlan::STATUS_SUBMITTED, AnnualProcurementPlan::STATUS_CONSOLIDATED])
                ->pluck('id');

            return $query->where(function (Builder $builder) use ($user, $appIds) {
                $builder->whereHas('procurementDocument', function (Builder $document) use ($user) {
                    $document->assignedToApprovingAuthority($user);
                });

                if ($appIds->isNotEmpty()) {
                    $builder->orWhere(function (Builder $app) use ($appIds) {
                        $app->whereIn('document_type', ['app', 'annual_procurement_plan'])
                            ->whereIn('document_id', $appIds->all());
                    });
                }
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public function canUpload(User $user): bool
    {
        return $user->isAdmin()
            || $user->hasRole('head_office')
            || $user->hasRole(User::ROLE_HEAD_OFFICE)
            || $user->hasRole('bac_secretariat')
            || $user->hasRole(User::ROLE_BAC_SECRETARIAT);
    }

    public function canViewAttachment(?User $user, DocumentAttachment $attachment): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ((int) $attachment->uploaded_by_user_id === (int) $user->id) {
            return true;
        }

        $matchesQuery = (clone $this->attachmentQueryFor($user))
            ->whereKey($attachment->getKey())
            ->exists();

        if (! $matchesQuery) {
            return false;
        }

        if (! $attachment->is_confidential) {
            return true;
        }

        return $user->hasRole('bac_secretariat')
            || $user->hasRole(User::ROLE_BAC_SECRETARIAT)
            || $user->hasRole('approving_authority')
            || $user->hasRole(User::ROLE_APPROVING_AUTHORITY);
    }

    public function formatUpload(DocumentAttachment $attachment, User $viewer): array
    {
        return [
            'id' => $attachment->id,
            'fileName' => $attachment->displayName(),
            'uploadedBy' => $attachment->uploadedBy?->name ?? 'System',
            'office' => $attachment->office?->name
                ?? $attachment->office_name
                ?? $attachment->procurementDocument?->submittingOffice?->name
                ?? 'N/A',
            'category' => $this->categoryLabel($attachment->attachment_category),
            'documentType' => $this->documentTypeLabel($attachment->document_type ?? $attachment->procurementDocument?->document_type),
            'trackingNumber' => $attachment->tracking_number
                ?? $attachment->procurementDocument?->tracking_number
                ?? 'N/A',
            'aiStatus' => $this->aiStatusLabel($attachment->ai_analysis_status),
            'aiStatusKey' => $this->aiStatusKey($attachment->ai_analysis_status),
            'ocrStatus' => $this->ocrStatusLabel($attachment->ocr_status),
            'metadataSummary' => blank($attachment->extracted_metadata) ? 'Not yet processed' : 'Processed metadata available',
            'isConfidential' => (bool) $attachment->is_confidential,
            'uploadedAt' => $attachment->created_at?->format('M d, Y h:i A') ?? 'N/A',
            'viewUrl' => $this->canViewAttachment($viewer, $attachment) && Route::has('document-attachments.view')
                ? route('document-attachments.view', $attachment)
                : null,
            'downloadUrl' => $this->canViewAttachment($viewer, $attachment) && Route::has('document-attachments.download')
                ? route('document-attachments.download', $attachment)
                : null,
        ];
    }

    private function statsFor(User $user, string $variant, Builder $base): array
    {
        $stats = match ($variant) {
            'admin' => [
                ['label' => 'Total AI Uploads', 'value' => (string) (clone $base)->count(), 'accent' => 'navy', 'href' => $this->routeUrl('ai-document-verification.index', ['scope' => 'all'])],
                ['label' => 'Pending AI Review', 'value' => (string) $this->pendingCount($base), 'accent' => 'gold', 'href' => $this->routeUrl('ai-document-verification.index', ['scope' => 'all', 'ai_status' => 'pending'])],
                ['label' => 'Processing', 'value' => (string) $this->processingCount($base), 'accent' => 'blue', 'href' => $this->routeUrl('ai-document-verification.index', ['scope' => 'all', 'ai_status' => 'processing'])],
                ['label' => 'Completed', 'value' => (string) (clone $base)->where('ai_analysis_status', DocumentAttachment::AI_COMPLETED)->count(), 'accent' => 'green', 'href' => $this->routeUrl('ai-document-verification.index', ['scope' => 'all', 'ai_status' => 'completed'])],
                ['label' => 'Failed', 'value' => (string) $this->failedCount($base), 'accent' => 'red', 'href' => $this->routeUrl('ai-document-verification.index', ['scope' => 'all', 'ai_status' => 'failed'])],
                ['label' => 'Confidential Uploads', 'value' => (string) (clone $base)->where('is_confidential', true)->count(), 'accent' => 'gray', 'href' => $this->routeUrl('ai-document-verification.index', ['scope' => 'all'])],
            ],
            'bac_secretariat' => [
                ['label' => 'Incoming Files for AI Review', 'value' => (string) $this->pendingCount($base), 'accent' => 'gold', 'href' => $this->routeUrl('ai-document-verification.index', ['scope' => 'assigned', 'ai_status' => 'pending'])],
                ['label' => 'Pending AI Review', 'value' => (string) $this->pendingCount($base), 'accent' => 'navy', 'href' => $this->routeUrl('ai-document-verification.index', ['scope' => 'assigned', 'ai_status' => 'pending'])],
                ['label' => 'Recent BAC Verification Uploads', 'value' => (string) (clone $base)->whereDate('created_at', '>=', now()->subDays(7)->toDateString())->count(), 'accent' => 'blue', 'href' => $this->routeUrl('ai-document-verification.index', ['scope' => 'assigned'])],
            ],
            'approver' => [
                ['label' => 'Pending Approvals with AI Review', 'value' => (string) $this->approverPendingDocumentCount($user), 'accent' => 'gold', 'href' => $this->routeUrl('approving-authority.pending.index')],
                ['label' => 'Pending AI Verification', 'value' => (string) $this->pendingCount($base), 'accent' => 'navy', 'href' => $this->routeUrl('ai-document-verification.index', ['scope' => 'assigned', 'ai_status' => 'pending'])],
                ['label' => 'Completed AI Verification', 'value' => (string) (clone $base)->where('ai_analysis_status', DocumentAttachment::AI_COMPLETED)->count(), 'accent' => 'green', 'href' => $this->routeUrl('ai-document-verification.index', ['scope' => 'assigned', 'ai_status' => 'completed'])],
                ['label' => 'Returned / Needs Correction', 'value' => (string) $this->approverReturnedDocumentCount($user), 'accent' => 'blue', 'href' => $this->routeUrl('approving-authority.returned.index')],
            ],
            default => [
                ['label' => 'My Pending AI Review', 'value' => (string) $this->pendingCount($base), 'accent' => 'gold', 'href' => $this->routeUrl('ai-document-verification.index', ['scope' => 'mine', 'ai_status' => 'pending'])],
                ['label' => 'My Completed Verifications', 'value' => (string) (clone $base)->where('ai_analysis_status', DocumentAttachment::AI_COMPLETED)->count(), 'accent' => 'green', 'href' => $this->routeUrl('ai-document-verification.index', ['scope' => 'mine', 'ai_status' => 'completed'])],
                ['label' => 'My Failed Verifications', 'value' => (string) $this->failedCount($base), 'accent' => 'red', 'href' => $this->routeUrl('ai-document-verification.index', ['scope' => 'mine', 'ai_status' => 'failed'])],
                ['label' => 'Recent Uploads', 'value' => (string) (clone $base)->whereDate('created_at', '>=', now()->subDays(7)->toDateString())->count(), 'accent' => 'blue', 'href' => $this->routeUrl('ai-document-verification.index', ['scope' => 'mine'])],
            ],
        };

        $missingCount = $this->missingRequirementsCount($base);

        if ($variant === 'bac_secretariat' && $missingCount > 0) {
            $stats[] = ['label' => 'Files with Missing Requirements', 'value' => (string) $missingCount, 'accent' => 'red', 'href' => $this->routeUrl('ai-document-verification.index', ['scope' => 'assigned'])];
        }

        return $stats;
    }

    private function recentUploadsFor(User $user, Builder $base): Collection
    {
        return (clone $base)
            ->with(['uploadedBy.assignedOffice', 'office', 'procurementDocument.submittingOffice', 'procurementDocument.currentOffice'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (DocumentAttachment $attachment) => $this->formatUpload($attachment, $user));
    }

    private function documentRowsFor(User $user, string $variant): Collection
    {
        if ($variant === 'bac_secretariat') {
            return ProcurementDocument::query()
                ->with(['submittingOffice', 'currentOffice'])
                ->whereIn('status', $this->bacRelevantStatuses())
                ->where(function (Builder $document) use ($user) {
                    $document->visibleToBacSecretariat($user)
                        ->orWhereIn('document_type', ['PPMP', 'PR', 'Purchase Request']);
                })
                ->whereHas('attachments', fn (Builder $attachment) => $this->pendingOrProcessing($attachment))
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (ProcurementDocument $document) => $this->formatDocumentRow($document, $user, 'bac_secretariat'));
        }

        if ($variant === 'approver') {
            $procurementRows = ProcurementDocument::query()
                ->with(['submittingOffice', 'currentOffice'])
                ->assignedToApprovingAuthority($user)
                ->whereHas('attachments', fn (Builder $attachment) => $this->pendingOrProcessing($attachment))
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (ProcurementDocument $document) => $this->formatDocumentRow($document, $user, 'approver'));

            $remaining = max(0, 5 - $procurementRows->count());

            if ($remaining === 0) {
                return $procurementRows;
            }

            $appIds = DocumentAttachment::query()
                ->active()
                ->whereIn('document_type', ['app', 'annual_procurement_plan'])
                ->whereNotNull('document_id')
                ->where(fn (Builder $attachment) => $this->pendingOrProcessing($attachment))
                ->pluck('document_id')
                ->unique()
                ->values();

            $appRows = AnnualProcurementPlan::query()
                ->whereIn('id', $appIds)
                ->whereIn('status', [AnnualProcurementPlan::STATUS_SUBMITTED, AnnualProcurementPlan::STATUS_CONSOLIDATED])
                ->latest()
                ->limit($remaining)
                ->get()
                ->map(fn (AnnualProcurementPlan $app) => [
                    'trackingNumber' => $app->displayNumber(),
                    'documentType' => 'APP',
                    'requestingOffice' => $app->office_name ?? $app->office?->name ?? 'BAC Secretariat',
                    'amount' => $this->money($app->total_estimated_budget),
                    'aiStatus' => 'Pending AI Review',
                    'aiStatusKey' => 'pending',
                    'currentStage' => 'Head of the Procuring Entity Review',
                    'submittedDate' => $app->submitted_at?->format('M d, Y') ?? $app->created_at?->format('M d, Y') ?? 'N/A',
                    'viewUrl' => $this->routeUrl('bac-secretariat.app.show', ['app' => $app->id]),
                    'verificationUrl' => $this->routeUrl('ai-document-verification.index', ['scope' => 'assigned', 'search' => $app->displayNumber()]),
                ]);

            return $procurementRows->concat($appRows)->values();
        }

        return collect();
    }

    private function formatDocumentRow(ProcurementDocument $document, User $user, string $variant): array
    {
        return [
            'trackingNumber' => $document->tracking_number ?? $document->pr_no ?? 'Document #' . $document->id,
            'documentType' => $document->document_type ?? 'Document',
            'requestingOffice' => $document->submittingOffice?->name ?? $document->department_name ?? 'N/A',
            'amount' => $this->money($document->total_amount),
            'aiStatus' => $this->aiStatusForProcurementDocument($document),
            'aiStatusKey' => $this->aiStatusKeyForProcurementDocument($document),
            'currentStage' => $document->stage ?? 'N/A',
            'submittedDate' => $document->submitted_at?->format('M d, Y') ?? $document->created_at?->format('M d, Y') ?? 'N/A',
            'viewUrl' => $this->documentUrlFor($document, $variant),
            'verificationUrl' => $this->routeUrl('ai-document-verification.index', ['scope' => $variant === 'bac_secretariat' ? 'assigned' : 'assigned', 'search' => $document->tracking_number]),
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('original_filename', 'like', "%{$search}%")
                    ->orWhere('original_name', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhere('document_type', 'like', "%{$search}%")
                    ->orWhere('office_name', 'like', "%{$search}%");
            });
        }

        if (filled($filters['category'] ?? null)) {
            $query->where('attachment_category', $filters['category']);
        }

        match ($filters['ai_status'] ?? null) {
            'pending' => $query->where(function (Builder $builder) {
                $builder->whereIn('ai_analysis_status', [DocumentAttachment::AI_PENDING, DocumentAttachment::AI_QUEUED])
                    ->orWhereNull('ai_analysis_status');
            }),
            'processing' => $query->where(function (Builder $builder) {
                $builder->whereIn('ai_analysis_status', self::AI_PROCESSING_STATUSES)
                    ->orWhere('ocr_status', DocumentAttachment::OCR_PROCESSING);
            }),
            'completed' => $query->where('ai_analysis_status', DocumentAttachment::AI_COMPLETED),
            'failed' => $query->where(function (Builder $builder) {
                $builder->where('ai_analysis_status', DocumentAttachment::AI_FAILED)
                    ->orWhere('ocr_status', DocumentAttachment::OCR_FAILED);
            }),
            default => null,
        };
    }

    private function pendingOrProcessing(Builder $query): Builder
    {
        return $query->active()->where(function (Builder $builder) {
            $builder->whereIn('ai_analysis_status', [DocumentAttachment::AI_PENDING, DocumentAttachment::AI_QUEUED])
                ->orWhereNull('ai_analysis_status')
                ->orWhere('ocr_status', DocumentAttachment::OCR_PROCESSING);
        });
    }

    private function pendingCount(Builder $base): int
    {
        return (clone $base)
            ->where(function (Builder $builder) {
                $builder->whereIn('ai_analysis_status', [DocumentAttachment::AI_PENDING, DocumentAttachment::AI_QUEUED])
                    ->orWhereNull('ai_analysis_status');
            })
            ->count();
    }

    private function processingCount(Builder $base): int
    {
        return (clone $base)
            ->where(function (Builder $builder) {
                $builder->whereIn('ai_analysis_status', self::AI_PROCESSING_STATUSES)
                    ->orWhere('ocr_status', DocumentAttachment::OCR_PROCESSING);
            })
            ->count();
    }

    private function failedCount(Builder $base): int
    {
        return (clone $base)
            ->where(function (Builder $builder) {
                $builder->where('ai_analysis_status', DocumentAttachment::AI_FAILED)
                    ->orWhere('ocr_status', DocumentAttachment::OCR_FAILED);
            })
            ->count();
    }

    private function missingRequirementsCount(Builder $base): int
    {
        return (clone $base)
            ->whereNotNull('extracted_metadata')
            ->get(['extracted_metadata'])
            ->filter(function (DocumentAttachment $attachment) {
                $metadata = $attachment->extracted_metadata ?: [];

                return filled(data_get($metadata, 'missing_requirements'))
                    || filled(data_get($metadata, 'requirements.missing'));
            })
            ->count();
    }

    private function approverPendingDocumentCount(User $user): int
    {
        return ProcurementDocument::query()
            ->assignedToApprovingAuthority($user)
            ->whereHas('attachments', fn (Builder $attachment) => $this->pendingOrProcessing($attachment))
            ->count();
    }

    private function approverReturnedDocumentCount(User $user): int
    {
        return ProcurementDocument::query()
            ->where('status', ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY)
            ->where(function (Builder $owner) use ($user) {
                $owner->where('approved_by_user_id', $user->id)
                    ->orWhereHas('documentApprovals', fn (Builder $approval) => $approval->where('approved_by_user_id', $user->id));
            })
            ->whereHas('attachments')
            ->count();
    }

    private function aiStatusForProcurementDocument(ProcurementDocument $document): string
    {
        return $this->aiStatusLabel($this->aiStatusKeyForProcurementDocument($document));
    }

    private function aiStatusKeyForProcurementDocument(ProcurementDocument $document): string
    {
        $query = DocumentAttachment::query()
            ->active()
            ->where('procurement_document_id', $document->id);

        if ((clone $query)->where('ai_analysis_status', DocumentAttachment::AI_FAILED)->exists()) {
            return 'failed';
        }

        if ((clone $query)->where(function (Builder $builder) {
            $builder->where('ai_analysis_status', DocumentAttachment::AI_QUEUED)
                ->orWhere('ocr_status', DocumentAttachment::OCR_PROCESSING);
        })->exists()) {
            return 'processing';
        }

        if ((clone $query)->where('ai_analysis_status', DocumentAttachment::AI_COMPLETED)->exists()) {
            return 'completed';
        }

        return 'pending';
    }

    private function variantFor(User $user): ?string
    {
        if ($user->isAdmin()) {
            return 'admin';
        }

        if ($user->hasRole('head_office') || $user->hasRole(User::ROLE_HEAD_OFFICE)) {
            return 'end_user';
        }

        if ($user->hasRole('bac_secretariat') || $user->hasRole(User::ROLE_BAC_SECRETARIAT)) {
            return 'bac_secretariat';
        }

        if ($user->hasRole('approving_authority') || $user->hasRole(User::ROLE_APPROVING_AUTHORITY)) {
            return 'approver';
        }

        return null;
    }

    private function primaryActionLabelFor(string $variant): string
    {
        return match ($variant) {
            'admin' => 'Upload Document',
            'bac_secretariat' => 'View Incoming Verification',
            'approver' => 'View Pending Verification',
            default => 'Upload Document',
        };
    }

    private function defaultScopeFor(string $variant): string
    {
        return match ($variant) {
            'admin' => 'all',
            'bac_secretariat', 'approver' => 'assigned',
            default => 'mine',
        };
    }

    private function defaultIndexParamsFor(string $variant): array
    {
        return ['scope' => $this->defaultScopeFor($variant)];
    }

    private function documentUrlFor(ProcurementDocument $document, string $variant): ?string
    {
        if ($variant === 'bac_secretariat') {
            if ($document->document_type === 'PPMP') {
                return $this->routeUrl('bac-secretariat.ppmp.show', ['document' => $document->id]);
            }

            if (in_array($document->document_type, ['PR', 'Purchase Request'], true)) {
                return $this->routeUrl('bac-secretariat.pr.show', ['document' => $document->id]);
            }

            return $this->routeUrl('bac-secretariat.incoming.show', ['document' => $document->id]);
        }

        if ($variant === 'approver') {
            return $this->routeUrl('approving-authority.pending.show', ['document' => $document->id]);
        }

        return $this->routeUrl('head-office.documents.show', ['document' => $document->id]);
    }

    private function categoryOptions(): array
    {
        return [
            DocumentAttachment::CATEGORY_SCANNED_DOCUMENT => 'Scanned Document',
            DocumentAttachment::CATEGORY_SUPPORTING_DOCUMENT => 'Supporting Document',
            DocumentAttachment::CATEGORY_BAC_DOCUMENT => 'BAC Document',
            DocumentAttachment::CATEGORY_QUOTATION => 'Quotation',
            DocumentAttachment::CATEGORY_ELIGIBILITY_DOCUMENT => 'Eligibility Document',
            DocumentAttachment::CATEGORY_SIGNED_DOCUMENT => 'Signed Document',
            DocumentAttachment::CATEGORY_OTHER => 'Other',
        ];
    }

    private function categoryLabel(?string $category): string
    {
        return $this->categoryOptions()[$category ?: DocumentAttachment::CATEGORY_SCANNED_DOCUMENT]
            ?? Str::of($category ?: 'scanned_document')->replace('_', ' ')->title()->toString();
    }

    private function aiStatusLabel(?string $status): string
    {
        return match ($status) {
            DocumentAttachment::AI_COMPLETED, 'completed' => 'Completed',
            DocumentAttachment::AI_FAILED, 'failed' => 'Failed',
            DocumentAttachment::AI_QUEUED, 'queued', 'processing' => 'Processing',
            DocumentAttachment::AI_SKIPPED, 'skipped' => 'Not yet processed',
            default => 'Pending AI Review',
        };
    }

    private function aiStatusKey(?string $status): string
    {
        return match ($status) {
            DocumentAttachment::AI_COMPLETED, 'completed' => 'completed',
            DocumentAttachment::AI_FAILED, 'failed' => 'failed',
            DocumentAttachment::AI_QUEUED, 'queued', 'processing' => 'processing',
            default => 'pending',
        };
    }

    private function ocrStatusLabel(?string $status): string
    {
        return Str::of($status ?: DocumentAttachment::OCR_PENDING)->replace('_', ' ')->title()->toString();
    }

    private function documentTypeLabel(?string $type): string
    {
        return Str::of($type ?: 'Upload')->replace(['_', '-'], ' ')->title()->toString();
    }

    private function bacRelevantStatuses(): array
    {
        return [
            ProcurementDocument::STATUS_PENDING_PPMP_REVIEW,
            ProcurementDocument::STATUS_UNDER_PPMP_REVIEW,
            ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
            ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION,
            ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW,
            ProcurementDocument::STATUS_RECEIVED_BY_BAC_SECRETARIAT,
            ProcurementDocument::STATUS_UNDER_BAC_SECRETARIAT_REVIEW,
            ProcurementDocument::STATUS_READY_FOR_DOCUMENT_ROUTING,
            ProcurementDocument::STATUS_SUBMITTED_TO_BAC_SECRETARIAT,
            ProcurementDocument::STATUS_PR_SUBMITTED,
            ProcurementDocument::STATUS_PR_RECEIVED_BY_BAC_SECRETARIAT,
            ProcurementDocument::STATUS_UNDER_PR_VALIDATION,
            ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION,
        ];
    }

    private function routeUrl(string $name, array $parameters = []): ?string
    {
        return Route::has($name) ? route($name, $parameters) : null;
    }

    private function money(mixed $value): string
    {
        return 'PHP ' . number_format((float) $value, 2);
    }
}
