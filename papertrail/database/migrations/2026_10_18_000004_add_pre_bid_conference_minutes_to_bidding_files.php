<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('competitive_bidding_records')->select('id')->orderBy('id')->chunkById(100, function ($records): void {
            foreach ($records as $record) {
                DB::transaction(function () use ($record): void {
                    $items = DB::table('competitive_bidding_supporting_items')
                        ->where('competitive_bidding_record_id', $record->id)
                        ->whereNull('competitive_bidding_supplier_id');

                    if ((clone $items)->where('requirement_key', 'pre_bid_conference_minutes')->exists()) {
                        return;
                    }

                    (clone $items)->where('display_order', '>=', 12)->increment('display_order');

                    DB::table('competitive_bidding_supporting_items')->insert([
                        'competitive_bidding_record_id' => $record->id,
                        'competitive_bidding_supplier_id' => null,
                        'guide_number' => 'PB',
                        'requirement_key' => 'pre_bid_conference_minutes',
                        'display_order' => 12,
                        'section' => 'BAC Proceedings',
                        'phase' => 'bidding',
                        'requirement_name' => 'Minutes of the Pre-Bid Conference',
                        'document_source' => 'BACSEC-002',
                        'is_required' => false,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
            }
        });
    }

    public function down(): void
    {
        DB::table('competitive_bidding_supporting_items')
            ->whereNull('competitive_bidding_supplier_id')
            ->where('requirement_key', 'pre_bid_conference_minutes')
            ->delete();

        DB::table('competitive_bidding_supporting_items')
            ->whereNull('competitive_bidding_supplier_id')
            ->where('display_order', '>', 12)
            ->decrement('display_order');
    }
};
