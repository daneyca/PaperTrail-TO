<?php

namespace Tests\Feature;

use App\Models\{ProcurementDocument, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficialPrNumberAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $controller;
    private User $originator;

    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = parent::createApplication();
        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql.database', 'papertrail_testing');
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->controller = User::where('user_id', User::PROCUREMENT_CONTROLLER_USER_ID)->firstOrFail();
        $this->originator = User::where('user_id', 'MCR-001')->firstOrFail();
        $this->controller->forceFill(['must_change_password' => false, 'status' => User::STATUS_ACTIVE])->save();
        $this->actingAs($this->controller);
    }

    public function test_fund_specific_placeholders_and_official_assignments_follow_the_pr(): void
    {
        foreach (['General Fund' => '101-2026-09-350', 'Trust Fund' => '401-2026-09-010', 'Special Education Fund' => '221-2026-09-002'] as $fund => $number) {
            $pr = $this->pendingPr($fund);
            $trackingNumber = $pr->tracking_number;
            $fundCode = substr($number, 0, 3);
            $this->get(route('pr-numbering.pending.show', $pr))->assertOk()->assertSee('placeholder="'.$fundCode.'-2026-09-001"', false)->assertSee($fund);
            $this->patch(route('pr-numbering.pending.assign', $pr), ['pr_no' => $number, 'pr_date' => '2026-09-15'])
                ->assertSessionHasNoErrors()->assertRedirect(route('pr-numbering.assigned.index'));
            $this->assertDatabaseHas('procurement_documents', ['id' => $pr->id, 'pr_no' => $number, 'tracking_number' => $trackingNumber, 'pr_number_status' => ProcurementDocument::PR_NUMBER_STATUS_ASSIGNED]);
            $this->assertDatabaseHas('pr_number_sequences', ['fiscal_year' => 2026, 'prefix' => $fundCode.'-2026-', 'last_sequence' => (int) substr($number, -3)]);
        }
    }

    public function test_wrong_prefix_format_and_date_cannot_be_saved_or_changed_by_a_posted_fund(): void
    {
        $pr = $this->pendingPr('General Fund');
        foreach (['401-2026-09-010', 'PR-2026-001', '101-2026-13-001', '101-2026-09-01', '101-2026-09-000', '101-2025-09-001', '101-2026-08-001'] as $number) {
            $this->from(route('pr-numbering.pending.show', $pr))->patch(route('pr-numbering.pending.assign', $pr), [
                'pr_no' => $number, 'pr_date' => '2026-09-15', 'fund_cluster' => 'Trust Fund',
            ])->assertSessionHasErrors('pr_no');
            $this->assertDatabaseHas('procurement_documents', ['id' => $pr->id, 'fund_cluster' => 'General Fund', 'pr_no' => null, 'pr_number_status' => ProcurementDocument::PR_NUMBER_STATUS_PENDING_ASSIGNMENT]);
        }
    }

    public function test_duplicate_official_numbers_are_rejected_after_trimming(): void
    {
        $first = $this->pendingPr('General Fund');
        $second = $this->pendingPr('General Fund');
        $this->patch(route('pr-numbering.pending.assign', $first), ['pr_no' => '101-2026-09-350', 'pr_date' => '2026-09-15'])->assertSessionHasNoErrors();
        $this->patch(route('pr-numbering.pending.assign', $second), ['pr_no' => ' 101-2026-09-350 ', 'pr_date' => '2026-09-15'])->assertSessionHasErrors('pr_no');
        $this->assertNull($second->fresh()->pr_no);
    }

    public function test_unknown_fund_requires_pr_correction_before_number_assignment(): void
    {
        $pr = $this->pendingPr('Unspecified fund');
        $this->get(route('pr-numbering.pending.show', $pr))->assertOk()->assertSee('Return this PR for correction of its fund source');
        $this->patch(route('pr-numbering.pending.assign', $pr), ['pr_no' => '101-2026-09-001', 'pr_date' => '2026-09-15'])->assertSessionHasErrors('pr_no');
        $this->assertNull($pr->fresh()->pr_no);
    }

    private function pendingPr(string $fund): ProcurementDocument
    {
        $reference = 'PR-FUND-'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(8));
        return ProcurementDocument::withoutEvents(fn () => ProcurementDocument::create([
            'document_type' => 'PR', 'fiscal_year' => 2026, 'tracking_number' => $reference, 'document_reference_number' => $reference,
            'title' => 'Fund based PR numbering test', 'fund_cluster' => $fund, 'pr_date' => '2026-09-15',
            'status' => ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT,
            'pr_number_status' => ProcurementDocument::PR_NUMBER_STATUS_PENDING_ASSIGNMENT,
            'pr_no_requested_at' => now(), 'stage' => ProcurementDocument::STAGE_PR_NUMBER_ASSIGNMENT,
            'prepared_by_user_id' => $this->originator->id, 'submitted_by_user_id' => $this->originator->id,
            'submitting_office_id' => $this->originator->office_id, 'current_office_id' => $this->controller->office_id,
            'assigned_to_user_id' => $this->controller->id,
        ]));
    }
}
