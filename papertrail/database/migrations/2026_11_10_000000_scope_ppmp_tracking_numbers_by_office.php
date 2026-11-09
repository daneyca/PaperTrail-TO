<?php

use App\Models\ProcurementDocument;
use App\Services\AuditLogger;
use App\Services\DocumentReferenceNumberService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ppmp_reference_sequences')) {
            Schema::create('ppmp_reference_sequences', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('office_id');
                $table->unsignedSmallInteger('created_year');
                $table->unsignedInteger('last_sequence')->default(0);
                $table->timestamps();
                $table->unique(['office_id', 'created_year'], 'ppmp_office_year_unique');
            });
        }

        foreach (['procurement_documents', 'audit_logs'] as $table) {
            $type = Schema::getColumnType($table, 'document_reference_number', true);
            if (preg_match('/char\((\d+)\)/i', $type, $length) && (int) $length[1] < 128) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->string('document_reference_number', 128)->nullable()->change());
            }
        }

        $documents = ProcurementDocument::query()->where('document_type', 'PPMP')
            ->where('submitting_office_id', '>', 0)->orderBy('created_at')->orderBy('id')->get();

        // Seed counters first so rerunning the migration preserves office numbers already issued.
        foreach ($documents as $document) {
            if (!preg_match('/^(\d{4})-\d{2}-PPMP-.+-(\d{4,})$/', (string) $document->document_reference_number, $matches)) {
                continue;
            }
            DB::table('ppmp_reference_sequences')->insertOrIgnore([
                'office_id' => $document->submitting_office_id, 'created_year' => (int) $matches[1],
                'last_sequence' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('ppmp_reference_sequences')->where('office_id', $document->submitting_office_id)
                ->where('created_year', (int) $matches[1])->where('last_sequence', '<', (int) $matches[2])
                ->update(['last_sequence' => (int) $matches[2]]);
        }

        foreach ($documents as $record) {
            DB::transaction(function () use ($record): void {
                $document = ProcurementDocument::query()->lockForUpdate()->find($record->id);
                if (!$document || preg_match('/^\d{4}-\d{2}-PPMP-.+-\d{4,}$/', (string) $document->document_reference_number)) {
                    return;
                }
                $old = $document->only(['document_reference_number', 'tracking_number', 'ppmp_no', 'sequence_number']);
                $oldReferences = array_filter([$old['document_reference_number'], $document->getRawOriginal('tracking_number')]);
                $document->forceFill(['document_reference_number' => null, 'tracking_number' => null]);
                app(DocumentReferenceNumberService::class)->assign($document, DocumentReferenceNumberService::TYPE_PPMP);
                if (blank($document->ppmp_no) || in_array($document->ppmp_no, $oldReferences, true)) {
                    $document->ppmp_no = $document->document_reference_number;
                }
                DB::table('procurement_documents')->where('id', $document->id)->update($document->only([
                    'document_reference_number', 'tracking_number', 'ppmp_no', 'sequence_number', 'created_year', 'created_month',
                ]));
                DB::table('signature_requests')->where('document_type', 'ppmp')->where('document_id', $document->id)
                    ->update(['tracking_number' => $document->document_reference_number]);

                // Signed snapshots keep their original content and hashes; the audit trail maps the old reference.
                AuditLogger::log('PPMP Numbering', 'PPMP Reference Updated', 'PPMP tracking number changed to the office yearly sequence.',
                    $document, $old, $document->only(['document_reference_number', 'tracking_number', 'ppmp_no', 'sequence_number']));
            });
        }
    }

    public function down(): void
    {
        // Keep issued references and counters to prevent number reuse after a rollback.
    }
};
