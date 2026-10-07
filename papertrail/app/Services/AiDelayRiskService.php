<?php

namespace App\Services;

use App\Models\AiDelayRiskCheck;
use App\Models\AuditLog;
use App\Models\BacResolution;
use App\Models\ElectronicSignature;
use App\Models\InspectionAcceptanceRecord;
use App\Models\ProcurementDocument;
use App\Models\PurchaseOrder;
use App\Models\SignatureRequest;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class AiDelayRiskService
{
    private const SUPPORTED_TYPES = [
        'ppmp',
        'ppmp_record',
        'app',
        'supplemental_app',
        'purchase_request',
        'procurement_document',
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

    public function analyzeDelayRisk(User $user, string $documentType, int $documentId): array
    {
        $startedAt = microtime(true);
        $type = $this->canonicalType($documentType);

        if (! in_array($type, self::SUPPORTED_TYPES, true)) {
            throw new InvalidArgumentException('AI delay risk analysis is not available for this document type yet.');
        }

        $document = $this->documents->resolve($type, $documentId);

        if (! $this->documents->canView($user, $document)) {
            AuditLogger::denied('unauthorized_ai_delay_attempt', [
                'module' => 'AI Delay Risk',
                'document_type' => $type,
                'document_id' => $document->getKey(),
                'tracking_number' => $this->documents->displayTrackingNumber($document),
                'user_id' => $user->id,
                'severity' => 'warning',
            ]);

            throw new AuthorizationException('You do not have permission to analyze this document.');
        }

        $trackingNumber = $this->documents->displayTrackingNumber($document);

        $check = AiDelayRiskCheck::create([
            'document_type' => $type,
            'document_id' => $document->getKey(),
            'tracking_number' => $trackingNumber,
            'status' => AiDelayRiskCheck::STATUS_PROCESSING,
            'created_by_user_id' => $user->id,
        ]);

        AuditLogger::ai('ai_delay_check_started', $document, [
            'module' => 'AI Delay Risk',
            'document_type' => $type,
            'document_id' => $document->getKey(),
            'tracking_number' => $trackingNumber,
            'ai_delay_risk_check_id' => $check->id,
            'user_id' => $user->id,
            'execution_time' => 0,
        ]);

        Log::info('ai_delay_check_started', [
            'user_id' => $user->id,
            'document_type' => $type,
            'document_id' => $document->getKey(),
            'ai_delay_risk_check_id' => $check->id,
            'execution_time' => 0,
        ]);

        try {
            $document->loadMissing($this->relationshipsFor($document));

            $context = $this->buildContext($document, $type);
            $systemAssessment = $this->systemAssessment($context);
            $aiAssessment = $this->aiAssessment($context, $systemAssessment);
            $riskFactors = $this->uniqueList(array_merge(
                $systemAssessment['risk_factors'],
                Arr::wrap($aiAssessment['risk_factors'] ?? [])
            ));
            $recommendation = trim((string) ($aiAssessment['recommendation'] ?? '')) !== ''
                ? trim((string) $aiAssessment['recommendation'])
                : $this->fallbackRecommendation($context, $systemAssessment);
            $executionTime = round(microtime(true) - $startedAt, 3);

            $check->update([
                'current_status' => $context['current_status_label'],
                'current_holder' => $context['current_holder'],
                'risk_level' => $systemAssessment['risk_level'],
                'delay_days' => $context['days_waiting'],
                'risk_factors' => $riskFactors,
                'recommendations' => $recommendation,
                'ai_response' => $aiAssessment['ai_response'],
                'status' => AiDelayRiskCheck::STATUS_COMPLETED,
            ]);

            AuditLogger::ai('ai_delay_check_completed', $document, [
                'module' => 'AI Delay Risk',
                'document_type' => $type,
                'document_id' => $document->getKey(),
                'tracking_number' => $trackingNumber,
                'ai_delay_risk_check_id' => $check->id,
                'user_id' => $user->id,
                'risk_level' => $systemAssessment['risk_level'],
                'delay_days' => $context['days_waiting'],
                'used_openai' => (bool) ($aiAssessment['used_openai'] ?? false),
                'execution_time' => $executionTime,
            ]);

            Log::info('ai_delay_check_completed', [
                'user_id' => $user->id,
                'document_type' => $type,
                'document_id' => $document->getKey(),
                'ai_delay_risk_check_id' => $check->id,
                'risk_level' => $systemAssessment['risk_level'],
                'delay_days' => $context['days_waiting'],
                'used_openai' => (bool) ($aiAssessment['used_openai'] ?? false),
                'execution_time' => $executionTime,
            ]);

            return [
                ...$context,
                'check_id' => $check->id,
                'risk_level' => $systemAssessment['risk_level'],
                'risk_label' => $this->riskLabel($systemAssessment['risk_level']),
                'risk_factors' => $riskFactors,
                'anomalies' => $systemAssessment['anomalies'],
                'recommendation' => $recommendation,
                'ai_message' => $aiAssessment['message'] ?: $this->fallbackMessage($context, $systemAssessment),
                'used_openai' => (bool) ($aiAssessment['used_openai'] ?? false),
                'execution_time' => $executionTime,
            ];
        } catch (Throwable $exception) {
            $executionTime = round(microtime(true) - $startedAt, 3);

            $check->update([
                'status' => AiDelayRiskCheck::STATUS_FAILED,
                'ai_response' => $exception::class . ': ' . $exception->getMessage(),
            ]);

            AuditLogger::ai('ai_delay_check_failed', $document, [
                'module' => 'AI Delay Risk',
                'document_type' => $type,
                'document_id' => $document->getKey(),
                'tracking_number' => $trackingNumber,
                'ai_delay_risk_check_id' => $check->id,
                'user_id' => $user->id,
                'severity' => 'warning',
                'failure' => $exception::class,
                'execution_time' => $executionTime,
            ]);

            Log::error('AI delay risk analysis failed.', [
                'user_id' => $user->id,
                'document_type' => $type,
                'document_id' => $document->getKey(),
                'ai_delay_risk_check_id' => $check->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'execution_time' => $executionTime,
            ]);

            throw new RuntimeException('AI delay risk analysis is temporarily unavailable.', previous: $exception);
        }
    }

    private function buildContext(Model $document, string $type): array
    {
        $source = $this->sourceProcurementDocument($document);

        if ($source && ! $source->is($document)) {
            $source->loadMissing($this->relationshipsFor($source));
        }

        $status = $this->statusFor($document, $source);
        $stage = $this->stageFor($document, $source);
        $history = $this->historyFor($document, $source);
        $signatureSummary = $this->signatureSummary($document, $type);
        $auditSummary = $this->auditSummary($document, $type, $source);
        $lastActivityAt = $this->lastActivityAt($document, $source, $history, $signatureSummary);
        $daysWaiting = $lastActivityAt
            ? max(0, (int) $lastActivityAt->diffInDays(now(), false))
            : 0;

        return [
            'document_type' => $this->typeLabel($type, $document),
            'document_type_key' => $type,
            'document_id' => $document->getKey(),
            'tracking_number' => $this->documents->displayTrackingNumber($document) ?: '#' . $document->getKey(),
            'title' => $this->titleFor($document, $source),
            'requesting_office' => $this->documents->officeNameFor($document) ?: $this->documents->officeNameFor($source) ?: 'Not recorded',
            'created_at' => $this->formatDate($this->dateFromAttributes($document, ['created_at'])),
            'submitted_at' => $this->formatDate($this->dateFromAttributes($document, ['submitted_at', 'pr_no_requested_at', 'forwarded_to_hope_at'])),
            'current_status' => $status ?: 'not_recorded',
            'current_status_label' => $this->humanizeStatus($status) ?: 'Not recorded',
            'current_stage' => $stage ?: $this->humanizeStatus($status) ?: 'Not recorded',
            'current_holder' => $this->currentHolderFor($document, $source, $status) ?: 'Not recorded',
            'last_activity_at' => $this->formatDate($lastActivityAt),
            'days_waiting' => $daysWaiting,
            'history' => $history,
            'audit_summary' => $auditSummary,
            'signature_summary' => $signatureSummary,
            'next_expected_step' => $this->nextExpectedStep($document, $type, $status),
        ];
    }

    private function systemAssessment(array $context): array
    {
        $days = (int) ($context['days_waiting'] ?? 0);
        $riskLevel = $this->riskLevelForDays($days);
        $riskFactors = [];
        $anomalies = [];

        if ($days <= 2) {
            $riskFactors[] = 'Document movement is within the normal 0-2 day monitoring window.';
        } else {
            $riskFactors[] = "No recorded movement for {$days} days in the current stage.";
        }

        if (($context['current_holder'] ?? 'Not recorded') === 'Not recorded') {
            $anomalies[] = 'Current holder is not clearly recorded.';
        }

        if (($context['history'] ?? []) === [] && $days >= 3) {
            $anomalies[] = 'No routing history is available even though the document has been waiting for several days.';
        }

        if (str_contains((string) ($context['current_status'] ?? ''), 'returned')) {
            $riskFactors[] = 'Document is currently returned and needs correction or resubmission.';
        }

        $signature = $context['signature_summary'] ?? [];
        $openSignatures = (int) ($signature['open_count'] ?? 0);

        if ($openSignatures > 0) {
            $riskFactors[] = "{$openSignatures} signature request(s) are still open.";
        }

        if ((int) ($signature['overdue_count'] ?? 0) > 0) {
            $anomalies[] = "{$signature['overdue_count']} signature request(s) are overdue.";
            $riskLevel = $this->maxRisk($riskLevel, AiDelayRiskCheck::RISK_HIGH);
        }

        if ($this->hasFutureDates($context)) {
            $anomalies[] = 'One or more workflow dates appear to be in the future.';
            $riskLevel = $this->maxRisk($riskLevel, AiDelayRiskCheck::RISK_MEDIUM);
        }

        return [
            'risk_level' => $riskLevel,
            'risk_factors' => $this->uniqueList(array_merge($riskFactors, $anomalies)),
            'anomalies' => $this->uniqueList($anomalies),
        ];
    }

    private function aiAssessment(array $context, array $systemAssessment): array
    {
        $payload = [
            'document_type' => $context['document_type'],
            'tracking_number' => $context['tracking_number'],
            'current_status' => $context['current_status_label'],
            'current_stage' => $context['current_stage'],
            'current_holder' => $context['current_holder'],
            'days_waiting' => $context['days_waiting'],
            'system_risk_level' => $systemAssessment['risk_level'],
            'system_risk_factors' => $systemAssessment['risk_factors'],
            'anomalies' => $systemAssessment['anomalies'],
            'history' => array_slice($context['history'], -8),
            'signature_summary' => $context['signature_summary'],
            'next_expected_step' => $context['next_expected_step'],
        ];

        try {
            $response = $this->openAI->chat([
                [
                    'role' => 'system',
                    'content' => 'You are PaperTrail AI Delay Risk Assistant. Analyze procurement document progress using only the provided Laravel workflow information. Do not invent statuses, offices, approvals, or decisions. Do not change the workflow. Return JSON only with keys message, risk_level, risk_factors, recommendation.',
                ],
                [
                    'role' => 'user',
                    'content' => json_encode($payload, JSON_UNESCAPED_SLASHES),
                ],
            ], [
                'temperature' => 0,
                'max_tokens' => 320,
                'response_format' => ['type' => 'json_object'],
            ]);

            $parsed = json_decode($response, true);

            return [
                'used_openai' => true,
                'message' => trim((string) data_get($parsed, 'message', '')),
                'risk_factors' => Arr::wrap(data_get($parsed, 'risk_factors', [])),
                'recommendation' => trim((string) data_get($parsed, 'recommendation', '')),
                'ai_response' => $response,
            ];
        } catch (Throwable $exception) {
            Log::warning('ai_delay_openai_unavailable_using_rules', [
                'document_type' => $context['document_type_key'],
                'document_id' => $context['document_id'],
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [
                'used_openai' => false,
                'message' => null,
                'risk_factors' => [],
                'recommendation' => null,
                'ai_response' => json_encode([
                    'mode' => 'rule_based_fallback',
                    'message' => 'OpenAI explanation was unavailable; delay risk was computed from workflow timestamps.',
                ]),
            ];
        }
    }

    private function relationshipsFor(Model $document): array
    {
        $relations = [
            'submittingOffice',
            'currentOffice',
            'assignedTo',
            'routeDestinationOffice',
            'submittedBy',
            'preparedBy',
            'prNoAssignedBy',
            'prReceivedBy',
            'ppmpReviewedBy',
            'office',
            'creator',
            'createdBy',
            'approvedBy',
            'sourcePrDocument',
            'procurementDocument',
            'sourceBacResolution',
            'sourceAbstract',
            'purchaseOrder',
            'routingHistories.actionBy',
            'routingHistories.fromOffice',
            'routingHistories.toOffice',
        ];

        return array_values(array_filter($relations, function (string $relation) use ($document) {
            $root = Str::before($relation, '.');

            return method_exists($document, $root);
        }));
    }

    private function historyFor(Model $document, ?ProcurementDocument $source): array
    {
        $historyDocument = $source ?: ($document instanceof ProcurementDocument ? $document : null);

        if ($historyDocument && method_exists($historyDocument, 'routingHistories')) {
            $historyDocument->loadMissing([
                'routingHistories.actionBy',
                'routingHistories.fromOffice',
                'routingHistories.toOffice',
            ]);

            $history = $historyDocument->routingHistories
                ->sortBy('action_at')
                ->values()
                ->map(fn ($item) => [
                    'action' => $item->action ?: 'Workflow action',
                    'status_from' => $this->humanizeStatus($item->status_from),
                    'status_to' => $this->humanizeStatus($item->status_to),
                    'date' => $this->formatDate($item->action_at),
                    'raw_date' => $this->dateString($item->action_at),
                    'actor' => $item->actionBy?->name,
                    'from' => $item->fromOffice?->name,
                    'to' => $item->toOffice?->name,
                    'comments' => Str::limit((string) $item->comments, 180),
                ])
                ->all();

            if ($history !== []) {
                return $history;
            }
        }

        return $this->fallbackHistory($document);
    }

    private function fallbackHistory(Model $document): array
    {
        $events = [];

        foreach ([
            'created_at' => 'Document Created',
            'pr_no_requested_at' => 'Submitted for PR Number',
            'pr_no_assigned_at' => 'PR Number Assigned',
            'submitted_at' => 'Document Submitted',
            'pr_received_at' => 'PR Received',
            'pr_validation_started_at' => 'PR Validation Started',
            'ppmp_review_started_at' => 'PPMP Review Started',
            'ppmp_reviewed_at' => 'PPMP Reviewed',
            'forwarded_to_hope_at' => 'Forwarded to HOPE',
            'approved_at' => 'Document Approved',
            'returned_at' => 'Document Returned',
            'issued_at' => 'Purchase Order Issued',
            'completed_at' => 'Document Completed',
            'updated_at' => 'Last Updated',
        ] as $attribute => $label) {
            $date = $this->dateFromAttributes($document, [$attribute]);

            if ($date) {
                $events[] = [
                    'action' => $label,
                    'status_from' => null,
                    'status_to' => null,
                    'date' => $this->formatDate($date),
                    'raw_date' => $this->dateString($date),
                    'actor' => null,
                    'from' => null,
                    'to' => null,
                    'comments' => null,
                ];
            }
        }

        return collect($events)
            ->sortBy('raw_date')
            ->values()
            ->all();
    }

    private function auditSummary(Model $document, string $type, ?ProcurementDocument $source): array
    {
        if (! Schema::hasTable('audit_logs')) {
            return [];
        }

        $ids = array_values(array_unique(array_filter([
            $document->getKey(),
            $source?->getKey(),
        ])));

        return AuditLog::query()
            ->where(function ($query) use ($type, $ids, $document, $source) {
                $query->where(function ($documentQuery) use ($type, $ids, $document) {
                    $documentQuery->whereIn('document_id', $ids)
                        ->whereIn('document_type', $this->documentTypeCandidates($type, $document));
                });

                if ($source) {
                    $query->orWhere(function ($sourceQuery) use ($source) {
                        $sourceQuery->where('target_type', ProcurementDocument::class)
                            ->where('target_id', $source->getKey());
                    });
                }
            })
            ->latest('created_at')
            ->limit(6)
            ->get()
            ->map(fn (AuditLog $log) => [
                'action' => $log->action,
                'status' => $log->status,
                'date' => $this->formatDate($log->created_at),
            ])
            ->all();
    }

    private function signatureSummary(Model $document, string $type): array
    {
        if (! Schema::hasTable('signature_requests')) {
            return [
                'open_count' => 0,
                'signed_count' => 0,
                'overdue_count' => 0,
                'latest_activity_at' => null,
            ];
        }

        $types = $this->documentTypeCandidates($type, $document);
        $requests = SignatureRequest::query()
            ->where('document_id', $document->getKey())
            ->whereIn('document_type', $types)
            ->latest('updated_at')
            ->get();

        if ($requests->isEmpty() && $source = $this->sourceProcurementDocument($document)) {
            $requests = SignatureRequest::query()
                ->where('document_id', $source->getKey())
                ->whereIn('document_type', ['purchase_request', 'procurement_document', 'PR', 'ppmp'])
                ->latest('updated_at')
                ->get();
        }

        $latest = $requests
            ->map(fn (SignatureRequest $request) => $this->latestDateFrom($request, ['signed_at', 'viewed_at', 'notification_sent_at', 'email_sent_at', 'updated_at', 'created_at']))
            ->filter()
            ->sortDesc()
            ->first();

        $signedCount = $requests->where('status', SignatureRequest::STATUS_SIGNED)->count();
        $open = $requests->filter(fn (SignatureRequest $request) => $request->isOpen());

        return [
            'open_count' => $open->count(),
            'signed_count' => $signedCount,
            'overdue_count' => $open->filter(fn (SignatureRequest $request) => $request->due_at && $request->due_at->isPast())->count(),
            'latest_activity_at' => $this->dateString($latest),
        ];
    }

    private function lastActivityAt(Model $document, ?ProcurementDocument $source, array $history, array $signatureSummary): ?Carbon
    {
        $dates = [];

        foreach ($history as $event) {
            $dates[] = $this->toCarbon($event['raw_date'] ?? $event['date'] ?? null);
        }

        foreach ([$document, $source] as $item) {
            if (! $item) {
                continue;
            }

            foreach ([
                'updated_at',
                'submitted_at',
                'pr_no_requested_at',
                'pr_no_assigned_at',
                'pr_received_at',
                'pr_validation_started_at',
                'ppmp_review_started_at',
                'ppmp_reviewed_at',
                'forwarded_to_hope_at',
                'approved_at',
                'returned_at',
                'issued_at',
                'completed_at',
                'created_at',
            ] as $attribute) {
                $dates[] = $this->dateFromAttributes($item, [$attribute]);
            }
        }

        $dates[] = $this->toCarbon($signatureSummary['latest_activity_at'] ?? null);

        return collect($dates)
            ->filter()
            ->sortDesc()
            ->first();
    }

    private function currentHolderFor(Model $document, ?ProcurementDocument $source, ?string $status): ?string
    {
        $current = $document instanceof ProcurementDocument ? $document : $source;

        if ($current) {
            $current->loadMissing('assignedTo', 'currentOffice', 'routeDestinationOffice', 'submittingOffice');

            if ($current->assignedTo) {
                return trim($current->assignedTo->name . ' (' . $this->roleLabel($current->assignedTo->role) . ')');
            }

            if ($current->currentOffice) {
                return $current->currentOffice->name;
            }

            if ($current->routeDestinationOffice) {
                return $current->routeDestinationOffice->name;
            }

            if ($current->submittingOffice) {
                return $current->submittingOffice->name;
            }
        }

        $holder = $this->holderFromStatus($document, $status);

        if ($holder) {
            return $holder;
        }

        foreach (['office', 'createdBy', 'preparedBy', 'submittedBy', 'approvedBy', 'issuedBy'] as $relation) {
            if (! method_exists($document, $relation)) {
                continue;
            }

            $document->loadMissing($relation);
            $related = $document->{$relation};

            if (filled($related?->name) && ! ($related instanceof User)) {
                return $related->name;
            }

            if ($related instanceof User) {
                return trim($related->name . ' (' . $this->roleLabel($related->role) . ')');
            }
        }

        return $this->documents->officeNameFor($document);
    }

    private function holderFromStatus(Model $document, ?string $status): ?string
    {
        if ($document instanceof BacResolution) {
            return match ($status) {
                BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR,
                BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR => 'BAC Chair',
                BacResolution::STATUS_FORWARDED_TO_HOPE => 'Head of the Procuring Entity',
                BacResolution::STATUS_APPROVED_BY_HOPE,
                BacResolution::STATUS_RETURNED_TO_END_USER => $this->documents->officeNameFor($document) ?: 'Requesting Office',
                default => null,
            };
        }

        if ($document instanceof PurchaseOrder) {
            return match ($status) {
                PurchaseOrder::STATUS_DRAFT,
                PurchaseOrder::STATUS_SUBMITTED,
                PurchaseOrder::STATUS_FORWARDED_TO_SUPPLIER,
                PurchaseOrder::STATUS_RETURNED => $this->documents->officeNameFor($document) ?: 'Requesting Office',
                PurchaseOrder::STATUS_FUND_CERTIFIED => 'Accounting Office',
                PurchaseOrder::STATUS_APPROVED,
                PurchaseOrder::STATUS_ISSUED,
                PurchaseOrder::STATUS_COMPLETED => 'Requesting Office',
                default => null,
            };
        }

        return match ($status) {
            ProcurementDocument::STATUS_PENDING_PPMP_REVIEW,
            ProcurementDocument::STATUS_UNDER_PPMP_REVIEW,
            ProcurementDocument::STATUS_PR_SUBMITTED,
            ProcurementDocument::STATUS_SUBMITTED_TO_BAC_SECRETARIAT,
            ProcurementDocument::STATUS_PR_RECEIVED_BY_BAC_SECRETARIAT,
            ProcurementDocument::STATUS_UNDER_PR_VALIDATION,
            ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION,
            ProcurementDocument::STATUS_BAC_RESOLUTION_CREATED => 'BAC Secretariat',
            ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT => 'PR Numbering Staff',
            ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW,
            ProcurementDocument::STATUS_UNDER_BUDGET_REVIEW => 'Budget Office',
            ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW,
            ProcurementDocument::STATUS_UNDER_ACCOUNTING_REVIEW => 'Accounting Office',
            ProcurementDocument::STATUS_PENDING_APPROVAL,
            ProcurementDocument::STATUS_UNDER_APPROVAL => 'Head of the Procuring Entity',
            default => null,
        };
    }

    private function sourceProcurementDocument(Model $document): ?ProcurementDocument
    {
        if ($document instanceof ProcurementDocument) {
            return $document;
        }

        foreach (['sourcePrDocument', 'procurementDocument'] as $relation) {
            if (! method_exists($document, $relation)) {
                continue;
            }

            $document->loadMissing($relation);

            if ($document->{$relation} instanceof ProcurementDocument) {
                return $document->{$relation};
            }
        }

        if ($document instanceof InspectionAcceptanceRecord && method_exists($document, 'purchaseOrder')) {
            $document->loadMissing('purchaseOrder.sourcePrDocument', 'purchaseOrder.procurementDocument');

            return $document->purchaseOrder?->sourcePrDocument ?: $document->purchaseOrder?->procurementDocument;
        }

        return null;
    }

    private function statusFor(Model $document, ?ProcurementDocument $source): ?string
    {
        foreach ([$document, $source] as $item) {
            if (! $item) {
                continue;
            }

            foreach (['status', 'pr_status', 'signature_status'] as $attribute) {
                if (filled($item->{$attribute} ?? null)) {
                    return (string) $item->{$attribute};
                }
            }
        }

        return null;
    }

    private function stageFor(Model $document, ?ProcurementDocument $source): ?string
    {
        foreach ([$document, $source] as $item) {
            if (! $item) {
                continue;
            }

            foreach (['stage', 'current_stage', 'workflow_stage'] as $attribute) {
                if (filled($item->{$attribute} ?? null)) {
                    return (string) $item->{$attribute};
                }
            }
        }

        return null;
    }

    private function nextExpectedStep(Model $document, string $type, ?string $status): string
    {
        if ($document instanceof BacResolution || $type === 'bac_resolution') {
            return match ($status) {
                BacResolution::STATUS_DRAFT => 'BAC Secretariat should submit the BAC Resolution for signature when ready.',
                BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR => 'BAC Chair should review and sign or return the BAC Resolution.',
                BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR => 'BAC Chair should forward the confirmed BAC Resolution to HOPE.',
                BacResolution::STATUS_FORWARDED_TO_HOPE => 'Head of the Procuring Entity should approve or return the BAC Resolution.',
                BacResolution::STATUS_APPROVED_BY_HOPE => 'End User can proceed with the next SVP document steps.',
                BacResolution::STATUS_RETURNED_BY_BAC_CHAIR,
                BacResolution::STATUS_RETURNED_BY_HOPE,
                BacResolution::STATUS_RETURNED_TO_END_USER,
                BacResolution::STATUS_RETURNED => 'Review correction remarks and resubmit through the configured BAC Resolution workflow.',
                default => 'Check the current BAC Resolution workflow step manually and follow up with the recorded holder if needed.',
            };
        }

        if ($document instanceof PurchaseOrder || $type === 'purchase_order') {
            return match ($status) {
                PurchaseOrder::STATUS_DRAFT => 'Complete and submit the Purchase Order when ready.',
                PurchaseOrder::STATUS_SUBMITTED => 'Review the submitted Purchase Order for issuance or funding action.',
                PurchaseOrder::STATUS_FORWARDED_TO_SUPPLIER => 'Wait for supplier confirmation and delivery monitoring.',
                PurchaseOrder::STATUS_FUND_CERTIFIED => 'Proceed to authorized approval or issuance.',
                PurchaseOrder::STATUS_PREPARED => 'Complete the Purchase Order review and prepare for issuance.',
                PurchaseOrder::STATUS_APPROVED,
                PurchaseOrder::STATUS_ISSUED => 'Proceed to delivery, inspection, and acceptance.',
                PurchaseOrder::STATUS_COMPLETED => 'No further delay action is required.',
                PurchaseOrder::STATUS_RETURNED => 'Review return remarks and correct the Purchase Order.',
                default => 'Check the current Purchase Order workflow step manually and follow up with the recorded holder if needed.',
            };
        }

        return match ($status) {
            ProcurementDocument::STATUS_PPMP_DRAFT => 'Complete the required Head of Office e-signature before submitting this PPMP.',
            ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES => 'Complete the pending Head of Office e-signature.',
            ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED => 'Submit the signed PPMP to BACSEC-004 for APP consolidation.',
            ProcurementDocument::STATUS_PENDING_PPMP_REVIEW => 'BACSEC-004 should start APP consolidation review.',
            ProcurementDocument::STATUS_UNDER_PPMP_REVIEW => 'BACSEC-004 should accept for APP consolidation or return with remarks.',
            ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION => 'Include the accepted PPMP in APP consolidation.',
            ProcurementDocument::STATUS_PR_DRAFT => 'Submit the PR for official PR number assignment.',
            ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT => 'PR Numbering Staff should assign the official PR number.',
            ProcurementDocument::STATUS_PR_NUMBER_ASSIGNED,
            ProcurementDocument::STATUS_PR_NUMBER_ASSIGNED_RETURNED_TO_END_USER => 'End User should submit the numbered PR to BAC Secretariat.',
            ProcurementDocument::STATUS_PR_SUBMITTED,
            ProcurementDocument::STATUS_SUBMITTED_TO_BAC_SECRETARIAT,
            ProcurementDocument::STATUS_PR_RECEIVED_BY_BAC_SECRETARIAT => 'BAC Secretariat should validate the submitted PR.',
            ProcurementDocument::STATUS_UNDER_PR_VALIDATION => 'BAC Secretariat should route the PR to the next review step or return it with remarks.',
            ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION => 'BAC Secretariat should prepare the BAC Resolution.',
            default => 'Check the current workflow step manually and follow up with the recorded holder if needed.',
        };
    }

    private function riskLevelForDays(int $days): string
    {
        return match (true) {
            $days > 10 => AiDelayRiskCheck::RISK_CRITICAL,
            $days >= 6 => AiDelayRiskCheck::RISK_HIGH,
            $days >= 3 => AiDelayRiskCheck::RISK_MEDIUM,
            default => AiDelayRiskCheck::RISK_LOW,
        };
    }

    private function riskLabel(string $riskLevel): string
    {
        return match ($riskLevel) {
            AiDelayRiskCheck::RISK_CRITICAL => 'Critical Delay Risk',
            AiDelayRiskCheck::RISK_HIGH => 'High Delay Risk',
            AiDelayRiskCheck::RISK_MEDIUM => 'Medium Delay Risk',
            default => 'Low Delay Risk',
        };
    }

    private function maxRisk(string $current, string $candidate): string
    {
        $rank = [
            AiDelayRiskCheck::RISK_LOW => 1,
            AiDelayRiskCheck::RISK_MEDIUM => 2,
            AiDelayRiskCheck::RISK_HIGH => 3,
            AiDelayRiskCheck::RISK_CRITICAL => 4,
        ];

        return ($rank[$candidate] ?? 0) > ($rank[$current] ?? 0) ? $candidate : $current;
    }

    private function fallbackMessage(array $context, array $systemAssessment): string
    {
        if ($systemAssessment['risk_level'] === AiDelayRiskCheck::RISK_LOW) {
            return 'This document is still within the normal monitoring window based on the latest recorded workflow activity.';
        }

        return "This document has been waiting for {$context['days_waiting']} days. Follow up with {$context['current_holder']} and review the latest workflow remarks.";
    }

    private function fallbackRecommendation(array $context, array $systemAssessment): string
    {
        return match ($systemAssessment['risk_level']) {
            AiDelayRiskCheck::RISK_CRITICAL => "Escalate this document to the responsible office immediately and verify why no movement was recorded for {$context['days_waiting']} days.",
            AiDelayRiskCheck::RISK_HIGH => "Follow up with {$context['current_holder']} and confirm the next workflow action today.",
            AiDelayRiskCheck::RISK_MEDIUM => "Monitor this document closely and remind {$context['current_holder']} about the pending action.",
            default => $context['next_expected_step'],
        };
    }

    private function documentTypeCandidates(string $type, Model $document): array
    {
        $class = Str::snake(class_basename($document));

        $map = [
            'ppmp' => ['ppmp', 'PPMP', 'procurement_document'],
            'ppmp_record' => ['ppmp_record', 'ppmp'],
            'purchase_request' => ['purchase_request', 'procurement_document', 'PR', 'Purchase Request'],
            'procurement_document' => ['procurement_document', 'purchase_request', 'PR', 'PPMP'],
            'app' => ['app', 'annual_procurement_plan', 'APP'],
            'supplemental_app' => ['supplemental_app', 'Supplemental APP'],
            'bac_resolution' => ['bac_resolution', 'BAC Resolution'],
            'rfq' => ['rfq', 'request_for_quotation', 'RFQ'],
            'abstract' => ['abstract', 'abstract_quotation', 'Abstract'],
            'purchase_order' => ['purchase_order', 'PO', 'Purchase Order'],
            'inspection_acceptance' => ['inspection_acceptance', 'inspection_acceptance_record'],
        ];

        return array_values(array_unique(array_merge($map[$type] ?? [$type], [$class])));
    }

    private function titleFor(Model $document, ?ProcurementDocument $source): string
    {
        foreach ([$document, $source] as $item) {
            if (! $item) {
                continue;
            }

            foreach (['title', 'project_title', 'purpose', 'description', 'supplier_name'] as $attribute) {
                if (filled($item->{$attribute} ?? null)) {
                    return Str::limit((string) $item->{$attribute}, 140);
                }
            }
        }

        return class_basename($document) . ' #' . $document->getKey();
    }

    private function typeLabel(string $type, Model $document): string
    {
        if (method_exists($document, 'documentType')) {
            return (string) $document->documentType();
        }

        return match ($type) {
            'ppmp', 'ppmp_record' => 'Project Procurement Management Plan',
            'purchase_request' => 'Purchase Request',
            'app' => 'Annual Procurement Plan',
            'supplemental_app' => 'Supplemental APP',
            'bac_resolution' => 'BAC Resolution',
            'rfq' => 'Request for Quotation',
            'abstract' => 'Abstract of Quotations',
            'purchase_order' => 'Purchase Order',
            'inspection_acceptance' => 'Inspection / Acceptance',
            default => Str::title(str_replace('_', ' ', $type)),
        };
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
            'bac_resolutions', 'resolution' => 'bac_resolution',
            default => $type,
        };
    }

    private function humanizeStatus(?string $status): ?string
    {
        if (! filled($status)) {
            return null;
        }

        $label = Str::of($status)
            ->replace(['_', '-'], ' ')
            ->title()
            ->toString();

        $label = preg_replace('/\bPpmp\b/', 'PPMP', $label);
        $label = preg_replace('/\bPr\b/', 'PR', $label);
        $label = preg_replace('/\bPo\b/', 'PO', $label);
        $label = preg_replace('/\bBac\b/', 'BAC', $label);
        $label = preg_replace('/\bRfq\b/', 'RFQ', $label);
        $label = preg_replace('/\bApp\b/', 'APP', $label);
        $label = preg_replace('/\bHope\b/', 'HOPE', $label);

        return $label;
    }

    private function roleLabel(?string $role): ?string
    {
        if (! filled($role)) {
            return null;
        }

        return match ($role) {
            User::ROLE_HEAD_OFFICE, 'head_office' => 'Head of Office / End User',
            User::ROLE_BAC_SECRETARIAT, 'bac_secretariat' => 'BAC Secretariat',
            User::ROLE_BAC_MEMBER, 'bac_member' => 'BAC Member',
            User::ROLE_BAC_CHAIR, 'bac_chair' => 'BAC Chair',
            User::ROLE_BUDGET, 'budget_officer', 'budget' => 'Budget Office',
            User::ROLE_ACCOUNTING, 'accounting_officer', 'accounting' => 'Accounting Office',
            User::ROLE_APPROVING_AUTHORITY, 'approving_authority' => 'Head of the Procuring Entity',
            User::ROLE_PR_NUMBERING, 'pr_numbering_staff', 'pr-numbering' => 'PR Numbering Staff',
            User::ROLE_ADMIN, 'admin' => 'Admin',
            default => Str::title(str_replace(['_', '-'], ' ', $role)),
        };
    }

    private function dateFromAttributes(Model $document, array $attributes): ?Carbon
    {
        foreach ($attributes as $attribute) {
            if (filled($document->{$attribute} ?? null)) {
                return $this->toCarbon($document->{$attribute});
            }
        }

        return null;
    }

    private function latestDateFrom(Model $document, array $attributes): ?Carbon
    {
        return collect($attributes)
            ->map(fn (string $attribute) => $this->dateFromAttributes($document, [$attribute]))
            ->filter()
            ->sortDesc()
            ->first();
    }

    private function toCarbon(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function formatDate(mixed $value): ?string
    {
        $date = $this->toCarbon($value);

        return $date?->format('M d, Y h:i A');
    }

    private function dateString(mixed $value): ?string
    {
        return $this->toCarbon($value)?->toDateTimeString();
    }

    private function hasFutureDates(array $context): bool
    {
        foreach ([
            $context['created_at'] ?? null,
            $context['submitted_at'] ?? null,
            $context['last_activity_at'] ?? null,
        ] as $date) {
            $parsed = $this->toCarbon($date);

            if ($parsed && $parsed->gt(now()->addMinutes(5))) {
                return true;
            }
        }

        foreach ($context['history'] ?? [] as $event) {
            $parsed = $this->toCarbon($event['raw_date'] ?? $event['date'] ?? null);

            if ($parsed && $parsed->gt(now()->addMinutes(5))) {
                return true;
            }
        }

        return false;
    }

    private function uniqueList(array $items): array
    {
        return collect($items)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
