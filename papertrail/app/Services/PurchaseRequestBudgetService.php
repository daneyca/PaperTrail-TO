<?php

namespace App\Services;

use App\Models\AppItem;
use App\Models\ProcurementDocument;

class PurchaseRequestBudgetService
{
    public function info(?AppItem $appItem, ?ProcurementDocument $excludingDocument = null): ?array
    {
        if (! $appItem) {
            return null;
        }

        $approved = $this->budgetForAppItem($appItem);
        $used = $this->usedBudgetForAppItem($appItem, $excludingDocument);

        return [
            'approved' => $approved,
            'used' => $used,
            'remaining' => max($approved - $used, 0),
            'project_title' => $appItem->general_description ?: 'Selected APP project',
        ];
    }

    public function submissionErrorForItems(?AppItem $appItem, array $items, ?ProcurementDocument $excludingDocument = null): ?string
    {
        return $this->submissionError($appItem, $this->totalFromItems($items), $excludingDocument);
    }

    public function submissionErrorForDocument(ProcurementDocument $document): ?string
    {
        $document->loadMissing(['appItem', 'purchaseRequestItems']);

        $appItem = $document->appItem;

        if (! $appItem && $document->purchaseRequestItems->isNotEmpty()) {
            $appItemId = $document->purchaseRequestItems
                ->pluck('app_item_id')
                ->first(fn ($id) => filled($id));
            $appItem = filled($appItemId) ? AppItem::find($appItemId) : null;
        }

        return $this->submissionError($appItem, (float) $document->total_amount, $document);
    }

    public function totalFromItems(array $items): float
    {
        return collect($items)->sum(function ($item): float {
            if (! is_array($item)) {
                return 0;
            }

            $quantity = max((float) ($item['quantity'] ?? 0), 0);
            $unitCost = max((float) ($item['estimated_unit_cost'] ?? 0), 0);

            return round($quantity * $unitCost, 2);
        });
    }

    private function submissionError(?AppItem $appItem, float $requestedTotal, ?ProcurementDocument $excludingDocument = null): ?string
    {
        if (! $appItem) {
            return null;
        }

        $approved = $this->budgetForAppItem($appItem);

        if ($approved <= 0) {
            return null;
        }

        $used = $this->usedBudgetForAppItem($appItem, $excludingDocument);
        $remaining = max($approved - $used, 0);

        if ($requestedTotal <= $remaining + 0.009) {
            return null;
        }

        $projectTitle = $appItem->general_description ?: 'selected APP project';

        return sprintf(
            'The PR total (PHP %s) exceeds the remaining APP/PPMP budget (PHP %s) for %s. Approved budget: PHP %s. Already used by submitted PRs: PHP %s.',
            number_format($requestedTotal, 2),
            number_format($remaining, 2),
            $projectTitle,
            number_format($approved, 2),
            number_format($used, 2),
        );
    }

    private function budgetForAppItem(AppItem $appItem): float
    {
        $total = (float) ($appItem->estimated_total_cost ?? 0);

        if ($total > 0) {
            return round($total, 2);
        }

        return round(max((float) ($appItem->quantity ?? 0), 0) * max((float) ($appItem->estimated_unit_cost ?? 0), 0), 2);
    }

    private function usedBudgetForAppItem(AppItem $appItem, ?ProcurementDocument $excludingDocument = null): float
    {
        return (float) ProcurementDocument::query()
            ->whereIn('document_type', ['PR', 'Purchase Request'])
            ->where('status', '!=', ProcurementDocument::STATUS_PR_DRAFT)
            ->when($excludingDocument?->exists, fn ($query) => $query->whereKeyNot($excludingDocument->id))
            ->where(function ($query) use ($appItem): void {
                $query->where('app_item_id', $appItem->id)
                    ->orWhereHas('purchaseRequestItems', fn ($items) => $items->where('app_item_id', $appItem->id));
            })
            ->sum('total_amount');
    }
}
