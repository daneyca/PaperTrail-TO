<?php

namespace App\Models\Concerns;

use App\Services\DocumentReferenceNumberService;

trait HasDocumentReferenceNumber
{
    protected static function bootHasDocumentReferenceNumber(): void
    {
        static::creating(function ($document): void {
            if (
                method_exists($document, 'shouldAssignPaperTrailReferenceOnCreate')
                && ! $document->shouldAssignPaperTrailReferenceOnCreate()
            ) {
                return;
            }

            app(DocumentReferenceNumberService::class)->assign(
                $document,
                $document->paperTrailReferenceType(),
            );
        });
    }

    public function paperTrailReferenceType(): string
    {
        return property_exists(static::class, 'documentReferenceType')
            ? static::$documentReferenceType
            : 'DOC';
    }

    public function getPapertrailReferenceNumberAttribute(): ?string
    {
        return $this->getAttribute('document_reference_number');
    }
}
