<?php

namespace App\Services\Templates;

use App\Models\User;
use InvalidArgumentException;

class TemplateGeneratorService
{
    public const SUPPORTED_TYPES = ['ppmp', 'pr'];

    public function __construct(
        private PpmpTemplateGenerator $ppmpGenerator,
        private PurchaseRequestTemplateGenerator $purchaseRequestGenerator,
    ) {
    }

    public function canGenerate(string $documentType): bool
    {
        return in_array($documentType, self::SUPPORTED_TYPES, true);
    }

    public function generate(string $documentType, ?User $user = null): array
    {
        return match ($documentType) {
            'ppmp' => $this->ppmpGenerator->generate($user),
            'pr' => $this->purchaseRequestGenerator->generate($user),
            default => throw new InvalidArgumentException('Template generation is not implemented for this document type.'),
        };
    }
}
