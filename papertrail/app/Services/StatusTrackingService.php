<?php

namespace App\Services;

use App\Models\{AbstractQuotation, AnnualProcurementPlan, BacResolution, CompetitiveBiddingSignatureDocument, InspectionAcceptanceRecord, Ppmp, ProcurementDocument, PurchaseOrder, Rfq, SignatureRequest, SupplementalApp, SvpPostingRecord, User};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class StatusTrackingService
{
    private const PARTICIPANT_FIELDS = [
        'uploaded_by_user_id', 'submitted_by_user_id', 'prepared_by_user_id', 'assigned_to_user_id',
        'created_by', 'approved_by_user_id', 'accepted_by_user_id', 'bac_chair_confirmed_by_user_id',
        'inspected_by_user_id', 'pr_no_assigned_by_user_id', 'budget_reviewed_by_user_id',
        'accounting_reviewed_by_user_id', 'bac_secretariat_received_by_user_id',
        'bac_member_reviewed_by_user_id', 'bac_chair_reviewed_by_user_id',
    ];
    private const OFFICE_FIELDS = ['office_id', 'submitting_office_id', 'current_office_id', 'route_destination_office_id', 'requesting_office_id'];

    public function __construct(
        private readonly DocumentResolverService $resolver,
        private readonly SignatureRequestService $signatures,
        private readonly DocumentTrackingService $tracking,
    ) {}

    public function registry(): array
    {
        return [
            'procurement_document' => [ProcurementDocument::class, 'Document', ['document_reference_number', 'tracking_number', 'pr_no', 'ppmp_no'], ['title', 'description'], ['procurement_document', 'purchase_request', 'ppmp']],
            'ppmp_record' => [Ppmp::class, 'PPMP', ['ppmp_no'], ['end_user_unit', 'office_name'], ['ppmp_record', 'standalone_ppmp']],
            'app' => [AnnualProcurementPlan::class, 'APP', ['document_reference_number', 'app_number', 'app_no'], ['title'], ['app', 'annual_procurement_plan']],
            'supplemental_app' => [SupplementalApp::class, 'Supplemental APP', ['supplemental_app_number'], ['title', 'purpose'], ['supplemental_app']],
            'bac_resolution' => [BacResolution::class, 'BAC Resolution', ['document_reference_number', 'resolution_number'], ['project_title', 'title'], ['bac_resolution']],
            'rfq' => [Rfq::class, 'RFQ', ['document_reference_number', 'rfq_number'], ['purpose', 'supplier_name'], ['rfq']],
            'abstract' => [AbstractQuotation::class, 'Abstract', ['document_reference_number', 'abstract_number'], ['project_name', 'purpose'], ['abstract', 'abstract_quotation']],
            'purchase_order' => [PurchaseOrder::class, 'Purchase Order', ['document_reference_number', 'po_number'], ['supplier_name'], ['purchase_order']],
            'inspection_acceptance' => [InspectionAcceptanceRecord::class, 'Inspection / Acceptance', ['document_reference_number', 'delivery_receipt_number', 'invoice_number'], ['findings'], ['inspection_acceptance']],
            'svp_posting' => [SvpPostingRecord::class, 'SVP Posting', ['philgeps_reference_number', 'pr_reference'], ['procurement_title'], ['svp_posting', 'svp_posting_record']],
            'competitive_bidding_document' => [CompetitiveBiddingSignatureDocument::class, 'Competitive Bidding', ['tracking_number'], ['document_title'], ['competitive_bidding_document']],
        ];
    }

    public function listing(User $user, array $filters): array
    {
        $union = null;
        foreach ($this->registry() as $type => [$class, $label, $numbers, $titles]) {
            $model = new $class;
            $query = $this->visibleQuery($user, $type);
            $number = $this->coalesce($model, $numbers);
            if ($model instanceof SvpPostingRecord) {
                $number = "CONCAT('POST-', LPAD(".$model->qualifyColumn('id').", 5, '0'))";
            }
            $title = $this->coalesce($model, $titles);
            $query->selectRaw('? AS tracking_type', [$type]);
            if ($model instanceof ProcurementDocument) {
                $query->selectRaw('COALESCE(NULLIF(document_type, ?), ?) AS document_type', ['', $label]);
            } elseif ($model instanceof CompetitiveBiddingSignatureDocument) {
                $query->selectRaw('CASE WHEN template_key = ? THEN ? ELSE ? END AS document_type', ['annual_procurement_plan', 'APP', $label]);
            } else {
                $query->selectRaw('? AS document_type', [$label]);
            }
            $query->selectRaw("{$number} AS reference, {$title} AS title")
                ->addSelect([$model->qualifyColumn('id').' AS document_id', $model->qualifyColumn('status').' AS status', $model->qualifyColumn('updated_at').' AS updated_at']);
            $union = $union ? $union->unionAll($query) : $query;
        }
        $catalog = DB::query()->fromSub($union, 'tracked_documents');
        $types = (clone $catalog)->distinct()->orderBy('document_type')->pluck('document_type');
        $statuses = (clone $catalog)->whereNotNull('status')->distinct()->orderBy('status')->pluck('status');
        $catalog->when(filled($filters['search'] ?? null), function ($query) use ($filters) {
            $query->where(fn ($search) => $search->where('reference', 'like', '%'.trim($filters['search']).'%')->orWhere('title', 'like', '%'.trim($filters['search']).'%'));
        })->when(filled($filters['type'] ?? null), fn ($query) => $query->where('document_type', $filters['type']))
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('status', $filters['status']))
            ->when(filled($filters['date_from'] ?? null), fn ($query) => $query->whereDate('updated_at', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn ($query) => $query->whereDate('updated_at', '<=', $filters['date_to']));
        $documents = $catalog->orderByDesc('updated_at')->orderBy('tracking_type')->orderByDesc('document_id')->paginate(15)->withQueryString();
        $this->hydrateRows($documents);

        return compact('documents', 'types', 'statuses');
    }

    public function find(User $user, string $type, int $id): Model
    {
        $definition = $this->registry()[$type] ?? abort(404);
        $class = $definition[0];
        $document = $class::query()->with($this->relations(new $class))->findOrFail($id);
        abort_unless($this->canView($user, $type, $document), 403);
        if (! $document instanceof CompetitiveBiddingSignatureDocument) {
            $document->setRelation('trackingRequests', $this->pendingRequestsQuery($type)->where('document_id', $id)->get());
        }

        return $document;
    }

    public function canView(User $user, string $type, Model $document): bool
    {
        return $this->resolver->canView($user, $document)
            || $this->signatureDocuments($user, $type)->where('document_id', $document->getKey())->exists();
    }

    public function details(Model $document, string $type): array
    {
        if ($document instanceof CompetitiveBiddingSignatureDocument) {
            return $this->signatureDetails($document);
        }
        $payload = $this->tracking->buildTrackingPayload($document);
        $payload['document_type'] = $document instanceof ProcurementDocument ? $document->document_type : $this->registry()[$type][1];
        $payload['tracking_number'] = $this->resolver->displayTrackingNumber($document) ?: $payload['tracking_number'];
        $payload['current_handler'] = $this->handler($document);
        if (($location = $this->location($document)) !== 'Not assigned') {
            $payload['current_holder'] = $location;
        }
        if ($document instanceof Ppmp) {
            $payload['title'] = $document->end_user_unit ?: $document->office_name ?: 'PPMP';
        }

        return $payload;
    }

    private function visibleQuery(User $user, string $type, bool $withSignatures = true): Builder
    {
        [$class] = $this->registry()[$type];
        $model = new $class;
        $query = $model->newQuery();
        if ($user->isAdmin()) {
            return $query;
        }
        return $query->where(function (Builder $visible) use ($user, $type, $model, $withSignatures) {
            $visible->whereRaw('1 = 0');
            foreach (array_intersect(self::PARTICIPANT_FIELDS, $model->getFillable()) as $field) {
                $visible->orWhere($model->qualifyColumn($field), $user->id);
            }
            if ($user->office_id) {
                foreach (array_intersect(self::OFFICE_FIELDS, $model->getFillable()) as $field) {
                    $visible->orWhere($model->qualifyColumn($field), $user->office_id);
                }
            }
            if ($withSignatures) {
                $visible->orWhereIn($model->qualifyColumn('id'), $this->signatureDocuments($user, $type)->select('document_id')->toBase());
            }
            $sources = $this->sourceRelations($model);
            foreach ($sources as $index => $relation) {
                $visible->orWhere(function (Builder $parent) use ($user, $relation, $sources, $index) {
                    foreach (array_slice($sources, 0, $index) as $previous) {
                        $parent->whereDoesntHave($previous);
                    }
                    $parent->whereHas($relation, function (Builder $source) use ($user) {
                        // Linked records inherit the established source-document permission.
                        $authorized = $this->visibleQuery($user, 'procurement_document', false);
                        $source->addNestedWhereQuery($authorized->getQuery());
                    });
                });
            }
            $visible->orWhere(function (Builder $role) use ($user, $model, $sources) {
                foreach ($sources as $relation) {
                    $role->whereDoesntHave($relation);
                }
                $this->roleScope($role, $user, $model);
            });
        });
    }

    private function roleScope(Builder $query, User $user, Model $model): void
    {
        if ($model instanceof ProcurementDocument) {
            $scope = $this->resolver->procurementVisibility($user);
            $query->where(function (Builder $allowed) use ($scope, $user) {
                $allowed->whereIn('status', $scope['statuses']);
                if ($scope['role'] === User::ROLE_BAC_SECRETARIAT) {
                    $allowed->orWhere(fn (Builder $ppmp) => $ppmp->where('document_type', 'PPMP')->where('status', '!=', ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION))
                        ->orWhere(fn (Builder $assigned) => $assigned->where('status', ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION)->where('assigned_to_user_id', $user->id));
                }
            });
            return;
        }
        if ($model instanceof CompetitiveBiddingSignatureDocument) {
            $query->whereRaw('1 = 0');
            return;
        }
        if ($user->hasRole('bac_secretariat') || $user->hasRole(User::ROLE_BAC_SECRETARIAT)) {
            $query->whereRaw('1 = 1');
            return;
        }
        if ($model instanceof BacResolution && ($user->hasRole('bac_chair') || $user->hasRole(User::ROLE_BAC_CHAIR))) {
            $query->whereIn('status', [BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR, BacResolution::STATUS_RETURNED_BY_BAC_CHAIR, BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR, BacResolution::STATUS_FORWARDED_TO_HOPE, BacResolution::STATUS_APPROVED_BY_HOPE]);
            return;
        }
        if ($user->hasRole('approving_authority') || $user->hasRole(User::ROLE_APPROVING_AUTHORITY)) {
            if ($model instanceof AnnualProcurementPlan) {
                $query->whereIn('status', [AnnualProcurementPlan::STATUS_SUBMITTED, AnnualProcurementPlan::STATUS_CONSOLIDATED, AnnualProcurementPlan::STATUS_APPROVED, AnnualProcurementPlan::STATUS_RETURNED]);
                return;
            }
            if ($model instanceof BacResolution) {
                $query->whereIn('status', [BacResolution::STATUS_FORWARDED_TO_HOPE, BacResolution::STATUS_APPROVED_BY_HOPE, BacResolution::STATUS_RETURNED_BY_HOPE]);
                return;
            }
        }
        $query->whereRaw('1 = 0');
    }

    private function signatureDocuments(User $user, string $type): Builder
    {
        return $this->signatures->requestsForUser($user)->setEagerLoads([])
            ->whereIn('document_type', $this->registry()[$type][4])
            ->whereNotIn('status', [SignatureRequest::STATUS_CANCELLED, SignatureRequest::STATUS_SKIPPED]);
    }

    private function sourceRelations(Model $model): array
    {
        $relations = array_values(array_filter(['sourcePrDocument', 'procurementDocument'], fn ($relation) => method_exists($model, $relation)));
        if ($model instanceof InspectionAcceptanceRecord) {
            $relations[] = 'purchaseOrder.sourcePrDocument';
            $relations[] = 'purchaseOrder.procurementDocument';
        }
        return $relations;
    }

    private function relations(Model $model): array
    {
        $relations = array_values(array_filter(['currentOffice', 'assignedTo', 'submittingOffice', 'office', 'requestingOffice', 'preparedBy', 'creator', 'createdBy'], fn ($relation) => method_exists($model, $relation)));
        foreach ($this->sourceRelations($model) as $source) {
            foreach (['currentOffice', 'assignedTo', 'submittingOffice'] as $relation) {
                $relations[] = $source.'.'.$relation;
            }
        }
        if ($model instanceof CompetitiveBiddingSignatureDocument) {
            array_push($relations, 'signatureRequests.requestedTo', 'signatureRequests.requestedBy', 'signatureRequests.requestedOffice');
        }
        return $relations;
    }

    private function coalesce(Model $model, array $fields): string
    {
        $grammar = $model->getConnection()->getQueryGrammar();
        $parts = array_map(fn ($field) => "NULLIF(".$grammar->wrap($model->qualifyColumn($field)).", '')", array_values(array_intersect($fields, $model->getFillable())));
        $parts[] = 'CAST('.$grammar->wrap($model->qualifyColumn('id')).' AS CHAR)';
        return 'COALESCE('.implode(', ', $parts).')';
    }

    private function hydrateRows(LengthAwarePaginator $page): void
    {
        foreach ($page->getCollection()->groupBy('tracking_type') as $type => $rows) {
            [$class] = $this->registry()[$type];
            $models = $class::query()->with($this->relations(new $class))->whereKey($rows->pluck('document_id'))->get()->keyBy('id');
            $pending = $this->pendingRequestsQuery($type)->whereIn('document_id', $rows->pluck('document_id'))->get()->groupBy('document_id');
            foreach ($rows as $row) {
                $document = $models->get($row->document_id);
                if (! $document) {
                    $row->location = 'Unavailable';
                    $row->handler = null;
                    $row->stage = 'Record removed';
                    continue;
                }
                $document->setRelation('trackingRequests', $pending->get($document->id, collect()));
                $row->reference = $this->resolver->displayTrackingNumber($document) ?: $row->reference;
                $row->location = $this->location($document);
                $row->handler = $this->handler($document);
                $row->stage = $this->source($document)?->stage ?? $document->stage ?? $row->document_type;
            }
        }
    }

    private function source(Model $document): ?ProcurementDocument
    {
        if ($document instanceof ProcurementDocument) {
            return $document;
        }
        foreach ($this->sourceRelations($document) as $relation) {
            if (($source = data_get($document, $relation)) instanceof ProcurementDocument) {
                return $source;
            }
        }
        return null;
    }

    private function location(Model $document): string
    {
        $source = $this->source($document);
        if ($source?->currentOffice?->name) {
            return $source->currentOffice->name;
        }
        $pending = $this->pendingRequests($document)->first();
        if ($office = ($pending?->requestedOffice?->name ?? $pending?->requestedTo?->assignedOffice?->name)) {
            return $office;
        }
        if ($source?->submittingOffice?->name) {
            return $source->submittingOffice->name;
        }
        if ($document instanceof CompetitiveBiddingSignatureDocument) {
            return $document->preparedBy?->assignedOffice?->name ?? 'Signatory workflow';
        }
        foreach (['office', 'requestingOffice'] as $relation) {
            if (method_exists($document, $relation) && $document->{$relation}?->name) {
                return $document->{$relation}->name;
            }
        }
        return $document->office_name ?? $document->requesting_office_name ?? $document->requesting_office ?? 'Not assigned';
    }

    private function handler(Model $document): ?string
    {
        if ($handler = $this->source($document)?->assignedTo?->name) {
            return $handler;
        }
        return $this->pendingRequests($document)->map(fn ($request) => $request->requestedTo?->name ?? $request->requested_to_role)->filter()->unique()->implode(', ') ?: null;
    }

    private function pendingRequestsQuery(string $type): Builder
    {
        return SignatureRequest::query()->whereIn('document_type', $this->registry()[$type][4])
            ->whereIn('status', SignatureRequest::OPEN_STATUSES)->orderBy('signing_order')
            ->with(['requestedTo.assignedOffice', 'requestedOffice']);
    }

    private function pendingRequests(Model $document): \Illuminate\Support\Collection
    {
        return $document instanceof CompetitiveBiddingSignatureDocument
            ? $document->signatureRequests->whereIn('status', SignatureRequest::OPEN_STATUSES)->sortBy('signing_order')
            : ($document->relationLoaded('trackingRequests') ? $document->getRelation('trackingRequests') : collect());
    }

    private function signatureDetails(CompetitiveBiddingSignatureDocument $document): array
    {
        $events = collect([['label' => 'Document submitted for signatures', 'date' => $document->submitted_at ?? $document->created_at, 'location' => $document->preparedBy?->name, 'state' => 'completed']]);
        foreach ($document->signatureRequests->sortBy('signing_order') as $request) {
            $recipient = $request->requestedTo?->name ?? $request->signatory_label ?? 'Assigned signatory';
            $events->push(['label' => 'Signature requested: '.$recipient, 'date' => $request->created_at, 'location' => $request->requestedOffice?->name, 'state' => 'completed']);
            foreach (['viewed_at' => 'Viewed', 'signed_at' => 'Signed', 'declined_at' => 'Declined', 'cancelled_at' => 'Cancelled'] as $field => $label) {
                if ($request->{$field}) {
                    $events->push(['label' => $label.': '.$recipient, 'date' => $request->{$field}, 'location' => $request->requestedOffice?->name, 'state' => 'completed', 'actor' => $recipient, 'comments' => $request->remarks]);
                }
            }
            if ($request->status === SignatureRequest::STATUS_RETURNED) {
                $events->push(['label' => 'Returned: '.$recipient, 'date' => $request->declined_at ?? $request->updated_at, 'location' => $request->requestedOffice?->name, 'state' => 'completed', 'actor' => $recipient, 'comments' => $request->remarks]);
            }
        }
        $timeline = $events->filter(fn ($event) => $event['date'])->sortBy('date')->values()->map(function ($event) {
            $event['date_display'] = $event['date']->format('M d, Y h:i A');
            return $event;
        })->all();
        if ($timeline) {
            $timeline[array_key_last($timeline)]['state'] = 'current';
        }
        $next = match ($document->status) {
            CompetitiveBiddingSignatureDocument::STATUS_FULLY_SIGNED => 'All required signatures are complete. Continue with the next project document step.',
            CompetitiveBiddingSignatureDocument::STATUS_RETURNED => 'Review return remarks and correct the document before resubmitting.',
            default => 'Await the remaining required signatures'.($this->handler($document) ? ', including '.$this->handler($document) : '').'.',
        };
        return ['document_type' => $document->template_key === 'annual_procurement_plan' ? 'APP' : 'Competitive Bidding', 'tracking_number' => $document->displayNumber(), 'title' => $document->document_title,
            'current_status' => $document->statusLabel(), 'current_holder' => $this->location($document), 'current_handler' => $this->handler($document),
            'current_stage' => $document->status === CompetitiveBiddingSignatureDocument::STATUS_FULLY_SIGNED ? 'Signatures completed' : 'Signatory workflow', 'timeline' => $timeline, 'next_step' => $next];
    }
}
