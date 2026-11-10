<?php

namespace App\Http\Controllers\PrNumbering;

use App\Http\Controllers\Controller;
use App\Models\DocumentRoutingHistory;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\OfficialPrNumberService;
use App\Services\SvpChainService;
use App\Services\SystemNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PrNumberingController extends Controller
{
    public function index(Request $request): View
    {
        AuditLogger::log('PR Number Assignment', 'Pending PR Number Requests Viewed', 'Procurement Controller viewed pending PR number requests.');

        $query = $this->pendingQuery()
            ->with(['submittingOffice', 'submittedBy', 'currentOffice']);

        $this->applyFilters($query, $request);

        return view('pr-numbering.pending.index', [
            'documents' => $query->latest('pr_no_requested_at')->paginate(10)->withQueryString(),
            'pendingRfqs' => Rfq::query()
                ->where('status', Rfq::STATUS_PENDING_CONTROL)
                ->with(['sourcePrDocument.submittingOffice', 'preparedBy.assignedOffice'])
                ->latest('submitted_at')
                ->get(),
            'pendingPurchaseOrders' => PurchaseOrder::query()
                ->where('status', PurchaseOrder::STATUS_PENDING_CONTROL)
                ->with(['sourcePrDocument.submittingOffice', 'preparedBy.assignedOffice'])
                ->latest('submitted_at')
                ->get(),
            'summary' => $this->summary($request->user()),
            'filters' => $request->only(['search', 'fiscal_year', 'office_id', 'date_from', 'date_to']),
            'fiscalYears' => ProcurementDocument::query()
                ->whereIn('document_type', ['PR', 'Purchase Request'])
                ->select('fiscal_year')
                ->whereNotNull('fiscal_year')
                ->distinct()
                ->orderByDesc('fiscal_year')
                ->pluck('fiscal_year'),
            'offices' => Office::orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, ProcurementDocument $document): View|RedirectResponse
    {
        if (! $this->canViewPending($document)) {
            AuditLogger::log('PR Number Assignment', 'Unauthorized PR Number Access Attempt', 'Invalid pending PR number request access attempt.', $document, null, null, 'warning');

            return redirect()
                ->route('pr-numbering.pending.index')
                ->with('error', 'This Purchase Request is not pending PR number assignment.');
        }

        AuditLogger::log('PR Number Assignment', 'Pending PR Number Detail Viewed', 'Procurement Controller viewed a pending Purchase Request.', $document);

        $document->load([
            'submittingOffice',
            'submittedBy',
            'preparedBy',
            'currentOffice',
            'assignedTo',
            'purchaseRequestItems',
            'routingHistories.actionBy',
            'routingHistories.fromOffice',
            'routingHistories.toOffice',
        ]);

        return view('pr-numbering.pending.show', ['document' => $document]);
    }

    public function assign(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (! $this->canViewPending($document)) {
            AuditLogger::log('PR Number Assignment', 'Unauthorized PR Number Access Attempt', 'Invalid PR number assignment attempt.', $document, null, null, 'warning');

            return back()->with('error', 'This Purchase Request cannot be assigned a PR number.');
        }

        if (is_string($request->input('pr_no'))) {
            $request->merge(['pr_no' => trim($request->input('pr_no'))]);
        }
        $validated = $request->validate([
            'pr_no' => [
                'bail',
                'required',
                'string',
                'max:120',
                function ($attribute, $value, $fail) use ($document, $request) {
                    $error = app(OfficialPrNumberService::class)->validationError($document, $value, $request->input('pr_date'));
                    if ($error) {
                        $fail($error);
                    }
                },
                Rule::unique('procurement_documents', 'pr_no')->ignore($document->id),
            ],
            'pr_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $document, $validated) {
            $oldValues = $document->only(['status', 'pr_number_status', 'pr_no', 'pr_date', 'current_office_id', 'assigned_to_user_id']);
            $oldStatus = $document->status;
            $fromOffice = $document->current_office_id;
            $returnTarget = $this->requestingOfficeReturnTarget($document);
            $requestingOffice = $returnTarget['office'];
            $requestingUser = $returnTarget['user'];
            $requestingOfficeId = $requestingOffice?->id ?? $document->submitting_office_id;

            $document->update([
                'pr_no' => trim($validated['pr_no']),
                'pr_number_status' => ProcurementDocument::PR_NUMBER_STATUS_ASSIGNED,
                'pr_no_assigned_by_user_id' => $request->user()->id,
                'pr_no_assigned_at' => now(),
                'pr_number_remarks' => $validated['remarks'] ?? null,
                'status' => ProcurementDocument::STATUS_PR_NUMBER_ASSIGNED_RETURNED_TO_END_USER,
                'pr_status' => null,
                'stage' => ProcurementDocument::STAGE_RETURNED_TO_REQUESTING_OFFICE,
                'submitted_at' => $document->submitted_at ?? now(),
                'pr_date' => $validated['pr_date'],
                'current_office_id' => $requestingOfficeId,
                'assigned_to_user_id' => $requestingUser?->id,
                'bac_secretariat_status' => null,
            ]);

            $this->updateSequence($document->fresh(), $request->user());

            $this->recordRouting(
                $document,
                $request->user(),
                'PR Number Assigned and Returned to Requesting Office',
                $oldStatus,
                ProcurementDocument::STATUS_PR_NUMBER_ASSIGNED_RETURNED_TO_END_USER,
                $validated['remarks'] ?? 'Official PR number assigned. Purchase Request returned to the requesting office for submission to BAC Secretariat.',
                $fromOffice,
                $requestingOfficeId,
            );

            if ($requestingUser) {
                SystemNotificationService::notify(
                    $requestingUser,
                    'PR Number Assigned',
                    "Purchase Request {$document->tracking_number} has been assigned an official PR number and returned to your office.",
                    SystemNotification::TYPE_SUCCESS,
                    'PR Number Assignment',
                    $document,
                    route('head-office.pr.show', $document),
                );
            }

            AuditLogger::log('PR Number Assignment', 'PR Number Assigned', 'Procurement Controller assigned an official PR number and returned the PR to the requesting office.', $document, $oldValues, $document->fresh()->only(['status', 'pr_number_status', 'pr_no', 'pr_date', 'current_office_id', 'assigned_to_user_id']));

            app(SvpChainService::class)->findOrCreateFromPr($document->refresh(), $request->user(), 'PR number assigned and returned to Requesting Office', $validated['remarks'] ?? null);
        });

        return redirect()
            ->route('pr-numbering.assigned.index')
            ->with('status', 'Official PR number assigned and returned to the requesting office.');
    }

    public function return(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (! $this->canViewPending($document)) {
            AuditLogger::log('PR Number Assignment', 'Unauthorized PR Number Access Attempt', 'Invalid PR number return attempt.', $document, null, null, 'warning');

            return back()->with('error', 'This Purchase Request cannot be returned.');
        }

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $document, $validated) {
            $oldValues = $document->only(['status', 'pr_number_status', 'current_office_id', 'assigned_to_user_id']);
            $oldStatus = $document->status;
            $fromOffice = $document->current_office_id;

            $document->update([
                'status' => ProcurementDocument::STATUS_RETURNED_BY_PR_NUMBERING_STAFF,
                'pr_number_status' => ProcurementDocument::PR_NUMBER_STATUS_RETURNED,
                'stage' => ProcurementDocument::STAGE_RETURNED_TO_REQUESTING_OFFICE,
                'current_office_id' => $document->submitting_office_id,
                'assigned_to_user_id' => $document->submitted_by_user_id,
                'returned_at' => now(),
                'pr_number_remarks' => $validated['remarks'],
                'remarks' => $validated['remarks'],
            ]);

            $this->recordRouting(
                $document,
                $request->user(),
                'PR Returned by Procurement Controller',
                $oldStatus,
                ProcurementDocument::STATUS_RETURNED_BY_PR_NUMBERING_STAFF,
                $validated['remarks'],
                $fromOffice,
                $document->current_office_id,
            );

            SystemNotificationService::notify(
                $document->submittedBy,
                'Purchase Request Returned Before PR Number Assignment',
                "Purchase Request {$document->tracking_number} was returned by the Procurement Controller for correction.",
                SystemNotification::TYPE_WARNING,
                'PR Number Assignment',
                $document,
                route('head-office.returned.show', $document),
            );

            AuditLogger::log('PR Number Assignment', 'PR Returned by Procurement Controller', 'Procurement Controller returned a Purchase Request before official PR number assignment.', $document, $oldValues, $document->fresh()->only(['status', 'pr_number_status', 'current_office_id', 'assigned_to_user_id']), 'warning');

            app(SvpChainService::class)->findOrCreateFromPr($document->refresh(), $request->user(), 'PR returned by the Procurement Controller', $validated['remarks']);
        });

        return redirect()
            ->route('pr-numbering.pending.index')
            ->with('status', 'Purchase Request returned to the requesting office.');
    }

    public function showRfq(Rfq $rfq): View|RedirectResponse
    {
        if ($rfq->status !== Rfq::STATUS_PENDING_CONTROL) {
            return redirect()
                ->route('pr-numbering.pending.index')
                ->with('error', 'This RFQ is not pending procurement control.');
        }

        $rfq->load(['sourcePrDocument.submittingOffice', 'sourceBacResolution', 'preparedBy.assignedOffice', 'submittedBy', 'items']);

        AuditLogger::log('Procurement Control', 'Pending RFQ Viewed', 'Procurement Controller viewed an RFQ awaiting its official number and date.', $rfq);

        return view('pr-numbering.pending.rfq-show', ['rfq' => $rfq]);
    }

    public function assignRfq(Request $request, Rfq $rfq): RedirectResponse
    {
        if ($rfq->status !== Rfq::STATUS_PENDING_CONTROL) {
            return back()->with('error', 'This RFQ cannot be assigned an official number.');
        }

        $validated = $request->validate([
            'rfq_number' => ['required', 'string', 'max:100', Rule::unique('rfqs', 'rfq_number')->ignore($rfq->id)],
            'rfq_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $rfq, $validated) {
            $oldValues = $rfq->only(['rfq_number', 'rfq_date', 'status']);
            $originator = $rfq->preparedBy ?: $rfq->submittedBy;

            $rfq->update([
                'rfq_number' => trim($validated['rfq_number']),
                'rfq_date' => $validated['rfq_date'],
                'status' => Rfq::STATUS_SUBMITTED,
                'control_assigned_by_user_id' => $request->user()->id,
                'control_assigned_at' => now(),
                'control_remarks' => $validated['remarks'] ?? null,
            ]);

            if ($originator) {
                SystemNotificationService::notify(
                    $originator,
                    'RFQ Number and Date Assigned',
                    "RFQ {$rfq->document_reference_number} now has its official quotation number and date and has been returned to your office.",
                    SystemNotification::TYPE_SUCCESS,
                    'Procurement Control',
                    $rfq,
                    route('head-office.rfqs.show', $rfq),
                );
            }

            AuditLogger::log('Procurement Control', 'RFQ Control Details Assigned', 'Procurement Controller assigned the official RFQ quotation number and date.', $rfq, $oldValues, $rfq->fresh()->only(['rfq_number', 'rfq_date', 'status']));
        });

        return redirect()
            ->route('pr-numbering.pending.index')
            ->with('status', 'Official RFQ quotation number and date assigned. The RFQ was returned to its requesting office.');
    }

    public function returnRfq(Request $request, Rfq $rfq): RedirectResponse
    {
        if ($rfq->status !== Rfq::STATUS_PENDING_CONTROL) {
            return back()->with('error', 'This RFQ cannot be returned.');
        }

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        DB::transaction(function () use ($rfq, $validated) {
            $originator = $rfq->preparedBy ?: $rfq->submittedBy;
            $oldStatus = $rfq->status;

            $rfq->update([
                'status' => Rfq::STATUS_RETURNED,
                'control_remarks' => $validated['remarks'],
                'remarks' => $validated['remarks'],
            ]);

            if ($originator) {
                SystemNotificationService::notify(
                    $originator,
                    'RFQ Returned for Correction',
                    "RFQ {$rfq->document_reference_number} was returned by the Procurement Controller.",
                    SystemNotification::TYPE_WARNING,
                    'Procurement Control',
                    $rfq,
                    route('head-office.rfqs.show', $rfq),
                );
            }

            AuditLogger::log('Procurement Control', 'RFQ Returned', 'Procurement Controller returned an RFQ for correction.', $rfq, ['status' => $oldStatus], ['status' => Rfq::STATUS_RETURNED, 'remarks' => $validated['remarks']], 'warning');
        });

        return redirect()
            ->route('pr-numbering.pending.index')
            ->with('status', 'RFQ returned to the requesting office for correction.');
    }

    public function showPurchaseOrder(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_PENDING_CONTROL) {
            return redirect()
                ->route('pr-numbering.pending.index')
                ->with('error', 'This Purchase Order is not pending procurement control.');
        }

        $purchaseOrder->load(['sourcePrDocument.submittingOffice', 'preparedBy.assignedOffice', 'submittedBy', 'items']);

        AuditLogger::log('Procurement Control', 'Pending Purchase Order Viewed', 'Procurement Controller viewed a Purchase Order awaiting its official number and date.', $purchaseOrder);

        return view('pr-numbering.pending.purchase-order-show', ['purchaseOrder' => $purchaseOrder]);
    }

    public function assignPurchaseOrder(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_PENDING_CONTROL) {
            return back()->with('error', 'This Purchase Order cannot be assigned an official number.');
        }

        $validated = $request->validate([
            'po_number' => ['required', 'string', 'max:100', Rule::unique('purchase_orders', 'po_number')->ignore($purchaseOrder->id)],
            'po_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $purchaseOrder, $validated) {
            $purchaseOrder->loadMissing(['sourcePrDocument', 'preparedBy', 'submittedBy', 'procurementDocument']);
            $oldValues = $purchaseOrder->only(['po_number', 'po_date', 'status']);
            $originator = $purchaseOrder->preparedBy ?: $purchaseOrder->submittedBy;
            $officeId = $purchaseOrder->sourcePrDocument?->submitting_office_id ?: $originator?->office_id;

            $purchaseOrder->update([
                'po_number' => trim($validated['po_number']),
                'po_date' => $validated['po_date'],
                'status' => PurchaseOrder::STATUS_SUBMITTED,
                'control_assigned_by_user_id' => $request->user()->id,
                'control_assigned_at' => now(),
                'control_remarks' => $validated['remarks'] ?? null,
            ]);

            $document = $purchaseOrder->procurementDocument ?: ProcurementDocument::create([
                'tracking_number' => trim($validated['po_number']),
                'document_type' => 'PO',
                'title' => 'Purchase Order '.trim($validated['po_number']),
                'description' => $purchaseOrder->remarks,
                'fiscal_year' => $purchaseOrder->fiscal_year,
                'submitting_office_id' => $officeId,
                'submitted_by_user_id' => $originator?->id,
                'current_office_id' => $officeId,
                'assigned_to_user_id' => $originator?->id,
                'status' => ProcurementDocument::STATUS_PO_PREPARED,
                'stage' => ProcurementDocument::STAGE_PURCHASE_ORDER_PREPARATION,
                'priority' => 'normal',
                'total_amount' => $purchaseOrder->total_amount,
                'submitted_at' => now(),
            ]);

            $document->update([
                'tracking_number' => trim($validated['po_number']),
                'title' => 'Purchase Order '.trim($validated['po_number']),
                'status' => ProcurementDocument::STATUS_PO_PREPARED,
                'stage' => ProcurementDocument::STAGE_PURCHASE_ORDER_PREPARATION,
                'current_office_id' => $officeId,
                'assigned_to_user_id' => $originator?->id,
                'total_amount' => $purchaseOrder->total_amount,
            ]);

            $purchaseOrder->update(['procurement_document_id' => $document->id]);

            if ($originator) {
                SystemNotificationService::notify(
                    $originator,
                    'PO Number and Date Assigned',
                    "Purchase Order {$purchaseOrder->document_reference_number} now has its official PO number and date and has been returned to your office.",
                    SystemNotification::TYPE_SUCCESS,
                    'Procurement Control',
                    $purchaseOrder,
                    route('head-office.purchase-orders.show', $purchaseOrder),
                );
            }

            AuditLogger::log('Procurement Control', 'PO Control Details Assigned', 'Procurement Controller assigned the official Purchase Order number and date.', $purchaseOrder, $oldValues, $purchaseOrder->fresh()->only(['po_number', 'po_date', 'status']));

            app(SvpChainService::class)->linkPurchaseOrder($purchaseOrder->refresh(), $request->user(), 'Official PO number and date assigned; returned to requesting office');
        });

        return redirect()
            ->route('pr-numbering.pending.index')
            ->with('status', 'Official PO number and date assigned. The Purchase Order was returned to its requesting office.');
    }

    public function returnPurchaseOrder(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_PENDING_CONTROL) {
            return back()->with('error', 'This Purchase Order cannot be returned.');
        }

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        DB::transaction(function () use ($purchaseOrder, $validated) {
            $originator = $purchaseOrder->preparedBy ?: $purchaseOrder->submittedBy;
            $oldStatus = $purchaseOrder->status;

            $purchaseOrder->update([
                'status' => PurchaseOrder::STATUS_RETURNED,
                'control_remarks' => $validated['remarks'],
                'remarks' => $validated['remarks'],
                'returned_at' => now(),
            ]);

            if ($originator) {
                SystemNotificationService::notify(
                    $originator,
                    'Purchase Order Returned for Correction',
                    "Purchase Order {$purchaseOrder->document_reference_number} was returned by the Procurement Controller.",
                    SystemNotification::TYPE_WARNING,
                    'Procurement Control',
                    $purchaseOrder,
                    route('head-office.purchase-orders.show', $purchaseOrder),
                );
            }

            AuditLogger::log('Procurement Control', 'Purchase Order Returned', 'Procurement Controller returned a Purchase Order for correction.', $purchaseOrder, ['status' => $oldStatus], ['status' => PurchaseOrder::STATUS_RETURNED, 'remarks' => $validated['remarks']], 'warning');
        });

        return redirect()
            ->route('pr-numbering.pending.index')
            ->with('status', 'Purchase Order returned to the requesting office for correction.');
    }

    public function assigned(Request $request): View
    {
        AuditLogger::log('Procurement Control', 'Assignment History Viewed', 'Procurement Controller viewed assigned PR, RFQ, and PO numbers and dates.');

        $query = ProcurementDocument::query()
            ->whereIn('document_type', ['PR', 'Purchase Request'])
            ->where('pr_number_status', ProcurementDocument::PR_NUMBER_STATUS_ASSIGNED)
            ->with(['submittingOffice', 'submittedBy', 'currentOffice', 'prNoAssignedBy']);

        $this->applyFilters($query, $request);

        return view('pr-numbering.assigned.index', [
            'documents' => $query->latest('pr_no_assigned_at')->paginate(10)->withQueryString(),
            'assignedRfqs' => Rfq::query()
                ->whereNotNull('control_assigned_at')
                ->with(['sourcePrDocument.submittingOffice', 'controlAssignedBy'])
                ->latest('control_assigned_at')
                ->get(),
            'assignedPurchaseOrders' => PurchaseOrder::query()
                ->whereNotNull('control_assigned_at')
                ->with(['sourcePrDocument.submittingOffice', 'controlAssignedBy'])
                ->latest('control_assigned_at')
                ->get(),
            'summary' => $this->summary($request->user()),
            'filters' => $request->only(['search', 'fiscal_year', 'office_id', 'date_from', 'date_to']),
            'fiscalYears' => ProcurementDocument::query()
                ->whereIn('document_type', ['PR', 'Purchase Request'])
                ->select('fiscal_year')
                ->whereNotNull('fiscal_year')
                ->distinct()
                ->orderByDesc('fiscal_year')
                ->pluck('fiscal_year'),
            'offices' => Office::orderBy('name')->get(),
        ]);
    }

    private function pendingQuery(): Builder
    {
        return ProcurementDocument::query()
            ->whereIn('document_type', ['PR', 'Purchase Request'])
            ->where('status', ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT)
            ->where('pr_number_status', ProcurementDocument::PR_NUMBER_STATUS_PENDING_ASSIGNMENT);
    }

    private function canViewPending(ProcurementDocument $document): bool
    {
        return in_array($document->document_type, ['PR', 'Purchase Request'], true)
            && $document->status === ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT
            && $document->pr_number_status === ProcurementDocument::PR_NUMBER_STATUS_PENDING_ASSIGNMENT;
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();

            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('tracking_number', 'like', "%{$search}%")
                    ->orWhere('pr_no', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhereHas('submittingOffice', fn (Builder $office) => $office->where('name', 'like', "%{$search}%"));
            });
        });

        $query->when($request->filled('fiscal_year'), fn (Builder $builder) => $builder->where('fiscal_year', $request->input('fiscal_year')));
        $query->when($request->filled('office_id'), fn (Builder $builder) => $builder->where('submitting_office_id', $request->input('office_id')));
        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('updated_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('updated_at', '<=', $request->date('date_to')));
    }

    private function summary(?User $user): array
    {
        $base = ProcurementDocument::query()
            ->whereIn('document_type', ['PR', 'Purchase Request']);

        return [
            'pending' => (clone $base)->where('status', ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT)->where('pr_number_status', ProcurementDocument::PR_NUMBER_STATUS_PENDING_ASSIGNMENT)->count()
                + Rfq::where('status', Rfq::STATUS_PENDING_CONTROL)->count()
                + PurchaseOrder::where('status', PurchaseOrder::STATUS_PENDING_CONTROL)->count(),
            'assignedToday' => (clone $base)->where('pr_number_status', ProcurementDocument::PR_NUMBER_STATUS_ASSIGNED)->whereDate('pr_no_assigned_at', today())->count()
                + Rfq::whereDate('control_assigned_at', today())->count()
                + PurchaseOrder::whereDate('control_assigned_at', today())->count(),
            'assignedByYou' => (clone $base)->where('pr_no_assigned_by_user_id', $user?->id)->count()
                + Rfq::where('control_assigned_by_user_id', $user?->id)->count()
                + PurchaseOrder::where('control_assigned_by_user_id', $user?->id)->count(),
            'returned' => (clone $base)->where('status', ProcurementDocument::STATUS_RETURNED_BY_PR_NUMBERING_STAFF)->count()
                + Rfq::where('status', Rfq::STATUS_RETURNED)->count()
                + PurchaseOrder::where('status', PurchaseOrder::STATUS_RETURNED)->count(),
        ];
    }

    private function updateSequence(ProcurementDocument $document, User $user): void
    {
        $parts = app(OfficialPrNumberService::class)->parts((string) $document->pr_no);
        if (! $parts) {
            return;
        }
        $year = $parts['year'];
        $prefix = $parts['fund'].'-'.$year.'-';
        $sequence = $parts['sequence'];

        $current = DB::table('pr_number_sequences')
            ->where('fiscal_year', $year)
            ->where('prefix', $prefix)
            ->lockForUpdate()
            ->first();

        if (! $current) {
            DB::table('pr_number_sequences')->insert([
                'fiscal_year' => $year,
                'office_id' => null,
                'prefix' => $prefix,
                'last_sequence' => $sequence,
                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        if ($sequence > (int) $current->last_sequence) {
            DB::table('pr_number_sequences')
                ->where('id', $current->id)
                ->update([
                    'last_sequence' => $sequence,
                    'updated_by_user_id' => $user->id,
                    'updated_at' => now(),
                ]);
        }
    }

    private function requestingOfficeReturnTarget(ProcurementDocument $document): array
    {
        $document->loadMissing(['submittedBy', 'submittingOffice']);

        $office = $document->submittingOffice
            ?: ($document->submitting_office_id ? Office::find($document->submitting_office_id) : null);
        $submitter = $document->submittedBy
            ?: ($document->submitted_by_user_id ? User::find($document->submitted_by_user_id) : null);

        if (! $this->isBacsec002PurchaseRequest($document, $submitter)) {
            return ['office' => $office ?: $submitter?->assignedOffice, 'user' => $submitter];
        }

        if ($office && (int) $office->id !== (int) ($submitter?->office_id)) {
            return [
                'office' => $office,
                'user' => $this->activeHeadOfficeUserFor($office) ?: $submitter,
            ];
        }

        return ['office' => $office ?: $submitter?->assignedOffice, 'user' => $submitter];
    }

    private function isBacsec002PurchaseRequest(ProcurementDocument $document, ?User $submitter = null): bool
    {
        return in_array($document->document_type, ['PR', 'Purchase Request'], true)
            && ($submitter?->user_id === 'BACSEC-001'
                || User::whereKey($document->submitted_by_user_id)->where('user_id', 'BACSEC-001')->exists());
    }

    private function activeHeadOfficeUserFor(Office $office): ?User
    {
        return User::where('status', User::STATUS_ACTIVE)
            ->where('office_id', $office->id)
            ->where(function (Builder $query) {
                $query->whereHas('assignedRole', function (Builder $roleQuery) {
                    $roleQuery->where('code', 'head_office')
                        ->orWhere('name', User::ROLE_HEAD_OFFICE);
                })
                    ->orWhere('role', User::ROLE_HEAD_OFFICE);
            })
            ->orderBy('user_id')
            ->first()
            ?: User::where('status', User::STATUS_ACTIVE)
                ->where('office_id', $office->id)
                ->orderBy('user_id')
                ->first();
    }

    private function bacSecretariatTarget(): array
    {
        $office = Office::where('code', 'BACSEC')
            ->orWhere('name', 'BAC Secretariat')
            ->first();

        $user = User::where('user_id', 'BACSEC-001')->where('status', User::STATUS_ACTIVE)->first()
            ?: User::where('status', User::STATUS_ACTIVE)
            ->where(function (Builder $query) {
                $query->whereHas('assignedRole', function (Builder $roleQuery) {
                    $roleQuery->where('code', 'bac_secretariat')
                        ->orWhere('name', User::ROLE_BAC_SECRETARIAT);
                })
                    ->orWhere('role', User::ROLE_BAC_SECRETARIAT)
                    ->orWhere('user_id', 'BACSEC-001');
            })
            ->first();

        return ['office' => $office, 'user' => $user];
    }

    private function recordRouting(ProcurementDocument $document, User $user, string $action, ?string $fromStatus, string $toStatus, ?string $comments, ?int $fromOfficeId, ?int $toOfficeId): void
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
}
