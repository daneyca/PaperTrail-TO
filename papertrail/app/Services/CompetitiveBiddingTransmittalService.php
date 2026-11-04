<?php

namespace App\Services;

use App\Models\CompetitiveBiddingRecord;
use App\Models\CompetitiveBiddingSignatureDocument;
use App\Models\CompetitiveBiddingSupportingItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CompetitiveBiddingTransmittalService
{
    public const TEMPLATE_KEY = 'coa_transmittal';

    public function checklist(CompetitiveBiddingRecord $record): Collection
    {
        $record->loadMissing('suppliers');
        $items = $record->supportingItems()
            ->with(['attachments', 'supplier'])
            ->orderBy('display_order')
            ->get();

        $systemDocuments = CompetitiveBiddingSignatureDocument::query()
            ->whereIn('id', $items
                ->filter(fn (CompetitiveBiddingSupportingItem $item) => $item->hasSystemDocument())
                ->pluck('source_id'))
            ->get(['id', 'status'])
            ->keyBy('id');

        return $items
            ->sortBy(fn (CompetitiveBiddingSupportingItem $item) => sprintf(
                '%06d-%06d',
                $item->competitive_bidding_supplier_id
                    ? ($record->suppliers->search(fn ($supplier) => $supplier->id === $item->competitive_bidding_supplier_id) + 1)
                    : 0,
                $item->display_order,
            ))
            ->values()
            ->map(function (CompetitiveBiddingSupportingItem $item) use ($systemDocuments): array {
                $systemSigned = in_array($item->status, [
                    CompetitiveBiddingSupportingItem::STATUS_ATTACHED,
                    CompetitiveBiddingSupportingItem::STATUS_VERIFIED,
                ], true)
                    && $item->hasSystemDocument()
                    && $item->source_status === CompetitiveBiddingSignatureDocument::STATUS_FULLY_SIGNED
                    && $systemDocuments->get($item->source_id)?->status === CompetitiveBiddingSignatureDocument::STATUS_FULLY_SIGNED;
                $manuallyVerified = $item->status === CompetitiveBiddingSupportingItem::STATUS_VERIFIED
                    && $item->attachments->isNotEmpty();
                $ready = $item->status !== CompetitiveBiddingSupportingItem::STATUS_NOT_APPLICABLE
                    && ($systemSigned || $manuallyVerified);

                return [
                    'item_id' => $item->id,
                    'supplier_name' => $item->supplier?->supplier_name,
                    'name' => $item->requirement_name,
                    'ready' => $ready,
                    'status' => $ready ? 'In file' : $item->statusLabel(),
                ];
            });
    }

    public function mayorCandidates(): Collection
    {
        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->where(fn (Builder $query) => $query
                ->where('role', User::ROLE_APPROVING_AUTHORITY)
                ->orWhere(fn (Builder $headOffice) => $headOffice
                    ->where('role', User::ROLE_HEAD_OFFICE)
                    ->whereHas('assignedOffice', fn (Builder $office) => $office->where('code', 'MO'))))
            ->orderBy('user_id')
            ->get(['id', 'user_id', 'name', 'role', 'office_id']);
    }
}
