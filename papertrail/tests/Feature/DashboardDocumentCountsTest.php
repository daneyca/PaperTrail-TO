<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Models\ProcurementDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class DashboardDocumentCountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_head_office_counts_each_document_once_across_types_and_statuses(): void
    {
        $user = User::factory()->create(['user_id' => 'HEAD-001', 'role' => User::ROLE_HEAD_OFFICE]);
        $other = User::factory()->create(['user_id' => 'HEAD-002', 'role' => User::ROLE_HEAD_OFFICE]);

        foreach ([
            ['PPMP', ProcurementDocument::STATUS_PPMP_DRAFT, $user->id, $user->id],
            ['PPMP', ProcurementDocument::STATUS_RETURNED_BY_BUDGET, $user->id, null],
            ['PR', ProcurementDocument::STATUS_APPROVED, null, $user->id],
            ['PPMP', ProcurementDocument::STATUS_APPROVED, $other->id, null],
        ] as $index => [$type, $status, $submitter, $preparer]) {
            DB::table('procurement_documents')->insert([
                'tracking_number' => 'TEST-' . $index,
                'document_type' => $type,
                'title' => 'Test document',
                'fiscal_year' => 2026,
                'status' => $status,
                'submitted_by_user_id' => $submitter,
                'prepared_by_user_id' => $preparer,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $controller = new DashboardController;
        $counts = (new ReflectionMethod($controller, 'headOfficeDocumentCounts'))->invoke($controller, $user);
        $statusCount = new ReflectionMethod($controller, 'headOfficeStatusCount');

        $this->assertSame(3, $counts['total']);
        $this->assertSame(2, $counts['by_type']['PPMP']);
        $this->assertSame(1, $counts['by_type']['PR']);
        $this->assertSame(1, $statusCount->invoke($controller, $counts, [ProcurementDocument::STATUS_RETURNED_BY_BUDGET]));
        $this->assertSame(2, $statusCount->invoke($controller, $counts, [ProcurementDocument::STATUS_PPMP_DRAFT], true));
    }
}
