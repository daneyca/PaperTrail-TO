<?php

namespace App\Services;

use App\Models\AbstractQuotation;
use App\Models\BacResolution;
use App\Models\DocumentRoutingHistory;
use App\Models\InspectionAcceptanceRecord;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\SvpChainEvent;
use App\Models\SvpProcurementChain;
use App\Models\SvpPostingRecord;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class SvpChainService
{
    private const POSTING_PROCESSOR_USER_ID = 'BACSEC-004';
    public const POSTING_MIN_AMOUNT = 50000.0;
    public const POSTING_MAX_AMOUNT = 200000.0;

    public function findOrCreateFromPr(
        ProcurementDocument $pr,
        ?User $user = null,
        ?string $action = null,
        ?string $remarks = null,
    ): SvpProcurementChain {
        $pr->loadMissing('submittingOffice');
        $office = $pr->submittingOffice;

        $chain = SvpProcurementChain::firstOrCreate(
            ['source_pr_document_id' => $pr->id],
            [
                'chain_number' => $this->generateChainNumber($pr),
                'tracking_number' => $pr->tracking_number,
                'office_id' => $office?->id,
                'office_name' => $office?->name ?? $pr->department_name,
                'current_stage' => SvpProcurementChain::STAGE_PURCHASE_REQUEST,
                'current_status' => $pr->status,
                'procurement_mode' => 'SVP',
                'total_amount' => $pr->total_amount,
                'created_by_user_id' => $user?->id ?? $pr->prepared_by_user_id ?? $pr->submitted_by_user_id,
                'updated_by_user_id' => $user?->id,
            ],
        );

        $chain->fill([
            'tracking_number' => $pr->tracking_number ?? $chain->tracking_number,
            'office_id' => $office?->id ?? $chain->office_id,
            'office_name' => $office?->name ?? $pr->department_name ?? $chain->office_name,
            'current_stage' => SvpProcurementChain::STAGE_PURCHASE_REQUEST,
            'current_status' => $pr->status,
            'total_amount' => $pr->total_amount ?? $chain->total_amount,
            'updated_by_user_id' => $user?->id ?? $chain->updated_by_user_id,
        ]);

        if (! $chain->chain_number) {
            $chain->chain_number = $this->generateChainNumber($pr);
        }

        $chain->save();

        if ($action) {
            $this->addEvent($chain, [
                'document_type' => 'Purchase Request',
                'document_id' => $pr->id,
                'action' => $action,
                'stage' => SvpProcurementChain::STAGE_PURCHASE_REQUEST,
                'status' => $pr->status,
                'performed_by_user_id' => $user?->id,
                'from_office_id' => $pr->submitting_office_id,
                'to_office_id' => $pr->current_office_id,
                'remarks' => $remarks,
            ]);
        }

        return $chain->refresh();
    }

    public function findBySource(mixed $source): ?SvpProcurementChain
    {
        if (! $source instanceof Model) {
            return null;
        }

        return match (true) {
            $source instanceof ProcurementDocument => SvpProcurementChain::where('source_pr_document_id', $source->id)->first(),
            $source instanceof BacResolution => $this->chainByReferences([
                'bac_resolution_id' => $source->id,
                'source_pr_document_id' => $source->source_pr_document_id,
            ]),
            $source instanceof Rfq => $this->chainByReferences([
                'rfq_id' => $source->id,
                'source_pr_document_id' => $source->source_pr_document_id,
                'bac_resolution_id' => $source->source_bac_resolution_id,
            ]),
            $source instanceof AbstractQuotation => $this->chainByReferences([
                'abstract_id' => $source->id,
                'source_pr_document_id' => $source->source_pr_document_id,
                'rfq_id' => $source->source_rfq_id,
                'bac_resolution_id' => $source->source_bac_resolution_id,
            ]),
            $source instanceof PurchaseOrder => $this->chainByReferences([
                'purchase_order_id' => $source->id,
                'source_pr_document_id' => $source->source_pr_document_id,
                'abstract_id' => $source->source_abstract_id,
                'bac_resolution_id' => $source->source_bac_resolution_id,
            ]),
            $source instanceof InspectionAcceptanceRecord => $this->chainByReferences([
                'inspection_id' => $source->id,
                'purchase_order_id' => $source->purchase_order_id,
                'source_pr_document_id' => $source->source_pr_document_id,
            ]),
            default => null,
        };
    }

    public function linkBacResolution(BacResolution $resolution, ?User $user = null, ?string $action = null, ?string $remarks = null): ?SvpProcurementChain
    {
        $sourcePr = $resolution->sourcePrDocument ?: ProcurementDocument::find($resolution->source_pr_document_id);

        if (! $sourcePr) {
            return null;
        }

        $chain = $this->findOrCreateFromPr($sourcePr, $user);
        $payload = [
            'bac_resolution_id' => $resolution->id,
            'total_amount' => $resolution->total_amount ?? $resolution->abc_amount ?? $chain->total_amount,
            'updated_by_user_id' => $user?->id,
        ];

        if (! $this->shouldPreserveActiveStage($chain)) {
            $payload['current_stage'] = SvpProcurementChain::STAGE_BAC_RESOLUTION;
            $payload['current_status'] = $resolution->status;
        }

        $chain->update($payload);

        $this->addEvent($chain, [
            'document_type' => 'BAC Resolution',
            'document_id' => $resolution->id,
            'action' => $action ?? 'BAC Resolution linked to SVP chain',
            'stage' => SvpProcurementChain::STAGE_BAC_RESOLUTION,
            'status' => $resolution->status,
            'performed_by_user_id' => $user?->id,
            'from_office_id' => $sourcePr->current_office_id,
            'to_office_id' => $sourcePr->submitting_office_id,
            'remarks' => $remarks,
        ]);

        return $chain->refresh();
    }

    public function linkRfq(Rfq $rfq, ?User $user = null, ?string $action = null, ?string $remarks = null): ?SvpProcurementChain
    {
        $sourcePr = $this->sourcePrFrom($rfq);

        if (! $sourcePr) {
            return null;
        }

        $chain = $this->findOrCreateFromPr($sourcePr, $user);
        $chain->update([
            'rfq_id' => $rfq->id,
            'bac_resolution_id' => $rfq->source_bac_resolution_id ?: $chain->bac_resolution_id,
            'current_stage' => SvpProcurementChain::STAGE_RFQ,
            'current_status' => $rfq->status,
            'total_amount' => $rfq->abc_amount ?? $chain->total_amount,
            'updated_by_user_id' => $user?->id,
        ]);

        $this->addEvent($chain, [
            'document_type' => 'RFQ',
            'document_id' => $rfq->id,
            'action' => $action ?? 'RFQ linked to SVP chain',
            'stage' => SvpProcurementChain::STAGE_RFQ,
            'status' => $rfq->status,
            'performed_by_user_id' => $user?->id,
            'remarks' => $remarks,
        ]);

        return $chain->refresh();
    }

    public function linkAbstract(AbstractQuotation $abstract, ?User $user = null, ?string $action = null, ?string $remarks = null): ?SvpProcurementChain
    {
        $sourcePr = $this->sourcePrFrom($abstract);

        if (! $sourcePr) {
            return null;
        }

        $chain = $this->findOrCreateFromPr($sourcePr, $user);
        $chain->update([
            'abstract_id' => $abstract->id,
            'rfq_id' => $abstract->source_rfq_id ?: $chain->rfq_id,
            'bac_resolution_id' => $abstract->source_bac_resolution_id ?: $chain->bac_resolution_id,
            'current_stage' => SvpProcurementChain::STAGE_ABSTRACT,
            'current_status' => $abstract->status,
            'total_amount' => $abstract->lowest_total_amount ?? $abstract->abc_amount ?? $chain->total_amount,
            'updated_by_user_id' => $user?->id,
        ]);

        $this->addEvent($chain, [
            'document_type' => 'Abstract',
            'document_id' => $abstract->id,
            'action' => $action ?? 'Abstract linked to SVP chain',
            'stage' => SvpProcurementChain::STAGE_ABSTRACT,
            'status' => $abstract->status,
            'performed_by_user_id' => $user?->id,
            'remarks' => $remarks,
        ]);

        return $chain->refresh();
    }

    public function linkPurchaseOrder(PurchaseOrder $purchaseOrder, ?User $user = null, ?string $action = null, ?string $remarks = null): ?SvpProcurementChain
    {
        $sourcePr = $this->sourcePrFrom($purchaseOrder);

        if (! $sourcePr) {
            return null;
        }

        $chain = $this->findOrCreateFromPr($sourcePr, $user);
        $chain->update([
            'purchase_order_id' => $purchaseOrder->id,
            'abstract_id' => $purchaseOrder->source_abstract_id ?: $chain->abstract_id,
            'bac_resolution_id' => $purchaseOrder->source_bac_resolution_id ?: $chain->bac_resolution_id,
            'current_stage' => SvpProcurementChain::STAGE_PURCHASE_ORDER,
            'current_status' => $purchaseOrder->status,
            'total_amount' => $purchaseOrder->total_amount ?? $chain->total_amount,
            'updated_by_user_id' => $user?->id,
        ]);

        $this->addEvent($chain, [
            'document_type' => 'Purchase Order',
            'document_id' => $purchaseOrder->id,
            'action' => $action ?? 'Purchase Order linked to SVP chain',
            'stage' => SvpProcurementChain::STAGE_PURCHASE_ORDER,
            'status' => $purchaseOrder->status,
            'performed_by_user_id' => $user?->id,
            'remarks' => $remarks,
        ]);

        return $chain->refresh();
    }

    public function linkInspection(InspectionAcceptanceRecord $inspection, ?User $user = null, ?string $action = null, ?string $remarks = null): ?SvpProcurementChain
    {
        $inspection->loadMissing('purchaseOrder');
        $sourcePr = $this->sourcePrFrom($inspection);

        if (! $sourcePr) {
            return null;
        }

        $isCompleted = $inspection->status === InspectionAcceptanceRecord::STATUS_COMPLETED;
        $chain = $this->findOrCreateFromPr($sourcePr, $user);
        $chain->update([
            'inspection_id' => $inspection->id,
            'purchase_order_id' => $inspection->purchase_order_id ?: $chain->purchase_order_id,
            'current_stage' => $isCompleted ? SvpProcurementChain::STAGE_COMPLETED : SvpProcurementChain::STAGE_INSPECTION,
            'current_status' => $isCompleted ? 'completed' : $inspection->status,
            'updated_by_user_id' => $user?->id,
            'completed_at' => $isCompleted ? now() : $chain->completed_at,
        ]);

        $this->addEvent($chain, [
            'document_type' => 'Inspection / Acceptance',
            'document_id' => $inspection->id,
            'action' => $action ?? ($isCompleted ? 'SVP chain completed' : 'Inspection / Acceptance linked to SVP chain'),
            'stage' => $isCompleted ? SvpProcurementChain::STAGE_COMPLETED : SvpProcurementChain::STAGE_INSPECTION,
            'status' => $isCompleted ? 'completed' : $inspection->status,
            'performed_by_user_id' => $user?->id,
            'remarks' => $remarks,
        ]);

        return $chain->refresh();
    }

    public function addEvent(SvpProcurementChain $chain, array $data): SvpChainEvent
    {
        $signature = [
            'document_type' => $data['document_type'] ?? null,
            'document_id' => $data['document_id'] ?? null,
            'action' => $data['action'] ?? null,
            'stage' => $data['stage'] ?? $chain->current_stage,
            'status' => $data['status'] ?? $chain->current_status,
        ];

        if ($signature['document_type'] && $signature['document_id'] && $signature['action']) {
            $existing = $chain->events()
                ->where($signature)
                ->latest()
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $event = $chain->events()->create([
            'document_type' => $signature['document_type'],
            'document_id' => $signature['document_id'],
            'action' => $signature['action'],
            'stage' => $signature['stage'],
            'status' => $signature['status'],
            'from_role' => $data['from_role'] ?? null,
            'to_role' => $data['to_role'] ?? null,
            'from_office_id' => $data['from_office_id'] ?? null,
            'to_office_id' => $data['to_office_id'] ?? null,
            'performed_by_user_id' => $data['performed_by_user_id'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ]);

        AuditLogger::log(
            'SVP Tracking',
            'SVP Chain Event Added',
            $event->action ?: 'SVP chain event recorded.',
            $chain,
            null,
            [
                'stage' => $event->stage,
                'status' => $event->status,
                'document_type' => $event->document_type,
                'document_id' => $event->document_id,
            ],
        );

        return $event;
    }

    public function requiresPosting(mixed $amount): bool
    {
        $amount = (float) ($amount ?? 0);

        return $amount > self::POSTING_MIN_AMOUNT && $amount < self::POSTING_MAX_AMOUNT;
    }

    public function routePrForRfqPreparation(ProcurementDocument $sourceDocument, User $actor, ?string $remarks = null): array
    {
        return DB::transaction(function () use ($sourceDocument, $actor, $remarks) {
            $sourceDocument->loadMissing(['submittingOffice', 'submittedBy', 'preparedBy']);

            $chain = $this->findOrCreateFromPr($sourceDocument, $actor);
            $chain->loadMissing(['latestPostingRecord', 'events', 'rfq']);
            $amount = $this->workflowAmount($chain, $chain->bacResolution, $sourceDocument);

            if ($this->requiresPosting($amount)) {
                if ($chain->current_stage === SvpProcurementChain::STAGE_POSTING
                    && $chain->current_status === ProcurementDocument::STATUS_SVP_POSTING_REQUIRED) {
                    return [
                        'ok' => true,
                        'message' => 'Posting has already been assigned to BACSEC-004. BAC Resolution may continue in parallel.',
                    ];
                }

                if ($this->hasPostingCompleted($chain, $sourceDocument) || $this->hasRfqOrLaterStarted($chain)) {
                    return [
                        'ok' => true,
                        'message' => 'This PR has already cleared the posting path and may proceed to RFQ.',
                    ];
                }

                return $this->routePrToPosting(
                    $chain,
                    $sourceDocument,
                    $actor,
                    $amount,
                    'PR Ready - Posting Required',
                    $remarks,
                );
            }

            $chain->update([
                'current_stage' => SvpProcurementChain::STAGE_RFQ,
                'current_status' => ProcurementDocument::STATUS_READY_FOR_RFQ,
                'total_amount' => $amount ?: $chain->total_amount,
                'updated_by_user_id' => $actor->id,
            ]);

            $this->addEvent($chain->refresh(), [
                'document_type' => 'Purchase Request',
                'document_id' => $sourceDocument->id,
                'action' => 'PR ready for RFQ while BAC Resolution is pending',
                'stage' => SvpProcurementChain::STAGE_RFQ,
                'status' => ProcurementDocument::STATUS_READY_FOR_RFQ,
                'performed_by_user_id' => $actor->id,
                'from_office_id' => $sourceDocument->current_office_id,
                'to_office_id' => $sourceDocument->submitting_office_id,
                'remarks' => $remarks ?: 'Posting is not required for this amount path. RFQ may proceed while BAC Resolution remains tracked.',
            ]);

            foreach ([$sourceDocument->submittedBy, $sourceDocument->preparedBy] as $recipient) {
                if (! $recipient) {
                    continue;
                }

                SystemNotificationService::notify(
                    $recipient,
                    'PR Ready for RFQ',
                    'This PR may proceed to RFQ while the BAC Resolution remains tracked in the workflow.',
                    SystemNotification::TYPE_SUCCESS,
                    'SVP RFQ',
                    $sourceDocument,
                    $this->notificationUrl('head-office.rfqs.create', ['source_pr_document_id' => $sourceDocument->id]),
                );
            }

            AuditLogger::log('SVP Routing', 'PR Ready for RFQ', 'PR was made available for RFQ while BAC Resolution remains pending.', $chain);

            return [
                'ok' => true,
                'message' => 'This PR may proceed to RFQ while BAC Resolution remains tracked.',
            ];
        });
    }

    public function bacResolutionNextStep(BacResolution $resolution): array
    {
        $resolution->loadMissing('sourcePrDocument');

        $sourceDocument = $resolution->sourcePrDocument;
        $chain = $sourceDocument
            ? SvpProcurementChain::query()->where('source_pr_document_id', $sourceDocument->id)->first()
            : null;
        $amount = $this->workflowAmount($chain, $resolution, $sourceDocument);

        if ($sourceDocument?->status === ProcurementDocument::STATUS_SVP_POSTING_REQUIRED
            || $chain?->current_stage === SvpProcurementChain::STAGE_POSTING) {
            return [
                'key' => 'posting_required',
                'label' => 'For BACSEC-004 Posting',
                'description' => 'BACSEC-004 must complete the PhilGEPS posting/publication step before RFQ preparation.',
                'button_label' => null,
                'confirm' => null,
                'tone' => 'warning',
            ];
        }

        if ($sourceDocument?->status === ProcurementDocument::STATUS_READY_FOR_RFQ
            || $chain?->current_stage === SvpProcurementChain::STAGE_RFQ) {
            return [
                'key' => 'ready_for_rfq',
                'label' => 'Ready for RFQ',
                'description' => 'The amount path is complete and the requesting office may prepare the RFQ.',
                'button_label' => null,
                'confirm' => null,
                'tone' => 'success',
            ];
        }

        if ($this->requiresPosting($amount)) {
            return [
                'key' => 'send_to_posting',
                'label' => 'Send to BACSEC-004 Posting',
                'description' => 'Amount is above PHP 50,000 and below PHP 200,000, so posting/publication is required before RFQ.',
                'button_label' => 'Acknowledge / Send to BACSEC-004 Posting',
                'confirm' => 'Acknowledge this BAC Resolution and route the PR to BACSEC-004 for PhilGEPS posting before RFQ?',
                'tone' => 'warning',
            ];
        }

        return [
            'key' => 'ready_for_rfq_after_acknowledgment',
            'label' => 'Ready for RFQ after acknowledgment',
            'description' => 'Amount is at or below PHP 50,000, so posting is skipped and RFQ may proceed after acknowledgment.',
            'button_label' => 'Acknowledge / Ready for RFQ',
            'confirm' => 'Acknowledge this BAC Resolution and continue to RFQ?',
            'tone' => 'success',
        ];
    }

    public function routeAfterBacResolutionCompleted(BacResolution $resolution, User $actor, ?string $remarks = null): array
    {
        return DB::transaction(function () use ($resolution, $actor, $remarks) {
            $resolution->loadMissing(['sourcePrDocument.submittingOffice', 'sourcePrDocument.submittedBy', 'sourcePrDocument.preparedBy', 'preparedBy']);
            $sourceDocument = $resolution->sourcePrDocument;

            if (! $sourceDocument) {
                return [
                    'ok' => false,
                    'message' => 'This BAC Resolution has no linked Purchase Request.',
                ];
            }

            $chain = SvpProcurementChain::query()
                ->where('source_pr_document_id', $sourceDocument->id)
                ->first();

            $amount = $this->workflowAmount($chain, $resolution, $sourceDocument);

            $chain = $chain ?: $this->findOrCreateFromPr($sourceDocument, $actor);
            $chain->loadMissing(['latestPostingRecord', 'events', 'rfq']);

            if ($chain->current_stage === SvpProcurementChain::STAGE_POSTING
                && $chain->current_status === ProcurementDocument::STATUS_SVP_POSTING_REQUIRED) {
                $chain->update([
                    'bac_resolution_id' => $resolution->id,
                    'updated_by_user_id' => $actor->id,
                ]);

                return [
                    'ok' => true,
                    'message' => 'Posting has already been assigned to BACSEC-004.',
                ];
            }

            if ($this->hasRfqOrLaterStarted($chain)
                || $this->hasPostingCompleted($chain, $sourceDocument)
                || $sourceDocument->status === ProcurementDocument::STATUS_READY_FOR_RFQ) {
                $this->markSourceReadyForRfqAfterResolutionLink($sourceDocument, $resolution, $actor, $remarks);

                $chain->update([
                    'bac_resolution_id' => $resolution->id,
                    'total_amount' => $amount ?: $chain->total_amount,
                    'updated_by_user_id' => $actor->id,
                ]);

                $this->addEvent($chain->refresh(), [
                    'document_type' => 'BAC Resolution',
                    'document_id' => $resolution->id,
                    'action' => 'BAC Resolution acknowledged after RFQ path started',
                    'stage' => $chain->current_stage,
                    'status' => $chain->current_status,
                    'performed_by_user_id' => $actor->id,
                    'remarks' => $remarks ?: 'BAC Resolution linked without restarting posting or RFQ routing.',
                ]);

                return [
                    'ok' => true,
                    'message' => 'BAC Resolution linked. The existing RFQ/posting path remains active.',
                ];
            }

            return $this->requiresPosting($amount)
                ? $this->routeToPosting($chain, $resolution, $sourceDocument, $actor, $amount, 'BAC Resolution Completed - Posting Required', $remarks)
                : $this->routeToRfq($chain, $resolution, $sourceDocument, $actor, $amount, 'BAC Resolution Completed - Ready for RFQ', $remarks);
        });
    }

    public function routeAfterBacResolutionAcknowledged(BacResolution $resolution, User $actor): array
    {
        return $this->routeAfterBacResolutionCompleted(
            $resolution,
            $actor,
            'Requesting office acknowledged the BAC Resolution.'
        );
    }

    public function canCompletePosting(SvpProcurementChain $chain, ?User $user): bool
    {
        if (! $user || $user->user_id !== self::POSTING_PROCESSOR_USER_ID) {
            return false;
        }

        $chain->loadMissing('sourcePrDocument');
        $amount = (float) ($chain->total_amount ?? $chain->sourcePrDocument?->total_amount ?? 0);

        return $this->requiresPosting($amount)
            && $chain->current_stage === SvpProcurementChain::STAGE_POSTING
            && $chain->current_status === ProcurementDocument::STATUS_SVP_POSTING_REQUIRED;
    }

    public function completePosting(SvpProcurementChain $chain, User $actor, ?string $remarks = null, ?SvpPostingRecord $postingRecord = null): array
    {
        return DB::transaction(function () use ($chain, $actor, $remarks, $postingRecord) {
            $chain->loadMissing(['sourcePrDocument.submittingOffice', 'sourcePrDocument.submittedBy', 'sourcePrDocument.preparedBy', 'bacResolution.preparedBy', 'latestPostingRecord']);

            if (! $this->canCompletePosting($chain, $actor)) {
                return [
                    'ok' => false,
                    'message' => 'Only BACSEC-004 can complete an active SVP posting task.',
                ];
            }

            $sourceDocument = $chain->sourcePrDocument;
            $postingRecord = $postingRecord ?: $chain->latestPostingRecord;

            if (! $sourceDocument) {
                return [
                    'ok' => false,
                    'message' => 'This SVP chain has no linked Purchase Request.',
                ];
            }

            $oldStatus = $sourceDocument->status;
            $fromOfficeId = $sourceDocument->current_office_id;
            $toOfficeId = $sourceDocument->submitting_office_id;
            $assignedUserId = $sourceDocument->submitted_by_user_id ?: $sourceDocument->prepared_by_user_id;

            $sourceDocument->update([
                'status' => ProcurementDocument::STATUS_READY_FOR_RFQ,
                'stage' => ProcurementDocument::STAGE_READY_FOR_RFQ,
                'current_office_id' => $toOfficeId,
                'assigned_to_user_id' => $assignedUserId,
                'route_destination_role' => User::ROLE_HEAD_OFFICE,
                'route_destination_office_id' => $toOfficeId,
                'route_remarks' => 'SVP posting completed by BACSEC-004. Requesting office may proceed with RFQ.',
            ]);

            DocumentRoutingHistory::create([
                'procurement_document_id' => $sourceDocument->id,
                'action_by_user_id' => $actor->id,
                'from_office_id' => $fromOfficeId,
                'to_office_id' => $toOfficeId,
                'action' => 'SVP Posting Completed',
                'status_from' => $oldStatus,
                'status_to' => ProcurementDocument::STATUS_READY_FOR_RFQ,
                'comments' => $remarks ?: 'BACSEC-004 completed the SVP posting step. PR is ready for RFQ.',
                'action_at' => now(),
            ]);

            $chain->update([
                'current_stage' => SvpProcurementChain::STAGE_RFQ,
                'current_status' => ProcurementDocument::STATUS_READY_FOR_RFQ,
                'updated_by_user_id' => $actor->id,
            ]);

            if ($postingRecord && ! in_array($postingRecord->status, [SvpPostingRecord::STATUS_POSTED, SvpPostingRecord::STATUS_CLOSED], true)) {
                $postingRecord->update([
                    'status' => SvpPostingRecord::STATUS_POSTED,
                    'completed_by_user_id' => $actor->id,
                    'completed_at' => now(),
                    'remarks' => $remarks ?: $postingRecord->remarks,
                ]);
            }

            $this->addEvent($chain, [
                'document_type' => 'Purchase Request',
                'document_id' => $sourceDocument->id,
                'action' => 'Posting completed - RFQ may proceed',
                'stage' => SvpProcurementChain::STAGE_POSTING_COMPLETED,
                'status' => ProcurementDocument::STATUS_READY_FOR_RFQ,
                'performed_by_user_id' => $actor->id,
                'from_office_id' => $fromOfficeId,
                'to_office_id' => $toOfficeId,
                'remarks' => $remarks,
            ]);

            $prNumber = $sourceDocument->pr_no ?? $sourceDocument->tracking_number;

            foreach ([$sourceDocument->submittedBy, $sourceDocument->preparedBy, $chain->bacResolution?->preparedBy] as $recipient) {
                if (! $recipient) {
                    continue;
                }

                SystemNotificationService::notify(
                    $recipient,
                    'SVP Posting Completed',
                "Posting for {$prNumber} was marked as posted. The PR is ready for RFQ.",
                    SystemNotification::TYPE_SUCCESS,
                    'SVP Posting',
                    $sourceDocument,
                    $this->notificationUrl('head-office.rfqs.create', ['source_pr_document_id' => $sourceDocument->id])
                        ?: $this->notificationUrl('bac-secretariat.svp-monitoring.show', $chain),
                );
            }

            AuditLogger::log('SVP Posting', 'SVP Posting Completed', 'BACSEC-004 completed the SVP posting step.', $chain);

            return [
                'ok' => true,
                'message' => 'Posting marked complete. This PR is now ready for RFQ.',
            ];
        });
    }

    public function workflowSteps(SvpProcurementChain $chain): array
    {
        $chain->loadMissing([
            'sourcePrDocument',
            'bacResolution',
            'latestPostingRecord',
            'rfq',
            'abstract',
            'purchaseOrder',
            'inspection',
            'events',
        ]);

        $pr = $chain->sourcePrDocument;
        $amount = $this->workflowAmount($chain, $chain->bacResolution, $pr);
        $postingRequired = $this->requiresPosting($amount);
        $hasRfq = (bool) $chain->rfq;
        $postingCompleted = $postingRequired && (
            $hasRfq
            || in_array($chain->latestPostingRecord?->status, [SvpPostingRecord::STATUS_POSTED, SvpPostingRecord::STATUS_CLOSED], true)
            || $pr?->status === ProcurementDocument::STATUS_READY_FOR_RFQ
            || $chain->current_stage === SvpProcurementChain::STAGE_RFQ
            || $chain->current_stage === SvpProcurementChain::STAGE_POSTING_COMPLETED
            || $chain->events->contains(fn (SvpChainEvent $event) => $event->stage === SvpProcurementChain::STAGE_POSTING_COMPLETED)
        );
        $postingStepDescription = match (true) {
            $amount <= 0 => 'Amount pending before posting path can be confirmed.',
            $postingRequired => 'Required for SVP above PHP 50,000 and below PHP 200,000. Assigned to BACSEC-004.',
            $amount <= self::POSTING_MIN_AMOUNT => 'Not required at or below PHP 50,000.',
            default => 'Amount exceeds the configured SVP posting band; review procurement mode before RFQ.',
        };
        $postingCompletedDescription = match (true) {
            $amount <= 0 => 'Waiting for amount path confirmation.',
            $postingRequired => 'Posting period completed before RFQ.',
            $amount <= self::POSTING_MIN_AMOUNT => 'Posting period skipped for this amount path.',
            default => 'Posting path should be reviewed before continuing.',
        };
        $hasAbstract = (bool) $chain->abstract;
        $hasPurchaseOrder = (bool) $chain->purchaseOrder;
        $hasInspection = (bool) $chain->inspection;
        $isCompleted = filled($chain->completed_at)
            || $chain->current_stage === SvpProcurementChain::STAGE_COMPLETED
            || $chain->current_status === 'completed';
        $hasAccountingSubmission = $isCompleted || $chain->events->contains(function (SvpChainEvent $event) {
            $haystack = Str::lower(implode(' ', array_filter([
                $event->action,
                $event->stage,
                $event->status,
                $event->to_role,
                $event->remarks,
            ])));

            return Str::contains($haystack, 'accounting');
        });

        $steps = [
            $this->workflowStep('AIP', 'Budget reference before PPMP and APP.', (bool) $pr),
            $this->workflowStep('PPMP', 'Project procurement plan reference.', (bool) $pr),
            $this->workflowStep('APP', 'Approved annual procurement plan reference.', (bool) $pr),
            $this->workflowStep(SvpProcurementChain::STAGE_PURCHASE_REQUEST, $this->documentStepDescription($pr, 'PR is created and tracked.'), (bool) $pr),
            $this->workflowStep('PR Signatories', 'Requested and approved signatories are recorded.', (bool) ($pr?->submitted_at || $pr?->pr_no || $chain->bacResolution)),
            $this->workflowStep('PR Number Assignment', $pr?->pr_no ? "Official PR number {$pr->pr_no} assigned." : 'Waiting for official PR number.', (bool) ($pr?->pr_no || $pr?->pr_number_status === ProcurementDocument::PR_NUMBER_STATUS_ASSIGNED)),
            $this->workflowStep(SvpProcurementChain::STAGE_BAC_RESOLUTION, $this->documentStepDescription($chain->bacResolution, 'Resolution prepares BAC recommendation.'), (bool) $chain->bacResolution),
            $this->workflowStep(SvpProcurementChain::STAGE_POSTING, $postingStepDescription, $postingCompleted, $postingRequired ? 'pending' : 'skipped'),
            $this->workflowStep(SvpProcurementChain::STAGE_POSTING_COMPLETED, $postingCompletedDescription, $postingCompleted, $postingRequired ? 'pending' : 'skipped'),
            $this->workflowStep(SvpProcurementChain::STAGE_RFQ, $this->documentStepDescription($chain->rfq, 'Request for quotation prepared.'), $hasRfq),
            $this->workflowStep(SvpProcurementChain::STAGE_SUPPLIER_QUOTATIONS, 'Supplier quotations are received and checked.', $hasAbstract || in_array($chain->rfq?->status, [Rfq::STATUS_QUOTED], true)),
            $this->workflowStep(SvpProcurementChain::STAGE_ABSTRACT, $this->documentStepDescription($chain->abstract, 'Abstract of Quotations compares suppliers.'), $hasAbstract),
            $this->workflowStep(SvpProcurementChain::STAGE_PURCHASE_ORDER, $this->documentStepDescription($chain->purchaseOrder, 'Purchase Order is prepared from the abstract.'), $hasPurchaseOrder),
            $this->workflowStep(SvpProcurementChain::STAGE_DELIVERY, 'Supplier delivery is monitored before inspection.', $hasInspection || in_array($chain->purchaseOrder?->status, [PurchaseOrder::STATUS_ISSUED, PurchaseOrder::STATUS_COMPLETED], true)),
            $this->workflowStep(SvpProcurementChain::STAGE_INSPECTION, $this->documentStepDescription($chain->inspection, 'Inspection and acceptance are recorded.'), $hasInspection),
            $this->workflowStep(SvpProcurementChain::STAGE_ACCOUNTING_SUBMISSION, $hasAccountingSubmission ? 'Accounting submission is recorded in the chain history.' : 'Completed procurement is ready for accounting submission.', $hasAccountingSubmission),
            $this->workflowStep('Completed Procurement', 'SVP chain is complete.', $isCompleted),
        ];

        $currentMarked = false;

        return collect($steps)
            ->map(function (array $step) use (&$currentMarked) {
                if ($step['state'] === 'pending' && ! $currentMarked) {
                    $step['state'] = 'current';
                    $currentMarked = true;
                }

                return $step;
            })
            ->all();
    }

    public function documentCards(SvpProcurementChain $chain, string $context): array
    {
        $chain->loadMissing([
            'sourcePrDocument',
            'bacResolution',
            'latestPostingRecord',
            'rfq',
            'abstract',
            'purchaseOrder',
            'inspection',
        ]);

        $cards = [
            $this->card('Purchase Request', $chain->sourcePrDocument, $context, $chain),
            $this->card('BAC Resolution', $chain->bacResolution, $context, $chain),
        ];

        if ($this->requiresPosting($this->workflowAmount($chain, $chain->bacResolution, $chain->sourcePrDocument))) {
            $cards[] = $this->card('Posting', $chain->latestPostingRecord, $context, $chain);
        }

        return [
            ...$cards,
            $this->card('RFQ', $chain->rfq, $context, $chain),
            $this->card('Abstract', $chain->abstract, $context, $chain),
            $this->card('Purchase Order', $chain->purchaseOrder, $context, $chain),
            $this->card('Inspection / Acceptance', $chain->inspection, $context, $chain),
        ];
    }

    private function workflowStep(string $label, string $description, bool $complete, string $defaultState = 'pending'): array
    {
        return [
            'label' => $label,
            'description' => $description,
            'state' => $complete ? 'complete' : $defaultState,
        ];
    }

    private function documentStepDescription(?Model $document, string $fallback): string
    {
        if (! $document) {
            return $fallback;
        }

        $status = $document->status ?? null;

        if (! $status) {
            return 'Document linked to this SVP chain.';
        }

        return str($status)->replace('_', ' ')->title()->toString();
    }

    private function card(string $type, ?Model $document, string $context, SvpProcurementChain $chain): array
    {
        return [
            'type' => $type,
            'document' => $document,
            'number' => $document ? $this->documentNumber($type, $document) : null,
            'status' => $document ? ($document->status ?? 'available') : 'pending',
            'date' => $document?->updated_at ?? $document?->created_at,
            'view_url' => $document ? $this->documentRoute($type, $document, $context, $chain, false) : null,
            'print_url' => $document ? $this->documentRoute($type, $document, $context, $chain, true) : null,
        ];
    }

    private function routePrToPosting(
        SvpProcurementChain $chain,
        ProcurementDocument $sourceDocument,
        User $actor,
        float $amount,
        ?string $historyAction = null,
        ?string $remarks = null,
    ): array
    {
        $postingUser = $this->postingProcessor();

        if (! $postingUser) {
            return [
                'ok' => false,
                'message' => 'BACSEC-004 posting processor account was not found.',
            ];
        }

        $toOfficeId = $postingUser->office_id ?: $this->bacSecretariatOffice()?->id;

        $chain->update([
            'current_stage' => SvpProcurementChain::STAGE_POSTING,
            'current_status' => ProcurementDocument::STATUS_SVP_POSTING_REQUIRED,
            'total_amount' => $amount,
            'updated_by_user_id' => $actor->id,
        ]);

        $this->addEvent($chain->refresh(), [
            'document_type' => 'Purchase Request',
            'document_id' => $sourceDocument->id,
            'action' => $historyAction ?: 'Posting required - assigned to BACSEC-004',
            'stage' => SvpProcurementChain::STAGE_POSTING,
            'status' => ProcurementDocument::STATUS_SVP_POSTING_REQUIRED,
            'from_role' => User::ROLE_HEAD_OFFICE,
            'to_role' => User::ROLE_BAC_SECRETARIAT,
            'from_office_id' => $sourceDocument->current_office_id,
            'to_office_id' => $toOfficeId,
            'performed_by_user_id' => $actor->id,
            'remarks' => $remarks ?: 'Posting is required before RFQ. BAC Resolution remains tracked in parallel.',
        ]);

        $prNumber = $sourceDocument->pr_no ?? $sourceDocument->tracking_number;

        SystemNotificationService::notify(
            $postingUser,
            'SVP Posting Required',
            "PR {$prNumber} requires posting before RFQ. BAC Resolution may continue in parallel.",
            SystemNotification::TYPE_WARNING,
            'SVP Posting',
            $sourceDocument,
            $this->notificationUrl('bac-secretariat.svp-posting.create', $chain)
                ?: $this->notificationUrl('bac-secretariat.svp-monitoring.show', $chain),
        );

        AuditLogger::log('SVP Routing', 'Posting Assigned', 'SVP posting was assigned to BACSEC-004 while BAC Resolution remains pending.', $chain);

        return [
            'ok' => true,
            'message' => 'Posting is required and has been assigned to BACSEC-004. BAC Resolution may continue in parallel.',
        ];
    }

    private function documentRoute(string $type, Model $document, string $context, SvpProcurementChain $chain, bool $print): ?string
    {
        $routes = match ($context) {
            'head-office' => [
                'Purchase Request' => $print ? 'head-office.pr.print' : 'head-office.pr.show',
                'BAC Resolution' => $print ? null : 'head-office.resolutions.show',
                'RFQ' => $print ? 'head-office.rfqs.print' : 'head-office.rfqs.show',
                'Abstract' => $print ? 'head-office.abstracts.print' : 'head-office.abstracts.show',
                'Purchase Order' => $print ? 'head-office.purchase-orders.print' : 'head-office.purchase-orders.show',
                'Inspection / Acceptance' => $print ? null : 'head-office.inspection.show',
            ],
            'bac-secretariat' => [
                'Purchase Request' => $print ? 'bac-secretariat.pr.print' : 'bac-secretariat.pr.show',
                'BAC Resolution' => $print ? 'bac-secretariat.resolutions.print' : 'bac-secretariat.resolutions.show',
                'Posting' => $print ? null : 'bac-secretariat.svp-posting.show',
                'RFQ' => $print ? 'bac-secretariat.rfqs.print' : 'bac-secretariat.rfqs.show',
                'Abstract' => $print ? 'bac-secretariat.abstracts.print' : 'bac-secretariat.abstracts.show',
                'Purchase Order' => $print ? 'bac-secretariat.purchase-orders.print' : 'bac-secretariat.purchase-orders.show',
            ],
            default => [],
        };

        $route = $routes[$type] ?? null;

        if (! $route || ! Route::has($route)) {
            return null;
        }

        $parameter = $type === 'Inspection / Acceptance'
            ? $chain->purchaseOrder
            : $document;

        return $parameter ? route($route, $parameter) : null;
    }

    private function documentNumber(string $type, Model $document): string
    {
        return match ($type) {
            'Purchase Request' => $document->pr_no ?? $document->tracking_number ?? 'Draft PR',
            'BAC Resolution' => method_exists($document, 'displayNumber') ? $document->displayNumber() : ($document->resolution_number ?? 'Draft Resolution'),
            'Posting' => method_exists($document, 'displayNumber') ? $document->displayNumber() : 'Posting Record #'.$document->getKey(),
            'RFQ' => $document->rfq_number ?? 'Draft RFQ',
            'Abstract' => $document->abstract_number ?? 'Draft Abstract',
            'Purchase Order' => $document->po_number ?? 'Draft PO',
            'Inspection / Acceptance' => 'Inspection Record #'.$document->getKey(),
            default => 'Document #'.$document->getKey(),
        };
    }

    private function routeToPosting(
        SvpProcurementChain $chain,
        BacResolution $resolution,
        ProcurementDocument $sourceDocument,
        User $actor,
        float $amount,
        ?string $historyAction = null,
        ?string $remarks = null,
    ): array
    {
        $postingUser = $this->postingProcessor();

        if (! $postingUser) {
            return [
                'ok' => false,
                'message' => 'BACSEC-004 posting processor account was not found.',
            ];
        }

        $oldStatus = $sourceDocument->status;
        $fromOfficeId = $sourceDocument->current_office_id;
        $toOfficeId = $postingUser->office_id ?: $this->bacSecretariatOffice()?->id;

        $sourceDocument->update([
            'status' => ProcurementDocument::STATUS_SVP_POSTING_REQUIRED,
            'stage' => ProcurementDocument::STAGE_SVP_POSTING,
            'current_office_id' => $toOfficeId,
            'assigned_to_user_id' => $postingUser->id,
            'route_destination_role' => User::ROLE_BAC_SECRETARIAT,
            'route_destination_office_id' => $toOfficeId,
            'route_remarks' => 'SVP amount requires posting after BAC Resolution. Assigned to BACSEC-004 before RFQ.',
        ]);

        DocumentRoutingHistory::create([
            'procurement_document_id' => $sourceDocument->id,
            'action_by_user_id' => $actor->id,
            'from_office_id' => $fromOfficeId,
            'to_office_id' => $toOfficeId,
            'action' => $historyAction ?: 'BAC Resolution Completed - Posting Required',
            'status_from' => $oldStatus,
            'status_to' => ProcurementDocument::STATUS_SVP_POSTING_REQUIRED,
            'comments' => $remarks ?: 'SVP amount is PHP '.number_format($amount, 2).'. Posting is required and assigned to BACSEC-004 before RFQ.',
            'action_at' => now(),
        ]);

        $chain->update([
            'bac_resolution_id' => $resolution->id,
            'current_stage' => SvpProcurementChain::STAGE_POSTING,
            'current_status' => ProcurementDocument::STATUS_SVP_POSTING_REQUIRED,
            'total_amount' => $amount,
            'updated_by_user_id' => $actor->id,
        ]);

        $this->addEvent($chain, [
            'document_type' => 'BAC Resolution',
            'document_id' => $resolution->id,
            'action' => 'Posting required - assigned to BACSEC-004',
            'stage' => SvpProcurementChain::STAGE_POSTING,
            'status' => ProcurementDocument::STATUS_SVP_POSTING_REQUIRED,
            'from_role' => User::ROLE_HEAD_OFFICE,
            'to_role' => User::ROLE_BAC_SECRETARIAT,
            'from_office_id' => $fromOfficeId,
            'to_office_id' => $toOfficeId,
            'performed_by_user_id' => $actor->id,
            'remarks' => $remarks ?: 'SVP amount path: PHP '.number_format($amount, 2).' requires posting before RFQ.',
        ]);

        $prNumber = $sourceDocument->pr_no ?? $sourceDocument->tracking_number;

        SystemNotificationService::notify(
            $postingUser,
            'SVP Posting Required',
            "PR {$prNumber} requires posting before RFQ.",
            SystemNotification::TYPE_WARNING,
            'SVP Posting',
            $sourceDocument,
            $this->notificationUrl('bac-secretariat.svp-posting.create', $chain)
                ?: $this->notificationUrl('bac-secretariat.svp-monitoring.show', $chain),
        );

        SystemNotificationService::notify(
            $resolution->preparedBy,
            'BAC Resolution Completed',
            "BAC Resolution {$resolution->displayNumber()} is complete. Posting was assigned to BACSEC-004.",
            SystemNotification::TYPE_INFO,
            'BAC Resolution',
            $resolution,
            $this->notificationUrl('bac-secretariat.svp-monitoring.show', $chain),
        );

        AuditLogger::log('SVP Routing', 'Posting Assigned', 'SVP posting was assigned to BACSEC-004 based on PR amount.', $chain);

        return [
            'ok' => true,
            'message' => 'Posting is required and has been assigned to BACSEC-004.',
        ];
    }

    private function routeToRfq(
        SvpProcurementChain $chain,
        BacResolution $resolution,
        ProcurementDocument $sourceDocument,
        User $actor,
        float $amount,
        ?string $historyAction = null,
        ?string $remarks = null,
    ): array
    {
        $sourceDocument->loadMissing(['submittedBy', 'preparedBy']);
        $oldStatus = $sourceDocument->status;
        $fromOfficeId = $sourceDocument->current_office_id;
        $toOfficeId = $sourceDocument->submitting_office_id ?: $actor->office_id;
        $assignedUserId = $sourceDocument->submitted_by_user_id ?: $sourceDocument->prepared_by_user_id ?: $actor->id;

        $sourceDocument->update([
            'status' => ProcurementDocument::STATUS_READY_FOR_RFQ,
            'stage' => ProcurementDocument::STAGE_READY_FOR_RFQ,
            'current_office_id' => $toOfficeId,
            'assigned_to_user_id' => $assignedUserId,
            'route_destination_role' => User::ROLE_HEAD_OFFICE,
            'route_destination_office_id' => $toOfficeId,
            'route_remarks' => $amount > 0 && $amount <= self::POSTING_MIN_AMOUNT
                ? 'BAC Resolution completed. Posting is skipped at or below PHP 50,000; RFQ may proceed.'
                : 'BAC Resolution completed. SVP RFQ may proceed.',
        ]);

        DocumentRoutingHistory::create([
            'procurement_document_id' => $sourceDocument->id,
            'action_by_user_id' => $actor->id,
            'from_office_id' => $fromOfficeId,
            'to_office_id' => $toOfficeId,
            'action' => $historyAction ?: 'BAC Resolution Completed - Ready for RFQ',
            'status_from' => $oldStatus,
            'status_to' => ProcurementDocument::STATUS_READY_FOR_RFQ,
            'comments' => $remarks ?: ($amount > 0 && $amount <= self::POSTING_MIN_AMOUNT
                ? 'BAC Resolution completed. Posting is skipped at or below PHP 50,000 and the document is ready for RFQ.'
                : 'BAC Resolution completed. Document is ready for RFQ.'),
            'action_at' => now(),
        ]);

        $chain->update([
            'bac_resolution_id' => $resolution->id,
            'current_stage' => SvpProcurementChain::STAGE_RFQ,
            'current_status' => ProcurementDocument::STATUS_READY_FOR_RFQ,
            'total_amount' => $amount ?: $chain->total_amount,
            'updated_by_user_id' => $actor->id,
        ]);

        $this->addEvent($chain, [
            'document_type' => 'BAC Resolution',
            'document_id' => $resolution->id,
            'action' => 'BAC Resolution acknowledged - ready for RFQ',
            'stage' => SvpProcurementChain::STAGE_RFQ,
            'status' => ProcurementDocument::STATUS_READY_FOR_RFQ,
            'from_office_id' => $fromOfficeId,
            'to_office_id' => $toOfficeId,
            'performed_by_user_id' => $actor->id,
            'remarks' => $amount > 0 && $amount <= self::POSTING_MIN_AMOUNT ? 'Posting skipped at or below PHP 50,000.' : null,
        ]);

        SystemNotificationService::notify(
            $resolution->preparedBy,
            'BAC Resolution Completed',
            "BAC Resolution {$resolution->displayNumber()} is complete and ready for RFQ.",
            SystemNotification::TYPE_SUCCESS,
            'BAC Resolution',
            $resolution,
            $this->notificationUrl('bac-secretariat.resolutions.show', $resolution),
        );

        foreach ([$sourceDocument->submittedBy, $sourceDocument->preparedBy] as $recipient) {
            if (! $recipient) {
                continue;
            }

            SystemNotificationService::notify(
                $recipient,
                'PR Ready for RFQ',
                "BAC Resolution {$resolution->displayNumber()} is complete. You may proceed with RFQ preparation.",
                SystemNotification::TYPE_SUCCESS,
                'SVP RFQ',
                $sourceDocument,
                $this->notificationUrl('head-office.rfqs.create', ['source_pr_document_id' => $sourceDocument->id]),
            );
        }

        AuditLogger::log('SVP Routing', 'BAC Resolution Completed', 'BAC Resolution completed and PR was routed to RFQ.', $resolution, ['source_status' => $oldStatus], ['source_status' => ProcurementDocument::STATUS_READY_FOR_RFQ]);

        return [
            'ok' => true,
            'message' => 'This document is now ready for RFQ.',
        ];
    }

    private function postingProcessor(): ?User
    {
        return User::query()
            ->where('user_id', self::POSTING_PROCESSOR_USER_ID)
            ->where('status', User::STATUS_ACTIVE)
            ->first();
    }

    private function workflowAmount(?SvpProcurementChain $chain, ?BacResolution $resolution = null, ?ProcurementDocument $sourceDocument = null): float
    {
        foreach ([
            $chain?->total_amount,
            $resolution?->total_amount,
            $resolution?->abc_amount,
            $sourceDocument?->total_amount,
        ] as $amount) {
            $amount = (float) ($amount ?? 0);

            if ($amount > 0) {
                return $amount;
            }
        }

        return 0.0;
    }

    private function bacSecretariatOffice(): ?Office
    {
        return Office::query()
            ->where(function ($query) {
                $query->where('code', 'BACSEC')
                    ->orWhere('name', 'BAC Secretariat');
            })
            ->first();
    }

    private function shouldPreserveActiveStage(SvpProcurementChain $chain): bool
    {
        return $this->hasRfqOrLaterStarted($chain)
            || in_array($chain->current_stage, [
                SvpProcurementChain::STAGE_POSTING,
                SvpProcurementChain::STAGE_POSTING_COMPLETED,
            ], true);
    }

    private function hasRfqOrLaterStarted(SvpProcurementChain $chain): bool
    {
        return filled($chain->rfq_id)
            || filled($chain->abstract_id)
            || filled($chain->purchase_order_id)
            || filled($chain->inspection_id)
            || in_array($chain->current_stage, [
                SvpProcurementChain::STAGE_RFQ,
                SvpProcurementChain::STAGE_SUPPLIER_QUOTATIONS,
                SvpProcurementChain::STAGE_ABSTRACT,
                SvpProcurementChain::STAGE_PURCHASE_ORDER,
                SvpProcurementChain::STAGE_DELIVERY,
                SvpProcurementChain::STAGE_INSPECTION,
                SvpProcurementChain::STAGE_ACCOUNTING_SUBMISSION,
                SvpProcurementChain::STAGE_COMPLETED,
            ], true);
    }

    private function hasPostingCompleted(SvpProcurementChain $chain, ?ProcurementDocument $sourceDocument = null): bool
    {
        $chain->loadMissing(['latestPostingRecord', 'events']);

        return in_array($chain->latestPostingRecord?->status, [SvpPostingRecord::STATUS_POSTED, SvpPostingRecord::STATUS_CLOSED], true)
            || $sourceDocument?->status === ProcurementDocument::STATUS_READY_FOR_RFQ
            || $chain->current_stage === SvpProcurementChain::STAGE_POSTING_COMPLETED
            || $chain->events->contains(fn (SvpChainEvent $event) => $event->stage === SvpProcurementChain::STAGE_POSTING_COMPLETED);
    }

    private function markSourceReadyForRfqAfterResolutionLink(
        ProcurementDocument $sourceDocument,
        BacResolution $resolution,
        User $actor,
        ?string $remarks = null,
    ): void
    {
        if (! in_array($sourceDocument->status, [
            ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION,
            ProcurementDocument::STATUS_BAC_RESOLUTION_CREATED,
            ProcurementDocument::STATUS_BAC_RESOLUTION_RETURNED_TO_END_USER,
            ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW,
            ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW,
            ProcurementDocument::STATUS_PENDING_APPROVAL,
        ], true)) {
            return;
        }

        $oldStatus = $sourceDocument->status;
        $fromOfficeId = $sourceDocument->current_office_id;
        $toOfficeId = $sourceDocument->submitting_office_id ?: $actor->office_id;
        $assignedUserId = $sourceDocument->submitted_by_user_id ?: $sourceDocument->prepared_by_user_id ?: $actor->id;

        $sourceDocument->update([
            'status' => ProcurementDocument::STATUS_READY_FOR_RFQ,
            'stage' => ProcurementDocument::STAGE_READY_FOR_RFQ,
            'current_office_id' => $toOfficeId,
            'assigned_to_user_id' => $assignedUserId,
            'route_destination_role' => User::ROLE_HEAD_OFFICE,
            'route_destination_office_id' => $toOfficeId,
            'route_remarks' => 'BAC Resolution linked. Existing RFQ/posting path remains active.',
        ]);

        DocumentRoutingHistory::create([
            'procurement_document_id' => $sourceDocument->id,
            'action_by_user_id' => $actor->id,
            'from_office_id' => $fromOfficeId,
            'to_office_id' => $toOfficeId,
            'action' => 'BAC Resolution Linked - RFQ Path Continues',
            'status_from' => $oldStatus,
            'status_to' => ProcurementDocument::STATUS_READY_FOR_RFQ,
            'comments' => $remarks ?: "BAC Resolution {$resolution->displayNumber()} was linked without restarting posting or RFQ routing.",
            'action_at' => now(),
        ]);
    }

    private function notificationUrl(string $route, mixed $parameters = []): ?string
    {
        return Route::has($route) ? route($route, $parameters) : null;
    }

    private function sourcePrFrom(Model $source): ?ProcurementDocument
    {
        if ($source instanceof ProcurementDocument) {
            return $source;
        }

        if ($source instanceof BacResolution) {
            return $source->sourcePrDocument ?: ProcurementDocument::find($source->source_pr_document_id);
        }

        if ($source instanceof Rfq) {
            return $source->sourcePrDocument
                ?: $source->sourceBacResolution?->sourcePrDocument
                ?: ProcurementDocument::find($source->source_pr_document_id);
        }

        if ($source instanceof AbstractQuotation) {
            return $source->sourcePrDocument
                ?: $source->sourceRfq?->sourcePrDocument
                ?: $source->sourceBacResolution?->sourcePrDocument
                ?: ProcurementDocument::find($source->source_pr_document_id);
        }

        if ($source instanceof PurchaseOrder) {
            return $source->sourcePrDocument
                ?: $source->sourceAbstract?->sourcePrDocument
                ?: $source->sourceBacResolution?->sourcePrDocument
                ?: ProcurementDocument::find($source->source_pr_document_id);
        }

        if ($source instanceof InspectionAcceptanceRecord) {
            return $source->sourcePrDocument
                ?: $source->purchaseOrder?->sourcePrDocument
                ?: ProcurementDocument::find($source->source_pr_document_id);
        }

        return null;
    }

    private function generateChainNumber(ProcurementDocument $pr): string
    {
        $year = $pr->fiscal_year ?: now()->year;
        $officeCode = $this->officeCode($pr->submittingOffice);
        $prefix = "SVP-{$year}-{$officeCode}-";
        $lastNumber = SvpProcurementChain::where('chain_number', 'like', $prefix.'%')
            ->orderByDesc('chain_number')
            ->value('chain_number');
        $sequence = 1;

        if ($lastNumber && preg_match('/(\d+)$/', $lastNumber, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function officeCode(?Office $office): string
    {
        $code = $office?->code ?: $office?->name ?: 'OFFICE';
        $code = preg_replace('/[^A-Z0-9]+/', '', Str::upper($code));

        return $code ?: 'OFFICE';
    }

    private function chainByReferences(array $references): ?SvpProcurementChain
    {
        $references = array_filter($references, fn ($value) => filled($value));

        if ($references === []) {
            return null;
        }

        return SvpProcurementChain::query()
            ->where(function ($query) use ($references) {
                foreach ($references as $column => $value) {
                    $query->orWhere($column, $value);
                }
            })
            ->first();
    }
}
