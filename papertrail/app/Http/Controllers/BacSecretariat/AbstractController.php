<?php

namespace App\Http\Controllers\BacSecretariat;

use App\Http\Controllers\Controller;
use App\Models\AbstractQuotation;
use App\Models\AbstractQuotationItem;
use App\Models\BacResolution;
use App\Models\ProcurementDocument;
use App\Models\Rfq;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentDraftService;
use App\Services\SignatureRequestService;
use App\Services\SvpChainService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AbstractController extends Controller
{
    public function index(Request $request): View
    {
        AuditLogger::log('Abstract', 'Abstract Page Viewed', 'BAC Secretariat viewed Abstracts.');

        $query = AbstractQuotation::query()->with(['sourcePrDocument.submittingOffice', 'sourceRfq', 'preparedBy']);
        $this->applyFilters($query, $request);

        return view('bac-secretariat.abstracts.index', [
            'abstracts' => $query->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary(),
            'filters' => $request->only(['search', 'fiscal_year', 'status', 'date_from', 'date_to']),
            'fiscalYears' => AbstractQuotation::query()->selectRaw('YEAR(COALESCE(abstract_date, created_at)) as fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year')->filter(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(Request $request): View
    {
        $sourceRfq = $this->selectedRfq($request->input('source_rfq_id'));
        $sourceResolution = $this->readyBacResolutionForRfq($sourceRfq) ?: $this->selectedResolution($request->input('source_bac_resolution_id'));
        $sourceDocument = $sourceRfq?->sourcePrDocument ?: $sourceResolution?->sourcePrDocument ?: $this->selectedPr($request->input('source_pr_document_id'));

        $abstract = new AbstractQuotation($this->defaultData($sourceDocument, $sourceRfq, $sourceResolution, $request->user()->id));
        $abstract->setRelation('items', $this->defaultItems($sourceDocument, $sourceRfq));

        AuditLogger::log('Abstract', 'Abstract Create Page Viewed', 'BAC Secretariat opened Abstract creation.');

        return view('bac-secretariat.abstracts.create', [
            'abstract' => $abstract,
            'sourceDocument' => $sourceDocument,
            'sourceRfq' => $sourceRfq,
            'sourceResolution' => $sourceResolution,
            'eligibleRfqs' => $this->eligibleRfqs()->with(['sourcePrDocument.submittingOffice', 'sourceBacResolution'])->latest('updated_at')->get(),
            'eligibleResolutions' => $this->eligibleResolutions()->with(['sourcePrDocument.submittingOffice'])->latest('updated_at')->get(),
            'eligiblePrs' => $this->eligiblePrs()->with('submittingOffice')->latest('updated_at')->get(),
            'recentDrafts' => app(DocumentDraftService::class)->getAbstractDraftsForBacSecretariat($request->user(), 5),
        ]);
    }

    public function createBidsAsRead(Request $request): View
    {
        $abstract = new AbstractQuotation([
            ...$this->defaultData(null, null, null, $request->user()->id),
            'abstract_number' => 'AOB-AS-READ-' . now()->format('Ymd-His'),
            'purpose' => 'Abstract of Bids as Read',
        ]);

        AuditLogger::log('Competitive Bidding', 'Abstract of Bids as Read Page Viewed', 'BAC Secretariat opened Abstract of Bids as Read template.');

        return view('bac-secretariat.competitive-bidding.abstract-bids-as-read', [
            'abstract' => $abstract,
            'signatoryUsers' => $this->abstractSignatoryUsers(),
        ]);
    }

    public function createBidsAsCalculated(Request $request): View
    {
        $abstract = new AbstractQuotation([
            ...$this->defaultData(null, null, null, $request->user()->id),
            'abstract_number' => 'AOB-AS-CALC-' . now()->format('Ymd-His'),
            'purpose' => 'Abstract of Bids as Calculated',
        ]);

        AuditLogger::log('Competitive Bidding', 'Abstract of Bids as Calculated Page Viewed', 'BAC Secretariat opened Abstract of Bids as Calculated template.');

        return view('bac-secretariat.competitive-bidding.abstract-bids-as-calculated', [
            'abstract' => $abstract,
            'signatoryUsers' => $this->abstractSignatoryUsers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $isSubmitting = $request->input('save_action') === 'submit';
        $validated = $this->validatedAbstract($request, $isSubmitting);
        $sourceRfq = $this->selectedRfq($validated['source_rfq_id'] ?? null);
        $sourceResolution = $this->readyBacResolutionForRfq($sourceRfq) ?: $this->selectedResolution($validated['source_bac_resolution_id'] ?? null);
        $sourceDocument = $sourceRfq?->sourcePrDocument ?: $sourceResolution?->sourcePrDocument ?: $this->selectedPr($validated['source_pr_document_id'] ?? null);
        $abstract = null;

        if (($validated['source_rfq_id'] ?? null) && ! $sourceRfq) {
            return back()
                ->with('error', $this->bacResolutionRequiredMessage())
                ->withInput();
        }

        if ($this->missingBacResolutionForAbstract($sourceRfq, $sourceResolution)) {
            return back()
                ->with('error', $this->bacResolutionRequiredMessage())
                ->withInput();
        }

        $requiresSignatureRouting = $this->isCompetitiveBiddingAbstractOfBids($validated['purpose'] ?? null);
        $signatureConfig = $requiresSignatureRouting
            ? $this->abstractSignatureRequestConfig($validated['committee'] ?? [])
            : ['signers' => [], 'missing' => []];

        if ($isSubmitting && $requiresSignatureRouting && ! empty($signatureConfig['missing'])) {
            return back()
                ->with('error', 'Assign signer accounts for '.implode(', ', $signatureConfig['missing']).' before submitting this Abstract of Bids for electronic signatures.')
                ->withInput();
        }

        DB::transaction(function () use ($request, $validated, $sourceDocument, $sourceRfq, $sourceResolution, $isSubmitting, $requiresSignatureRouting, $signatureConfig, &$abstract) {
            $abstract = AbstractQuotation::create([
                ...$this->payload($validated, $sourceDocument, $sourceRfq, $sourceResolution),
                'status' => $isSubmitting ? AbstractQuotation::STATUS_SUBMITTED : AbstractQuotation::STATUS_DRAFT,
                'prepared_by_user_id' => $request->user()->id,
                'submitted_by_user_id' => $isSubmitting ? $request->user()->id : null,
                'submitted_at' => $isSubmitting ? now() : null,
            ]);

            $this->syncItems($abstract, $validated['items'] ?? []);

            AuditLogger::log('Abstract', $isSubmitting ? 'Abstract Submitted' : 'Abstract Draft Created', $isSubmitting ? 'BAC Secretariat submitted an Abstract.' : 'BAC Secretariat created an Abstract draft.', $abstract);

            app(SvpChainService::class)->linkAbstract($abstract->refresh(), $request->user(), $isSubmitting ? 'Abstract submitted' : 'Abstract draft created');

            if ($isSubmitting && $requiresSignatureRouting) {
                $this->routeAbstractSignatureRequests($abstract->refresh(), $signatureConfig, $request->user());
            }
        });

        return redirect()
            ->route('bac-secretariat.abstracts.show', $abstract)
            ->with('status', $isSubmitting ? 'Abstract submitted.' : 'Abstract draft saved.');
    }

    public function show(AbstractQuotation $abstract): View
    {
        $abstract->load(['sourcePrDocument.submittingOffice', 'sourceRfq', 'sourceBacResolution', 'preparedBy', 'submittedBy', 'items']);
        AuditLogger::log('Abstract', 'Abstract Viewed', 'BAC Secretariat viewed an Abstract.', $abstract);

        return view('bac-secretariat.abstracts.show', ['abstract' => $abstract]);
    }

    public function edit(AbstractQuotation $abstract): View|RedirectResponse
    {
        if (! $abstract->isEditable()) {
            return redirect()->route('bac-secretariat.abstracts.show', $abstract)->with('error', 'Only draft or returned Abstracts can be edited.');
        }

        $abstract->load(['sourcePrDocument.submittingOffice', 'sourceRfq', 'sourceBacResolution', 'items']);

        return view('bac-secretariat.abstracts.edit', [
            'abstract' => $abstract,
            'sourceDocument' => $abstract->sourcePrDocument,
            'sourceRfq' => $abstract->sourceRfq,
            'sourceResolution' => $abstract->sourceBacResolution,
        ]);
    }

    public function update(Request $request, AbstractQuotation $abstract): RedirectResponse
    {
        if (! $abstract->isEditable()) {
            return back()->with('error', 'Only draft or returned Abstracts can be updated.');
        }

        $isSubmitting = $request->input('save_action') === 'submit';
        $validated = $this->validatedAbstract($request, $isSubmitting, $abstract);
        $sourceRfq = $this->selectedRfq($validated['source_rfq_id'] ?? null) ?: $abstract->sourceRfq;
        $sourceResolution = $this->readyBacResolutionForRfq($sourceRfq) ?: ($this->selectedResolution($validated['source_bac_resolution_id'] ?? null) ?: $abstract->sourceBacResolution);
        $sourceDocument = $sourceRfq?->sourcePrDocument ?: $sourceResolution?->sourcePrDocument ?: ($this->selectedPr($validated['source_pr_document_id'] ?? null) ?: $abstract->sourcePrDocument);

        if ($this->missingBacResolutionForAbstract($sourceRfq, $sourceResolution)) {
            return back()
                ->with('error', $this->bacResolutionRequiredMessage())
                ->withInput();
        }

        $requiresSignatureRouting = $this->isCompetitiveBiddingAbstractOfBids($validated['purpose'] ?? $abstract->purpose);
        $signatureConfig = $requiresSignatureRouting
            ? $this->abstractSignatureRequestConfig($validated['committee'] ?? [])
            : ['signers' => [], 'missing' => []];

        if ($isSubmitting && $requiresSignatureRouting && ! empty($signatureConfig['missing'])) {
            return back()
                ->with('error', 'Assign signer accounts for '.implode(', ', $signatureConfig['missing']).' before submitting this Abstract of Bids for electronic signatures.')
                ->withInput();
        }

        DB::transaction(function () use ($request, $validated, $abstract, $sourceDocument, $sourceRfq, $sourceResolution, $isSubmitting, $requiresSignatureRouting, $signatureConfig) {
            $oldValues = $abstract->only(['abstract_number', 'status', 'lowest_total_amount']);

            $abstract->update([
                ...$this->payload($validated, $sourceDocument, $sourceRfq, $sourceResolution),
                'status' => $isSubmitting ? AbstractQuotation::STATUS_SUBMITTED : AbstractQuotation::STATUS_DRAFT,
                'submitted_by_user_id' => $isSubmitting ? $request->user()->id : $abstract->submitted_by_user_id,
                'submitted_at' => $isSubmitting ? now() : $abstract->submitted_at,
            ]);

            $this->syncItems($abstract, $validated['items'] ?? []);

            AuditLogger::log('Abstract', $isSubmitting ? 'Abstract Submitted' : 'Abstract Updated', $isSubmitting ? 'BAC Secretariat submitted an Abstract.' : 'BAC Secretariat updated an Abstract.', $abstract, $oldValues, $abstract->only(['abstract_number', 'status', 'lowest_total_amount']));

            app(SvpChainService::class)->linkAbstract($abstract->refresh(), $request->user(), $isSubmitting ? 'Abstract submitted' : 'Abstract updated');

            if ($isSubmitting && $requiresSignatureRouting) {
                $this->routeAbstractSignatureRequests($abstract->refresh(), $signatureConfig, $request->user());
            }
        });

        return redirect()
            ->route('bac-secretariat.abstracts.show', $abstract)
            ->with('status', $isSubmitting ? 'Abstract submitted.' : 'Abstract updated.');
    }

    public function submit(Request $request, AbstractQuotation $abstract): RedirectResponse
    {
        if (! $abstract->canSubmit()) {
            return back()->with('error', 'Only draft or returned Abstracts can be submitted.');
        }

        if (! filled($abstract->document_text)) {
            return back()->with('error', 'Abstract content is empty. Please complete the document before submitting.');
        }

        $abstract->loadMissing(['sourceRfq.sourcePrDocument.latestBacResolution', 'sourceRfq.sourceBacResolution', 'sourceBacResolution']);
        $sourceResolution = $abstract->sourceBacResolution ?: $this->readyBacResolutionForRfq($abstract->sourceRfq);

        if ($this->missingBacResolutionForAbstract($abstract->sourceRfq, $sourceResolution)) {
            return back()->with('error', $this->bacResolutionRequiredMessage());
        }

        if ($sourceResolution && ! $abstract->source_bac_resolution_id) {
            $abstract->forceFill(['source_bac_resolution_id' => $sourceResolution->id])->save();
            $abstract->setRelation('sourceBacResolution', $sourceResolution);
        }

        $requiresSignatureRouting = $this->isCompetitiveBiddingAbstractOfBids($abstract->purpose);
        $signatureConfig = $requiresSignatureRouting
            ? $this->abstractSignatureRequestConfig($abstract->committee_json ?: [])
            : ['signers' => [], 'missing' => []];

        if ($requiresSignatureRouting && ! empty($signatureConfig['missing'])) {
            return back()->with('error', 'Assign signer accounts for '.implode(', ', $signatureConfig['missing']).' before submitting this Abstract of Bids for electronic signatures.');
        }

        $oldStatus = $abstract->status;
        $abstract->update([
            'status' => AbstractQuotation::STATUS_SUBMITTED,
            'submitted_by_user_id' => $request->user()->id,
            'submitted_at' => now(),
        ]);

        AuditLogger::log('Abstract', 'Abstract Submitted', 'BAC Secretariat submitted an Abstract.', $abstract, ['status' => $oldStatus], ['status' => AbstractQuotation::STATUS_SUBMITTED]);

        app(SvpChainService::class)->linkAbstract($abstract->refresh(), $request->user(), 'Abstract submitted');

        if ($requiresSignatureRouting) {
            $this->routeAbstractSignatureRequests($abstract->refresh(), $signatureConfig, $request->user());
        }

        return back()->with('status', 'Abstract submitted.');
    }

    public function print(AbstractQuotation $abstract): View
    {
        $abstract->load(['sourcePrDocument.submittingOffice', 'sourceRfq', 'sourceBacResolution', 'preparedBy', 'submittedBy', 'items']);
        AuditLogger::log('Abstract', 'Abstract Print Viewed', 'BAC Secretariat opened Abstract print view.', $abstract);

        return view('bac-secretariat.abstracts.print', ['abstract' => $abstract]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();
            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('document_reference_number', 'like', "%{$search}%")
                    ->orWhere('abstract_number', 'like', "%{$search}%")
                    ->orWhere('project_name', 'like', "%{$search}%")
                    ->orWhere('implementing_office', 'like', "%{$search}%")
                    ->orWhere('lowest_supplier_name', 'like', "%{$search}%")
                    ->orWhereHas('sourcePrDocument', fn (Builder $pr) => $pr->where('document_reference_number', 'like', "%{$search}%")
                        ->orWhere('tracking_number', 'like', "%{$search}%")
                        ->orWhere('pr_no', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhereHas('submittingOffice', fn (Builder $office) => $office->where('name', 'like', "%{$search}%")));
            });
        });

        $query->when($request->filled('fiscal_year'), fn (Builder $builder) => $builder->whereYear('abstract_date', $request->input('fiscal_year')));
        $query->when($request->filled('created_year'), fn (Builder $builder) => $builder->where('created_year', $request->input('created_year')));
        $query->when($request->filled('created_month'), fn (Builder $builder) => $builder->where('created_month', $request->input('created_month')));
        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', fn (Builder $builder) => $builder->where('status', $request->input('status')));
        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('updated_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('updated_at', '<=', $request->date('date_to')));
    }

    private function validatedAbstract(Request $request, bool $submitting, ?AbstractQuotation $abstract = null): array
    {
        $items = $this->filteredItems($this->jsonArray($request->input('items_json')));
        $suppliers = $this->jsonArray($request->input('suppliers_json'));
        $awards = $this->jsonArray($request->input('awards_json'));
        $committee = $this->jsonArray($request->input('committee_json'));

        $request->merge([
            'items' => $items,
            'suppliers' => $suppliers,
            'awards' => $awards,
            'committee' => $committee,
            'abc_amount' => $this->numericString($request->input('abc_amount')),
            'lowest_total_amount' => $this->numericString($request->input('lowest_total_amount')),
        ]);

        return Validator::make($request->all(), [
            'source_pr_document_id' => ['nullable', 'exists:procurement_documents,id'],
            'source_rfq_id' => ['nullable', 'exists:rfqs,id'],
            'source_bac_resolution_id' => ['nullable', 'exists:bac_resolutions,id'],
            'abstract_number' => [$submitting ? 'required' : 'nullable', 'string', 'max:255', Rule::unique('abstracts', 'abstract_number')->ignore($abstract?->id)],
            'abstract_date' => ['nullable', 'date'],
            'project_name' => [$submitting ? 'required' : 'nullable', 'string', 'max:255'],
            'implementing_office' => ['nullable', 'string', 'max:255'],
            'abc_amount' => ['nullable', 'numeric', 'min:0'],
            'purpose' => ['nullable', 'string'],
            'supplier_1_name' => ['nullable', 'string', 'max:255'],
            'supplier_2_name' => ['nullable', 'string', 'max:255'],
            'supplier_3_name' => ['nullable', 'string', 'max:255'],
            'supplier_4_name' => ['nullable', 'string', 'max:255'],
            'supplier_5_name' => ['nullable', 'string', 'max:255'],
            'lowest_supplier_name' => ['nullable', 'string', 'max:255'],
            'lowest_total_amount' => ['nullable', 'numeric', 'min:0'],
            'document_html' => ['nullable', 'string'],
            'document_text' => [$submitting ? 'required' : 'nullable', 'string'],
            'items_json' => ['nullable', 'json'],
            'suppliers_json' => ['nullable', 'json'],
            'awards_json' => ['nullable', 'json'],
            'committee_json' => ['nullable', 'json'],
            'items' => ['nullable', 'array'],
            'suppliers' => ['nullable', 'array'],
            'awards' => ['nullable', 'array'],
            'committee' => ['nullable', 'array'],
            'remarks' => ['nullable', 'string'],
        ])->validate();
    }

    private function payload(array $validated, ?ProcurementDocument $sourceDocument, ?Rfq $sourceRfq, ?BacResolution $sourceResolution): array
    {
        $supplierSummary = $this->supplierSummary($validated['suppliers'] ?? []);

        return [
            'source_pr_document_id' => $sourceDocument?->id,
            'source_rfq_id' => $sourceRfq?->id,
            'source_bac_resolution_id' => $sourceResolution?->id,
            'abstract_number' => $validated['abstract_number'] ?? null,
            'abstract_date' => $validated['abstract_date'] ?? null,
            'project_name' => $validated['project_name'] ?? null,
            'implementing_office' => $validated['implementing_office'] ?? null,
            'abc_amount' => filled($validated['abc_amount'] ?? null) ? $this->money($validated['abc_amount']) : null,
            'purpose' => $validated['purpose'] ?? null,
            'supplier_1_name' => $validated['supplier_1_name'] ?? null,
            'supplier_2_name' => $validated['supplier_2_name'] ?? null,
            'supplier_3_name' => $validated['supplier_3_name'] ?? null,
            'supplier_4_name' => $validated['supplier_4_name'] ?? null,
            'supplier_5_name' => $validated['supplier_5_name'] ?? null,
            'lowest_supplier_name' => $validated['lowest_supplier_name'] ?? $supplierSummary['lowest_supplier_name'],
            'lowest_total_amount' => filled($validated['lowest_total_amount'] ?? null) ? $this->money($validated['lowest_total_amount']) : $supplierSummary['lowest_total_amount'],
            'document_html' => $this->sanitizeHtml($validated['document_html'] ?? null),
            'document_text' => $validated['document_text'] ?? null,
            'items_json' => $this->filteredItems($validated['items'] ?? []),
            'suppliers_json' => $validated['suppliers'] ?? [],
            'awards_json' => $validated['awards'] ?? [],
            'committee_json' => $validated['committee'] ?? [],
            'remarks' => $validated['remarks'] ?? null,
        ];
    }

    private function syncItems(AbstractQuotation $abstract, array $items): void
    {
        $abstract->items()->delete();

        foreach (array_values($items) as $index => $item) {
            AbstractQuotationItem::create([
                'abstract_id' => $abstract->id,
                'item_no' => $item['item_no'] ?? null,
                'name_of_goods_services' => $item['name_of_goods_services'] ?? null,
                'quantity' => filled($item['quantity'] ?? null) ? $this->money($item['quantity']) : null,
                'unit_of_measure' => $item['unit_of_measure'] ?? null,
                'supplier_1_amount' => filled($item['supplier_1_amount'] ?? null) ? $this->money($item['supplier_1_amount']) : null,
                'supplier_2_amount' => filled($item['supplier_2_amount'] ?? null) ? $this->money($item['supplier_2_amount']) : null,
                'supplier_3_amount' => filled($item['supplier_3_amount'] ?? null) ? $this->money($item['supplier_3_amount']) : null,
                'supplier_4_amount' => filled($item['supplier_4_amount'] ?? null) ? $this->money($item['supplier_4_amount']) : null,
                'supplier_5_amount' => filled($item['supplier_5_amount'] ?? null) ? $this->money($item['supplier_5_amount']) : null,
                'total_lowest_price' => filled($item['total_lowest_price'] ?? null) ? $this->money($item['total_lowest_price']) : null,
                'sort_order' => $index,
            ]);
        }
    }

    private function abstractSignatoryUsers()
    {
        $slots = collect($this->abstractSignatureSlots());
        $roleCodes = $slots->pluck('role_code')->filter()->unique()->values()->all();
        $roleNames = $slots->pluck('role_name')->filter()->unique()->values()->all();

        $users = User::query()
            ->with(['assignedOffice', 'assignedRole'])
            ->where('status', User::STATUS_ACTIVE)
            ->where(function (Builder $query) use ($roleCodes, $roleNames) {
                $query->whereIn('role', $roleNames)
                    ->orWhereIn('role', $roleCodes)
                    ->orWhereHas('assignedRole', function (Builder $role) use ($roleCodes, $roleNames) {
                        $role->whereIn('code', $roleCodes)
                            ->orWhereIn('name', $roleNames);
                    });
            })
            ->orderBy('name')
            ->get();

        return $users->isNotEmpty()
            ? $users
            : User::query()
                ->with(['assignedOffice', 'assignedRole'])
                ->where('status', User::STATUS_ACTIVE)
                ->orderBy('name')
                ->get();
    }

    private function abstractSignatureRequestConfig(array $committee): array
    {
        $missing = [];
        $signers = [];
        $usedUserIds = [];

        foreach ($this->abstractSignatureSlots() as $roleKey => $slot) {
            $details = $this->committeeEntry($committee, $roleKey);
            $printedName = trim((string) ($details['name'] ?? ''));
            $accountCode = trim((string) ($details['account_code'] ?? ''));
            $designation = trim((string) ($details['designation'] ?? ''));
            $signer = $this->userByAccountCode($accountCode)
                ?: $this->userByPrintedName($printedName, $usedUserIds)
                ?: $this->activeUserByRoleOrCode($slot['role_code'], $slot['role_name'], $usedUserIds);

            if (! $signer) {
                $missing[] = $slot['label'];
                continue;
            }

            $usedUserIds[] = $signer->id;
            $signers[] = [
                'slot' => $slot['slot'],
                'label' => $slot['label'],
                'account_code' => $signer->user_id,
                'role_code' => $slot['role_code'],
                'role_name' => $designation !== '' ? $designation : $slot['designation'],
                'designation' => $designation !== '' ? $designation : $slot['designation'],
                'printed_name' => $printedName !== '' ? $printedName : ($signer->typed_signature_name ?: $signer->name),
                'order' => $slot['order'],
                'required' => true,
            ];
        }

        return [
            'signing_mode' => 'parallel',
            'signers' => $signers,
            'missing' => $missing,
        ];
    }

    private function routeAbstractSignatureRequests(AbstractQuotation $abstract, array $signatureConfig, User $requestedBy): void
    {
        $signatureRequests = app(SignatureRequestService::class)->createRequestsForDocument(
            $abstract,
            'abstract',
            $signatureConfig,
            $requestedBy,
        );

        AuditLogger::signature('signature_requests_created', $abstract, [
            'description' => 'BAC Secretariat routed Abstract of Bids for electronic signatures.',
            'metadata' => [
                'signature_request_count' => $signatureRequests->count(),
                'assigned_signature_request_count' => $signatureRequests->whereNotNull('requested_to_user_id')->count(),
            ],
        ]);
    }

    private function abstractSignatureSlots(): array
    {
        return [
            'bac_chairperson' => ['slot' => 'bac_chairperson', 'label' => 'BAC Chairperson', 'designation' => 'BAC Chairperson', 'role_code' => 'bac_chair', 'role_name' => User::ROLE_BAC_CHAIR, 'order' => 1],
            'bac_member_1' => ['slot' => 'bac_member_1', 'label' => 'BAC Member', 'designation' => 'BAC Member', 'role_code' => 'bac_member', 'role_name' => User::ROLE_BAC_MEMBER, 'order' => 2],
            'bac_member_2' => ['slot' => 'bac_member_2', 'label' => 'BAC Member', 'designation' => 'BAC Member', 'role_code' => 'bac_member', 'role_name' => User::ROLE_BAC_MEMBER, 'order' => 3],
            'bac_member_alternate_1' => ['slot' => 'bac_member_alternate_1', 'label' => 'BAC Member-Alternate', 'designation' => 'BAC Member-Alternate', 'role_code' => 'bac_member', 'role_name' => User::ROLE_BAC_MEMBER, 'order' => 4],
            'bac_member_alternate_2' => ['slot' => 'bac_member_alternate_2', 'label' => 'BAC Member-Alternate', 'designation' => 'BAC Member-Alternate', 'role_code' => 'bac_member', 'role_name' => User::ROLE_BAC_MEMBER, 'order' => 5],
            'bac_twg' => ['slot' => 'bac_twg', 'label' => 'BAC-TWG', 'designation' => 'BAC-TWG', 'role_code' => 'bac_twg', 'role_name' => 'BAC-TWG', 'order' => 6],
        ];
    }

    private function committeeEntry(array $committee, string $roleKey): array
    {
        $entry = $committee[$roleKey] ?? [];

        if (is_array($entry)) {
            return $entry;
        }

        return [
            'name' => (string) $entry,
            'account_code' => (string) ($committee[$roleKey.'_account_code'] ?? ''),
            'designation' => (string) ($committee[$roleKey.'_designation'] ?? ''),
        ];
    }

    private function userByAccountCode(?string $accountCode): ?User
    {
        if (! filled($accountCode)) {
            return null;
        }

        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->where('user_id', $accountCode)
            ->first();
    }

    private function userByPrintedName(?string $printedName, array $excludedUserIds = []): ?User
    {
        if (! filled($printedName)) {
            return null;
        }

        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->when($excludedUserIds !== [], fn (Builder $query) => $query->whereNotIn('id', $excludedUserIds))
            ->where(function (Builder $query) use ($printedName) {
                $query->where('name', $printedName)
                    ->orWhere('typed_signature_name', $printedName);
            })
            ->first();
    }

    private function activeUserByRoleOrCode(string $roleCode, string $roleName, array $excludedUserIds = []): ?User
    {
        return User::query()
            ->with('assignedRole')
            ->where('status', User::STATUS_ACTIVE)
            ->when($excludedUserIds !== [], fn (Builder $query) => $query->whereNotIn('id', $excludedUserIds))
            ->where(function (Builder $query) use ($roleCode, $roleName) {
                $query->where('role', $roleName)
                    ->orWhere('role', $roleCode)
                    ->orWhereHas('assignedRole', function (Builder $role) use ($roleCode, $roleName) {
                        $role->where('code', $roleCode)
                            ->orWhere('name', $roleName);
                    });
            })
            ->orderBy('name')
            ->first();
    }

    private function isCompetitiveBiddingAbstractOfBids(?string $purpose): bool
    {
        $purpose = strtolower((string) $purpose);

        return str_contains($purpose, 'abstract of bids as read')
            || str_contains($purpose, 'abstract of bids as calculated');
    }

    private function defaultData(?ProcurementDocument $sourceDocument, ?Rfq $sourceRfq, ?BacResolution $sourceResolution, int $userId): array
    {
        return [
            'source_pr_document_id' => $sourceDocument?->id,
            'source_rfq_id' => $sourceRfq?->id,
            'source_bac_resolution_id' => $sourceResolution?->id,
            'abstract_date' => now()->toDateString(),
            'project_name' => $sourceRfq?->purpose ?? $sourceResolution?->project_title ?? $sourceResolution?->title ?? $sourceDocument?->purpose ?? $sourceDocument?->title,
            'implementing_office' => $sourceDocument?->submittingOffice?->name ?? $sourceResolution?->requesting_office_name,
            'abc_amount' => $sourceRfq?->abc_amount ?? $sourceResolution?->abc_amount ?? $sourceDocument?->total_amount,
            'purpose' => $sourceRfq?->purpose ?? $sourceDocument?->purpose,
            'prepared_by_user_id' => $userId,
            'status' => AbstractQuotation::STATUS_DRAFT,
        ];
    }

    private function defaultItems(?ProcurementDocument $sourceDocument, ?Rfq $sourceRfq)
    {
        if ($sourceRfq) {
            $sourceRfq->loadMissing('items');

            return $sourceRfq->items->map(fn ($item, int $index) => new AbstractQuotationItem([
                'item_no' => $item->item_no ?: (string) ($index + 1),
                'name_of_goods_services' => $item->description,
                'quantity' => $item->quantity,
                'unit_of_measure' => $item->unit_of_issue,
                'supplier_1_amount' => $item->unit_price,
                'total_lowest_price' => $item->total,
                'sort_order' => $index,
            ]));
        }

        if (! $sourceDocument) {
            return collect();
        }

        $sourceDocument->loadMissing('purchaseRequestItems');

        return $sourceDocument->purchaseRequestItems->map(fn ($item, int $index) => new AbstractQuotationItem([
            'item_no' => $item->item_no ?: (string) ($index + 1),
            'name_of_goods_services' => $item->description ?: $item->item_description,
            'quantity' => $item->quantity,
            'unit_of_measure' => $item->unit ?: $item->unit_of_issue,
            'sort_order' => $index,
        ]));
    }

    private function filteredItems(array $items): array
    {
        return collect($items)->filter(fn (array $item) => collect($item)->some(fn ($value) => filled($value)))
            ->map(fn (array $item) => [
                'item_no' => $item['item_no'] ?? null,
                'name_of_goods_services' => $item['name_of_goods_services'] ?? null,
                'quantity' => $this->numericString($item['quantity'] ?? null),
                'unit_of_measure' => $item['unit_of_measure'] ?? null,
                'supplier_1_amount' => $this->numericString($item['supplier_1_amount'] ?? null),
                'supplier_2_amount' => $this->numericString($item['supplier_2_amount'] ?? null),
                'supplier_3_amount' => $this->numericString($item['supplier_3_amount'] ?? null),
                'supplier_4_amount' => $this->numericString($item['supplier_4_amount'] ?? null),
                'supplier_5_amount' => $this->numericString($item['supplier_5_amount'] ?? null),
                'total_lowest_price' => $this->numericString($item['total_lowest_price'] ?? null),
            ])->values()->all();
    }

    private function eligibleRfqs(): Builder
    {
        return Rfq::query()
            ->whereIn('status', [Rfq::STATUS_SUBMITTED, Rfq::STATUS_ISSUED, Rfq::STATUS_QUOTED, 'ready_for_abstract'])
            ->where(function (Builder $resolution) {
                $resolution->whereHas('sourceBacResolution', fn (Builder $query) => $this->readyBacResolutionPath($query))
                    ->orWhereHas('sourcePrDocument.latestBacResolution', fn (Builder $query) => $this->readyBacResolutionPath($query));
            });
    }

    private function eligibleResolutions(): Builder
    {
        return BacResolution::query()
            ->whereIn('status', [BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR, BacResolution::STATUS_APPROVED_BY_HOPE, 'approved', 'confirmed', 'ready_for_rfq'])
            ->where(function (Builder $query) {
                $query->whereDoesntHave('sourcePrDocument')
                    ->orWhereHas('sourcePrDocument', fn (Builder $document) => $this->readyForQuotationPath($document));
            });
    }

    private function readyForQuotationPath(Builder $query): Builder
    {
        return $query->where(function (Builder $path) {
            $path->whereIn('status', [
                    ProcurementDocument::STATUS_READY_FOR_RFQ,
                    ProcurementDocument::STATUS_READY_FOR_PO,
                ])
                ->orWhere(function (Builder $lowValue) {
                    $lowValue->where(function (Builder $amount) {
                        $amount->whereNull('total_amount')
                            ->orWhere('total_amount', '<', 50000);
                    })->where('status', '!=', ProcurementDocument::STATUS_SVP_POSTING_REQUIRED);
                });
        });
    }

    private function eligiblePrs(): Builder
    {
        return ProcurementDocument::query()
            ->whereIn('document_type', ['PR', 'Purchase Request'])
            ->whereIn('status', ['approved', 'ready_for_po', 'approved_for_rfq', 'ready_for_rfq', ProcurementDocument::STATUS_APPROVED, ProcurementDocument::STATUS_READY_FOR_PO]);
    }

    private function selectedRfq(mixed $id): ?Rfq
    {
        return filled($id) ? $this->eligibleRfqs()->with(['sourcePrDocument.submittingOffice', 'sourcePrDocument.purchaseRequestItems', 'sourceBacResolution', 'items'])->whereKey($id)->first() : null;
    }

    private function selectedResolution(mixed $id): ?BacResolution
    {
        return filled($id) ? $this->eligibleResolutions()->with(['sourcePrDocument.submittingOffice', 'sourcePrDocument.purchaseRequestItems'])->whereKey($id)->first() : null;
    }

    private function selectedPr(mixed $id): ?ProcurementDocument
    {
        return filled($id) ? $this->eligiblePrs()->with(['submittingOffice', 'purchaseRequestItems'])->whereKey($id)->first() : null;
    }

    private function missingBacResolutionForAbstract(?Rfq $sourceRfq, ?BacResolution $sourceResolution): bool
    {
        return $sourceRfq && ! $this->bacResolutionReadyForAbstract($sourceResolution);
    }

    private function bacResolutionRequiredMessage(): string
    {
        return 'BAC Resolution is still required before creating or submitting an Abstract. You may prepare the RFQ first, then link or complete the BAC Resolution before the Abstract stage.';
    }

    private function readyBacResolutionForRfq(?Rfq $sourceRfq): ?BacResolution
    {
        if (! $sourceRfq) {
            return null;
        }

        $sourceRfq->loadMissing(['sourceBacResolution', 'sourcePrDocument.latestBacResolution']);

        if ($this->bacResolutionReadyForAbstract($sourceRfq->sourceBacResolution)) {
            return $sourceRfq->sourceBacResolution;
        }

        $latestResolution = $sourceRfq->sourcePrDocument?->latestBacResolution;

        return $this->bacResolutionReadyForAbstract($latestResolution) ? $latestResolution : null;
    }

    private function bacResolutionReadyForAbstract(?BacResolution $resolution): bool
    {
        return $resolution
            && in_array($resolution->status, $this->readyBacResolutionStatuses(), true);
    }

    private function readyBacResolutionPath(Builder $query): Builder
    {
        return $query->whereIn('status', $this->readyBacResolutionStatuses());
    }

    private function readyBacResolutionStatuses(): array
    {
        return [
            BacResolution::STATUS_RETURNED_TO_END_USER,
            BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR,
            BacResolution::STATUS_APPROVED_BY_HOPE,
            'approved',
            'confirmed',
            'ready_for_rfq',
        ];
    }

    private function summary(): array
    {
        return [
            'draft' => AbstractQuotation::where('status', AbstractQuotation::STATUS_DRAFT)->count(),
            'submitted' => AbstractQuotation::where('status', AbstractQuotation::STATUS_SUBMITTED)->count(),
            'ready_for_po' => AbstractQuotation::where('status', AbstractQuotation::STATUS_READY_FOR_PO)->count(),
            'returned' => AbstractQuotation::where('status', AbstractQuotation::STATUS_RETURNED)->count(),
        ];
    }

    private function supplierSummary(array $suppliers): array
    {
        $totals = collect(range(1, 5))->map(function (int $index) use ($suppliers) {
            return [
                'name' => $suppliers["supplier_{$index}_name"] ?? null,
                'total' => $this->money($suppliers["supplier_{$index}_total"] ?? null),
            ];
        })->filter(fn (array $supplier) => $supplier['total'] > 0);

        if ($totals->isEmpty()) {
            return ['lowest_supplier_name' => null, 'lowest_total_amount' => null];
        }

        $lowest = $totals->sortBy('total')->first();

        return [
            'lowest_supplier_name' => $lowest['name'] ?: null,
            'lowest_total_amount' => $lowest['total'],
        ];
    }

    private function statuses(): array
    {
        return [AbstractQuotation::STATUS_DRAFT, AbstractQuotation::STATUS_SUBMITTED, AbstractQuotation::STATUS_READY_FOR_PO, AbstractQuotation::STATUS_RETURNED, AbstractQuotation::STATUS_CANCELLED];
    }

    private function jsonArray(mixed $json): array
    {
        $decoded = filled($json) ? json_decode((string) $json, true) : [];

        return is_array($decoded) ? $decoded : [];
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
