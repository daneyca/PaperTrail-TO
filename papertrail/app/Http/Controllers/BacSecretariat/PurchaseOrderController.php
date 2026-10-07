<?php

namespace App\Http\Controllers\BacSecretariat;

use App\Http\Controllers\Controller;
use App\Models\DocumentRoutingHistory;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentDraftService;
use App\Services\SvpChainService;
use App\Services\SystemNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    private const ELIGIBLE_PR_STATUSES = [
        ProcurementDocument::STATUS_READY_FOR_PO,
        ProcurementDocument::STATUS_APPROVED,
        ProcurementDocument::STATUS_PO_APPROVED,
        'approved',
        'pr_approved',
        'approved_for_po',
    ];

    public function index(Request $request): View
    {
        AuditLogger::log('Purchase Orders', 'Purchase Orders Page Viewed', 'BAC Secretariat viewed Purchase Orders.');

        $query = PurchaseOrder::query()->with(['sourcePrDocument.submittingOffice', 'preparedBy']);
        $this->applyFilters($query, $request);

        return view('bac-secretariat.po.index', [
            'purchaseOrders' => $query->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary(),
            'filters' => $request->only(['search', 'fiscal_year', 'status', 'date_from', 'date_to']),
            'fiscalYears' => PurchaseOrder::query()->select('fiscal_year')->whereNotNull('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(Request $request): View
    {
        AuditLogger::log('Purchase Orders', 'PO Create Page Viewed', 'BAC Secretariat opened the official Purchase Order document form.');

        $sourceDocument = $this->selectedEligiblePr($request->input('source_pr_document_id'));
        $purchaseOrder = new PurchaseOrder($this->defaultOrderData($sourceDocument, $request->user()));
        $purchaseOrder->setRelation('items', $this->defaultItems($sourceDocument));

        return view('bac-secretariat.po.create', [
            'po' => $purchaseOrder,
            'sourceDocument' => $sourceDocument,
            'eligiblePrs' => $this->eligiblePrQuery()->with(['submittingOffice'])->latest('updated_at')->get(),
            'recentDrafts' => app(DocumentDraftService::class)->getPurchaseOrderDraftsForBacSecretariat($request->user(), 5),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $isSubmitting = $request->input('save_action') === 'submit';
        $validated = $this->validatedOrder($request, $isSubmitting);
        $sourceDocument = $this->selectedEligiblePr($validated['source_pr_document_id'] ?? null);

        if (($validated['source_pr_document_id'] ?? null) && ! $sourceDocument) {
            return back()->with('error', 'The selected Purchase Request is not eligible for Purchase Order preparation.')->withInput();
        }

        $purchaseOrder = null;

        DB::transaction(function () use ($request, $validated, $sourceDocument, $isSubmitting, &$purchaseOrder) {
            $purchaseOrder = PurchaseOrder::create([
                ...$this->orderPayload($validated, $sourceDocument),
                ...$this->documentPayload($validated),
                'prepared_by_user_id' => $request->user()->id,
                'status' => $isSubmitting ? PurchaseOrder::STATUS_SUBMITTED : PurchaseOrder::STATUS_DRAFT,
                'submitted_by_user_id' => $isSubmitting ? $request->user()->id : null,
                'submitted_at' => $isSubmitting ? now() : null,
            ]);

            $this->syncItems($purchaseOrder, $validated['items'] ?? []);
            $this->refreshTotal($purchaseOrder, $validated);

            if ($isSubmitting) {
                $this->recordSubmission($purchaseOrder->refresh(), $request->user());
            }

            AuditLogger::log(
                'Purchase Orders',
                $isSubmitting ? 'Purchase Order Submitted' : 'PO Draft Created',
                $isSubmitting ? 'BAC Secretariat submitted a Purchase Order.' : 'BAC Secretariat created a Purchase Order draft.',
                $purchaseOrder,
                null,
                ['source_pr' => $sourceDocument?->tracking_number, 'status' => $purchaseOrder->status],
            );

            app(SvpChainService::class)->linkPurchaseOrder($purchaseOrder->refresh(), $request->user(), $isSubmitting ? 'Purchase Order submitted' : 'Purchase Order draft created');
        });

        return redirect()
            ->route('bac-secretariat.purchase-orders.show', $purchaseOrder)
            ->with('status', $isSubmitting ? 'Purchase Order submitted.' : 'Purchase Order draft saved.');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        AuditLogger::log('Purchase Orders', 'Purchase Order Detail Viewed', 'BAC Secretariat viewed a Purchase Order.', $purchaseOrder);

        $purchaseOrder->load([
            'sourcePrDocument.submittingOffice',
            'sourcePrDocument.submittedBy',
            'sourcePrDocument.routingHistories.actionBy',
            'sourcePrDocument.routingHistories.fromOffice',
            'sourcePrDocument.routingHistories.toOffice',
            'procurementDocument.routingHistories.actionBy',
            'procurementDocument.routingHistories.fromOffice',
            'procurementDocument.routingHistories.toOffice',
            'preparedBy',
            'submittedBy',
            'items',
        ]);

        return view('bac-secretariat.po.show', ['po' => $purchaseOrder]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        if (! $purchaseOrder->isEditable()) {
            return redirect()
                ->route('bac-secretariat.purchase-orders.show', $purchaseOrder)
                ->with('error', 'Only draft or returned Purchase Orders can be edited.');
        }

        $purchaseOrder->load(['sourcePrDocument.submittingOffice', 'items']);

        return view('bac-secretariat.po.edit', [
            'po' => $purchaseOrder,
            'sourceDocument' => $purchaseOrder->sourcePrDocument,
            'eligiblePrs' => $this->eligiblePrQuery()->with(['submittingOffice'])->latest('updated_at')->get(),
        ]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! $purchaseOrder->isEditable()) {
            return back()->with('error', 'Only draft or returned Purchase Orders can be updated.');
        }

        $isSubmitting = $request->input('save_action') === 'submit';
        $validated = $this->validatedOrder($request, $isSubmitting, $purchaseOrder);
        $sourceDocument = $this->selectedEligiblePr($validated['source_pr_document_id'] ?? null) ?: $purchaseOrder->sourcePrDocument;

        DB::transaction(function () use ($request, $validated, $purchaseOrder, $sourceDocument, $isSubmitting) {
            $oldValues = $purchaseOrder->only(['po_number', 'status', 'total_amount', 'source_pr_document_id']);

            $purchaseOrder->update([
                ...$this->orderPayload($validated, $sourceDocument),
                ...$this->documentPayload($validated, $purchaseOrder),
                'status' => $isSubmitting ? PurchaseOrder::STATUS_SUBMITTED : PurchaseOrder::STATUS_DRAFT,
                'submitted_by_user_id' => $isSubmitting ? $request->user()->id : $purchaseOrder->submitted_by_user_id,
                'submitted_at' => $isSubmitting ? now() : $purchaseOrder->submitted_at,
            ]);

            $this->syncItems($purchaseOrder, $validated['items'] ?? []);
            $this->refreshTotal($purchaseOrder, $validated);

            if ($isSubmitting) {
                $this->recordSubmission($purchaseOrder->refresh(), $request->user());
            }

            AuditLogger::log(
                'Purchase Orders',
                $isSubmitting ? 'Purchase Order Submitted' : 'Purchase Order Updated',
                $isSubmitting ? 'BAC Secretariat submitted a Purchase Order.' : 'BAC Secretariat updated a Purchase Order.',
                $purchaseOrder,
                $oldValues,
                $purchaseOrder->only(['po_number', 'status', 'total_amount', 'source_pr_document_id']),
            );

            app(SvpChainService::class)->linkPurchaseOrder($purchaseOrder->refresh(), $request->user(), $isSubmitting ? 'Purchase Order submitted' : 'Purchase Order updated');
        });

        return redirect()
            ->route('bac-secretariat.purchase-orders.show', $purchaseOrder)
            ->with('status', $isSubmitting ? 'Purchase Order submitted.' : 'Purchase Order updated.');
    }

    public function submit(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! $purchaseOrder->canSubmit()) {
            return back()->with('error', 'This Purchase Order is not eligible for submission.');
        }

        $purchaseOrder->load(['items', 'sourcePrDocument']);
        $errors = $this->submissionErrors($purchaseOrder);

        if ($errors) {
            return back()->with('error', implode(' ', $errors));
        }

        DB::transaction(function () use ($request, $purchaseOrder) {
            $oldStatus = $purchaseOrder->status;

            $purchaseOrder->update([
                'status' => PurchaseOrder::STATUS_SUBMITTED,
                'submitted_by_user_id' => $request->user()->id,
                'submitted_at' => now(),
            ]);

            $this->recordSubmission($purchaseOrder->refresh(), $request->user());

            AuditLogger::log(
                'Purchase Orders',
                'Purchase Order Submitted',
                'BAC Secretariat submitted a Purchase Order.',
                $purchaseOrder,
                ['status' => $oldStatus],
                ['status' => PurchaseOrder::STATUS_SUBMITTED],
            );

            app(SvpChainService::class)->linkPurchaseOrder($purchaseOrder->refresh(), $request->user(), 'Purchase Order submitted');
        });

        return back()->with('status', 'Purchase Order submitted.');
    }

    public function print(PurchaseOrder $purchaseOrder): View
    {
        AuditLogger::log('Purchase Orders', 'Purchase Order Print Viewed', 'BAC Secretariat opened the Purchase Order print view.', $purchaseOrder);

        $purchaseOrder->load(['sourcePrDocument.submittingOffice', 'preparedBy', 'submittedBy', 'items']);

        return view('bac-secretariat.po.print', ['po' => $purchaseOrder]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();
            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('document_reference_number', 'like', "%{$search}%")
                    ->orWhere('po_number', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%")
                    ->orWhereHas('sourcePrDocument', fn (Builder $pr) => $pr->where('document_reference_number', 'like', "%{$search}%")
                        ->orWhere('tracking_number', 'like', "%{$search}%")
                        ->orWhere('pr_no', 'like', "%{$search}%")
                        ->orWhereHas('submittingOffice', fn (Builder $office) => $office->where('name', 'like', "%{$search}%")));
            });
        });

        foreach (['fiscal_year', 'created_year', 'created_month'] as $field) {
            $query->when($request->filled($field), fn (Builder $builder) => $builder->where($field, $request->input($field)));
        }

        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', fn (Builder $builder) => $builder->where('status', $request->input('status')));

        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('updated_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('updated_at', '<=', $request->date('date_to')));
    }

    private function validatedOrder(Request $request, bool $submitting, ?PurchaseOrder $purchaseOrder = null): array
    {
        $jsonItems = $this->itemsFromJson($request->input('items_json'));
        $items = $this->filteredItems($jsonItems ?: $request->input('items', []));
        $computedTotal = $this->itemTotal($items);

        if (! filled($request->input('total_amount')) && $computedTotal > 0) {
            $request->merge(['total_amount' => $computedTotal]);
        }

        if (! filled($request->input('total_amount_words')) && $computedTotal > 0) {
            $request->merge(['total_amount_words' => $this->amountInWords($computedTotal)]);
        }

        $request->merge([
            'items' => $items,
            'total_amount' => $this->numericString($request->input('total_amount')),
            'alobs_amount' => $this->numericString($request->input('alobs_amount')),
        ]);

        $rules = [
            'source_pr_document_id' => ['nullable', 'exists:procurement_documents,id'],
            'po_number' => [$submitting ? 'required' : 'nullable', 'string', 'max:100', Rule::unique('purchase_orders', 'po_number')->ignore($purchaseOrder?->id)],
            'po_date' => [$submitting ? 'required' : 'nullable', 'date'],
            'supplier_name' => [$submitting ? 'required' : 'nullable', 'string', 'max:255'],
            'supplier_address' => ['nullable', 'string'],
            'mode_of_procurement' => [$submitting ? 'required' : 'nullable', 'string', 'max:255'],
            'place_of_delivery' => ['nullable', 'string', 'max:255'],
            'date_of_delivery' => ['nullable', 'string', 'max:255'],
            'delivery_term' => ['nullable', 'string', 'max:255'],
            'payment_term' => ['nullable', 'string', 'max:255'],
            'total_amount' => [$submitting ? 'required' : 'nullable', 'numeric', 'min:0'],
            'total_amount_words' => ['nullable', 'string', 'max:500'],
            'penalty_clause' => ['nullable', 'string'],
            'authorized_official_name' => ['nullable', 'string', 'max:255'],
            'authorized_official_designation' => ['nullable', 'string', 'max:255'],
            'supplier_representative_name' => ['nullable', 'string', 'max:255'],
            'supplier_representative_designation' => ['nullable', 'string', 'max:255'],
            'supplier_conforme_date' => ['nullable', 'string', 'max:255'],
            'fund_available_text' => ['nullable', 'string', 'max:255'],
            'alobs_number' => ['nullable', 'string', 'max:255'],
            'alobs_amount' => ['nullable', 'numeric', 'min:0'],
            'accountant_name' => ['nullable', 'string', 'max:255'],
            'accountant_designation' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'document_html' => ['nullable', 'string'],
            'document_text' => ['nullable', 'string'],
            'items_json' => ['nullable', 'json'],
            'items' => [$submitting ? 'required' : 'nullable', 'array', $submitting ? 'min:1' : ''],
            'items.*.source_pr_item_id' => ['nullable', 'integer'],
            'items.*.item_no' => ['nullable', 'string', 'max:50'],
            'items.*.quantity' => [$submitting ? 'required' : 'nullable', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:100'],
            'items.*.description' => [$submitting ? 'required' : 'nullable', 'string'],
            'items.*.unit_cost' => [$submitting ? 'required' : 'nullable', 'numeric', 'min:0'],
            'items.*.total_cost' => ['nullable', 'numeric', 'min:0'],
        ];

        $rules['items'] = array_values(array_filter($rules['items']));

        return Validator::make($request->all(), $rules)->validate();
    }

    private function orderPayload(array $validated, ?ProcurementDocument $sourceDocument): array
    {
        $totalAmount = $this->money($validated['total_amount'] ?? 0);

        return [
            'source_pr_document_id' => $sourceDocument?->id,
            'po_number' => $validated['po_number'] ?? null,
            'po_date' => $validated['po_date'] ?? null,
            'fiscal_year' => $sourceDocument?->fiscal_year ?? now()->year,
            'supplier_name' => $validated['supplier_name'] ?? null,
            'supplier_address' => $validated['supplier_address'] ?? null,
            'mode_of_procurement' => $validated['mode_of_procurement'] ?? null,
            'place_of_delivery' => $validated['place_of_delivery'] ?? null,
            'date_of_delivery' => $validated['date_of_delivery'] ?? null,
            'delivery_term' => $validated['delivery_term'] ?? null,
            'payment_term' => $validated['payment_term'] ?? null,
            'delivery_place' => $validated['place_of_delivery'] ?? null,
            'delivery_date' => null,
            'delivery_terms' => $validated['delivery_term'] ?? null,
            'payment_terms' => $validated['payment_term'] ?? null,
            'total_amount' => $totalAmount,
            'total_amount_words' => $validated['total_amount_words'] ?? ($totalAmount > 0 ? $this->amountInWords($totalAmount) : null),
            'penalty_clause' => $validated['penalty_clause'] ?? $this->defaultPenaltyClause(),
            'authorized_official_name' => $validated['authorized_official_name'] ?? null,
            'authorized_official_designation' => $validated['authorized_official_designation'] ?? 'Authorized Official',
            'supplier_representative_name' => $validated['supplier_representative_name'] ?? null,
            'supplier_representative_designation' => $validated['supplier_representative_designation'] ?? null,
            'supplier_conforme_date' => $validated['supplier_conforme_date'] ?? null,
            'fund_available_text' => $validated['fund_available_text'] ?? 'Fund Available:',
            'alobs_number' => $validated['alobs_number'] ?? null,
            'alobs_amount' => filled($validated['alobs_amount'] ?? null) ? $this->money($validated['alobs_amount']) : null,
            'accountant_name' => $validated['accountant_name'] ?? null,
            'accountant_designation' => $validated['accountant_designation'] ?? 'Municipal Accountant',
            'remarks' => $validated['remarks'] ?? null,
        ];
    }

    private function documentPayload(array $validated, ?PurchaseOrder $purchaseOrder = null): array
    {
        $payload = [];

        if (array_key_exists('document_html', $validated)) {
            $payload['document_html'] = $this->sanitizePurchaseOrderHtml($validated['document_html']);
            $payload['document_text'] = $validated['document_text'] ?? strip_tags($payload['document_html']);
            $payload['items_json'] = $this->filteredItems($this->itemsFromJson($validated['items_json'] ?? null));
            $payload['document_version'] = $purchaseOrder
                ? max(1, (int) $purchaseOrder->document_version + 1)
                : 1;
        }

        return $payload;
    }

    private function syncItems(PurchaseOrder $purchaseOrder, array $items): void
    {
        $purchaseOrder->items()->delete();

        foreach (array_values($items) as $index => $item) {
            $quantity = $this->money($item['quantity'] ?? 0);
            $unitCost = $this->money($item['unit_cost'] ?? 0);
            $totalCost = filled($item['total_cost'] ?? null) ? $this->money($item['total_cost']) : $quantity * $unitCost;
            $description = trim((string) ($item['description'] ?? ''));

            PurchaseOrderItem::create([
                'purchase_order_id' => $purchaseOrder->id,
                'source_pr_item_id' => filled($item['source_pr_item_id'] ?? null) ? (int) $item['source_pr_item_id'] : null,
                'item_no' => $item['item_no'] ?? null,
                'item_description' => $description,
                'description' => $description,
                'quantity' => $quantity,
                'unit' => $item['unit'] ?? null,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'sort_order' => $index,
            ]);
        }
    }

    private function filteredItems(array $items): array
    {
        return collect($items)
            ->filter(function (array $item) {
                foreach (['item_no', 'quantity', 'unit', 'description', 'unit_cost', 'total_cost'] as $field) {
                    if ($this->normalizeItemText($item[$field] ?? null) !== '') {
                        return true;
                    }
                }

                return false;
            })
            ->map(function (array $item) {
                $itemNo = $this->normalizeItemText($item['item_no'] ?? null);
                $unit = $this->normalizeItemText($item['unit'] ?? null);
                $description = $this->normalizeItemText($item['description'] ?? null);

                return [
                    'source_pr_item_id' => $item['source_pr_item_id'] ?? null,
                    'item_no' => $itemNo !== '' ? $itemNo : null,
                    'quantity' => $this->numericString($item['quantity'] ?? null),
                    'unit' => $unit !== '' ? $unit : null,
                    'description' => $description !== '' ? $description : null,
                    'unit_cost' => $this->numericString($item['unit_cost'] ?? null),
                    'total_cost' => $this->numericString($item['total_cost'] ?? null),
                ];
            })
            ->values()
            ->all();
    }

    private function numericString(mixed $value): mixed
    {
        $normalized = $this->normalizeItemText($value);

        return $normalized !== ''
            ? str_replace([',', 'PHP', 'php', 'Php'], '', $normalized)
            : null;
    }

    private function normalizeItemText(mixed $value): string
    {
        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', (string) ($value ?? '')));
    }

    private function itemsFromJson(mixed $itemsJson): array
    {
        if (! filled($itemsJson)) {
            return [];
        }

        if (is_array($itemsJson)) {
            return $itemsJson;
        }

        $decoded = json_decode((string) $itemsJson, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function sanitizePurchaseOrderHtml(?string $html): ?string
    {
        if (! filled($html)) {
            return null;
        }

        $html = preg_replace('/<\s*(script|style|iframe|object|embed)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $html);
        $html = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/is', '', $html);
        $html = preg_replace('/\s+(href|src)\s*=\s*("|\')\s*javascript:.*?\2/is', '', $html);

        return trim($html);
    }

    private function refreshTotal(PurchaseOrder $purchaseOrder, array $validated): void
    {
        $computed = (float) $purchaseOrder->items()->sum('total_cost');
        $manual = filled($validated['total_amount'] ?? null) ? $this->money($validated['total_amount']) : null;
        $total = $manual !== null && $manual > 0 ? $manual : $computed;

        $purchaseOrder->update([
            'total_amount' => $total,
            'total_amount_words' => $validated['total_amount_words'] ?? ($total > 0 ? $this->amountInWords($total) : null),
        ]);
    }

    private function recordSubmission(PurchaseOrder $purchaseOrder, User $user): void
    {
        $bacOffice = $this->bacSecretariatOffice();

        $document = $purchaseOrder->procurementDocument ?: ProcurementDocument::create([
            'tracking_number' => $purchaseOrder->po_number ?: 'PO-DRAFT-'.$purchaseOrder->id,
            'document_type' => 'PO',
            'title' => 'Purchase Order'.($purchaseOrder->po_number ? ' '.$purchaseOrder->po_number : ''),
            'description' => $purchaseOrder->remarks,
            'fiscal_year' => $purchaseOrder->fiscal_year,
            'submitting_office_id' => $purchaseOrder->sourcePrDocument?->submitting_office_id,
            'submitted_by_user_id' => $user->id,
            'current_office_id' => $bacOffice?->id,
            'assigned_to_user_id' => $user->id,
            'status' => ProcurementDocument::STATUS_PO_PREPARED,
            'stage' => ProcurementDocument::STAGE_PURCHASE_ORDER_PREPARATION,
            'priority' => 'normal',
            'total_amount' => $purchaseOrder->total_amount,
            'submitted_at' => now(),
        ]);

        $oldStatus = $document->status;
        $document->update([
            'tracking_number' => $purchaseOrder->po_number ?: $document->tracking_number,
            'title' => 'Purchase Order'.($purchaseOrder->po_number ? ' '.$purchaseOrder->po_number : ''),
            'status' => ProcurementDocument::STATUS_PO_PREPARED,
            'stage' => ProcurementDocument::STAGE_PURCHASE_ORDER_PREPARATION,
            'current_office_id' => $bacOffice?->id,
            'assigned_to_user_id' => $user->id,
            'total_amount' => $purchaseOrder->total_amount,
        ]);

        $purchaseOrder->update(['procurement_document_id' => $document->id]);

        $this->recordRouting(
            $document,
            $user,
            'Purchase Order Submitted',
            $oldStatus,
            ProcurementDocument::STATUS_PO_PREPARED,
            'Purchase Order submitted by BAC Secretariat.',
            $bacOffice?->id,
            $bacOffice?->id,
        );

        $this->notifySourceUser(
            $purchaseOrder,
            'Purchase Order Submitted',
            'Purchase Order '.($purchaseOrder->po_number ?? 'draft').' has been submitted by BAC Secretariat.',
            SystemNotification::TYPE_INFO,
        );
    }

    private function defaultOrderData(?ProcurementDocument $sourceDocument, User $user): array
    {
        $total = (float) ($sourceDocument?->total_amount ?? 0);

        return [
            'source_pr_document_id' => $sourceDocument?->id,
            'fiscal_year' => $sourceDocument?->fiscal_year ?? now()->year,
            'total_amount' => $total,
            'total_amount_words' => $total > 0 ? $this->amountInWords($total) : null,
            'mode_of_procurement' => $sourceDocument?->mode_of_procurement,
            'place_of_delivery' => $sourceDocument?->submittingOffice?->name,
            'date_of_delivery' => $sourceDocument?->requested_delivery_date?->format('M d, Y'),
            'delivery_term' => null,
            'payment_term' => null,
            'penalty_clause' => $this->defaultPenaltyClause(),
            'authorized_official_designation' => 'Authorized Official',
            'fund_available_text' => 'Fund Available:',
            'accountant_designation' => 'Municipal Accountant',
            'prepared_by_user_id' => $user->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
        ];
    }

    private function defaultItems(?ProcurementDocument $sourceDocument): Collection
    {
        if (! $sourceDocument) {
            return collect();
        }

        $sourceDocument->loadMissing('purchaseRequestItems');

        return $sourceDocument->purchaseRequestItems->map(function ($item, int $index) {
            $unitCost = (float) ($item->estimated_unit_cost ?? 0);
            $quantity = (float) ($item->quantity ?? 0);

            return new PurchaseOrderItem([
                'source_pr_item_id' => $item->id,
                'item_no' => $item->item_no ?: (string) ($index + 1),
                'quantity' => $quantity,
                'unit' => $item->unit ?: $item->unit_of_issue,
                'item_description' => $item->description ?: $item->item_description,
                'description' => $item->description ?: $item->item_description,
                'unit_cost' => $unitCost,
                'total_cost' => (float) ($item->estimated_total_cost ?? $item->estimated_cost ?? ($quantity * $unitCost)),
                'sort_order' => $index,
            ]);
        });
    }

    private function submissionErrors(PurchaseOrder $purchaseOrder): array
    {
        $errors = [];

        foreach ([
            'supplier_name' => 'Supplier name is required.',
            'po_number' => 'P.O. number is required.',
            'po_date' => 'P.O. date is required.',
            'mode_of_procurement' => 'Mode of procurement is required.',
        ] as $field => $message) {
            if (! filled($purchaseOrder->{$field})) {
                $errors[] = $message;
            }
        }

        if (! $purchaseOrder->items()->exists()) {
            $errors[] = 'At least one Purchase Order item is required.';
        }

        if ((float) $purchaseOrder->total_amount <= 0) {
            $errors[] = 'Total amount is required.';
        }

        return $errors;
    }

    private function selectedEligiblePr(mixed $id): ?ProcurementDocument
    {
        if (! filled($id)) {
            return null;
        }

        return $this->eligiblePrQuery()
            ->with(['submittingOffice', 'purchaseRequestItems'])
            ->whereKey($id)
            ->first();
    }

    private function eligiblePrQuery(): Builder
    {
        return ProcurementDocument::query()
            ->whereIn('document_type', ['PR', 'Purchase Request'])
            ->whereIn('status', self::ELIGIBLE_PR_STATUSES)
            ->whereDoesntHave('sourcePurchaseOrders', fn (Builder $query) => $query->whereNotIn('status', [PurchaseOrder::STATUS_CANCELLED, PurchaseOrder::STATUS_RETURNED]));
    }

    private function itemTotal(array $items): float
    {
        return collect($items)->sum(function (array $item) {
            if (filled($item['total_cost'] ?? null)) {
                return $this->money($item['total_cost']);
            }

            return $this->money($item['quantity'] ?? 0) * $this->money($item['unit_cost'] ?? 0);
        });
    }

    private function money(mixed $value): float
    {
        if (! filled($value)) {
            return 0.0;
        }

        return max((float) str_replace([',', 'PHP', 'php', 'Php'], '', (string) $value), 0);
    }

    private function defaultPenaltyClause(): string
    {
        return 'In case of failure to make the full delivery within the time specified above, a penalty of one-tenth (1/10) of one percent for every day of delay shall be imposed.';
    }

    private function summary(): array
    {
        return [
            'draft' => PurchaseOrder::whereIn('status', [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_PREPARED])->count(),
            'submitted' => PurchaseOrder::whereIn('status', [PurchaseOrder::STATUS_SUBMITTED, PurchaseOrder::STATUS_ISSUED])->count(),
            'fund_certified' => PurchaseOrder::where('status', PurchaseOrder::STATUS_FUND_CERTIFIED)->count(),
            'approved' => PurchaseOrder::whereIn('status', [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_COMPLETED])->count(),
            'returned' => PurchaseOrder::where('status', PurchaseOrder::STATUS_RETURNED)->count(),
        ];
    }

    private function statuses(): array
    {
        return [
            PurchaseOrder::STATUS_DRAFT,
            PurchaseOrder::STATUS_SUBMITTED,
            PurchaseOrder::STATUS_FORWARDED_TO_SUPPLIER,
            PurchaseOrder::STATUS_FUND_CERTIFIED,
            PurchaseOrder::STATUS_APPROVED,
            PurchaseOrder::STATUS_RETURNED,
            PurchaseOrder::STATUS_CANCELLED,
        ];
    }

    private function recordRouting(ProcurementDocument $document, User $user, string $action, ?string $fromStatus, string $toStatus, ?string $comments = null, ?int $fromOfficeId = null, ?int $toOfficeId = null): void
    {
        DocumentRoutingHistory::create([
            'procurement_document_id' => $document->id,
            'action_by_user_id' => $user->id,
            'from_office_id' => $fromOfficeId ?? $document->current_office_id,
            'to_office_id' => $toOfficeId ?? $document->current_office_id,
            'action' => $action,
            'status_from' => $fromStatus,
            'status_to' => $toStatus,
            'comments' => $comments,
            'action_at' => now(),
        ]);
    }

    private function notifySourceUser(PurchaseOrder $purchaseOrder, string $title, string $message, string $type): void
    {
        SystemNotificationService::notify($purchaseOrder->sourcePrDocument?->submittedBy, $title, $message, $type, 'Purchase Orders', $purchaseOrder);
    }

    private function bacSecretariatOffice(): ?Office
    {
        return Office::where('code', 'BACSEC')->orWhere('name', 'BAC Secretariat')->first();
    }

    private function amountInWords(float $amount): string
    {
        $whole = (int) floor($amount);
        $centavos = (int) round(($amount - $whole) * 100);
        $words = $this->numberToWords($whole);
        $result = str($words ?: 'zero')->title()->toString().' Pesos';

        if ($centavos > 0) {
            $result .= ' and '.$this->numberToWords($centavos).' Centavos';
        }

        return $result;
    }

    private function numberToWords(int $number): string
    {
        $units = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
        $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

        if ($number < 20) {
            return $units[$number];
        }

        if ($number < 100) {
            return trim($tens[intdiv($number, 10)].' '.$units[$number % 10]);
        }

        if ($number < 1000) {
            return trim($units[intdiv($number, 100)].' hundred '.$this->numberToWords($number % 100));
        }

        foreach ([1000000000 => 'billion', 1000000 => 'million', 1000 => 'thousand'] as $value => $label) {
            if ($number >= $value) {
                return trim($this->numberToWords(intdiv($number, $value))." {$label} ".$this->numberToWords($number % $value));
            }
        }

        return '';
    }
}
