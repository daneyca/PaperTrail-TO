<?php

use App\Models\ProcurementDocument;
use App\Services\DocumentReferenceNumberService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        ProcurementDocument::query()
            ->where('document_type', 'PPMP')
            ->where(fn ($query) => $query->whereNull('document_reference_number')->orWhere('document_reference_number', ''))
            ->where(fn ($query) => $query->whereNull('tracking_number')->orWhere('tracking_number', ''))
            ->chunkById(100, function ($documents): void {
                foreach ($documents as $record) {
                    DB::transaction(function () use ($record): void {
                        $document = ProcurementDocument::query()->lockForUpdate()->find($record->id);
                        if (!$document || filled($document->document_reference_number) || filled($document->getRawOriginal('tracking_number'))) {
                            return;
                        }

                        app(DocumentReferenceNumberService::class)->assign($document, DocumentReferenceNumberService::TYPE_PPMP);

                        DB::table('procurement_documents')->where('id', $document->id)->update($document->only([
                            'document_reference_number', 'tracking_number', 'sequence_number', 'created_year', 'created_month',
                        ]));

                        DB::table('signature_requests')->where('document_type', 'ppmp')->where('document_id', $document->id)
                            ->where(fn ($query) => $query->whereNull('tracking_number')->orWhereIn('tracking_number', [
                                '', 'Document #' . $document->id, 'PPMP #' . $document->id,
                            ]))
                            ->update(['tracking_number' => $document->document_reference_number]);
                    });
                }
            });
    }

    public function down(): void
    {
        // Assigned reference numbers remain stable when rolling back application code.
    }
};
