<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AbstractQuotation;
use App\Models\AnnualProcurementPlan;
use App\Models\AuditLog;
use App\Models\BacResolution;
use App\Models\DocumentApproval;
use App\Models\Office;
use App\Models\Permission;
use App\Models\ProcurementDocument;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\Role;
use App\Models\SignatureRequest;
use App\Services\AiDocumentVerificationDashboardService;
use App\Services\DashboardWidgetService;
use App\Services\SignatureRequestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $route = $user?->dashboardRoute();

        if ($route && Route::has($route)) {
            return redirect()->route($route);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('error', 'Your account dashboard is not configured. Please contact the administrator.');
    }

    public function admin(): View
    {
        return $this->view('Admin Dashboard', User::ROLE_ADMIN, [
            'heroLabel' => 'System Administration',
            'subtitle' => 'Manage users, roles, offices, monitoring, and AI configuration for PaperTrail.',
            'primaryActionRoute' => 'admin.users.index',
        ]);
    }

    public function headOffice(): View
    {
        $user = Auth::user();
        $officeName = $this->officeName($user);

        return $this->view($officeName . ' Dashboard', User::ROLE_HEAD_OFFICE, [
            'heroLabel' => 'End-User Office Portal',
            'subtitle' => 'Monitor procurement documents, workflow status, and pending actions for your office.',
            'primaryActionRoute' => 'head-office.ppmp.create',
            'headOfficeDashboard' => $this->headOfficeDocumentDashboard($user),
        ]);
    }

    public function budget(): View
    {
        return $this->view('Budget Officer Dashboard', User::ROLE_BUDGET);
    }

    public function accounting(): View
    {
        return $this->view('Accounting Officer Dashboard', User::ROLE_ACCOUNTING);
    }

    public function bacSecretariat(): View
    {
        return $this->view('BAC Secretariat Dashboard', User::ROLE_BAC_SECRETARIAT);
    }

    public function bacMember(): View
    {
        return $this->view('BAC Member Dashboard', User::ROLE_BAC_MEMBER);
    }

    public function bacChair(): View
    {
        return $this->view('BAC Chair Dashboard', User::ROLE_BAC_CHAIR);
    }

    public function approvingAuthority(): View
    {
        return $this->view('Head of the Procuring Entity Dashboard', User::ROLE_APPROVING_AUTHORITY);
    }

    public function prNumbering(): View
    {
        return $this->view('PR Numbering Staff Dashboard', User::ROLE_PR_NUMBERING, [
            'heroLabel' => 'PR Number Assignment',
            'subtitle' => 'Assign official LGU Purchase Request numbers before BAC Secretariat validation.',
            'primaryActionRoute' => 'pr-numbering.pending.index',
        ]);
    }

    private function view(string $title, string $role, array $overrides = []): View
    {
        $user = Auth::user();
        $config = $this->dashboardConfig($role);
        $isBacsecPrSignatureOnly = $role === User::ROLE_BAC_SECRETARIAT && $this->isBacsecPrSignatureOnly($user);
        $isBacsec002PrWorkspace = $role === User::ROLE_BAC_SECRETARIAT
            && $user
            && method_exists($user, 'hasBacsec002PurchaseRequestCapability')
            && $user->hasBacsec002PurchaseRequestCapability();

        if ($isBacsecPrSignatureOnly) {
            $config['primaryAction'] = 'View Signature Requests';
            $config['tasks'] = [
                'Review pending PR signature requests',
                'Sign PR forms routed for BAC Secretariat signature',
                'Check signed document records',
            ];
        }

        if ($isBacsec002PrWorkspace) {
            $config['primaryAction'] = 'Prepare BAC Resolution';
            $config['tasks'] = [
                'Review completed PR references routed after PR numbering and signatures',
                'Prepare BAC Resolution drafts from assigned PR references',
                'Track BAC Resolution records until they are submitted for confirmation',
            ];
            $overrides['primaryActionRoute'] ??= 'bac-secretariat.resolutions.create';
        }

        $stats = match (true) {
            $isBacsecPrSignatureOnly => [],
            $isBacsec002PrWorkspace => $this->bacsec002PrStats($user),
            default => $config['stats'],
        };
        $aiVerification = app(AiDocumentVerificationDashboardService::class)->quickViewFor($user);
        $widgetService = app(DashboardWidgetService::class);

        if ($this->roleCanSign($role)) {
            $stats = array_merge($this->signatureStats($user), $stats);
        }

        return view('dashboard.show', [
            'title' => $title,
            'role' => $role,
            'heroLabel' => $overrides['heroLabel'] ?? 'Role-Based Workspace',
            'subtitle' => $overrides['subtitle'] ?? 'Monitor procurement documents, workflow status, and pending actions.',
            'primaryAction' => $config['primaryAction'],
            'primaryActionRoute' => $overrides['primaryActionRoute'] ?? null,
            'stats' => $stats,
            'tasks' => $config['tasks'],
            'activities' => $this->activities(),
            'categories' => $this->categories($role, $stats),
            'aiVerification' => $aiVerification,
            'headOfficeDashboard' => $overrides['headOfficeDashboard'] ?? null,
            'signatureOnlyDashboard' => $isBacsecPrSignatureOnly,
            'bacsec002ResolutionWorkspace' => $isBacsec002PrWorkspace,
            'signatureDashboard' => $isBacsecPrSignatureOnly ? $this->signatureDashboard($user) : null,
            'calendar' => $user ? $widgetService->calendarData($user, request('calendar_month'), request('calendar_date')) : [],
            'adminStatistics' => $user && $role === User::ROLE_ADMIN ? $widgetService->adminStatistics() : null,
        ]);
    }

    private function officeName(?User $user): string
    {
        return $user?->assignedOffice?->name
            ?? $user?->office
            ?? 'Office';
    }

    private function dashboardConfig(string $role): array
    {
        return match ($role) {
            User::ROLE_ADMIN => [
                'primaryAction' => 'Manage Users',
                'stats' => [
                    ['label' => 'Total Users', 'value' => (string) User::count(), 'tone' => 'navy', 'href' => $this->routeUrl('admin.users.index')],
                    ['label' => 'Active Users', 'value' => (string) User::where('status', User::STATUS_ACTIVE)->count(), 'tone' => 'green', 'href' => $this->routeUrl('admin.users.index')],
                    ['label' => 'Roles', 'value' => (string) Role::count(), 'tone' => 'green', 'href' => $this->routeUrl('admin.roles.index')],
                    ['label' => 'Offices', 'value' => (string) Office::count(), 'tone' => 'blue', 'href' => $this->routeUrl('admin.offices.index')],
                    ['label' => 'Permissions', 'value' => (string) Permission::count(), 'tone' => 'purple', 'href' => $this->routeUrl('admin.roles.index')],
                    ['label' => "Today's Audit Logs", 'value' => (string) AuditLog::whereDate('created_at', today())->count(), 'tone' => 'navy', 'href' => $this->routeUrl('admin.audit.index')],
                    ['label' => 'System Logs', 'value' => (string) AuditLog::whereIn('module', ['Rules Configuration', 'Settings', 'Workflow', 'Permissions', 'Roles', 'Offices'])->count(), 'tone' => 'gold', 'href' => $this->routeUrl('admin.audit.index')],
                    ['label' => 'AI Events', 'value' => (string) AuditLog::where('module', 'like', 'AI%')->count(), 'tone' => 'blue', 'href' => $this->routeUrl('admin.ai-summary.index')],
                ],
                'tasks' => ['Review audit logs', 'Manage inactive users', 'Check role assignments', 'Review AI configuration'],
            ],
            User::ROLE_HEAD_OFFICE => [
                'primaryAction' => 'Submit PPMP',
                'stats' => $this->headOfficeStats(),
                'tasks' => ['Prepare PPMP draft', 'Submit PPMP to BAC Secretariat', 'Check PPMP notifications'],
            ],
            User::ROLE_BUDGET => [
                'primaryAction' => 'Review Budget',
                'stats' => $this->budgetStats(),
                'tasks' => ['Review fund availability', 'Return incomplete document', 'Check budget notifications'],
            ],
            User::ROLE_ACCOUNTING => [
                'primaryAction' => 'Review Accounting',
                'stats' => $this->accountingStats(),
                'tasks' => ['Verify accounting requirements', 'Review returned documents', 'Check supporting attachments'],
            ],
            User::ROLE_BAC_SECRETARIAT => [
                'primaryAction' => 'Route Document',
                'stats' => $this->bacSecretariatStats(),
                'tasks' => ['Route incoming document', 'Update tracking status', 'Check APP consolidation queue'],
            ],
            User::ROLE_BAC_MEMBER => [
                'primaryAction' => 'Review Document',
                'stats' => $this->bacMemberStats(),
                'tasks' => ['Review assigned procurement document', 'Check BAC deliberation notes', 'Confirm review status'],
            ],
            User::ROLE_BAC_CHAIR => [
                'primaryAction' => 'Review Approval',
                'stats' => $this->bacChairStats(),
                'tasks' => ['Confirm BAC review', 'Review approval queue', 'Check pending reports'],
            ],
            User::ROLE_APPROVING_AUTHORITY => [
                'primaryAction' => 'Approve Document',
                'stats' => $this->approvingAuthorityStats(),
                'tasks' => ['Approve pending document', 'Review returned documents', 'Check approval notifications'],
            ],
            User::ROLE_PR_NUMBERING => [
                'primaryAction' => 'Assign PR Numbers',
                'stats' => $this->prNumberingStats(),
                'tasks' => ['Review pending PR number requests', 'Assign official PR numbers', 'Return PRs that need correction'],
            ],
            default => [
                'primaryAction' => 'View Documents',
                'stats' => [],
                'tasks' => [],
            ],
        };
    }

    private function activities(): array
    {
        return [
            'PPMP submitted by Engineering Office',
            'Purchase Request routed to Budget Office',
            'Document returned for missing attachment',
            'Budget review completed',
            'BAC Secretariat updated routing status',
        ];
    }

    private function categories(string $role, array $stats = []): array
    {
        if (! empty($stats)) {
            return collect($stats)
                ->take(4)
                ->map(fn (array $stat): array => [
                    'label' => $stat['label'] ?? 'Workspace Area',
                    'value' => $stat['value'] ?? '0',
                ])
                ->values()
                ->all();
        }

        return match ($role) {
            User::ROLE_ADMIN => [
                ['label' => 'Users', 'value' => (string) User::count()],
                ['label' => 'Active Users', 'value' => (string) User::where('status', User::STATUS_ACTIVE)->count()],
                ['label' => 'Roles', 'value' => (string) Role::count()],
                ['label' => 'Audit Trail', 'value' => (string) AuditLog::count()],
            ],
            User::ROLE_BAC_SECRETARIAT => [
                ['label' => 'Incoming', 'value' => '0'],
                ['label' => 'APP', 'value' => '0'],
                ['label' => 'PR Review', 'value' => '0'],
                ['label' => 'BAC Resolution', 'value' => '0'],
            ],
            default => [],
        };
    }

    private function headOfficeDocumentDashboard(?User $user): array
    {
        $base = $this->headOfficeDocumentBase($user);
        $returnedStatuses = [
            ProcurementDocument::STATUS_RETURNED_BY_PR_NUMBERING_STAFF,
            ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
            ProcurementDocument::STATUS_RETURNED_BY_BUDGET,
            ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING,
            ProcurementDocument::STATUS_RETURNED_BY_BAC_MEMBER,
            ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR,
            ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY,
            ProcurementDocument::STATUS_PO_RETURNED,
        ];

        $documentTypes = [
            [
                'key' => 'PPMP',
                'title' => 'PPMP',
                'description' => 'Project Procurement Management Plan',
                'types' => ['PPMP'],
                'accent' => 'ppmp',
                'icon' => 'ppmp',
                'href' => $this->routeUrl('head-office.ppmp.index'),
                'count' => (clone $base)->whereIn('document_type', ['PPMP'])->count(),
                'actionLabel' => 'View PPMP',
            ],
            [
                'key' => 'PR',
                'title' => 'PR',
                'description' => 'Purchase Request',
                'types' => ['PR', 'Purchase Request'],
                'accent' => 'pr',
                'icon' => 'pr',
                'href' => $this->routeUrl('head-office.pr.index'),
                'count' => (clone $base)->whereIn('document_type', ['PR', 'Purchase Request'])->count(),
                'actionLabel' => 'View PR',
            ],
            [
                'key' => 'ALL',
                'title' => 'My Documents',
                'description' => 'All procurement records linked to your office',
                'types' => [],
                'accent' => 'documents',
                'icon' => 'document',
                'href' => $this->routeUrl('head-office.documents.index'),
                'count' => (clone $base)->count(),
                'actionLabel' => 'View My Documents',
            ],
            [
                'key' => 'RETURNED',
                'title' => 'Returned Documents',
                'description' => 'Documents that need correction or clarification',
                'types' => [],
                'accent' => 'returned',
                'icon' => 'return',
                'href' => $this->routeUrl('head-office.documents.index', ['tab' => 'returned']),
                'count' => (clone $base)->whereIn('status', $returnedStatuses)->count(),
                'actionLabel' => 'View Returned',
            ],
        ];

        $documentCards = collect($documentTypes)
            ->filter(fn (array $type): bool => ! empty($type['href']))
            ->values()
            ->all();

        $documents = (clone $base)
            ->with([
                'submittingOffice',
                'currentOffice',
                'assignedTo',
                'submittedBy',
                'preparedBy',
                'routingHistories.actionBy',
                'routingHistories.fromOffice',
                'routingHistories.toOffice',
            ])
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(fn (ProcurementDocument $document): array => $this->dashboardDocumentRow($document))
            ->values()
            ->all();

        return [
            'totalDocuments' => (clone $base)->count(),
            'documentCards' => $documentCards,
            'documents' => $documents,
            'selectedDocument' => $documents[0] ?? null,
            'tabs' => array_merge([
                [
                    'label' => 'All Documents',
                    'count' => (clone $base)->count(),
                    'href' => $this->routeUrl('head-office.documents.index'),
                    'active' => true,
                ],
            ], collect($documentCards)->map(fn (array $card): array => [
                'label' => $card['title'],
                'count' => $card['count'],
                'href' => $card['href'],
                'active' => false,
            ])->all()),
            'searchAction' => $this->routeUrl('head-office.documents.index'),
            'createAction' => $this->routeUrl('head-office.ppmp.create'),
            'officeName' => $this->officeName($user),
        ];
    }

    private function headOfficeDocumentBase(?User $user): Builder
    {
        $query = ProcurementDocument::query();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $builder) use ($user): void {
            if ($user->office_id) {
                $builder->where('submitting_office_id', $user->office_id);
            }

            $builder->orWhere('submitted_by_user_id', $user->id)
                ->orWhere('prepared_by_user_id', $user->id);
        });
    }

    private function dashboardDocumentRow(ProcurementDocument $document): array
    {
        $type = $this->dashboardDocumentType($document->document_type);
        $status = (string) ($document->status ?? '');

        return [
            'id' => $document->id,
            'number' => $document->tracking_number ?: $document->ppmp_no ?: $document->pr_no ?: ('Draft #' . $document->id),
            'type' => $type,
            'typeLabel' => $this->dashboardDocumentTypeLabel($type),
            'title' => $document->title ?: $document->purpose ?: $document->description ?: 'Untitled procurement document',
            'office' => $document->submittingOffice?->name ?? $this->officeName($document->preparedBy ?? $document->submittedBy),
            'stage' => $document->stage ?: $this->statusLabel($status),
            'status' => $this->statusLabel($status),
            'statusKey' => $status ?: 'unknown',
            'statusTone' => $this->statusTone($status),
            'createdAt' => $document->created_at?->format('M d, Y h:i A') ?? 'N/A',
            'submittedAt' => $document->submitted_at?->format('M d, Y h:i A') ?? 'Not submitted',
            'daysLeft' => $this->daysLeft($document),
            'progress' => $this->workflowProgress($status, $type),
            'amount' => 'PHP ' . number_format((float) $document->total_amount, 2),
            'currentOffice' => $document->assignedTo
                ? trim(($document->assignedTo->user_id ? $document->assignedTo->user_id.' - ' : '').$document->assignedTo->name)
                : ($document->currentOffice?->name ?? 'Not routed'),
            'owner' => $document->submittedBy?->name ?? $document->preparedBy?->name ?? 'N/A',
            'viewUrl' => $this->routeUrl('head-office.documents.show', ['document' => $document]),
            'printUrl' => $this->documentPrintUrl($document, $type),
            'history' => $this->dashboardDocumentHistory($document),
        ];
    }

    private function dashboardDocumentHistory(ProcurementDocument $document): array
    {
        $history = $document->routingHistories
            ->sortByDesc(fn ($item) => $item->action_at ?? $item->created_at)
            ->take(4)
            ->map(fn ($item): array => [
                'label' => $item->action ?: $this->statusLabel($item->status_to),
                'date' => ($item->action_at ?? $item->created_at)?->format('M d, Y h:i A') ?? 'No timestamp',
                'location' => $item->toOffice?->name ?? $item->fromOffice?->name ?? 'PaperTrail',
                'actor' => $item->actionBy?->name ?? 'System',
                'comments' => $item->comments,
            ])
            ->values()
            ->all();

        if (! empty($history)) {
            return $history;
        }

        return [[
            'label' => 'Document Created',
            'date' => $document->created_at?->format('M d, Y h:i A') ?? 'No timestamp',
            'location' => $document->submittingOffice?->name ?? $this->officeName($document->preparedBy ?? $document->submittedBy),
            'actor' => $document->preparedBy?->name ?? $document->submittedBy?->name ?? 'System',
            'comments' => null,
        ]];
    }

    private function dashboardDocumentType(?string $type): string
    {
        return match (strtolower((string) $type)) {
            'purchase request' => 'PR',
            'purchase order' => 'PO',
            'annual procurement plan' => 'APP',
            default => strtoupper((string) ($type ?: 'DOC')),
        };
    }

    private function dashboardDocumentTypeLabel(string $type): string
    {
        return match ($type) {
            'PPMP' => 'Project Procurement Management Plan',
            'APP' => 'Annual Procurement Plan',
            'PR' => 'Purchase Request',
            'PO' => 'Purchase Order',
            default => 'Procurement Document',
        };
    }

    private function documentPrintUrl(ProcurementDocument $document, string $type): ?string
    {
        return match ($type) {
            'PPMP' => $this->routeUrl('head-office.ppmp.print', ['document' => $document]),
            'PR' => $this->routeUrl('head-office.pr.print', ['document' => $document]),
            default => null,
        };
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES => 'Pending Signature',
            ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED => 'Signed',
            ProcurementDocument::STATUS_PENDING_PPMP_REVIEW => 'Submitted to BAC',
            ProcurementDocument::STATUS_UNDER_PPMP_REVIEW => 'Under APP Consolidation',
            ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION => 'Approved',
            default => $this->formatStatusLabel($status),
        };
    }

    private function formatStatusLabel(?string $status): string
    {
        $label = ucwords(str_replace('_', ' ', (string) ($status ?: 'Unknown')));

        return str_replace('Ppmp', 'PPMP', $label);
    }

    private function statusTone(?string $status): string
    {
        $status = strtolower((string) $status);

        if ($status === ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES) {
            return 'pending';
        }

        if ($status === ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED) {
            return 'success';
        }

        if (str_contains($status, 'return')) {
            return 'danger';
        }

        if (str_contains($status, 'approved') || str_contains($status, 'accepted') || str_contains($status, 'completed')) {
            return 'success';
        }

        if (str_contains($status, 'review') || str_contains($status, 'validation') || str_contains($status, 'routed')) {
            return 'progress';
        }

        if (str_contains($status, 'draft')) {
            return 'muted';
        }

        return 'pending';
    }

    private function workflowProgress(?string $status, ?string $type = null): int
    {
        $status = strtolower((string) $status);
        $type = strtoupper((string) $type);

        if ($type === 'PPMP') {
            return match ($status) {
                ProcurementDocument::STATUS_PPMP_DRAFT,
                ProcurementDocument::STATUS_PPMP_SIGNATURE_RETURNED => 1,
                ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES => 2,
                ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED => 3,
                ProcurementDocument::STATUS_PENDING_PPMP_REVIEW => 4,
                ProcurementDocument::STATUS_UNDER_PPMP_REVIEW => 5,
                ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION => 6,
                default => 1,
            };
        }

        if (str_contains($status, 'completed')) {
            return 5;
        }

        if (str_contains($status, 'approved') || str_contains($status, 'accepted') || str_contains($status, 'ready') || str_contains($status, 'issued')) {
            return 4;
        }

        if (str_contains($status, 'review') || str_contains($status, 'validation') || str_contains($status, 'routed') || str_contains($status, 'pending')) {
            return 3;
        }

        if (str_contains($status, 'return') || str_contains($status, 'submitted')) {
            return 2;
        }

        return 1;
    }

    private function daysLeft(ProcurementDocument $document): array
    {
        foreach (['requested_delivery_date', 'target_date', 'due_date', 'deadline_at', 'deadline'] as $field) {
            $value = $document->getAttribute($field);

            if (! $value) {
                continue;
            }

            try {
                $date = Carbon::parse($value)->startOfDay();
            } catch (\Throwable) {
                continue;
            }

            $days = today()->diffInDays($date, false);

            return [
                'label' => $days < 0 ? abs($days) . ' days late' : ($days === 0 ? 'Due today' : $days . ' days left'),
                'tone' => $days < 0 ? 'danger' : ($days <= 3 ? 'warning' : 'success'),
            ];
        }

        return [
            'label' => 'No target date',
            'tone' => 'muted',
        ];
    }

    private function budgetStats(): array
    {
        $user = Auth::user();
        $base = ProcurementDocument::query()->visibleToBudgetOfficer($user);

        return [
            ['label' => 'Pending Budget Review', 'value' => (string) (clone $base)->where('status', ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW)->count(), 'tone' => 'gold', 'href' => $this->routeUrl('budget.pending-review.index', ['status' => ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW])],
            ['label' => 'Under Review', 'value' => (string) (clone $base)->where('status', ProcurementDocument::STATUS_UNDER_BUDGET_REVIEW)->count(), 'tone' => 'navy', 'href' => $this->routeUrl('budget.pending-review.index', ['status' => ProcurementDocument::STATUS_UNDER_BUDGET_REVIEW])],
            ['label' => 'Returned', 'value' => (string) ProcurementDocument::query()->returnedByBudgetOfficer($user)->count(), 'tone' => 'blue', 'href' => $this->routeUrl('budget.returned.index')],
            ['label' => 'Reviewed', 'value' => (string) ProcurementDocument::query()->reviewedByBudgetOfficer($user)->count(), 'tone' => 'green', 'href' => $this->routeUrl('budget.reviewed.index')],
        ];
    }

    private function headOfficeStats(): array
    {
        $user = Auth::user();

        if (!$user) {
            return [];
        }

        $base = ProcurementDocument::query()
            ->where(function ($query) use ($user) {
                if ($user->office_id) {
                    $query->where('submitting_office_id', $user->office_id);
                }

                $query->orWhere('submitted_by_user_id', $user->id)
                    ->orWhere('prepared_by_user_id', $user->id);
            });

        $draftStatuses = [
            ProcurementDocument::STATUS_PPMP_DRAFT,
            ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES,
            ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED,
            ProcurementDocument::STATUS_PO_DRAFT,
            'draft',
        ];

        $returnedStatuses = [
            ProcurementDocument::STATUS_RETURNED_BY_PR_NUMBERING_STAFF,
            ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
            ProcurementDocument::STATUS_RETURNED_BY_BUDGET,
            ProcurementDocument::STATUS_RETURNED_BY_ACCOUNTING,
            ProcurementDocument::STATUS_RETURNED_BY_BAC_MEMBER,
            ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR,
            ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY,
            ProcurementDocument::STATUS_PO_RETURNED,
        ];

        $approvedStatuses = [
            ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION,
            ProcurementDocument::STATUS_APPROVED,
            ProcurementDocument::STATUS_APP_APPROVED,
            ProcurementDocument::STATUS_READY_FOR_PO,
            ProcurementDocument::STATUS_PO_APPROVED,
            ProcurementDocument::STATUS_PO_ISSUED,
            ProcurementDocument::STATUS_PO_COMPLETED,
            'completed',
        ];

        return [
            ['label' => 'Submitted', 'value' => (string) (clone $base)->whereNotIn('status', $draftStatuses)->count(), 'tone' => 'navy', 'href' => $this->routeUrl('head-office.documents.index')],
            ['label' => 'Returned', 'value' => (string) (clone $base)->whereIn('status', $returnedStatuses)->count(), 'tone' => 'gold', 'href' => $this->routeUrl('head-office.documents.index', ['tab' => 'returned'])],
            ['label' => 'Approved / Accepted', 'value' => (string) (clone $base)->whereIn('status', $approvedStatuses)->count(), 'tone' => 'green', 'href' => $this->routeUrl('head-office.documents.index', ['status' => 'approved'])],
            ['label' => 'Pending', 'value' => (string) (clone $base)->whereNotIn('status', array_merge($returnedStatuses, $approvedStatuses))->count(), 'tone' => 'blue', 'href' => $this->routeUrl('head-office.documents.index', ['status' => 'pending'])],
        ];
    }

    private function accountingStats(): array
    {
        $user = Auth::user();
        $base = ProcurementDocument::query()->visibleToAccountingOfficer($user);

        return [
            ['label' => 'Pending Accounting Review', 'value' => (string) (clone $base)->where('status', ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW)->count(), 'tone' => 'gold', 'href' => $this->routeUrl('accounting.pending-review.index', ['status' => ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW])],
            ['label' => 'Under Review', 'value' => (string) (clone $base)->where('status', ProcurementDocument::STATUS_UNDER_ACCOUNTING_REVIEW)->count(), 'tone' => 'navy', 'href' => $this->routeUrl('accounting.pending-review.index', ['status' => ProcurementDocument::STATUS_UNDER_ACCOUNTING_REVIEW])],
            ['label' => 'Returned', 'value' => (string) ProcurementDocument::query()->returnedByAccountingOfficer($user)->count(), 'tone' => 'blue', 'href' => $this->routeUrl('accounting.returned.index')],
            ['label' => 'Reviewed', 'value' => (string) ProcurementDocument::query()->reviewedByAccountingOfficer($user)->count(), 'tone' => 'green', 'href' => $this->routeUrl('accounting.reviewed.index')],
        ];
    }

    private function bacSecretariatStats(): array
    {
        $user = Auth::user();
        $ppmpBase = ProcurementDocument::query()
            ->where('document_type', 'PPMP')
            ->where(fn (Builder $query) => $this->whereCompletedPpmpSignature($query))
            ->when($user, function (Builder $query) use ($user) {
                $query->where(function (Builder $assignment) use ($user) {
                    $assignment->where('assigned_to_user_id', $user->id)
                        ->orWhereNull('assigned_to_user_id');
                });
            });
        $incomingBase = $user
            ? ProcurementDocument::query()->visibleToBacSecretariat($user)
            : ProcurementDocument::query();

        if ($user) {
            $incomingBase->where(function (Builder $visibility) use ($user) {
                $visibility->whereNull('document_type')
                    ->orWhereNotIn('document_type', ['PPMP', 'Project Procurement Management Plan'])
                    ->orWhere('assigned_to_user_id', $user->id)
                    ->orWhereNull('assigned_to_user_id');
            });
        }
        $incomingStatuses = [
            ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW,
            ProcurementDocument::STATUS_RECEIVED_BY_BAC_SECRETARIAT,
            ProcurementDocument::STATUS_SUBMITTED_TO_BAC_SECRETARIAT,
            ProcurementDocument::STATUS_PR_SUBMITTED,
            ProcurementDocument::STATUS_PR_RECEIVED_BY_BAC_SECRETARIAT,
            ProcurementDocument::STATUS_UNDER_PR_VALIDATION,
            ProcurementDocument::STATUS_PENDING_PPMP_REVIEW,
        ];
        $prStatuses = [
            ProcurementDocument::STATUS_SUBMITTED_TO_BAC_SECRETARIAT,
            ProcurementDocument::STATUS_PR_SUBMITTED,
            ProcurementDocument::STATUS_PR_RECEIVED_BY_BAC_SECRETARIAT,
            ProcurementDocument::STATUS_UNDER_PR_VALIDATION,
            ProcurementDocument::STATUS_NO_PPMP_RECORD_FOUND,
            ProcurementDocument::STATUS_PENDING_SUPPLEMENTAL_APP,
            ProcurementDocument::STATUS_SUPPLEMENTAL_APP_CREATED,
            ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION,
        ];
        $prBase = $user
            ? ProcurementDocument::query()->purchaseRequestsForBacSecretariat($user)
            : ProcurementDocument::query()->whereIn('document_type', ['PR', 'Purchase Request']);
        $assignedActions = (clone $prBase)
            ->where('assigned_to_user_id', $user?->id)
            ->whereIn('status', [
                ProcurementDocument::STATUS_PR_RECEIVED_BY_BAC_SECRETARIAT,
                ProcurementDocument::STATUS_UNDER_PR_VALIDATION,
                ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION,
            ])
            ->count();

        return [
            ['label' => 'Pending Actions', 'value' => (string) $assignedActions, 'tone' => 'gold', 'href' => $this->routeUrl('bac-secretariat.pr.index')],
            ['label' => 'Incoming Documents', 'value' => (string) (clone $incomingBase)->whereIn('status', $incomingStatuses)->count(), 'tone' => 'blue', 'href' => $this->routeUrl('bac-secretariat.incoming.index')],
            ['label' => 'PPMP Review', 'value' => (string) (clone $ppmpBase)->whereIn('status', [ProcurementDocument::STATUS_PENDING_PPMP_REVIEW, ProcurementDocument::STATUS_UNDER_PPMP_REVIEW])->count(), 'tone' => 'blue', 'href' => $this->routeUrl('bac-secretariat.ppmp.index')],
            ['label' => 'APP Records', 'value' => (string) AnnualProcurementPlan::count(), 'tone' => 'green', 'href' => $this->routeUrl('bac-secretariat.app.index')],
            ['label' => 'PR Review', 'value' => (string) (clone $prBase)->whereIn('status', $prStatuses)->count(), 'tone' => 'navy', 'href' => $this->routeUrl('bac-secretariat.pr.index')],
            ['label' => 'RFQ', 'value' => (string) Rfq::count(), 'tone' => 'green', 'href' => $this->routeUrl('bac-secretariat.rfqs.index')],
            ['label' => 'Abstract', 'value' => (string) AbstractQuotation::count(), 'tone' => 'blue', 'href' => $this->routeUrl('bac-secretariat.abstracts.index')],
            ['label' => 'BAC Resolution', 'value' => (string) BacResolution::count(), 'tone' => 'gold', 'href' => $this->routeUrl('bac-secretariat.resolutions.index')],
            ['label' => 'Purchase Orders', 'value' => (string) PurchaseOrder::count(), 'tone' => 'navy', 'href' => $this->routeUrl('bac-secretariat.purchase-orders.index')],
        ];
    }

    private function bacsec002PrStats(User $user): array
    {
        $assignedPrBase = ProcurementDocument::query()
            ->purchaseRequestsForBacSecretariat($user);
        $resolutionBase = BacResolution::query()
            ->where('prepared_by_user_id', $user->id);
        $submittedStatuses = [
            ProcurementDocument::STATUS_PR_SUBMITTED,
            ProcurementDocument::STATUS_SUBMITTED_TO_BAC_SECRETARIAT,
            ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION,
        ];

        return [
            ['label' => 'Submitted PRs', 'value' => (string) (clone $assignedPrBase)->whereIn('status', $submittedStatuses)->count(), 'tone' => 'gold', 'href' => $this->routeUrl('bac-secretariat.pr.index', ['status' => 'submitted'])],
            ['label' => 'Draft BAC Resolutions', 'value' => (string) (clone $resolutionBase)->where('status', BacResolution::STATUS_DRAFT)->count(), 'tone' => 'blue', 'href' => $this->routeUrl('bac-secretariat.resolutions.index', ['status' => BacResolution::STATUS_DRAFT])],
            ['label' => 'Prepared BAC Resolutions', 'value' => (string) (clone $resolutionBase)->count(), 'tone' => 'navy', 'href' => $this->routeUrl('bac-secretariat.resolutions.index')],
        ];
    }

    private function whereCompletedPpmpSignature(Builder $query): void
    {
        $query
            ->whereExists(function ($signature) {
                $signature->selectRaw('1')
                    ->from('signature_requests')
                    ->whereColumn('signature_requests.document_id', 'procurement_documents.id')
                    ->where('signature_requests.document_type', 'ppmp')
                    ->where('signature_requests.is_required', true);
            })
            ->whereNotExists(function ($signature) {
                $signature->selectRaw('1')
                    ->from('signature_requests')
                    ->whereColumn('signature_requests.document_id', 'procurement_documents.id')
                    ->where('signature_requests.document_type', 'ppmp')
                    ->where('signature_requests.is_required', true)
                    ->where('signature_requests.status', '!=', SignatureRequest::STATUS_SIGNED);
            });
    }

    private function bacMemberStats(): array
    {
        $user = Auth::user();
        $base = ProcurementDocument::query()->assignedToBacMember($user);

        return [
            ['label' => 'Documents for Review', 'value' => (string) (clone $base)->where('status', ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW)->count(), 'tone' => 'gold', 'href' => $this->routeUrl('bac-member.review.index', ['status' => ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW])],
            ['label' => 'Under Review', 'value' => (string) (clone $base)->where('status', ProcurementDocument::STATUS_UNDER_BAC_MEMBER_REVIEW)->count(), 'tone' => 'blue', 'href' => $this->routeUrl('bac-member.review.index', ['status' => ProcurementDocument::STATUS_UNDER_BAC_MEMBER_REVIEW])],
            ['label' => 'Reviewed', 'value' => (string) ProcurementDocument::query()->reviewedByBacMember($user)->count(), 'tone' => 'green', 'href' => $this->routeUrl('bac-member.reviewed.index')],
            ['label' => 'Notifications', 'value' => (string) $user->systemNotifications()->whereNull('read_at')->count(), 'tone' => 'navy', 'href' => $this->routeUrl('notifications.index', ['status' => 'unread'])],
        ];
    }

    private function bacChairStats(): array
    {
        return [
            ['label' => 'Pending BAC Resolutions', 'value' => (string) BacResolution::where('status', BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR)->count(), 'tone' => 'gold', 'href' => $this->routeUrl('bac-chair.resolutions.index', ['status' => BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR])],
            ['label' => 'Confirmed Resolutions', 'value' => (string) BacResolution::where('status', BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR)->count(), 'tone' => 'green', 'href' => $this->routeUrl('bac-chair.resolutions.index', ['status' => BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR])],
            ['label' => 'Forwarded to HOPE', 'value' => (string) BacResolution::where('status', BacResolution::STATUS_FORWARDED_TO_HOPE)->count(), 'tone' => 'navy', 'href' => $this->routeUrl('bac-chair.resolutions.index', ['status' => BacResolution::STATUS_FORWARDED_TO_HOPE])],
            ['label' => 'Returned Resolutions', 'value' => (string) BacResolution::where('status', BacResolution::STATUS_RETURNED_BY_BAC_CHAIR)->count(), 'tone' => 'blue', 'href' => $this->routeUrl('bac-chair.resolutions.index', ['status' => BacResolution::STATUS_RETURNED_BY_BAC_CHAIR])],
        ];
    }

    private function approvingAuthorityStats(): array
    {
        $user = Auth::user();
        $pendingApps = AnnualProcurementPlan::query()
            ->whereIn('status', [AnnualProcurementPlan::STATUS_SUBMITTED, AnnualProcurementPlan::STATUS_CONSOLIDATED])
            ->count();
        $approvedApps = AnnualProcurementPlan::query()
            ->where('status', AnnualProcurementPlan::STATUS_APPROVED)
            ->where('approved_by_user_id', $user?->id)
            ->count();

        return [
            ['label' => 'Pending Approval', 'value' => (string) (ProcurementDocument::query()->assignedToApprovingAuthority($user)->count() + $pendingApps), 'tone' => 'gold', 'href' => $this->routeUrl('approving-authority.pending.index')],
            ['label' => 'Approved', 'value' => (string) (ProcurementDocument::query()->approvedByApprovingAuthority($user)->count() + $approvedApps), 'tone' => 'green', 'href' => $this->routeUrl('approving-authority.approved.index')],
            ['label' => 'Returned', 'value' => (string) $this->approvingAuthorityReturnedCount($user), 'tone' => 'blue', 'href' => $this->routeUrl('approving-authority.returned.index')],
            ['label' => 'Completed', 'value' => (string) ProcurementDocument::query()->approvedByApprovingAuthority($user)->whereIn('status', [ProcurementDocument::STATUS_APPROVED, ProcurementDocument::STATUS_PO_COMPLETED])->count(), 'tone' => 'navy', 'href' => $this->routeUrl('approving-authority.approved.index', ['status' => ProcurementDocument::STATUS_PO_COMPLETED])],
        ];
    }

    private function approvingAuthorityReturnedCount(User $user): int
    {
        return ProcurementDocument::query()
            ->whereNotIn('status', [
                ProcurementDocument::STATUS_PENDING_APPROVAL,
                ProcurementDocument::STATUS_UNDER_APPROVAL,
                ProcurementDocument::STATUS_APPROVED,
                ProcurementDocument::STATUS_APP_APPROVED,
                ProcurementDocument::STATUS_READY_FOR_PO,
                ProcurementDocument::STATUS_PO_APPROVED,
                ProcurementDocument::STATUS_PENDING_BAC_CHAIR_REVIEW,
                ProcurementDocument::STATUS_UNDER_BAC_CHAIR_REVIEW,
                ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW,
                ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW,
                ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW,
            ])
            ->where(function ($status) {
                $status->where('status', ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY)
                    ->orWhere('approval_status', DocumentApproval::STATUS_RETURNED)
                    ->orWhereIn('approval_decision', [
                        DocumentApproval::DECISION_RETURNED_TO_BAC_CHAIR,
                        DocumentApproval::DECISION_RETURNED_TO_BAC_SECRETARIAT,
                        DocumentApproval::DECISION_RETURNED_TO_ACCOUNTING,
                        DocumentApproval::DECISION_RETURNED_TO_REQUESTING_OFFICE,
                    ]);
            })
            ->where(function ($owner) use ($user) {
                $owner->where('approved_by_user_id', $user->id)
                    ->orWhereHas('documentApprovals', function ($approval) use ($user) {
                        $approval->where('approved_by_user_id', $user->id)
                            ->where('approval_status', DocumentApproval::STATUS_RETURNED);
                    })
                    ->orWhereHas('routingHistories', function ($history) use ($user) {
                        $history->where('action_by_user_id', $user->id)
                            ->where(function ($action) {
                                $action->where('action', 'like', '%Returned by Head of the Procuring Entity%')
                                    ->orWhere('action', 'like', '%Returned by Approving Authority%')
                                    ->orWhere('status_to', ProcurementDocument::STATUS_RETURNED_BY_APPROVING_AUTHORITY);
                            });
                    });
            })
            ->count();
    }

    private function prNumberingStats(): array
    {
        $user = Auth::user();

        $base = ProcurementDocument::query()
            ->whereIn('document_type', ['PR', 'Purchase Request']);

        return [
            ['label' => 'Pending PR Number Requests', 'value' => (string) (clone $base)->where('status', ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT)->where('pr_number_status', ProcurementDocument::PR_NUMBER_STATUS_PENDING_ASSIGNMENT)->count(), 'tone' => 'gold', 'href' => $this->routeUrl('pr-numbering.pending.index')],
            ['label' => 'Assigned Today', 'value' => (string) (clone $base)->where('pr_number_status', ProcurementDocument::PR_NUMBER_STATUS_ASSIGNED)->whereDate('pr_no_assigned_at', today())->count(), 'tone' => 'green', 'href' => $this->routeUrl('pr-numbering.assigned.index', ['date' => today()->toDateString()])],
            ['label' => 'Assigned by You', 'value' => (string) (clone $base)->where('pr_no_assigned_by_user_id', $user?->id)->count(), 'tone' => 'navy', 'href' => $this->routeUrl('pr-numbering.assigned.index')],
            ['label' => 'Returned for Correction', 'value' => (string) (clone $base)->where('status', ProcurementDocument::STATUS_RETURNED_BY_PR_NUMBERING_STAFF)->count(), 'tone' => 'blue', 'href' => $this->routeUrl('pr-numbering.pending.index', ['status' => ProcurementDocument::STATUS_RETURNED_BY_PR_NUMBERING_STAFF])],
        ];
    }

    private function signatureStats(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $service = app(SignatureRequestService::class);
        $base = $service->requestsForUser($user);

        return [
            ['label' => 'Pending Signatures', 'value' => (string) $service->getPendingRequestsForUser($user)->count(), 'tone' => 'gold', 'href' => $this->routeUrl('signature-requests.index')],
            ['label' => 'Signed Documents', 'value' => (string) (clone $base)->where('status', SignatureRequest::STATUS_SIGNED)->count(), 'tone' => 'green', 'href' => $this->routeUrl('signature-requests.index', ['status' => SignatureRequest::STATUS_SIGNED])],
        ];
    }

    private function signatureDashboard(?User $user): array
    {
        if (! $user) {
            return ['pending' => [], 'signed' => []];
        }

        $service = app(SignatureRequestService::class);
        $mapRequest = function (SignatureRequest $request): array {
            $status = (string) ($request->status ?? '');

            return [
                'tracking' => $request->tracking_number ?: $request->document_label ?: 'N/A',
                'document' => $request->document_label ?: 'Purchase Request',
                'requestedBy' => $request->requestedBy?->name ?? 'System',
                'office' => $request->requestedBy?->assignedOffice?->name ?? $request->requestedBy?->office ?? 'N/A',
                'task' => $request->signatory_label ?: 'Signature',
                'status' => $status,
                'statusLabel' => ucwords(str_replace('_', ' ', $status ?: 'pending')),
                'date' => ($request->signed_at ?? $request->created_at)?->format('M d, Y h:i A') ?? 'N/A',
                'href' => Route::has('signature-requests.show') ? route('signature-requests.show', $request) : null,
            ];
        };

        return [
            'pending' => $service->requestsForUser($user)
                ->with(['requestedBy.assignedOffice'])
                ->where('document_type', 'purchase_request')
                ->open()
                ->latest('created_at')
                ->limit(5)
                ->get()
                ->map($mapRequest)
                ->values()
                ->all(),
            'signed' => $service->requestsForUser($user)
                ->with(['requestedBy.assignedOffice'])
                ->where('document_type', 'purchase_request')
                ->where('status', SignatureRequest::STATUS_SIGNED)
                ->latest('signed_at')
                ->limit(5)
                ->get()
                ->map($mapRequest)
                ->values()
                ->all(),
        ];
    }

    private function isBacsecPrSignatureOnly(?User $user): bool
    {
        return $user?->user_id === 'BACSEC-003'
            && ($user->hasRole('bac_secretariat') || $user->hasRole(User::ROLE_BAC_SECRETARIAT));
    }

    private function roleCanSign(string $role): bool
    {
        return in_array($role, [
            User::ROLE_HEAD_OFFICE,
            User::ROLE_BUDGET,
            User::ROLE_ACCOUNTING,
            User::ROLE_BAC_SECRETARIAT,
            User::ROLE_BAC_MEMBER,
            User::ROLE_BAC_CHAIR,
            User::ROLE_APPROVING_AUTHORITY,
        ], true);
    }

    private function routeUrl(string $name, array $parameters = []): ?string
    {
        return Route::has($name) ? route($name, $parameters) : null;
    }
}
