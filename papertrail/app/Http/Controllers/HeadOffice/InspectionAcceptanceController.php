<?php

namespace App\Http\Controllers\HeadOffice;

use App\Http\Controllers\Controller;
use App\Models\InspectionAcceptanceRecord;
use App\Models\PurchaseOrder;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentDraftService;
use App\Services\SvpChainService;
use App\Services\SystemNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InspectionAcceptanceController extends Controller
{
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
            'canAccept' => in_array($purchaseOrder->status, [
                PurchaseOrder::STATUS_SUBMITTED,
                PurchaseOrder::STATUS_ISSUED,
                PurchaseOrder::STATUS_FORWARDED_TO_SUPPLIER,
                PurchaseOrder::STATUS_FUND_CERTIFIED,
            ], true),
            'canComplete' => $purchaseOrder->status === PurchaseOrder::STATUS_APPROVED,
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
            $record = $purchaseOrder->latestInspectionAcceptanceRecord()->first();
            $oldValues = $record?->only(array_keys($validated));

            $record = InspectionAcceptanceRecord::updateOrCreate(
                ['purchase_order_id' => $purchaseOrder->id],
                array_merge($validated, [
                    'source_pr_document_id' => $purchaseOrder->source_pr_document_id,
                    'office_id' => $purchaseOrder->sourcePrDocument?->submitting_office_id ?? $request->user()->office_id,
                    'inspected_by_user_id' => $request->user()->id,
                    'status' => InspectionAcceptanceRecord::STATUS_INSPECTED,
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
        });

        return redirect()
            ->route('head-office.inspection.show', $purchaseOrder)
            ->with('status', 'Inspection details saved.');
    }

    public function accept(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! $this->canAccess($request->user(), $purchaseOrder)) {
            AuditLogger::log('Inspection / Acceptance', 'Unauthorized Purchase Order Acceptance', 'Head Office user attempted to accept a Purchase Order outside their office.', $purchaseOrder, null, null, 'warning');

            abort(403);
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

        DB::transaction(function () use ($purchaseOrder, $request, $validated) {
            $oldStatus = $purchaseOrder->status;

            $record = InspectionAcceptanceRecord::updateOrCreate(
                ['purchase_order_id' => $purchaseOrder->id],
                [
                    'source_pr_document_id' => $purchaseOrder->source_pr_document_id,
                    'office_id' => $purchaseOrder->sourcePrDocument?->submitting_office_id ?? $request->user()->office_id,
                    'inspected_by_user_id' => $purchaseOrder->latestInspectionAcceptanceRecord?->inspected_by_user_id ?? $request->user()->id,
                    'accepted_by_user_id' => $request->user()->id,
                    'status' => InspectionAcceptanceRecord::STATUS_ACCEPTED,
                    'inspection_date' => $purchaseOrder->latestInspectionAcceptanceRecord?->inspection_date ?? now()->toDateString(),
                    'acceptance_date' => $validated['acceptance_date'],
                    'remarks' => $validated['remarks'] ?? $purchaseOrder->latestInspectionAcceptanceRecord?->remarks,
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

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_APPROVED) {
            return back()->with('error', 'Only accepted Purchase Orders can be marked completed.');
        }

        $validated = $request->validate([
            'remarks' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($purchaseOrder, $request, $validated) {
            $oldStatus = $purchaseOrder->status;

            $record = InspectionAcceptanceRecord::updateOrCreate(
                ['purchase_order_id' => $purchaseOrder->id],
                [
                    'source_pr_document_id' => $purchaseOrder->source_pr_document_id,
                    'office_id' => $purchaseOrder->sourcePrDocument?->submitting_office_id ?? $request->user()->office_id,
                    'accepted_by_user_id' => $request->user()->id,
                    'status' => InspectionAcceptanceRecord::STATUS_COMPLETED,
                    'acceptance_date' => now()->toDateString(),
                    'remarks' => $validated['remarks'] ?? $purchaseOrder->latestInspectionAcceptanceRecord?->remarks,
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
            'quantity_condition' => ['required', 'string', 'max:120'],
            'quality_condition' => ['required', 'string', 'max:120'],
            'findings' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
