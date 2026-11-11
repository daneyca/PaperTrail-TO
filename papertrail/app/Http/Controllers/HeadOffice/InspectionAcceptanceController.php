<?php

namespace App\Http\Controllers\HeadOffice;

use App\Http\Controllers\Controller;
use App\Models\InspectionAcceptanceRecord;
use App\Models\PurchaseOrder;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentDraftService;
use App\Services\InspectionSignatureWorkflowService;
use App\Services\SvpChainService;
use App\Services\SystemNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class InspectionAcceptanceController extends Controller
{
    public function create(Request $request): View
    {
        $eligiblePurchaseOrders = $this->eligiblePurchaseOrders($request->user())
            ->with(['sourcePrDocument.submittingOffice', 'latestInspectionAcceptanceRecord'])
            ->latest('updated_at')->get();
        $purchaseOrder = null;

        if ($request->filled('purchase_order_id')) {
            $purchaseOrder = $eligiblePurchaseOrders->firstWhere('id', $request->integer('purchase_order_id'));
            abort_unless($purchaseOrder, 403);
            $purchaseOrder->load('items');
        }

        $record = $purchaseOrder?->latestInspectionAcceptanceRecord ?? new InspectionAcceptanceRecord([
            'report_date' => now()->toDateString(),
            'inspection_date' => now()->toDateString(),
            'quantity_condition' => 'Complete',
            'quality_condition' => 'Conforming',
        ]);

        AuditLogger::log('Inspection / Acceptance', 'Inspection Create Page Viewed', 'Head Office opened the official SVP IAR creation form.');

        return view('head-office.inspection.create', [
            'purchaseOrder' => $purchaseOrder,
            'record' => $record,
            'eligiblePurchaseOrders' => $eligiblePurchaseOrders,
            'recentDrafts' => app(DocumentDraftService::class)->getInspectionDraftsForUser($request->user(), 5),
        ]);
    }

    public function index(Request $request): View
    {
        AuditLogger::log('Inspection / Acceptance', 'Inspection / Acceptance Page Viewed', 'Head Office viewed office inspection and acceptance records.');

        $query = $this->eligiblePurchaseOrders($request->user())
            ->with(['sourcePrDocument.submittingOffice', 'preparedBy', 'latestInspectionAcceptanceRecord.acceptedBy']);

        $this->applyFilters($query, $request);

        return view('head-office.inspection.index', [
            'purchaseOrders' => $query->latest('updated_at')->paginate(10)->withQueryString(),
            'filters' => $request->only(['search', 'status', 'date_from', 'date_to']),
            'recentDrafts' => app(DocumentDraftService::class)->getInspectionDraftsForUser($request->user(), 5),
            'summary' => [
                'draft' => app(DocumentDraftService::class)->getInspectionDraftsForUser($request->user())->count(),
                'forInspection' => (clone $this->eligiblePurchaseOrders($request->user()))->whereIn('status', [
                    PurchaseOrder::STATUS_SUBMITTED,
                    PurchaseOrder::STATUS_ISSUED,
                    PurchaseOrder::STATUS_FORWARDED_TO_SUPPLIER,
                    PurchaseOrder::STATUS_FUND_CERTIFIED,
                ])->count(),
                'accepted' => (clone $this->eligiblePurchaseOrders($request->user()))->whereIn('status', [
                    PurchaseOrder::STATUS_APPROVED,
                    PurchaseOrder::STATUS_COMPLETED,
                ])->count(),
                'completed' => (clone $this->eligiblePurchaseOrders($request->user()))->where('status', PurchaseOrder::STATUS_COMPLETED)->count(),
            ],
            'statuses' => $this->visibleStatuses(),
        ]);
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        if (! $this->canAccess($request->user(), $purchaseOrder)) {
            AuditLogger::log('Inspection / Acceptance', 'Unauthorized Inspection Record Access', 'Head Office user attempted to access an inspection record outside their office.', $purchaseOrder, null, null, 'warning');

            abort(403);
        }

        AuditLogger::log('Inspection / Acceptance', 'Inspection / Acceptance Detail Viewed', 'Head Office viewed an inspection and acceptance detail page.', $purchaseOrder);

        $purchaseOrder->load([
            'sourcePrDocument.submittingOffice',
            'sourceAbstract',
            'sourceBacResolution',
            'preparedBy',
            'submittedBy',
            'items',
            'inspectionAcceptanceRecords.inspectedBy',
            'inspectionAcceptanceRecords.acceptedBy',
        ]);

        $record = $purchaseOrder->inspectionAcceptanceRecords->first() ?? new InspectionAcceptanceRecord([
            'inspection_date' => now()->toDateString(),
            'acceptance_date' => now()->toDateString(),
            'quantity_condition' => 'Complete',
            'quality_condition' => 'Conforming',
        ]);

        return view('head-office.inspection.show', [
            'purchaseOrder' => $purchaseOrder,
            'record' => $record,
            'records' => $purchaseOrder->inspectionAcceptanceRecords,
            'canAccept' => app(InspectionSignatureWorkflowService::class)->isFullySigned($record) && ! $request->user()->isOfficeDocumentPreparer() && in_array($purchaseOrder->status, [
                PurchaseOrder::STATUS_SUBMITTED,
                PurchaseOrder::STATUS_ISSUED,
                PurchaseOrder::STATUS_FORWARDED_TO_SUPPLIER,
                PurchaseOrder::STATUS_FUND_CERTIFIED,
            ], true),
            'canComplete' => app(InspectionSignatureWorkflowService::class)->isFullySigned($record) && ! $request->user()->isOfficeDocumentPreparer() && $purchaseOrder->status === PurchaseOrder::STATUS_APPROVED,
        ]);
    }

    public function store(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! $this->canAccess($request->user(), $purchaseOrder)) {
            AuditLogger::log('Inspection / Acceptance', 'Unauthorized Inspection Record Save', 'Head Office user attempted to save an inspection record outside their office.', $purchaseOrder, null, null, 'warning');

            abort(403);
        }

        $validated = $request->validate($this->recordRules());

        DB::transaction(function () use ($purchaseOrder, $request, $validated) {
            $record = $purchaseOrder->latestInspectionAcceptanceRecord()->lockForUpdate()->first();
            if ($record && app(InspectionSignatureWorkflowService::class)->isLocked($record)) {
                throw ValidationException::withMessages(['signatures' => 'Use Revise Report to edit a routed or signed report. All three accounts must sign a revised report again.']);
            }
            $oldValues = $record?->only(array_keys($validated));

            $record = InspectionAcceptanceRecord::updateOrCreate(
                ['purchase_order_id' => $purchaseOrder->id],
                array_merge($validated, [
                    'source_pr_document_id' => $purchaseOrder->source_pr_document_id,
                    'office_id' => $purchaseOrder->sourcePrDocument?->submitting_office_id ?? $request->user()->office_id,
                    'inspected_by_user_id' => $request->user()->id,
                    'status' => in_array($record?->status, [InspectionAcceptanceRecord::STATUS_ACCEPTED, InspectionAcceptanceRecord::STATUS_COMPLETED], true)
                        ? $record->status
                        : ($request->input('save_action') === 'draft' ? InspectionAcceptanceRecord::STATUS_DRAFT : InspectionAcceptanceRecord::STATUS_INSPECTED),
                ])
            );

            AuditLogger::log(
                'Inspection / Acceptance',
                'Inspection Record Saved',
                'Head Office saved inspection details for a Purchase Order.',
                $record,
                $oldValues,
                $record->only(array_keys($validated))
            );

            app(SvpChainService::class)->linkInspection($record->refresh(), $request->user(), 'Inspection record saved');
            if ($request->input('save_action') === 'submit') {
                app(InspectionSignatureWorkflowService::class)->submit($record, $request->user());
            }
        });

        if ($request->input('return_to') === 'editor') {
            return redirect()->route('head-office.inspection.create', ['purchase_order_id' => $purchaseOrder->id, 'edit' => 1])
                ->with('status', $request->input('save_action') === 'submit' ? 'IAR routed to MO-STAFF, MCR-001, and PC-001 for signatures.' : 'Inspection draft saved.');
        }

        return redirect()
            ->route('head-office.inspection.show', $purchaseOrder)
            ->with('status', 'Inspection details saved.');
    }

    public function submitForSignatures(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($this->canAccess($request->user(), $purchaseOrder), 403);
        $record = $purchaseOrder->latestInspectionAcceptanceRecord()->firstOrFail();
        app(InspectionSignatureWorkflowService::class)->submit($record, $request->user());
        return back()->with('status', 'IAR routed to MO-STAFF, MCR-001, and PC-001 for signatures.');
    }

    public function revise(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($this->canAccess($request->user(), $purchaseOrder), 403);
        $record = $purchaseOrder->latestInspectionAcceptanceRecord()->firstOrFail();
        app(InspectionSignatureWorkflowService::class)->revise($record, $request->user());
        return redirect()->route('head-office.inspection.create', ['purchase_order_id' => $purchaseOrder->id, 'edit' => 1])
            ->with('status', 'Report reopened. Previous signatures are preserved in history; all three accounts must sign the revision.');
    }

    public function print(Request $request, PurchaseOrder $purchaseOrder): View
    {
        abort_unless($this->canAccess($request->user(), $purchaseOrder), 403);

        $purchaseOrder->load(['sourcePrDocument.submittingOffice', 'items', 'latestInspectionAcceptanceRecord']);

        AuditLogger::log('Inspection / Acceptance', 'Inspection Report Print Viewed', 'Head Office opened the official SVP Inspection and Acceptance Report.', $purchaseOrder);

        return view('head-office.inspection.print', [
            'purchaseOrder' => $purchaseOrder,
            'record' => $purchaseOrder->latestInspectionAcceptanceRecord ?? new InspectionAcceptanceRecord(),
        ]);
    }

    public function printTemplate(): View
    {
        $purchaseOrder = new PurchaseOrder();
        $purchaseOrder->setRelation('items', collect());
        $purchaseOrder->setRelation('sourcePrDocument', null);

        return view('head-office.inspection.print', [
            'purchaseOrder' => $purchaseOrder,
            'record' => new InspectionAcceptanceRecord(),
        ]);
    }

    public function accept(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! $this->canAccess($request->user(), $purchaseOrder)) {
            AuditLogger::log('Inspection / Acceptance', 'Unauthorized Purchase Order Acceptance', 'Head Office user attempted to accept a Purchase Order outside their office.', $purchaseOrder, null, null, 'warning');

            abort(403);
        }

        $signedRecord = $purchaseOrder->latestInspectionAcceptanceRecord()->first();
        if (! $signedRecord || ! app(InspectionSignatureWorkflowService::class)->isFullySigned($signedRecord)) {
            return back()->with('error', 'All three assigned accounts must sign the current IAR before acceptance.');
        }

        if (! in_array($purchaseOrder->status, [
            PurchaseOrder::STATUS_SUBMITTED,
            PurchaseOrder::STATUS_ISSUED,
            PurchaseOrder::STATUS_FORWARDED_TO_SUPPLIER,
            PurchaseOrder::STATUS_FUND_CERTIFIED,
        ], true)) {
            return back()->with('error', 'This Purchase Order is not ready for acceptance.');
        }

        $validated = $request->validate([
            'acceptance_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
        ]);
        if (\Illuminate\Support\Carbon::parse($validated['acceptance_date'])->toDateString() !== $signedRecord->acceptance_date?->toDateString()) {
            throw ValidationException::withMessages(['acceptance_date' => 'The date received must match the signed report. Revise and re-sign the report to change it.']);
        }

        DB::transaction(function () use ($purchaseOrder, $request, $validated) {
            $oldStatus = $purchaseOrder->status;

            $currentReport = $purchaseOrder->latestInspectionAcceptanceRecord()->lockForUpdate()->first();
            if (! $currentReport || ! app(InspectionSignatureWorkflowService::class)->isFullySigned($currentReport)) {
                throw ValidationException::withMessages(['signatures' => 'All three signatures on the current report are required.']);
            }

            $record = InspectionAcceptanceRecord::updateOrCreate(
                ['purchase_order_id' => $purchaseOrder->id],
                [
                    'source_pr_document_id' => $purchaseOrder->source_pr_document_id,
                    'office_id' => $purchaseOrder->sourcePrDocument?->submitting_office_id ?? $request->user()->office_id,
                    'inspected_by_user_id' => $purchaseOrder->latestInspectionAcceptanceRecord?->inspected_by_user_id ?? $request->user()->id,
                    'accepted_by_user_id' => User::where('user_id', User::PROCUREMENT_CONTROLLER_USER_ID)->value('id'),
                    'status' => InspectionAcceptanceRecord::STATUS_ACCEPTED,
                    'inspection_date' => $purchaseOrder->latestInspectionAcceptanceRecord?->inspection_date ?? now()->toDateString(),
                    'acceptance_date' => $validated['acceptance_date'],
                    'remarks' => $purchaseOrder->latestInspectionAcceptanceRecord?->remarks,
                ]
            );

            $purchaseOrder->update([
                'status' => PurchaseOrder::STATUS_APPROVED,
                'remarks' => $validated['remarks'] ?? $purchaseOrder->remarks,
            ]);

            AuditLogger::log(
                'Inspection / Acceptance',
                'Purchase Order Accepted',
                'Head Office accepted a Purchase Order after inspection.',
                $purchaseOrder,
                ['status' => $oldStatus],
                ['status' => PurchaseOrder::STATUS_APPROVED, 'inspection_record_id' => $record->id]
            );

            app(SvpChainService::class)->linkInspection($record->refresh(), $request->user(), 'Inspection / Acceptance accepted', $validated['remarks'] ?? null);

            SystemNotificationService::notify(
                $request->user(),
                'Inspection / Acceptance Submitted',
                'Your Inspection / Acceptance record has been submitted.',
                SystemNotification::TYPE_SUCCESS,
                'Inspection / Acceptance',
                $record,
                route('head-office.inspection.show', $purchaseOrder),
            );
        });

        return redirect()
            ->route('head-office.inspection.show', $purchaseOrder)
            ->with('status', 'Purchase Order marked as accepted.');
    }

    public function complete(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! $this->canAccess($request->user(), $purchaseOrder)) {
            AuditLogger::log('Inspection / Acceptance', 'Unauthorized Purchase Order Completion', 'Head Office user attempted to complete a Purchase Order outside their office.', $purchaseOrder, null, null, 'warning');

            abort(403);
        }

        $signedRecord = $purchaseOrder->latestInspectionAcceptanceRecord()->first();
        if (! $signedRecord || ! app(InspectionSignatureWorkflowService::class)->isFullySigned($signedRecord)) {
            return back()->with('error', 'All three assigned accounts must sign the current IAR before completion.');
        }

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_APPROVED) {
            return back()->with('error', 'Only accepted Purchase Orders can be marked completed.');
        }

        $validated = $request->validate([
            'remarks' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($purchaseOrder, $request, $validated) {
            $oldStatus = $purchaseOrder->status;

            $currentReport = $purchaseOrder->latestInspectionAcceptanceRecord()->lockForUpdate()->first();
            if (! $currentReport || ! app(InspectionSignatureWorkflowService::class)->isFullySigned($currentReport)) {
                throw ValidationException::withMessages(['signatures' => 'All three signatures on the current report are required.']);
            }

            $record = InspectionAcceptanceRecord::updateOrCreate(
                ['purchase_order_id' => $purchaseOrder->id],
                [
                    'source_pr_document_id' => $purchaseOrder->source_pr_document_id,
                    'office_id' => $purchaseOrder->sourcePrDocument?->submitting_office_id ?? $request->user()->office_id,
                    'accepted_by_user_id' => User::where('user_id', User::PROCUREMENT_CONTROLLER_USER_ID)->value('id'),
                    'status' => InspectionAcceptanceRecord::STATUS_COMPLETED,
                    'acceptance_date' => $purchaseOrder->latestInspectionAcceptanceRecord?->acceptance_date ?? now()->toDateString(),
                    'remarks' => $purchaseOrder->latestInspectionAcceptanceRecord?->remarks,
                ]
            );

            $purchaseOrder->update([
                'status' => PurchaseOrder::STATUS_COMPLETED,
                'completed_at' => now(),
                'remarks' => $validated['remarks'] ?? $purchaseOrder->remarks,
            ]);

            AuditLogger::log(
                'Inspection / Acceptance',
                'Purchase Order Completed',
                'Head Office marked a Purchase Order as completed.',
                $purchaseOrder,
                ['status' => $oldStatus],
                ['status' => PurchaseOrder::STATUS_COMPLETED, 'inspection_record_id' => $record->id]
            );

            app(SvpChainService::class)->linkInspection($record->refresh(), $request->user(), 'SVP chain completed', $validated['remarks'] ?? null);
        });

        return redirect()
            ->route('head-office.inspection.show', $purchaseOrder)
            ->with('status', 'Purchase Order marked as completed.');
    }

    private function eligiblePurchaseOrders(User $user): Builder
    {
        return $this->officePurchaseOrders($user)
            ->whereIn('status', $this->visibleStatuses());
    }

    private function officePurchaseOrders(User $user): Builder
    {
        return PurchaseOrder::query()
            ->where(function (Builder $query) use ($user) {
                $query->where('prepared_by_user_id', $user->id)
                    ->orWhere('submitted_by_user_id', $user->id)
                    ->orWhereHas('sourcePrDocument', fn (Builder $document) => $this->scopeOfficeDocument($document, $user))
                    ->orWhereHas('sourceAbstract.sourcePrDocument', fn (Builder $document) => $this->scopeOfficeDocument($document, $user))
                    ->orWhereHas('sourceBacResolution.sourcePrDocument', fn (Builder $document) => $this->scopeOfficeDocument($document, $user));
            });
    }

    private function scopeOfficeDocument(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $office) use ($user) {
            if ($user->office_id) {
                $office->where('submitting_office_id', $user->office_id)
                    ->orWhere('current_office_id', $user->office_id);
            }

            $office->orWhere('submitted_by_user_id', $user->id)
                ->orWhere('prepared_by_user_id', $user->id);
        });
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();
            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('document_reference_number', 'like', "%{$search}%")
                    ->orWhere('po_number', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%")
                    ->orWhereHas('sourcePrDocument', fn (Builder $document) => $document->where('document_reference_number', 'like', "%{$search}%")
                        ->orWhere('tracking_number', 'like', "%{$search}%")
                        ->orWhere('pr_no', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%"));
            });
        });

        $query->when($request->filled('created_year'), fn (Builder $builder) => $builder->where('created_year', $request->input('created_year')));
        $query->when($request->filled('created_month'), fn (Builder $builder) => $builder->where('created_month', $request->input('created_month')));

        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', function (Builder $builder) use ($request): void {
            $status = (string) $request->input('status');
            $groups = [
                'draft' => [PurchaseOrder::STATUS_DRAFT],
                'for_inspection' => [
                    PurchaseOrder::STATUS_SUBMITTED,
                    PurchaseOrder::STATUS_ISSUED,
                    PurchaseOrder::STATUS_FORWARDED_TO_SUPPLIER,
                    PurchaseOrder::STATUS_FUND_CERTIFIED,
                ],
                'accepted' => [
                    PurchaseOrder::STATUS_APPROVED,
                    PurchaseOrder::STATUS_COMPLETED,
                ],
                'completed' => [PurchaseOrder::STATUS_COMPLETED],
            ];

            $builder->whereIn('status', $groups[$status] ?? [$status]);
        });
        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('updated_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('updated_at', '<=', $request->date('date_to')));
    }

    private function canAccess(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->officePurchaseOrders($user)->whereKey($purchaseOrder->id)->exists();
    }

    private function visibleStatuses(): array
    {
        return [
            PurchaseOrder::STATUS_SUBMITTED,
            PurchaseOrder::STATUS_ISSUED,
            PurchaseOrder::STATUS_FORWARDED_TO_SUPPLIER,
            PurchaseOrder::STATUS_FUND_CERTIFIED,
            PurchaseOrder::STATUS_APPROVED,
            PurchaseOrder::STATUS_COMPLETED,
        ];
    }

    private function recordRules(): array
    {
        return [
            'inspection_date' => ['required', 'date'],
            'acceptance_date' => ['nullable', 'date'],
            'delivery_receipt_number' => ['nullable', 'string', 'max:120'],
            'invoice_number' => ['nullable', 'string', 'max:120'],
            'report_date' => ['nullable', 'date'],
            'invoice_date' => ['nullable', 'date'],
            'inspector_name' => ['nullable', 'string', 'max:255'],
            'inspection_chair_name' => ['nullable', 'string', 'max:255'],
            'acceptance_officer_name' => ['nullable', 'string', 'max:255'],
            'inspector_designation' => ['nullable', 'string', 'max:255'],
            'inspection_chair_designation' => ['nullable', 'string', 'max:255'],
            'acceptance_officer_designation' => ['nullable', 'string', 'max:255'],
            'quantity_condition' => ['required', 'string', 'max:120'],
            'quality_condition' => ['required', 'string', 'max:120'],
            'findings' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
