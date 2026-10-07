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
        'procurement_documents' => ['after' => 'tracking_number', 'has_document_type' => true],
        'annual_procurement_plans' => ['after' => 'app_number', 'type' => DocumentReferenceNumberService::TYPE_APP],
        'app_consolidations' => ['after' => 'app_number', 'type' => DocumentReferenceNumberService::TYPE_APP],
        'bac_resolutions' => ['after' => 'resolution_number', 'type' => DocumentReferenceNumberService::TYPE_BAC],
        'rfqs' => ['after' => 'rfq_number', 'type' => DocumentReferenceNumberService::TYPE_RFQ],
        'abstracts' => ['after' => 'abstract_number', 'type' => DocumentReferenceNumberService::TYPE_AOQ],
        'purchase_orders' => ['after' => 'po_number', 'type' => DocumentReferenceNumberService::TYPE_PO],
        'inspection_acceptance_records' => ['after' => 'purchase_order_id', 'type' => DocumentReferenceNumberService::TYPE_IA],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('document_reference_sequences')) {
            Schema::create('document_reference_sequences', function (Blueprint $table): void {
                $table->id();
                $table->string('document_type', 20);
                $table->unsignedSmallInteger('created_year');
                $table->unsignedTinyInteger('created_month');
                $table->unsignedInteger('last_sequence')->default(0);
                $table->timestamps();

                $table->unique(['document_type', 'created_year'], 'doc_ref_sequence_unique');
            });
        }

        foreach ($this->referenceTables as $tableName => $config) {
            $this->addReferenceColumns($tableName, $config);
        }

        $this->backfillReferences();
    }

    public function down(): void
    {
        foreach (array_keys($this->referenceTables) as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                foreach (['document_reference_number', 'sequence_number', 'created_year', 'created_month'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }

                if ($tableName !== 'procurement_documents' && Schema::hasColumn($tableName, 'document_type')) {
                    $table->dropColumn('document_type');
                }
            });
        }

        Schema::dropIfExists('document_reference_sequences');
    }

    private function addReferenceColumns(string $tableName, array $config): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName, $config): void {
            $after = $config['after'] ?? null;

            if (! Schema::hasColumn($tableName, 'document_reference_number')) {
                $column = $table->string('document_reference_number', 32)->nullable()->unique();
                if ($after) {
                    $column->after($after);
                }
            }

            if (! ($config['has_document_type'] ?? false) && ! Schema::hasColumn($tableName, 'document_type')) {
                $column = $table->string('document_type', 40)->nullable()->index();
                if (Schema::hasColumn($tableName, 'document_reference_number')) {
                    $column->after('document_reference_number');
                }
            }

            if (! Schema::hasColumn($tableName, 'sequence_number')) {
                $table->unsignedInteger('sequence_number')->nullable()->index()->after('document_reference_number');
            }

            if (! Schema::hasColumn($tableName, 'created_year')) {
                $table->unsignedSmallInteger('created_year')->nullable()->index()->after('sequence_number');
            }

            if (! Schema::hasColumn($tableName, 'created_month')) {
                $table->unsignedTinyInteger('created_month')->nullable()->index()->after('created_year');
            }
        });
    }

    private function backfillReferences(): void
    {
        foreach ($this->referenceTables as $tableName => $config) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'document_reference_number')) {
                continue;
            }

            DB::table($tableName)
                ->whereNull('document_reference_number')
                ->orderBy('created_at')
                ->orderBy('id')
                ->chunkById(100, function ($records) use ($tableName, $config): void {
                    foreach ($records as $record) {
                        $type = $config['type'] ?? DocumentReferenceNumberService::normalizeType($record->document_type ?? 'DOC');
                        $date = filled($record->created_at ?? null) ? Carbon::parse($record->created_at) : now();
                        $year = (int) $date->format('Y');
                        $month = (int) $date->format('m');
                        $sequence = $this->nextSequence($type, $year, $month);

                        DB::table($tableName)
                            ->where('id', $record->id)
                            ->update(array_filter([
                                'document_reference_number' => sprintf('%04d-%02d-%s-%04d', $year, $month, $type, $sequence),
                                'document_type' => ($config['has_document_type'] ?? false) ? null : $type,
                                'sequence_number' => $sequence,
                                'created_year' => $year,
                                'created_month' => $month,
                            ], fn ($value) => $value !== null));
                    }
                });
        }
    }

    private function nextSequence(string $type, int $year, int $month): int
    {
        $sequences = DB::table('document_reference_sequences')
            ->where('document_type', $type)
            ->where('created_year', $year)
            ->lockForUpdate()
            ->orderBy('id')
            ->get();

        if ($sequences->isEmpty()) {
            DB::table('document_reference_sequences')->insert([
                'document_type' => $type,
                'created_year' => $year,
                'created_month' => $month,
                'last_sequence' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return 1;
        }

        $sequence = $sequences->first();
        $next = ((int) $sequences->max('last_sequence')) + 1;

        if ($sequences->count() > 1) {
            DB::table('document_reference_sequences')
                ->whereIn('id', $sequences->skip(1)->pluck('id')->all())
                ->delete();
        }

        DB::table('document_reference_sequences')
            ->where('id', $sequence->id)
            ->update([
                'created_month' => $month,
                'last_sequence' => $next,
                'updated_at' => now(),
            ]);

        return $next;
    }
};
