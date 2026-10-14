<?php

namespace App\Observers;

use App\Models\CompetitiveBiddingSignatureDocument;
use App\Services\CompetitiveBiddingDocumentHandoffService;

class CompetitiveBiddingSignatureDocumentObserver
{
    public function updated(CompetitiveBiddingSignatureDocument $document): void
    {
        if (! $document->wasChanged('status')) {
            return;
        }

        if (! in_array($document->status, [
            CompetitiveBiddingSignatureDocument::STATUS_FULLY_SIGNED,
            CompetitiveBiddingSignatureDocument::STATUS_RETURNED,
        ], true)) {
            return;
        }

        app(CompetitiveBiddingDocumentHandoffService::class)->syncSignatureDocument($document);
    }
}
