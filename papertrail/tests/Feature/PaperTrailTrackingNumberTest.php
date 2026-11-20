<?php

namespace Tests\Feature;

use App\Models\AbstractQuotation;
use App\Models\AnnualProcurementPlan;
use App\Models\AppConsolidation;
use App\Models\BacResolution;
use App\Models\InspectionAcceptanceRecord;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\User;
use App\Services\DocumentReferenceNumberService;
use App\Services\DocumentTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaperTrailTrackingNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_document_types_receive_the_new_tracking_format_without_changing_official_numbers(): void
    {
        $documents = $this->documents();
        foreach ($documents as $type => $document) {
            $this->assertSame('PT-'.$type.'-2026-0001', $document->document_reference_number);
            $this->assertSame($document->document_reference_number, $document->displayNumber());
            $this->assertSame(1, (int) $document->sequence_number);
            $this->assertSame(2026, (int) $document->created_year);
            $this->assertSame(11, (int) $document->created_month);
            $original = $document->document_reference_number;
            $document->save();
            $this->assertSame($original, $document->fresh()->document_reference_number);
        }
        $this->assertSame('101-2026-11-123', $documents['PR']->fresh()->pr_no);
        $this->assertSame('APP-OFFICIAL-123', $documents['APP']->fresh()->app_no);
        $this->assertSame('237', $documents['BAC']->fresh()->resolution_number);
        $this->assertSame('RFQ-OFFICIAL-123', $documents['RFQ']->fresh()->rfq_number);
        $this->assertSame('AOQ-OFFICIAL-123', $documents['AOQ']->fresh()->abstract_number);
        $this->assertSame('PO-OFFICIAL-123', $documents['PO']->fresh()->po_number);

        $consolidation = new AppConsolidation(['app_number' => 'APP-CONSOLIDATION-123', 'title' => 'Tracking consolidation', 'fiscal_year' => 2026, 'status' => AppConsolidation::STATUS_DRAFT]);
        $consolidation->forceFill(['created_at' => '2026-12-01 10:00:00'])->save();
        $this->assertSame('PT-APP-2026-0002', $consolidation->document_reference_number);
    }

    public function test_tracking_lookup_recognizes_the_full_pt_number_for_every_document_type(): void
    {
        $admin = User::factory()->create(['user_id' => 'PT-TRACK-ADMIN', 'role' => User::ROLE_ADMIN, 'status' => User::STATUS_ACTIVE]);
        foreach ($this->documents() as $type => $document) {
            $result = app(DocumentTrackingService::class)->getDocumentTracking($admin, 'Track '.$document->document_reference_number);
            $this->assertTrue($result['found'], $type.': '.($result['message'] ?? 'not found'));
            $this->assertTrue($result['authorized'], $type);
            $this->assertSame($document->document_reference_number, $result['document']['tracking_number']);
        }
    }

    public function test_navbar_search_accepts_tracking_and_official_numbers_and_keeps_legacy_search_working(): void
    {
        $user = User::factory()->create(['user_id' => 'PT-SEARCH-001', 'role' => User::ROLE_HEAD_OFFICE, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);
        $other = User::factory()->create(['user_id' => 'PT-SEARCH-002', 'role' => User::ROLE_HEAD_OFFICE, 'status' => User::STATUS_ACTIVE]);
        $own = Rfq::create(['rfq_number' => 'RFQ-SEARCH-123', 'supplier_name' => 'Number Search Supplier', 'prepared_by_user_id' => $user->id]);
        $hidden = Rfq::create(['rfq_number' => 'RFQ-PRIVATE-123', 'prepared_by_user_id' => $other->id]);
        $legacy = Rfq::create(['document_reference_number' => '2026-11-RFQ-0099', 'rfq_number' => 'RFQ-LEGACY-123', 'prepared_by_user_id' => $user->id]);

        foreach ([$own->document_reference_number, 'RFQ-SEARCH-123', $legacy->document_reference_number, 'RFQ-LEGACY-123'] as $term) {
            $expected = str_contains($term, 'LEGACY') || $term === $legacy->document_reference_number ? $legacy : $own;
            $this->actingAs($user)->getJson(route('search.suggestions', ['q' => $term]))
                ->assertOk()->assertJsonCount(1, 'results')
                ->assertJsonPath('results.0.reference', $expected->document_reference_number);
        }
        $this->getJson(route('search.suggestions', ['q' => $hidden->document_reference_number]))
            ->assertOk()->assertJsonCount(0, 'results');
    }

    public function test_the_shared_ppmp_counter_continues_after_legacy_counters_without_renumbering_records(): void
    {
        $office = Office::create(['code' => 'PT-LEGACY', 'name' => 'Legacy Tracking Office', 'type' => Office::TYPE_END_USER]);
        $legacy = ProcurementDocument::create([
            'document_type' => 'PPMP', 'title' => 'Existing numbered plan', 'fiscal_year' => 2026,
            'document_reference_number' => '2026-11-PPMP-PT-LEGACY-0012', 'tracking_number' => '2026-11-PPMP-PT-LEGACY-0012',
            'sequence_number' => 12, 'created_year' => 2026, 'created_month' => 11,
            'submitting_office_id' => $office->id, 'status' => ProcurementDocument::STATUS_PPMP_DRAFT,
        ]);
        DB::table('ppmp_reference_sequences')->insert(['office_id' => $office->id, 'created_year' => 2026, 'last_sequence' => 20]);
        $migration = require database_path('migrations/2026_11_17_000002_seed_shared_ppmp_tracking_sequence.php');
        $migration->up();

        $next = new ProcurementDocument(['document_type' => 'PPMP', 'title' => 'New plan', 'fiscal_year' => 2026, 'status' => ProcurementDocument::STATUS_PPMP_DRAFT]);
        $next->forceFill(['created_at' => '2026-11-17 10:00:00'])->save();
        $this->assertSame('PT-PPMP-2026-0021', $next->tracking_number);
        $this->assertSame('2026-11-PPMP-PT-LEGACY-0012', $legacy->fresh()->tracking_number);
        app(DocumentReferenceNumberService::class)->assign($legacy, 'PPMP');
        $this->assertFalse($legacy->isDirty());

        $migration->up();
        $this->assertDatabaseHas('document_reference_sequences', ['document_type' => 'PPMP', 'created_year' => 2026, 'last_sequence' => 21]);
    }

    public function test_sequence_numbers_can_grow_beyond_four_digits(): void
    {
        DB::table('document_reference_sequences')->insert(['document_type' => 'RFQ', 'created_year' => 2026, 'created_month' => 11, 'last_sequence' => 9999]);
        $rfq = new Rfq;
        $rfq->forceFill(['created_at' => '2026-11-17 10:00:00'])->save();
        $this->assertSame('PT-RFQ-2026-10000', $rfq->document_reference_number);
    }

    private function documents(): array
    {
        $preparer = User::factory()->create(['user_id' => 'PT-DOCUMENT-CREATOR', 'role' => User::ROLE_HEAD_OFFICE, 'status' => User::STATUS_ACTIVE]);
        $cases = [
            'PPMP' => [ProcurementDocument::class, ['document_type' => 'PPMP', 'title' => 'Tracking plan', 'fiscal_year' => 2026, 'status' => ProcurementDocument::STATUS_PPMP_DRAFT]],
            'PR' => [ProcurementDocument::class, ['document_type' => 'PR', 'title' => 'Tracking request', 'fiscal_year' => 2026, 'status' => ProcurementDocument::STATUS_PR_DRAFT, 'pr_no' => '101-2026-11-123']],
            'APP' => [AnnualProcurementPlan::class, ['app_no' => 'APP-OFFICIAL-123', 'title' => 'Tracking annual plan', 'fiscal_year' => 2026]],
            'BAC' => [BacResolution::class, ['resolution_number' => '237', 'title' => 'Tracking resolution', 'prepared_by_user_id' => $preparer->id]],
            'RFQ' => [Rfq::class, ['rfq_number' => 'RFQ-OFFICIAL-123']],
            'AOQ' => [AbstractQuotation::class, ['abstract_number' => 'AOQ-OFFICIAL-123']],
            'PO' => [PurchaseOrder::class, ['po_number' => 'PO-OFFICIAL-123', 'fiscal_year' => 2026]],
            'IA' => [InspectionAcceptanceRecord::class, []],
        ];
        $documents = [];
        foreach ($cases as $type => [$model, $attributes]) {
            if ($type === 'IA') {
                $attributes['purchase_order_id'] = $documents['PO']->id;
            }
            $document = new $model(['status' => 'draft', ...$attributes]);
            $document->forceFill(['created_at' => '2026-11-17 10:00:00'])->save();
            $documents[$type] = $document;
        }

        return $documents;
    }
}
