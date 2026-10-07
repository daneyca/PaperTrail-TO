<?php

namespace App\Http\Controllers\BacSecretariat;

use App\Http\Controllers\Controller;
use App\Models\AppConsolidation;
use App\Models\AppItem;
use App\Models\AppPpmpSource;
use App\Models\DocumentRoutingHistory;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SystemNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AppConsolidationController extends Controller
{
    private const ACCEPTED_PPMP_STATUSES = [
        'accepted_for_app_consolidation',
        'ppmp_accepted',
        'ready_for_app_consolidation',
    ];

    public function index(Request $request): View
    {
        AuditLogger::log('APP Consolidation', 'APP Consolidation Page Viewed', 'BAC Secretariat viewed APP consolidation records.');

        $query = AppConsolidation::query()->with(['preparedBy', 'ppmpSources']);
        $this->applyFilters($query, $request);

        return view('bac-secretariat.app.index', [
            'apps' => $query->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary(),
            'filters' => $request->only(['search', 'fiscal_year', 'status', 'date_from', 'date_to']),
            'fiscalYears' => AppConsolidation::query()->select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
            'statuses' => $this->appStatuses(),
        ]);
    }

    public function create(): View
    {
        return view('bac-secretariat.app.create', [
            'app' => new AppConsolidation(['fiscal_year' => now()->year]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->formRules());

        $app = AppConsolidation::create([
            ...$validated,
            'prepared_by_user_id' => $request->user()->id,
            'status' => AppConsolidation::STATUS_DRAFT,
        ]);

        AuditLogger::log('APP Consolidation', 'APP Draft Created', 'BAC Secretariat created an APP draft.', $app);

        return redirect()
            ->route('bac-secretariat.app.show', $app)
            ->with('status', 'APP draft created.');
    }

    public function show(Request $request, AppConsolidation $app): View
    {
        AuditLogger::log('APP Consolidation', 'APP Draft Viewed', 'BAC Secretariat viewed APP consolidation detail.', $app);

        $app->load([
            'preparedBy',
            'procurementDocument.routingHistories.actionBy',
            'procurementDocument.routingHistories.fromOffice',
            'procurementDocument.routingHistories.toOffice',
            'ppmpSources.procurementDocument.submittingOffice',
            'ppmpSources.includedBy',
            'appItems.office',
        ]);

        return view('bac-secretariat.app.show', [
            'app' => $app,
            'eligiblePpmps' => $this->eligiblePpmpQuery($app->fiscal_year)->with(['submittingOffice', 'submittedBy'])->latest('updated_at')->paginate(5, ['*'], 'eligible_page')->withQueryString(),
            'officeSummary' => $this->summaryBy($app, 'office'),
            'categorySummary' => $this->summaryBy($app, 'category'),
            'modeSummary' => $this->summaryBy($app, 'procurement_mode'),
        ]);
    }

    public function edit(AppConsolidation $app): View|RedirectResponse
    {
        if (!$app->isDraft()) {
            return redirect()
                ->route('bac-secretariat.app.show', $app)
                ->with('error', 'Only draft APP records can be edited.');
        }

        return view('bac-secretariat.app.edit', ['app' => $app]);
    }

    public function update(Request $request, AppConsolidation $app): RedirectResponse
    {
        if (!$app->isDraft()) {
            return redirect()
                ->route('bac-secretariat.app.show', $app)
                ->with('error', 'Only draft APP records can be updated.');
        }

        $app->update($request->validate($this->formRules()));

        AuditLogger::log('APP Consolidation', 'APP Draft Updated', 'BAC Secretariat updated an APP draft.', $app);

        return redirect()
            ->route('bac-secretariat.app.show', $app)
            ->with('status', 'APP draft updated.');
    }

    public function addPpmp(Request $request, AppConsolidation $app, ProcurementDocument $document): RedirectResponse
    {
        if (!$app->isDraft()) {
            return back()->with('error', 'PPMP records can only be added to draft APP records.');
        }

        if (!$this->eligiblePpmpQuery($app->fiscal_year)->whereKey($document->id)->exists()) {
            return back()->with('error', 'This PPMP is not eligible for APP consolidation.');
        }

        DB::transaction(function () use ($request, $app, $document) {
            AppPpmpSource::create([
                'app_consolidation_id' => $app->id,
                'procurement_document_id' => $document->id,
                'office_id' => $document->submitting_office_id,
                'included_by_user_id' => $request->user()->id,
                'included_at' => now(),
                'status' => AppPpmpSource::STATUS_INCLUDED,
            ]);

            $this->copyPpmpItems($app, $document);
            $this->recalculateTotal($app);

            SystemNotificationService::notify(
                $document->submittedBy,
                'PPMP Included in APP Draft',
                "PPMP {$document->tracking_number} has been included in {$app->title}.",
                SystemNotification::TYPE_INFO,
                'APP Consolidation',
                $app,
            );

            AuditLogger::log('APP Consolidation', 'PPMP Added to APP', 'BAC Secretariat added a PPMP to an APP draft.', $app, null, ['ppmp' => $document->tracking_number]);
        });

        return back()->with('status', 'PPMP added to APP draft.');
    }

    public function removePpmp(AppConsolidation $app, ProcurementDocument $document): RedirectResponse
    {
        if (!$app->isDraft()) {
            return back()->with('error', 'PPMP records can only be removed from draft APP records.');
        }

        DB::transaction(function () use ($app, $document) {
            AppItem::where('app_consolidation_id', $app->id)
                ->where('source_ppmp_document_id', $document->id)
                ->delete();

            AppPpmpSource::where('app_consolidation_id', $app->id)
                ->where('procurement_document_id', $document->id)
                ->delete();

            $this->recalculateTotal($app);

            AuditLogger::log('APP Consolidation', 'PPMP Removed from APP', 'BAC Secretariat removed a PPMP from an APP draft.', $app, null, ['ppmp' => $document->tracking_number]);
        });

        return back()->with('status', 'PPMP removed from APP draft.');
    }

    public function consolidate(Request $request, AppConsolidation $app): RedirectResponse
    {
        if (!$app->isDraft()) {
            return back()->with('error', 'Only draft APP records can be consolidated.');
        }

        if (!$app->ppmpSources()->exists() || !$app->appItems()->exists()) {
            return back()->with('error', 'Add at least one accepted PPMP before consolidating the APP.');
        }

        DB::transaction(function () use ($request, $app) {
            $this->recalculateTotal($app);
            $app->refresh();

            $appNumber = $app->app_number ?: $this->generateAppNumber((int) $app->fiscal_year);
            $bacOffice = $this->officeByCodeOrName('BACSEC', 'BAC Secretariat');

            $document = $app->procurementDocument ?: ProcurementDocument::create([
                'tracking_number' => $appNumber,
                'document_type' => 'APP',
                'title' => $app->title,
                'description' => $app->description,
                'fiscal_year' => $app->fiscal_year,
                'submitted_by_user_id' => $request->user()->id,
                'current_office_id' => $bacOffice?->id,
                'assigned_to_user_id' => $request->user()->id,
                'status' => ProcurementDocument::STATUS_APP_CONSOLIDATED,
                'stage' => 'APP Consolidation',
                'priority' => 'normal',
                'total_amount' => $app->total_amount,
                'submitted_at' => now(),
            ]);

            $document->update([
                'tracking_number' => $appNumber,
                'status' => ProcurementDocument::STATUS_APP_CONSOLIDATED,
                'stage' => 'APP Consolidation',
                'current_office_id' => $bacOffice?->id,
                'assigned_to_user_id' => $request->user()->id,
                'total_amount' => $app->total_amount,
            ]);

            $app->update([
                'app_number' => $appNumber,
                'procurement_document_id' => $document->id,
                'status' => AppConsolidation::STATUS_CONSOLIDATED,
                'consolidated_at' => now(),
            ]);

            $this->recordRouting($document, $request->user(), 'APP Consolidated', null, ProcurementDocument::STATUS_APP_CONSOLIDATED, 'APP consolidated from accepted PPMP records.', $bacOffice?->id, $bacOffice?->id);

            SystemNotificationService::notify(
                $request->user(),
                'APP Consolidated',
                "APP {$appNumber} has been consolidated.",
                SystemNotification::TYPE_SUCCESS,
                'APP Consolidation',
                $app,
            );

            AuditLogger::log('APP Consolidation', 'APP Consolidated', 'BAC Secretariat consolidated an APP.', $app, ['status' => AppConsolidation::STATUS_DRAFT], ['status' => AppConsolidation::STATUS_CONSOLIDATED, 'app_number' => $appNumber]);
        });

        return back()->with('status', 'APP consolidated successfully.');
    }

    public function submitForApproval(Request $request, AppConsolidation $app): RedirectResponse
    {
        if ($app->status !== AppConsolidation::STATUS_CONSOLIDATED) {
            return back()->with('error', 'Only consolidated APP records can be submitted for approval.');
        }

        $approvingOffice = $this->officeByCodeOrName('OMM', 'Office of the Municipal Mayor');
        $approvingUser = User::where('status', User::STATUS_ACTIVE)
            ->where(function (Builder $query) {
                $query->whereHas('assignedRole', fn (Builder $roleQuery) => $roleQuery->where('code', 'approving_authority'))
                    ->orWhere('role', User::ROLE_APPROVING_AUTHORITY)
                    ->orWhere('user_id', 'HOPE-001')
                    ->orWhere('user_id', 'MAYOR-001');
            })
            ->first();

        if (!$approvingOffice || !$approvingUser) {
            return back()->with('error', 'Head of the Procuring Entity routing target is not configured.');
        }

        DB::transaction(function () use ($request, $app, $approvingOffice, $approvingUser) {
            $app->loadMissing('procurementDocument');
            $document = $app->procurementDocument;

            if (!$document) {
                $document = ProcurementDocument::create([
                    'tracking_number' => $app->app_number ?: $this->generateAppNumber((int) $app->fiscal_year),
                    'document_type' => 'APP',
                    'title' => $app->title,
                    'description' => $app->description,
                    'fiscal_year' => $app->fiscal_year,
                    'submitted_by_user_id' => $request->user()->id,
                    'priority' => 'normal',
                    'submitted_at' => now(),
                    'total_amount' => $app->total_amount,
                    'status' => ProcurementDocument::STATUS_PENDING_APPROVAL,
                    'stage' => ProcurementDocument::STAGE_APPROVING_AUTHORITY_REVIEW,
                ]);
            }

            $oldStatus = $document->status;
            $fromOffice = $document->current_office_id;

            $document->update([
                'status' => ProcurementDocument::STATUS_PENDING_APPROVAL,
                'stage' => ProcurementDocument::STAGE_APPROVING_AUTHORITY_REVIEW,
                'current_office_id' => $approvingOffice->id,
                'assigned_to_user_id' => $approvingUser->id,
                'total_amount' => $app->total_amount,
            ]);

            $app->update([
                'procurement_document_id' => $document->id,
                'status' => AppConsolidation::STATUS_SUBMITTED_FOR_APPROVAL,
                'submitted_for_approval_at' => now(),
            ]);

            $this->recordRouting($document, $request->user(), 'APP Submitted for Approval', $oldStatus, ProcurementDocument::STATUS_PENDING_APPROVAL, 'APP routed to Head of the Procuring Entity.', $fromOffice, $approvingOffice->id);

            SystemNotificationService::notify(
                $approvingUser,
                'APP Submitted for Approval',
                "APP {$app->app_number} has been submitted for your approval.",
                SystemNotification::TYPE_INFO,
                'APP Consolidation',
                $app,
            );

            AuditLogger::log('APP Consolidation', 'APP Submitted for Approval', 'BAC Secretariat submitted an APP for approval.', $app);
        });

        return back()->with('status', 'APP submitted for approval.');
    }

    public function cancel(AppConsolidation $app): RedirectResponse
    {
        if (!$app->isDraft()) {
            return back()->with('error', 'Only draft APP records can be cancelled.');
        }

        $app->update([
            'status' => AppConsolidation::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        AuditLogger::log('APP Consolidation', 'APP Cancelled', 'BAC Secretariat cancelled an APP draft.', $app, ['status' => AppConsolidation::STATUS_DRAFT], ['status' => AppConsolidation::STATUS_CANCELLED], 'warning');

        return redirect()
            ->route('bac-secretariat.app.index')
            ->with('status', 'APP draft cancelled.');
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();
            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('document_reference_number', 'like', "%{$search}%")
                    ->orWhere('app_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        });

        foreach (['fiscal_year', 'status', 'created_year', 'created_month'] as $field) {
            $query->when($request->filled($field), fn (Builder $builder) => $builder->where($field, $request->input($field)));
        }

        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('updated_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('updated_at', '<=', $request->date('date_to')));
    }

    private function eligiblePpmpQuery(?int $fiscalYear = null): Builder
    {
        return ProcurementDocument::query()
            ->where('document_type', 'PPMP')
            ->whereIn('status', self::ACCEPTED_PPMP_STATUSES)
            ->when($fiscalYear, fn (Builder $query) => $query->where('fiscal_year', $fiscalYear))
            ->whereDoesntHave('appPpmpSources.appConsolidation', function (Builder $query) {
                $query->whereIn('status', [
                    AppConsolidation::STATUS_DRAFT,
                    AppConsolidation::STATUS_CONSOLIDATED,
                    AppConsolidation::STATUS_SUBMITTED_FOR_APPROVAL,
                    AppConsolidation::STATUS_APPROVED,
                    AppConsolidation::STATUS_RETURNED,
                ]);
            });
    }

    private function copyPpmpItems(AppConsolidation $app, ProcurementDocument $document): void
    {
        if (Schema::hasTable('ppmp_items')) {
            $columns = Schema::getColumnListing('ppmp_items');
            $documentColumn = in_array('procurement_document_id', $columns, true) ? 'procurement_document_id' : (in_array('document_id', $columns, true) ? 'document_id' : null);

            if ($documentColumn) {
                $items = DB::table('ppmp_items')->where($documentColumn, $document->id)->get();

                foreach ($items as $item) {
                    $description = $this->firstValue($item, ['general_description', 'description', 'item_description', 'name', 'title']) ?? $document->title;
                    $quantity = (float) ($this->firstValue($item, ['quantity', 'qty']) ?? 1);
                    $unitCost = (float) ($this->firstValue($item, ['estimated_unit_cost', 'unit_cost', 'cost']) ?? 0);
                    $totalCost = (float) ($this->firstValue($item, ['estimated_total_cost', 'total_cost', 'amount']) ?? ($quantity * $unitCost));

                    AppItem::create([
                        'app_consolidation_id' => $app->id,
                        'source_ppmp_document_id' => $document->id,
                        'source_ppmp_item_id' => $item->id ?? null,
                        'office_id' => $document->submitting_office_id,
                        'item_no' => $this->firstValue($item, ['item_no', 'item_number']),
                        'general_description' => $description,
                        'quantity' => $quantity,
                        'unit' => $this->firstValue($item, ['unit', 'uom']),
                        'estimated_unit_cost' => $unitCost,
                        'estimated_total_cost' => $totalCost,
                        'procurement_mode' => $this->firstValue($item, ['procurement_mode', 'mode']),
                        'schedule_quarter' => $this->firstValue($item, ['schedule_quarter', 'quarter']),
                        'category' => $this->firstValue($item, ['category']),
                        'remarks' => $this->firstValue($item, ['remarks', 'notes']),
                    ]);
                }

                if ($items->isNotEmpty()) {
                    return;
                }
            }
        }

        AppItem::create([
            'app_consolidation_id' => $app->id,
            'source_ppmp_document_id' => $document->id,
            'office_id' => $document->submitting_office_id,
            'general_description' => $document->description ?: $document->title,
            'quantity' => 1,
            'unit' => 'lot',
            'estimated_unit_cost' => $document->total_amount,
            'estimated_total_cost' => $document->total_amount,
            'remarks' => 'Summarized from PPMP document record.',
        ]);
    }

    private function recalculateTotal(AppConsolidation $app): void
    {
        $app->update([
            'total_amount' => AppItem::where('app_consolidation_id', $app->id)->sum('estimated_total_cost'),
        ]);
    }

    private function generateAppNumber(int $year): string
    {
        $last = AppConsolidation::where('fiscal_year', $year)
            ->whereNotNull('app_number')
            ->lockForUpdate()
            ->orderByDesc('app_number')
            ->value('app_number');

        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return 'APP-' . $year . '-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function summary(): array
    {
        return [
            'drafts' => AppConsolidation::where('status', AppConsolidation::STATUS_DRAFT)->count(),
            'consolidated' => AppConsolidation::where('status', AppConsolidation::STATUS_CONSOLIDATED)->count(),
            'eligiblePpmps' => $this->eligiblePpmpQuery()->count(),
            'totalAmount' => AppConsolidation::whereNotIn('status', [AppConsolidation::STATUS_CANCELLED])->sum('total_amount'),
        ];
    }

    private function summaryBy(AppConsolidation $app, string $type): array
    {
        $items = $app->appItems;

        return match ($type) {
            'office' => $items->groupBy(fn (AppItem $item) => $item->office?->name ?? 'Unassigned Office')
                ->map(fn ($group) => ['count' => $group->count(), 'total' => $group->sum('estimated_total_cost')])
                ->all(),
            'category' => $items->groupBy(fn (AppItem $item) => $item->category ?: 'Uncategorized')
                ->map(fn ($group) => ['count' => $group->count(), 'total' => $group->sum('estimated_total_cost')])
                ->all(),
            default => $items->groupBy(fn (AppItem $item) => $item->procurement_mode ?: 'Not specified')
                ->map(fn ($group) => ['count' => $group->count(), 'total' => $group->sum('estimated_total_cost')])
                ->all(),
        };
    }

    private function formRules(): array
    {
        return [
            'fiscal_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
        ];
    }

    private function appStatuses(): array
    {
        return [
            AppConsolidation::STATUS_DRAFT,
            AppConsolidation::STATUS_CONSOLIDATED,
            AppConsolidation::STATUS_SUBMITTED_FOR_APPROVAL,
            AppConsolidation::STATUS_APPROVED,
            AppConsolidation::STATUS_RETURNED,
            AppConsolidation::STATUS_CANCELLED,
        ];
    }

    private function firstValue(object $item, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (property_exists($item, $key) && $item->{$key} !== null && $item->{$key} !== '') {
                return $item->{$key};
            }
        }

        return null;
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

    private function officeByCodeOrName(string $code, string $name): ?Office
    {
        return Office::where('code', $code)->orWhere('name', $name)->first();
    }
}
