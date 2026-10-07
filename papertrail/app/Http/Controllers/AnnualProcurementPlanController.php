<?php

namespace App\Http\Controllers;

use App\Models\AnnualProcurementPlan;
use App\Models\AnnualProcurementPlanItem;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnualProcurementPlanController extends Controller
{
    private const PLAN_TYPES = [
        'indicative' => 'Indicative',
        'final' => 'Final',
        'update' => 'Update',
    ];

    private const STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'reviewed' => 'Reviewed',
        'approved' => 'Approved',
    ];

    private const CATEGORIES = [
        'general_requirements' => 'General Requirements',
        'miscellaneous_items' => 'Miscellaneous Items (for Direct Acquisition only) Sec. 32.2 of RA No. 12009',
        'cse' => 'Common Use Supplies and Equipment (CSE) to be purchased from PS-DBM',
    ];

    private const MODES = [
        'Competitive Bidding',
        'Small Value Procurement',
        'Negotiated Procurement',
        'Direct Acquisition',
        'Shopping',
        'Other',
    ];

    private const EARLY_PROCUREMENT_OPTIONS = ['Yes', 'No', 'N/A'];

    private const FUND_SOURCES = [
        'General Fund',
        'Trust Fund',
        'Special Purpose Fund',
        'Other',
    ];

    public function index(Request $request): View|RedirectResponse
    {
        if ($this->shouldUseBacAppModule($request->user())) {
            return redirect()->route('bac-secretariat.app.index');
        }

        AuditLogger::log('Annual Procurement Plan', 'APP List Viewed', 'User viewed official Annual Procurement Plan records.');

        $query = AnnualProcurementPlan::query()
            ->withCount('items')
            ->with(['createdBy']);

        $this->applyFilters($query, $request);

        $base = AnnualProcurementPlan::query();

        return view('annual-procurement-plans.index', [
            'apps' => $query->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => [
                'total' => (clone $base)->count(),
                'draft' => (clone $base)->where('status', AnnualProcurementPlan::STATUS_DRAFT)->count(),
                'approved' => (clone $base)->where('status', AnnualProcurementPlan::STATUS_APPROVED)->count(),
                'budget' => (clone $base)->sum('total_estimated_budget'),
            ],
            'filters' => $request->only(['search', 'fiscal_year', 'status', 'plan_type']),
            'fiscalYears' => AnnualProcurementPlan::query()
                ->whereNotNull('fiscal_year')
                ->distinct()
                ->orderByDesc('fiscal_year')
                ->pluck('fiscal_year'),
            'planTypes' => self::PLAN_TYPES,
            'statuses' => self::STATUSES,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if ($this->shouldUseBacAppModule($request->user())) {
            return redirect()->route('bac-secretariat.app.create');
        }

        $user = $request->user();
        $app = new AnnualProcurementPlan([
            'fiscal_year' => now()->year,
            'municipality' => 'MUNICIPALITY OF TOMAS OPPUS',
            'province' => 'Province of Southern Leyte',
            'plan_type' => 'indicative',
            'status' => AnnualProcurementPlan::STATUS_DRAFT,
            'prepared_by_name' => $user?->name,
            'prepared_by_position' => $user?->position,
            'prepared_by_office' => $user?->assignedOffice?->name ?? $user?->office,
        ]);

        AuditLogger::log('Annual Procurement Plan', 'APP Create Page Viewed', 'User opened the official APP creation page.');

        return view('annual-procurement-plans.create', [
            'app' => $app,
            'items' => [$this->blankItem()],
            'options' => $this->options(),
        ]);
    }

    private function shouldUseBacAppModule(?User $user): bool
    {
        return (bool) ($user?->hasRole('bac_secretariat') || $user?->hasRole(User::ROLE_BAC_SECRETARIAT));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $app = null;

        DB::transaction(function () use ($request, $validated, &$app) {
            $app = AnnualProcurementPlan::create($this->payload($validated, $request));
            $this->syncItems($app, $validated['items']);

            AuditLogger::log('Annual Procurement Plan', 'APP Created', 'User created an official Annual Procurement Plan.', $app, null, $this->auditSummary($app->refresh()));
        });

        return redirect()
            ->route('annual-procurement-plans.show', $app)
            ->with('status', 'Annual Procurement Plan saved.');
    }

    public function show(AnnualProcurementPlan $annualProcurementPlan): View
    {
        $annualProcurementPlan->load(['items', 'createdBy']);
        AuditLogger::log('Annual Procurement Plan', 'APP Preview Viewed', 'User viewed official APP preview.', $annualProcurementPlan);

        return view('annual-procurement-plans.show', [
            'app' => $annualProcurementPlan,
            'categories' => self::CATEGORIES,
        ]);
    }

    public function edit(AnnualProcurementPlan $annualProcurementPlan): View
    {
        $annualProcurementPlan->load(['items']);

        return view('annual-procurement-plans.edit', [
            'app' => $annualProcurementPlan,
            'items' => $this->formItems($annualProcurementPlan),
            'options' => $this->options(),
        ]);
    }

    public function update(Request $request, AnnualProcurementPlan $annualProcurementPlan): RedirectResponse
    {
        $validated = $this->validated($request, $annualProcurementPlan);
        $oldValues = $this->auditSummary($annualProcurementPlan);

        DB::transaction(function () use ($request, $validated, $annualProcurementPlan, $oldValues) {
            $annualProcurementPlan->update($this->payload($validated, $request));
            $this->syncItems($annualProcurementPlan, $validated['items']);

            AuditLogger::log('Annual Procurement Plan', 'APP Updated', 'User updated an official Annual Procurement Plan.', $annualProcurementPlan, $oldValues, $this->auditSummary($annualProcurementPlan->refresh()));
        });

        return redirect()
            ->route('annual-procurement-plans.show', $annualProcurementPlan)
            ->with('status', 'Annual Procurement Plan updated.');
    }

    public function destroy(AnnualProcurementPlan $annualProcurementPlan): RedirectResponse
    {
        DB::transaction(function () use ($annualProcurementPlan) {
            AuditLogger::log('Annual Procurement Plan', 'APP Deleted', 'User deleted an official Annual Procurement Plan.', $annualProcurementPlan, $this->auditSummary($annualProcurementPlan), null, 'warning');
            $annualProcurementPlan->delete();
        });

        return redirect()
            ->route('annual-procurement-plans.index')
            ->with('status', 'Annual Procurement Plan deleted.');
    }

    public function print(AnnualProcurementPlan $annualProcurementPlan): View
    {
        $annualProcurementPlan->load(['items', 'createdBy']);
        AuditLogger::log('Annual Procurement Plan', 'APP Print Viewed', 'User opened official APP print view.', $annualProcurementPlan);

        return view('annual-procurement-plans.print', [
            'app' => $annualProcurementPlan,
            'categories' => self::CATEGORIES,
        ]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();
            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('document_reference_number', 'like', "%{$search}%")
                    ->orWhere('app_no', 'like', "%{$search}%")
                    ->orWhere('app_number', 'like', "%{$search}%")
                    ->orWhere('municipality', 'like', "%{$search}%")
                    ->orWhere('prepared_by_name', 'like', "%{$search}%")
                    ->orWhereHas('items', function (Builder $itemQuery) use ($search) {
                        $itemQuery->where('project_title', 'like', "%{$search}%")
                            ->orWhere('end_user_unit', 'like', "%{$search}%");
                    });
            });
        });

        $query->when($request->filled('fiscal_year'), fn (Builder $builder) => $builder->where('fiscal_year', $request->input('fiscal_year')));
        $query->when($request->filled('created_year'), fn (Builder $builder) => $builder->where('created_year', $request->input('created_year')));
        $query->when($request->filled('created_month'), fn (Builder $builder) => $builder->where('created_month', $request->input('created_month')));
        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', fn (Builder $builder) => $builder->where('status', $request->input('status')));
        $query->when($request->filled('plan_type'), fn (Builder $builder) => $builder->where('plan_type', $request->input('plan_type')));
    }

    private function validated(Request $request, ?AnnualProcurementPlan $app = null): array
    {
        $items = collect($request->input('items', []))
            ->map(fn ($item) => is_array($item) ? $item : [])
            ->filter(function (array $item) {
                return collect([
                    $item['project_title'] ?? null,
                    $item['end_user_unit'] ?? null,
                    $item['general_description'] ?? null,
                    $item['bid_evaluation_criteria'] ?? null,
                    $item['start_procurement_activity'] ?? null,
                    $item['end_procurement_activity'] ?? null,
                    $item['estimated_budget'] ?? null,
                    $item['procurement_strategy_or_tools'] ?? null,
                    $item['remarks'] ?? null,
                ])->contains(fn ($value) => filled($value));
            })
            ->values()
            ->all();

        $request->merge(['items' => $items]);

        return $request->validate([
            'app_no' => ['nullable', 'string', 'max:255', Rule::unique('annual_procurement_plans', 'app_no')->ignore($app?->id)],
            'fiscal_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'municipality' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'plan_type' => ['required', Rule::in(array_keys(self::PLAN_TYPES))],
            'update_version_no' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'prepared_by_name' => ['nullable', 'string', 'max:255'],
            'prepared_by_position' => ['nullable', 'string', 'max:255'],
            'prepared_by_office' => ['nullable', 'string', 'max:255'],
            'recommended_by_name' => ['nullable', 'string', 'max:255'],
            'recommended_by_position' => ['nullable', 'string', 'max:255'],
            'recommended_by_office' => ['nullable', 'string', 'max:255'],
            'approved_by_name' => ['nullable', 'string', 'max:255'],
            'approved_by_position' => ['nullable', 'string', 'max:255'],
            'approved_by_office' => ['nullable', 'string', 'max:255'],
            'prepared_date' => ['nullable', 'date'],
            'recommended_date' => ['nullable', 'date'],
            'approved_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'items.*.project_title' => ['required', 'string'],
            'items.*.end_user_unit' => ['required', 'string', 'max:255'],
            'items.*.general_description' => ['nullable', 'string'],
            'items.*.mode_of_procurement' => ['required', 'string', 'max:255'],
            'items.*.early_procurement_activity' => ['required', Rule::in(self::EARLY_PROCUREMENT_OPTIONS)],
            'items.*.bid_evaluation_criteria' => ['nullable', 'string'],
            'items.*.start_procurement_activity' => ['required', 'string', 'max:255'],
            'items.*.end_procurement_activity' => ['required', 'string', 'max:255'],
            'items.*.source_of_funds' => ['required', 'string', 'max:255'],
            'items.*.estimated_budget' => ['required', 'numeric', 'min:0'],
            'items.*.procurement_strategy_or_tools' => ['nullable', 'string'],
            'items.*.remarks' => ['nullable', 'string'],
        ]);
    }

    private function payload(array $validated, Request $request): array
    {
        $totals = $this->totals($validated['items']);

        return [
            'app_no' => $validated['app_no'] ?? null,
            'app_number' => $validated['app_no'] ?? null,
            'fiscal_year' => $validated['fiscal_year'],
            'municipality' => $validated['municipality'],
            'province' => $validated['province'],
            'plan_type' => $validated['plan_type'],
            'update_version_no' => $validated['update_version_no'] ?? null,
            'title' => 'Annual Procurement Plan CY ' . $validated['fiscal_year'],
            'office_id' => $request->user()?->office_id,
            'office_name' => $request->user()?->assignedOffice?->name ?? $request->user()?->office,
            'status' => $validated['status'],
            'prepared_by_user_id' => $request->user()?->id,
            'created_by' => $request->user()?->id,
            'prepared_by_name' => $validated['prepared_by_name'] ?? null,
            'prepared_by_position' => $validated['prepared_by_position'] ?? null,
            'prepared_by_office' => $validated['prepared_by_office'] ?? null,
            'recommended_by_name' => $validated['recommended_by_name'] ?? null,
            'recommended_by_position' => $validated['recommended_by_position'] ?? null,
            'recommended_by_office' => $validated['recommended_by_office'] ?? null,
            'approved_by_name' => $validated['approved_by_name'] ?? null,
            'approved_by_position' => $validated['approved_by_position'] ?? null,
            'approved_by_office' => $validated['approved_by_office'] ?? null,
            'prepared_date' => $validated['prepared_date'] ?? null,
            'recommended_date' => $validated['recommended_date'] ?? null,
            'approved_date' => $validated['approved_date'] ?? null,
            'total_epa_budget' => $totals['epa'],
            'total_cse_budget' => $totals['cse'],
            'total_estimated_budget' => $totals['estimated'],
            'items_json' => $validated['items'],
            'document_text' => $this->documentText($validated),
            'remarks' => null,
        ];
    }

    private function syncItems(AnnualProcurementPlan $app, array $items): void
    {
        AnnualProcurementPlanItem::query()
            ->where('annual_procurement_plan_id', $app->id)
            ->delete();

        foreach ($items as $index => $item) {
            AnnualProcurementPlanItem::create([
                'annual_procurement_plan_id' => $app->id,
                'row_order' => $index,
                'sort_order' => $index,
                'category' => $item['category'],
                'project_title' => $item['project_title'],
                'procurement_program_project' => $item['project_title'],
                'end_user_unit' => $item['end_user_unit'],
                'pmo_end_user' => $item['end_user_unit'],
                'general_description' => $item['general_description'] ?? null,
                'mode_of_procurement' => $item['mode_of_procurement'],
                'early_procurement_activity' => $item['early_procurement_activity'],
                'bid_evaluation_criteria' => $item['bid_evaluation_criteria'] ?? null,
                'start_procurement_activity' => $item['start_procurement_activity'],
                'ads_post_ib_rei' => $item['start_procurement_activity'],
                'end_procurement_activity' => $item['end_procurement_activity'],
                'contract_signing' => $item['end_procurement_activity'],
                'source_of_funds' => $item['source_of_funds'],
                'estimated_budget' => $this->money($item['estimated_budget']),
                'estimated_total' => $this->money($item['estimated_budget']),
                'procurement_strategy_or_tools' => $item['procurement_strategy_or_tools'] ?? null,
                'remarks' => $item['remarks'] ?? null,
            ]);
        }
    }

    private function totals(array $items): array
    {
        return [
            'epa' => collect($items)->sum(fn (array $item) => ($item['early_procurement_activity'] ?? null) === 'Yes' ? $this->money($item['estimated_budget']) : 0),
            'cse' => collect($items)->sum(fn (array $item) => ($item['category'] ?? null) === 'cse' ? $this->money($item['estimated_budget']) : 0),
            'estimated' => collect($items)->sum(fn (array $item) => $this->money($item['estimated_budget'])),
        ];
    }

    private function formItems(AnnualProcurementPlan $app): array
    {
        $items = $app->items->map(fn (AnnualProcurementPlanItem $item) => [
            'category' => $item->category ?: 'general_requirements',
            'project_title' => $item->project_title ?: $item->procurement_program_project,
            'end_user_unit' => $item->end_user_unit ?: $item->pmo_end_user,
            'general_description' => $item->general_description,
            'mode_of_procurement' => $item->mode_of_procurement,
            'early_procurement_activity' => $item->early_procurement_activity ?: 'No',
            'bid_evaluation_criteria' => $item->bid_evaluation_criteria,
            'start_procurement_activity' => $item->start_procurement_activity ?: $item->ads_post_ib_rei,
            'end_procurement_activity' => $item->end_procurement_activity ?: $item->contract_signing,
            'source_of_funds' => $item->source_of_funds,
            'estimated_budget' => $item->estimated_budget ?: $item->estimated_total,
            'procurement_strategy_or_tools' => $item->procurement_strategy_or_tools,
            'remarks' => $item->remarks,
        ])->all();

        return $items ?: [$this->blankItem()];
    }

    private function blankItem(): array
    {
        return [
            'category' => 'general_requirements',
            'project_title' => '',
            'end_user_unit' => '',
            'general_description' => '',
            'mode_of_procurement' => 'Small Value Procurement',
            'early_procurement_activity' => 'No',
            'bid_evaluation_criteria' => '',
            'start_procurement_activity' => '',
            'end_procurement_activity' => '',
            'source_of_funds' => 'General Fund',
            'estimated_budget' => '',
            'procurement_strategy_or_tools' => '',
            'remarks' => '',
        ];
    }

    private function options(): array
    {
        return [
            'planTypes' => self::PLAN_TYPES,
            'statuses' => self::STATUSES,
            'categories' => self::CATEGORIES,
            'modes' => self::MODES,
            'earlyProcurementOptions' => self::EARLY_PROCUREMENT_OPTIONS,
            'fundSources' => self::FUND_SOURCES,
        ];
    }

    private function money(mixed $value): float
    {
        return filled($value) ? max((float) preg_replace('/[^0-9.\-]/', '', (string) $value), 0) : 0.0;
    }

    private function documentText(array $validated): string
    {
        $items = collect($validated['items'])
            ->pluck('project_title')
            ->filter()
            ->implode('; ');

        return trim("Annual Procurement Plan CY {$validated['fiscal_year']} {$items}");
    }

    private function auditSummary(AnnualProcurementPlan $app): array
    {
        return [
            'app_no' => $app->app_no,
            'fiscal_year' => $app->fiscal_year,
            'plan_type' => $app->plan_type,
            'status' => $app->status,
            'total_estimated_budget' => $app->total_estimated_budget,
        ];
    }
}
