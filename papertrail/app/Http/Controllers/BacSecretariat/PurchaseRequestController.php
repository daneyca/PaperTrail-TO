<?php

namespace App\Http\Controllers\BacSecretariat;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DocumentRoutingHistory;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\PurchaseRequestValidation;
use App\Models\SupplementalApp;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SystemNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PurchaseRequestController extends Controller
{
    public function index(Request $request): View
    {
        AuditLogger::log('Purchase Requests', 'Purchase Requests Page Viewed', 'BAC Secretariat viewed Purchase Requests.');

        $query = ProcurementDocument::query()
            ->purchaseRequestsForBacSecretariat($request->user())
            ->with(['submittingOffice', 'currentOffice', 'assignedTo', 'appConsolidation', 'latestBacResolution']);

        $this->applyFilters($query, $request);

        return view('bac-secretariat.pr.index', [
            'documents' => $query->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary($request->user()),
            'filters' => $request->only(['search', 'fiscal_year', 'pr_status', 'status', 'office_id', 'date_from', 'date_to']),
            'fiscalYears' => ProcurementDocument::query()->whereIn('document_type', ['PR', 'Purchase Request'])->select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
            'offices' => Office::orderBy('name')->get(),
            'prStatuses' => [
                ProcurementDocument::PR_STATUS_SUBMITTED,
                ProcurementDocument::PR_STATUS_RECEIVED,
                ProcurementDocument::PR_STATUS_UNDER_VALIDATION,
                ProcurementDocument::PR_STATUS_RETURNED,
                ProcurementDocument::PR_STATUS_ROUTED_TO_BUDGET,
            ],
            'currentStatuses' => $this->workflowStatuses(),
        ]);
    }

    public function show(Request $request, ProcurementDocument $document): View|RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('Purchase Requests', 'Unauthorized Access Attempt', 'BAC Secretariat attempted to view a PR outside their scope.', $document, null, null, 'warning');

            return redirect()
                ->route('bac-secretariat.pr.index')
                ->with('error', 'You are not authorized to view this Purchase Request.');
        }

        AuditLogger::log('Purchase Requests', 'Purchase Request Detail Viewed', 'BAC Secretariat viewed Purchase Request detail.', $document);

        $relations = [
            'submittingOffice',
            'currentOffice',
            'submittedBy',
            'assignedTo',
            'prReceivedBy',
            'prValidatedBy',
            'appConsolidation',
            'appItem.office',
            'supplementalApps.items',
            'supplementalApps.preparedBy',
            'supplementalApps.acceptedBy',
            'purchaseRequestItems.appItem',
            'purchaseRequestValidations.validatedBy',
            'latestBacResolution',
            'routingHistories.actionBy',
            'routingHistories.fromOffice',
            'routingHistories.toOffice',
        ];

        if (Schema::hasTable('document_attachments')) {
            $relations[] = 'attachments.uploadedBy';
        }

        $document->load($relations);
        $supplementalApp = $document->supplementalApps
            ->sortByDesc(fn (SupplementalApp $app) => $app->accepted_at ?? $app->updated_at)
            ->first();

        return view('bac-secretariat.pr.show', [
            'document' => $document,
            'latestValidation' => $document->purchaseRequestValidations->sortByDesc(fn (PurchaseRequestValidation $validation) => $validation->completed_at ?? $validation->created_at)->first(),
            'hasAttachmentsTable' => Schema::hasTable('document_attachments'),
            'hasPpmpAppReference' => $this->hasPpmpAppReference($document),
            'supplementalApp' => $supplementalApp,
            'acceptedSupplementalApp' => $document->supplementalApps->first(fn (SupplementalApp $app) => $app->isAccepted()),
            'activities' => AuditLog::query()
                ->where('auditable_type', ProcurementDocument::class)
                ->where('auditable_id', $document->id)
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }

    public function print(Request $request, ProcurementDocument $document): View|RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('Purchase Requests', 'Unauthorized Print Access Attempt', 'BAC Secretariat attempted to print a PR outside their scope.', $document, null, null, 'warning');

            return redirect()
                ->route('bac-secretariat.pr.index')
                ->with('error', 'You are not authorized to print this Purchase Request.');
        }

        AuditLogger::log('Purchase Requests', 'Purchase Request Print Viewed', 'BAC Secretariat opened the official PR print view.', $document);

        return view('head-office.pr.print', [
            'document' => $document->load(['submittingOffice', 'submittedBy', 'preparedBy', 'purchaseRequestItems']),
            'backUrl' => route('bac-secretariat.pr.show', $document),
        ]);
    }

    public function acknowledge(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document) || ! in_array($document->status, [
            ProcurementDocument::STATUS_PR_SUBMITTED,
            ProcurementDocument::STATUS_SUBMITTED_TO_BAC_SECRETARIAT,
        ], true)) {
            AuditLogger::log('Purchase Requests', 'Unauthorized Access Attempt', 'Invalid PR acknowledgement attempt.', $document, null, null, 'warning');

            return back()->with('error', 'This Purchase Request cannot be acknowledged.');
        }

        DB::transaction(function () use ($request, $document) {
            $oldStatus = $document->status;
            $bacOffice = $this->bacSecretariatOffice();

            $document->update([
                'status' => ProcurementDocument::STATUS_PR_RECEIVED_BY_BAC_SECRETARIAT,
                'pr_status' => ProcurementDocument::PR_STATUS_RECEIVED,
                'pr_received_by_user_id' => $request->user()->id,
                'pr_received_at' => now(),
                'stage' => ProcurementDocument::STAGE_BAC_SECRETARIAT_PR_VALIDATION,
                'current_office_id' => $bacOffice?->id ?? $document->current_office_id,
                'assigned_to_user_id' => $request->user()->id,
            ]);

            $this->recordRouting($document, $request->user(), 'PR Receipt Acknowledged', $oldStatus, $document->status, 'Purchase Request receipt acknowledged.', $document->getOriginal('current_office_id'), $document->current_office_id);
            $this->notifySubmitter($document, 'Purchase Request Received', "Purchase Request {$document->tracking_number} was received by BAC Secretariat.", SystemNotification::TYPE_INFO);
            AuditLogger::log('Purchase Requests', 'PR Receipt Acknowledged', 'BAC Secretariat acknowledged PR receipt.', $document, ['status' => $oldStatus], ['status' => $document->status]);
        });

        return back()->with('status', 'Purchase Request receipt acknowledged.');
    }

    public function startValidation(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document) || $document->status !== ProcurementDocument::STATUS_PR_RECEIVED_BY_BAC_SECRETARIAT) {
            AuditLogger::log('Purchase Requests', 'Unauthorized Access Attempt', 'Invalid PR validation start attempt.', $document, null, null, 'warning');

            return back()->with('error', 'This Purchase Request cannot be started for validation.');
        }

        DB::transaction(function () use ($request, $document) {
            $oldStatus = $document->status;

            $document->update([
                'status' => ProcurementDocument::STATUS_UNDER_PR_VALIDATION,
                'pr_status' => ProcurementDocument::PR_STATUS_UNDER_VALIDATION,
                'pr_validation_started_at' => now(),
                'stage' => ProcurementDocument::STAGE_BAC_SECRETARIAT_PR_VALIDATION,
                'assigned_to_user_id' => $request->user()->id,
            ]);

            $document->purchaseRequestValidations()->create([
                'validated_by_user_id' => $request->user()->id,
                'validation_status' => PurchaseRequestValidation::STATUS_UNDER_VALIDATION,
                'started_at' => now(),
            ]);

            $this->recordRouting($document, $request->user(), 'PR Validation Started', $oldStatus, $document->status, 'BAC Secretariat started PR validation.', $document->getOriginal('current_office_id'), $document->current_office_id);
            AuditLogger::log('Purchase Requests', 'PR Validation Started', 'BAC Secretariat started PR validation.', $document, ['status' => $oldStatus], ['status' => $document->status]);
        });

        return back()->with('status', 'Purchase Request validation started.');
    }

    public function return(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)
            || !in_array($document->status, [
                ProcurementDocument::STATUS_PR_SUBMITTED,
                ProcurementDocument::STATUS_SUBMITTED_TO_BAC_SECRETARIAT,
                ProcurementDocument::STATUS_PR_RECEIVED_BY_BAC_SECRETARIAT,
                ProcurementDocument::STATUS_UNDER_PR_VALIDATION,
            ], true)) {
            AuditLogger::log('Purchase Requests', 'Unauthorized Access Attempt', 'Invalid PR return attempt.', $document, null, null, 'warning');

            return back()->with('error', 'This Purchase Request cannot be returned.');
        }

        $validated = $request->validate([
            'comments' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $document, $validated) {
            $oldStatus = $document->status;
            $fromOffice = $document->current_office_id;

            $document->update([
                'status' => ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
                'pr_status' => ProcurementDocument::PR_STATUS_RETURNED,
                'stage' => ProcurementDocument::STAGE_RETURNED_TO_REQUESTING_OFFICE,
                'current_office_id' => $document->submitting_office_id,
                'assigned_to_user_id' => $document->submitted_by_user_id,
                'pr_remarks' => $validated['comments'],
                'remarks' => $validated['comments'],
            ]);

            $document->purchaseRequestValidations()->create([
                'validated_by_user_id' => $request->user()->id,
                'validation_status' => PurchaseRequestValidation::STATUS_RETURNED,
                'remarks' => $validated['comments'],
                'started_at' => $document->pr_validation_started_at,
                'completed_at' => now(),
            ]);

            $this->recordRouting($document, $request->user(), 'PR Returned by BAC Secretariat', $oldStatus, $document->status, $validated['comments'], $fromOffice, $document->current_office_id);
            $this->notifySubmitter($document, 'Purchase Request Returned', "Purchase Request {$document->tracking_number} was returned for correction or clarification.", SystemNotification::TYPE_WARNING);
            AuditLogger::log('Purchase Requests', 'PR Returned by BAC Secretariat', 'BAC Secretariat returned a Purchase Request.', $document, ['status' => $oldStatus], ['status' => $document->status], 'warning');
        });

        return redirect()
            ->route('bac-secretariat.pr.index')
            ->with('status', 'Purchase Request returned.');
    }

    public function routeBudget(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document) || $document->status !== ProcurementDocument::STATUS_UNDER_PR_VALIDATION) {
            AuditLogger::log('Purchase Requests', 'Unauthorized Access Attempt', 'Invalid PR route to Budget attempt.', $document, null, null, 'warning');

            return back()->with('error', 'Only PRs under validation can be routed to Budget Office.');
        }

        $validated = $request->validate([
            'app_reference_checked' => ['accepted'],
            'item_details_checked' => ['accepted'],
            'attachments_checked' => ['accepted'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $this->hasPpmpAppReference($document) && ! $this->hasAcceptedSupplementalApp($document)) {
            return back()->with('error', 'APP reference or accepted Supplemental APP is required before routing this Purchase Request.');
        }

        if ($document->purchaseRequestItems()->count() < 1) {
            return back()->with('error', 'Purchase Request must have at least one item before routing.');
        }

        if ((float) $document->total_amount <= 0) {
            return back()->with('error', 'Purchase Request total amount must be greater than zero.');
        }

        $budgetOffice = $this->budgetOffice();
        $budgetUser = $this->budgetOfficer();

        if (!$budgetOffice || !$budgetUser) {
            return back()->with('error', 'Budget Office routing target is not configured.');
        }

        DB::transaction(function () use ($request, $document, $validated, $budgetOffice, $budgetUser) {
            $oldStatus = $document->status;
            $fromOffice = $document->current_office_id;

            $document->update([
                'status' => ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW,
                'pr_status' => ProcurementDocument::PR_STATUS_ROUTED_TO_BUDGET,
                'stage' => ProcurementDocument::STAGE_BUDGET_REVIEW,
                'current_office_id' => $budgetOffice->id,
                'assigned_to_user_id' => $budgetUser->id,
                'pr_validated_by_user_id' => $request->user()->id,
                'pr_validated_at' => now(),
                'pr_remarks' => $validated['remarks'] ?? $document->pr_remarks,
            ]);

            $document->purchaseRequestValidations()->create([
                'validated_by_user_id' => $request->user()->id,
                'validation_status' => PurchaseRequestValidation::STATUS_ROUTED_TO_BUDGET,
                'app_reference_checked' => true,
                'attachments_checked' => true,
                'item_details_checked' => true,
                'remarks' => $validated['remarks'] ?? null,
                'started_at' => $document->pr_validation_started_at,
                'completed_at' => now(),
            ]);

            $this->recordRouting($document, $request->user(), 'PR Routed to Budget Office', $oldStatus, $document->status, $validated['remarks'] ?? 'Purchase Request routed to Budget Office.', $fromOffice, $budgetOffice->id);
            SystemNotificationService::notify($budgetUser, 'Purchase Request Routed to Budget Office', "Purchase Request {$document->tracking_number} is ready for budget review.", SystemNotification::TYPE_INFO, 'Purchase Requests', $document, route('budget.pending-review.show', $document));
            AuditLogger::log('Purchase Requests', 'PR Routed to Budget Office', 'BAC Secretariat routed a Purchase Request to Budget Office.', $document, ['status' => $oldStatus], ['status' => $document->status]);
        });

        return redirect()
            ->route('bac-secretariat.pr.show', $document)
            ->with('status', 'Purchase Request routed to Budget Office.');
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();
            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('tracking_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhereHas('submittingOffice', fn (Builder $office) => $office->where('name', 'like', "%{$search}%"));
            });
        });

        foreach (['fiscal_year', 'pr_status'] as $field) {
            $query->when($request->filled($field), fn (Builder $builder) => $builder->where($field, $request->input($field)));
        }

        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', function (Builder $builder) use ($request): void {
            $status = (string) $request->input('status');

            if ($status === 'submitted') {
                $this->applySubmittedPrCopyScope($builder);

                return;
            }

            $builder->whereIn('status', [$status]);
        });

        $query->when($request->filled('office_id'), fn (Builder $builder) => $builder->where('submitting_office_id', $request->input('office_id')));
        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('submitted_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('submitted_at', '<=', $request->date('date_to')));
    }

    private function canAccess(User $user, ProcurementDocument $document): bool
    {
        return ProcurementDocument::query()
            ->purchaseRequestsForBacSecretariat($user)
            ->whereKey($document->id)
            ->exists();
    }

    private function summary(User $user): array
    {
        $base = ProcurementDocument::query()->purchaseRequestsForBacSecretariat($user);
        $submitted = clone $base;
        $this->applySubmittedPrCopyScope($submitted);

        return [
            'submitted' => $submitted->count(),
            'underValidation' => (clone $base)->where('status', ProcurementDocument::STATUS_UNDER_PR_VALIDATION)->count(),
            'routedToBudget' => (clone $base)->where('pr_status', ProcurementDocument::PR_STATUS_ROUTED_TO_BUDGET)->count(),
            'returned' => (clone $base)->where('pr_status', ProcurementDocument::PR_STATUS_RETURNED)->count(),
        ];
    }

    private function applySubmittedPrCopyScope(Builder $query): void
    {
        $query->where(function (Builder $submitted): void {
            $submitted->whereIn('status', $this->submittedPrStatuses())
                ->orWhereHas('bacResolutions');
        });
    }

    private function submittedPrStatuses(): array
    {
        return [
            ProcurementDocument::STATUS_PR_SUBMITTED,
            ProcurementDocument::STATUS_SUBMITTED_TO_BAC_SECRETARIAT,
            ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION,
            ProcurementDocument::STATUS_BAC_RESOLUTION_CREATED,
        ];
    }

    private function workflowStatuses(): array
    {
        return [
            ProcurementDocument::STATUS_SUBMITTED_TO_BAC_SECRETARIAT,
            ProcurementDocument::STATUS_PR_SUBMITTED,
            ProcurementDocument::STATUS_PR_RECEIVED_BY_BAC_SECRETARIAT,
            ProcurementDocument::STATUS_UNDER_PR_VALIDATION,
            ProcurementDocument::STATUS_NO_PPMP_RECORD_FOUND,
            ProcurementDocument::STATUS_PENDING_SUPPLEMENTAL_APP,
            ProcurementDocument::STATUS_SUPPLEMENTAL_APP_CREATED,
            ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION,
            ProcurementDocument::STATUS_BAC_RESOLUTION_CREATED,
            ProcurementDocument::STATUS_BAC_RESOLUTION_RETURNED_TO_END_USER,
            ProcurementDocument::STATUS_READY_FOR_RFQ,
            ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
            ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW,
            ProcurementDocument::STATUS_UNDER_BUDGET_REVIEW,
            ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW,
            ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW,
            ProcurementDocument::STATUS_READY_FOR_DOCUMENT_ROUTING,
            ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW,
            ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW,
            ProcurementDocument::STATUS_PENDING_APPROVAL,
            'approved',
        ];
    }

    private function hasPpmpAppReference(ProcurementDocument $document): bool
    {
        return filled($document->app_consolidation_id)
            || filled($document->app_item_id)
            || $document->purchaseRequestItems->contains(fn ($item) => filled($item->app_item_id));
    }

    private function hasAcceptedSupplementalApp(ProcurementDocument $document): bool
    {
        if ($document->relationLoaded('supplementalApps')) {
            return $document->supplementalApps->contains(fn (SupplementalApp $app) => $app->isAccepted());
        }

        return $document->acceptedSupplementalApps()->exists();
    }

    private function recordRouting(ProcurementDocument $document, User $user, string $action, ?string $fromStatus, string $toStatus, ?string $comments = null, ?int $fromOfficeId = null, ?int $toOfficeId = null): void
    {
        DocumentRoutingHistory::create([
            'procurement_document_id' => $document->id,
            'action_by_user_id' => $user->id,
            'from_office_id' => $fromOfficeId,
            'to_office_id' => $toOfficeId,
            'action' => $action,
            'status_from' => $fromStatus,
            'status_to' => $toStatus,
            'comments' => $comments,
            'action_at' => now(),
        ]);
    }

    private function notifySubmitter(ProcurementDocument $document, string $title, string $message, string $type): void
    {
        $document->loadMissing(['submittedBy', 'preparedBy']);

        $recipient = $document->submittedBy ?: $document->preparedBy;
        $actionUrl = $type === SystemNotification::TYPE_WARNING
            ? route('head-office.returned.show', $document)
            : route('head-office.pr.show', $document);

        SystemNotificationService::notify($recipient, $title, $message, $type, 'Purchase Requests', $document, $actionUrl);
    }

    private function bacSecretariatOffice(): ?Office
    {
        return Office::where('code', 'BACSEC')->orWhere('name', 'BAC Secretariat')->first();
    }

    private function budgetOffice(): ?Office
    {
        return Office::where('code', 'MBO')->orWhere('name', 'Budget Office')->first();
    }

    private function budgetOfficer(): ?User
    {
        return User::where(function (Builder $query) {
                $query->where('role', User::ROLE_BUDGET)
                    ->orWhere('user_id', 'BUDGET-001');
            })
            ->where('status', User::STATUS_ACTIVE)
            ->first();
    }
}
