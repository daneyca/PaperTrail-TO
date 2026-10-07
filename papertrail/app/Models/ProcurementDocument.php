<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentReferenceNumber;
use App\Services\DocumentReferenceNumberService;
use App\Services\NotificationDispatchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProcurementDocument extends Model
{
    use HasDocumentReferenceNumber;

    public const STATUS_PPMP_DRAFT = 'ppmp_draft';
    public const STATUS_PPMP_PENDING_SIGNATORIES = 'ppmp_pending_signatories';
    public const STATUS_PPMP_SIGNATORIES_COMPLETED = 'ppmp_signatories_completed';
    public const STATUS_PPMP_SIGNATURE_RETURNED = 'ppmp_signature_returned';
    public const STATUS_PENDING_PPMP_REVIEW = 'pending_ppmp_review';
    public const STATUS_UNDER_PPMP_REVIEW = 'under_ppmp_review';
    public const STATUS_ACCEPTED_FOR_APP_CONSOLIDATION = 'accepted_for_app_consolidation';
    public const STATUS_PPMP_CANCELLED = 'ppmp_cancelled';

    public const STATUS_PENDING_BUDGET_REVIEW = 'pending_budget_review';
    public const STATUS_UNDER_BUDGET_REVIEW = 'under_budget_review';
    public const STATUS_RETURNED_BY_BUDGET = 'returned_by_budget';
    public const STATUS_BUDGET_REVIEWED = 'budget_reviewed';
    public const STATUS_PENDING_ACCOUNTING_REVIEW = 'pending_accounting_review';
    public const STATUS_UNDER_ACCOUNTING_REVIEW = 'under_accounting_review';
    public const STATUS_RETURNED_BY_ACCOUNTING = 'returned_by_accounting';
    public const STATUS_ACCOUNTING_REVIEWED = 'accounting_reviewed';
    public const STATUS_PENDING_BAC_SECRETARIAT_REVIEW = 'pending_bac_secretariat_review';
    public const STATUS_RECEIVED_BY_BAC_SECRETARIAT = 'received_by_bac_secretariat';
    public const STATUS_UNDER_BAC_SECRETARIAT_REVIEW = 'under_bac_secretariat_review';
    public const STATUS_RETURNED_BY_BAC_SECRETARIAT = 'returned_by_bac_secretariat';
    public const STATUS_READY_FOR_DOCUMENT_ROUTING = 'ready_for_document_routing';
    public const STATUS_ROUTED_TO_BAC_MEMBER = 'routed_to_bac_member';
    public const STATUS_PENDING_BAC_MEMBER_REVIEW = 'pending_bac_member_review';
    public const STATUS_UNDER_BAC_MEMBER_REVIEW = 'under_bac_member_review';
    public const STATUS_RETURNED_BY_BAC_MEMBER = 'returned_by_bac_member';
    public const STATUS_ENDORSED_BY_BAC_MEMBER = 'endorsed_by_bac_member';
    public const STATUS_ROUTED_TO_BAC_CHAIR = 'routed_to_bac_chair';
    public const STATUS_PENDING_BAC_CHAIR_REVIEW = 'pending_bac_chair_review';
    public const STATUS_UNDER_BAC_CHAIR_REVIEW = 'under_bac_chair_review';
    public const STATUS_READY_FOR_BAC_CHAIR_CONFIRMATION = 'ready_for_bac_chair_confirmation';
    public const STATUS_RETURNED_BY_BAC_CHAIR = 'returned_by_bac_chair';
    public const STATUS_CONFIRMED_BY_BAC_CHAIR = 'confirmed_by_bac_chair';
    public const STATUS_ROUTED_TO_APPROVING_AUTHORITY = 'routed_to_approving_authority';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_UNDER_APPROVAL = 'under_approval';
    public const STATUS_RETURNED_BY_APPROVING_AUTHORITY = 'returned_by_approving_authority';
    public const STATUS_APPROVAL_DEFERRED = 'approval_deferred';
    public const STATUS_ROUTED_FOR_ADDITIONAL_REVIEW = 'routed_for_additional_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_READY_FOR_PO = 'ready_for_po';
    public const STATUS_APP_APPROVED = 'app_approved';
    public const STATUS_PO_APPROVED = 'po_approved';
    public const STATUS_APP_CONSOLIDATED = 'app_consolidated';
    public const STATUS_APP_SUBMITTED_FOR_APPROVAL = 'app_submitted_for_approval';
    public const STATUS_PR_DRAFT = 'pr_draft';
    public const STATUS_PR_PENDING_SIGNATORIES = 'pr_pending_signatories';
    public const STATUS_PR_SIGNATORIES_COMPLETED = 'pr_signatories_completed';
    public const STATUS_PENDING_PR_NUMBER_ASSIGNMENT = 'pending_pr_number_assignment';
    public const STATUS_PR_NUMBER_ASSIGNED = 'pr_number_assigned';
    public const STATUS_PR_NUMBER_ASSIGNED_RETURNED_TO_END_USER = 'pr_number_assigned_returned_to_end_user';
    public const STATUS_RETURNED_BY_PR_NUMBERING_STAFF = 'returned_by_pr_numbering_staff';
    public const STATUS_SUBMITTED_TO_BAC_SECRETARIAT = 'submitted_to_bac_secretariat';
    public const STATUS_PR_SUBMITTED = 'pr_submitted';
    public const STATUS_PR_RECEIVED_BY_BAC_SECRETARIAT = 'pr_received_by_bac_secretariat';
    public const STATUS_UNDER_PR_VALIDATION = 'under_pr_validation';
    public const STATUS_NO_PPMP_RECORD_FOUND = 'no_ppmp_record_found';
    public const STATUS_PENDING_SUPPLEMENTAL_APP = 'pending_supplemental_app';
    public const STATUS_SUPPLEMENTAL_APP_CREATED = 'supplemental_app_created';
    public const STATUS_READY_FOR_BAC_RESOLUTION = 'ready_for_bac_resolution';
    public const STATUS_BAC_RESOLUTION_CREATED = 'bac_resolution_created';
    public const STATUS_BAC_RESOLUTION_RETURNED_TO_END_USER = 'bac_resolution_returned_to_end_user';
    public const STATUS_SVP_POSTING_REQUIRED = 'svp_posting_required';
    public const STATUS_SVP_POSTING_COMPLETED = 'svp_posting_completed';
    public const STATUS_READY_FOR_RFQ = 'ready_for_rfq';
    public const STATUS_PO_DRAFT = 'po_draft';
    public const STATUS_PO_PREPARED = 'po_prepared';
    public const STATUS_PO_ISSUED = 'po_issued';
    public const STATUS_PO_RETURNED = 'po_returned';
    public const STATUS_PO_COMPLETED = 'po_completed';
    public const STATUS_PO_CANCELLED = 'po_cancelled';

    public const PR_STATUS_SUBMITTED = 'submitted';
    public const PR_STATUS_RECEIVED = 'received';
    public const PR_STATUS_UNDER_VALIDATION = 'under_validation';
    public const PR_STATUS_RETURNED = 'returned';
    public const PR_STATUS_ROUTED_TO_BUDGET = 'routed_to_budget';

    public const PR_NUMBER_STATUS_NOT_REQUESTED = 'not_requested';
    public const PR_NUMBER_STATUS_PENDING_ASSIGNMENT = 'pending_assignment';
    public const PR_NUMBER_STATUS_ASSIGNED = 'assigned';
    public const PR_NUMBER_STATUS_RETURNED = 'returned';

    public const STAGE_BUDGET_REVIEW = 'Budget Review';
    public const STAGE_ACCOUNTING_REVIEW = 'Accounting Review';
    public const STAGE_BAC_SECRETARIAT_REVIEW = 'BAC Secretariat Review';
    public const STAGE_DOCUMENT_ROUTING = 'Document Routing';
    public const STAGE_BAC_MEMBER_REVIEW = 'BAC Member Review';
    public const STAGE_BAC_CHAIR_REVIEW = 'BAC Chair Review';
    public const STAGE_BAC_CHAIR_CONFIRMATION = 'BAC Chair Confirmation';
    public const STAGE_APPROVING_AUTHORITY_REVIEW = 'Head of the Procuring Entity Review';
    public const STAGE_APPROVED = 'Approved';
    public const STAGE_READY_FOR_PURCHASE_ORDER = 'Ready for Purchase Order';
    public const STAGE_APP_APPROVED = 'APP Approved';
    public const STAGE_PURCHASE_ORDER_APPROVED = 'Purchase Order Approved';
    public const STAGE_ADDITIONAL_REVIEW = 'Additional Review';
    public const STAGE_PR_SIGNATORY_WORKFLOW = 'PR Signatory Workflow';
    public const STAGE_PR_SIGNATORIES_COMPLETED = 'Completed Signatories';
    public const STAGE_PR_NUMBER_ASSIGNMENT = 'PR Number Assignment';
    public const STAGE_PR_NUMBER_ASSIGNED = 'PR Number Assigned';
    public const STAGE_BAC_SECRETARIAT_PR_VALIDATION = 'BAC Secretariat PR Validation';
    public const STAGE_SUPPLEMENTAL_APP_PREPARATION = 'Supplemental APP Preparation';
    public const STAGE_READY_FOR_BAC_RESOLUTION = 'Ready for BAC Resolution';
    public const STAGE_BAC_RESOLUTION_PREPARATION = 'BAC Resolution Preparation';
    public const STAGE_BAC_RESOLUTION_RETURNED_TO_END_USER = 'BAC Resolution Returned to End User';
    public const STAGE_SVP_POSTING = 'Posting';
    public const STAGE_SVP_POSTING_COMPLETED = 'Posting Period Completed';
    public const STAGE_READY_FOR_RFQ = 'Ready for RFQ';
    public const STAGE_PURCHASE_ORDER_PREPARATION = 'Purchase Order Preparation';
    public const STAGE_PURCHASE_ORDER_ISSUED = 'Purchase Order Issued';
    public const STAGE_PURCHASE_ORDER_COMPLETED = 'Purchase Order Completed';
    public const STAGE_READY_FOR_ROUTING = 'Ready for Routing';
    public const STAGE_RETURNED_TO_BAC_SECRETARIAT = 'Returned to BAC Secretariat';
    public const STAGE_RETURNED_TO_BAC_MEMBER = 'Returned to BAC Member';
    public const STAGE_RETURNED_TO_ACCOUNTING_OFFICE = 'Returned to Accounting Office';
    public const STAGE_RETURNED_TO_END_USER = 'Returned to End User';
    public const STAGE_RETURNED_TO_BUDGET_OFFICE = 'Returned to Budget Office';
    public const STAGE_RETURNED_TO_REQUESTING_OFFICE = 'Returned to Requesting Office';
    public const STAGE_PPMP_PREPARATION = 'PPMP Preparation';
    public const STAGE_PPMP_SIGNATURE_WORKFLOW = 'PPMP Signature Workflow';
    public const STAGE_PPMP_SIGNATORIES_COMPLETED = 'PPMP Signatories Completed';
    public const STAGE_PPMP_SUBMITTED_TO_BAC = 'Submitted to BAC';
    public const STAGE_PPMP_UNDER_APP_CONSOLIDATION = 'Under APP Consolidation';
    public const STAGE_BAC_SECRETARIAT_PPMP_REVIEW = 'BAC Secretariat PPMP Review';
    public const STAGE_ACCEPTED_FOR_APP_CONSOLIDATION = 'Accepted for APP Consolidation';
    public const STAGE_CANCELLED = 'Cancelled';

    protected $fillable = [
        'tracking_number',
        'document_reference_number',
        'sequence_number',
        'created_year',
        'created_month',
        'ppmp_no',
        'ppmp_plan_type',
        'document_type',
        'title',
        'description',
        'fiscal_year',
        'submitting_office_id',
        'submitted_by_user_id',
        'prepared_by_user_id',
        'prepared_by_name',
        'submitted_by_name',
        'current_office_id',
        'assigned_to_user_id',
        'status',
        'stage',
        'priority',
        'total_amount',
        'remarks',
        'submitted_at',
        'returned_at',
        'accepted_at',
        'ppmp_review_status',
        'ppmp_reviewed_by_user_id',
        'ppmp_review_started_at',
        'ppmp_reviewed_at',
        'ppmp_remarks',
        'budget_status',
        'budget_reviewed_by_user_id',
        'budget_review_started_at',
        'budget_reviewed_at',
        'budget_remarks',
        'accounting_status',
        'accounting_reviewed_by_user_id',
        'accounting_review_started_at',
        'accounting_reviewed_at',
        'accounting_remarks',
        'accounting_reference_no',
        'account_code',
        'object_code',
        'responsibility_center',
        'bac_secretariat_status',
        'bac_secretariat_received_by_user_id',
        'bac_secretariat_received_at',
        'bac_secretariat_review_started_at',
        'bac_secretariat_processed_at',
        'bac_secretariat_remarks',
        'routed_by_user_id',
        'routed_at',
        'route_destination_role',
        'route_destination_office_id',
        'route_remarks',
        'bac_member_status',
        'bac_member_reviewed_by_user_id',
        'bac_member_review_started_at',
        'bac_member_reviewed_at',
        'bac_member_remarks',
        'bac_chair_status',
        'bac_chair_reviewed_by_user_id',
        'bac_chair_review_started_at',
        'bac_chair_reviewed_at',
        'bac_chair_decision',
        'bac_chair_remarks',
        'bac_chair_confirmation_status',
        'bac_chair_confirmed_at',
        'bac_chair_confirmation_remarks',
        'approval_status',
        'approval_started_at',
        'approved_by_user_id',
        'approved_at',
        'approval_decision',
        'approval_remarks',
        'returned_by_approving_authority_at',
        'pr_status',
        'pr_number_status',
        'pr_no_requested_at',
        'pr_no_assigned_by_user_id',
        'pr_no_assigned_at',
        'pr_number_remarks',
        'pr_received_by_user_id',
        'pr_received_at',
        'pr_validation_started_at',
        'pr_validated_by_user_id',
        'pr_validated_at',
        'pr_remarks',
        'app_consolidation_id',
        'app_item_id',
        'requested_delivery_date',
        'purpose',
        'department_name',
        'fund_cluster',
        'section',
        'pr_no',
        'pr_date',
        'sai_no',
        'sai_date',
        'alobs_no',
        'alobs_date',
        'requested_by_name',
        'requested_by_designation',
        'approved_by_name',
        'approved_by_designation',
        'certification_text',
        'pr_signatories',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'returned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'ppmp_review_started_at' => 'datetime',
        'ppmp_reviewed_at' => 'datetime',
        'budget_review_started_at' => 'datetime',
        'budget_reviewed_at' => 'datetime',
        'accounting_review_started_at' => 'datetime',
        'accounting_reviewed_at' => 'datetime',
        'bac_secretariat_received_at' => 'datetime',
        'bac_secretariat_review_started_at' => 'datetime',
        'bac_secretariat_processed_at' => 'datetime',
        'routed_at' => 'datetime',
        'bac_member_review_started_at' => 'datetime',
        'bac_member_reviewed_at' => 'datetime',
        'bac_chair_review_started_at' => 'datetime',
        'bac_chair_reviewed_at' => 'datetime',
        'bac_chair_confirmed_at' => 'datetime',
        'approval_started_at' => 'datetime',
        'approved_at' => 'datetime',
        'returned_by_approving_authority_at' => 'datetime',
        'pr_no_requested_at' => 'datetime',
        'pr_no_assigned_at' => 'datetime',
        'pr_received_at' => 'datetime',
        'pr_validation_started_at' => 'datetime',
        'pr_validated_at' => 'datetime',
        'requested_delivery_date' => 'date',
        'pr_date' => 'date',
        'sai_date' => 'date',
        'alobs_date' => 'date',
        'pr_signatories' => 'array',
        'total_amount' => 'decimal:2',
        'sequence_number' => 'integer',
        'created_year' => 'integer',
        'created_month' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(function (ProcurementDocument $document) {
            if ($document->status !== self::STATUS_PENDING_BUDGET_REVIEW
                || (! $document->wasRecentlyCreated && ! $document->wasChanged(['status', 'current_office_id', 'assigned_to_user_id']))) {
                return;
            }

            User::where('role', User::ROLE_BUDGET)
                ->where('status', User::STATUS_ACTIVE)
                ->get()
                ->each(function (User $budgetOfficer) use ($document) {
                    $exists = SystemNotification::where('user_id', $budgetOfficer->id)
                        ->where('title', 'New Document for Budget Review')
                        ->where('related_type', $document::class)
                        ->where('related_id', $document->id)
                        ->exists();

                    if ($exists) {
                        return;
                    }

                    app(NotificationDispatchService::class)->notifyUser(
                        $budgetOfficer,
                        'New Document for Budget Review',
                        "Document {$document->tracking_number} has been routed to the Budget Office for review.",
                        route('budget.pending-review.show', $document),
                        [],
                        SystemNotification::TYPE_INFO,
                        'Budget Review',
                        $document,
                    );
                });
        });

        static::saved(function (ProcurementDocument $document) {
            if ($document->status !== self::STATUS_PENDING_ACCOUNTING_REVIEW
                || (! $document->wasRecentlyCreated && ! $document->wasChanged(['status', 'current_office_id', 'assigned_to_user_id']))) {
                return;
            }

            User::where('role', User::ROLE_ACCOUNTING)
                ->where('status', User::STATUS_ACTIVE)
                ->get()
                ->each(function (User $accountingOfficer) use ($document) {
                    $exists = SystemNotification::where('user_id', $accountingOfficer->id)
                        ->where('title', 'Document routed to Accounting Office')
                        ->where('related_type', $document::class)
                        ->where('related_id', $document->id)
                        ->exists();

                    if ($exists) {
                        return;
                    }

                    app(NotificationDispatchService::class)->notifyUser(
                        $accountingOfficer,
                        'New Document for Accounting Review',
                        "Document {$document->tracking_number} has been forwarded for accounting verification.",
                        route('accounting.pending-review.show', $document),
                        [],
                        SystemNotification::TYPE_INFO,
                        'Accounting Review',
                        $document,
                    );
                });
        });

        static::saved(function (ProcurementDocument $document) {
            if ($document->status !== self::STATUS_PENDING_BAC_SECRETARIAT_REVIEW
                || (!$document->wasRecentlyCreated && !$document->wasChanged(['status', 'current_office_id', 'assigned_to_user_id']))) {
                return;
            }

            User::where('role', User::ROLE_BAC_SECRETARIAT)
                ->where('status', User::STATUS_ACTIVE)
                ->get()
                ->each(function (User $bacUser) use ($document) {
                    $exists = SystemNotification::where('user_id', $bacUser->id)
                        ->where('title', 'New Document for BAC Secretariat Review')
                        ->where('related_type', $document::class)
                        ->where('related_id', $document->id)
                        ->exists();

                    if ($exists) {
                        return;
                    }

                    app(NotificationDispatchService::class)->notifyUser(
                        $bacUser,
                        'New Document for BAC Secretariat Review',
                        "Document {$document->tracking_number} has been verified by Accounting and forwarded to BAC Secretariat.",
                        route('bac-secretariat.incoming.show', $document),
                        [],
                        SystemNotification::TYPE_SUCCESS,
                        'BAC Secretariat',
                        $document,
                    );
                });
        });
    }

    public function submittingOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'submitting_office_id');
    }

    public function paperTrailReferenceType(): string
    {
        return DocumentReferenceNumberService::normalizeType($this->document_type);
    }

    public function shouldAssignPaperTrailReferenceOnCreate(): bool
    {
        if ($this->paperTrailReferenceType() !== DocumentReferenceNumberService::TYPE_PPMP) {
            return true;
        }

        return in_array($this->status, [
            self::STATUS_PENDING_PPMP_REVIEW,
            self::STATUS_UNDER_PPMP_REVIEW,
            self::STATUS_RETURNED_BY_BAC_SECRETARIAT,
            self::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION,
        ], true);
    }

    public function getTrackingNumberAttribute(?string $value): ?string
    {
        return $this->attributes['document_reference_number'] ?? $value;
    }

    public function getOfficialPrNumberAttribute(): ?string
    {
        return $this->pr_no;
    }

    public function displayNumber(): string
    {
        return $this->document_reference_number
            ?: $this->tracking_number
            ?: $this->pr_no
            ?: 'Document #' . $this->getKey();
    }

    public function getDisplayTrackingNumberAttribute(): string
    {
        return $this->displayNumber();
    }

    public function currentOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'current_office_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function budgetReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'budget_reviewed_by_user_id');
    }

    public function accountingReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accounting_reviewed_by_user_id');
    }

    public function bacSecretariatReceivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bac_secretariat_received_by_user_id');
    }

    public function routedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'routed_by_user_id');
    }

    public function bacMemberReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bac_member_reviewed_by_user_id');
    }

    public function bacChairReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bac_chair_reviewed_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function routeDestinationOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'route_destination_office_id');
    }

    public function prReceivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pr_received_by_user_id');
    }

    public function prNoAssignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pr_no_assigned_by_user_id');
    }

    public function prValidatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pr_validated_by_user_id');
    }

    public function ppmpReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ppmp_reviewed_by_user_id');
    }

    public function appConsolidation(): BelongsTo
    {
        return $this->belongsTo(AppConsolidation::class);
    }

    public function appItem(): BelongsTo
    {
        return $this->belongsTo(AppItem::class);
    }

    public function routingHistories(): HasMany
    {
        return $this->hasMany(DocumentRoutingHistory::class)->latest('action_at');
    }

    public function ppmpItems(): HasMany
    {
        return $this->hasMany(PpmpItem::class)->orderBy('row_order')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(DocumentAttachment::class);
    }

    public function budgetReviews(): HasMany
    {
        return $this->hasMany(BudgetReview::class);
    }

    public function accountingReviews(): HasMany
    {
        return $this->hasMany(AccountingReview::class);
    }

    public function bacSecretariatReviews(): HasMany
    {
        return $this->hasMany(BacSecretariatReview::class);
    }

    public function ppmpReviews(): HasMany
    {
        return $this->hasMany(PpmpReview::class);
    }

    public function bacMemberReviews(): HasMany
    {
        return $this->hasMany(BacMemberReview::class);
    }

    public function bacChairReviews(): HasMany
    {
        return $this->hasMany(BacChairReview::class);
    }

    public function documentApprovals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class);
    }

    public function bacDeliberations(): HasMany
    {
        return $this->hasMany(BacDeliberation::class);
    }

    public function appPpmpSources(): HasMany
    {
        return $this->hasMany(AppPpmpSource::class);
    }

    public function annualProcurementPlanItems(): HasMany
    {
        return $this->hasMany(AnnualProcurementPlanItem::class, 'source_ppmp_document_id');
    }

    public function purchaseRequestItems(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function purchaseRequestValidations(): HasMany
    {
        return $this->hasMany(PurchaseRequestValidation::class);
    }

    public function supplementalApps(): HasMany
    {
        return $this->hasMany(SupplementalApp::class, 'source_pr_document_id');
    }

    public function acceptedSupplementalApps(): HasMany
    {
        return $this->hasMany(SupplementalApp::class, 'source_pr_document_id')
            ->whereIn('status', [
                SupplementalApp::STATUS_ACCEPTED,
                SupplementalApp::STATUS_LINKED_TO_PR,
            ]);
    }

    public function bacResolutions(): HasMany
    {
        return $this->hasMany(BacResolution::class, 'source_pr_document_id');
    }

    public function latestBacResolution(): HasOne
    {
        return $this->hasOne(BacResolution::class, 'source_pr_document_id')->latestOfMany();
    }

    public function rfqs(): HasMany
    {
        return $this->hasMany(Rfq::class, 'source_pr_document_id');
    }

    public function sourcePurchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'source_pr_document_id');
    }

    public function purchaseOrder(): HasOne
    {
        return $this->hasOne(PurchaseOrder::class);
    }

    public function scopeVisibleToBudgetOfficer(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $builder) use ($user) {
            $builder->whereHas('currentOffice', function (Builder $officeQuery) {
                $officeQuery->where('code', 'MBO')
                    ->orWhere('name', 'Budget Office');
            })->orWhere('assigned_to_user_id', $user->id);
        });
    }

    public function scopeReviewedByBudgetOfficer(Builder $query, User $user): Builder
    {
        return $query
            ->whereIn('status', [
                self::STATUS_BUDGET_REVIEWED,
                self::STATUS_PENDING_ACCOUNTING_REVIEW,
            ])
            ->whereIn('budget_status', [
                BudgetReview::STATUS_BUDGET_AVAILABLE,
                'reviewed',
                'endorsed',
            ])
            ->where(function (Builder $builder) use ($user) {
                $builder->where('budget_reviewed_by_user_id', $user->id)
                    ->orWhereHas('budgetReviews', fn (Builder $review) => $review->where('reviewed_by_user_id', $user->id));
            });
    }

    public function scopeReturnedByBudgetOfficer(Builder $query, User $user): Builder
    {
        return $query
            ->whereNotIn('status', [
                self::STATUS_PENDING_BUDGET_REVIEW,
                self::STATUS_UNDER_BUDGET_REVIEW,
                self::STATUS_BUDGET_REVIEWED,
                self::STATUS_PENDING_ACCOUNTING_REVIEW,
            ])
            ->where(function (Builder $statusQuery) {
                $statusQuery->where('status', self::STATUS_RETURNED_BY_BUDGET)
                    ->orWhereIn('budget_status', [
                        BudgetReview::STATUS_RETURNED,
                        BudgetReview::STATUS_INSUFFICIENT_FUNDS,
                    ]);
            })
            ->where(function (Builder $ownerQuery) use ($user) {
                $ownerQuery->where('budget_reviewed_by_user_id', $user->id)
                    ->orWhereHas('budgetReviews', fn (Builder $review) => $review->where('reviewed_by_user_id', $user->id))
                    ->orWhereHas('routingHistories', function (Builder $history) use ($user) {
                        $history->where('action_by_user_id', $user->id)
                            ->where('status_to', self::STATUS_RETURNED_BY_BUDGET);
                    });
            });
    }

    public function scopeVisibleToAccountingOfficer(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $builder) use ($user) {
            $builder->whereHas('currentOffice', function (Builder $officeQuery) {
                $officeQuery->where('code', 'MACCO')
                    ->orWhere('name', 'Accounting Office');
            })->orWhere('assigned_to_user_id', $user->id);
        });
    }

    public function scopeReviewedByAccountingOfficer(Builder $query, User $user): Builder
    {
        return $query
            ->whereIn('status', [
                self::STATUS_ACCOUNTING_REVIEWED,
                self::STATUS_PENDING_BAC_SECRETARIAT_REVIEW,
                'under_bac_secretariat_review',
                'bac_secretariat_reviewed',
                'approved',
            ])
            ->whereIn('accounting_status', [
                AccountingReview::STATUS_ACCOUNTING_VERIFIED,
                'reviewed',
                'endorsed',
            ])
            ->where(function (Builder $builder) use ($user) {
                $builder->where('accounting_reviewed_by_user_id', $user->id)
                    ->orWhereHas('accountingReviews', fn (Builder $review) => $review->where('reviewed_by_user_id', $user->id));
            });
    }

    public function scopeReturnedByAccountingOfficer(Builder $query, User $user): Builder
    {
        return $query
            ->whereNotIn('status', [
                self::STATUS_PENDING_ACCOUNTING_REVIEW,
                self::STATUS_UNDER_ACCOUNTING_REVIEW,
                self::STATUS_ACCOUNTING_REVIEWED,
                self::STATUS_PENDING_BAC_SECRETARIAT_REVIEW,
            ])
            ->where(function (Builder $statusQuery) {
                $statusQuery->where('status', self::STATUS_RETURNED_BY_ACCOUNTING)
                    ->orWhereIn('accounting_status', [
                        AccountingReview::STATUS_RETURNED,
                        AccountingReview::STATUS_NON_COMPLIANT,
                    ]);
            })
            ->where(function (Builder $builder) use ($user) {
                $builder->where('accounting_reviewed_by_user_id', $user->id)
                    ->orWhereHas('accountingReviews', fn (Builder $review) => $review->where('reviewed_by_user_id', $user->id))
                    ->orWhereHas('routingHistories', function (Builder $history) use ($user) {
                        $history->where('action_by_user_id', $user->id)
                            ->where('status_to', self::STATUS_RETURNED_BY_ACCOUNTING);
                    });
            });
    }

    public function scopeVisibleToBacSecretariat(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $builder) use ($user) {
            $builder->whereHas('currentOffice', function (Builder $officeQuery) {
                $officeQuery->where('code', 'BACSEC')
                    ->orWhere('name', 'BAC Secretariat');
            })->orWhere('assigned_to_user_id', $user->id);
        });
    }

    public function scopeIncomingForBacSecretariat(Builder $query, User $user, bool $includeReturned = false): Builder
    {
        $statuses = [
            self::STATUS_PENDING_BAC_SECRETARIAT_REVIEW,
            self::STATUS_RECEIVED_BY_BAC_SECRETARIAT,
            self::STATUS_UNDER_BAC_SECRETARIAT_REVIEW,
            self::STATUS_READY_FOR_DOCUMENT_ROUTING,
            self::STATUS_PENDING_PPMP_REVIEW,
            self::STATUS_UNDER_PPMP_REVIEW,
        ];

        if ($includeReturned) {
            $statuses[] = self::STATUS_RETURNED_BY_BAC_SECRETARIAT;
        }

        return $query
            ->visibleToBacSecretariat($user)
            ->whereIn('status', $statuses);
    }

    public function scopeRoutingForBacSecretariat(Builder $query, User $user): Builder
    {
        return $query
            ->whereIn('status', [
                self::STATUS_READY_FOR_DOCUMENT_ROUTING,
                self::STATUS_ROUTED_TO_BAC_MEMBER,
                self::STATUS_PENDING_BAC_MEMBER_REVIEW,
                self::STATUS_ROUTED_TO_BAC_CHAIR,
                self::STATUS_PENDING_BAC_CHAIR_REVIEW,
                self::STATUS_ROUTED_TO_APPROVING_AUTHORITY,
                self::STATUS_PENDING_APPROVAL,
                self::STATUS_ROUTED_FOR_ADDITIONAL_REVIEW,
            ])
            ->where(function (Builder $builder) use ($user) {
                $builder->where(function (Builder $visible) use ($user) {
                    $visible->visibleToBacSecretariat($user);
                })->orWhere('routed_by_user_id', $user->id);
            });
    }

    public function scopePurchaseRequestsForBacSecretariat(Builder $query, User $user): Builder
    {
        $statuses = [
            self::STATUS_SUBMITTED_TO_BAC_SECRETARIAT,
            self::STATUS_PR_SUBMITTED,
            self::STATUS_PR_RECEIVED_BY_BAC_SECRETARIAT,
            self::STATUS_UNDER_PR_VALIDATION,
            self::STATUS_NO_PPMP_RECORD_FOUND,
            self::STATUS_PENDING_SUPPLEMENTAL_APP,
            self::STATUS_SUPPLEMENTAL_APP_CREATED,
            self::STATUS_BAC_RESOLUTION_CREATED,
            self::STATUS_BAC_RESOLUTION_RETURNED_TO_END_USER,
            self::STATUS_READY_FOR_RFQ,
            self::STATUS_RETURNED_BY_BAC_SECRETARIAT,
            self::STATUS_PENDING_BUDGET_REVIEW,
            self::STATUS_UNDER_BUDGET_REVIEW,
            self::STATUS_PENDING_ACCOUNTING_REVIEW,
            self::STATUS_PENDING_BAC_SECRETARIAT_REVIEW,
            self::STATUS_READY_FOR_DOCUMENT_ROUTING,
            self::STATUS_PENDING_BAC_MEMBER_REVIEW,
            self::STATUS_PENDING_BAC_CHAIR_REVIEW,
            self::STATUS_PENDING_APPROVAL,
            'approved',
        ];

        return $query
            ->whereIn('document_type', ['PR', 'Purchase Request'])
            ->where(function (Builder $numbering) {
                $numbering->whereNotIn('status', [
                    self::STATUS_PENDING_PR_NUMBER_ASSIGNMENT,
                    self::STATUS_RETURNED_BY_PR_NUMBERING_STAFF,
                ])
                    ->where(function (Builder $assigned) {
                        $assigned->whereNotIn('status', [self::STATUS_PR_SUBMITTED, self::STATUS_SUBMITTED_TO_BAC_SECRETARIAT])
                            ->orWhere(function (Builder $submitted) {
                                $submitted->whereNotNull('pr_no')
                                    ->where(function (Builder $status) {
                                        $status->where('pr_number_status', self::PR_NUMBER_STATUS_ASSIGNED)
                                            ->orWhereNull('pr_number_status');
                                    });
                            });
                    });
            })
            ->where(function (Builder $builder) use ($user, $statuses) {
                $builder->where(function (Builder $statusQuery) use ($user, $statuses) {
                    $statusQuery->whereIn('status', $statuses)
                        ->where(function (Builder $readyReference) use ($user) {
                            $readyReference->where('status', '!=', self::STATUS_READY_FOR_BAC_RESOLUTION)
                                ->orWhere('assigned_to_user_id', $user->id);
                        });
                })
                    ->orWhere('assigned_to_user_id', $user->id)
                    ->orWhere(function (Builder $officeVisibility) {
                        $officeVisibility->where('status', '!=', self::STATUS_READY_FOR_BAC_RESOLUTION)
                            ->whereHas('currentOffice', function (Builder $officeQuery) {
                                $officeQuery->where('code', 'BACSEC')
                                    ->orWhere('name', 'BAC Secretariat');
                            });
                    });
            });
    }

    public function scopeAssignedToBacMember(Builder $query, User $user): Builder
    {
        return $query
            ->whereIn('status', [
                self::STATUS_PENDING_BAC_MEMBER_REVIEW,
                self::STATUS_UNDER_BAC_MEMBER_REVIEW,
            ])
            ->where(function (Builder $builder) use ($user) {
                $builder->where('assigned_to_user_id', $user->id)
                    ->orWhere(function (Builder $unassigned) {
                        $unassigned->whereNull('assigned_to_user_id')
                            ->whereHas('currentOffice', function (Builder $officeQuery) {
                                $officeQuery->where('code', 'BAC')
                                    ->orWhere('name', 'Bids and Awards Committee');
                            });
                    });
            });
    }

    public function scopeReviewedByBacMember(Builder $query, User $user): Builder
    {
        return $query
            ->whereIn('status', [
                self::STATUS_ENDORSED_BY_BAC_MEMBER,
                self::STATUS_PENDING_BAC_CHAIR_REVIEW,
                'under_bac_chair_review',
                'bac_chair_reviewed',
                self::STATUS_RETURNED_BY_BAC_MEMBER,
            ])
            ->where(function (Builder $builder) use ($user) {
                $builder->where('bac_member_reviewed_by_user_id', $user->id)
                    ->orWhereHas('bacMemberReviews', fn (Builder $review) => $review->where('reviewed_by_user_id', $user->id))
                    ->orWhereHas('routingHistories', function (Builder $history) use ($user) {
                        $history->where('action_by_user_id', $user->id)
                            ->where(function (Builder $action) {
                                $action->where('action', 'like', '%BAC Member%')
                                    ->orWhereIn('status_to', [
                                        self::STATUS_ENDORSED_BY_BAC_MEMBER,
                                        self::STATUS_PENDING_BAC_CHAIR_REVIEW,
                                        self::STATUS_RETURNED_BY_BAC_MEMBER,
                                    ]);
                            });
                    });
            });
    }

    public function scopeAssignedToBacChair(Builder $query, User $user): Builder
    {
        return $query
            ->whereIn('status', [
                self::STATUS_PENDING_BAC_CHAIR_REVIEW,
                self::STATUS_UNDER_BAC_CHAIR_REVIEW,
            ])
            ->where(function (Builder $builder) use ($user) {
                $builder->where('assigned_to_user_id', $user->id)
                    ->orWhere(function (Builder $unassigned) {
                        $unassigned->whereNull('assigned_to_user_id')
                            ->whereHas('currentOffice', function (Builder $officeQuery) {
                                $officeQuery->where('code', 'BAC')
                                    ->orWhere('name', 'Bids and Awards Committee');
                            });
                    });
            });
    }

    public function scopeReviewedByBacChair(Builder $query, User $user): Builder
    {
        return $query
            ->whereIn('status', [
                self::STATUS_CONFIRMED_BY_BAC_CHAIR,
                self::STATUS_PENDING_APPROVAL,
                self::STATUS_RETURNED_BY_BAC_CHAIR,
                'approved',
            ])
            ->where(function (Builder $builder) use ($user) {
                $builder->where('bac_chair_reviewed_by_user_id', $user->id)
                    ->orWhereHas('bacChairReviews', fn (Builder $review) => $review->where('reviewed_by_user_id', $user->id))
                    ->orWhereHas('routingHistories', function (Builder $history) use ($user) {
                        $history->where('action_by_user_id', $user->id)
                            ->where(function (Builder $action) {
                                $action->where('action', 'like', '%BAC Chair%')
                                    ->orWhereIn('status_to', [
                                        self::STATUS_CONFIRMED_BY_BAC_CHAIR,
                                        self::STATUS_PENDING_APPROVAL,
                                        self::STATUS_RETURNED_BY_BAC_CHAIR,
                                    ]);
                            });
                    });
            });
    }

    public function scopeForBacChairConfirmation(Builder $query, User $user): Builder
    {
        return $query
            ->whereIn('status', [
                self::STATUS_UNDER_BAC_CHAIR_REVIEW,
                self::STATUS_READY_FOR_BAC_CHAIR_CONFIRMATION,
            ])
            ->where(function (Builder $builder) use ($user) {
                $builder->where('assigned_to_user_id', $user->id)
                    ->orWhere(function (Builder $bacOffice) {
                        $bacOffice->where('status', self::STATUS_UNDER_BAC_CHAIR_REVIEW)
                            ->whereNotNull('bac_chair_review_started_at')
                            ->whereHas('currentOffice', function (Builder $officeQuery) {
                                $officeQuery->where('code', 'BAC')
                                    ->orWhere('name', 'Bids and Awards Committee');
                            });
                    });
            });
    }

    public function scopeAssignedToApprovingAuthority(Builder $query, User $user): Builder
    {
        return $query
            ->whereIn('status', [self::STATUS_PENDING_APPROVAL, self::STATUS_UNDER_APPROVAL])
            ->where(function (Builder $builder) use ($user) {
                $builder->where('assigned_to_user_id', $user->id)
                    ->orWhere(function (Builder $officeScope) {
                        $officeScope->whereHas('currentOffice', function (Builder $office) {
                            $office->where('code', 'OMM')
                                ->orWhere('name', "Mayor's Office")
                                ->orWhere('name', 'Office of the Municipal Mayor')
                                ->orWhere('name', 'Head of the Procuring Entity')
                                ->orWhere('name', 'Approving Authority');
                        });
                    });
            });
    }

    public function scopeApprovedByApprovingAuthority(Builder $query, User $user): Builder
    {
        return $query
            ->whereIn('status', [
                self::STATUS_APPROVED,
                self::STATUS_APP_APPROVED,
                self::STATUS_READY_FOR_PO,
                self::STATUS_PO_APPROVED,
                self::STATUS_PO_COMPLETED,
                'completed',
            ])
            ->where(function (Builder $builder) use ($user) {
                $builder->where('approved_by_user_id', $user->id)
                    ->orWhereHas('documentApprovals', fn (Builder $approval) => $approval->where('approved_by_user_id', $user->id));
            });
    }
}
