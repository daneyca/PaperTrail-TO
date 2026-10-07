<?php

namespace App\Http\Controllers;

use App\Models\DocumentRoutingHistory;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\ProcurementDocument;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SystemNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PpmpController extends Controller
{
    private const APP_CONSOLIDATION_HANDLER_USER_ID = 'BACSEC-004';
    private const HEAD_OFFICE_SIGNATURE_REQUIRED_MESSAGE = 'PPMP requires Head of Office e-signature before submission to BAC Secretariat for APP consolidation.';

    private const PLAN_TYPES = [
        'indicative' => 'Indicative',
        'final' => 'Final',
    ];

    private const STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'reviewed' => 'Reviewed',
        'approved' => 'Approved',
        'returned' => 'Returned',
    ];

    private const PROJECT_TYPES = [
        'Goods',
        'Infrastructure',
        'Consulting Services',
    ];

    private const PROCUREMENT_MODES = [
        'Competitive Bidding',
        'Small Value Procurement',
        'Negotiated Procurement',
        'Direct Acquisition',
        'Shopping',
        'Other',
    ];

    private const PRE_PROCUREMENT_OPTIONS = [
        'Yes',
        'No',
        'N/A',
    ];

    private const FUND_SOURCES = [
        'General Fund',
        'Trust Fund',
        'Special Purpose Fund',
        'Other',
    ];

    public function index(Request $request): View
    {
        AuditLogger::log('PPMP', 'PPMP List Viewed', 'User viewed official PPMP records.');

        $query = $this->scopedPpmps($request->user())->withCount('items')->with('creator');
        $this->applyFilters($query, $request);

        $base = $this->scopedPpmps($request->user());

        return view('ppmps.index', [
            'ppmps' => $query->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => [
                'total' => (clone $base)->count(),
                'draft' => (clone $base)->where('status', Ppmp::STATUS_DRAFT)->count(),
                'submitted' => (clone $base)->where('status', Ppmp::STATUS_SUBMITTED)->count(),
                'budget' => (clone $base)->sum('total_budget'),
            ],
            'filters' => $request->only(['search', 'fiscal_year', 'status', 'plan_type']),
            'fiscalYears' => $this->scopedPpmps($request->user())
                ->whereNotNull('fiscal_year')
                ->distinct()
                ->orderByDesc('fiscal_year')
                ->pluck('fiscal_year'),
            'planTypes' => self::PLAN_TYPES,
            'statuses' => self::STATUSES,
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        $officeName = $user?->assignedOffice?->name ?? $user?->office ?? 'MUNICIPAL BUDGET OFFICE';

        $ppmp = new Ppmp([
            'fiscal_year' => now()->year,
            'end_user_unit' => $officeName,
            'plan_type' => 'indicative',
            'status' => Ppmp::STATUS_DRAFT,
            'office_id' => $user?->office_id,
            'office_name' => $officeName,
            'prepared_by_name' => $user?->name,
            'prepared_by_position' => $user?->position,
            'submitted_by_name' => $user?->name,
            'submitted_by_position' => $user?->position,
        ]);

        AuditLogger::log('PPMP', 'PPMP Create Page Viewed', 'User opened the official PPMP creation page.');

        return view('ppmps.create', [
            'ppmp' => $ppmp,
            'items' => $this->blankRows(),
            'options' => $this->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->input('save_action') === 'submit' && $this->requiresHeadOfficeSignatureWorkflow($request->user())) {
            return back()
                ->withInput()
                ->with('error', self::HEAD_OFFICE_SIGNATURE_REQUIRED_MESSAGE);
        }

        $validated = $this->validated($request);
        $ppmp = null;

        DB::transaction(function () use ($request, $validated, &$ppmp) {
            $ppmp = Ppmp::create($this->payload($validated, $request));
            $this->syncItems($ppmp, $validated['items']);

            AuditLogger::log('PPMP', 'PPMP Created', 'User created an official PPMP.', $ppmp, null, $this->auditSummary($ppmp->refresh()));
        });

        return redirect()
            ->route('ppmps.show', $ppmp)
            ->with('status', 'PPMP saved.');
    }

    public function show(Ppmp $ppmp): View
    {
        $this->authorizeAccess($ppmp);

        $ppmp->load(['items', 'creator']);

        if ($ppmp->status === Ppmp::STATUS_SUBMITTED && ! $this->requiresHeadOfficeSignatureWorkflow(auth()->user())) {
            $this->ensureBacReviewDocument($ppmp, auth()->user());
            $ppmp->refresh()->load(['items', 'creator']);
        }

        AuditLogger::log('PPMP', 'PPMP Preview Viewed', 'User viewed official PPMP preview.', $ppmp);

        return view('ppmps.show', [
            'ppmp' => $ppmp,
        ]);
    }

    public function edit(Ppmp $ppmp): View|RedirectResponse
    {
        $this->authorizeAccess($ppmp);

        if (! $ppmp->isEditable()) {
            return redirect()
                ->route('ppmps.show', $ppmp)
                ->with('error', 'Only draft or returned PPMP records can be edited.');
        }

        $ppmp->load('items');

        return view('ppmps.edit', [
            'ppmp' => $ppmp,
            'items' => $this->formItems($ppmp),
            'options' => $this->options(),
        ]);
    }

    public function update(Request $request, Ppmp $ppmp): RedirectResponse
    {
        $this->authorizeAccess($ppmp);

        if ($request->input('save_action') === 'submit' && $this->requiresHeadOfficeSignatureWorkflow($request->user())) {
            return back()
                ->withInput()
                ->with('error', self::HEAD_OFFICE_SIGNATURE_REQUIRED_MESSAGE);
        }

        if (! $ppmp->isEditable()) {
            return redirect()
                ->route('ppmps.show', $ppmp)
                ->with('error', 'Only draft or returned PPMP records can be updated.');
        }

        $validated = $this->validated($request, $ppmp);
        $oldValues = $this->auditSummary($ppmp);

        DB::transaction(function () use ($request, $validated, $ppmp, $oldValues) {
            $ppmp->update($this->payload($validated, $request, $ppmp));
            $this->syncItems($ppmp, $validated['items']);

            AuditLogger::log('PPMP', 'PPMP Updated', 'User updated an official PPMP.', $ppmp, $oldValues, $this->auditSummary($ppmp->refresh()));
        });

        return redirect()
            ->route('ppmps.show', $ppmp)
            ->with('status', 'PPMP updated.');
    }

    public function submit(Request $request, Ppmp $ppmp): RedirectResponse
    {
        $this->authorizeAccess($ppmp);

        if ($this->requiresHeadOfficeSignatureWorkflow($request->user())) {
            return redirect()
                ->route('ppmps.show', $ppmp)
                ->with('error', self::HEAD_OFFICE_SIGNATURE_REQUIRED_MESSAGE);
        }

        if (! $ppmp->isEditable()) {
            return redirect()
                ->route('ppmps.show', $ppmp)
                ->with('error', 'Only draft or returned PPMP records can be submitted.');
        }

        $ppmp->load('items');

        try {
            $this->validateSubmittedItems($this->storedItemsForValidation($ppmp));
        } catch (ValidationException $exception) {
            return redirect()
                ->route('ppmps.edit', $ppmp)
                ->withErrors($exception->errors())
                ->with('error', 'Complete at least one PPMP line item before submitting.');
        }

        $oldValues = $this->auditSummary($ppmp);
        $target = $this->appConsolidationTarget();

        if (! $target['office'] || ! $target['user']) {
            return redirect()
                ->route('ppmps.show', $ppmp)
                ->with('error', 'BACSEC-004 APP consolidation routing target is not configured.');
        }

        DB::transaction(function () use ($request, $ppmp, $oldValues, $target) {
            $user = $request->user();

            $ppmp->update([
                'status' => Ppmp::STATUS_SUBMITTED,
                'submitted_by_name' => $ppmp->submitted_by_name ?: $user?->name,
                'submitted_by_position' => $ppmp->submitted_by_position ?: $user?->position,
                'submitted_date' => $ppmp->submitted_date ?: now()->toDateString(),
            ]);

            $ppmp->refresh()->load('items');
            $this->ensureBacReviewDocument($ppmp, $user, true, $target);

            AuditLogger::log('PPMP', 'PPMP Submitted', 'End User submitted a PPMP to BACSEC-004 for APP consolidation.', $ppmp, $oldValues, $this->auditSummary($ppmp->refresh()));
        });

        return redirect()
            ->route('ppmps.show', $ppmp)
            ->with('status', 'PPMP submitted to BACSEC-004 for APP consolidation.');
    }

    public function destroy(Ppmp $ppmp): RedirectResponse
    {
        $this->authorizeAccess($ppmp);

        DB::transaction(function () use ($ppmp) {
            AuditLogger::log('PPMP', 'PPMP Deleted', 'User deleted an official PPMP.', $ppmp, $this->auditSummary($ppmp), null, 'warning');
            $ppmp->delete();
        });

        return redirect()
            ->route('ppmps.index')
            ->with('status', 'PPMP deleted.');
    }

    public function print(Ppmp $ppmp): View
    {
        $this->authorizeAccess($ppmp);

        $ppmp->load(['items', 'creator']);
        AuditLogger::log('PPMP', 'PPMP Print Viewed', 'User opened official PPMP print view.', $ppmp);

        return view('ppmps.print', [
            'ppmp' => $ppmp,
        ]);
    }

    private function scopedPpmps(?User $user): Builder
    {
        $query = Ppmp::query();

        if (! $user || $this->canViewAll($user)) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($user) {
            $builder->where('created_by', $user->id);

            if ($user->office_id) {
                $builder->orWhere('office_id', $user->office_id);
            }
        });
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();
            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('ppmp_no', 'like', "%{$search}%")
                    ->orWhere('end_user_unit', 'like', "%{$search}%")
                    ->orWhere('prepared_by_name', 'like', "%{$search}%")
                    ->orWhere('submitted_by_name', 'like', "%{$search}%")
                    ->orWhereHas('items', function (Builder $itemQuery) use ($search) {
                        $itemQuery->where('general_description', 'like', "%{$search}%")
                            ->orWhere('quantity_size', 'like', "%{$search}%")
                            ->orWhere('project_type', 'like', "%{$search}%");
                    });
            });
        });

        $query->when($request->filled('fiscal_year'), fn (Builder $builder) => $builder->where('fiscal_year', $request->input('fiscal_year')));
        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', fn (Builder $builder) => $builder->where('status', $request->input('status')));
        $query->when($request->filled('plan_type'), fn (Builder $builder) => $builder->where('plan_type', $request->input('plan_type')));
    }

    private function validated(Request $request, ?Ppmp $ppmp = null): array
    {
        $items = collect($request->input('items', []))
            ->map(fn ($item) => is_array($item) ? $item : [])
            ->filter(fn (array $item) => $this->hasItemContent($item))
            ->values()
            ->all();

        $request->merge(['items' => $items]);

        $validated = $request->validate([
            'ppmp_no' => ['nullable', 'string', 'max:255', Rule::unique('ppmps', 'ppmp_no')->ignore($ppmp?->id)],
            'fiscal_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'end_user_unit' => ['required', 'string', 'max:255'],
            'plan_type' => ['required', Rule::in(array_keys(self::PLAN_TYPES))],
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'prepared_by_name' => ['nullable', 'string', 'max:255'],
            'prepared_by_position' => ['nullable', 'string', 'max:255'],
            'submitted_by_name' => ['nullable', 'string', 'max:255'],
            'submitted_by_position' => ['nullable', 'string', 'max:255'],
            'prepared_date' => ['nullable', 'date'],
            'submitted_date' => ['nullable', 'date'],
            'items' => ['nullable', 'array'],
            'items.*.general_description' => ['nullable', 'string'],
            'items.*.project_type' => ['nullable', 'string', 'max:255'],
            'items.*.quantity_size' => ['nullable', 'string'],
            'items.*.recommended_mode' => ['nullable', 'string', 'max:255'],
            'items.*.procurement_mode' => ['nullable', 'string', 'max:255'],
            'items.*.pre_procurement_conference' => ['nullable', 'string', 'max:255'],
            'items.*.start_procurement_activity' => ['nullable', 'string', 'max:255'],
            'items.*.end_procurement_activity' => ['nullable', 'string', 'max:255'],
            'items.*.expected_delivery_period' => ['nullable', 'string', 'max:255'],
            'items.*.source_of_funds' => ['nullable', 'string', 'max:255'],
            'items.*.estimated_budget' => ['nullable', 'numeric', 'min:0'],
            'items.*.supporting_documents' => ['nullable', 'string'],
            'items.*.remarks' => ['nullable', 'string'],
        ]);

        $validated['items'] = $items;

        if ($request->input('save_action') === 'submit') {
            $this->validateSubmittedItems($items);
        }

        return $validated;
    }

    private function payload(array $validated, Request $request, ?Ppmp $ppmp = null): array
    {
        $user = $request->user();
        $officeName = $user?->assignedOffice?->name ?? $user?->office ?? $validated['end_user_unit'];
        $status = $request->input('save_action') === 'submit' ? Ppmp::STATUS_SUBMITTED : $validated['status'];

        return [
            'ppmp_no' => $validated['ppmp_no'] ?? null,
            'fiscal_year' => $validated['fiscal_year'],
            'end_user_unit' => $validated['end_user_unit'],
            'plan_type' => $validated['plan_type'],
            'status' => $status,
            'office_id' => $user?->office_id ?? $ppmp?->office_id,
            'office_name' => $officeName,
            'prepared_by_name' => $validated['prepared_by_name'] ?? null,
            'prepared_by_position' => $validated['prepared_by_position'] ?? null,
            'submitted_by_name' => $validated['submitted_by_name'] ?? null,
            'submitted_by_position' => $validated['submitted_by_position'] ?? null,
            'prepared_date' => $validated['prepared_date'] ?? null,
            'submitted_date' => $validated['submitted_date'] ?? null,
            'total_budget' => $this->totalBudget($validated['items']),
            'created_by' => $ppmp?->created_by ?? $user?->id,
        ];
    }

    private function syncItems(Ppmp $ppmp, array $items): void
    {
        PpmpItem::query()->where('ppmp_id', $ppmp->id)->delete();

        foreach ($items as $index => $item) {
            $budget = $this->money($item['estimated_budget'] ?? 0);

            PpmpItem::create([
                'ppmp_id' => $ppmp->id,
                'procurement_document_id' => null,
                'row_order' => $index,
                'item_no' => (string) ($index + 1),
                'general_description' => filled($item['general_description'] ?? null) ? $item['general_description'] : ' ',
                'project_type' => $item['project_type'] ?? null,
                'quantity_size' => $item['quantity_size'] ?? null,
                'quantity' => 0,
                'unit' => null,
                'estimated_unit_cost' => $budget,
                'estimated_total_cost' => $budget,
                'procurement_mode' => $this->procurementMode($item),
                'pre_procurement_conference' => $item['pre_procurement_conference'] ?? null,
                'start_procurement_activity' => $item['start_procurement_activity'] ?? null,
                'end_procurement_activity' => $item['end_procurement_activity'] ?? null,
                'expected_delivery_period' => $item['expected_delivery_period'] ?? null,
                'source_of_funds' => $item['source_of_funds'] ?? null,
                'attached_supporting_documents' => $item['supporting_documents'] ?? null,
                'schedule_quarter' => trim(($item['start_procurement_activity'] ?? '') . ' - ' . ($item['end_procurement_activity'] ?? ''), ' -'),
                'category' => $item['project_type'] ?? null,
                'remarks' => $item['remarks'] ?? null,
            ]);
        }
    }

    private function formItems(Ppmp $ppmp): array
    {
        $items = $ppmp->items->map(function (PpmpItem $item) {
            $budget = (float) $item->estimated_total_cost;

            return [
                'general_description' => trim((string) $item->general_description),
                'project_type' => $item->project_type,
                'quantity_size' => $item->quantity_size,
                'recommended_mode' => $item->procurement_mode,
                'procurement_mode' => $item->procurement_mode,
                'pre_procurement_conference' => $item->pre_procurement_conference,
                'start_procurement_activity' => $item->start_procurement_activity,
                'end_procurement_activity' => $item->end_procurement_activity,
                'expected_delivery_period' => $item->expected_delivery_period,
                'source_of_funds' => $item->source_of_funds,
                'estimated_budget' => $budget > 0 ? $item->estimated_total_cost : '',
                'supporting_documents' => $item->attached_supporting_documents,
                'remarks' => $item->remarks,
            ];
        })->values()->all();

        return $items;
    }

    private function storedItemsForValidation(Ppmp $ppmp): array
    {
        return $ppmp->items->map(fn (PpmpItem $item) => [
            'general_description' => trim((string) $item->general_description),
            'project_type' => $item->project_type,
            'quantity_size' => $item->quantity_size,
            'recommended_mode' => $item->procurement_mode,
            'pre_procurement_conference' => $item->pre_procurement_conference,
            'start_procurement_activity' => $item->start_procurement_activity,
            'end_procurement_activity' => $item->end_procurement_activity,
            'expected_delivery_period' => $item->expected_delivery_period,
            'source_of_funds' => $item->source_of_funds,
            'estimated_budget' => $item->estimated_total_cost,
            'supporting_documents' => $item->attached_supporting_documents,
            'remarks' => $item->remarks,
        ])->values()->all();
    }

    private function ensureBacReviewDocument(Ppmp $ppmp, ?User $user, bool $notify = true, ?array $target = null): ?ProcurementDocument
    {
        $existing = $this->linkedReviewDocument($ppmp);

        if ($existing) {
            return $existing;
        }

        $target ??= $this->appConsolidationTarget();

        if (! $target['office'] || ! $target['user']) {
            Log::warning('PPMP submitted but BACSEC-004 APP consolidation target is missing.', [
                'ppmp_id' => $ppmp->id,
            ]);

            return null;
        }

        $owner = $ppmp->creator ?: $user;
        $office = $ppmp->office_id
            ? Office::find($ppmp->office_id)
            : ($owner?->assignedOffice);
        $sourceMarker = $this->sourceMarker($ppmp);

        $document = ProcurementDocument::create([
            'tracking_number' => null,
            'ppmp_no' => null,
            'ppmp_plan_type' => $ppmp->plan_type,
            'document_type' => 'PPMP',
            'title' => 'Project Procurement Plan',
            'description' => trim("Official PPMP for {$ppmp->end_user_unit}. {$sourceMarker}"),
            'fiscal_year' => $ppmp->fiscal_year,
            'submitting_office_id' => $ppmp->office_id ?: $owner?->office_id,
            'submitted_by_user_id' => $ppmp->created_by ?: $owner?->id,
            'prepared_by_user_id' => $ppmp->created_by ?: $owner?->id,
            'current_office_id' => $target['office']->id,
            'assigned_to_user_id' => $target['user']?->id,
            'status' => ProcurementDocument::STATUS_PENDING_PPMP_REVIEW,
            'stage' => ProcurementDocument::STAGE_BAC_SECRETARIAT_PPMP_REVIEW,
            'priority' => 'normal',
            'total_amount' => $ppmp->total_budget,
            'remarks' => $sourceMarker,
            'submitted_at' => now(),
            'bac_secretariat_status' => 'pending',
        ]);

        $trackingNumber = $document->tracking_number;

        $ppmp->items()->update(['procurement_document_id' => $document->id]);

        if ($trackingNumber && $ppmp->ppmp_no !== $trackingNumber) {
            $ppmp->update(['ppmp_no' => $trackingNumber]);
        }

        $this->recordRouting(
            $document,
            $user ?: $owner,
            'PPMP Submitted to BACSEC-004 for APP Consolidation',
            Ppmp::STATUS_DRAFT,
            ProcurementDocument::STATUS_PENDING_PPMP_REVIEW,
            'PPMP routed to BACSEC-004 for APP consolidation processing.',
            $ppmp->office_id ?: $owner?->office_id,
            $target['office']->id,
        );

        if ($notify) {
            $submittingOfficeName = $document->submittingOffice?->name ?? $ppmp->end_user_unit;
            $message = "PPMP {$trackingNumber} was submitted by {$submittingOfficeName} for APP consolidation.";

            if ($target['user']) {
                SystemNotificationService::notify(
                    $target['user'],
                    'New PPMP for APP Consolidation',
                    $message,
                    SystemNotification::TYPE_INFO,
                    'PPMP Review',
                    $document,
                    route('bac-secretariat.ppmp.show', $document),
                );
            } else {
                Log::warning('PPMP submitted but BACSEC-004 notification target is missing.', [
                    'ppmp_id' => $ppmp->id,
                    'procurement_document_id' => $document->id,
                ]);
            }
        }

        return $document;
    }

    private function linkedReviewDocument(Ppmp $ppmp): ?ProcurementDocument
    {
        return ProcurementDocument::query()
            ->where('document_type', 'PPMP')
            ->where(function (Builder $query) use ($ppmp) {
                $query->where('description', 'like', '%' . $this->sourceMarker($ppmp) . '%')
                    ->orWhere('remarks', 'like', '%' . $this->sourceMarker($ppmp) . '%');

                if ($ppmp->ppmp_no) {
                    $query->orWhere('ppmp_no', $ppmp->ppmp_no)
                        ->orWhere('tracking_number', $ppmp->ppmp_no);
                }
            })
            ->first();
    }

    private function sourceMarker(Ppmp $ppmp): string
    {
        return "Source PPMP ID: {$ppmp->id};";
    }

    private function appConsolidationTarget(): array
    {
        $user = User::where('status', User::STATUS_ACTIVE)
            ->where('user_id', self::APP_CONSOLIDATION_HANDLER_USER_ID)
            ->first();

        $office = $user?->assignedOffice ?: Office::where('code', 'BACSEC')
            ->orWhere('code', 'BAC')
            ->orWhere('name', 'BAC Secretariat')
            ->orWhere('name', 'like', '%Bids and Awards%')
            ->orWhere('name', 'like', '%BAC%')
            ->first();

        return ['office' => $office, 'user' => $user];
    }

    private function generateTrackingNumber(int $year, ?Office $office): string
    {
        $officeCode = $this->trackingOfficeCode($office);
        $prefix = "PPMP-{$year}-{$officeCode}-";

        $lastTrackingNumber = ProcurementDocument::query()
            ->where('document_type', 'PPMP')
            ->where('tracking_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('tracking_number')
            ->value('tracking_number');

        $sequence = $lastTrackingNumber ? ((int) substr($lastTrackingNumber, -4)) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function trackingOfficeCode(?Office $office): string
    {
        $source = $office?->code ?: $office?->name ?: 'OFFICE';
        $code = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', $source));

        return $code !== '' ? $code : 'OFFICE';
    }

    private function recordRouting(ProcurementDocument $document, ?User $user, string $action, ?string $fromStatus, string $toStatus, ?string $comments, ?int $fromOfficeId, ?int $toOfficeId): void
    {
        if (! $user) {
            return;
        }

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

    private function blankRows(array $items = [], int $minimumRows = 3): array
    {
        while (count($items) < $minimumRows) {
            $items[] = $this->blankItem();
        }

        return $items;
    }

    private function blankItem(): array
    {
        return [
            'general_description' => '',
            'project_type' => '',
            'quantity_size' => '',
            'recommended_mode' => '',
            'procurement_mode' => '',
            'pre_procurement_conference' => '',
            'start_procurement_activity' => '',
            'end_procurement_activity' => '',
            'expected_delivery_period' => '',
            'source_of_funds' => '',
            'estimated_budget' => '',
            'supporting_documents' => '',
            'remarks' => '',
        ];
    }

    private function options(): array
    {
        return [
            'planTypes' => self::PLAN_TYPES,
            'statuses' => self::STATUSES,
            'projectTypes' => self::PROJECT_TYPES,
            'procurementModes' => self::PROCUREMENT_MODES,
            'preProcurementOptions' => self::PRE_PROCUREMENT_OPTIONS,
            'fundSources' => self::FUND_SOURCES,
        ];
    }

    private function totalBudget(array $items): float
    {
        return collect($items)->sum(fn (array $item) => $this->money($item['estimated_budget'] ?? 0));
    }

    private function hasItemContent(array $item): bool
    {
        return collect([
            $item['general_description'] ?? null,
            $item['project_type'] ?? null,
            $item['quantity_size'] ?? null,
            $item['recommended_mode'] ?? null,
            $item['procurement_mode'] ?? null,
            $item['pre_procurement_conference'] ?? null,
            $item['start_procurement_activity'] ?? null,
            $item['end_procurement_activity'] ?? null,
            $item['expected_delivery_period'] ?? null,
            $item['source_of_funds'] ?? null,
            $item['estimated_budget'] ?? null,
            $item['supporting_documents'] ?? null,
            $item['remarks'] ?? null,
        ])->contains(fn ($value) => filled($value));
    }

    private function hasOfficialItemColumns(array $item): bool
    {
        return collect([
            $item['project_type'] ?? null,
            $item['quantity_size'] ?? null,
            $item['recommended_mode'] ?? null,
            $item['procurement_mode'] ?? null,
            $item['pre_procurement_conference'] ?? null,
            $item['start_procurement_activity'] ?? null,
            $item['end_procurement_activity'] ?? null,
            $item['expected_delivery_period'] ?? null,
            $item['source_of_funds'] ?? null,
            $item['estimated_budget'] ?? null,
            $item['supporting_documents'] ?? null,
            $item['remarks'] ?? null,
        ])->contains(fn ($value) => filled($value));
    }

    /**
     * Section labels are allowed in Column 1 only. Rows with worksheet data must
     * be complete before submission, while drafts may keep partial rows.
     */
    private function validateSubmittedItems(array $items): void
    {
        $errors = [];
        $completeRows = 0;

        foreach ($items as $index => $item) {
            if (! $this->hasOfficialItemColumns($item)) {
                continue;
            }

            $completeRows++;

            foreach ([
                'general_description' => 'general description',
                'project_type' => 'type of project',
                'quantity_size' => 'quantity and size',
                'start_procurement_activity' => 'start of procurement activity',
                'end_procurement_activity' => 'end of procurement activity',
                'expected_delivery_period' => 'expected delivery/implementation period',
                'source_of_funds' => 'source of funds',
            ] as $field => $label) {
                if (! filled($item[$field] ?? null)) {
                    $errors["items.$index.$field"] = "The {$label} field is required for completed PPMP rows.";
                }
            }

            if (! filled($this->procurementMode($item))) {
                $errors["items.$index.recommended_mode"] = 'The recommended mode of procurement field is required for completed PPMP rows.';
            }

            if (! filled($item['estimated_budget'] ?? null) || $this->money($item['estimated_budget']) <= 0) {
                $errors["items.$index.estimated_budget"] = 'The estimated budget must be greater than zero for completed PPMP rows.';
            }
        }

        if ($completeRows === 0) {
            $errors['items'] = 'Add at least one complete PPMP line item before submitting.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function procurementMode(array $item): ?string
    {
        return $item['recommended_mode'] ?? $item['procurement_mode'] ?? null;
    }

    private function money(mixed $value): float
    {
        return round(max((float) str_replace(',', '', (string) $value), 0), 2);
    }

    private function auditSummary(Ppmp $ppmp): array
    {
        return [
            'ppmp_no' => $ppmp->ppmp_no,
            'fiscal_year' => $ppmp->fiscal_year,
            'end_user_unit' => $ppmp->end_user_unit,
            'plan_type' => $ppmp->plan_type,
            'status' => $ppmp->status,
            'total_budget' => $ppmp->total_budget,
        ];
    }

    private function authorizeAccess(Ppmp $ppmp): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        if ($this->canViewAll($user)) {
            return;
        }

        if ($ppmp->created_by === $user->id || ($user->office_id && $ppmp->office_id === $user->office_id)) {
            return;
        }

        abort(403);
    }

    private function canViewAll(User $user): bool
    {
        $roleSlug = method_exists($user, 'roleSlug') ? $user->roleSlug() : null;

        return in_array($roleSlug, ['admin', 'bac-secretariat'], true);
    }

    private function requiresHeadOfficeSignatureWorkflow(?User $user): bool
    {
        return $user
            && $user->user_id !== 'BACSEC-002'
            && ($user->hasRole('head_office') || $user->hasRole(User::ROLE_HEAD_OFFICE));
    }
}
