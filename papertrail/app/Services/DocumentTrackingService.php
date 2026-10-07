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
use App\Models\SvpProcurementChain;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DocumentTrackingService
{
    public function __construct(private readonly DocumentResolverService $resolver)
    {
    }

    public function getDocumentTracking(User $user, string $query): array
    {
        $trackingNumber = $this->extractTrackingNumber($query);
        $documentTypeHint = $this->documentTypeHint($query, $trackingNumber);

        $this->audit($user, 'document_tracking_requested', 'User requested document tracking through the Smart Procurement Chatbot.', null, [
            'document_type_hint' => $documentTypeHint,
            'has_tracking_number' => filled($trackingNumber),
        ]);

        if (! $trackingNumber) {
            return [
                'intent' => 'tracking',
                'found' => false,
                'authorized' => false,
                'needs_tracking_number' => true,
                'message' => $this->requestNumberMessage($documentTypeHint),
                'document' => null,
                'summary' => 'The user asked to track a document but did not provide a document number.',
            ];
        }

        try {
            $matches = $this->candidateDocuments($trackingNumber, $documentTypeHint);

            if ($matches->isEmpty()) {
                return $this->missingResponse($user, $trackingNumber, $documentTypeHint);
            }

            $authorizedDocument = $matches->first(fn (Model $document) => $this->resolver->canView($user, $document));

            if (! $authorizedDocument) {
                $this->audit($user, 'document_tracking_denied', 'Document tracking request was denied.', $matches->first(), [
                    'document_type' => $this->documentTypeLabel($matches->first()),
                    'tracking_number' => $this->displayNumber($matches->first()),
                ], 'warning');

                return [
                    'intent' => 'tracking',
                    'found' => true,
                    'authorized' => false,
                    'message' => 'I cannot find an authorized document matching that number.',
                    'document' => null,
                    'summary' => 'Tracking denied. The user is not authorized to view this document.',
                ];
            }

            $tracking = $this->buildTrackingPayload($authorizedDocument);

            $this->audit($user, 'document_tracking_success', 'Document tracking information was retrieved for the chatbot.', $authorizedDocument, [
                'document_type' => $tracking['document_type'],
                'tracking_number' => $tracking['tracking_number'],
            ]);

            return [
                'intent' => 'tracking',
                'found' => true,
                'authorized' => true,
                'message' => null,
                'document' => $tracking,
                'summary' => $this->safeSummary($tracking),
            ];
        } catch (Throwable $exception) {
            Log::error('PaperTrail document tracking lookup failed.', [
                'user_id' => $user->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $this->audit($user, 'chatbot_tracking_failed', 'Document tracking lookup failed safely.', null, [
                'document_type_hint' => $documentTypeHint,
                'error_class' => $exception::class,
            ], 'error');

            return [
                'intent' => 'tracking',
                'found' => false,
                'authorized' => false,
                'message' => 'I could not retrieve tracking information right now. Please try again later.',
                'document' => null,
                'summary' => 'Tracking lookup failed.',
            ];
        }
    }

    public function safeSummary(array $tracking): string
    {
        $timeline = collect($tracking['timeline'] ?? [])
            ->map(function (array $event): string {
                $date = $event['date_display'] ?? 'No date recorded';
                $location = filled($event['location'] ?? null) ? " at {$event['location']}" : '';

                return "- {$event['label']}{$location}: {$date}";
            })
            ->implode("\n");

        $timeline = $timeline !== '' ? $timeline : '- No routing history has been recorded yet.';

        return <<<SUMMARY
Document Type: {$tracking['document_type']}
Tracking Number: {$tracking['tracking_number']}
Title: {$tracking['title']}
Current Status: {$tracking['current_status']}
Current Location: {$tracking['current_holder']}
Current Stage: {$tracking['current_stage']}
Next Expected Step: {$tracking['next_step']}

Timeline:
{$timeline}
SUMMARY;
    }

    private function candidateDocuments(?string $trackingNumber, ?string $documentTypeHint): Collection
    {
        $candidates = collect();

        foreach ($this->searchPlans($documentTypeHint) as $plan) {
            /** @var class-string<Model> $model */
            $model = $plan['model'];
            $query = $model::query();

            if ($relations = $plan['with'] ?? []) {
                $query->with($relations);
            }

            if ($trackingNumber) {
                $this->applyNumberFilter($query, $plan['columns'], $trackingNumber);
            } elseif ($documentTypeHint && in_array($documentTypeHint, $plan['types'], true)) {
                $this->applyTypeHintFilter($query, $model, $documentTypeHint);
                $query->latest();
            } else {
                continue;
            }

            $candidates = $candidates->merge($query->limit(20)->get());
        }

        return $candidates;
    }

    private function searchPlans(?string $documentTypeHint): array
    {
        $plans = [
            [
                'types' => ['ppmp', 'pr', 'purchase_request', 'procurement_document'],
                'model' => ProcurementDocument::class,
                'columns' => ['tracking_number', 'pr_no', 'ppmp_no', 'title'],
                'with' => [
                    'submittingOffice',
                    'currentOffice',
                    'submittedBy',
                    'preparedBy',
                    'assignedTo',
                    'routingHistories.fromOffice',
                    'routingHistories.toOffice',
                    'routingHistories.actionBy',
                ],
            ],
            [
                'types' => ['app', 'annual_procurement_plan'],
                'model' => AnnualProcurementPlan::class,
                'columns' => ['app_number', 'app_no', 'title'],
                'with' => ['office', 'preparedBy', 'submittedBy', 'approvedBy'],
            ],
            [
                'types' => ['supplemental_app'],
                'model' => SupplementalApp::class,
                'columns' => ['supplemental_app_number', 'title', 'purpose'],
                'with' => ['requestingOffice', 'sourcePrDocument.submittingOffice', 'preparedBy', 'submittedBy', 'acceptedBy'],
            ],
            [
                'types' => ['bac_resolution', 'resolution'],
                'model' => BacResolution::class,
                'columns' => ['resolution_number', 'pr_number', 'title', 'project_title'],
                'with' => ['sourcePrDocument.submittingOffice', 'preparedBy', 'submittedBy', 'approvedBy', 'bacChairConfirmedBy', 'forwardedToHopeBy'],
            ],
            [
                'types' => ['rfq'],
                'model' => Rfq::class,
                'columns' => ['rfq_number', 'supplier_name', 'purpose'],
                'with' => ['sourcePrDocument.submittingOffice', 'sourceBacResolution', 'preparedBy', 'submittedBy'],
            ],
            [
                'types' => ['abstract', 'abstract_quotation'],
                'model' => AbstractQuotation::class,
                'columns' => ['abstract_number', 'project_name', 'purpose'],
                'with' => ['sourcePrDocument.submittingOffice', 'sourceRfq', 'sourceBacResolution', 'preparedBy', 'submittedBy'],
            ],
            [
                'types' => ['po', 'purchase_order'],
                'model' => PurchaseOrder::class,
                'columns' => ['po_number', 'supplier_name'],
                'with' => ['sourcePrDocument.submittingOffice', 'sourceBacResolution', 'sourceAbstract', 'preparedBy', 'submittedBy', 'issuedBy'],
            ],
            [
                'types' => ['inspection', 'inspection_acceptance'],
                'model' => InspectionAcceptanceRecord::class,
                'columns' => ['delivery_receipt_number', 'invoice_number'],
                'with' => ['sourcePrDocument.submittingOffice', 'purchaseOrder', 'office', 'inspectedBy', 'acceptedBy'],
            ],
        ];

        if (! $documentTypeHint) {
            return $plans;
        }

        $preferred = [];
        $rest = [];

        foreach ($plans as $plan) {
            if (in_array($documentTypeHint, $plan['types'], true)) {
                $preferred[] = $plan;
            } else {
                $rest[] = $plan;
            }
        }

        return array_merge($preferred, $rest);
    }

    private function applyNumberFilter(Builder $query, array $columns, string $trackingNumber): void
    {
        $normalized = $this->normalizeLookupValue($trackingNumber);
        $loose = '%' . str_replace('-', '%', $normalized) . '%';

        $query->where(function (Builder $builder) use ($columns, $normalized, $loose) {
            foreach ($columns as $column) {
                $builder->orWhere($column, $normalized)
                    ->orWhere($column, 'like', $loose);
            }
        });
    }

    private function applyTypeHintFilter(Builder $query, string $model, string $documentTypeHint): void
    {
        if ($model !== ProcurementDocument::class) {
            return;
        }

        if ($documentTypeHint === 'ppmp') {
            $query->where('document_type', 'PPMP');

            return;
        }

        if (in_array($documentTypeHint, ['pr', 'purchase_request', 'procurement_document'], true)) {
            $query->whereIn('document_type', ['PR', 'Purchase Request']);
        }
    }

    private function buildTrackingPayload(Model $document): array
    {
        $sourcePr = $this->sourcePrDocument($document);
        $chain = $this->svpChainFor($document, $sourcePr);
        $timeline = $this->timelineFor($document, $sourcePr, $chain);
        $currentStatus = $this->statusLabel($document->status ?? $sourcePr?->status ?? null);
        $currentStage = $this->stageFor($document, $sourcePr, $chain);

        return [
            'document_type' => $this->documentTypeLabel($document),
            'tracking_number' => $this->displayNumber($document),
            'title' => $this->documentTitle($document),
            'current_status' => $currentStatus,
            'current_holder' => $this->currentHolder($document, $sourcePr, $chain),
            'current_stage' => $currentStage,
            'timeline' => $timeline,
            'next_step' => $this->nextExpectedStep($document, $sourcePr, $chain),
        ];
    }

    private function timelineFor(Model $document, ?ProcurementDocument $sourcePr, ?SvpProcurementChain $chain): array
    {
        $events = collect();

        if ($sourcePr) {
            $sourcePr->loadMissing([
                'submittingOffice',
                'currentOffice',
                'submittedBy',
                'preparedBy',
                'routingHistories.fromOffice',
                'routingHistories.toOffice',
                'routingHistories.actionBy',
            ]);

            $events->push($this->timelineEvent(
                $sourcePr->document_type === 'PPMP' ? 'PPMP Draft Created' : 'Document Draft Created',
                $sourcePr->created_at,
                $sourcePr->submittingOffice?->name
            ));

            if ($sourcePr->submitted_at) {
                $events->push($this->timelineEvent('Submitted', $sourcePr->submitted_at, $sourcePr->submittingOffice?->name));
            }

            $history = $sourcePr->routingHistories()->with(['fromOffice', 'toOffice', 'actionBy'])->oldest('action_at')->get();

            foreach ($history as $item) {
                $events->push($this->timelineEvent(
                    $this->historyLabel($item->action, $item->status_to),
                    $item->action_at ?? $item->created_at,
                    $item->toOffice?->name ?? $item->fromOffice?->name
                ));
            }
        }

        if (! $document instanceof ProcurementDocument) {
            $events = $events->merge($this->modelTimeline($document));
        }

        if ($chain) {
            $chain->loadMissing(['events.fromOffice', 'events.toOffice', 'events.performedBy']);

            foreach ($chain->events as $event) {
                $events->push($this->timelineEvent(
                    $event->action ?: $event->stage ?: 'SVP workflow updated',
                    $event->created_at,
                    $event->toOffice?->name ?? $event->fromOffice?->name ?? $event->to_role ?? $event->from_role,
                    $event->stage,
                    $event->status
                ));
            }
        }

        $events = $events
            ->filter(fn (?array $event) => $event && $event['date'])
            ->sortBy('date')
            ->values()
            ->unique(fn (array $event) => $event['label'] . '|' . $event['date_iso'] . '|' . $event['location'])
            ->values();

        if ($events->isEmpty()) {
            $events->push($this->timelineEvent(
                'Document Recorded',
                $document->created_at ?? $sourcePr?->created_at,
                $this->resolver->officeNameFor($document) ?? $sourcePr?->submittingOffice?->name
            ));
        }

        return $events
            ->values()
            ->map(function (array $event, int $index) use ($events): array {
                $event['state'] = $index === $events->count() - 1 ? 'current' : 'completed';

                return $event;
            })
            ->all();
    }

    private function modelTimeline(Model $document): Collection
    {
        $events = collect();
        $office = $this->resolver->officeNameFor($document);

        $events->push($this->timelineEvent($this->documentTypeLabel($document) . ' Created', $document->created_at, $office));

        foreach ([
            'submitted_at' => 'Submitted',
            'bac_chair_confirmed_at' => 'BAC Chair Confirmed',
            'forwarded_to_hope_at' => 'Forwarded to HOPE',
            'approved_at' => 'Approved',
            'accepted_at' => 'Accepted',
            'issued_at' => 'Issued',
            'inspection_date' => 'Inspected',
            'acceptance_date' => 'Accepted',
            'returned_at' => 'Returned',
            'completed_at' => 'Completed',
            'cancelled_at' => 'Cancelled',
        ] as $attribute => $label) {
            if (filled($document->{$attribute} ?? null)) {
                $events->push($this->timelineEvent($label, $document->{$attribute}, $office));
            }
        }

        return $events;
    }

    private function timelineEvent(string $label, mixed $date, ?string $location = null, ?string $stage = null, ?string $status = null): ?array
    {
        if (! $date) {
            return null;
        }

        $date = $date instanceof \DateTimeInterface ? $date : rescue(fn () => \Carbon\Carbon::parse($date), null, false);

        if (! $date) {
            return null;
        }

        $date = $date->copy()->timezone(config('app.timezone', 'Asia/Manila'));

        return [
            'label' => $this->sentenceLabel($label),
            'date' => $date,
            'date_iso' => $date->toDateTimeString(),
            'date_display' => $date->format('M d, Y h:i A'),
            'location' => $location,
            'stage' => $stage,
            'status' => $this->statusLabel($status),
            'state' => 'completed',
        ];
    }

    private function sourcePrDocument(Model $document): ?ProcurementDocument
    {
        if ($document instanceof ProcurementDocument) {
            return $document;
        }

        foreach (['sourcePrDocument', 'procurementDocument'] as $relation) {
            if (method_exists($document, $relation)) {
                $document->loadMissing($relation);

                if ($document->{$relation} instanceof ProcurementDocument) {
                    return $document->{$relation};
                }
            }
        }

        if ($document instanceof InspectionAcceptanceRecord) {
            $document->loadMissing('purchaseOrder.sourcePrDocument');

            return $document->sourcePrDocument ?: $document->purchaseOrder?->sourcePrDocument;
        }

        return null;
    }

    private function svpChainFor(Model $document, ?ProcurementDocument $sourcePr): ?SvpProcurementChain
    {
        $query = SvpProcurementChain::query()->with(['events.fromOffice', 'events.toOffice', 'events.performedBy']);

        return match (true) {
            $document instanceof ProcurementDocument => $query->where('source_pr_document_id', $document->id)->first(),
            $document instanceof BacResolution => $query->where('bac_resolution_id', $document->id)
                ->orWhere('source_pr_document_id', $sourcePr?->id)->first(),
            $document instanceof Rfq => $query->where('rfq_id', $document->id)
                ->orWhere('source_pr_document_id', $sourcePr?->id)->first(),
            $document instanceof AbstractQuotation => $query->where('abstract_id', $document->id)
                ->orWhere('source_pr_document_id', $sourcePr?->id)->first(),
            $document instanceof PurchaseOrder => $query->where('purchase_order_id', $document->id)
                ->orWhere('source_pr_document_id', $sourcePr?->id)->first(),
            $document instanceof InspectionAcceptanceRecord => $query->where('inspection_id', $document->id)
                ->orWhere('source_pr_document_id', $sourcePr?->id)->first(),
            default => null,
        };
    }

    private function currentHolder(Model $document, ?ProcurementDocument $sourcePr, ?SvpProcurementChain $chain): string
    {
        if ($sourcePr) {
            $sourcePr->loadMissing(['currentOffice', 'assignedTo', 'submittingOffice']);

            if ($sourcePr->currentOffice?->name) {
                return $sourcePr->currentOffice->name;
            }

            if ($sourcePr->assignedTo?->name) {
                return $sourcePr->assignedTo->name;
            }
        }

        if ($chain?->current_stage) {
            return $this->holderFromStage($chain->current_stage);
        }

        return $this->holderFromStatus($document->status ?? $sourcePr?->status ?? null)
            ?? $this->resolver->officeNameFor($document)
            ?? 'Not currently assigned';
    }

    private function stageFor(Model $document, ?ProcurementDocument $sourcePr, ?SvpProcurementChain $chain): string
    {
        return $sourcePr?->stage
            ?: $chain?->current_stage
            ?: $this->documentTypeLabel($document);
    }

    private function nextExpectedStep(Model $document, ?ProcurementDocument $sourcePr, ?SvpProcurementChain $chain): string
    {
        $status = (string) ($sourcePr?->status ?? $document->status ?? $chain?->current_status ?? '');
        $stage = (string) ($sourcePr?->stage ?? $chain?->current_stage ?? '');

        $map = [
            ProcurementDocument::STATUS_PPMP_DRAFT => 'Complete the required Head of Office e-signature before submitting this PPMP.',
            ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES => 'Waiting for the required Head of Office e-signature.',
            ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED => 'Submit the signed PPMP to BACSEC-004 for APP consolidation.',
            ProcurementDocument::STATUS_PENDING_PPMP_REVIEW => 'Waiting for BACSEC-004 to start APP consolidation review.',
            ProcurementDocument::STATUS_UNDER_PPMP_REVIEW => 'BACSEC-004 reviews and either returns or accepts for APP consolidation.',
            ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION => 'Include this PPMP in APP consolidation.',
            ProcurementDocument::STATUS_PR_DRAFT => 'Submit the Purchase Request for PR number assignment.',
            ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT => 'Waiting for PR Numbering Staff to assign an official PR number.',
            ProcurementDocument::STATUS_PR_NUMBER_ASSIGNED => 'Submit the numbered Purchase Request to BAC Secretariat.',
            ProcurementDocument::STATUS_PR_NUMBER_ASSIGNED_RETURNED_TO_END_USER => 'Requesting Office forwards the finalized Purchase Request reference to BACSEC-002.',
            ProcurementDocument::STATUS_RETURNED_BY_PR_NUMBERING_STAFF => 'End User corrects the Purchase Request and resubmits for PR number assignment.',
            ProcurementDocument::STATUS_SUBMITTED_TO_BAC_SECRETARIAT => 'Waiting for BAC Secretariat receipt.',
            ProcurementDocument::STATUS_PR_SUBMITTED => 'Waiting for BAC Secretariat receipt.',
            ProcurementDocument::STATUS_PR_RECEIVED_BY_BAC_SECRETARIAT => 'BAC Secretariat starts PR validation.',
            ProcurementDocument::STATUS_UNDER_PR_VALIDATION => 'BAC Secretariat validates the PR and prepares the next workflow action.',
            ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION => 'BACSEC-002 prepares the BAC Resolution from the finalized PR reference.',
            ProcurementDocument::STATUS_BAC_RESOLUTION_CREATED => 'BAC Resolution moves to BAC Chair confirmation.',
            ProcurementDocument::STATUS_BAC_RESOLUTION_RETURNED_TO_END_USER => 'End User proceeds with the next SVP document step.',
            ProcurementDocument::STATUS_SVP_POSTING_REQUIRED => 'BACSEC-004 completes the required SVP posting before RFQ.',
            ProcurementDocument::STATUS_SVP_POSTING_COMPLETED => 'SVP posting is complete. End User prepares the RFQ.',
            ProcurementDocument::STATUS_READY_FOR_RFQ => 'End User prepares the RFQ.',
            ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW => 'Waiting for Budget Office review.',
            ProcurementDocument::STATUS_UNDER_BUDGET_REVIEW => 'Budget Office completes availability review.',
            ProcurementDocument::STATUS_BUDGET_REVIEWED => 'Forward to Accounting Office.',
            ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW => 'Waiting for Accounting Office verification.',
            ProcurementDocument::STATUS_UNDER_ACCOUNTING_REVIEW => 'Accounting Office completes verification.',
            ProcurementDocument::STATUS_ACCOUNTING_REVIEWED => 'Forward to BAC Secretariat for next processing.',
            ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW => 'Waiting for BAC Chair review.',
            ProcurementDocument::STATUS_UNDER_BAC_CHAIR_REVIEW => 'BAC Chair reviews and confirms or returns.',
            ProcurementDocument::STATUS_READY_FOR_BAC_CHAIR_CONFIRMATION => 'Waiting for BAC Chair confirmation.',
            ProcurementDocument::STATUS_CONFIRMED_BY_BAC_CHAIR => 'Forward to HOPE for final approval if required.',
            ProcurementDocument::STATUS_PENDING_APPROVAL => 'Waiting for HOPE approval.',
            ProcurementDocument::STATUS_UNDER_APPROVAL => 'HOPE completes approval decision.',
            ProcurementDocument::STATUS_APPROVED => 'Proceed to the next approved document step.',
            ProcurementDocument::STATUS_READY_FOR_PO => 'Prepare Purchase Order.',
            ProcurementDocument::STATUS_PO_APPROVED => 'Proceed to Inspection / Acceptance when delivery is complete.',
            ProcurementDocument::STATUS_PO_COMPLETED => 'Procurement chain completed.',
        ];

        if (isset($map[$status])) {
            return $map[$status];
        }

        if (str_contains($status, 'returned')) {
            return 'The assigned office should correct the document and resubmit it through the proper workflow.';
        }

        if ($document instanceof AnnualProcurementPlan) {
            return match ($document->status) {
                AnnualProcurementPlan::STATUS_DRAFT => 'Submit the APP for HOPE approval.',
                AnnualProcurementPlan::STATUS_SUBMITTED => 'Waiting for HOPE approval.',
                AnnualProcurementPlan::STATUS_APPROVED => 'APP is approved and available for reference.',
                AnnualProcurementPlan::STATUS_RETURNED => 'BAC Secretariat revises the APP and resubmits.',
                default => 'Continue with the configured APP workflow.',
            };
        }

        if ($document instanceof BacResolution) {
            return match ($document->status) {
                BacResolution::STATUS_DRAFT => 'Submit the BAC Resolution to BAC Chair.',
                BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR => 'Waiting for BAC Chair confirmation.',
                BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR => 'Return confirmed resolution to the requesting office.',
                BacResolution::STATUS_FORWARDED_TO_HOPE => 'Waiting for HOPE approval.',
                BacResolution::STATUS_APPROVED_BY_HOPE => 'Proceed to the next SVP document step.',
                default => 'Continue with the BAC Resolution workflow.',
            };
        }

        if ($document instanceof Rfq) {
            return $document->status === Rfq::STATUS_DRAFT
                ? 'Submit or issue the RFQ when ready.'
                : 'Proceed to Abstract preparation when quotations are ready.';
        }

        if ($document instanceof AbstractQuotation) {
            return $document->status === AbstractQuotation::STATUS_READY_FOR_PO
                ? 'Prepare the Purchase Order.'
                : 'Submit or complete the Abstract before Purchase Order preparation.';
        }

        if ($document instanceof PurchaseOrder) {
            return in_array($document->status, [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_ISSUED], true)
                ? 'Proceed to delivery monitoring and Inspection / Acceptance.'
                : 'Complete the Purchase Order approval and issuance steps.';
        }

        if ($document instanceof InspectionAcceptanceRecord) {
            return $document->status === InspectionAcceptanceRecord::STATUS_COMPLETED
                ? 'Inspection / Acceptance is completed.'
                : 'Complete inspection and acceptance recording.';
        }

        return $stage ? "Continue the {$stage} workflow." : 'Continue the configured procurement workflow.';
    }

    private function documentTypeHint(string $query, ?string $trackingNumber): ?string
    {
        $value = Str::lower($query . ' ' . ($trackingNumber ?? ''));

        return match (true) {
            str_contains($value, 'bac-res') || str_contains($value, 'resolution') => 'bac_resolution',
            str_contains($value, 'supplemental app') || str_contains($value, 'saip') => 'supplemental_app',
            str_contains($value, 'purchase order') || preg_match('/\bpo\b/i', $value) => 'po',
            str_contains($value, 'purchase request') || preg_match('/\bpr\b/i', $value) => 'pr',
            str_contains($value, 'ppmp') => 'ppmp',
            str_contains($value, 'annual procurement plan') || preg_match('/\bapp\b/i', $value) => 'app',
            str_contains($value, 'rfq') || str_contains($value, 'quotation') => 'rfq',
            str_contains($value, 'abstract') => 'abstract',
            str_contains($value, 'inspection') || str_contains($value, 'acceptance') => 'inspection',
            default => null,
        };
    }

    private function requestNumberMessage(?string $documentTypeHint): string
    {
        return match ($documentTypeHint) {
            'pr', 'purchase_request' => "I can help track your Purchase Request. Please provide your PR number.\n\nExample:\nPR-2026-0005",
            'po', 'purchase_order' => "I can help track your Purchase Order. Please provide your PO number.\n\nExample:\nPO-2026-0005",
            'bac_resolution', 'resolution' => "I can help track your BAC Resolution. Please provide the resolution number.\n\nExample:\nBAC-RES-2026-0005",
            'ppmp' => "I can help track your PPMP. Please provide the PPMP number.\n\nExample:\nPPMP-2026-0005",
            'app', 'annual_procurement_plan' => "I can help track your APP. Please provide the APP number.\n\nExample:\nAPP-2026-0005",
            'rfq' => "I can help track your RFQ. Please provide the RFQ number.\n\nExample:\nRFQ-2026-0005",
            'abstract', 'abstract_quotation' => "I can help track your Abstract. Please provide the Abstract number.\n\nExample:\nABS-2026-0005",
            default => "I can help track your procurement document. Please provide the document number.\n\nExamples:\nPR-2026-0005\nPO-2026-0005\nBAC-RES-2026-0005",
        };
    }

    private function extractTrackingNumber(string $query): ?string
    {
        $patterns = [
            '/\bBAC[-\s]?RES[-\s]?\d{4}[-\s]?[A-Z0-9-]*\d+\b/i',
            '/\b(PPMP|APP|SAIP|SUPP[-\s]?APP|PR|PO|RFQ|ABSTRACT|ABS|IAR)[-\s]?\d{4}[-\s]?[A-Z0-9-]*\d+\b/i',
            '/\b(BAC[-\s]?RES|PPMP|APP|SAIP|SUPP[-\s]?APP|PR|PO|RFQ|ABSTRACT|ABS|IAR)(?:[-\s]?[A-Z0-9]+){1,5}\b/i',
            '/\b[A-Z]{2,8}[-\s]?\d{4}[-\s]?[A-Z0-9-]*\d+\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $query, $matches)) {
                if (preg_match('/\d/', $matches[0])) {
                    return $this->normalizeLookupValue($matches[0]);
                }
            }
        }

        return null;
    }

    private function normalizeLookupValue(string $value): string
    {
        return Str::of($value)
            ->upper()
            ->replaceMatches('/\s+/', '-')
            ->replaceMatches('/-+/', '-')
            ->trim('-')
            ->toString();
    }

    private function displayNumber(Model $document): string
    {
        return $this->resolver->displayTrackingNumber($document)
            ?: $this->sourcePrDocument($document)?->tracking_number
            ?: class_basename($document) . ' #' . $document->getKey();
    }

    private function documentTypeLabel(Model $document): string
    {
        return match (true) {
            $document instanceof ProcurementDocument && $document->document_type === 'PPMP' => 'Project Procurement Management Plan',
            $document instanceof ProcurementDocument => 'Purchase Request',
            $document instanceof AnnualProcurementPlan => 'Annual Procurement Plan',
            $document instanceof SupplementalApp => 'Supplemental APP',
            $document instanceof BacResolution => 'BAC Resolution',
            $document instanceof Rfq => 'Request for Quotation',
            $document instanceof AbstractQuotation => 'Abstract of Quotations',
            $document instanceof PurchaseOrder => 'Purchase Order',
            $document instanceof InspectionAcceptanceRecord => 'Inspection / Acceptance',
            default => Str::headline(class_basename($document)),
        };
    }

    private function documentTitle(Model $document): string
    {
        foreach (['title', 'project_title', 'project_name', 'purpose', 'description', 'supplier_name'] as $attribute) {
            if (filled($document->{$attribute} ?? null)) {
                return Str::limit((string) $document->{$attribute}, 160);
            }
        }

        return $this->documentTypeLabel($document);
    }

    private function statusLabel(?string $status): string
    {
        if (! filled($status)) {
            return 'Not recorded';
        }

        return Str::of($status)
            ->replace('_', ' ')
            ->replace('-', ' ')
            ->headline()
            ->replace('Pr ', 'PR ')
            ->replace('Ppmp', 'PPMP')
            ->replace('App', 'APP')
            ->replace('Bac', 'BAC')
            ->replace('Rfq', 'RFQ')
            ->toString();
    }

    private function sentenceLabel(?string $action, ?string $fallbackStatus = null): string
    {
        $label = filled($action) ? (string) $action : $this->statusLabel($fallbackStatus);

        return Str::of($label)
            ->replace('_', ' ')
            ->replace('-', ' ')
            ->squish()
            ->headline()
            ->replace('Pr ', 'PR ')
            ->replace('Ppmp', 'PPMP')
            ->replace('App', 'APP')
            ->replace('Bac', 'BAC')
            ->replace('Rfq', 'RFQ')
            ->toString();
    }

    private function historyLabel(?string $action, ?string $statusTo): string
    {
        if (filled($action)) {
            return $action;
        }

        return $statusTo ? $this->statusLabel($statusTo) : 'Workflow Updated';
    }

    private function holderFromStage(?string $stage): ?string
    {
        if (! $stage) {
            return null;
        }

        $value = Str::lower($stage);

        return match (true) {
            str_contains($value, 'bac secretariat') => 'BAC Secretariat',
            str_contains($value, 'bac chair') => 'BAC Chair',
            str_contains($value, 'bac member') => 'BAC Member',
            str_contains($value, 'budget') => 'Budget Office',
            str_contains($value, 'accounting') => 'Accounting Office',
            str_contains($value, 'approval') || str_contains($value, 'hope') => 'Head of the Procuring Entity',
            str_contains($value, 'purchase order') || str_contains($value, 'rfq') || str_contains($value, 'abstract') || str_contains($value, 'inspection') => 'End User / Head Office',
            default => null,
        };
    }

    private function holderFromStatus(?string $status): ?string
    {
        if (! $status) {
            return null;
        }

        $value = Str::lower($status);

        return match (true) {
            str_contains($value, 'pr_number') => 'PR Numbering Staff',
            str_contains($value, 'bac_secretariat') => 'BAC Secretariat',
            str_contains($value, 'bac_chair') => 'BAC Chair',
            str_contains($value, 'bac_member') => 'BAC Member',
            str_contains($value, 'budget') => 'Budget Office',
            str_contains($value, 'accounting') => 'Accounting Office',
            str_contains($value, 'approval') || str_contains($value, 'hope') => 'Head of the Procuring Entity',
            str_contains($value, 'returned') => 'Requesting Office',
            default => null,
        };
    }

    private function missingResponse(User $user, ?string $trackingNumber, ?string $documentTypeHint): array
    {
        $this->audit($user, 'document_tracking_denied', 'No matching authorized document was found for tracking.', null, [
            'document_type_hint' => $documentTypeHint,
            'tracking_number' => $trackingNumber,
        ], 'notice');

        return [
            'intent' => 'tracking',
            'found' => false,
            'authorized' => false,
            'message' => 'I cannot find an authorized document matching that number.',
            'document' => null,
            'summary' => 'No matching authorized document found.',
        ];
    }

    private function audit(User $user, string $action, string $description, ?Model $document = null, array $metadata = [], string $severity = 'info'): void
    {
        AuditLogger::log('Smart Procurement Chatbot', $action, $description, $document, null, null, $severity, array_merge([
            'user_id' => $user->id,
            'timestamp' => now()->toDateTimeString(),
        ], $metadata));
    }
}
