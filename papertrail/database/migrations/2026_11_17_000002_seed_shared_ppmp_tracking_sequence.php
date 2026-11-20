<?php

use App\Services\DocumentReferenceNumberService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $highest = [];
            foreach (DB::table('ppmp_reference_sequences')->get() as $counter) {
                $year = (int) $counter->created_year;
                $highest[$year] = max($highest[$year] ?? 0, (int) $counter->last_sequence);
            }

            $documents = DB::table('procurement_documents')
                ->where('document_type', 'PPMP')->whereNotNull('created_year')
                ->selectRaw('created_year, MAX(sequence_number) AS last_sequence')
                ->groupBy('created_year')->get();
            foreach ($documents as $document) {
                $year = (int) $document->created_year;
                $highest[$year] = max($highest[$year] ?? 0, (int) $document->last_sequence);
            }

            foreach ($highest as $year => $last) {
                DB::table('document_reference_sequences')->insertOrIgnore([
                    'document_type' => DocumentReferenceNumberService::TYPE_PPMP,
                    'created_year' => $year, 'created_month' => 1, 'last_sequence' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('document_reference_sequences')
                    ->where('document_type', DocumentReferenceNumberService::TYPE_PPMP)
                    ->where('created_year', $year)->where('last_sequence', '<', $last)
                    ->update(['last_sequence' => $last]);
            }
        });
    }

    public function down(): void
    {
        // Keep issued numbers and counter history to prevent number reuse.
    }
};
