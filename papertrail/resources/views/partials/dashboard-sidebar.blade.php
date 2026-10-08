@php
    use Illuminate\Support\Facades\Route;

    $user = auth()->user();
    $roleSlug = $user?->roleSlug() ?? '';

    $isCompetitiveBiddingBacsec = $user?->user_id === 'BACSEC-002';
    $isSvpPostingProcessor = $user?->user_id === 'BACSEC-004';
    $isPrSignatureOnlyBacsec = $user?->user_id === 'BACSEC-003';
    $isBacsec002PrSigner = $user?->user_id === 'BACSEC-002';
    $isBacsecPrSignatureOnly = $user?->user_id === 'BACSEC-003';
    $hasBacsec002PurchaseRequestCapability = $user?->hasBacsec002PurchaseRequestCapability() ?? false;

    $safeCount = function (callable $callback): int {
        try {
            return (int) $callback();
        } catch (\Throwable $exception) {
            return 0;
        }
    };

    $pendingSignatureCount = $safeCount(function () use ($user) {
        if (! $user) {
            return 0;
        }

        return app(\App\Services\SignatureRequestService::class)
            ->getPendingRequestsForUser($user)
            ->count();
    });

    $pendingPrNumberCount = $safeCount(fn () => \App\Models\ProcurementDocument::query()
        ->where('status', \App\Models\ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT)
        ->where('pr_number_status', \App\Models\ProcurementDocument::PR_NUMBER_STATUS_PENDING_ASSIGNMENT)
        ->count());

    $pendingPpmpReviewCount = $safeCount(fn () => \App\Models\ProcurementDocument::query()
        ->whereIn('status', [
            \App\Models\ProcurementDocument::STATUS_PENDING_PPMP_REVIEW,
            \App\Models\ProcurementDocument::STATUS_UNDER_PPMP_REVIEW,
        ])
        ->count());

    $submittedPrCount = $safeCount(function () use ($user) {
        if (! $user) {
            return 0;
        }

        return \App\Models\ProcurementDocument::query()
            ->purchaseRequestsForBacSecretariat($user)
            ->where(function ($query) {
                $query->whereIn('status', [
                    \App\Models\ProcurementDocument::STATUS_PR_SUBMITTED,
                    \App\Models\ProcurementDocument::STATUS_SUBMITTED_TO_BAC_SECRETARIAT,
                    \App\Models\ProcurementDocument::STATUS_READY_FOR_BAC_RESOLUTION,
                    \App\Models\ProcurementDocument::STATUS_BAC_RESOLUTION_CREATED,
                ])->orWhereHas('bacResolutions');
            })
            ->count();
    });

    $signatureMenu = [
        'label' => 'Signatures',
        'icon' => 'signature',
        'count' => $pendingSignatureCount,
        'children' => [
            ['label' => 'Pending Signatures', 'route' => 'signature-requests.index', 'active' => 'signature-requests.*', 'query' => ['status' => 'pending'], 'count' => $pendingSignatureCount],
            ['label' => 'Signed Documents', 'route' => 'signature-requests.index', 'active' => 'signature-requests.index', 'query' => ['status' => \App\Models\SignatureRequest::STATUS_SIGNED]],
        ],
    ];

    $items = [
        'admin' => [
            ['section' => 'Workspace'],
            ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'dashboard'],
            [
                'label' => 'Management',
                'icon' => 'users',
                'children' => [
                    ['label' => 'Users', 'route' => 'admin.users.index', 'active' => 'admin.users.*'],
                    ['label' => 'Offices', 'route' => 'admin.offices.index', 'active' => 'admin.offices.*'],
                    ['label' => 'Roles & Permissions', 'route' => 'admin.roles.index', 'active' => 'admin.roles.*'],
                    ['label' => 'Document Templates', 'route' => 'admin.document-templates.index', 'active' => 'admin.document-templates.*'],
                ],
            ],
            [
                'label' => 'Monitoring',
                'icon' => 'tracking',
                'children' => [
                    ['label' => 'Audit Trail', 'route' => 'admin.audit.index', 'active' => 'admin.audit.*'],
                    ['label' => 'Reports', 'route' => 'admin.reports.index', 'active' => 'admin.reports.*'],
                    ['label' => 'AI Summary', 'route' => 'admin.ai-summary.index', 'active' => 'admin.ai-summary.*'],
                ],
            ],
            ['label' => 'Settings', 'route' => 'admin.settings.index', 'icon' => 'settings', 'active' => 'admin.settings.*'],
        ],
        'head-office' => [
            ['section' => 'Workspace'],
            ['label' => 'Dashboard', 'route' => 'head-office.dashboard', 'icon' => 'dashboard'],
            [
                'label' => 'Documents',
                'icon' => 'planning',
                'children' => [
                    ['label' => 'PPMP', 'route' => 'head-office.ppmp.index', 'active' => 'head-office.ppmp.*'],
                    ['label' => 'My Documents', 'route' => 'head-office.documents.index', 'active' => ['head-office.documents.*', 'head-office.returned.*']],
                    ['label' => 'Status Tracking', 'route' => 'head-office.svp-tracking.index', 'active' => 'head-office.svp-tracking.*'],
                ],
            ],
            [
                'label' => 'SVP / Alternative Procurement',
                'icon' => 'workflow',
                'children' => [
                    ['label' => 'Create PR', 'route' => 'head-office.pr.create', 'active' => 'head-office.pr.create'],
                    ['label' => 'Received BAC Resolutions', 'route' => 'head-office.resolutions.index', 'active' => 'head-office.resolutions.*'],
                    ['label' => 'RFQ', 'route' => 'head-office.rfqs.create', 'active' => 'head-office.rfqs.*'],
                    ['label' => 'Abstract', 'route' => 'head-office.abstracts.create', 'active' => 'head-office.abstracts.*'],
                    ['label' => 'Purchase Order', 'route' => 'head-office.purchase-orders.create', 'active' => 'head-office.purchase-orders.*'],
                    ['label' => 'Inspection / Acceptance', 'route' => 'head-office.inspection.index', 'active' => 'head-office.inspection.*'],
                ],
            ],
            ['label' => 'AI Center', 'route' => 'assistant.index', 'icon' => 'review', 'active' => 'assistant.*'],
        ],
        'budget' => [
            ['section' => 'Workspace'],
            ['label' => 'Dashboard', 'route' => 'budget.dashboard', 'icon' => 'dashboard'],
            [
                'label' => 'Budget Review',
                'icon' => 'budget',
                'children' => [
                    ['label' => 'Pending Review', 'route' => 'budget.pending-review.index', 'active' => 'budget.pending-review.*'],
                    ['label' => 'Reviewed Documents', 'route' => 'budget.reviewed.index', 'active' => 'budget.reviewed.*'],
                    ['label' => 'Returned Documents', 'route' => 'budget.returned.index', 'active' => 'budget.returned.*'],
                    ['label' => 'Reports', 'route' => 'budget.reports.index', 'active' => 'budget.reports.*'],
                ],
            ],
            ['label' => 'AI Center', 'route' => 'assistant.index', 'icon' => 'review', 'active' => 'assistant.*'],
        ],
        'accounting' => [
            ['section' => 'Workspace'],
            ['label' => 'Dashboard', 'route' => 'accounting.dashboard', 'icon' => 'dashboard'],
            [
                'label' => 'Accounting Review',
                'icon' => 'review',
                'children' => [
                    ['label' => 'Pending Review', 'route' => 'accounting.pending-review.index', 'active' => 'accounting.pending-review.*'],
                    ['label' => 'Reviewed Documents', 'route' => 'accounting.reviewed.index', 'active' => 'accounting.reviewed.*'],
                    ['label' => 'Returned Documents', 'route' => 'accounting.returned.index', 'active' => 'accounting.returned.*'],
                    ['label' => 'Reports', 'route' => 'accounting.reports.index', 'active' => 'accounting.reports.*'],
                ],
            ],
            ['label' => 'AI Center', 'route' => 'assistant.index', 'icon' => 'review', 'active' => 'assistant.*'],
        ],
        'pr-numbering' => [
            ['section' => 'Workspace'],
            ['label' => 'Dashboard', 'route' => 'pr-numbering.dashboard', 'icon' => 'dashboard'],
            [
                'label' => 'PR Numbering',
                'icon' => 'hash',
                'count' => $pendingPrNumberCount,
                'children' => [
                    ['label' => 'Pending Requests', 'route' => 'pr-numbering.pending.index', 'active' => 'pr-numbering.pending.*', 'count' => $pendingPrNumberCount],
                    ['label' => 'Assigned PR Numbers', 'route' => 'pr-numbering.assigned.index', 'active' => 'pr-numbering.assigned.*'],
                ],
            ],
            ['label' => 'AI Center', 'route' => 'assistant.index', 'icon' => 'review', 'active' => 'assistant.*'],
        ],
        'bac-secretariat' => [
            ['section' => 'Workspace'],
            ['label' => 'Dashboard', 'route' => 'bac-secretariat.dashboard', 'icon' => 'dashboard'],
            [
                'label' => 'Planning',
                'icon' => 'planning',
                'children' => [
                    ['label' => 'PPMP Review', 'route' => 'bac-secretariat.ppmp.index', 'active' => 'bac-secretariat.ppmp.*', 'count' => $pendingPpmpReviewCount],
                    ['label' => 'APP Consolidation', 'route' => 'bac-secretariat.app.index', 'active' => 'bac-secretariat.app.*'],
                    ['label' => 'Supplemental APP', 'route' => 'bac-secretariat.supplemental-apps.create', 'active' => 'bac-secretariat.supplemental-apps.*'],
                ],
            ],
            [
                'label' => 'SVP / Alternative Procurement',
                'icon' => 'workflow',
                'children' => [
                    ['label' => 'Incoming Documents', 'route' => 'bac-secretariat.incoming.index', 'active' => 'bac-secretariat.incoming.*'],
                    ['label' => 'Purchase Requests', 'route' => 'bac-secretariat.pr.index', 'active' => 'bac-secretariat.pr.*'],
                    ['label' => 'BAC Resolution', 'route' => 'bac-secretariat.resolutions.create', 'active' => 'bac-secretariat.resolutions.*'],
                    ['label' => 'RFQ', 'route' => 'bac-secretariat.rfqs.create', 'active' => 'bac-secretariat.rfqs.*'],
                    ['label' => 'Abstract', 'route' => 'bac-secretariat.abstracts.create', 'active' => 'bac-secretariat.abstracts.*'],
                    ['label' => 'Purchase Order', 'route' => 'bac-secretariat.purchase-orders.create', 'active' => 'bac-secretariat.purchase-orders.*'],
                    ['label' => 'Posting', 'route' => 'bac-secretariat.svp-posting.pending', 'active' => 'bac-secretariat.svp-posting.*', 'posting_only' => true],
                ],
            ],
            [
                'label' => 'Monitoring',
                'icon' => 'tracking',
                'children' => [
                    ['label' => 'Document Tracking', 'route' => 'bac-secretariat.routing.index', 'active' => 'bac-secretariat.routing.*'],
                    ['label' => 'SVP Monitoring', 'route' => 'bac-secretariat.svp-monitoring.index', 'active' => 'bac-secretariat.svp-monitoring.*'],
                    ['label' => 'Audit Trail', 'route' => 'bac-secretariat.audit.index', 'active' => 'bac-secretariat.audit.*'],
                    ['label' => 'Reports', 'route' => 'bac-secretariat.reports.index', 'active' => 'bac-secretariat.reports.*'],
                ],
            ],
            ['label' => 'AI Center', 'route' => 'assistant.index', 'icon' => 'review', 'active' => 'assistant.*'],
        ],
        'bac-member' => [
            ['section' => 'Workspace'],
            ['label' => 'Dashboard', 'route' => 'bac-member.dashboard', 'icon' => 'dashboard'],
            [
                'label' => 'BAC Review',
                'icon' => 'review',
                'children' => [
                    ['label' => 'Documents for Review', 'route' => 'bac-member.review.index', 'active' => 'bac-member.review.*'],
                    ['label' => 'Reviewed Documents', 'route' => 'bac-member.reviewed.index', 'active' => 'bac-member.reviewed.*'],
                    ['label' => 'Deliberations', 'route' => 'bac-member.deliberations.index', 'active' => 'bac-member.deliberations.*'],
                ],
            ],
        ],
        'bac-chair' => [
            ['section' => 'Workspace'],
            ['label' => 'Dashboard', 'route' => 'bac-chair.dashboard', 'icon' => 'dashboard'],
            [
                'label' => 'BAC Chair Review',
                'icon' => 'approval',
                'children' => [
                    ['label' => 'Approvals', 'route' => 'bac-chair.approvals.index', 'active' => 'bac-chair.approvals.*'],
                    ['label' => 'Reviewed Documents', 'route' => 'bac-chair.reviewed.index', 'active' => 'bac-chair.reviewed.*'],
                    ['label' => 'BAC Resolutions', 'route' => 'bac-chair.resolutions.index', 'active' => 'bac-chair.resolutions.*'],
                    ['label' => 'Reports', 'route' => 'bac-chair.reports.index', 'active' => 'bac-chair.reports.*'],
                ],
            ],
        ],
        'approving-authority' => [
            ['section' => 'Workspace'],
            ['label' => 'Dashboard', 'route' => 'approving-authority.dashboard', 'icon' => 'dashboard'],
            [
                'label' => 'Approvals',
                'icon' => 'approval',
                'children' => [
                    ['label' => 'Pending Approval', 'route' => 'approving-authority.pending.index', 'active' => 'approving-authority.pending.*'],
                    ['label' => 'Approved Documents', 'route' => 'approving-authority.approved.index', 'active' => 'approving-authority.approved.*'],
                    ['label' => 'Returned Documents', 'route' => 'approving-authority.returned.index', 'active' => 'approving-authority.returned.*'],
                    ['label' => 'Reports', 'route' => 'approving-authority.reports.index', 'active' => 'approving-authority.reports.*'],
                ],
            ],
        ],
    ][$roleSlug] ?? [
        ['section' => 'Workspace'],
        ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'dashboard'],
    ];

    if ($roleSlug === 'bac-secretariat' && ($isCompetitiveBiddingBacsec || $isBacsecPrSignatureOnly)) {
        $items = array_values(array_filter($items, fn ($item) => ($item['label'] ?? null) !== 'Planning'));
    }

    if ($roleSlug === 'bac-secretariat' && ($isPrSignatureOnlyBacsec || $isBacsec002PrSigner || $isBacsecPrSignatureOnly)) {
        $items = array_values(array_filter($items, fn ($item) => ($item['label'] ?? null) !== 'SVP / Alternative Procurement'));
    }

    if ($roleSlug === 'bac-secretariat' && $isSvpPostingProcessor) {
        foreach ($items as &$item) {
            if (($item['label'] ?? null) === 'SVP / Alternative Procurement') {
                $item['label'] = 'Posting Management';
                $item['children'] = array_values(array_filter($item['children'] ?? [], fn ($child) => ! empty($child['posting_only'])));
            }
        }
        unset($item);
        $items = array_values(array_filter($items, fn ($item) => ($item['label'] ?? null) !== 'Monitoring'));
    } elseif ($roleSlug === 'bac-secretariat') {
        foreach ($items as &$item) {
            if (($item['label'] ?? null) === 'SVP / Alternative Procurement') {
                $item['children'] = array_values(array_filter($item['children'] ?? [], fn ($child) => empty($child['posting_only'])));
            }
        }
        unset($item);
    }

    if ($roleSlug === 'bac-secretariat' && $hasBacsec002PurchaseRequestCapability && ! $isBacsecPrSignatureOnly) {
        $competitiveBiddingTask = fn (string $label, string $task) => [
            'label' => $label,
            'route' => 'bac-secretariat.competitive-bidding.menu',
            'active' => 'bac-secretariat.competitive-bidding.menu',
            'query' => ['task' => $task],
        ];

        $svpResolutionMenu = [
            'label' => 'SVP / Alternative Procurement',
            'icon' => 'workflow',
            'children' => [
                ['label' => 'PR / Resolution', 'section' => true],
                ['label' => 'Submitted PRs', 'route' => 'bac-secretariat.pr.index', 'active' => 'bac-secretariat.pr.*', 'query' => ['status' => 'submitted'], 'count' => $submittedPrCount],
                ['label' => 'BAC Resolution Records', 'route' => 'bac-secretariat.resolutions.create', 'active' => ['bac-secretariat.resolutions.menu', 'bac-secretariat.resolutions.*']],
            ],
        ];

        $competitiveBiddingMenu = [
            'label' => 'Competitive Bidding',
            'icon' => 'workflow',
            'children' => [
                ['label' => 'Competitive Bidding', 'section' => true],
                $competitiveBiddingTask('Letter of Invitation to Observer', 'letter-of-invitation-to-observer'),
                [
                    'label' => 'Checklist Requirements',
                    'children' => [
                        ['label' => 'Technical', 'route' => 'bac-secretariat.competitive-bidding.checklist.technical', 'active' => 'bac-secretariat.competitive-bidding.checklist.technical'],
                        ['label' => 'Financial', 'route' => 'bac-secretariat.competitive-bidding.checklist.financial', 'active' => 'bac-secretariat.competitive-bidding.checklist.financial'],
                    ],
                ],
                [
                    'label' => 'Abstract of Bids',
                    'children' => [
                        ['label' => 'As Read', 'route' => 'bac-secretariat.competitive-bidding.abstract-bids.as-read', 'active' => 'bac-secretariat.competitive-bidding.abstract-bids.as-read'],
                        ['label' => 'As Calculated', 'route' => 'bac-secretariat.competitive-bidding.abstract-bids.as-calculated', 'active' => 'bac-secretariat.competitive-bidding.abstract-bids.as-calculated'],
                    ],
                ],
                ['label' => 'Bid Evaluation', 'route' => 'bac-secretariat.competitive-bidding.bid-evaluation', 'active' => 'bac-secretariat.competitive-bidding.bid-evaluation'],
                ['label' => 'Post Qualification Evaluation', 'route' => 'bac-secretariat.competitive-bidding.post-qualification-evaluation', 'active' => 'bac-secretariat.competitive-bidding.post-qualification-evaluation'],
                $competitiveBiddingTask('Post Qualification Report of Technical Working Group', 'post-qualification-report-twg'),
                $competitiveBiddingTask('Notice of Post Qualification', 'notice-of-post-qualification'),
                $competitiveBiddingTask('BAC Resolution on Post Qualification', 'bac-resolution-on-post-qualification'),
                $competitiveBiddingTask('Notice Issued by the BAC', 'notice-issued-by-the-bac'),
                $competitiveBiddingTask('Notice of Award of Contract', 'notice-of-award-of-contract'),
                $competitiveBiddingTask('Performance Bond', 'performance-bond'),
                $competitiveBiddingTask('Contract Agreement Form', 'contract-agreement-form'),
                ['label' => 'Purchase Order', 'route' => 'bac-secretariat.purchase-orders.create', 'active' => ['bac-secretariat.purchase-orders.menu', 'bac-secretariat.purchase-orders.*']],
                $competitiveBiddingTask('Notice to Proceed', 'notice-to-proceed'),
            ],
        ];

        array_splice($items, 2, 0, [$svpResolutionMenu, $competitiveBiddingMenu]);
    }

    $canShowSignatureMenu = in_array($roleSlug, [
        'head-office',
        'budget',
        'accounting',
        'bac-secretariat',
        'bac-member',
        'bac-chair',
        'approving-authority',
    ], true);

    if ($canShowSignatureMenu) {
        $items[] = $signatureMenu;
    }

    $items = array_merge($items, [
        ['section' => 'Account'],
        ['label' => 'Email Activity', 'route' => 'email-activity.index', 'icon' => 'mail', 'active' => 'email-activity.*'],
        ['label' => 'Profile', 'route' => 'profile.show', 'icon' => 'user', 'active' => 'profile.*'],
    ]);

    $routeMatches = function ($patterns): bool {
        foreach ((array) $patterns as $pattern) {
            if ($pattern && request()->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    };

    $queryMatches = function (array $query): bool {
        foreach ($query as $key => $value) {
            if ((string) request()->query($key) !== (string) $value) {
                return false;
            }
        }

        return true;
    };

    $itemIsActive = function (array $item) use (&$itemIsActive, $routeMatches, $queryMatches): bool {
        $patterns = $item['active'] ?? ($item['route'] ?? null);
        $active = $patterns ? $routeMatches($patterns) : false;

        if ($active && ! empty($item['query']) && ! $queryMatches($item['query'])) {
            $active = false;
        }

        if ($active) {
            return true;
        }

        foreach ($item['children'] ?? [] as $child) {
            if (empty($child['section']) && $itemIsActive($child)) {
                return true;
            }
        }

        return false;
    };

    $routeUrl = function (array $item): ?string {
        if (empty($item['url']) && (empty($item['route']) || ! Route::has($item['route']))) {
            return null;
        }

        if (! empty($item['url'])) {
            return $item['url'];
        }

        return route($item['route'], array_merge($item['parameters'] ?? [], $item['query'] ?? []));
    };

    $isRenderable = function (array $item) use ($routeUrl): bool {
        return ! empty($item['section']) || ! empty($item['children']) || $routeUrl($item) !== null || ! empty($item['disabled']);
    };

    $dashboardRoute = $user?->dashboardRoute() ?? 'dashboard';
    $dashboardHref = Route::has($dashboardRoute) ? route($dashboardRoute) : url('/');
    $paperTrailLogoPath = 'images/logos/papertrail-logo.jpg';
    $hasPaperTrailLogo = file_exists(public_path($paperTrailLogoPath));
@endphp

<aside id="dashboard-sidebar" class="dashboard-sidebar" aria-label="Primary navigation">
    <div class="sidebar-top">
        <div class="sidebar-header">
            <a href="{{ $dashboardHref }}" class="sidebar-brand">
                <span class="brand-mark {{ $hasPaperTrailLogo ? 'brand-mark--image' : '' }}">
                    @if ($hasPaperTrailLogo)
                        <img src="{{ asset($paperTrailLogoPath) }}" alt="" aria-hidden="true">
                    @else
                        PT
                    @endif
                </span>
                <span class="brand-text">
                    <strong>PaperTrail</strong>
                    <small>LGU Procurement</small>
                </span>
            </a>
            <button class="sidebar-collapse-button" type="button" data-sidebar-collapse aria-expanded="true" aria-label="Collapse sidebar">
                <svg class="collapse-icon collapse-icon-left" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M15 18 9 12l6-6" />
                </svg>
            </button>
        </div>

        <nav class="sidebar-nav">
            @foreach ($items as $item)
                @if (! empty($item['section']))
                    <span class="sidebar-section-label">{{ $item['section'] }}</span>
                    @continue
                @endif

                @if (! $isRenderable($item))
                    @continue
                @endif

                @php
                    $hasChildren = ! empty($item['children']);
                    $itemActive = $itemIsActive($item);
                    $itemHref = $routeUrl($item);
                    $itemCount = (int) ($item['count'] ?? 0);
                @endphp

                @if ($hasChildren)
                    <div class="sidebar-dropdown {{ $itemActive ? 'is-open' : '' }}" data-sidebar-dropdown>
                        <button
                            class="sidebar-dropdown-toggle"
                            type="button"
                            title="{{ $item['label'] }}"
                            aria-expanded="{{ $itemActive ? 'true' : 'false' }}"
                            data-sidebar-dropdown-toggle
                        >
                            <x-sidebar-icon :name="$item['icon'] ?? 'circle'" />
                            <span class="sidebar-link-label">{{ $item['label'] }}</span>
                            @if ($itemCount > 0)
                                <span class="sidebar-count-badge {{ ($item['label'] ?? '') === 'Signatures' ? 'sidebar-count-badge--signature' : '' }}">
                                    {{ $itemCount > 99 ? '99+' : number_format($itemCount) }}
                                </span>
                            @endif
                            <span class="sidebar-dropdown-arrow" aria-hidden="true">
                                <svg viewBox="0 0 20 20" focusable="false">
                                    <path d="m6 8 4 4 4-4" />
                                </svg>
                            </span>
                        </button>

                        <div class="sidebar-submenu sidebar-dropdown-menu">
                            @foreach ($item['children'] as $child)
                                @if (! empty($child['section']))
                                    <span class="sidebar-submenu-heading">{{ $child['label'] }}</span>
                                    @continue
                                @endif

                                @php
                                    $childHasChildren = ! empty($child['children']);
                                    $childHref = $routeUrl($child);
                                    $childActive = $itemIsActive($child);
                                    $childCount = (int) ($child['count'] ?? 0);
                                    $childBadgeClass = ($item['label'] ?? '') === 'Signatures' && ($child['label'] ?? '') === 'Pending Signatures'
                                        ? 'sidebar-count-badge--signature'
                                        : '';
                                @endphp

                                @if ($childHasChildren)
                                    <div class="sidebar-nested-dropdown {{ $childActive ? 'is-open' : '' }}" data-sidebar-dropdown>
                                        <button
                                            class="sidebar-nested-toggle {{ $childActive ? 'is-active' : '' }}"
                                            type="button"
                                            title="{{ $child['label'] }}"
                                            aria-expanded="{{ $childActive ? 'true' : 'false' }}"
                                            data-sidebar-dropdown-toggle
                                        >
                                            <span class="sidebar-link-label">{{ $child['label'] }}</span>
                                            <span class="sidebar-dropdown-arrow" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" focusable="false">
                                                    <path d="m6 8 4 4 4-4" />
                                                </svg>
                                            </span>
                                        </button>

                                        <div class="sidebar-nested-menu">
                                            @foreach ($child['children'] as $nestedChild)
                                                @if (! empty($nestedChild['section']))
                                                    <span class="sidebar-submenu-heading">{{ $nestedChild['label'] }}</span>
                                                    @continue
                                                @endif

                                                @php
                                                    $nestedHref = $routeUrl($nestedChild);
                                                    $nestedActive = $itemIsActive($nestedChild);
                                                    $nestedCount = (int) ($nestedChild['count'] ?? 0);
                                                @endphp

                                                @if (! $nestedHref)
                                                    @continue
                                                @endif

                                                <a
                                                    href="{{ $nestedHref }}"
                                                    class="{{ $nestedActive ? 'is-active' : '' }}"
                                                    title="{{ $nestedChild['label'] }}"
                                                    data-sidebar-link="true"
                                                >
                                                    <span class="sidebar-link-label">{{ $nestedChild['label'] }}</span>
                                                    @if ($nestedCount > 0)
                                                        <span class="sidebar-count-badge">{{ $nestedCount > 99 ? '99+' : number_format($nestedCount) }}</span>
                                                    @elseif (! empty($nestedChild['badge']))
                                                        <span class="sidebar-coming-soon">{{ $nestedChild['badge'] }}</span>
                                                    @endif
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                    @continue
                                @endif

                                @if (! $childHref)
                                    @continue
                                @endif

                                <a
                                    href="{{ $childHref }}"
                                    class="{{ $childActive ? 'is-active' : '' }}"
                                    title="{{ $child['label'] }}"
                                    data-sidebar-link="true"
                                >
                                    <span class="sidebar-link-label">{{ $child['label'] }}</span>
                                    @if ($childCount > 0)
                                        <span class="sidebar-count-badge {{ $childBadgeClass }}">{{ $childCount > 99 ? '99+' : number_format($childCount) }}</span>
                                    @elseif (! empty($child['badge']))
                                        <span class="sidebar-coming-soon">{{ $child['badge'] }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @elseif ($itemHref)
                    <a
                        href="{{ $itemHref }}"
                        class="{{ $itemActive ? 'is-active' : '' }}"
                        title="{{ $item['label'] }}"
                        data-sidebar-link="true"
                    >
                        <x-sidebar-icon :name="$item['icon'] ?? 'circle'" />
                        <span class="sidebar-link-label">{{ $item['label'] }}</span>
                        @if ($itemCount > 0)
                            <span class="sidebar-count-badge">{{ $itemCount > 99 ? '99+' : number_format($itemCount) }}</span>
                        @endif
                    </a>

                @endif
            @endforeach
        </nav>
    </div>

    <form method="POST" action="{{ route('logout') }}" class="sidebar-logout">
        @csrf
        <button type="submit" title="Logout">
            <x-sidebar-icon name="logout" />
            <span class="sidebar-link-label">Logout</span>
        </button>
    </form>
</aside>
