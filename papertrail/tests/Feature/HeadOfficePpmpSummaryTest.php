<?php

namespace Tests\Feature;

use App\Http\Controllers\HeadOffice\PpmpController;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class HeadOfficePpmpSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_counts_only_the_offices_ppmps_by_status(): void
    {
        $office = Office::create(['code' => 'TEST-OFFICE', 'name' => 'Test Office', 'type' => Office::TYPE_END_USER]);
        $otherOffice = Office::create(['code' => 'OTHER-OFFICE', 'name' => 'Other Office', 'type' => Office::TYPE_END_USER]);
        $user = User::factory()->create(['user_id' => 'HEAD-TEST', 'role' => User::ROLE_HEAD_OFFICE, 'office_id' => $office->id]);

        foreach ([
            ['PPMP', ProcurementDocument::STATUS_PPMP_DRAFT, $office->id, 100],
            ['PPMP', ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES, $office->id, 50],
            ['PPMP', ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED, $office->id, 75],
            ['PPMP', ProcurementDocument::STATUS_PENDING_PPMP_REVIEW, $office->id, 200],
            ['PPMP', ProcurementDocument::STATUS_UNDER_PPMP_REVIEW, $office->id, 300],
            ['PPMP', ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT, $office->id, 25],
            ['PPMP', ProcurementDocument::STATUS_PPMP_SIGNATURE_RETURNED, $office->id, 75],
            ['PPMP', ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION, $office->id, 400],
            ['PPMP', ProcurementDocument::STATUS_PPMP_DRAFT, $otherOffice->id, 999],
            ['PR', ProcurementDocument::STATUS_PPMP_DRAFT, $office->id, 999],
        ] as $index => [$type, $status, $officeId, $amount]) {
            DB::table('procurement_documents')->insert([
                'tracking_number' => 'PPMP-SUMMARY-' . $index,
                'document_type' => $type,
                'title' => 'Test document',
                'fiscal_year' => 2026,
                'status' => $status,
                'submitting_office_id' => $officeId,
                'total_amount' => $amount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $summary = (new ReflectionMethod(PpmpController::class, 'summary'))
            ->invoke(new PpmpController, $user);

        $this->assertSame(8, $summary['total']);
        $this->assertSame(1, $summary['draft']);
        $this->assertSame(1, $summary['submitted']);
        $this->assertSame(1, $summary['reviewed']);
        $this->assertSame(2, $summary['returned']);
        $this->assertSame(1, $summary['accepted']);
        $this->assertSame(4, $summary['pending']);
        $this->assertSame(1225.0, $summary['budget']);
    }
}
