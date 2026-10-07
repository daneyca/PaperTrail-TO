<?php

namespace App\Http\Controllers\BacSecretariat;

use App\Http\Controllers\Controller;
use App\Models\DocumentRoutingHistory;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\SupplementalApp;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentDraftService;
use App\Services\SystemNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplementalAppController extends Controller
{
    private const DEFAULT_JUSTIFICATION = 'This Supplemental APP is prepared because the procurement request has no matching record in the existing PPMP/APP and is necessary to support the processing of the Purchase Request.';

    public function index(Request $request): View
    {
        AuditLogger::log('Supplemental APP', 'Supplemental APP Page Viewed', 'BAC Secretariat viewed Supplemental APP records.');

        $query = SupplementalApp::query()
            ->with(['sourcePrDocument.submittingOffice', 'requestingOffice', 'preparedBy']);

        $this->applyFilters($query, $request);

        return view('bac-secretariat.supplemental-apps.index', [
            'supplementalApps' => $query->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary(),
            'recentDrafts' => app(DocumentDraftService::class)->getSupplementalAppDraftsForBacSecretariat($request->user(), 5),
            'filters' => $request->only(['search', 'fiscal_year', 'status', 'office_id', 'date_from', 'date_to']),
            'fiscalYears' => SupplementalApp::query()->whereNotNull('fiscal_year')->select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
            'statuses' => $this->statuses(),
            'offices' => Office::requesting()->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('bac-secretariat.supplemental-apps.create', [
            'supplementalApp' => new SupplementalApp([
                'supplemental_app_number' => $this->nextSupplementalAppNumber(now()->year),
                'fiscal_year' => now()->year,
                'justification' => self::DEFAULT_JUSTIFICATION,
            ]),
            'sourceDocument' => null,
            'items' => $this->emptyItemRows(),
            'offices' => Office::requesting()->orderBy('name')->get(),
            'recentDrafts' => app(DocumentDraftService::class)->getSupplementalAppDraftsForBacSecretariat($request->user(), 5),
        ]);
    }

    public function createFromPr(Request $request, ProcurementDocument $document): View|RedirectResponse
    {
        if (! $this->canUseSourcePr($request->user(), $document)) {
            AuditLogger::log('Supplemental APP', 'Unauthorized Access Attempt', 'BAC Secretariat attempted to create a Supplemental APP from an inaccessible PR.', $document, null, null, 'warning');

            return redirect()
                ->route('bac-secretariat.pr.index')
                ->with('error', 'You are not authorized to create a Supplemental APP from this Purchase Request.');
        }

        $document->load(['submittingOffice', 'submittedBy', 'purchaseRequestItems']);

        return view('bac-secretariat.supplemental-apps.create', [
            'supplementalApp' => new SupplementalApp([
                'supplemental_app_number' => $this->nextSupplementalAppNumber((int) $document->fiscal_year),
                'fiscal_year' => $document->fiscal_year,
                'source_pr_document_id' => $document->id,
                'requesting_office_id' => $document->submitting_office_id,
                'requesting_office_name' => $document->submittingOffice?->name,
                'title' => $document->title,
                'purpose' => $document->purpose ?: $document->description,
                'justification' => self::DEFAULT_JUSTIFICATION,
                'total_amount' => $document->total_amount,
            ]),
            'sourceDocument' => $document,
            'items' => $this->itemsFromPr($document),
            'offices' => Office::requesting()->orderBy('name')->get(),
            'recentDrafts' => app(DocumentDraftService::class)->getSupplementalAppDraftsForBacSecretariat($request->user(), 5),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedPayload($request);
        $sourceDocument = $this->sourcePrFromPayload($request, $validated);
        $items = $this->validatedItems($request);

        $supplementalApp = null;

        DB::transaction(function () use ($request, $validated, $sourceDocument, $items, &$supplementalApp): void {
            $status = $this->statusForSaveAction($request->input('save_action'));
            $now = now();

            $supplementalApp = SupplementalApp::create([
                ...$validated,
                'supplemental_app_number' => $validated['supplemental_app_number'] ?: $this->nextSupplementalAppNumber((int) ($validated['fiscal_year'] ?: now()->year)),
                'source_pr_document_id' => $sourceDocument?->id,
                'requesting_office_id' => $validated['requesting_office_id'] ?? $sourceDocument?->submitting_office_id,
                'requesting_office_name' => ($validated['requesting_office_name'] ?? null) ?: $sourceDocument?->submittingOffice?->name,
                'prepared_by_user_id' => $request->user()->id,
                'submitted_by_user_id' => $status === SupplementalApp::STATUS_SUBMITTED ? $request->user()->id : null,
                'submitted_at' => $status === SupplementalApp::STATUS_SUBMITTED ? $now : null,
                'accepted_by_user_id' => $status === SupplementalApp::STATUS_ACCEPTED ? $request->user()->id : null,
                'accepted_at' => $status === SupplementalApp::STATUS_ACCEPTED ? $now : null,
                'status' => $status,
                'total_amount' => $this->itemsTotal($items),
            ]);

            $this->syncItems($supplementalApp, $items);

            if ($sourceDocument) {
                $this->markSourcePendingSupplementalApp($sourceDocument, $request->user(), $supplementalApp);

                if ($status === SupplementalApp::STATUS_ACCEPTED) {
                    $this->markSourceReadyForResolution($sourceDocument, $request->user(), $supplementalApp);
                }
            }

            AuditLogger::log('Supplemental APP', 'Supplemental APP Created', 'BAC Secretariat created a Supplemental APP record.', $supplementalApp, null, [
                'status' => $status,
                'source_pr' => $sourceDocument?->tracking_number,
            ]);
        });

        return redirect()
            ->route('bac-secretariat.supplemental-apps.show', $supplementalApp)
            ->with('status', 'Supplemental APP saved.');
    }

    public function show(Request $request, SupplementalApp $supplementalApp): View
    {
        AuditLogger::log('Supplemental APP', 'Supplemental APP Detail Viewed', 'BAC Secretariat viewed Supplemental APP detail.', $supplementalApp);

        $supplementalApp->load($this->detailRelations());

        return view('bac-secretariat.supplemental-apps.show', [
            'supplementalApp' => $supplementalApp,
        ]);
    }

    public function edit(SupplementalApp $supplementalApp): View|RedirectResponse
    {
        if (! $supplementalApp->isEditable()) {
            return redirect()
                ->route('bac-secretariat.supplemental-apps.show', $supplementalApp)
                ->with('error', 'Only draft or created Supplemental APP records can be edited.');
        }

        $supplementalApp->load(['sourcePrDocument.submittingOffice', 'items']);

        return view('bac-secretariat.supplemental-apps.edit', [
            'supplementalApp' => $supplementalApp,
            'sourceDocument' => $supplementalApp->sourcePrDocument,
            'items' => $this->itemsFromSupplementalApp($supplementalApp),
            'offices' => Office::requesting()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, SupplementalApp $supplementalApp): RedirectResponse
    {
        if (! $supplementalApp->isEditable()) {
            return redirect()
                ->route('bac-secretariat.supplemental-apps.show', $supplementalApp)
                ->with('error', 'Only draft or created Supplemental APP records can be edited.');
        }

        $validated = $this->validatedPayload($request, $supplementalApp);
        $items = $this->validatedItems($request);
        $sourceDocument = $this->sourcePrFromPayload($request, $validated);

        DB::transaction(function () use ($request, $supplementalApp, $validated, $items, $sourceDocument): void {
            $oldValues = $supplementalApp->only(['supplemental_app_number', 'status', 'total_amount']);

            $supplementalApp->update([
                ...$validated,
                'source_pr_document_id' => $sourceDocument?->id,
                'requesting_office_id' => $validated['requesting_office_id'] ?? $sourceDocument?->submitting_office_id,
                'requesting_office_name' => ($validated['requesting_office_name'] ?? null) ?: $sourceDocument?->submittingOffice?->name,
                'total_amount' => $this->itemsTotal($items),
            ]);

            $this->syncItems($supplementalApp, $items);

            AuditLogger::log('Supplemental APP', 'Supplemental APP Updated', 'BAC Secretariat updated a Supplemental APP.', $supplementalApp, $oldValues, $supplementalApp->only(['supplemental_app_number', 'status', 'total_amount']));
        });

        return redirect()
            ->route('bac-secretariat.supplemental-apps.show', $supplementalApp)
            ->with('status', 'Supplemental APP updated.');
    }

    public function submit(Request $request, SupplementalApp $supplementalApp): RedirectResponse
    {
        if (! in_array($supplementalApp->status, [SupplementalApp::STATUS_DRAFT, SupplementalApp::STATUS_CREATED], true)) {
            return back()->with('error', 'Only draft or created Supplemental APP records can be submitted.');
        }

        if (! $supplementalApp->items()->exists()) {
            return back()->with('error', 'Add at least one item before submitting the Supplemental APP.');
        }

        $supplementalApp->update([
            'status' => SupplementalApp::STATUS_SUBMITTED,
            'submitted_by_user_id' => $request->user()->id,
            'submitted_at' => now(),
        ]);

        $this->notifySubmitter($supplementalApp, 'Supplemental APP Submitted', "Supplemental APP {$supplementalApp->supplemental_app_number} has been submitted by BAC Secretariat.");
        AuditLogger::log('Supplemental APP', 'Supplemental APP Submitted', 'BAC Secretariat submitted a Supplemental APP.', $supplementalApp);

        return back()->with('status', 'Supplemental APP submitted.');
    }

    public function accept(Request $request, SupplementalApp $supplementalApp): RedirectResponse
    {
        if (! in_array($supplementalApp->status, [SupplementalApp::STATUS_DRAFT, SupplementalApp::STATUS_CREATED, SupplementalApp::STATUS_SUBMITTED], true)) {
            return back()->with('error', 'This Supplemental APP cannot be accepted.');
        }

        $supplementalApp->load(['sourcePrDocument.submittedBy', 'sourcePrDocument.submittingOffice']);

        DB::transaction(function () use ($request, $supplementalApp): void {
            $oldStatus = $supplementalApp->status;

            $supplementalApp->update([
                'status' => SupplementalApp::STATUS_ACCEPTED,
                'accepted_by_user_id' => $request->user()->id,
                'accepted_at' => now(),
            ]);

            if ($supplementalApp->sourcePrDocument) {
                $this->markSourceReadyForResolution($supplementalApp->sourcePrDocument, $request->user(), $supplementalApp);
            }

            $this->notifySubmitter($supplementalApp, 'Supplemental APP Accepted', "Supplemental APP {$supplementalApp->supplemental_app_number} has been accepted and linked to the Purchase Request.");
            AuditLogger::log('Supplemental APP', 'Supplemental APP Accepted', 'BAC Secretariat accepted a Supplemental APP.', $supplementalApp, ['status' => $oldStatus], ['status' => SupplementalApp::STATUS_ACCEPTED]);
        });

        return back()->with('status', 'Supplemental APP accepted. The linked PR is now ready for BAC Resolution.');
    }

    public function print(SupplementalApp $supplementalApp): View
    {
        AuditLogger::log('Supplemental APP', 'Supplemental APP Print Viewed', 'BAC Secretariat opened Supplemental APP print view.', $supplementalApp);

        return view('bac-secretariat.supplemental-apps.print', [
            'supplementalApp' => $supplementalApp->load($this->detailRelations()),
            'backUrl' => route('bac-secretariat.supplemental-apps.show', $supplementalApp),
        ]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request): void {
            $search = $request->string('search')->toString();
            $builder->where(function (Builder $nested) use ($search): void {
                $nested->where('supplemental_app_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhere('requesting_office_name', 'like', "%{$search}%")
                    ->orWhereHas('sourcePrDocument', function (Builder $document) use ($search): void {
                        $document->where('tracking_number', 'like', "%{$search}%")
                            ->orWhere('pr_no', 'like', "%{$search}%");
                    });
            });
        });

        foreach (['fiscal_year', 'requesting_office_id'] as $field) {
            $input = $field === 'requesting_office_id' ? 'office_id' : $field;
            $query->when($request->filled($input), fn (Builder $builder) => $builder->where($field, $request->input($input)));
        }

        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', function (Builder $builder) use ($request): void {
            $status = (string) $request->input('status');
            $groups = [
                'accepted' => [SupplementalApp::STATUS_ACCEPTED, SupplementalApp::STATUS_LINKED_TO_PR],
            ];

            $builder->whereIn('status', $groups[$status] ?? [$status]);
        });

        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('updated_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('updated_at', '<=', $request->date('date_to')));
    }

    private function validatedPayload(Request $request, ?SupplementalApp $supplementalApp = null): array
    {
        return $request->validate([
            'supplemental_app_number' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('supplemental_apps', 'supplemental_app_number')->ignore($supplementalApp?->id),
            ],
            'fiscal_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'source_pr_document_id' => ['nullable', 'integer', 'exists:procurement_documents,id'],
            'requesting_office_id' => [
                'nullable',
                'integer',
                Rule::exists('offices', 'id')->where(fn ($query) => $query
                    ->where('status', Office::STATUS_ACTIVE)
                    ->where('is_requesting_office', true)),
            ],
            'requesting_office_name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string'],
            'justification' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
        ]);
    }

    private function validatedItems(Request $request): array
    {
        $items = collect($request->input('items', []))
            ->filter(function (array $item): bool {
                return filled($item['item_no'] ?? null)
                    || filled($item['description'] ?? null)
                    || filled($item['quantity'] ?? null)
                    || filled($item['unit'] ?? null)
                    || filled($item['estimated_unit_cost'] ?? null)
                    || filled($item['remarks'] ?? null);
            })
            ->values()
            ->all();

        $request->merge(['items' => $items]);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.source_pr_item_id' => ['nullable', 'integer', 'exists:purchase_request_items,id'],
            'items.*.item_no' => ['nullable', 'string', 'max:120'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:100'],
            'items.*.estimated_unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string'],
        ]);

        return collect($validated['items'])
            ->map(function (array $item, int $index): array {
                $quantity = (float) ($item['quantity'] ?? 0);
                $unitCost = (float) ($item['estimated_unit_cost'] ?? 0);

                return [
                    'source_pr_item_id' => $item['source_pr_item_id'] ?? null,
                    'item_no' => $item['item_no'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $quantity,
                    'unit' => $item['unit'] ?? null,
                    'estimated_unit_cost' => $unitCost,
                    'estimated_total_cost' => $quantity * $unitCost,
                    'remarks' => $item['remarks'] ?? null,
                    'sort_order' => $index,
                ];
            })
            ->all();
    }

    private function sourcePrFromPayload(Request $request, array $validated): ?ProcurementDocument
    {
        if (blank($validated['source_pr_document_id'] ?? null)) {
            return null;
        }

        $document = ProcurementDocument::with(['submittingOffice', 'submittedBy', 'purchaseRequestItems'])->find($validated['source_pr_document_id']);

        if (! $document || ! $this->canUseSourcePr($request->user(), $document)) {
            abort(403, 'You are not authorized to use this Purchase Request for Supplemental APP.');
        }

        return $document;
    }

    private function canUseSourcePr(User $user, ProcurementDocument $document): bool
    {
        return ProcurementDocument::query()
            ->purchaseRequestsForBacSecretariat($user)
            ->whereKey($document->id)
            ->exists();
    }

    private function syncItems(SupplementalApp $supplementalApp, array $items): void
    {
        $supplementalApp->items()->delete();

        foreach ($items as $item) {
            $supplementalApp->items()->create($item);
        }
    }

    private function itemsFromPr(ProcurementDocument $document): array
    {
        $items = $document->purchaseRequestItems
            ->values()
            ->map(function ($item, int $index): array {
                $quantity = (float) ($item->quantity ?? 0);
                $unitCost = (float) ($item->estimated_unit_cost ?? 0);

                return [
                    'source_pr_item_id' => $item->id,
                    'item_no' => $item->item_no,
                    'description' => $item->description ?? $item->item_description,
                    'quantity' => $quantity,
                    'unit' => $item->unit ?? $item->unit_of_issue,
                    'estimated_unit_cost' => $unitCost,
                    'estimated_total_cost' => (float) ($item->estimated_cost ?? $item->estimated_total_cost ?? ($quantity * $unitCost)),
                    'remarks' => $item->remarks,
                    'sort_order' => $index,
                ];
            })
            ->all();

        return $this->padItemRows($items);
    }

    private function itemsFromSupplementalApp(SupplementalApp $supplementalApp): array
    {
        return $this->padItemRows($supplementalApp->items->map(fn ($item): array => [
            'source_pr_item_id' => $item->source_pr_item_id,
            'item_no' => $item->item_no,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit' => $item->unit,
            'estimated_unit_cost' => $item->estimated_unit_cost,
            'estimated_total_cost' => $item->estimated_total_cost,
            'remarks' => $item->remarks,
        ])->all());
    }

    private function emptyItemRows(): array
    {
        return $this->padItemRows([]);
    }

    private function padItemRows(array $items, int $minimum = 8): array
    {
        while (count($items) < $minimum) {
            $items[] = [
                'source_pr_item_id' => null,
                'item_no' => null,
                'description' => null,
                'quantity' => null,
                'unit' => null,
                'estimated_unit_cost' => null,
                'estimated_total_cost' => null,
                'remarks' => null,
            ];
        }

        return $items;
    }

    private function itemsTotal(array $items): float
    {
        return collect($items)->sum(fn (array $item): float => (float) ($item['estimated_total_cost'] ?? 0));
    }

    private function statusForSaveAction(?string $action): string
    {
        return match ($action) {
            'created' => SupplementalApp::STATUS_CREATED,
            'submit' => SupplementalApp::STATUS_SUBMITTED,
            'accept' => SupplementalApp::STATUS_ACCEPTED,
            default => SupplementalApp::STATUS_DRAFT,
        };
    }

    private function markSourcePendingSupplementalApp(ProcurementDocument $document, User $user, SupplementalApp $supplementalApp): void
    {
        if (in_array($document->status, [
            ProcurementDocument::STATUS_SUPPLEMENTAL_APP_CREATED,
            ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION,
            ProcurementDocument::STATUS_BAC_RESOLUTION_CREATED,
        ], true)) {
            return;
        }

        $oldStatus = $document->status;

        $document->update([
            'status' => ProcurementDocument::STATUS_PENDING_SUPPLEMENTAL_APP,
            'stage' => ProcurementDocument::STAGE_SUPPLEMENTAL_APP_PREPARATION,
            'assigned_to_user_id' => $user->id,
            'current_office_id' => $user->office_id ?: $document->current_office_id,
            'route_destination_role' => User::ROLE_BAC_SECRETARIAT,
            'route_remarks' => "Supplemental APP {$supplementalApp->supplemental_app_number} linked for missing PPMP/APP record.",
            'routed_by_user_id' => $user->id,
            'routed_at' => now(),
        ]);

        $this->recordRouting($document, $user, 'Supplemental APP Linked', $oldStatus, $document->status, "Supplemental APP {$supplementalApp->supplemental_app_number} was linked to this PR.", $document->getOriginal('current_office_id'), $document->current_office_id);
    }

    private function markSourceReadyForResolution(ProcurementDocument $document, User $user, SupplementalApp $supplementalApp): void
    {
        $oldStatus = $document->status;

        $document->update([
            'status' => ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION,
            'stage' => ProcurementDocument::STAGE_READY_FOR_BAC_RESOLUTION,
            'assigned_to_user_id' => $user->id,
            'current_office_id' => $user->office_id ?: $document->current_office_id,
            'route_destination_role' => User::ROLE_BAC_SECRETARIAT,
            'route_remarks' => "Supplemental APP {$supplementalApp->supplemental_app_number} accepted. PR is ready for BAC Resolution.",
            'routed_by_user_id' => $user->id,
            'routed_at' => now(),
        ]);

        $this->recordRouting($document, $user, 'Supplemental APP Accepted', $oldStatus, ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION, "Accepted Supplemental APP {$supplementalApp->supplemental_app_number} made this PR eligible for BAC Resolution.", $document->getOriginal('current_office_id'), $document->current_office_id);
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

    private function notifySubmitter(SupplementalApp $supplementalApp, string $title, string $message): void
    {
        $supplementalApp->loadMissing('sourcePrDocument.submittedBy');

        SystemNotificationService::notify(
            $supplementalApp->sourcePrDocument?->submittedBy,
            $title,
            $message,
            SystemNotification::TYPE_INFO,
            'Supplemental APP',
            $supplementalApp,
            route('head-office.supplemental-apps.show', $supplementalApp),
        );
    }

    private function nextSupplementalAppNumber(int $year): string
    {
        $prefix = "SAPP-{$year}-";
        $last = SupplementalApp::query()
            ->where('supplemental_app_number', 'like', "{$prefix}%")
            ->orderByDesc('supplemental_app_number')
            ->value('supplemental_app_number');

        $sequence = $last ? ((int) substr($last, -3)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    private function statuses(): array
    {
        return [
            SupplementalApp::STATUS_DRAFT,
            SupplementalApp::STATUS_CREATED,
            SupplementalApp::STATUS_SUBMITTED,
            SupplementalApp::STATUS_ACCEPTED,
            SupplementalApp::STATUS_LINKED_TO_PR,
            SupplementalApp::STATUS_CANCELLED,
        ];
    }

    private function summary(): array
    {
        return [
            'total' => SupplementalApp::count(),
            'draft' => SupplementalApp::where('status', SupplementalApp::STATUS_DRAFT)->count(),
            'submitted' => SupplementalApp::where('status', SupplementalApp::STATUS_SUBMITTED)->count(),
            'accepted' => SupplementalApp::whereIn('status', [SupplementalApp::STATUS_ACCEPTED, SupplementalApp::STATUS_LINKED_TO_PR])->count(),
        ];
    }

    private function detailRelations(): array
    {
        return [
            'items.sourcePrItem',
            'sourcePrDocument.submittingOffice',
            'sourcePrDocument.submittedBy',
            'sourcePrDocument.routingHistories.actionBy',
            'sourcePrDocument.routingHistories.fromOffice',
            'sourcePrDocument.routingHistories.toOffice',
            'requestingOffice',
            'preparedBy',
            'submittedBy',
            'acceptedBy',
        ];
    }
}
