<?php

namespace App\Http\Controllers\BacSecretariat;

use App\Http\Controllers\Controller;
use App\Models\BacResolution;
use App\Models\ProcurementDocument;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Services\AuditLogger;
use App\Services\DocumentDraftService;
use App\Services\SvpChainService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RfqController extends Controller
{
    public function index(Request $request): View
    {
        AuditLogger::log('RFQ', 'RFQ Page Viewed', 'BAC Secretariat viewed RFQs.');

        $query = Rfq::query()->with(['sourcePrDocument.submittingOffice', 'sourceBacResolution', 'preparedBy']);
        $this->applyFilters($query, $request);

        return view('bac-secretariat.rfqs.index', [
            'rfqs' => $query->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary(),
            'filters' => $request->only(['search', 'fiscal_year', 'status', 'date_from', 'date_to']),
            'fiscalYears' => Rfq::query()->selectRaw('YEAR(COALESCE(rfq_date, created_at)) as fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year')->filter(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(Request $request): View
    {
        $sourceResolution = $this->selectedResolution($request->input('source_bac_resolution_id'));
        $sourceDocument = $sourceResolution?->sourcePrDocument ?: $this->selectedPr($request->input('source_pr_document_id'));
        $rfq = new Rfq($this->defaultData($sourceDocument, $sourceResolution, $request->user()->id));
        $rfq->setRelation('items', $this->defaultItems($sourceDocument));

        AuditLogger::log('RFQ', 'RFQ Create Page Viewed', 'BAC Secretariat opened RFQ creation.');

        return view('bac-secretariat.rfqs.create', [
            'rfq' => $rfq,
            'sourceDocument' => $sourceDocument,
            'sourceResolution' => $sourceResolution,
            'eligiblePrs' => $this->eligiblePrs()->with('submittingOffice')->whereDoesntHave('rfqs')->latest('updated_at')->get(),
            'eligibleResolutions' => $this->eligibleResolutions()
                ->with(['sourcePrDocument.submittingOffice'])
                ->whereDoesntHave('rfqs')
                ->whereDoesntHave('sourcePrDocument.rfqs')
                ->latest('updated_at')
                ->get(),
            'recentDrafts' => app(DocumentDraftService::class)->getRfqDraftsForBacSecretariat($request->user(), 5),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $isSubmitting = $request->input('save_action') === 'submit';
        $validated = $this->validatedRfq($request, $isSubmitting);
        $sourceResolution = $this->selectedResolution($validated['source_bac_resolution_id'] ?? null);
        $sourceDocument = $sourceResolution?->sourcePrDocument ?: $this->selectedPr($validated['source_pr_document_id'] ?? null);
        $rfq = null;

        DB::transaction(function () use ($request, $validated, $sourceDocument, $sourceResolution, $isSubmitting, &$rfq) {
            $rfq = Rfq::create([
                ...$this->payload($validated, $sourceDocument, $sourceResolution),
                'status' => $isSubmitting ? Rfq::STATUS_SUBMITTED : Rfq::STATUS_DRAFT,
                'prepared_by_user_id' => $request->user()->id,
                'submitted_by_user_id' => $isSubmitting ? $request->user()->id : null,
                'submitted_at' => $isSubmitting ? now() : null,
            ]);

            $this->syncItems($rfq, $validated['items'] ?? []);

            AuditLogger::log('RFQ', $isSubmitting ? 'RFQ Submitted' : 'RFQ Draft Created', $isSubmitting ? 'BAC Secretariat submitted an RFQ.' : 'BAC Secretariat created an RFQ draft.', $rfq);

            app(SvpChainService::class)->linkRfq($rfq->refresh(), $request->user(), $isSubmitting ? 'RFQ submitted' : 'RFQ draft created');
        });

        return redirect()
            ->route('bac-secretariat.rfqs.show', $rfq)
            ->with('status', $isSubmitting ? 'RFQ submitted.' : 'RFQ draft saved.');
    }

    public function show(Rfq $rfq): View
    {
        $rfq->load(['sourcePrDocument.submittingOffice', 'sourceBacResolution', 'preparedBy', 'submittedBy', 'items']);
        AuditLogger::log('RFQ', 'RFQ Viewed', 'BAC Secretariat viewed an RFQ.', $rfq);

        return view('bac-secretariat.rfqs.show', ['rfq' => $rfq]);
    }

    public function edit(Rfq $rfq): View|RedirectResponse
    {
        if (! $rfq->isEditable()) {
            return redirect()->route('bac-secretariat.rfqs.show', $rfq)->with('error', 'Only draft or returned RFQs can be edited.');
        }

        $rfq->load(['sourcePrDocument.submittingOffice', 'sourceBacResolution', 'items']);

        return view('bac-secretariat.rfqs.edit', [
            'rfq' => $rfq,
            'sourceDocument' => $rfq->sourcePrDocument,
            'sourceResolution' => $rfq->sourceBacResolution,
            'eligiblePrs' => $this->eligiblePrs()->with('submittingOffice')->latest('updated_at')->get(),
            'eligibleResolutions' => $this->eligibleResolutions()->with(['sourcePrDocument.submittingOffice'])->latest('updated_at')->get(),
        ]);
    }

    public function update(Request $request, Rfq $rfq): RedirectResponse
    {
        if (! $rfq->isEditable()) {
            return back()->with('error', 'Only draft or returned RFQs can be updated.');
        }

        $isSubmitting = $request->input('save_action') === 'submit';
        $validated = $this->validatedRfq($request, $isSubmitting, $rfq);
        $sourceResolution = $this->selectedResolution($validated['source_bac_resolution_id'] ?? null) ?: $rfq->sourceBacResolution;
        $sourceDocument = $sourceResolution?->sourcePrDocument ?: ($this->selectedPr($validated['source_pr_document_id'] ?? null) ?: $rfq->sourcePrDocument);

        DB::transaction(function () use ($request, $validated, $rfq, $sourceDocument, $sourceResolution, $isSubmitting) {
            $oldValues = $rfq->only(['rfq_number', 'status', 'abc_amount']);

            $rfq->update([
                ...$this->payload($validated, $sourceDocument, $sourceResolution),
                'status' => $isSubmitting ? Rfq::STATUS_SUBMITTED : Rfq::STATUS_DRAFT,
                'submitted_by_user_id' => $isSubmitting ? $request->user()->id : $rfq->submitted_by_user_id,
                'submitted_at' => $isSubmitting ? now() : $rfq->submitted_at,
            ]);

            $this->syncItems($rfq, $validated['items'] ?? []);

            AuditLogger::log('RFQ', $isSubmitting ? 'RFQ Submitted' : 'RFQ Updated', $isSubmitting ? 'BAC Secretariat submitted an RFQ.' : 'BAC Secretariat updated an RFQ.', $rfq, $oldValues, $rfq->only(['rfq_number', 'status', 'abc_amount']));

            app(SvpChainService::class)->linkRfq($rfq->refresh(), $request->user(), $isSubmitting ? 'RFQ submitted' : 'RFQ updated');
        });

        return redirect()
            ->route('bac-secretariat.rfqs.show', $rfq)
            ->with('status', $isSubmitting ? 'RFQ submitted.' : 'RFQ updated.');
    }

    public function submit(Request $request, Rfq $rfq): RedirectResponse
    {
        if (! $rfq->canSubmit()) {
            return back()->with('error', 'Only draft or returned RFQs can be submitted.');
        }

        if (! filled($rfq->document_text)) {
            return back()->with('error', 'RFQ content is empty. Please complete the document before submitting.');
        }

        $oldStatus = $rfq->status;
        $rfq->update([
            'status' => Rfq::STATUS_SUBMITTED,
            'submitted_by_user_id' => $request->user()->id,
            'submitted_at' => now(),
        ]);

        AuditLogger::log('RFQ', 'RFQ Submitted', 'BAC Secretariat submitted an RFQ.', $rfq, ['status' => $oldStatus], ['status' => Rfq::STATUS_SUBMITTED]);

        app(SvpChainService::class)->linkRfq($rfq->refresh(), $request->user(), 'RFQ submitted');

        return back()->with('status', 'RFQ submitted.');
    }

    public function print(Rfq $rfq): View
    {
        $rfq->load(['sourcePrDocument.submittingOffice', 'sourceBacResolution', 'preparedBy', 'submittedBy', 'items']);
        AuditLogger::log('RFQ', 'RFQ Print Viewed', 'BAC Secretariat opened RFQ print view.', $rfq);

        return view('bac-secretariat.rfqs.print', ['rfq' => $rfq]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();
            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('document_reference_number', 'like', "%{$search}%")
                    ->orWhere('rfq_number', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%")
                    ->orWhereHas('sourcePrDocument', fn (Builder $pr) => $pr->where('document_reference_number', 'like', "%{$search}%")
                        ->orWhere('tracking_number', 'like', "%{$search}%")
                        ->orWhere('pr_no', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhereHas('submittingOffice', fn (Builder $office) => $office->where('name', 'like', "%{$search}%")));
            });
        });

        $query->when($request->filled('fiscal_year'), fn (Builder $builder) => $builder->whereYear('rfq_date', $request->input('fiscal_year')));
        $query->when($request->filled('created_year'), fn (Builder $builder) => $builder->where('created_year', $request->input('created_year')));
        $query->when($request->filled('created_month'), fn (Builder $builder) => $builder->where('created_month', $request->input('created_month')));
        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', fn (Builder $builder) => $builder->where('status', $request->input('status')));
        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('updated_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('updated_at', '<=', $request->date('date_to')));
    }

    private function validatedRfq(Request $request, bool $submitting, ?Rfq $rfq = null): array
    {
        $items = $this->filteredItems($this->itemsFromJson($request->input('items_json')));
        $request->merge([
            'items' => $items,
            'abc_amount' => $this->numericString($request->input('abc_amount')),
        ]);

        return Validator::make($request->all(), [
            'source_pr_document_id' => ['nullable', 'exists:procurement_documents,id'],
            'source_bac_resolution_id' => ['nullable', 'exists:bac_resolutions,id'],
            'rfq_number' => [$submitting ? 'required' : 'nullable', 'string', 'max:255', Rule::unique('rfqs', 'rfq_number')->ignore($rfq?->id)],
            'rfq_date' => [$submitting ? 'required' : 'nullable', 'date'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'supplier_address' => ['nullable', 'string'],
            'procurement_officer_name' => ['nullable', 'string', 'max:255'],
            'procurement_officer_designation' => ['nullable', 'string', 'max:255'],
            'abc_amount' => ['nullable', 'numeric', 'min:0'],
            'purpose' => ['nullable', 'string'],
            'delivery_period' => ['nullable', 'string', 'max:255'],
            'warranty_text' => ['nullable', 'string'],
            'price_validity_text' => ['nullable', 'string'],
            'philgeps_requirement_text' => ['nullable', 'string'],
            'mayors_permit_requirement_text' => ['nullable', 'string'],
            'document_html' => ['nullable', 'string'],
            'document_text' => [$submitting ? 'required' : 'nullable', 'string'],
            'items_json' => ['nullable', 'json'],
            'items' => ['nullable', 'array'],
            'remarks' => ['nullable', 'string'],
        ])->validate();
    }

    private function payload(array $validated, ?ProcurementDocument $sourceDocument, ?BacResolution $sourceResolution): array
    {
        return [
            'source_pr_document_id' => $sourceDocument?->id,
            'source_bac_resolution_id' => $sourceResolution?->id,
            'rfq_number' => $validated['rfq_number'] ?? null,
            'rfq_date' => $validated['rfq_date'] ?? null,
            'supplier_name' => $validated['supplier_name'] ?? null,
            'supplier_address' => $validated['supplier_address'] ?? null,
            'procurement_officer_name' => $validated['procurement_officer_name'] ?? null,
            'procurement_officer_designation' => $validated['procurement_officer_designation'] ?? null,
            'abc_amount' => filled($validated['abc_amount'] ?? null) ? $this->money($validated['abc_amount']) : null,
            'purpose' => $validated['purpose'] ?? null,
            'delivery_period' => $validated['delivery_period'] ?? null,
            'warranty_text' => $validated['warranty_text'] ?? null,
            'price_validity_text' => $validated['price_validity_text'] ?? null,
            'philgeps_requirement_text' => $validated['philgeps_requirement_text'] ?? null,
            'mayors_permit_requirement_text' => $validated['mayors_permit_requirement_text'] ?? null,
            'document_html' => $this->sanitizeHtml($validated['document_html'] ?? null),
            'document_text' => $validated['document_text'] ?? null,
            'items_json' => $this->filteredItems($validated['items'] ?? []),
            'remarks' => $validated['remarks'] ?? null,
        ];
    }

    private function syncItems(Rfq $rfq, array $items): void
    {
        $rfq->items()->delete();

        foreach (array_values($items) as $index => $item) {
            $quantity = $this->money($item['quantity'] ?? 0);
            $unitPrice = $this->money($item['unit_price'] ?? 0);
            $total = filled($item['total'] ?? null) ? $this->money($item['total']) : $quantity * $unitPrice;

            RfqItem::create([
                'rfq_id' => $rfq->id,
                'item_no' => $item['item_no'] ?? null,
                'description' => $item['description'] ?? null,
                'quantity' => $quantity ?: null,
                'unit_of_issue' => $item['unit_of_issue'] ?? null,
                'unit_price' => $unitPrice ?: null,
                'total' => $total ?: null,
                'sort_order' => $index,
            ]);
        }
    }

    private function defaultData(?ProcurementDocument $sourceDocument, ?BacResolution $sourceResolution, int $userId): array
    {
        return [
            'source_pr_document_id' => $sourceDocument?->id,
            'source_bac_resolution_id' => $sourceResolution?->id,
            'rfq_date' => now()->toDateString(),
            'abc_amount' => $sourceResolution?->abc_amount ?? $sourceDocument?->total_amount,
            'purpose' => $sourceResolution?->project_title ?? $sourceResolution?->title ?? $sourceDocument?->purpose ?? $sourceDocument?->title,
            'procurement_officer_name' => 'ROLAND P. FELICILDA',
            'procurement_officer_designation' => 'Procurement Officer',
            'warranty_text' => 'Warranty shall be a period of six (6) months for supplies and materials. One (1) year for equipment, from date of acceptance.',
            'price_validity_text' => 'Price validity shall be for a period of sixty (60) calendar days.',
            'philgeps_requirement_text' => 'PhilGeps Registration Certificate/Number shall be provided in the quotation.',
            'mayors_permit_requirement_text' => "Mayor's Business Permit shall be attached upon submission of the quotation.",
            'prepared_by_user_id' => $userId,
            'status' => Rfq::STATUS_DRAFT,
        ];
    }

    private function defaultItems(?ProcurementDocument $sourceDocument)
    {
        if (! $sourceDocument) {
            return collect();
        }

        $sourceDocument->loadMissing('purchaseRequestItems');

        return $sourceDocument->purchaseRequestItems->map(fn ($item, int $index) => new RfqItem([
            'item_no' => $item->item_no ?: (string) ($index + 1),
            'description' => $item->description ?: $item->item_description,
            'quantity' => $item->quantity,
            'unit_of_issue' => $item->unit ?: $item->unit_of_issue,
            'unit_price' => $item->estimated_unit_cost,
            'total' => $item->estimated_total_cost ?? $item->estimated_cost,
            'sort_order' => $index,
        ]));
    }

    private function filteredItems(array $items): array
    {
        return collect($items)->filter(fn (array $item) => collect($item)->some(fn ($value) => filled($value)))
            ->map(fn (array $item) => [
                'item_no' => $item['item_no'] ?? null,
                'description' => $item['description'] ?? null,
                'quantity' => $this->numericString($item['quantity'] ?? null),
                'unit_of_issue' => $item['unit_of_issue'] ?? null,
                'unit_price' => $this->numericString($item['unit_price'] ?? null),
                'total' => $this->numericString($item['total'] ?? null),
            ])->values()->all();
    }

    private function itemsFromJson(mixed $itemsJson): array
    {
        $decoded = filled($itemsJson) ? json_decode((string) $itemsJson, true) : [];

        return is_array($decoded) ? $decoded : [];
    }

    private function eligiblePrs(): Builder
    {
        return ProcurementDocument::query()
            ->whereIn('document_type', ['PR', 'Purchase Request'])
            ->where(fn (Builder $query) => $this->readyPrForRfqPath($query));
    }

    private function eligibleResolutions(): Builder
    {
        return BacResolution::query()
            ->whereIn('status', [BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR, BacResolution::STATUS_APPROVED_BY_HOPE, 'approved', 'confirmed', 'ready_for_rfq'])
            ->where(function (Builder $query) {
                $query->whereDoesntHave('sourcePrDocument')
                    ->orWhereHas('sourcePrDocument', fn (Builder $document) => $this->readyForRfqPath($document));
            });
    }

    private function readyForRfqPath(Builder $query): Builder
    {
        return $query->where(function (Builder $path) {
            $path->where('status', ProcurementDocument::STATUS_READY_FOR_RFQ)
                ->orWhere(function (Builder $lowValue) {
                    $lowValue->whereIn('status', $this->preResolutionRfqStatuses())
                        ->where('total_amount', '>', 0)
                        ->where('total_amount', '<=', SvpChainService::POSTING_MIN_AMOUNT);
                });
        });
    }

    private function readyPrForRfqPath(Builder $query): Builder
    {
        return $query->where(function (Builder $path) {
            $path->whereIn('status', [ProcurementDocument::STATUS_READY_FOR_RFQ, 'ready_for_rfq'])
                ->orWhere(function (Builder $lowValue) {
                    $lowValue->whereIn('status', $this->preResolutionRfqStatuses())
                        ->where('total_amount', '>', 0)
                        ->where('total_amount', '<=', SvpChainService::POSTING_MIN_AMOUNT);
                });
        });
    }

    private function preResolutionRfqStatuses(): array
    {
        return [
            ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION,
            ProcurementDocument::STATUS_BAC_RESOLUTION_CREATED,
            ProcurementDocument::STATUS_BAC_RESOLUTION_RETURNED_TO_END_USER,
            ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW,
            ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW,
            ProcurementDocument::STATUS_PENDING_APPROVAL,
        ];
    }

    private function selectedPr(mixed $id): ?ProcurementDocument
    {
        return filled($id) ? $this->eligiblePrs()->with(['submittingOffice', 'purchaseRequestItems'])->whereKey($id)->first() : null;
    }

    private function selectedResolution(mixed $id): ?BacResolution
    {
        return filled($id) ? $this->eligibleResolutions()->with(['sourcePrDocument.submittingOffice', 'sourcePrDocument.purchaseRequestItems'])->whereKey($id)->first() : null;
    }

    private function summary(): array
    {
        return [
            'draft' => Rfq::where('status', Rfq::STATUS_DRAFT)->count(),
            'submitted' => Rfq::where('status', Rfq::STATUS_SUBMITTED)->count(),
            'issued' => Rfq::where('status', Rfq::STATUS_ISSUED)->count(),
            'quoted' => Rfq::where('status', Rfq::STATUS_QUOTED)->count(),
        ];
    }

    private function statuses(): array
    {
        return [Rfq::STATUS_DRAFT, Rfq::STATUS_SUBMITTED, Rfq::STATUS_ISSUED, Rfq::STATUS_QUOTED, Rfq::STATUS_RETURNED, Rfq::STATUS_CANCELLED];
    }

    private function numericString(mixed $value): mixed
    {
        return filled($value) ? str_replace([',', 'PHP', 'Php', 'php'], '', (string) $value) : $value;
    }

    private function money(mixed $value): float
    {
        return filled($value) ? max((float) $this->numericString($value), 0) : 0.0;
    }

    private function sanitizeHtml(?string $html): ?string
    {
        if (! filled($html)) {
            return null;
        }

        $html = preg_replace('/<\s*(script|style|iframe|object|embed)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $html);
        $html = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/is', '', $html);
        $html = preg_replace('/\s+(href|src)\s*=\s*("|\')\s*javascript:.*?\2/is', '', $html);

        return trim($html);
    }
}
