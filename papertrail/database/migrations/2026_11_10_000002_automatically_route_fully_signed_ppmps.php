<?php

use App\Models\ProcurementDocument;
use App\Services\HeadOfficePpmpSignatureWorkflowService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ProcurementDocument::where('document_type', 'PPMP')
            ->where('status', ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED)
            ->with(['preparedBy', 'submittedBy'])->chunkById(100, function ($documents): void {
                foreach ($documents as $document) {
                    $actor = $document->preparedBy ?? $document->submittedBy;
                    if ($actor) {
                        app(HeadOfficePpmpSignatureWorkflowService::class)->routeSignedToBac($document, $actor);
                    }
                }
            });
    }

    public function down(): void
    {
        // Keep completed approvals, submission dates, and routing history intact.
    }
};
