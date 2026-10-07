<?php

namespace App\Services\Templates;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

class PurchaseRequestTemplateGenerator
{
    public function __construct(private SimpleXlsxBuilder $builder)
    {
    }

    public function generate(?User $user = null): array
    {
        $path = 'generated-templates/pr/pr_editable_template_' . now()->format('YmdHis') . '.xlsx';
        $office = $user?->assignedOffice?->name ?? $user?->office ?? '';
        $rows = [
            ['PURCHASE REQUEST EDITABLE TEMPLATE'],
            ['Fill this template and upload it back to PaperTrail. Uploaded templates are imported as Draft only.'],
            [],
            ['PR No.', 'Optional / system-generated'],
            ['Office', $office],
            ['Purpose', ''],
            ['Date', now()->format('Y-m-d')],
            ['Requested By', $user?->name ?? ''],
            [],
            ['Item No.', 'Unit', 'Item Description', 'Quantity', 'Unit Cost', 'Total Cost', 'Remarks'],
        ];

        for ($i = 1; $i <= 24; $i++) {
            $rows[] = [$i, '', '', '', '', '', ''];
        }

        $this->builder->save(Storage::disk('local')->path($path), 'PR Template', $rows, [12, 16, 42, 14, 16, 16, 24]);

        return [
            'path' => $path,
            'filename' => 'papertrail-pr-editable-template.xlsx',
            'format' => 'xlsx',
        ];
    }
}
