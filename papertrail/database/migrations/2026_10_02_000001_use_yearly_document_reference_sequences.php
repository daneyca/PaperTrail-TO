<?php

use App\Services\DocumentReferenceNumberService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $referenceTables = [
        'procurement_documents' => [
            'has_document_type' => true,
            'model' => \App\Models\ProcurementDocument::class,
        ],
        'annual_procurement_plans' => [
            'type' => DocumentReferenceNumberService::TYPE_APP,
            'model' => \App\Models\AnnualProcurementPlan::class,
        ],
        'app_consolidations' => [
            'type' => DocumentReferenceNumberService::TYPE_APP,
            'model' => \App\Models\AppConsolidation::class,
        ],
        'bac_resolutions' => [
            'type' => DocumentReferenceNumberService::TYPE_BAC,
            'model' => \App\Models\BacResolution::class,
        ],
        'rfqs' => [
            'type' => DocumentReferenceNumberService::TYPE_RFQ,
            'model' => \App\Models\Rfq::class,
        ],
        'abstracts' => [
            'type' => DocumentReferenceNumberService::TYPE_AOQ,
            'model' => \App\Models\AbstractQuotation::class,
        ],
        'purchase_orders' => [
            'type' => DocumentReferenceNumberService::TYPE_PO,
            'model' => \App\Models\PurchaseOrder::class,
        ],
        'inspection_acceptance_records' => [
            'type' => DocumentReferenceNumberService::TYPE_IA,
            'model' => \App\Models\InspectionAcceptanceRecord::class,
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('document_reference_sequences')) {
            return;
        }

        $result = $this->renumberExistingReferences();

        $this->updateAuditLogReferences($result['audit_updates']);
        $this->rebuildSequenceRows($result['sequence_summary']);
        $this->dropIndex('document_reference_sequences', 'doc_ref_sequence_unique', true);
        $this->addYearlyUniqueIndex();
    }

    public function down(): void
    {
        if (! Schema::hasTable('document_reference_sequences')) {
            return;
        }

        $this->dropIndex('document_reference_sequences', 'doc_ref_sequence_unique', true);

        Schema::table('document_reference_sequences', function (Blueprint $table): void {
            $table->unique(['document_type', 'created_year', 'created_month'], 'doc_ref_sequence_unique');
        });
    }

    private function renumberExistingReferences(): array
    {
        $groups = [];

        foreach ($this->referenceTables as $tableName => $config) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'document_reference_number')) {
                continue;
            }

            $columns = ['id', 'document_reference_number'];

            foreach (['document_type', 'created_at', 'created_year', 'created_month'] as $column) {
                if (Schema::hasColumn($tableName, $column)) {
                    $columns[] = $column;
                }
            }

            DB::table($tableName)
                ->select($columns)
                ->whereNotNull('document_reference_number')
                ->orderBy('id')
                ->get()
                ->each(function (object $record) use (&$groups, $tableName, $config): void {
                    [$year, $month, $timestamp] = $this->referenceDateParts($record);
                    $type = $this->typeForRecord($record, $config);
                    $key = "{$type}:{$year}";

                    $groups[$key][] = [
                        'table' => $tableName,
                        'id' => $record->id,
                        'old_reference' => (string) $record->document_reference_number,
                        'type' => $type,
                        'year' => $year,
                        'month' => $month,
                        'timestamp' => $timestamp,
                        'sets_document_type' => ! ($config['has_document_type'] ?? false)
                            && Schema::hasColumn($tableName, 'document_type'),
                        'model' => $config['model'] ?? null,
                    ];
                });
        }

        $auditUpdates = [];
        $sequenceSummary = [];

        foreach ($groups as $records) {
            usort($records, function (array $left, array $right): int {
                return ($left['timestamp'] <=> $right['timestamp'])
                    ?: strcmp($left['table'], $right['table'])
                    ?: ((int) $left['id'] <=> (int) $right['id']);
            });

            foreach ($records as $record) {
                DB::table($record['table'])
                    ->where('id', $record['id'])
                    ->update([
                        'document_reference_number' => $this->temporaryReference($record['table'], $record['id']),
                    ]);
            }

            foreach (array_values($records) as $index => $record) {
                $sequence = $index + 1;
                $reference = sprintf(
                    '%04d-%02d-%s-%04d',
                    $record['year'],
                    $record['month'],
                    $record['type'],
                    $sequence,
                );

                $update = [
                    'document_reference_number' => $reference,
                    'sequence_number' => $sequence,
                    'created_year' => $record['year'],
                    'created_month' => $record['month'],
                ];

                if ($record['sets_document_type']) {
                    $update['document_type'] = $record['type'];
                }

                DB::table($record['table'])
                    ->where('id', $record['id'])
                    ->update($update);

                $summaryKey = "{$record['type']}:{$record['year']}";
                $sequenceSummary[$summaryKey] = [
                    'type' => $record['type'],
                    'year' => $record['year'],
                    'month' => $record['month'],
                    'last_sequence' => max($sequence, $sequenceSummary[$summaryKey]['last_sequence'] ?? 0),
                ];

                if ($record['old_reference'] !== $reference) {
                    $auditUpdates[] = [
                        'old' => $record['old_reference'],
                        'new' => $reference,
                        'document_id' => $record['id'],
                        'model' => $record['model'],
                    ];
                }
            }
        }

        return [
            'audit_updates' => $auditUpdates,
            'sequence_summary' => $sequenceSummary,
        ];
    }

    private function rebuildSequenceRows(array $sequenceSummary): void
    {
        DB::table('document_reference_sequences')
            ->get()
            ->each(function (object $row) use (&$sequenceSummary): void {
                $type = DocumentReferenceNumberService::normalizeType($row->document_type ?? 'DOC');
                $year = (int) ($row->created_year ?? now()->year);
                $key = "{$type}:{$year}";

                $sequenceSummary[$key] = [
                    'type' => $type,
                    'year' => $year,
                    'month' => (int) ($row->created_month ?? ($sequenceSummary[$key]['month'] ?? 1)),
                    'last_sequence' => max(
                        (int) ($row->last_sequence ?? 0),
                        (int) ($sequenceSummary[$key]['last_sequence'] ?? 0),
                    ),
                ];
            });

        DB::table('document_reference_sequences')->delete();

        foreach ($sequenceSummary as $summary) {
            DB::table('document_reference_sequences')->insert([
                'document_type' => $summary['type'],
                'created_year' => $summary['year'],
                'created_month' => max(1, min(12, (int) $summary['month'])),
                'last_sequence' => (int) $summary['last_sequence'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function updateAuditLogReferences(array $updates): void
    {
        if (! $updates || ! Schema::hasTable('audit_logs')) {
            return;
        }

        $oldReferenceCounts = array_count_values(array_column($updates, 'old'));

        foreach ($updates as $update) {
            foreach (['document_reference_number', 'tracking_number'] as $column) {
                if (! Schema::hasColumn('audit_logs', $column)) {
                    continue;
                }

                $matched = $this->updateAuditLogColumn($column, $update, true);

                if (! $matched && ($oldReferenceCounts[$update['old']] ?? 0) === 1) {
                    $this->updateAuditLogColumn($column, $update, false);
                }
            }
        }
    }

    private function updateAuditLogColumn(string $column, array $update, bool $withIdentityScope): int
    {
        $query = DB::table('audit_logs')->where($column, $update['old']);

        if ($withIdentityScope && ! $this->canScopeAuditUpdate($update)) {
            return 0;
        }

        if ($withIdentityScope) {
            $query->where(function ($identity) use ($update): void {
                if (! $update['model'] && Schema::hasColumn('audit_logs', 'document_id')) {
                    $identity->orWhere('document_id', $update['document_id']);
                }

                if ($update['model'] && Schema::hasColumn('audit_logs', 'auditable_type') && Schema::hasColumn('audit_logs', 'auditable_id')) {
                    $identity->orWhere(function ($nested) use ($update): void {
                        $nested->where('auditable_type', $update['model'])
                            ->where('auditable_id', $update['document_id']);
                    });
                }

                if ($update['model'] && Schema::hasColumn('audit_logs', 'target_type') && Schema::hasColumn('audit_logs', 'target_id')) {
                    $identity->orWhere(function ($nested) use ($update): void {
                        $nested->where('target_type', $update['model'])
                            ->where('target_id', $update['document_id']);
                    });
                }
            });
        }

        return $query->update([$column => $update['new']]);
    }

    private function canScopeAuditUpdate(array $update): bool
    {
        if (! $update['model']) {
            return Schema::hasColumn('audit_logs', 'document_id');
        }

        return (Schema::hasColumn('audit_logs', 'auditable_type') && Schema::hasColumn('audit_logs', 'auditable_id'))
            || (Schema::hasColumn('audit_logs', 'target_type') && Schema::hasColumn('audit_logs', 'target_id'));
    }

    private function referenceDateParts(object $record): array
    {
        if (filled($record->created_at ?? null)) {
            $date = Carbon::parse($record->created_at);

            return [(int) $date->format('Y'), (int) $date->format('m'), $date->timestamp];
        }

        if (filled($record->created_year ?? null) && filled($record->created_month ?? null)) {
            $year = (int) $record->created_year;
            $month = (int) $record->created_month;

            return [$year, $month, Carbon::create($year, $month, 1)->timestamp];
        }

        if (preg_match('/^(\d{4})-(\d{2})-/', (string) ($record->document_reference_number ?? ''), $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];

            return [$year, $month, Carbon::create($year, $month, 1)->timestamp];
        }

        $date = now();

        return [(int) $date->format('Y'), (int) $date->format('m'), $date->timestamp];
    }

    private function typeForRecord(object $record, array $config): string
    {
        return DocumentReferenceNumberService::normalizeType(
            $config['type'] ?? ($record->document_type ?? 'DOC'),
        );
    }

    private function temporaryReference(string $tableName, int|string $id): string
    {
        return 'TMP-' . substr(md5($tableName . ':' . $id), 0, 27);
    }

    private function addYearlyUniqueIndex(): void
    {
        if ($this->indexExists('document_reference_sequences', 'doc_ref_sequence_unique')) {
            return;
        }

        Schema::table('document_reference_sequences', function (Blueprint $table): void {
            $table->unique(['document_type', 'created_year'], 'doc_ref_sequence_unique');
        });
    }

    private function dropIndex(string $table, string $index, bool $unique = false): void
    {
        if (! $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($index, $unique): void {
            $unique ? $blueprint->dropUnique($index) : $blueprint->dropIndex($index);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            return collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]))->isNotEmpty();
        }

        if ($driver === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(fn ($item) => ($item->name ?? null) === $index);
        }

        return false;
    }
};
