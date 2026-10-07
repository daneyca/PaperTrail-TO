@extends('layouts.dashboard')

@section('title', $title . ' | PaperTrail')

@push('vendor-styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
@endpush

@section('content')
    @php
        $user = auth()->user();
        $roleSlug = $user?->roleSlug() ?? '';
        $isAdminDashboard = $roleSlug === 'admin';
        $isSignatureOnlyDashboard = (bool) ($signatureOnlyDashboard ?? false);
        $isBacsec002ResolutionWorkspace = (bool) ($bacsec002ResolutionWorkspace ?? false);
        $officeName = $user?->assignedOffice?->name ?? $user?->office ?? 'your office';
        $displayName = trim((string) ($user?->name ?? ''));
        $firstName = $displayName !== '' ? explode(' ', $displayName)[0] : 'there';
        $hour = now('Asia/Manila')->hour;
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
        $primaryActionUrl = isset($primaryActionRoute) && $primaryActionRoute && \Illuminate\Support\Facades\Route::has($primaryActionRoute)
            ? route($primaryActionRoute)
            : null;

        $statsCollection = collect($stats ?? []);
        $statNumber = function ($value): int {
            $raw = is_array($value) ? ($value['value'] ?? 0) : $value;
            return (int) preg_replace('/[^0-9-]/', '', (string) $raw);
        };
        $findStat = function (array $needles) use ($statsCollection) {
            return $statsCollection->first(function (array $stat) use ($needles): bool {
                $label = strtolower((string) ($stat['label'] ?? ''));

                foreach ($needles as $needle) {
                    if (str_contains($label, strtolower($needle))) {
                        return true;
                    }
                }

                return false;
            });
        };

        $totalDocuments = $isAdminDashboard ? 0 : ($headOfficeDashboard['totalDocuments'] ?? $statNumber($findStat(['total documents', 'documents']) ?? 0));
        if ($totalDocuments === 0 && $statsCollection->isNotEmpty() && $roleSlug !== 'admin') {
            $totalDocuments = $statsCollection->sum(fn (array $stat): int => $statNumber($stat));
        }

        $pendingActions = $isAdminDashboard ? 0 : $statNumber($findStat(['pending action', 'pending', 'incoming', 'for review', 'for approval']) ?? 0);
        $returnedDocuments = $isAdminDashboard ? 0 : $statNumber($findStat(['returned']) ?? 0);
        $completedDocuments = $isAdminDashboard ? 0 : $statNumber($findStat(['completed', 'approved', 'accepted', 'signed']) ?? 0);

        $statusChart = [
            'labels' => ['Completed', 'Pending', 'Returned', 'Draft'],
            'series' => [
                max($completedDocuments, 0),
                max($pendingActions, 0),
                max($returnedDocuments, 0),
                $statNumber($findStat(['draft']) ?? 0),
            ],
        ];

        $workflowChart = [
            'categories' => ['Created', 'Submitted', 'Approved', 'Returned'],
            'series' => [[
                'name' => 'Documents',
                'data' => [
                    max($totalDocuments, 0),
                    $statNumber($findStat(['submitted']) ?? 0),
                    max($completedDocuments, 0),
                    max($returnedDocuments, 0),
                ],
            ]],
        ];

        $quickActionsForRole = [
            'head-office' => [
                ['label' => 'Create Purchase Request', 'route' => 'head-office.pr.create', 'description' => 'Prepare a new PR draft.', 'icon' => 'pr', 'tone' => 'blue'],
                ['label' => 'RFQ', 'route' => 'head-office.rfqs.create', 'description' => 'Prepare requests for quotation.', 'icon' => 'document', 'tone' => 'green'],
                ['label' => 'Abstract of Quotations', 'route' => 'head-office.abstracts.create', 'description' => 'Compare supplier quotations.', 'icon' => 'document', 'tone' => 'purple'],
                ['label' => 'Purchase Order', 'route' => 'head-office.purchase-orders.create', 'description' => 'Prepare purchase orders.', 'icon' => 'po', 'tone' => 'orange'],
                ['label' => 'Inspection Confirmation', 'route' => 'head-office.inspection.menu', 'description' => 'Record inspection and acceptance.', 'icon' => 'check', 'tone' => 'green'],
                ['label' => 'Submit PPMP', 'route' => 'head-office.ppmp.create', 'description' => 'Create or submit a PPMP.', 'icon' => 'ppmp', 'tone' => 'purple'],
                ['label' => 'View Pending Signatures', 'route' => 'signature-requests.index', 'description' => 'Review signature requests.', 'icon' => 'signature', 'tone' => 'navy'],
                ['label' => 'AI Delay Risk Analysis', 'route' => 'head-office.documents.index', 'description' => 'Open documents for AI checks.', 'icon' => 'clock', 'tone' => 'orange'],
            ],
            'bac-secretariat' => [
                ['label' => 'Incoming Documents', 'route' => 'bac-secretariat.incoming.menu', 'description' => 'Review newly routed records.', 'icon' => 'document', 'tone' => 'blue'],
                ['label' => 'APP Consolidation', 'route' => 'bac-secretariat.app.index', 'description' => 'Manage APP records.', 'icon' => 'app', 'tone' => 'green'],
                ['label' => 'Create BAC Resolution', 'route' => 'bac-secretariat.resolutions.create', 'description' => 'Prepare BAC resolution.', 'icon' => 'document', 'tone' => 'gold'],
                ['label' => 'AI Delay Risk Analysis', 'route' => 'bac-secretariat.svp-monitoring.index', 'description' => 'Monitor delayed chains.', 'icon' => 'clock', 'tone' => 'orange'],
            ],
            'budget' => [
                ['label' => 'Review Budget Requests', 'route' => 'budget.pending-review.index', 'description' => 'Check fund availability.', 'icon' => 'check', 'tone' => 'green'],
                ['label' => 'View Budget Reports', 'route' => 'budget.reports.index', 'description' => 'Open budget summaries.', 'icon' => 'document', 'tone' => 'blue'],
                ['label' => 'View Pending Signatures', 'route' => 'signature-requests.index', 'description' => 'Review signature requests.', 'icon' => 'signature', 'tone' => 'navy'],
            ],
            'accounting' => [
                ['label' => 'Review Accounting Queue', 'route' => 'accounting.pending-review.index', 'description' => 'Verify accounting details.', 'icon' => 'check', 'tone' => 'green'],
                ['label' => 'Accounting Reports', 'route' => 'accounting.reports.index', 'description' => 'Open report summaries.', 'icon' => 'document', 'tone' => 'blue'],
                ['label' => 'View Pending Signatures', 'route' => 'signature-requests.index', 'description' => 'Review signature requests.', 'icon' => 'signature', 'tone' => 'navy'],
            ],
            'bac-member' => [
                ['label' => 'Documents for Review', 'route' => 'bac-member.reviews.menu', 'description' => 'Open assigned BAC items.', 'icon' => 'check', 'tone' => 'purple'],
                ['label' => 'View Pending Signatures', 'route' => 'signature-requests.index', 'description' => 'Review signature requests.', 'icon' => 'signature', 'tone' => 'navy'],
            ],
            'bac-chair' => [
                ['label' => 'BAC Approvals', 'route' => 'bac-chair.approvals.index', 'description' => 'Review approvals queue.', 'icon' => 'check', 'tone' => 'purple'],
                ['label' => 'Documents for Confirmation', 'route' => 'bac-chair.confirmation.index', 'description' => 'Confirm BAC documents.', 'icon' => 'signature', 'tone' => 'gold'],
                ['label' => 'Reports', 'route' => 'bac-chair.reports.index', 'description' => 'Open review reports.', 'icon' => 'document', 'tone' => 'blue'],
            ],
            'approving-authority' => [
                ['label' => 'Pending Approvals', 'route' => 'approving-authority.pending.index', 'description' => 'Approve or return records.', 'icon' => 'check', 'tone' => 'green'],
                ['label' => 'Approved Documents', 'route' => 'approving-authority.approved.index', 'description' => 'Review approved records.', 'icon' => 'document', 'tone' => 'blue'],
            ],
            'pr-numbering' => [
                ['label' => 'Assign PR Numbers', 'route' => 'pr-numbering.pending.index', 'description' => 'Process PR number requests.', 'icon' => 'pr', 'tone' => $pendingActions > 0 ? 'gold' : 'blue', 'count' => $pendingActions > 0 ? $pendingActions : null],
                ['label' => 'Numbered PRs', 'route' => 'pr-numbering.assigned.index', 'description' => 'View assigned PR records.', 'icon' => 'check', 'tone' => 'green'],
            ],
            'admin' => [
                ['label' => 'Manage Users', 'route' => 'admin.users.index', 'description' => 'Maintain user accounts and access status.', 'icon' => 'users', 'tone' => 'blue'],
                ['label' => 'Roles & Permissions', 'route' => 'admin.roles.index', 'description' => 'Review role assignments and permission groups.', 'icon' => 'shield', 'tone' => 'green'],
                ['label' => 'Offices', 'route' => 'admin.offices.index', 'description' => 'Maintain registered LGU offices.', 'icon' => 'document', 'tone' => 'gold'],
                ['label' => 'Audit Trail', 'route' => 'admin.audit.index', 'description' => 'Review system activity.', 'icon' => 'clock', 'tone' => 'orange'],
                ['label' => 'System Settings', 'route' => 'admin.settings.index', 'description' => 'Configure PaperTrail system options.', 'icon' => 'key', 'tone' => 'navy'],
                ['label' => 'AI Summary Dashboard', 'route' => 'admin.ai-summary.index', 'description' => 'Review AI usage and generated summaries.', 'icon' => 'spark', 'tone' => 'purple'],
            ],
        ];

        if ($isSignatureOnlyDashboard) {
            $quickActionsForRole[$roleSlug] = [];
        }

        if ($isBacsec002ResolutionWorkspace) {
            $quickActionsForRole[$roleSlug] = [
                ['label' => 'Submitted PRs', 'route' => 'bac-secretariat.pr.index', 'params' => ['status' => 'submitted'], 'description' => 'Open submitted PRs assigned for BAC Resolution.', 'icon' => 'pr', 'tone' => 'gold'],
                ['label' => 'Create BAC Resolution', 'route' => 'bac-secretariat.resolutions.create', 'description' => 'Prepare a BAC Resolution from an assigned PR.', 'icon' => 'document', 'tone' => 'blue'],
                ['label' => 'BAC Resolution Records', 'route' => 'bac-secretariat.resolutions.index', 'description' => 'Track drafted and submitted resolutions.', 'icon' => 'document', 'tone' => 'green'],
            ];
        }

        $quickActions = collect($quickActionsForRole[$roleSlug] ?? [])
            ->filter(fn (array $action): bool => \Illuminate\Support\Facades\Route::has($action['route']))
            ->map(fn (array $action): array => $action + ['href' => route($action['route'], $action['params'] ?? [])])
            ->values()
            ->all();

        if ($primaryActionUrl && ! collect($quickActions)->contains('href', $primaryActionUrl)) {
            array_unshift($quickActions, [
                'label' => $primaryAction,
                'description' => 'Primary workspace action.',
                'href' => $primaryActionUrl,
                'icon' => 'document',
                'tone' => 'gold',
            ]);
        }

        $documentType = function (?string $type): string {
            return match (strtolower((string) $type)) {
                'purchase request' => 'PR',
                'purchase order' => 'PO',
                'annual procurement plan' => 'APP',
                default => strtoupper((string) ($type ?: 'DOC')),
            };
        };

        $statusLabel = function (?string $status): string {
            $ppmpStatusLabels = [
                \App\Models\ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES => 'Pending Signature',
                \App\Models\ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED => 'Signed',
                \App\Models\ProcurementDocument::STATUS_PENDING_PPMP_REVIEW => 'Submitted to BAC',
                \App\Models\ProcurementDocument::STATUS_UNDER_PPMP_REVIEW => 'Under APP Consolidation',
                \App\Models\ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION => 'Approved',
            ];

            if (isset($ppmpStatusLabels[$status])) {
                return $ppmpStatusLabels[$status];
            }

            if ($status === \App\Models\ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION) {
                return 'Prepare BAC Resolution';
            }

            return \Illuminate\Support\Str::of($status ?: 'Unknown')
                ->replace('_', ' ')
                ->title()
                ->replace('Ppmp', 'PPMP')
                ->toString();
        };
        $statusTone = function (?string $status): string {
            $status = strtolower((string) $status);

            if ($status === \App\Models\ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES) {
                return 'pending';
            }

            if ($status === \App\Models\ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED) {
                return 'success';
            }

            return str_contains($status, 'return') ? 'danger'
                : (str_contains($status, 'approved') || str_contains($status, 'accepted') || str_contains($status, 'completed') ? 'success'
                : (str_contains($status, 'review') || str_contains($status, 'validation') || str_contains($status, 'routed') ? 'progress'
                : (str_contains($status, 'draft') ? 'muted' : 'pending')));
        };
        $workflowProgress = function (?string $status, ?string $type = null): int {
            $status = strtolower((string) $status);
            $type = strtoupper((string) $type);

            if ($type === 'PPMP') {
                return match ($status) {
                    \App\Models\ProcurementDocument::STATUS_PPMP_DRAFT,
                    \App\Models\ProcurementDocument::STATUS_PPMP_SIGNATURE_RETURNED => 1,
                    \App\Models\ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES => 2,
                    \App\Models\ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED => 3,
                    \App\Models\ProcurementDocument::STATUS_PENDING_PPMP_REVIEW => 4,
                    \App\Models\ProcurementDocument::STATUS_UNDER_PPMP_REVIEW => 5,
                    \App\Models\ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION => 6,
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
        };
        $documentHistoryForDashboard = function ($document) use ($statusLabel, $officeName): array {
            $history = ($document->routingHistories ?? collect())
                ->sortByDesc(fn ($item) => $item->action_at ?? $item->created_at)
                ->take(4)
                ->map(fn ($item): array => [
                    'label' => $item->action ?: $statusLabel($item->status_to),
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
                'location' => $document->submittingOffice?->name ?? $officeName,
                'actor' => $document->preparedBy?->name ?? $document->submittedBy?->name ?? 'System',
                'comments' => null,
            ]];
        };

        $viewRouteForDocument = function ($document, string $type) use ($roleSlug) {
            $routeName = match ($roleSlug) {
                'head-office' => 'head-office.documents.show',
                'budget' => 'budget.pending-review.show',
                'accounting' => 'accounting.pending-review.show',
                'bac-member' => 'bac-member.review.show',
                'bac-chair' => 'bac-chair.approvals.show',
                'approving-authority' => 'approving-authority.pending.show',
                'pr-numbering' => 'pr-numbering.pending.show',
                'bac-secretariat' => $type === 'PPMP'
                    ? 'bac-secretariat.ppmp.show'
                    : ($type === 'PR' ? 'bac-secretariat.pr.show' : 'bac-secretariat.routing.show'),
                default => null,
            };

            return $routeName && \Illuminate\Support\Facades\Route::has($routeName) ? route($routeName, $document) : null;
        };

        $recentDocuments = $isAdminDashboard || $isSignatureOnlyDashboard ? [] : ($headOfficeDashboard['documents'] ?? []);

        if (! $isAdminDashboard && ! $isSignatureOnlyDashboard && empty($recentDocuments) && $user) {
            try {
                $documentQuery = \App\Models\ProcurementDocument::query()
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
                    ->limit(12);

                match ($roleSlug) {
                    'head-office' => $documentQuery->where(function ($query) use ($user) {
                        if ($user->office_id) {
                            $query->where('submitting_office_id', $user->office_id);
                        }

                        $query->orWhere('submitted_by_user_id', $user->id)
                            ->orWhere('prepared_by_user_id', $user->id);
                    }),
                    'budget' => $documentQuery->visibleToBudgetOfficer($user),
                    'accounting' => $documentQuery->visibleToAccountingOfficer($user),
                    'bac-secretariat' => $documentQuery
                        ->visibleToBacSecretariat($user)
                        ->when(
                            method_exists($user, 'hasBacsec002PurchaseRequestCapability') && $user->hasBacsec002PurchaseRequestCapability(),
                            fn ($query) => $query->where(function ($visible) {
                                $visible->whereNull('document_type')
                                    ->orWhereNotIn('document_type', ['PPMP', 'Project Procurement Management Plan']);
                            })
                        ),
                    'bac-member' => $documentQuery->assignedToBacMember($user),
                    'bac-chair' => $documentQuery->assignedToBacChair($user),
                    'approving-authority' => $documentQuery->assignedToApprovingAuthority($user),
                    'pr-numbering' => $documentQuery->whereIn('status', [
                        \App\Models\ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT,
                        \App\Models\ProcurementDocument::STATUS_PR_NUMBER_ASSIGNED,
                        \App\Models\ProcurementDocument::STATUS_RETURNED_BY_PR_NUMBERING_STAFF,
                    ]),
                    default => $documentQuery->whereRaw('1 = 0'),
                };

                $recentDocuments = $documentQuery->get()->map(function ($document) use ($documentType, $statusLabel, $statusTone, $workflowProgress, $viewRouteForDocument, $documentHistoryForDashboard): array {
                    $type = $documentType($document->document_type);

                    return [
                        'number' => $document->tracking_number ?: $document->ppmp_no ?: $document->pr_no ?: ('Draft #' . $document->id),
                        'type' => $type,
                        'title' => $document->title ?: $document->purpose ?: $document->description ?: 'Untitled procurement document',
                        'office' => $document->submittingOffice?->name ?? $document->preparedBy?->office ?? 'N/A',
                        'status' => $statusLabel($document->status),
                        'statusKey' => $document->status ?: 'unknown',
                        'statusTone' => $statusTone($document->status),
                        'stage' => $document->stage ?: $statusLabel($document->status),
                        'currentOffice' => $document->assignedTo
                            ? trim(($document->assignedTo->user_id ? $document->assignedTo->user_id.' - ' : '').$document->assignedTo->name)
                            : ($document->currentOffice?->name ?? 'Not routed'),
                        'createdAt' => $document->created_at?->format('M d, Y h:i A') ?? 'N/A',
                        'submittedAt' => $document->submitted_at?->format('M d, Y h:i A') ?? 'Not submitted',
                        'updatedAt' => $document->updated_at?->format('M d, Y h:i A') ?? 'N/A',
                        'progress' => $workflowProgress($document->status, $type),
                        'history' => $documentHistoryForDashboard($document),
                        'viewUrl' => $viewRouteForDocument($document, $type),
                    ];
                })->values()->all();
            } catch (\Throwable) {
                $recentDocuments = [];
            }
        }

        $dashboardDocuments = collect($recentDocuments);
        $bucketDocuments = function (array $needles, array $excludedStatusKeys = []) use ($dashboardDocuments): array {
            return $dashboardDocuments
                ->filter(function (array $document) use ($needles, $excludedStatusKeys): bool {
                    $statusKey = strtolower((string) ($document['statusKey'] ?? ''));

                    if (in_array($statusKey, $excludedStatusKeys, true)) {
                        return false;
                    }

                    $status = strtolower((string) ($document['status'] ?? ''));
                    $tone = strtolower((string) ($document['statusTone'] ?? ''));

                    foreach ($needles as $needle) {
                        $needle = strtolower($needle);

                        if (str_contains($status, $needle) || str_contains($tone, $needle)) {
                            return true;
                        }
                    }

                    return false;
                })
                ->take(4)
                ->values()
                ->all();
        };

        $dashboardDocumentBuckets = [
            [
                'title' => 'Latest Created',
                'tone' => 'blue',
                'icon' => 'document',
                'documents' => $bucketDocuments(['draft', 'created']),
            ],
            [
                'title' => 'Latest Submitted',
                'tone' => 'purple',
                'icon' => 'upload',
                'documents' => $bucketDocuments(
                    ['submitted', 'pending', 'review', 'validation', 'routed', 'assigned', 'received', 'progress'],
                    [
                        \App\Models\ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES,
                        \App\Models\ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED,
                    ],
                ),
            ],
            [
                'title' => 'Latest Approved',
                'tone' => 'green',
                'icon' => 'check',
                'documents' => $bucketDocuments(['approved', 'accepted', 'completed', 'signed', 'confirmed', 'success']),
            ],
            [
                'title' => 'Latest Returned',
                'tone' => 'orange',
                'icon' => 'return',
                'documents' => $bucketDocuments(['returned', 'danger']),
            ],
        ];
        $latestTrackingDocument = $dashboardDocuments->first();
        $latestTrackingHistory = $latestTrackingDocument['history'] ?? [];
        $latestTrackingStatus = strtolower((string) ($latestTrackingDocument['statusKey'] ?? $latestTrackingDocument['status'] ?? ''));
        $latestTrackingTone = strtolower((string) ($latestTrackingDocument['statusTone'] ?? 'pending'));
        $latestTrackingType = strtoupper((string) ($latestTrackingDocument['type'] ?? 'DOC'));
        $latestTrackingProgress = (int) ($latestTrackingDocument['progress'] ?? 1);
        $latestTrackingProgress = max(1, $latestTrackingProgress);
        $latestTrackingTemplates = match ($latestTrackingType) {
            'PPMP' => [
                ['label' => 'Created', 'icon' => 'document'],
                ['label' => 'Pending Signature', 'icon' => 'signature'],
                ['label' => 'Signed', 'icon' => 'signature'],
                ['label' => 'Submitted to BAC', 'icon' => 'upload'],
                ['label' => 'Under APP Consolidation', 'icon' => 'app'],
                ['label' => 'Approved', 'icon' => 'check'],
            ],
            'PR' => [
                ['label' => 'Created', 'icon' => 'document'],
                ['label' => 'PR Number', 'icon' => 'pr'],
                ['label' => 'BAC Review', 'icon' => 'clock'],
                ['label' => 'Approvals', 'icon' => 'signature'],
                ['label' => 'Completed', 'icon' => 'check'],
            ],
            'PO' => [
                ['label' => 'Prepared', 'icon' => 'po'],
                ['label' => 'Issued', 'icon' => 'upload'],
                ['label' => 'Delivery', 'icon' => 'truck'],
                ['label' => 'Inspection', 'icon' => 'check'],
                ['label' => 'Completed', 'icon' => 'check'],
            ],
            default => [
                ['label' => 'Created', 'icon' => 'document'],
                ['label' => 'Submitted', 'icon' => 'upload'],
                ['label' => 'Review', 'icon' => 'clock'],
                ['label' => 'Approval', 'icon' => 'signature'],
                ['label' => 'Completed', 'icon' => 'check'],
            ],
        };

        if ($latestTrackingTone === 'danger' || str_contains($latestTrackingStatus, 'return')) {
            $latestTrackingTemplates = [
                ['label' => 'Created', 'icon' => 'document'],
                ['label' => 'Submitted', 'icon' => 'upload'],
                ['label' => 'Returned', 'icon' => 'return'],
                ['label' => 'Correction', 'icon' => 'edit'],
                ['label' => 'Resubmit', 'icon' => 'upload'],
            ];
            $latestTrackingProgress = 3;
        }

        $latestTrackingProgress = max(1, min(count($latestTrackingTemplates), $latestTrackingProgress));

        $latestTrackingSteps = collect($latestTrackingTemplates)
            ->map(function (array $step, int $index) use ($latestTrackingProgress): array {
                $position = $index + 1;

                return $step + [
                    'state' => $position < $latestTrackingProgress ? 'completed' : ($position === $latestTrackingProgress ? 'current' : 'upcoming'),
                ];
            })
            ->all();

        $aiMetrics = [
            'high_risk_documents' => 0,
            'most_missing_requirement' => 'None recorded',
            'most_delayed_stage' => 'No delay data',
            'average_completeness_score' => 'Pending',
        ];

        if ($isAdminDashboard) {
            try {
                $completedChecks = \App\Models\AiDocumentCheck::query()
                    ->where('status', \App\Models\AiDocumentCheck::STATUS_COMPLETED);
                $averageScore = (clone $completedChecks)->avg('completeness_score');
                $latestMissing = (clone $completedChecks)
                    ->whereNotNull('missing_requirements')
                    ->latest()
                    ->limit(20)
                    ->pluck('missing_requirements')
                    ->flatten()
                    ->filter()
                    ->countBy()
                    ->sortDesc()
                    ->keys()
                    ->first();

                $highRisk = \App\Models\AiDelayRiskCheck::query()
                    ->where('status', \App\Models\AiDelayRiskCheck::STATUS_COMPLETED)
                    ->whereIn('risk_level', [\App\Models\AiDelayRiskCheck::RISK_HIGH, \App\Models\AiDelayRiskCheck::RISK_CRITICAL])
                    ->count();
                $mostDelayed = \App\Models\AiDelayRiskCheck::query()
                    ->where('status', \App\Models\AiDelayRiskCheck::STATUS_COMPLETED)
                    ->whereNotNull('current_holder')
                    ->select('current_holder')
                    ->selectRaw('COUNT(*) as aggregate')
                    ->groupBy('current_holder')
                    ->orderByDesc('aggregate')
                    ->value('current_holder');

                $aiMetrics = [
                    'high_risk_documents' => $highRisk,
                    'most_missing_requirement' => $latestMissing ?: 'None recorded',
                    'most_delayed_stage' => $mostDelayed ?: 'No delay data',
                    'average_completeness_score' => $averageScore !== null ? round((float) $averageScore) . '%' : 'Pending',
                ];
            } catch (\Throwable) {
                $aiMetrics = [
                    'high_risk_documents' => 0,
                    'most_missing_requirement' => 'No AI data yet',
                    'most_delayed_stage' => 'No delay data',
                    'average_completeness_score' => 'Pending',
                ];
            }
        }

        $summaryUrl = \Illuminate\Support\Facades\Route::has('admin.ai-summary.index') && $roleSlug === 'admin'
            ? route('admin.ai-summary.index')
            : null;
        $announcementsUrl = \Illuminate\Support\Facades\Route::has('notifications.index') ? route('notifications.index') : null;
        $documentsUrl = $isSignatureOnlyDashboard ? null : match ($roleSlug) {
            'head-office' => \Illuminate\Support\Facades\Route::has('head-office.documents.index') ? route('head-office.documents.index') : null,
            'bac-secretariat' => \Illuminate\Support\Facades\Route::has('bac-secretariat.routing.menu') ? route('bac-secretariat.routing.menu') : null,
            'budget' => \Illuminate\Support\Facades\Route::has('budget.review.menu') ? route('budget.review.menu') : null,
            'accounting' => \Illuminate\Support\Facades\Route::has('accounting.review.menu') ? route('accounting.review.menu') : null,
            'bac-member' => \Illuminate\Support\Facades\Route::has('bac-member.reviews.menu') ? route('bac-member.reviews.menu') : null,
            'bac-chair' => \Illuminate\Support\Facades\Route::has('bac-chair.reviews.menu') ? route('bac-chair.reviews.menu') : null,
            'approving-authority' => \Illuminate\Support\Facades\Route::has('approving-authority.approvals.menu') ? route('approving-authority.approvals.menu') : null,
            'pr-numbering' => \Illuminate\Support\Facades\Route::has('pr-numbering.menu') ? route('pr-numbering.menu') : null,
            'admin' => null,
            default => null,
        };

        if (! $isAdminDashboard
            && ! $isSignatureOnlyDashboard
            && \Illuminate\Support\Facades\Route::has('ai-document-verification.index')
            && ! collect($quickActions)->contains('href', route('ai-document-verification.index'))) {
            $quickActions[] = [
                'label' => 'Upload Document',
                'description' => 'Open OCR-ready uploads.',
                'href' => route('ai-document-verification.index'),
                'icon' => 'upload',
                'tone' => 'blue',
            ];
        }

        $adminKpis = collect($adminStatistics['kpis'] ?? $stats ?? [])->values()->all();
        $adminMonitoring = collect($adminStatistics['monitoring'] ?? [])->values()->all();
        $adminAiCenter = collect($adminStatistics['aiCenter'] ?? [])->values()->all();
    @endphp

    <div
        class="enterprise-dashboard hope-dashboard {{ $isAdminDashboard ? 'hope-dashboard-admin' : 'hope-dashboard-role' }} container-fluid px-0"
        data-workflow-chart='@json($workflowChart)'
        data-status-chart='@json($statusChart)'
    >
        <section class="enterprise-hero hope-hero pt-workspace-welcome">
            <div class="enterprise-hero__copy">
                <p class="eyebrow">{{ $isAdminDashboard ? 'System Administration' : ($isSignatureOnlyDashboard ? 'E-Signature Workspace' : 'Your procurement workspace') }}</p>
                <h1>{{ $greeting }}, {{ $firstName }}!</h1>
            </div>

            <div class="enterprise-hero__actions">
                @if ($isAdminDashboard && $primaryActionUrl)
                    <a class="enterprise-primary-action" href="{{ $primaryActionUrl }}">
                        <x-papertrail.icon name="users" />
                        Manage Users
                    </a>
                @endif

                @if ($documentsUrl)
                    <a class="enterprise-ghost-action" href="{{ $documentsUrl }}">
                        <x-papertrail.icon name="view" />
                        View Documents
                    </a>
                @endif

                @if ($announcementsUrl)
                    <a class="enterprise-announcement-action" href="{{ $announcementsUrl }}">
                        <x-papertrail.icon name="bell" />
                        Alerts
                    </a>
                @endif
            </div>
        </section>

        <section class="enterprise-kpi-grid hope-kpi-row row" aria-label="{{ $isAdminDashboard ? 'Administration summary' : 'Procurement summary' }}">
            @if ($isAdminDashboard)
                @foreach ($adminKpis as $kpi)
                    <div class="col-12 col-sm-6 col-xl">
                        <x-dashboard.summary-card
                            :label="$kpi['label'] ?? 'System Metric'"
                            :value="$kpi['value'] ?? '0'"
                            :description="$kpi['description'] ?? 'Administration metric'"
                            :tone="$kpi['tone'] ?? 'blue'"
                            :icon="$kpi['icon'] ?? 'shield'"
                            :href="$kpi['href'] ?? null"
                        />
                    </div>
                @endforeach
            @elseif ($isSignatureOnlyDashboard)
                @forelse ($statsCollection as $stat)
                    <div class="col-12 col-sm-6 col-lg-3">
                        <x-dashboard.summary-card
                            :label="$stat['label'] ?? 'Signature Requests'"
                            :value="$stat['value'] ?? '0'"
                            description="Electronic signature workload"
                            :tone="$stat['tone'] ?? 'navy'"
                            :icon="str_contains(strtolower((string) ($stat['label'] ?? '')), 'signed') ? 'check' : 'signature'"
                            :href="$stat['href'] ?? null"
                        />
                    </div>
                @empty
                    <div class="col-12 col-sm-6 col-lg-3">
                        <x-dashboard.summary-card
                            label="Pending Signatures"
                            value="0"
                            description="Electronic signature workload"
                            tone="gold"
                            icon="signature"
                        />
                    </div>
                @endforelse
            @else
                <div class="col-12 col-sm-6 col-lg-3">
                    <x-dashboard.summary-card
                        label="Total Documents"
                        :value="$totalDocuments"
                        description="Visible procurement records"
                        tone="blue"
                        icon="document"
                    />
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <x-dashboard.summary-card
                        label="Pending Actions"
                        :value="$pendingActions"
                        description="Needs review or routing"
                        tone="purple"
                        icon="clock"
                    />
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <x-dashboard.summary-card
                        label="Returned"
                        :value="$returnedDocuments"
                        description="Requires correction"
                        tone="orange"
                        icon="return"
                    />
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <x-dashboard.summary-card
                        label="Completed"
                        :value="$completedDocuments"
                        description="Approved or accepted"
                        tone="green"
                        icon="check"
                    />
                </div>
            @endif
        </section>

        @if (! $isAdminDashboard && ! $isSignatureOnlyDashboard)
            <section class="col-12">
                <div class="procurement-analytics-grid procurement-analytics-grid--with-signals">
                    <x-dashboard.workflow-chart
                        class="hope-large-chart-card"
                        title="Procurement Workflow Overview"
                        subtitle="Created, submitted, approved, and returned records"
                        chart-id="workflowOverviewChart"
                    />

                    <x-dashboard.chart-card
                        class="hope-status-card"
                        title="Document Status Distribution"
                        subtitle="Current document status mix"
                        chart-id="statusDistributionChart"
                    />

                    <x-dashboard.ai-insight-card :metrics="$aiMetrics" :summary-url="$summaryUrl" />
                </div>
            </section>

            <x-dashboard.latest-document-tracking
                class="dashboard-tracking-card--summary-position"
                :document="$latestTrackingDocument"
                :steps="$latestTrackingSteps"
                :history="$latestTrackingHistory"
            />
        @endif

        @if ($isAdminDashboard)
            <div class="hope-dashboard-content admin-dashboard-content row align-items-start g-3">
                <main class="col-12 col-xl-8">
                    <section class="hope-main-stack">
                        <article class="admin-dashboard-panel">
                            <div class="procurement-card-heading">
                                <div>
                                    <p class="eyebrow">Administration</p>
                                    <h2>Administration Modules</h2>
                                    <span>Manage accounts, role access, offices, and PaperTrail configuration.</span>
                                </div>
                            </div>

                            <div class="procurement-action-list admin-action-list">
                                @forelse ($quickActions as $action)
                                    <a class="procurement-action-item procurement-action-item--{{ $action['tone'] ?? 'blue' }}" href="{{ $action['href'] }}">
                                        <span aria-hidden="true">
                                            <x-papertrail.icon :name="$action['icon'] ?? 'shield'" />
                                        </span>
                                        <strong>{{ $action['label'] }}</strong>
                                        @if (! empty($action['description']))
                                            <small>{{ $action['description'] }}</small>
                                        @endif
                                    </a>
                                @empty
                                    <div class="procurement-action-empty">
                                        <strong>No administration actions available</strong>
                                        <span>Your available actions depend on your role and permissions.</span>
                                    </div>
                                @endforelse
                            </div>
                        </article>

                        <article class="admin-dashboard-panel">
                            <div class="procurement-card-heading">
                                <div>
                                    <p class="eyebrow">Monitoring</p>
                                    <h2>System Activity</h2>
                                    <span>Audit trail, user activity, and system log visibility for administrators.</span>
                                </div>
                            </div>

                            <div class="admin-dashboard-metric-grid">
                                @forelse ($adminMonitoring as $metric)
                                    @php
                                        $metricValue = $metric['value'] ?? '0';
                                        $metricDisplay = is_numeric($metricValue) ? number_format((int) $metricValue) : $metricValue;
                                    @endphp

                                    @if (! empty($metric['href']))
                                        <a href="{{ $metric['href'] }}" class="admin-dashboard-metric admin-dashboard-metric--{{ $metric['tone'] ?? 'blue' }}">
                                            <strong>{{ $metricDisplay }}</strong>
                                            <span>{{ $metric['label'] ?? 'System Metric' }}</span>
                                            <small>{{ $metric['description'] ?? 'System monitoring metric' }}</small>
                                        </a>
                                    @else
                                        <div class="admin-dashboard-metric admin-dashboard-metric--{{ $metric['tone'] ?? 'blue' }}">
                                            <strong>{{ $metricDisplay }}</strong>
                                            <span>{{ $metric['label'] ?? 'System Metric' }}</span>
                                            <small>{{ $metric['description'] ?? 'System monitoring metric' }}</small>
                                        </div>
                                    @endif
                                @empty
                                    <div class="procurement-action-empty">
                                        <strong>No monitoring data yet</strong>
                                        <span>System activity will appear after administrative actions are recorded.</span>
                                    </div>
                                @endforelse
                            </div>
                        </article>
                    </section>
                </main>

                <aside class="col-12 col-xl-4">
                    <div class="hope-side-stack">
                        <article class="admin-dashboard-panel">
                            <div class="procurement-card-heading">
                                <div>
                                    <p class="eyebrow">AI Center</p>
                                    <h2>AI Operations</h2>
                                    <span>Processing, usage, and configuration status.</span>
                                </div>
                            </div>

                            <div class="admin-dashboard-metric-grid admin-dashboard-metric-grid--stack">
                                @forelse ($adminAiCenter as $metric)
                                    @php
                                        $metricValue = $metric['value'] ?? '0';
                                        $metricDisplay = is_numeric($metricValue) ? number_format((int) $metricValue) : $metricValue;
                                    @endphp

                                    @if (! empty($metric['href']))
                                        <a href="{{ $metric['href'] }}" class="admin-dashboard-metric admin-dashboard-metric--{{ $metric['tone'] ?? 'blue' }}">
                                            <strong>{{ $metricDisplay }}</strong>
                                            <span>{{ $metric['label'] ?? 'AI Metric' }}</span>
                                            <small>{{ $metric['description'] ?? 'AI Center metric' }}</small>
                                        </a>
                                    @else
                                        <div class="admin-dashboard-metric admin-dashboard-metric--{{ $metric['tone'] ?? 'blue' }}">
                                            <strong>{{ $metricDisplay }}</strong>
                                            <span>{{ $metric['label'] ?? 'AI Metric' }}</span>
                                            <small>{{ $metric['description'] ?? 'AI Center metric' }}</small>
                                        </div>
                                    @endif
                                @empty
                                    <div class="procurement-action-empty">
                                        <strong>No AI data yet</strong>
                                        <span>AI activity appears here after the AI Center records usage.</span>
                                    </div>
                                @endforelse
                            </div>
                        </article>

                    </div>
                </aside>
            </div>
        @elseif ($isSignatureOnlyDashboard)
            <div class="hope-dashboard-content row align-items-start g-3">
                <main class="col-12">
                    <section class="hope-main-stack">
                        <article class="admin-dashboard-panel">
                            <div class="procurement-card-heading">
                                <div>
                                    <p class="eyebrow">E-Signature</p>
                                    <h2>PR Forms for Signature</h2>
                                    <span>Purchase Request forms currently routed to this account.</span>
                                </div>
                                <a href="{{ route('signature-requests.index') }}" class="dashboard-action secondary-action">Open Signatures</a>
                            </div>

                            <div class="table-responsive procurement-table-wrap">
                                <table class="table table-sm align-middle mb-0 procurement-dashboard-table procurement-dashboard-table--minimal">
                                    <thead>
                                        <tr>
                                            <th>Tracking No.</th>
                                            <th>Requesting Office</th>
                                            <th>Signatory Field</th>
                                            <th>Date Requested</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse (($signatureDashboard['pending'] ?? []) as $signatureItem)
                                            <tr>
                                                <td>
                                                    <strong>{{ $signatureItem['tracking'] }}</strong>
                                                    <span>{{ $signatureItem['document'] }}</span>
                                                </td>
                                                <td>
                                                    <strong>{{ $signatureItem['office'] }}</strong>
                                                    <span>{{ $signatureItem['requestedBy'] }}</span>
                                                </td>
                                                <td>{{ $signatureItem['task'] }}</td>
                                                <td>{{ $signatureItem['date'] }}</td>
                                                <td>
                                                    <span class="procurement-status-badge status-progress">{{ $signatureItem['statusLabel'] }}</span>
                                                </td>
                                                <td>
                                                    <div class="procurement-icon-actions">
                                                        @if (! empty($signatureItem['href']))
                                                            <x-ui.action-button
                                                                :href="$signatureItem['href']"
                                                                icon="signature"
                                                                label="Apply Signature"
                                                                tooltip="Apply Signature"
                                                                variant="signature"
                                                                icon-only
                                                            />
                                                        @else
                                                            <span class="procurement-table-muted">N/A</span>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6">
                                                    <div class="procurement-empty-table">
                                                        <strong>No pending PR signature requests</strong>
                                                        <span>New PR forms routed for signature will appear here.</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </article>

                        <article class="admin-dashboard-panel">
                            <div class="procurement-card-heading">
                                <div>
                                    <p class="eyebrow">Signed PR Forms</p>
                                    <h2>Recently Signed</h2>
                                    <span>Latest Purchase Request signatures completed by this account.</span>
                                </div>
                                <a href="{{ route('signature-requests.index', ['status' => 'signed']) }}" class="dashboard-action secondary-action">View Signed</a>
                            </div>

                            <div class="table-responsive procurement-table-wrap">
                                <table class="table table-sm align-middle mb-0 procurement-dashboard-table procurement-dashboard-table--minimal">
                                    <thead>
                                        <tr>
                                            <th>Tracking No.</th>
                                            <th>Requesting Office</th>
                                            <th>Signatory Field</th>
                                            <th>Date Signed</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse (($signatureDashboard['signed'] ?? []) as $signatureItem)
                                            <tr>
                                                <td>
                                                    <strong>{{ $signatureItem['tracking'] }}</strong>
                                                    <span>{{ $signatureItem['document'] }}</span>
                                                </td>
                                                <td>
                                                    <strong>{{ $signatureItem['office'] }}</strong>
                                                    <span>{{ $signatureItem['requestedBy'] }}</span>
                                                </td>
                                                <td>{{ $signatureItem['task'] }}</td>
                                                <td>{{ $signatureItem['date'] }}</td>
                                                <td>
                                                    <div class="procurement-icon-actions">
                                                        @if (! empty($signatureItem['href']))
                                                            <x-ui.action-button
                                                                :href="$signatureItem['href']"
                                                                icon="view"
                                                                label="View Details"
                                                                tooltip="View Details"
                                                                variant="view"
                                                                icon-only
                                                            />
                                                        @else
                                                            <span class="procurement-table-muted">N/A</span>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5">
                                                    <div class="procurement-empty-table">
                                                        <strong>No signed PR forms yet</strong>
                                                        <span>Completed PR signatures will appear here.</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    </section>
                </main>
            </div>
        @else
            <div class="hope-dashboard-content row align-items-start g-3">
                <main class="col-12">
                    <section class="hope-main-stack">
                        <x-dashboard.document-table
                            :documents="$recentDocuments"
                            title="Recent Documents"
                            subtitle="Latest accessible procurement records with only the details needed for action."
                        />
                    </section>
                </main>
            </div>
        @endif
    </div>
@endsection

@push('vendor-scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
@endpush

@push('scripts')
    <script>
        (() => {
            const dashboard = document.querySelector('.enterprise-dashboard');
            if (!dashboard) return;

            if (window.jQuery) {
                window.jQuery(() => {
                    if (window.bootstrap) {
                        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((item) => {
                            new window.bootstrap.Tooltip(item);
                        });
                    }

                    if (window.flatpickr) {
                        window.jQuery('[data-flatpickr]').each((index, input) => {
                            window.flatpickr(input, { dateFormat: 'Y-m-d' });
                        });
                    }
                });
            }

            const fallback = (id) => {
                document.querySelector(`[data-chart-fallback="${id}"]`)?.removeAttribute('hidden');
            };

            if (!window.ApexCharts) {
                fallback('workflowOverviewChart');
                fallback('statusDistributionChart');
                return;
            }

            const workflowData = JSON.parse(dashboard.dataset.workflowChart || '{}');
            const statusData = JSON.parse(dashboard.dataset.statusChart || '{}');
            const chartPalette = ['#10b981', '#8b5cf6', '#f97316', '#94a3b8'];

            const workflowTarget = document.querySelector('#workflowOverviewChart');
            if (workflowTarget) {
                new ApexCharts(workflowTarget, {
                    chart: {
                        type: 'line',
                        height: 252,
                        toolbar: { show: false },
                        fontFamily: 'Figtree, Arial, sans-serif',
                    },
                    colors: ['#2563eb'],
                    series: workflowData.series || [{ name: 'Documents', data: [0, 0, 0, 0] }],
                    xaxis: {
                        categories: workflowData.categories || ['Created', 'Submitted', 'Approved', 'Returned'],
                        labels: { style: { colors: '#64748b' } },
                    },
                    yaxis: {
                        labels: { style: { colors: '#64748b' }, formatter: (value) => Math.round(value) },
                    },
                    stroke: { width: 4, curve: 'smooth' },
                    markers: { size: 5, strokeWidth: 3, strokeColors: '#ffffff' },
                    fill: {
                        type: 'gradient',
                        gradient: {
                            shadeIntensity: 0.35,
                            opacityFrom: 0.28,
                            opacityTo: 0.04,
                        },
                    },
                    grid: { borderColor: '#e8eef7', strokeDashArray: 4 },
                    dataLabels: { enabled: false },
                }).render();
            }

            const statusTarget = document.querySelector('#statusDistributionChart');
            if (statusTarget) {
                new ApexCharts(statusTarget, {
                    chart: {
                        type: 'donut',
                        height: 204,
                        toolbar: { show: false },
                        fontFamily: 'Figtree, Arial, sans-serif',
                    },
                    colors: chartPalette,
                    series: statusData.series || [0, 0, 0, 0],
                    labels: statusData.labels || ['Completed', 'Pending', 'Returned', 'Draft'],
                    legend: {
                        position: 'bottom',
                        labels: { colors: '#334155' },
                    },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '68%',
                                labels: {
                                    show: true,
                                    total: {
                                        show: true,
                                        label: 'Total',
                                        color: '#64748b',
                                    },
                                },
                            },
                        },
                    },
                    dataLabels: { enabled: false },
                    stroke: { width: 0 },
                }).render();
            }
        })();
    </script>
@endpush
