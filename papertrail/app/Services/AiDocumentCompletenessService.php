<?php

namespace App\Services;

use App\Models\AiDocumentCheck;
use App\Models\AbstractQuotation;
use App\Models\AnnualProcurementPlan;
use App\Models\BacResolution;
use App\Models\DocumentAttachment;
use App\Models\DocumentRequirementRule;
use App\Models\InspectionAcceptanceRecord;
use App\Models\Ppmp;
use App\Models\ProcurementDocument;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\SupplementalApp;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class AiDocumentCompletenessService
{
    private const SUPPORTED_TYPES = [
        'ppmp',
        'ppmp_record',
        'app',
        'supplemental_app',
        'purchase_request',
        'bac_resolution',
        'rfq',
        'abstract',
        'purchase_order',
        'inspection_acceptance',
    ];

    public function __construct(
        private readonly OpenAIService $openAI,
        private readonly DocumentResolverService $documents,
    ) {
    }

    public function checkDocumentCompleteness(User $user, string $documentType, int $documentId): AiDocumentCheck
    {
        $startedAt = microtime(true);
        $type = $this->canonicalType($documentType);
        $document = $this->documents->resolve($type, $documentId);

        if (! in_array($type, self::SUPPORTED_TYPES, true)) {
            throw new InvalidArgumentException('AI completeness checking is not available for this document type yet.');
        }

        if (! $this->documents->canView($user, $document)) {
            AuditLogger::denied('unauthorized_ai_check_attempt', [
                'module' => 'AI Document Completeness',
                'document_type' => $type,
                'document_id' => $document->getKey(),
                'tracking_number' => $this->documents->displayTrackingNumber($document),
                'user_id' => $user->id,
                'severity' => 'warning',
            ]);

            throw new AuthorizationException('You do not have permission to check this document.');
        }

        $trackingNumber = $this->documents->displayTrackingNumber($document);

        $check = AiDocumentCheck::create([
            'user_id' => $user->id,
            'document_type' => $type,
            'document_id' => $document->getKey(),
            'document_tracking_number' => $trackingNumber,
            'status' => AiDocumentCheck::STATUS_PROCESSING,
        ]);

        AuditLogger::ai('ai_completeness_check_started', $document, [
            'module' => 'AI Document Completeness',
            'document_type' => $type,
            'document_id' => $document->getKey(),
            'tracking_number' => $trackingNumber,
            'ai_check_id' => $check->id,
            'user_id' => $user->id,
            'execution_time' => 0,
        ]);

        Log::info('ai_check_started', [
            'user_id' => $user->id,
            'document_type' => $type,
            'document_id' => $document->getKey(),
            'ai_check_id' => $check->id,
            'execution_time' => 0,
        ]);

        try {
            $context = $this->buildContext($document, $type);
            $manualFindings = $this->manualFindings($context);
            $response = null;
            $parsed = [];
            $usedOpenAi = true;

            try {
                $response = $this->openAI->chat($this->messagesFor($context, $manualFindings), [
                    'temperature' => 0,
                    'max_tokens' => 350,
                    'response_format' => ['type' => 'json_object'],
                ]);
                $parsed = $this->parseJsonResponse($response);
            } catch (Throwable $openAiException) {
                $usedOpenAi = false;

                Log::warning('ai_check_openai_unavailable_using_rules', [
                    'user_id' => $user->id,
                    'document_type' => $type,
                    'document_id' => $document->getKey(),
                    'ai_check_id' => $check->id,
                    'exception' => $openAiException::class,
                    'message' => $openAiException->getMessage(),
                ]);
            }

            $completed = $this->uniqueList(array_merge(
                $manualFindings['completed_requirements'],
                Arr::wrap($parsed['completed_items'] ?? $parsed['completed_requirements'] ?? [])
            ));
            $missing = $this->uniqueList(array_merge(
                $manualFindings['missing_requirements'],
                Arr::wrap($parsed['missing_requirements'] ?? [])
            ));
            $warnings = $this->uniqueList(array_merge(
                $manualFindings['warnings'],
                Arr::wrap($parsed['warnings'] ?? [])
            ));
            $recommendation = trim((string) ($parsed['recommendation'] ?? $parsed['recommendations'] ?? ''));
            $recommendation = $recommendation !== ''
                ? $recommendation
                : $this->fallbackRecommendation($missing, $warnings);
            $score = $this->normalizeScore($parsed['score'] ?? null, $missing, $warnings);
            $executionTime = round(microtime(true) - $startedAt, 3);

            $check->update([
                'completeness_score' => $score,
                'status' => AiDocumentCheck::STATUS_COMPLETED,
                'missing_requirements' => $missing,
                'warnings' => $warnings,
                'recommendations' => $recommendation,
                'ai_response' => $response ?: json_encode([
                    'mode' => 'rule_based_fallback',
                    'message' => 'OpenAI explanation was unavailable; system requirements were checked locally.',
                ]),
            ]);

            $check->setAttribute('completed_requirements', $completed);
            $check->setRelation('user', $user);

            AuditLogger::ai('ai_completeness_check_completed', $document, [
                'module' => 'AI Document Completeness',
                'document_type' => $type,
                'document_id' => $document->getKey(),
                'tracking_number' => $trackingNumber,
                'ai_check_id' => $check->id,
                'user_id' => $user->id,
                'score' => $score,
                'missing_count' => count($missing),
                'warning_count' => count($warnings),
                'used_openai' => $usedOpenAi,
                'execution_time' => $executionTime,
            ]);

            Log::info('ai_check_completed', [
                'user_id' => $user->id,
                'document_type' => $type,
                'document_id' => $document->getKey(),
                'ai_check_id' => $check->id,
                'score' => $score,
                'used_openai' => $usedOpenAi,
                'execution_time' => $executionTime,
            ]);

            return $check;
        } catch (Throwable $exception) {
            $executionTime = round(microtime(true) - $startedAt, 3);

            $check->update([
                'status' => AiDocumentCheck::STATUS_FAILED,
                'ai_response' => $exception::class . ': ' . $exception->getMessage(),
            ]);

            Log::error('AI document completeness check failed.', [
                'document_type' => $type,
                'document_id' => $document->getKey(),
                'ai_check_id' => $check->id,
                'user_id' => $user->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'execution_time' => $executionTime,
            ]);

            Log::warning('ai_check_failed', [
                'user_id' => $user->id,
                'document_type' => $type,
                'document_id' => $document->getKey(),
                'ai_check_id' => $check->id,
                'exception' => $exception::class,
                'execution_time' => $executionTime,
            ]);

            AuditLogger::ai('ai_completeness_check_failed', $document, [
                'module' => 'AI Document Completeness',
                'document_type' => $type,
                'document_id' => $document->getKey(),
                'tracking_number' => $trackingNumber,
                'ai_check_id' => $check->id,
                'user_id' => $user->id,
                'severity' => 'warning',
                'failure' => $exception::class,
                'execution_time' => $executionTime,
            ]);

            throw new RuntimeException('AI completeness checking is temporarily unavailable.', previous: $exception);
        }
    }

    private function canonicalType(string $documentType): string
    {
        $type = $this->documents->normalizeDocumentType($documentType);

        return match ($type) {
            'pr', 'purchase_requests' => 'purchase_request',
            'ppmp_document', 'procurement_document_ppmp' => 'ppmp',
            'ppmps', 'standalone_ppmp' => 'ppmp_record',
            'annual_procurement_plan', 'annual_procurement_plans', 'apps' => 'app',
            'supplemental_apps', 'supplemental_annual_procurement_plan' => 'supplemental_app',
            'po', 'purchase_orders' => 'purchase_order',
            'rfqs', 'request_for_quotation', 'request_for_quotations' => 'rfq',
            'abstracts', 'abstract_quotation', 'abstract_quotations' => 'abstract',
            'inspection', 'inspection_acceptance_record', 'inspection_acceptance_records' => 'inspection_acceptance',
            'bac_resolution', 'bac_resolutions', 'resolution' => 'bac_resolution',
            default => $type,
        };
    }

    private function buildContext(Model $document, string $type): array
    {
        $document->loadMissing($this->relationshipsFor($document));

        $fields = $this->fieldsFor($document, $type);
        $items = $this->itemsFor($document, $type);
        $attachments = $this->attachmentsFor($document, $type);
        $requirements = $this->requirementsFor($type);

        return [
            'document_type' => $this->typeLabel($type),
            'document_type_key' => $type,
            'tracking_number' => $this->documents->displayTrackingNumber($document),
            'status' => $fields['status'] ?? null,
            'stage' => $fields['stage'] ?? null,
            'fields' => $fields,
            'items' => $items,
            'attachments' => $attachments,
            'requirements' => $requirements,
        ];
    }

    private function relationshipsFor(Model $document): array
    {
        return match (true) {
            $document instanceof ProcurementDocument => [
                'submittingOffice',
                'currentOffice',
                'submittedBy',
                'preparedBy',
                'purchaseRequestItems',
                'ppmpItems',
                'attachments',
                'acceptedSupplementalApps',
            ],
            $document instanceof Ppmp => [
                'items',
                'office',
                'creator',
            ],
            $document instanceof AnnualProcurementPlan => [
                'items',
                'office',
                'preparedBy',
                'submittedBy',
                'approvedBy',
                'createdBy',
            ],
            $document instanceof SupplementalApp => [
                'items',
                'sourcePrDocument.submittingOffice',
                'requestingOffice',
                'preparedBy',
                'submittedBy',
                'acceptedBy',
            ],
            $document instanceof BacResolution => [
                'sourcePrDocument.submittingOffice',
                'procurementDocument',
                'items',
                'electronicSignatures',
            ],
            $document instanceof Rfq => [
                'items',
                'sourcePrDocument.submittingOffice',
                'sourceBacResolution',
                'preparedBy',
                'submittedBy',
            ],
            $document instanceof AbstractQuotation => [
                'items',
                'sourcePrDocument.submittingOffice',
                'sourceRfq',
                'sourceBacResolution',
                'preparedBy',
                'submittedBy',
            ],
            $document instanceof PurchaseOrder => [
                'procurementDocument',
                'sourcePrDocument.submittingOffice',
                'sourceBacResolution',
                'items',
            ],
            $document instanceof InspectionAcceptanceRecord => [
                'purchaseOrder.items',
                'purchaseOrder.sourcePrDocument.submittingOffice',
                'sourcePrDocument.submittingOffice',
                'office',
                'inspectedBy',
                'acceptedBy',
            ],
            default => [],
        };
    }

    private function fieldsFor(Model $document, string $type): array
    {
        $common = [
            'id' => $document->getKey(),
            'tracking_number' => $this->documents->displayTrackingNumber($document),
            'document_type' => $this->typeLabel($type),
            'status' => $document->status ?? null,
            'fiscal_year' => $document->fiscal_year ?? null,
            'total_amount' => $this->numberValue($document->total_amount ?? null),
        ];

        if ($document instanceof ProcurementDocument) {
            $isPpmp = strtoupper((string) $document->document_type) === 'PPMP';

            return array_merge($common, [
                'title' => $document->title,
                'purpose' => $document->purpose ?: $document->description,
                'office' => $document->submittingOffice?->name ?? $document->department_name,
                'current_office' => $document->currentOffice?->name,
                'stage' => $document->stage,
                'ppmp_no' => $document->ppmp_no,
                'plan_type' => $document->ppmp_plan_type,
                'pr_no' => $document->pr_no,
                'pr_date' => optional($document->pr_date)->toDateString(),
                'section' => $document->section,
                'item_count' => $isPpmp ? $document->ppmpItems->count() : $document->purchaseRequestItems->count(),
                'has_app_reference' => filled($document->app_item_id) || filled($document->app_consolidation_id) || $document->acceptedSupplementalApps->isNotEmpty(),
                'requested_by_name' => $document->requested_by_name,
                'approved_by_name' => $document->approved_by_name,
                'submitted_at' => optional($document->submitted_at)->toDateTimeString(),
            ]);
        }

        if ($document instanceof Ppmp) {
            return array_merge($common, [
                'ppmp_no' => $document->ppmp_no,
                'title' => $document->displayNumber(),
                'office' => $document->office?->name ?? $document->office_name ?? $document->end_user_unit,
                'end_user_unit' => $document->end_user_unit,
                'plan_type' => $document->plan_type,
                'prepared_by_name' => $document->prepared_by_name,
                'prepared_by_position' => $document->prepared_by_position,
                'submitted_by_name' => $document->submitted_by_name,
                'submitted_by_position' => $document->submitted_by_position,
                'prepared_date' => optional($document->prepared_date)->toDateString(),
                'submitted_date' => optional($document->submitted_date)->toDateString(),
                'item_count' => $document->items->count(),
                'total_amount' => $this->numberValue($document->total_budget),
            ]);
        }

        if ($document instanceof AnnualProcurementPlan) {
            return array_merge($common, [
                'app_number' => $document->app_no ?? $document->app_number,
                'title' => $document->title ?: $document->displayNumber(),
                'office' => $document->office?->name ?? $document->office_name,
                'plan_type' => $document->plan_type,
                'item_count' => $document->items->count() ?: count($this->jsonItemsFor($document)),
                'total_amount' => $this->numberValue($document->total_estimated_budget),
                'total_epa_budget' => $this->numberValue($document->total_epa_budget),
                'total_cse_budget' => $this->numberValue($document->total_cse_budget),
                'prepared_by_name' => $document->prepared_by_name ?? $document->preparedBy?->name,
                'recommended_by_name' => $document->recommended_by_name,
                'approved_by_name' => $document->approved_by_name ?? $document->approvedBy?->name,
                'submitted_at' => optional($document->submitted_at)->toDateTimeString(),
                'approved_at' => optional($document->approved_at)->toDateTimeString(),
            ]);
        }

        if ($document instanceof SupplementalApp) {
            return array_merge($common, [
                'supplemental_app_number' => $document->supplemental_app_number,
                'title' => $document->title ?: 'Supplemental Annual Procurement Plan',
                'purpose' => $document->purpose,
                'justification' => $document->justification,
                'office' => $document->requestingOffice?->name ?? $document->requesting_office_name,
                'requesting_office_name' => $document->requestingOffice?->name ?? $document->requesting_office_name,
                'source_pr_document_id' => $document->source_pr_document_id,
                'source_pr_tracking_number' => $document->sourcePrDocument?->pr_no ?? $document->sourcePrDocument?->tracking_number,
                'item_count' => $document->items->count(),
                'total_amount' => $this->numberValue($document->total_amount),
                'prepared_by_name' => $document->preparedBy?->name,
                'submitted_by_name' => $document->submittedBy?->name,
                'submitted_at' => optional($document->submitted_at)->toDateTimeString(),
                'accepted_at' => optional($document->accepted_at)->toDateTimeString(),
            ]);
        }

        if ($document instanceof BacResolution) {
            return array_merge($common, [
                'resolution_number' => $document->resolution_number,
                'resolution_date' => optional($document->resolution_date)->toDateString(),
                'title' => $document->title ?: $document->project_title,
                'project_title' => $document->project_title,
                'source_pr_document_id' => $document->source_pr_document_id,
                'source_pr_tracking_number' => $document->sourcePrDocument?->tracking_number,
                'pr_number' => $document->pr_number ?: $document->sourcePrDocument?->pr_no,
                'supplier_name' => $document->supplier_name ?: $document->contractor_name,
                'procurement_mode' => $document->procurement_mode,
                'signature_status' => $document->signature_status,
                'bac_chair_signature' => $document->electronicSignatures->where('signature_status', 'signed')->isNotEmpty()
                    || filled($document->bac_chair_confirmed_at),
                'item_count' => $document->items->count(),
                'submitted_at' => optional($document->submitted_at)->toDateTimeString(),
            ]);
        }

        if ($document instanceof Rfq) {
            return array_merge($common, [
                'rfq_number' => $document->rfq_number,
                'rfq_date' => optional($document->rfq_date)->toDateString(),
                'title' => 'Request for Quotation',
                'purpose' => $document->purpose,
                'office' => $document->sourcePrDocument?->submittingOffice?->name,
                'source_pr_document_id' => $document->source_pr_document_id,
                'source_pr_tracking_number' => $document->sourcePrDocument?->pr_no ?? $document->sourcePrDocument?->tracking_number,
                'source_bac_resolution_number' => $document->sourceBacResolution?->resolution_number,
                'supplier_name' => $document->supplier_name,
                'supplier_address' => $document->supplier_address,
                'delivery_period' => $document->delivery_period,
                'item_count' => $document->items->count() ?: count($this->jsonItemsFor($document)),
                'total_amount' => $this->numberValue($document->abc_amount),
                'submitted_at' => optional($document->submitted_at)->toDateTimeString(),
            ]);
        }

        if ($document instanceof AbstractQuotation) {
            return array_merge($common, [
                'abstract_number' => $document->abstract_number,
                'abstract_date' => optional($document->abstract_date)->toDateString(),
                'title' => $document->project_name ?: 'Abstract of Quotations',
                'project_title' => $document->project_name,
                'purpose' => $document->purpose,
                'office' => $document->implementing_office ?: $document->sourcePrDocument?->submittingOffice?->name,
                'source_pr_document_id' => $document->source_pr_document_id,
                'source_pr_tracking_number' => $document->sourcePrDocument?->pr_no ?? $document->sourcePrDocument?->tracking_number,
                'source_rfq_number' => $document->sourceRfq?->rfq_number,
                'source_bac_resolution_number' => $document->sourceBacResolution?->resolution_number,
                'supplier_name' => $document->lowest_supplier_name,
                'supplier_count' => $this->supplierCountFor($document),
                'item_count' => $document->items->count() ?: count($this->jsonItemsFor($document)),
                'total_amount' => $this->numberValue($document->lowest_total_amount ?: $document->abc_amount),
                'submitted_at' => optional($document->submitted_at)->toDateTimeString(),
            ]);
        }

        if ($document instanceof PurchaseOrder) {
            return array_merge($common, [
                'po_number' => $document->po_number,
                'po_date' => optional($document->po_date)->toDateString(),
                'supplier_name' => $document->supplier_name,
                'supplier_address' => $document->supplier_address,
                'mode_of_procurement' => $document->mode_of_procurement,
                'place_of_delivery' => $document->place_of_delivery ?: $document->delivery_place,
                'date_of_delivery' => $this->dateValue($document->date_of_delivery ?: $document->delivery_date),
                'delivery_term' => $document->delivery_term ?: $document->delivery_terms,
                'payment_term' => $document->payment_term ?: $document->payment_terms,
                'source_pr_document_id' => $document->source_pr_document_id,
                'source_pr_tracking_number' => $document->sourcePrDocument?->tracking_number,
                'pr_no' => $document->sourcePrDocument?->pr_no ?? $document->sourcePrDocument?->tracking_number,
                'source_bac_resolution_number' => $document->sourceBacResolution?->resolution_number,
                'item_count' => $document->items->count() ?: count($document->items_json ?? []),
                'submitted_at' => optional($document->submitted_at)->toDateTimeString(),
            ]);
        }

        if ($document instanceof InspectionAcceptanceRecord) {
            $purchaseOrder = $document->purchaseOrder;
            $sourceDocument = $document->sourcePrDocument ?: $purchaseOrder?->sourcePrDocument;

            return array_merge($common, [
                'title' => 'Inspection / Acceptance',
                'office' => $document->office?->name ?? $sourceDocument?->submittingOffice?->name,
                'purchase_order_id' => $document->purchase_order_id,
                'po_number' => $purchaseOrder?->po_number,
                'source_pr_document_id' => $document->source_pr_document_id ?? $purchaseOrder?->source_pr_document_id,
                'source_pr_tracking_number' => $sourceDocument?->pr_no ?? $sourceDocument?->tracking_number,
                'inspection_date' => optional($document->inspection_date)->toDateString(),
                'acceptance_date' => optional($document->acceptance_date)->toDateString(),
                'delivery_receipt_number' => $document->delivery_receipt_number,
                'invoice_number' => $document->invoice_number,
                'quantity_condition' => $document->quantity_condition,
                'quality_condition' => $document->quality_condition,
                'findings' => $document->findings,
                'remarks' => $document->remarks,
                'inspected_by_name' => $document->inspectedBy?->name,
                'accepted_by_name' => $document->acceptedBy?->name,
                'item_count' => $purchaseOrder?->items->count() ?: count($this->jsonItemsFor($purchaseOrder)),
                'total_amount' => $this->numberValue($purchaseOrder?->total_amount),
            ]);
        }

        return $common;
    }

    private function itemsFor(Model $document, string $type): array
    {
        $items = match (true) {
            $document instanceof ProcurementDocument && strtoupper((string) $document->document_type) === 'PPMP' => $document->ppmpItems,
            $document instanceof ProcurementDocument => $document->purchaseRequestItems,
            $document instanceof Ppmp => $document->items,
            $document instanceof AnnualProcurementPlan => $document->items,
            $document instanceof SupplementalApp => $document->items,
            $document instanceof BacResolution => $document->items,
            $document instanceof Rfq => $document->items,
            $document instanceof AbstractQuotation => $document->items,
            $document instanceof PurchaseOrder => $document->items,
            $document instanceof InspectionAcceptanceRecord => $document->purchaseOrder?->items ?? new EloquentCollection(),
            default => new EloquentCollection(),
        };

        if ($items->isEmpty()) {
            $jsonItems = $document instanceof InspectionAcceptanceRecord
                ? $this->jsonItemsFor($document->purchaseOrder)
                : $this->jsonItemsFor($document);

            if ($jsonItems !== []) {
                return collect($jsonItems)
                    ->take(5)
                    ->map(fn (array $item) => $this->itemSummaryFromArray($item))
                    ->values()
                    ->all();
            }
        }

        return $items
            ->take(5)
            ->map(fn (Model $item) => $this->itemSummaryFor($item))
            ->values()
            ->all();
    }

    private function itemSummaryFor(Model $item): array
    {
        return [
            'description' => $this->shortText($item->description
                ?? $item->item_description
                ?? $item->general_description
                ?? $item->project_title
                ?? $item->quantity_size
                ?? null, 180),
            'quantity' => $this->numberValue($item->quantity ?? null),
            'unit' => $item->unit ?? $item->unit_of_issue ?? $item->unit_of_measure ?? null,
            'unit_cost' => $this->numberValue($item->unit_cost ?? $item->unit_price ?? $item->estimated_unit_cost ?? null),
            'total_cost' => $this->numberValue($item->total_cost
                ?? $item->estimated_cost
                ?? $item->estimated_total_cost
                ?? $item->estimated_budget
                ?? $item->estimated_total
                ?? $item->total_lowest_price
                ?? $item->total
                ?? null),
            'remarks' => $this->shortText($item->remarks ?? null, 100),
        ];
    }

    private function itemSummaryFromArray(array $item): array
    {
        return [
            'description' => $this->shortText($item['description']
                ?? $item['item_description']
                ?? $item['general_description']
                ?? $item['project_title']
                ?? $item['quantity_size']
                ?? null, 180),
            'quantity' => $this->numberValue($item['quantity'] ?? null),
            'unit' => $item['unit'] ?? $item['unit_of_issue'] ?? $item['unit_of_measure'] ?? null,
            'unit_cost' => $this->numberValue($item['unit_cost'] ?? $item['unit_price'] ?? $item['estimated_unit_cost'] ?? null),
            'total_cost' => $this->numberValue($item['total_cost']
                ?? $item['estimated_cost']
                ?? $item['estimated_total_cost']
                ?? $item['estimated_budget']
                ?? $item['estimated_total']
                ?? $item['total_lowest_price']
                ?? $item['total']
                ?? null),
            'remarks' => $this->shortText($item['remarks'] ?? null, 100),
        ];
    }

    private function jsonItemsFor(?Model $document): array
    {
        if (! $document || ! is_array($document->items_json ?? null)) {
            return [];
        }

        return collect($document->items_json)
            ->flatMap(function (mixed $item) {
                if (! is_array($item)) {
                    return [];
                }

                if (array_is_list($item)) {
                    return $item;
                }

                foreach (['items', 'rows', 'general_requirements', 'miscellaneous_items', 'cse_items'] as $nestedKey) {
                    if (isset($item[$nestedKey]) && is_array($item[$nestedKey])) {
                        return $item[$nestedKey];
                    }
                }

                return [$item];
            })
            ->filter(fn (mixed $item) => is_array($item))
            ->values()
            ->all();
    }

    private function supplierCountFor(AbstractQuotation $document): int
    {
        $fromColumns = collect([
            $document->supplier_1_name,
            $document->supplier_2_name,
            $document->supplier_3_name,
            $document->supplier_4_name,
            $document->supplier_5_name,
        ])->filter(fn (mixed $value) => filled($value))->count();

        if ($fromColumns > 0) {
            return $fromColumns;
        }

        return is_array($document->suppliers_json)
            ? collect($document->suppliers_json)->filter()->count()
            : 0;
    }

    private function attachmentsFor(Model $document, string $type): array
    {
        $procurementDocumentId = $this->documents->procurementDocumentIdFor($document);

        return DocumentAttachment::query()
            ->active()
            ->where(function (Builder $query) use ($document, $type, $procurementDocumentId) {
                $query->where(function (Builder $inner) use ($document, $type) {
                    $inner->forDocument($type, $document);
                });

                if ($procurementDocumentId) {
                    $query->orWhere('procurement_document_id', $procurementDocumentId);
                }
            })
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (DocumentAttachment $attachment) => [
                'name' => $attachment->displayName(),
                'category' => $attachment->attachment_category ?? $attachment->document_section ?? DocumentAttachment::CATEGORY_OTHER,
                'section' => $attachment->document_section,
                'mime_type' => $attachment->mime_type,
                'ocr_status' => $attachment->ocr_status,
                'ai_analysis_status' => $attachment->ai_analysis_status,
            ])
            ->values()
            ->all();
    }

    private function requirementsFor(string $type): array
    {
        $configuredRules = DocumentRequirementRule::query()
            ->where('is_active', true)
            ->whereIn('document_type', $this->requirementTypeAliases($type))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (DocumentRequirementRule $rule) => [
                'name' => $rule->rule_name,
                'type' => $rule->requirement_type,
                'field_key' => $rule->field_key,
                'attachment_category' => $rule->attachment_category,
                'description' => $rule->description,
                'required' => (bool) $rule->is_required,
                'severity' => $rule->severity,
                'applies_to_status' => $rule->applies_to_status,
            ])
            ->values();

        $configuredNames = $configuredRules
            ->pluck('name')
            ->map(fn (mixed $name) => Str::lower(trim((string) $name)))
            ->all();

        $defaults = collect($this->defaultRequirementsFor($type))
            ->reject(fn (array $rule) => in_array(Str::lower(trim((string) $rule['name'])), $configuredNames, true));

        return $configuredRules
            ->concat($defaults)
            ->values()
            ->all();
    }

    private function requirementTypeAliases(string $type): array
    {
        return match ($type) {
            'purchase_request' => ['purchase_request', 'pr', 'PR', 'Purchase Request'],
            'ppmp', 'ppmp_record' => ['ppmp', 'PPMP', 'Project Procurement Management Plan'],
            'app' => ['app', 'annual_procurement_plan', 'APP', 'Annual Procurement Plan'],
            'supplemental_app' => ['supplemental_app', 'Supplemental APP', 'Supplemental Annual Procurement Plan'],
            'bac_resolution' => ['bac_resolution', 'BAC Resolution'],
            'rfq' => ['rfq', 'RFQ', 'request_for_quotation', 'Request for Quotation'],
            'abstract' => ['abstract', 'abstract_quotation', 'Abstract of Quotations', 'Abstract of Quotations/Canvass'],
            'purchase_order' => ['purchase_order', 'po', 'PO', 'Purchase Order'],
            'inspection_acceptance' => ['inspection_acceptance', 'inspection_acceptance_record', 'Inspection / Acceptance'],
            default => [$type],
        };
    }

    private function defaultRequirementsFor(string $type): array
    {
        return match ($type) {
            'purchase_request' => [
                $this->requirement('Purpose field required', 'field', 'purpose', null, 'Purpose field is required.'),
                $this->requirement('At least one item row required', 'field', 'items', null, 'At least one complete item row is required.'),
                $this->requirement('Total amount required', 'amount', 'total_amount', null, 'Total amount must be greater than zero.'),
                $this->requirement('Requesting office required', 'field', 'office', null, 'Requesting office must be identified.'),
            ],
            'ppmp', 'ppmp_record' => [
                $this->requirement('Fiscal year required', 'field', 'fiscal_year', null, 'Fiscal year is required.'),
                $this->requirement('End-user or implementing unit required', 'field', 'office', null, 'End-user or implementing unit must be identified.'),
                $this->requirement('At least one PPMP item row required', 'field', 'items', null, 'At least one PPMP item row is required.'),
                $this->requirement('Total budget required', 'amount', 'total_amount', null, 'Total budget must be greater than zero.'),
                $this->requirement('Prepared by name should be provided', 'field', 'prepared_by_name', null, 'Prepared by name should be provided.', false),
                $this->requirement('Submitted by name should be provided', 'field', 'submitted_by_name', null, 'Submitted by name should be provided.', false),
            ],
            'app' => [
                $this->requirement('Fiscal year required', 'field', 'fiscal_year', null, 'Fiscal year is required.'),
                $this->requirement('At least one APP item row required', 'field', 'items', null, 'At least one APP item row is required.'),
                $this->requirement('Total estimated budget required', 'amount', 'total_amount', null, 'Total estimated budget must be greater than zero.'),
                $this->requirement('Prepared by signatory should be provided', 'field', 'prepared_by_name', null, 'Prepared by signatory should be provided.', false),
                $this->requirement('Recommended by signatory should be provided', 'field', 'recommended_by_name', null, 'Recommended by signatory should be provided.', false),
                $this->requirement('Approved by signatory should be provided', 'field', 'approved_by_name', null, 'Approved by signatory should be provided.', false),
            ],
            'supplemental_app' => [
                $this->requirement('Fiscal year required', 'field', 'fiscal_year', null, 'Fiscal year is required.'),
                $this->requirement('Requesting office required', 'field', 'requesting_office_name', null, 'Requesting office must be identified.'),
                $this->requirement('Purpose required', 'field', 'purpose', null, 'Purpose is required.'),
                $this->requirement('Justification required', 'field', 'justification', null, 'Justification is required.'),
                $this->requirement('At least one Supplemental APP item row required', 'field', 'items', null, 'At least one Supplemental APP item row is required.'),
                $this->requirement('Total amount required', 'amount', 'total_amount', null, 'Total amount must be greater than zero.'),
                $this->requirement('Linked Purchase Request should be provided', 'field', 'source_pr_document_id', null, 'Linked Purchase Request should be provided.', false),
            ],
            'bac_resolution' => [
                $this->requirement('Resolution number required', 'field', 'resolution_number', null, 'Resolution number is required.'),
                $this->requirement('Resolution title required', 'field', 'title', null, 'Resolution title is required.'),
                $this->requirement('Source Purchase Request required', 'field', 'source_pr_document_id', null, 'Source Purchase Request is required.'),
                $this->requirement('Supplier or contractor required', 'field', 'supplier_name', null, 'Supplier or contractor should be identified.'),
                $this->requirement('BAC Chair signature should be present', 'field', 'bac_chair_signature', null, 'BAC Chair signature should be present before final routing.', false),
            ],
            'rfq' => [
                $this->requirement('RFQ number required', 'field', 'rfq_number', null, 'RFQ number is required.'),
                $this->requirement('Source Purchase Request required', 'field', 'source_pr_document_id', null, 'Source Purchase Request is required.'),
                $this->requirement('At least one RFQ item row required', 'field', 'items', null, 'At least one RFQ item row is required.'),
                $this->requirement('Approved Budget for the Contract required', 'amount', 'total_amount', null, 'Approved Budget for the Contract must be greater than zero.'),
                $this->requirement('Supplier name should be provided', 'field', 'supplier_name', null, 'Supplier name should be provided when issuing the RFQ.', false),
                $this->requirement('RFQ date should be provided', 'field', 'rfq_date', null, 'RFQ date should be provided.', false),
            ],
            'abstract' => [
                $this->requirement('Abstract number required', 'field', 'abstract_number', null, 'Abstract number is required.'),
                $this->requirement('Project name required', 'field', 'project_title', null, 'Project name is required.'),
                $this->requirement('Source RFQ required', 'field', 'source_rfq_number', null, 'Source RFQ is required.'),
                $this->requirement('At least one abstract item row required', 'field', 'items', null, 'At least one abstract item row is required.'),
                $this->requirement('At least one supplier quotation required', 'field', 'supplier_count', null, 'At least one supplier quotation is required.'),
                $this->requirement('Lowest calculated price required', 'amount', 'total_amount', null, 'Lowest calculated price must be greater than zero.'),
            ],
            'purchase_order' => [
                $this->requirement('PO number required', 'field', 'po_number', null, 'PO number is required.'),
                $this->requirement('Source Purchase Request required', 'field', 'source_pr_document_id', null, 'Source Purchase Request is required.'),
                $this->requirement('Supplier name required', 'field', 'supplier_name', null, 'Supplier name is required.'),
                $this->requirement('At least one PO item row required', 'field', 'items', null, 'At least one PO item row is required.'),
                $this->requirement('Total PO amount required', 'amount', 'total_amount', null, 'Total PO amount must be greater than zero.'),
                $this->requirement('Delivery terms should be provided', 'field', 'delivery_term', null, 'Delivery terms should be provided.', false),
                $this->requirement('Payment terms should be provided', 'field', 'payment_term', null, 'Payment terms should be provided.', false),
            ],
            'inspection_acceptance' => [
                $this->requirement('Linked Purchase Order required', 'field', 'purchase_order_id', null, 'Linked Purchase Order is required.'),
                $this->requirement('Inspection date required', 'field', 'inspection_date', null, 'Inspection date is required.'),
                $this->requirement('Quantity condition required', 'field', 'quantity_condition', null, 'Quantity condition is required.'),
                $this->requirement('Quality condition required', 'field', 'quality_condition', null, 'Quality condition is required.'),
                $this->requirement('Delivered items should be present', 'field', 'items', null, 'Delivered items should be present from the linked PO.', false),
                $this->requirement('Acceptance date should be provided when accepted', 'field', 'acceptance_date', null, 'Acceptance date should be provided when the document is accepted.', false),
            ],
            default => [],
        };
    }

    private function requirement(
        string $name,
        string $type,
        ?string $fieldKey = null,
        ?string $attachmentCategory = null,
        ?string $description = null,
        bool $required = true,
        string $severity = 'warning',
    ): array {
        return [
            'name' => $name,
            'type' => $type,
            'field_key' => $fieldKey,
            'attachment_category' => $attachmentCategory,
            'description' => $description,
            'required' => $required,
            'severity' => $severity,
            'applies_to_status' => null,
        ];
    }

    private function manualFindings(array $context): array
    {
        $missing = [];
        $warnings = [];
        $completed = [];
        $fields = $context['fields'];
        $attachments = collect($context['attachments']);
        $documentTypeKey = (string) ($context['document_type_key'] ?? '');
        $prConfiguredAttachmentCategories = [];

        foreach ($context['requirements'] as $requirement) {
            $label = $requirement['description'] ?: $requirement['name'];
            $isRequired = (bool) ($requirement['required'] ?? false);
            $type = $requirement['type'] ?? null;
            $category = Str::lower((string) ($requirement['attachment_category'] ?? ''));
            $present = true;

            if (in_array($type, ['field', 'workflow', 'signatory'], true)) {
                $present = $this->fieldIsPresent($fields, (string) ($requirement['field_key'] ?? ''));
            } elseif ($type === 'amount') {
                $present = $this->numberValue(Arr::get($fields, $requirement['field_key'] ?: 'total_amount')) > 0;
            } elseif ($type === 'attachment') {
                if ($documentTypeKey === 'purchase_request' && $category !== '') {
                    $prConfiguredAttachmentCategories[] = $category;
                }

                $present = $category === ''
                    ? $attachments->isNotEmpty()
                    : $this->hasAttachmentCategory($attachments, $category);

                if ($documentTypeKey === 'purchase_request'
                    && $category === DocumentAttachment::CATEGORY_SUPPORTING_DOCUMENT
                    && (bool) ($fields['has_app_reference'] ?? false)) {
                    $present = true;
                }
            }

            if ($present) {
                if ($isRequired) {
                    $completed[] = $requirement['name'];
                }

                continue;
            }

            if ($isRequired) {
                $missing[] = $label;
            } elseif ($documentTypeKey === 'purchase_request'
                && $type === 'attachment'
                && in_array($category, [
                    DocumentAttachment::CATEGORY_SUPPORTING_DOCUMENT,
                    DocumentAttachment::CATEGORY_QUOTATION,
                ], true)) {
                $missing[] = $this->prMissingAttachmentLabel($category, $label);
            } else {
                $warnings[] = $label;
            }
        }

        if ((int) ($fields['item_count'] ?? 0) < 1 && $context['items'] === []) {
            $missing[] = 'At least one complete item row is required.';
        }

        if ($documentTypeKey === 'purchase_request') {
            if ($this->numberValue($fields['total_amount'] ?? null) > 0
                && ! in_array(DocumentAttachment::CATEGORY_QUOTATION, $prConfiguredAttachmentCategories, true)
                && ! $this->hasAttachmentCategory($attachments, DocumentAttachment::CATEGORY_QUOTATION)) {
                $missing[] = $this->prMissingAttachmentLabel(DocumentAttachment::CATEGORY_QUOTATION);
            }

            if (! (bool) ($fields['has_app_reference'] ?? false)
                && ! in_array(DocumentAttachment::CATEGORY_SUPPORTING_DOCUMENT, $prConfiguredAttachmentCategories, true)
                && ! $this->hasAttachmentCategory($attachments, DocumentAttachment::CATEGORY_SUPPORTING_DOCUMENT)) {
                $missing[] = $this->prMissingAttachmentLabel(DocumentAttachment::CATEGORY_SUPPORTING_DOCUMENT);
            }
        }

        return [
            'missing_requirements' => $this->uniqueList($missing),
            'warnings' => $this->uniqueList($warnings),
            'completed_requirements' => $this->uniqueList($completed),
        ];
    }

    private function fieldIsPresent(array $fields, string $fieldKey): bool
    {
        if ($fieldKey === '') {
            return true;
        }

        if ($fieldKey === 'items' || $fieldKey === 'delivered_items') {
            return (int) ($fields['item_count'] ?? 0) > 0;
        }

        $value = Arr::get($fields, $fieldKey);

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value > 0;
        }

        return filled($value);
    }

    private function hasAttachmentCategory($attachments, string $category): bool
    {
        $needle = Str::lower($category);

        return collect($attachments)->contains(
            fn (array $attachment) => Str::lower((string) ($attachment['category'] ?? '')) === $needle
        );
    }

    private function prMissingAttachmentLabel(string $category, ?string $fallback = null): string
    {
        return match ($category) {
            DocumentAttachment::CATEGORY_QUOTATION => 'Quotation or canvass attachment is missing.',
            DocumentAttachment::CATEGORY_SUPPORTING_DOCUMENT => 'APP reference, Supplemental APP, or equivalent procurement planning support is missing.',
            default => $fallback ?: 'Required supporting attachment is missing.',
        };
    }

    private function messagesFor(array $context, array $manualFindings): array
    {
        $payload = $this->aiPayloadFor($context, $manualFindings);

        return [
            [
                'role' => 'system',
                'content' => 'You are PaperTrail AI Document Completeness Checker. Analyze only the provided procurement summary. Return only valid JSON with keys: score, completed_items, missing_requirements, warnings, recommendation. Do not return markdown. Do not add explanations outside JSON. Do not approve, reject, route, submit, or modify documents.',
            ],
            [
                'role' => 'user',
                'content' => json_encode($payload, JSON_UNESCAPED_SLASHES),
            ],
        ];
    }

    private function aiPayloadFor(array $context, array $manualFindings): array
    {
        $fields = $context['fields'];

        return [
            'expected_json_format' => [
                'score' => 0,
                'completed_items' => [],
                'missing_requirements' => [],
                'warnings' => [],
                'recommendation' => '',
            ],
            'document_summary' => [
                'document_type' => $context['document_type'],
                'document_number' => $fields['pr_no']
                    ?? $fields['ppmp_no']
                    ?? $fields['app_number']
                    ?? $fields['supplemental_app_number']
                    ?? $fields['rfq_number']
                    ?? $fields['abstract_number']
                    ?? $fields['po_number']
                    ?? $fields['resolution_number']
                    ?? $context['tracking_number'],
                'tracking_number' => $context['tracking_number'],
                'status' => $context['status'],
                'stage' => $context['stage'],
                'office' => $fields['office'] ?? $fields['requesting_office_name'] ?? null,
                'title' => $this->shortText($fields['title'] ?? $fields['project_title'] ?? null, 140),
                'purpose' => $this->shortText($fields['purpose'] ?? null, 180),
                'item_count' => (int) ($fields['item_count'] ?? count($context['items'] ?? [])),
                'estimated_amount' => $this->numberValue($fields['total_amount'] ?? null),
                'supplier_name' => $this->shortText($fields['supplier_name'] ?? null, 100),
                'source_pr' => $fields['source_pr_tracking_number'] ?? $fields['pr_number'] ?? $fields['pr_no'] ?? null,
                'has_app_reference' => (bool) ($fields['has_app_reference'] ?? false),
            ],
            'attachments' => collect($context['attachments'])
                ->take(8)
                ->map(fn (array $attachment) => [
                    'category' => $attachment['category'] ?? 'other',
                    'filename' => $this->shortText($attachment['name'] ?? 'Attachment', 80),
                ])
                ->values()
                ->all(),
            'requirements' => collect($context['requirements'])
                ->take(20)
                ->map(fn (array $requirement) => trim(sprintf(
                    '%s: %s',
                    ! empty($requirement['required']) ? 'Required' : 'Suggested',
                    $requirement['name'] ?? $requirement['description'] ?? 'Requirement'
                )))
                ->values()
                ->all(),
            'system_detected_findings' => [
                'completed_items' => $manualFindings['completed_requirements'],
                'missing_requirements' => $manualFindings['missing_requirements'],
                'warnings' => $manualFindings['warnings'],
            ],
            'instruction' => 'Use system_detected_findings as the baseline. Keep the response short and practical. Return JSON only.',
        ];
    }

    private function parseJsonResponse(string $response): array
    {
        $clean = trim($response);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean) ?? $clean;
        $clean = preg_replace('/\s*```$/', '', $clean) ?? $clean;

        if (! str_starts_with($clean, '{')) {
            $start = strpos($clean, '{');
            $end = strrpos($clean, '}');

            if ($start !== false && $end !== false && $end > $start) {
                $clean = substr($clean, $start, $end - $start + 1);
            }
        }

        $decoded = json_decode($clean, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI returned an invalid completeness JSON response.');
        }

        return $decoded;
    }

    private function normalizeScore(mixed $score, array $missing, array $warnings): int
    {
        $fallback = max(0, 100 - (count($missing) * 18) - (count($warnings) * 7));
        $normalized = is_numeric($score) ? (int) round((float) $score) : $fallback;

        if ($missing !== []) {
            $normalized = min($normalized, max(35, $fallback + 8));
        }

        return max(0, min(100, $normalized));
    }

    private function fallbackRecommendation(array $missing, array $warnings): string
    {
        if ($missing !== []) {
            return 'Complete the missing required items before routing this document.';
        }

        if ($warnings !== []) {
            return 'Review the warnings before continuing the workflow.';
        }

        return 'No missing required requirements were detected. Continue normal manual review.';
    }

    private function uniqueList(array $items): array
    {
        return collect($items)
            ->map(fn (mixed $item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'ppmp', 'ppmp_record' => 'Project Procurement Management Plan',
            'app' => 'Annual Procurement Plan',
            'supplemental_app' => 'Supplemental Annual Procurement Plan',
            'purchase_request' => 'Purchase Request',
            'bac_resolution' => 'BAC Resolution',
            'rfq' => 'Request for Quotation',
            'abstract' => 'Abstract of Quotations',
            'purchase_order' => 'Purchase Order',
            'inspection_acceptance' => 'Inspection / Acceptance',
            default => Str::headline($type),
        };
    }

    private function numberValue(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) str_replace(',', '', (string) $value);
    }

    private function shortText(mixed $value, int $limit): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)) ?? '');

        return $text === '' ? null : Str::limit($text, $limit, '');
    }

    private function dateValue(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return method_exists($value, 'toDateString') ? $value->toDateString() : (string) $value;
    }
}
