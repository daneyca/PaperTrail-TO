<?php

namespace App\Console\Commands;

use App\Models\AbstractQuotation;
use App\Models\AppConsolidation;
use App\Models\BacDeliberation;
use App\Models\BacResolution;
use App\Models\ElectronicSignature;
use App\Models\InspectionAcceptanceRecord;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\SignatureRequest;
use App\Models\SignedDocumentSnapshot;
use App\Models\SupplementalApp;
use App\Models\SvpChainEvent;
use App\Models\SvpPostingRecord;
use App\Models\SvpProcurementChain;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CleanupTestDocuments extends Command
{
    protected $signature = 'papertrail:cleanup-test-documents
        {--office= : Office code/name to clean, for example BAC}
        {--all-offices : Delete procurement transaction records for all offices}
        {--dry-run : Preview counts without deleting}
        {--force : Skip confirmation prompt}';

    protected $description = 'Safely remove local/demo procurement transaction records without deleting users, roles, offices, or settings.';

    private const TRACKED_TABLES = [
        'procurement_documents',
        'app_consolidations',
        'app_ppmp_sources',
        'app_items',
        'bac_resolutions',
        'supplemental_apps',
        'rfqs',
        'abstracts',
        'purchase_orders',
        'inspection_acceptance_records',
        'svp_procurement_chains',
        'svp_chain_events',
        'svp_posting_records',
        'signature_requests',
        'electronic_signatures',
        'signed_document_snapshots',
        'bac_deliberations',
    ];

    private const RELATED_MODEL_TYPES = [
        'procurement_documents' => ProcurementDocument::class,
        'app_consolidations' => AppConsolidation::class,
        'bac_resolutions' => BacResolution::class,
        'supplemental_apps' => SupplementalApp::class,
        'rfqs' => Rfq::class,
        'abstracts' => AbstractQuotation::class,
        'purchase_orders' => PurchaseOrder::class,
        'inspection_acceptance_records' => InspectionAcceptanceRecord::class,
        'svp_procurement_chains' => SvpProcurementChain::class,
        'svp_chain_events' => SvpChainEvent::class,
        'svp_posting_records' => SvpPostingRecord::class,
        'signature_requests' => SignatureRequest::class,
        'electronic_signatures' => ElectronicSignature::class,
        'signed_document_snapshots' => SignedDocumentSnapshot::class,
        'bac_deliberations' => BacDeliberation::class,
    ];

    private const SIGNATURE_DOCUMENT_TYPES = [
        'procurement_documents' => ['ppmp', 'purchase_request', 'procurement_document'],
        'bac_resolutions' => ['bac_resolution'],
        'abstracts' => ['abstract', 'abstract_quotation'],
        'rfqs' => ['rfq'],
        'purchase_orders' => ['purchase_order'],
        'inspection_acceptance_records' => ['inspection_acceptance'],
    ];

    private array $ids = [];

    private array $attachmentPaths = [];

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('This cleanup command is disabled in production.');

            return self::FAILURE;
        }

        $officeOption = trim((string) $this->option('office'));
        $allOffices = (bool) $this->option('all-offices');
        $dryRun = (bool) $this->option('dry-run');

        if (($officeOption === '' && ! $allOffices) || ($officeOption !== '' && $allOffices)) {
            $this->error('Choose exactly one cleanup scope: --office=CODE or --all-offices.');

            return self::FAILURE;
        }

        $office = null;
        $officeUserIds = [];

        if (! $allOffices) {
            $office = $this->resolveOffice($officeOption);

            if (! $office) {
                $this->error("Office [{$officeOption}] was not found.");

                return self::FAILURE;
            }

            $officeUserIds = User::query()
                ->where('office_id', $office->id)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $scopeLabel = $allOffices
            ? 'ALL OFFICES'
            : "{$office->code} - {$office->name}";

        $this->ids = $this->collectAffectedIds($office?->id, $officeUserIds, $allOffices);
        $counts = $this->buildCounts();

        $this->newLine();
        $this->info('PaperTrail procurement test-document cleanup');
        $this->line("Scope: {$scopeLabel}");
        $this->line('Mode: '.($dryRun ? 'DRY RUN' : 'DELETE'));
        $this->newLine();
        $this->table(['Record group', 'Rows'], $this->countRows($counts));

        $totalRows = array_sum($counts);

        if ($totalRows === 0) {
            $this->info('No matching procurement transaction records were found.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('Dry run complete. No records were deleted.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $confirmed = $this->confirm(
                "This will permanently delete {$totalRows} procurement transaction rows for {$scopeLabel}. Continue?",
                false
            );

            if (! $confirmed) {
                $this->warn('Cleanup cancelled.');

                return self::SUCCESS;
            }
        }

        DB::transaction(function (): void {
            $this->deleteRecords();
        });

        $deletedFiles = $this->deleteAttachmentFiles();

        $this->callSilent('optimize:clear');

        $this->newLine();
        $this->info("Cleanup complete. Deleted {$totalRows} database rows.");

        if ($deletedFiles > 0) {
            $this->line("Deleted {$deletedFiles} attachment file(s) from the public storage disk.");
        }

        $this->line('Laravel caches were cleared.');

        return self::SUCCESS;
    }

    private function resolveOffice(string $officeOption): ?Office
    {
        $needle = trim($officeOption);

        return Office::query()
            ->where('code', $needle)
            ->orWhere('name', $needle)
            ->orWhere('name', 'like', "%{$needle}%")
            ->when(strtoupper($needle) === 'BAC', function ($query): void {
                $query->orWhere('name', 'like', '%Bids and Awards Committee%')
                    ->orWhere('name', 'like', '%Bids and Awards Comittee%');
            })
            ->orderByRaw('CASE WHEN code = ? THEN 0 WHEN name = ? THEN 1 ELSE 2 END', [$needle, $needle])
            ->first();
    }

    private function collectAffectedIds(?int $officeId, array $officeUserIds, bool $allOffices): array
    {
        $ids = array_fill_keys(self::TRACKED_TABLES, []);

        if ($allOffices) {
            foreach (self::TRACKED_TABLES as $table) {
                $ids[$table] = $this->allIds($table);
            }

            return $this->normalizeIds($ids);
        }

        $ids['procurement_documents'] = $this->scopedIds(
            'procurement_documents',
            $officeId,
            $officeUserIds,
            ['office_id', 'submitting_office_id', 'requesting_office_id'],
            ['prepared_by_user_id', 'submitted_by_user_id', 'created_by_user_id']
        );

        for ($pass = 0; $pass < 4; $pass++) {
            $ids['app_consolidations'] = $this->mergeIds(
                $ids['app_consolidations'],
                $this->scopedIds(
                    'app_consolidations',
                    $officeId,
                    $officeUserIds,
                    ['office_id'],
                    ['prepared_by_user_id', 'submitted_by_user_id', 'created_by_user_id'],
                    ['procurement_document_id' => $ids['procurement_documents']]
                )
            );

            $ids['app_ppmp_sources'] = $this->mergeIds(
                $ids['app_ppmp_sources'],
                $this->scopedIds(
                    'app_ppmp_sources',
                    $officeId,
                    $officeUserIds,
                    ['office_id'],
                    ['included_by_user_id', 'created_by_user_id'],
                    [
                        'app_consolidation_id' => $ids['app_consolidations'],
                        'procurement_document_id' => $ids['procurement_documents'],
                    ]
                )
            );

            $ids['app_items'] = $this->mergeIds(
                $ids['app_items'],
                $this->scopedIds(
                    'app_items',
                    $officeId,
                    $officeUserIds,
                    ['office_id'],
                    ['created_by_user_id'],
                    [
                        'app_consolidation_id' => $ids['app_consolidations'],
                        'source_ppmp_document_id' => $ids['procurement_documents'],
                    ]
                )
            );

            $ids['procurement_documents'] = $this->mergeIds(
                $ids['procurement_documents'],
                $this->pluckIds('app_consolidations', 'procurement_document_id', $ids['app_consolidations']),
                $this->pluckIds('app_ppmp_sources', 'procurement_document_id', $ids['app_ppmp_sources']),
                $this->pluckIds('app_items', 'source_ppmp_document_id', $ids['app_items'])
            );

            $ids['bac_resolutions'] = $this->mergeIds(
                $ids['bac_resolutions'],
                $this->scopedIds(
                    'bac_resolutions',
                    $officeId,
                    $officeUserIds,
                    ['office_id', 'requesting_office_id'],
                    [
                        'prepared_by_user_id',
                        'submitted_by_user_id',
                        'created_by_user_id',
                    ],
                    [
                        'source_pr_document_id' => $ids['procurement_documents'],
                        'procurement_document_id' => $ids['procurement_documents'],
                    ]
                )
            );

            $ids['supplemental_apps'] = $this->mergeIds(
                $ids['supplemental_apps'],
                $this->scopedIds(
                    'supplemental_apps',
                    $officeId,
                    $officeUserIds,
                    ['requesting_office_id', 'office_id'],
                    ['prepared_by_user_id', 'submitted_by_user_id', 'accepted_by_user_id', 'created_by_user_id'],
                    ['source_pr_document_id' => $ids['procurement_documents']]
                )
            );

            $ids['rfqs'] = $this->mergeIds(
                $ids['rfqs'],
                $this->scopedIds(
                    'rfqs',
                    $officeId,
                    $officeUserIds,
                    ['office_id', 'requesting_office_id'],
                    ['prepared_by_user_id', 'submitted_by_user_id', 'created_by_user_id'],
                    [
                        'source_pr_document_id' => $ids['procurement_documents'],
                        'source_bac_resolution_id' => $ids['bac_resolutions'],
                    ]
                )
            );

            $ids['abstracts'] = $this->mergeIds(
                $ids['abstracts'],
                $this->scopedIds(
                    'abstracts',
                    $officeId,
                    $officeUserIds,
                    ['office_id', 'requesting_office_id'],
                    ['prepared_by_user_id', 'submitted_by_user_id', 'created_by_user_id'],
                    [
                        'source_pr_document_id' => $ids['procurement_documents'],
                        'source_rfq_id' => $ids['rfqs'],
                        'source_bac_resolution_id' => $ids['bac_resolutions'],
                    ]
                )
            );

            $ids['purchase_orders'] = $this->mergeIds(
                $ids['purchase_orders'],
                $this->scopedIds(
                    'purchase_orders',
                    $officeId,
                    $officeUserIds,
                    ['office_id', 'requesting_office_id'],
                    [
                        'prepared_by_user_id',
                        'submitted_by_user_id',
                        'issued_by_user_id',
                        'created_by_user_id',
                    ],
                    [
                        'procurement_document_id' => $ids['procurement_documents'],
                        'source_pr_document_id' => $ids['procurement_documents'],
                        'source_bac_resolution_id' => $ids['bac_resolutions'],
                        'source_abstract_id' => $ids['abstracts'],
                    ]
                )
            );

            $ids['inspection_acceptance_records'] = $this->mergeIds(
                $ids['inspection_acceptance_records'],
                $this->scopedIds(
                    'inspection_acceptance_records',
                    $officeId,
                    $officeUserIds,
                    ['office_id'],
                    ['inspected_by_user_id', 'accepted_by_user_id', 'created_by_user_id'],
                    [
                        'purchase_order_id' => $ids['purchase_orders'],
                        'source_pr_document_id' => $ids['procurement_documents'],
                    ]
                )
            );

            $ids['svp_procurement_chains'] = $this->mergeIds(
                $ids['svp_procurement_chains'],
                $this->scopedIds(
                    'svp_procurement_chains',
                    $officeId,
                    $officeUserIds,
                    ['office_id'],
                    ['created_by_user_id', 'updated_by_user_id'],
                    [
                        'source_pr_document_id' => $ids['procurement_documents'],
                        'bac_resolution_id' => $ids['bac_resolutions'],
                        'rfq_id' => $ids['rfqs'],
                        'abstract_id' => $ids['abstracts'],
                        'purchase_order_id' => $ids['purchase_orders'],
                        'inspection_id' => $ids['inspection_acceptance_records'],
                    ]
                )
            );

            $ids['svp_posting_records'] = $this->mergeIds(
                $ids['svp_posting_records'],
                $this->scopedIds(
                    'svp_posting_records',
                    $officeId,
                    $officeUserIds,
                    [],
                    ['created_by_user_id', 'completed_by_user_id'],
                    [
                        'svp_procurement_chain_id' => $ids['svp_procurement_chains'],
                        'source_pr_document_id' => $ids['procurement_documents'],
                    ]
                )
            );

            $ids['svp_procurement_chains'] = $this->mergeIds(
                $ids['svp_procurement_chains'],
                $this->pluckIds('svp_posting_records', 'svp_procurement_chain_id', $ids['svp_posting_records'])
            );

            $ids['svp_chain_events'] = $this->mergeIds(
                $ids['svp_chain_events'],
                $this->scopedIds(
                    'svp_chain_events',
                    $officeId,
                    $officeUserIds,
                    ['from_office_id', 'to_office_id'],
                    ['performed_by_user_id'],
                    ['svp_procurement_chain_id' => $ids['svp_procurement_chains']]
                )
            );

            $ids['signature_requests'] = $this->mergeIds(
                $ids['signature_requests'],
                $this->scopedIds(
                    'signature_requests',
                    $officeId,
                    $officeUserIds,
                    ['requested_to_office_id'],
                    ['requested_by_user_id', 'requested_to_user_id']
                ),
                $this->documentReferenceIds('signature_requests')
            );

            $ids['electronic_signatures'] = $this->mergeIds(
                $ids['electronic_signatures'],
                $this->scopedIds(
                    'electronic_signatures',
                    $officeId,
                    $officeUserIds,
                    ['signer_office_id'],
                    ['signer_user_id'],
                    ['signature_request_id' => $ids['signature_requests']]
                ),
                $this->documentReferenceIds('electronic_signatures')
            );

            $ids['signature_requests'] = $this->mergeIds(
                $ids['signature_requests'],
                $this->pluckIds('electronic_signatures', 'signature_request_id', $ids['electronic_signatures'])
            );

            $ids['signed_document_snapshots'] = $this->mergeIds(
                $ids['signed_document_snapshots'],
                $this->scopedIds(
                    'signed_document_snapshots',
                    $officeId,
                    $officeUserIds,
                    [],
                    ['created_by_user_id'],
                    ['electronic_signature_id' => $ids['electronic_signatures']]
                ),
                $this->documentReferenceIds('signed_document_snapshots')
            );

            $ids['electronic_signatures'] = $this->mergeIds(
                $ids['electronic_signatures'],
                $this->pluckIds('signed_document_snapshots', 'electronic_signature_id', $ids['signed_document_snapshots'])
            );

            $ids['bac_deliberations'] = $this->mergeIds(
                $ids['bac_deliberations'],
                $this->scopedIds(
                    'bac_deliberations',
                    $officeId,
                    $officeUserIds,
                    ['office_id'],
                    ['created_by_user_id', 'chair_user_id'],
                    ['procurement_document_id' => $ids['procurement_documents']]
                )
            );

            $ids['procurement_documents'] = $this->mergeIds(
                $ids['procurement_documents'],
                $this->pluckIds('bac_resolutions', 'source_pr_document_id', $ids['bac_resolutions']),
                $this->pluckIds('bac_resolutions', 'procurement_document_id', $ids['bac_resolutions']),
                $this->pluckIds('supplemental_apps', 'source_pr_document_id', $ids['supplemental_apps']),
                $this->pluckIds('rfqs', 'source_pr_document_id', $ids['rfqs']),
                $this->pluckIds('abstracts', 'source_pr_document_id', $ids['abstracts']),
                $this->pluckIds('purchase_orders', 'procurement_document_id', $ids['purchase_orders']),
                $this->pluckIds('purchase_orders', 'source_pr_document_id', $ids['purchase_orders']),
                $this->pluckIds('svp_procurement_chains', 'source_pr_document_id', $ids['svp_procurement_chains']),
                $this->pluckIds('svp_posting_records', 'source_pr_document_id', $ids['svp_posting_records']),
                $this->pluckIds('bac_deliberations', 'procurement_document_id', $ids['bac_deliberations'])
            );

            $ids['bac_resolutions'] = $this->mergeIds(
                $ids['bac_resolutions'],
                $this->pluckIds('svp_procurement_chains', 'bac_resolution_id', $ids['svp_procurement_chains'])
            );

            $ids['rfqs'] = $this->mergeIds(
                $ids['rfqs'],
                $this->pluckIds('svp_procurement_chains', 'rfq_id', $ids['svp_procurement_chains'])
            );

            $ids['abstracts'] = $this->mergeIds(
                $ids['abstracts'],
                $this->pluckIds('svp_procurement_chains', 'abstract_id', $ids['svp_procurement_chains'])
            );

            $ids['purchase_orders'] = $this->mergeIds(
                $ids['purchase_orders'],
                $this->pluckIds('svp_procurement_chains', 'purchase_order_id', $ids['svp_procurement_chains'])
            );

            $ids['inspection_acceptance_records'] = $this->mergeIds(
                $ids['inspection_acceptance_records'],
                $this->pluckIds('svp_procurement_chains', 'inspection_id', $ids['svp_procurement_chains'])
            );
        }

        return $this->normalizeIds($ids);
    }

    private function buildCounts(): array
    {
        $this->attachmentPaths = $this->attachmentPathsForTrackedRecords();

        return [
            'BAC deliberation comments' => $this->countWhereIn('bac_deliberation_comments', 'bac_deliberation_id', $this->ids['bac_deliberations'] ?? []),
            'BAC deliberation participants' => $this->countWhereIn('bac_deliberation_participants', 'bac_deliberation_id', $this->ids['bac_deliberations'] ?? []),
            'BAC deliberations' => $this->countIds('bac_deliberations', $this->ids['bac_deliberations'] ?? []),
            'SVP posting attachments' => $this->countAttachmentsFor('svp_posting_records'),
            'SVP posting records' => $this->countIds('svp_posting_records', $this->ids['svp_posting_records'] ?? []),
            'SVP chain events' => $this->countIds('svp_chain_events', $this->ids['svp_chain_events'] ?? []),
            'SVP procurement chains' => $this->countIds('svp_procurement_chains', $this->ids['svp_procurement_chains'] ?? []),
            'Signed document snapshots' => $this->countIds('signed_document_snapshots', $this->ids['signed_document_snapshots'] ?? []),
            'Electronic signatures' => $this->countIds('electronic_signatures', $this->ids['electronic_signatures'] ?? []),
            'Signature requests' => $this->countIds('signature_requests', $this->ids['signature_requests'] ?? []),
            'Inspection / acceptance records' => $this->countIds('inspection_acceptance_records', $this->ids['inspection_acceptance_records'] ?? []),
            'Purchase order items' => $this->countWhereIn('purchase_order_items', 'purchase_order_id', $this->ids['purchase_orders'] ?? []),
            'Purchase orders' => $this->countIds('purchase_orders', $this->ids['purchase_orders'] ?? []),
            'Abstract items' => $this->countWhereIn('abstract_items', 'abstract_id', $this->ids['abstracts'] ?? []),
            'Abstracts' => $this->countIds('abstracts', $this->ids['abstracts'] ?? []),
            'RFQ items' => $this->countWhereIn('rfq_items', 'rfq_id', $this->ids['rfqs'] ?? []),
            'RFQs' => $this->countIds('rfqs', $this->ids['rfqs'] ?? []),
            'BAC resolution items' => $this->countWhereIn('bac_resolution_items', 'bac_resolution_id', $this->ids['bac_resolutions'] ?? []),
            'BAC resolutions' => $this->countIds('bac_resolutions', $this->ids['bac_resolutions'] ?? []),
            'Supplemental APP items' => $this->countWhereIn('supplemental_app_items', 'supplemental_app_id', $this->ids['supplemental_apps'] ?? []),
            'Supplemental APPs' => $this->countIds('supplemental_apps', $this->ids['supplemental_apps'] ?? []),
            'APP PPMP sources' => $this->countIds('app_ppmp_sources', $this->ids['app_ppmp_sources'] ?? []),
            'APP items' => $this->countIds('app_items', $this->ids['app_items'] ?? []),
            'APP consolidations' => $this->countIds('app_consolidations', $this->ids['app_consolidations'] ?? []),
            'Purchase request validations' => $this->countWhereIn('purchase_request_validations', 'procurement_document_id', $this->ids['procurement_documents'] ?? []),
            'Purchase request items' => $this->countWhereIn('purchase_request_items', 'procurement_document_id', $this->ids['procurement_documents'] ?? []),
            'PPMP items' => $this->countWhereIn('ppmp_items', 'procurement_document_id', $this->ids['procurement_documents'] ?? []),
            'PPMP reviews' => $this->countWhereIn('ppmp_reviews', 'procurement_document_id', $this->ids['procurement_documents'] ?? []),
            'Budget reviews' => $this->countWhereIn('budget_reviews', 'procurement_document_id', $this->ids['procurement_documents'] ?? []),
            'Accounting reviews' => $this->countWhereIn('accounting_reviews', 'procurement_document_id', $this->ids['procurement_documents'] ?? []),
            'BAC Secretariat reviews' => $this->countWhereIn('bac_secretariat_reviews', 'procurement_document_id', $this->ids['procurement_documents'] ?? []),
            'BAC Member reviews' => $this->countWhereIn('bac_member_reviews', 'procurement_document_id', $this->ids['procurement_documents'] ?? []),
            'BAC Chair reviews' => $this->countWhereIn('bac_chair_reviews', 'procurement_document_id', $this->ids['procurement_documents'] ?? []),
            'Document approvals' => $this->countWhereIn('document_approvals', 'procurement_document_id', $this->ids['procurement_documents'] ?? []),
            'Document attachments' => $this->countWhereIn('document_attachments', 'procurement_document_id', $this->ids['procurement_documents'] ?? []),
            'Routing histories' => $this->countWhereIn('document_routing_histories', 'procurement_document_id', $this->ids['procurement_documents'] ?? []),
            'Notifications' => $this->countRelated('system_notifications', 'related_type', 'related_id'),
            'Related audit logs' => $this->countRelated('audit_logs', 'auditable_type', 'auditable_id'),
            'Procurement documents' => $this->countIds('procurement_documents', $this->ids['procurement_documents'] ?? []),
        ];
    }

    private function deleteRecords(): void
    {
        $this->deleteWhereIn('bac_deliberation_comments', 'bac_deliberation_id', $this->ids['bac_deliberations'] ?? []);
        $this->deleteWhereIn('bac_deliberation_participants', 'bac_deliberation_id', $this->ids['bac_deliberations'] ?? []);
        $this->deleteIds('bac_deliberations', $this->ids['bac_deliberations'] ?? []);

        $this->deleteAttachmentsFor('svp_posting_records');
        $this->deleteIds('svp_chain_events', $this->ids['svp_chain_events'] ?? []);
        $this->deleteIds('svp_posting_records', $this->ids['svp_posting_records'] ?? []);
        $this->deleteIds('svp_procurement_chains', $this->ids['svp_procurement_chains'] ?? []);

        $this->deleteIds('signed_document_snapshots', $this->ids['signed_document_snapshots'] ?? []);
        $this->deleteIds('electronic_signatures', $this->ids['electronic_signatures'] ?? []);
        $this->deleteIds('signature_requests', $this->ids['signature_requests'] ?? []);

        $this->deleteIds('inspection_acceptance_records', $this->ids['inspection_acceptance_records'] ?? []);
        $this->deleteWhereIn('purchase_order_items', 'purchase_order_id', $this->ids['purchase_orders'] ?? []);
        $this->deleteIds('purchase_orders', $this->ids['purchase_orders'] ?? []);

        $this->deleteWhereIn('abstract_items', 'abstract_id', $this->ids['abstracts'] ?? []);
        $this->deleteIds('abstracts', $this->ids['abstracts'] ?? []);

        $this->deleteWhereIn('rfq_items', 'rfq_id', $this->ids['rfqs'] ?? []);
        $this->deleteIds('rfqs', $this->ids['rfqs'] ?? []);

        $this->deleteWhereIn('bac_resolution_items', 'bac_resolution_id', $this->ids['bac_resolutions'] ?? []);
        $this->deleteIds('bac_resolutions', $this->ids['bac_resolutions'] ?? []);

        $this->deleteWhereIn('supplemental_app_items', 'supplemental_app_id', $this->ids['supplemental_apps'] ?? []);
        $this->deleteIds('supplemental_apps', $this->ids['supplemental_apps'] ?? []);

        $this->deleteIds('app_ppmp_sources', $this->ids['app_ppmp_sources'] ?? []);
        $this->deleteIds('app_items', $this->ids['app_items'] ?? []);
        $this->deleteIds('app_consolidations', $this->ids['app_consolidations'] ?? []);

        foreach ([
            'purchase_request_validations',
            'purchase_request_items',
            'ppmp_items',
            'ppmp_reviews',
            'budget_reviews',
            'accounting_reviews',
            'bac_secretariat_reviews',
            'bac_member_reviews',
            'bac_chair_reviews',
            'document_approvals',
            'document_attachments',
            'document_routing_histories',
        ] as $table) {
            $this->deleteWhereIn($table, 'procurement_document_id', $this->ids['procurement_documents'] ?? []);
        }

        $this->deleteRelated('system_notifications', 'related_type', 'related_id');
        $this->deleteRelated('audit_logs', 'auditable_type', 'auditable_id');

        $this->deleteIds('procurement_documents', $this->ids['procurement_documents'] ?? []);
    }

    private function scopedIds(
        string $table,
        ?int $officeId,
        array $userIds,
        array $officeColumns = [],
        array $userColumns = [],
        array $relatedColumns = []
    ): array {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $hasCriteria = false;
        $query = DB::table($table)->where(function ($builder) use (
            $table,
            $officeId,
            $userIds,
            $officeColumns,
            $userColumns,
            $relatedColumns,
            &$hasCriteria
        ): void {
            if ($officeId) {
                foreach ($officeColumns as $column) {
                    if ($this->hasColumn($table, $column)) {
                        $builder->orWhere($column, $officeId);
                        $hasCriteria = true;
                    }
                }
            }

            if ($userIds !== []) {
                foreach ($userColumns as $column) {
                    if ($this->hasColumn($table, $column)) {
                        $builder->orWhereIn($column, $userIds);
                        $hasCriteria = true;
                    }
                }
            }

            foreach ($relatedColumns as $column => $ids) {
                $ids = $this->cleanIds($ids);

                if ($ids !== [] && $this->hasColumn($table, $column)) {
                    $builder->orWhereIn($column, $ids);
                    $hasCriteria = true;
                }
            }
        });

        if (! $hasCriteria) {
            return [];
        }

        return $this->cleanIds($query->pluck('id')->all());
    }

    private function allIds(string $table): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        return $this->cleanIds(DB::table($table)->pluck('id')->all());
    }

    private function pluckIds(string $table, string $column, array $rowIds): array
    {
        $rowIds = $this->cleanIds($rowIds);

        if ($rowIds === [] || ! Schema::hasTable($table) || ! $this->hasColumn($table, $column)) {
            return [];
        }

        return $this->cleanIds(DB::table($table)->whereIn('id', $rowIds)->pluck($column)->all());
    }

    private function documentReferenceIds(string $table): array
    {
        if (! Schema::hasTable($table) || ! $this->hasColumn($table, 'document_type') || ! $this->hasColumn($table, 'document_id')) {
            return [];
        }

        $query = DB::table($table);
        $hasCriteria = false;

        $query->where(function ($builder) use (&$hasCriteria): void {
            foreach (self::SIGNATURE_DOCUMENT_TYPES as $sourceTable => $documentTypes) {
                $ids = $this->cleanIds($this->ids[$sourceTable] ?? []);

                if ($ids === []) {
                    continue;
                }

                $builder->orWhere(function ($nested) use ($documentTypes, $ids): void {
                    $nested->whereIn('document_type', $documentTypes)
                        ->whereIn('document_id', $ids);
                });

                $hasCriteria = true;
            }
        });

        return $hasCriteria ? $this->cleanIds($query->pluck('id')->all()) : [];
    }

    private function countIds(string $table, array $ids): int
    {
        $ids = $this->cleanIds($ids);

        if ($ids === [] || ! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)->whereIn('id', $ids)->count();
    }

    private function countWhereIn(string $table, string $column, array $ids): int
    {
        $ids = $this->cleanIds($ids);

        if ($ids === [] || ! Schema::hasTable($table) || ! $this->hasColumn($table, $column)) {
            return 0;
        }

        return DB::table($table)->whereIn($column, $ids)->count();
    }

    private function countRelated(string $table, string $typeColumn, string $idColumn): int
    {
        if (! Schema::hasTable($table) || ! $this->hasColumn($table, $typeColumn) || ! $this->hasColumn($table, $idColumn)) {
            return 0;
        }

        $query = DB::table($table);
        $hasCriteria = false;

        $query->where(function ($builder) use ($typeColumn, $idColumn, &$hasCriteria): void {
            foreach (self::RELATED_MODEL_TYPES as $sourceTable => $modelType) {
                $ids = $this->cleanIds($this->ids[$sourceTable] ?? []);

                if ($ids === []) {
                    continue;
                }

                $builder->orWhere(function ($nested) use ($typeColumn, $idColumn, $modelType, $sourceTable, $ids): void {
                    $nested->whereIn($typeColumn, [$modelType, $sourceTable])
                        ->whereIn($idColumn, $ids);
                });

                $hasCriteria = true;
            }
        });

        return $hasCriteria ? $query->count() : 0;
    }

    private function countAttachmentsFor(string $sourceTable): int
    {
        $modelType = self::RELATED_MODEL_TYPES[$sourceTable] ?? null;
        $ids = $this->cleanIds($this->ids[$sourceTable] ?? []);

        if (! $modelType || $ids === [] || ! $this->hasAttachmentMorphColumns()) {
            return 0;
        }

        return DB::table('document_attachments')
            ->whereIn('attachable_type', [$modelType, $sourceTable])
            ->whereIn('attachable_id', $ids)
            ->count();
    }

    private function deleteIds(string $table, array $ids): int
    {
        $ids = $this->cleanIds($ids);

        if ($ids === [] || ! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)->whereIn('id', $ids)->delete();
    }

    private function deleteWhereIn(string $table, string $column, array $ids): int
    {
        $ids = $this->cleanIds($ids);

        if ($ids === [] || ! Schema::hasTable($table) || ! $this->hasColumn($table, $column)) {
            return 0;
        }

        return DB::table($table)->whereIn($column, $ids)->delete();
    }

    private function deleteRelated(string $table, string $typeColumn, string $idColumn): int
    {
        if (! Schema::hasTable($table) || ! $this->hasColumn($table, $typeColumn) || ! $this->hasColumn($table, $idColumn)) {
            return 0;
        }

        $query = DB::table($table);
        $hasCriteria = false;

        $query->where(function ($builder) use ($typeColumn, $idColumn, &$hasCriteria): void {
            foreach (self::RELATED_MODEL_TYPES as $sourceTable => $modelType) {
                $ids = $this->cleanIds($this->ids[$sourceTable] ?? []);

                if ($ids === []) {
                    continue;
                }

                $builder->orWhere(function ($nested) use ($typeColumn, $idColumn, $modelType, $sourceTable, $ids): void {
                    $nested->whereIn($typeColumn, [$modelType, $sourceTable])
                        ->whereIn($idColumn, $ids);
                });

                $hasCriteria = true;
            }
        });

        return $hasCriteria ? $query->delete() : 0;
    }

    private function deleteAttachmentsFor(string $sourceTable): int
    {
        $modelType = self::RELATED_MODEL_TYPES[$sourceTable] ?? null;
        $ids = $this->cleanIds($this->ids[$sourceTable] ?? []);

        if (! $modelType || $ids === [] || ! $this->hasAttachmentMorphColumns()) {
            return 0;
        }

        return DB::table('document_attachments')
            ->whereIn('attachable_type', [$modelType, $sourceTable])
            ->whereIn('attachable_id', $ids)
            ->delete();
    }

    private function attachmentPathsForTrackedRecords(): array
    {
        if (! Schema::hasTable('document_attachments') || ! $this->hasColumn('document_attachments', 'file_path')) {
            return [];
        }

        $query = DB::table('document_attachments');
        $hasCriteria = false;

        $query->where(function ($builder) use (&$hasCriteria): void {
            $documentIds = $this->cleanIds($this->ids['procurement_documents'] ?? []);

            if ($documentIds !== [] && $this->hasColumn('document_attachments', 'procurement_document_id')) {
                $builder->orWhereIn('procurement_document_id', $documentIds);
                $hasCriteria = true;
            }

            if (! $this->hasAttachmentMorphColumns()) {
                return;
            }

            foreach (self::RELATED_MODEL_TYPES as $sourceTable => $modelType) {
                $ids = $this->cleanIds($this->ids[$sourceTable] ?? []);

                if ($ids === []) {
                    continue;
                }

                $builder->orWhere(function ($nested) use ($modelType, $sourceTable, $ids): void {
                    $nested->whereIn('attachable_type', [$modelType, $sourceTable])
                        ->whereIn('attachable_id', $ids);
                });

                $hasCriteria = true;
            }
        });

        if (! $hasCriteria) {
            return [];
        }

        return $query
            ->pluck('file_path')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function deleteAttachmentFiles(): int
    {
        $paths = array_values(array_unique(array_filter($this->attachmentPaths)));

        if ($paths === []) {
            return 0;
        }

        $deleted = 0;

        foreach ($paths as $path) {
            if (Storage::disk('public')->exists($path) && Storage::disk('public')->delete($path)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function countRows(array $counts): array
    {
        return collect($counts)
            ->filter(fn (int $count): bool => $count > 0)
            ->map(fn (int $count, string $label): array => [$label, $count])
            ->values()
            ->all();
    }

    private function normalizeIds(array $ids): array
    {
        foreach ($ids as $table => $tableIds) {
            $ids[$table] = $this->cleanIds($tableIds);
        }

        return $ids;
    }

    private function mergeIds(array ...$sets): array
    {
        return $this->cleanIds(array_merge(...$sets));
    }

    private function cleanIds(array $ids): array
    {
        return collect($ids)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function hasColumn(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }

    private function hasAttachmentMorphColumns(): bool
    {
        return Schema::hasTable('document_attachments')
            && $this->hasColumn('document_attachments', 'attachable_type')
            && $this->hasColumn('document_attachments', 'attachable_id');
    }
}
