<?php

namespace App\Services;

use App\Models\AbstractQuotation;
use App\Models\PurchaseOrder;
use App\Models\RfqSupplierQuotation;

class PurchaseOrderSupplierService
{
    public function quotation(?AbstractQuotation $abstract, ?string $supplierName = null): ?RfqSupplierQuotation
    {
        $name = $supplierName ?: $abstract?->lowest_supplier_name;
        if (! $abstract?->source_rfq_id || blank($name) || ! $abstract->sourceRfq) {
            return null;
        }

        // Match the selected supplier, never the first invitation or first response.
        $matches = app(RfqSupplierWorkflowService::class)->verified($abstract->sourceRfq)
            ->filter(fn ($quote) => $this->normalize($quote->response_snapshot['supplier_name'] ?? '') === $this->normalize($name));

        return $matches->count() === 1 ? $matches->first() : null;
    }

    public function defaults(?AbstractQuotation $abstract, ?string $supplierName = null): array
    {
        $quote = $this->quotation($abstract, $supplierName);
        if (! $quote) {
            return [];
        }
        $response = $quote->response_snapshot;

        return [
            'source_rfq_supplier_quotation_id' => $quote->id,
            'supplier_name' => $response['supplier_name'],
            'supplier_address' => $response['supplier_address'] ?? null,
            'supplier_email' => $response['supplier_email'] ?? null,
            'supplier_contact' => $response['supplier_contact'] ?? null,
            'supplier_tin' => $response['supplier_tin'] ?? null,
            'supplier_representative_name' => $response['signatory_name'] ?? null,
            'supplier_representative_designation' => $response['signatory_position'] ?? null,
        ];
    }

    public function fillMissing(PurchaseOrder $order): void
    {
        foreach ($this->defaults($order->sourceAbstract, $order->supplier_name) as $field => $value) {
            if (blank($order->{$field})) {
                $order->{$field} = $value;
            }
        }
    }

    public function snapshot(PurchaseOrder $order): array
    {
        return [
            ...$order->only(['po_number', 'supplier_name', 'supplier_address', 'mode_of_procurement',
                'place_of_delivery', 'date_of_delivery', 'delivery_term', 'payment_term', 'total_amount', 'penalty_clause']),
            'po_date' => $order->po_date?->toDateString(),
            'items' => $order->items->map(fn ($item) => $item->only([
                'item_no', 'quantity', 'unit', 'description', 'item_description', 'unit_cost', 'total_cost',
            ]))->all(),
        ];
    }

    private function normalize(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
    }
}
