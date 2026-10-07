<?php

namespace App\Services;

use App\Models\AbstractQuotation;
use App\Models\AiDelayRiskCheck;
use App\Models\AiDocumentCheck;
use App\Models\AiDocumentMetadata;
use App\Models\AiSummaryReport;
use App\Models\AnnualProcurementPlan;
use App\Models\AuditLog;
use App\Models\BacResolution;
use App\Models\InspectionAcceptanceRecord;
use App\Models\Ppmp;
use App\Models\ProcurementDocument;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\SupplementalApp;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AiSummaryDashboardService
{
    private const SUMMARY_TYPE = 'procurement_insights';

    public function __construct(private readonly OpenAIService $openAI)
    {
    }

    public function dashboardData(User $user, array $filters = []): array
    {
        $this->authorize($user);

        $normalized = $this->normalizeFilters($filters);
        $summary = Cache::remember(
            $this->cacheKey($normalized),
            (int) config('papertrail.ai_summary_cache_seconds', 300),
            fn () => $this->calculateSummary($normalized)
        );

        return [
            'summary' => $summary,
            'filters' => $normalized,
            'documentTypeOptions' => $this->documentTypeOptions(),
            'statusOptions' => $this->statusOptions(),
            'latestReport' => $this->latestReport(),
            'openAiConfigured' => $this->openAI->isConfigured(),
        ];
    }

    public function generateSummary(User $user, array $filters = []): AiSummaryReport
    {
        $this->authorize($user);

        $normalized = $this->normalizeFilters($filters);
        $summary = $this->calculateSummary($normalized);

        try {
            $aiSummary = $this->requestAiSummary($summary);

            $report = AiSummaryReport::create([
                'report_period' => $summary['period'],
                'summary_type' => self::SUMMARY_TYPE,
                'summary_data' => $summary,
                'ai_summary' => $aiSummary,
                'generated_by_user_id' => $user->id,
            ]);

            Cache::forget($this->cacheKey($normalized));

            AuditLogger::log(
                'AI Procurement Summary',
                'ai_summary_generated',
                'Admin generated an AI procurement summary.',
                $report,
                null,
                null,
                'info',
                [
                    'period' => $summary['period'],
                    'document_type' => $normalized['document_type'],
                    'office_id' => $normalized['office_id'],
                    'status' => $normalized['status'],
                ]
            );

            Log::info('ai_summary_generated', [
                'user_id' => $user->id,
                'period' => $summary['period'],
                'total_documents' => $summary['total_documents'],
            ]);

            return $report;
        } catch (Throwable $exception) {
            AuditLogger::log(
                'AI Procurement Summary',
                'ai_summary_failed',
                'AI procurement summary generation failed.',
                null,
                null,
                null,
                'warning',
                [
                    'period' => $summary['period'],
                    'failure' => $exception::class,
                ]
            );

            Log::error('AI procurement summary generation failed.', [
                'user_id' => $user->id,
                'period' => $summary['period'],
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException('AI summary generation is temporarily unavailable.', previous: $exception);
        }
    }

    public function latestReport(): ?AiSummaryReport
    {
        if (! Schema::hasTable('ai_summary_reports')) {
            return null;
        }

        return AiSummaryReport::query()
            ->with('generatedBy')
            ->where('summary_type', self::SUMMARY_TYPE)
            ->latest()
            ->first();
    }

    public function documentTypeOptions(): array
    {
        return [
            'all' => 'All document types',
            'ppmp' => 'PPMP',
            'app' => 'APP',
            'supplemental_app' => 'Supplemental APP',
            'purchase_request' => 'Purchase Request',
            'bac_resolution' => 'BAC Resolution',
            'rfq' => 'RFQ',
            'abstract' => 'Abstract',
            'purchase_order' => 'Purchase Order',
            'inspection_acceptance' => 'Inspection / Acceptance',
        ];
    }

    public function statusOptions(): array
    {
        $statuses = collect();

        foreach ($this->documentSources() as $source) {
            $class = $source['class'];

            if (! $this->sourceExists($class) || ! $this->hasColumn($class, 'status')) {
                continue;
            }

            $statuses = $statuses->merge(
                $class::query()
                    ->select('status')
                    ->whereNotNull('status')
                    ->distinct()
                    ->pluck('status')
            );
        }

        return $statuses
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $status): array => [$status => $this->humanize($status)])
            ->prepend('All statuses', 'all')
            ->all();
    }

    private function calculateSummary(array $filters): array
    {
        $typeCounts = [];
        $statusBuckets = [
            'Draft' => 0,
            'Pending / For Action' => 0,
            'In Progress' => 0,
            'Completed / Approved' => 0,
            'Returned / Cancelled' => 0,
            'Unspecified' => 0,
        ];
        $totalDocuments = 0;
        $processingDays = [];

        foreach ($this->documentSources() as $source) {
            $query = $this->queryForSource($source, $filters);

            if (! $query) {
                continue;
            }

            $count = (clone $query)->count();
            $totalDocuments += $count;
            $typeCounts[$source['label']] = ($typeCounts[$source['label']] ?? 0) + $count;

            if ($this->hasColumn($source['class'], 'status')) {
                (clone $query)
                    ->selectRaw('status, COUNT(*) as aggregate_count')
                    ->groupBy('status')
                    ->get()
                    ->each(function ($row) use (&$statusBuckets): void {
                        $bucket = $this->statusBucket($row->status);
                        $statusBuckets[$bucket] = ($statusBuckets[$bucket] ?? 0) + (int) $row->aggregate_count;
                    });
            } else {
                $statusBuckets['Unspecified'] += $count;
            }

            $processingDays = array_merge($processingDays, $this->processingDaysFor($source, $query));
        }

        $completeness = $this->completenessSummary($filters);
        $delayRisk = $this->delayRiskSummary($filters);
        $metadata = $this->metadataSummary($filters);
        $routeValidation = $this->routeValidationSummary($filters);
        $commonMissing = $this->commonMissingRequirements($filters);
        $recentActivity = $this->recentAiActivity($filters);

        $averageProcessingDays = count($processingDays) > 0
            ? round(array_sum($processingDays) / count($processingDays), 1)
            : 0.0;

        $documentTypes = collect($typeCounts)
            ->map(fn (int $count, string $label): array => [
                'label' => $label,
                'count' => $count,
                'percentage' => $totalDocuments > 0 ? round(($count / $totalDocuments) * 100) : 0,
                'tone' => $this->toneForDocumentType($label),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        $statusBreakdown = collect($statusBuckets)
            ->map(fn (int $count, string $label): array => [
                'label' => $label,
                'count' => $count,
                'percentage' => $totalDocuments > 0 ? round(($count / $totalDocuments) * 100) : 0,
            ])
            ->values()
            ->all();

        return [
            'period' => $this->periodLabel($filters),
            'generated_at' => now()->format('M d, Y h:i A'),
            'filters' => $this->filterLabels($filters),
            'total_documents' => $totalDocuments,
            'average_processing_days' => $averageProcessingDays,
            'completeness_average' => $completeness['average_score'],
            'completeness_checks' => $completeness['checks'],
            'low_completeness_count' => $completeness['low_score_count'],
            'high_risk_documents' => $delayRisk['high_risk_count'],
            'route_warning_count' => $routeValidation['warning_count'],
            'metadata_pending_review_count' => $metadata['pending_review_count'],
            'document_types' => $documentTypes,
            'status_breakdown' => $statusBreakdown,
            'delay_risk' => $delayRisk,
            'metadata' => $metadata,
            'route_validation' => $routeValidation,
            'common_missing_requirements' => $commonMissing,
            'recent_ai_activity' => $recentActivity,
        ];
    }

    private function requestAiSummary(array $summary): string
    {
        if (! $this->openAI->isConfigured()) {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $payload = Arr::only($summary, [
            'period',
            'filters',
            'total_documents',
            'average_processing_days',
            'completeness_average',
            'high_risk_documents',
            'route_warning_count',
            'metadata_pending_review_count',
            'document_types',
            'status_breakdown',
            'delay_risk',
            'metadata',
            'route_validation',
            'common_missing_requirements',
        ]);

        return $this->openAI->chat([
            [
                'role' => 'system',
                'content' => 'You are PaperTrail AI Procurement Summary Assistant. Generate a concise procurement performance summary based only on the provided statistics. Explain the current procurement condition, common issues, and possible improvements. Do not make decisions. Do not invent information. Return a professional administrative summary.',
            ],
            [
                'role' => 'user',
                'content' => json_encode($payload, JSON_PRETTY_PRINT),
            ],
        ], [
            'temperature' => 0.2,
            'max_tokens' => 420,
        ]);
    }

    private function completenessSummary(array $filters): array
    {
        if (! Schema::hasTable('ai_document_checks')) {
            return ['checks' => 0, 'average_score' => 0, 'low_score_count' => 0];
        }

        $query = AiDocumentCheck::query()
            ->where('status', AiDocumentCheck::STATUS_COMPLETED)
            ->whereNotNull('completeness_score');

        $this->applyAiFilters($query, 'document_type', $filters, 'user');

        return [
            'checks' => (clone $query)->count(),
            'average_score' => (int) round((float) (clone $query)->avg('completeness_score')),
            'low_score_count' => (clone $query)->where('completeness_score', '<', 75)->count(),
        ];
    }

    private function delayRiskSummary(array $filters): array
    {
        $levels = [
            AiDelayRiskCheck::RISK_LOW => 0,
            AiDelayRiskCheck::RISK_MEDIUM => 0,
            AiDelayRiskCheck::RISK_HIGH => 0,
            AiDelayRiskCheck::RISK_CRITICAL => 0,
        ];

        if (! Schema::hasTable('ai_delay_risk_checks')) {
            return [
                'levels' => $levels,
                'total_checks' => 0,
                'high_risk_count' => 0,
                'average_delay_days' => 0,
                'top_recommendations' => [],
            ];
        }

        $query = AiDelayRiskCheck::query()
            ->where('status', AiDelayRiskCheck::STATUS_COMPLETED);

        $this->applyAiFilters($query, 'document_type', $filters, 'createdBy');

        (clone $query)
            ->selectRaw('risk_level, COUNT(*) as aggregate_count')
            ->groupBy('risk_level')
            ->get()
            ->each(function ($row) use (&$levels): void {
                $level = strtoupper((string) $row->risk_level);
                $levels[$level] = (int) $row->aggregate_count;
            });

        $recommendations = (clone $query)
            ->whereNotNull('recommendations')
            ->latest()
            ->limit(12)
            ->pluck('recommendations')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->take(4)
            ->values()
            ->all();

        return [
            'levels' => $levels,
            'total_checks' => array_sum($levels),
            'high_risk_count' => ($levels[AiDelayRiskCheck::RISK_HIGH] ?? 0) + ($levels[AiDelayRiskCheck::RISK_CRITICAL] ?? 0),
            'average_delay_days' => (int) round((float) (clone $query)->avg('delay_days')),
            'top_recommendations' => $recommendations,
        ];
    }

    private function metadataSummary(array $filters): array
    {
        if (! Schema::hasTable('ai_document_metadata')) {
            return [
                'total_records' => 0,
                'average_confidence' => 0,
                'pending_review_count' => 0,
                'status_counts' => [],
                'classifications' => [],
            ];
        }

        $query = AiDocumentMetadata::query();
        $this->applyAiFilters($query, 'document_type', $filters, 'createdBy');

        $records = (clone $query)
            ->latest()
            ->limit(1000)
            ->get(['classification_result', 'confidence_score', 'status']);

        $classifications = $records
            ->map(fn (AiDocumentMetadata $record) => $record->classification_result['category'] ?? null)
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(6)
            ->map(fn (int $count, string $label): array => [
                'label' => $label,
                'count' => $count,
            ])
            ->values()
            ->all();

        $statusCounts = $records
            ->pluck('status')
            ->filter()
            ->countBy()
            ->map(fn (int $count, string $status): array => [
                'label' => $this->humanize($status),
                'count' => $count,
            ])
            ->values()
            ->all();

        return [
            'total_records' => $records->count(),
            'average_confidence' => (int) round((float) $records->avg('confidence_score')),
            'pending_review_count' => $records->where('status', AiDocumentMetadata::STATUS_PENDING_REVIEW)->count(),
            'status_counts' => $statusCounts,
            'classifications' => $classifications,
        ];
    }

    private function routeValidationSummary(array $filters): array
    {
        if (! Schema::hasTable('audit_logs')) {
            return ['total_checks' => 0, 'warning_count' => 0, 'failed_count' => 0];
        }

        $query = AuditLog::query()->where(function (Builder $builder): void {
            $builder->where('module', 'AI Route Validation')
                ->orWhere('action', 'like', 'ai_route_validation%');
        });

        $this->applyAuditFilters($query, $filters);

        return [
            'total_checks' => (clone $query)->count(),
            'warning_count' => (clone $query)
                ->where(function (Builder $builder): void {
                    $builder->whereIn('status', ['warning', 'failed', 'denied'])
                        ->orWhereIn('severity', ['warning', 'critical']);
                })
                ->count(),
            'failed_count' => (clone $query)->where('status', 'failed')->count(),
        ];
    }

    private function commonMissingRequirements(array $filters): array
    {
        if (! Schema::hasTable('ai_document_checks')) {
            return [];
        }

        $query = AiDocumentCheck::query()
            ->where('status', AiDocumentCheck::STATUS_COMPLETED)
            ->whereNotNull('missing_requirements');

        $this->applyAiFilters($query, 'document_type', $filters, 'user');

        return (clone $query)
            ->latest()
            ->limit(1000)
            ->get(['missing_requirements'])
            ->flatMap(fn (AiDocumentCheck $check): array => Arr::wrap($check->missing_requirements))
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(6)
            ->map(fn (int $count, string $label): array => [
                'label' => $label,
                'count' => $count,
            ])
            ->values()
            ->all();
    }

    private function recentAiActivity(array $filters): array
    {
        $items = collect();

        if (Schema::hasTable('ai_document_checks')) {
            $query = AiDocumentCheck::query();
            $this->applyAiFilters($query, 'document_type', $filters, 'user');

            $items = $items->merge(
                (clone $query)->latest()->limit(6)->get()->map(fn (AiDocumentCheck $check): array => [
                    'type' => 'Completeness',
                    'title' => $this->typeLabel($check->document_type) . ' ' . ($check->document_tracking_number ?: '#' . $check->document_id),
                    'detail' => $check->status === AiDocumentCheck::STATUS_COMPLETED
                        ? 'Score ' . ((int) $check->completeness_score) . '%'
                        : $this->humanize($check->status),
                    'tone' => $this->scoreTone((int) $check->completeness_score),
                    'created_at' => $check->created_at,
                ])
            );
        }

        if (Schema::hasTable('ai_delay_risk_checks')) {
            $query = AiDelayRiskCheck::query();
            $this->applyAiFilters($query, 'document_type', $filters, 'createdBy');

            $items = $items->merge(
                (clone $query)->latest()->limit(6)->get()->map(fn (AiDelayRiskCheck $check): array => [
                    'type' => 'Delay Risk',
                    'title' => $this->typeLabel($check->document_type) . ' ' . ($check->tracking_number ?: '#' . $check->document_id),
                    'detail' => 'Risk level ' . ($check->risk_level ?: 'LOW'),
                    'tone' => strtolower((string) ($check->risk_level ?: 'LOW')),
                    'created_at' => $check->created_at,
                ])
            );
        }

        if (Schema::hasTable('ai_document_metadata')) {
            $query = AiDocumentMetadata::query();
            $this->applyAiFilters($query, 'document_type', $filters, 'createdBy');

            $items = $items->merge(
                (clone $query)->latest()->limit(6)->get()->map(fn (AiDocumentMetadata $metadata): array => [
                    'type' => 'Metadata',
                    'title' => $metadata->classification_result['category'] ?? $this->typeLabel($metadata->document_type),
                    'detail' => 'Confidence ' . ((int) $metadata->confidence_score) . '%',
                    'tone' => $this->scoreTone((int) $metadata->confidence_score),
                    'created_at' => $metadata->created_at,
                ])
            );
        }

        if (Schema::hasTable('audit_logs')) {
            $query = AuditLog::query()->where(function (Builder $builder): void {
                $builder->where('module', 'AI Route Validation')
                    ->orWhere('action', 'like', 'ai_route_validation%');
            });
            $this->applyAuditFilters($query, $filters);

            $items = $items->merge(
                (clone $query)->latest()->limit(6)->get()->map(fn (AuditLog $log): array => [
                    'type' => 'Route Validation',
                    'title' => $log->tracking_number ?: $this->humanize($log->document_type ?: 'Document route'),
                    'detail' => $this->humanize($log->action),
                    'tone' => in_array($log->status, ['failed', 'denied', 'warning'], true) ? 'high' : 'low',
                    'created_at' => $log->created_at,
                ])
            );
        }

        return $items
            ->filter(fn (array $item): bool => $item['created_at'] !== null)
            ->sortByDesc(fn (array $item) => $item['created_at']->timestamp)
            ->take(8)
            ->map(fn (array $item): array => [
                ...$item,
                'date' => $item['created_at']?->format('M d, Y h:i A') ?? 'N/A',
            ])
            ->values()
            ->all();
    }

    private function documentSources(): array
    {
        return [
            [
                'key' => 'ppmp',
                'label' => 'PPMP',
                'class' => ProcurementDocument::class,
                'document_type_values' => ['PPMP'],
            ],
            [
                'key' => 'ppmp',
                'label' => 'PPMP',
                'class' => Ppmp::class,
            ],
            [
                'key' => 'app',
                'label' => 'APP',
                'class' => AnnualProcurementPlan::class,
            ],
            [
                'key' => 'supplemental_app',
                'label' => 'Supplemental APP',
                'class' => SupplementalApp::class,
            ],
            [
                'key' => 'purchase_request',
                'label' => 'Purchase Request',
                'class' => ProcurementDocument::class,
                'document_type_values' => ['PR', 'Purchase Request'],
            ],
            [
                'key' => 'bac_resolution',
                'label' => 'BAC Resolution',
                'class' => BacResolution::class,
            ],
            [
                'key' => 'rfq',
                'label' => 'RFQ',
                'class' => Rfq::class,
            ],
            [
                'key' => 'abstract',
                'label' => 'Abstract',
                'class' => AbstractQuotation::class,
            ],
            [
                'key' => 'purchase_order',
                'label' => 'Purchase Order',
                'class' => PurchaseOrder::class,
            ],
            [
                'key' => 'inspection_acceptance',
                'label' => 'Inspection / Acceptance',
                'class' => InspectionAcceptanceRecord::class,
            ],
        ];
    }

    private function queryForSource(array $source, array $filters): ?Builder
    {
        if ($filters['document_type'] !== 'all' && $filters['document_type'] !== $source['key']) {
            return null;
        }

        $class = $source['class'];

        if (! $this->sourceExists($class)) {
            return null;
        }

        /** @var Builder $query */
        $query = $class::query();

        if (! empty($source['document_type_values']) && $this->hasColumn($class, 'document_type')) {
            $query->whereIn('document_type', $source['document_type_values']);
        }

        $this->applyDateFilters($query, $class, $filters);

        if ($filters['status'] !== 'all' && $this->hasColumn($class, 'status')) {
            $query->where('status', $filters['status']);
        }

        if ($filters['office_id'] !== 'all') {
            $this->applyOfficeFilter($query, $class, (int) $filters['office_id']);
        }

        return $query;
    }

    private function applyAiFilters(Builder $query, string $documentTypeColumn, array $filters, ?string $userRelation = null): void
    {
        $this->applyDateFilters($query, get_class($query->getModel()), $filters);

        if ($filters['document_type'] !== 'all') {
            $query->whereIn($documentTypeColumn, $this->documentTypeAliases($filters['document_type']));
        }

        if ($filters['office_id'] !== 'all' && $userRelation) {
            $query->whereHas($userRelation, fn (Builder $user): Builder => $user->where('office_id', (int) $filters['office_id']));
        }
    }

    private function applyAuditFilters(Builder $query, array $filters): void
    {
        $this->applyDateFilters($query, AuditLog::class, $filters);

        if ($filters['document_type'] !== 'all') {
            $query->whereIn('document_type', $this->documentTypeAliases($filters['document_type']));
        }

        if ($filters['office_id'] !== 'all') {
            $query->where('office_id', (int) $filters['office_id']);
        }
    }

    private function applyDateFilters(Builder $query, string $class, array $filters): void
    {
        if (! $this->hasColumn($class, 'created_at')) {
            return;
        }

        if ($filters['date_from']) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
    }

    private function applyOfficeFilter(Builder $query, string $class, int $officeId): void
    {
        $columns = collect([
            'office_id',
            'submitting_office_id',
            'current_office_id',
            'requesting_office_id',
            'route_destination_office_id',
        ])->filter(fn (string $column): bool => $this->hasColumn($class, $column))->values();

        $documentOfficeColumns = collect([
            'submitting_office_id',
            'current_office_id',
            'route_destination_office_id',
        ])->filter(fn (string $column): bool => $this->hasColumn(ProcurementDocument::class, $column))->values();
        $model = new $class();
        $relations = $documentOfficeColumns->isEmpty()
            ? collect()
            : collect(['sourcePrDocument', 'procurementDocument'])
                ->filter(fn (string $relation): bool => method_exists($model, $relation))
                ->values();

        if ($columns->isEmpty() && $relations->isEmpty()) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where(function (Builder $officeQuery) use ($columns, $relations, $documentOfficeColumns, $officeId): void {
            foreach ($columns as $column) {
                $officeQuery->orWhere($column, $officeId);
            }

            foreach ($relations as $relation) {
                $officeQuery->orWhereHas($relation, function (Builder $document) use ($documentOfficeColumns, $officeId): void {
                    $document->where(function (Builder $documentOffice) use ($documentOfficeColumns, $officeId): void {
                        foreach ($documentOfficeColumns as $column) {
                            $documentOffice->orWhere($column, $officeId);
                        }
                    });
                });
            }
        });
    }

    private function processingDaysFor(array $source, Builder $query): array
    {
        $class = $source['class'];

        if (! $this->hasColumn($class, 'created_at') || ! $this->hasColumn($class, 'updated_at')) {
            return [];
        }

        $columns = collect([
            'created_at',
            'updated_at',
            'submitted_at',
            'approved_at',
            'accepted_at',
            'completed_at',
            'reviewed_at',
            'issued_at',
        ])->filter(fn (string $column): bool => $this->hasColumn($class, $column))->values()->all();

        return (clone $query)
            ->select($columns)
            ->limit(1000)
            ->get()
            ->map(function ($document): ?float {
                $createdAt = $document->created_at ? Carbon::parse($document->created_at) : null;

                if (! $createdAt) {
                    return null;
                }

                $endedAt = collect([
                    $document->approved_at ?? null,
                    $document->accepted_at ?? null,
                    $document->completed_at ?? null,
                    $document->issued_at ?? null,
                    $document->reviewed_at ?? null,
                    $document->submitted_at ?? null,
                    $document->updated_at ?? null,
                ])
                    ->filter()
                    ->map(fn ($date) => Carbon::parse($date))
                    ->sortDesc()
                    ->first();

                if (! $endedAt) {
                    return null;
                }

                return round(max(0, $createdAt->diffInHours($endedAt)) / 24, 1);
            })
            ->filter(fn ($days): bool => $days !== null)
            ->values()
            ->all();
    }

    private function normalizeFilters(array $filters): array
    {
        $documentTypes = array_keys($this->documentTypeOptions());
        $requestedDocumentType = $filters['document_type'] ?? 'all';
        $documentType = in_array($requestedDocumentType, $documentTypes, true)
            ? $requestedDocumentType
            : 'all';

        return [
            'date_from' => $this->parseDate($filters['date_from'] ?? null, false),
            'date_to' => $this->parseDate($filters['date_to'] ?? null, true),
            'date_from_input' => trim((string) ($filters['date_from'] ?? '')),
            'date_to_input' => trim((string) ($filters['date_to'] ?? '')),
            'document_type' => $documentType,
            'office_id' => filled($filters['office_id'] ?? null) ? (string) $filters['office_id'] : 'all',
            'status' => filled($filters['status'] ?? null) ? (string) $filters['status'] : 'all',
        ];
    }

    private function parseDate(mixed $value, bool $endOfDay): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            $date = Carbon::parse($value);

            return $endOfDay ? $date->endOfDay() : $date->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private function filterLabels(array $filters): array
    {
        return [
            'period' => $this->periodLabel($filters),
            'document_type' => $this->documentTypeOptions()[$filters['document_type']] ?? 'All document types',
            'office' => $filters['office_id'] === 'all' ? 'All offices' : 'Selected office',
            'status' => $filters['status'] === 'all' ? 'All statuses' : $this->humanize($filters['status']),
        ];
    }

    private function periodLabel(array $filters): string
    {
        $from = $filters['date_from'];
        $to = $filters['date_to'];

        if ($from && $to) {
            return $from->format('M d, Y') . ' - ' . $to->format('M d, Y');
        }

        if ($from) {
            return 'From ' . $from->format('M d, Y');
        }

        if ($to) {
            return 'Until ' . $to->format('M d, Y');
        }

        return 'All available records';
    }

    private function cacheKey(array $filters): string
    {
        return 'papertrail_ai_summary_dashboard:' . md5(json_encode([
            'date_from' => $filters['date_from']?->toDateString(),
            'date_to' => $filters['date_to']?->toDateString(),
            'document_type' => $filters['document_type'],
            'office_id' => $filters['office_id'],
            'status' => $filters['status'],
        ]));
    }

    private function documentTypeAliases(string $type): array
    {
        return match ($type) {
            'ppmp' => ['ppmp', 'ppmp_record', 'standalone_ppmp', 'ppmp_document', 'PPMP'],
            'app' => ['app', 'annual_procurement_plan', 'annual_procurement_plans'],
            'supplemental_app' => ['supplemental_app', 'supplemental_apps'],
            'purchase_request' => ['purchase_request', 'pr', 'PR', 'Purchase Request'],
            'bac_resolution' => ['bac_resolution', 'bac_resolutions', 'resolution'],
            'rfq' => ['rfq', 'rfqs', 'request_for_quotation'],
            'abstract' => ['abstract', 'abstract_quotation', 'abstracts'],
            'purchase_order' => ['purchase_order', 'purchase_orders', 'po'],
            'inspection_acceptance' => ['inspection_acceptance', 'inspection_acceptance_record', 'inspection_acceptance_records'],
            default => [$type],
        };
    }

    private function statusBucket(?string $status): string
    {
        if (blank($status)) {
            return 'Unspecified';
        }

        $value = Str::lower($status);

        if (str_contains($value, 'draft')) {
            return 'Draft';
        }

        if (str_contains($value, 'returned') || str_contains($value, 'cancel')) {
            return 'Returned / Cancelled';
        }

        if (str_contains($value, 'approved')
            || str_contains($value, 'accepted')
            || str_contains($value, 'completed')
            || str_contains($value, 'issued')
            || str_contains($value, 'confirmed')
            || str_contains($value, 'certified')) {
            return 'Completed / Approved';
        }

        if (str_contains($value, 'under') || str_contains($value, 'review')) {
            return 'In Progress';
        }

        return 'Pending / For Action';
    }

    private function typeLabel(?string $type): string
    {
        if (blank($type)) {
            return 'Document';
        }

        $normalized = Str::of((string) $type)->replace('-', '_')->lower()->toString();

        return $this->documentTypeOptions()[$normalized]
            ?? match ($normalized) {
                'pr' => 'Purchase Request',
                'po' => 'Purchase Order',
                default => $this->humanize((string) $type),
            };
    }

    private function humanize(?string $value): string
    {
        return Str::of((string) $value)
            ->replace(['_', '-'], ' ')
            ->title()
            ->toString();
    }

    private function toneForDocumentType(string $label): string
    {
        return match ($label) {
            'PPMP' => 'purple',
            'APP' => 'green',
            'Purchase Request' => 'blue',
            'Purchase Order' => 'orange',
            'RFQ' => 'teal',
            'Abstract' => 'indigo',
            'BAC Resolution' => 'amber',
            default => 'navy',
        };
    }

    private function scoreTone(int $score): string
    {
        if ($score >= 85) {
            return 'low';
        }

        if ($score >= 70) {
            return 'medium';
        }

        return 'high';
    }

    private function sourceExists(string $class): bool
    {
        return Schema::hasTable((new $class())->getTable());
    }

    private function hasColumn(string $class, string $column): bool
    {
        $table = (new $class())->getTable();

        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }

    private function authorize(User $user): void
    {
        abort_unless($user->isAdmin(), 403);
    }
}
