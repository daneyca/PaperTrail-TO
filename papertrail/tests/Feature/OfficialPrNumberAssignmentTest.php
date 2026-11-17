<?php

namespace Tests\Feature;

use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\SvpChainService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class OfficialPrNumberAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $controller;

    private User $originator;

    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = parent::createApplication();
        // Allow a disposable test database, and never refresh a working database.
        $database = (string) env('DB_DATABASE', 'papertrail_testing');
        if (! str_ends_with($database, '_testing')) {
            throw new RuntimeException('PR numbering tests require a database ending in _testing.');
        }
        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql.database', $database);

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

    public function test_each_fund_is_numbered_automatically_and_tracking_stays_separate(): void
    {
        $counterCount = 0;
        foreach (['General Fund' => '101', 'Trust Fund' => '401', 'Special Funds' => '221'] as $fund => $code) {
            $pr = $this->pendingPr($fund);
            $tracking = $pr->tracking_number;
            $response = $this->get(route('pr-numbering.pending.show', $pr))->assertOk()->assertSee($fund);
            $html = new DOMDocument;
            @$html->loadHTML($response->getContent());
            $input = (new DOMXPath($html))->query('//input[@id="pr_no"]')->item(0);
            $this->assertSame($code.'-2026-09-001', $input->getAttribute('value'));
            $this->assertTrue($input->hasAttribute('readonly'));
            $this->assertFalse($input->hasAttribute('name'));
            $this->assertDatabaseCount('pr_number_sequences', $counterCount);

            $this->patch(route('pr-numbering.pending.assign', $pr), ['pr_date' => '2026-09-15'])
                ->assertSessionHasNoErrors()->assertRedirect(route('pr-numbering.assigned.index'));
            $this->assertDatabaseHas('procurement_documents', [
                'id' => $pr->id, 'pr_no' => $code.'-2026-09-001', 'tracking_number' => $tracking,
                'document_reference_number' => $tracking, 'pr_number_status' => ProcurementDocument::PR_NUMBER_STATUS_ASSIGNED,
                'assigned_to_user_id' => $this->originator->id, 'pr_no_assigned_by_user_id' => $this->controller->id,
                'status' => ProcurementDocument::STATUS_PR_NUMBER_ASSIGNED_RETURNED_TO_END_USER,
            ]);
            $this->assertDatabaseHas('pr_number_sequences', ['fiscal_year' => 2026, 'prefix' => $code.'-2026-', 'last_sequence' => 1]);
            $counterCount++;
        }
    }

    public function test_numbers_continue_after_existing_prs_across_months_and_offices(): void
    {
        $legacy = $this->pendingPr('General Fund');
        $legacy->update(['pr_no' => '101-2026-08-350', 'pr_number_status' => ProcurementDocument::PR_NUMBER_STATUS_ASSIGNED]);
        $otherOffice = User::where('user_id', 'MEO-001')->firstOrFail();
        $first = $this->pendingPr('General Fund');
        $first->update(['submitting_office_id' => $otherOffice->office_id]);
        $second = $this->pendingPr('General Fund');

        $this->patch(route('pr-numbering.pending.assign', $first), ['pr_date' => '2026-09-15'])->assertSessionHasNoErrors();
        $this->patch(route('pr-numbering.pending.assign', $second), ['pr_date' => '2026-10-01'])->assertSessionHasNoErrors();
        $this->assertSame('101-2026-09-351', $first->fresh()->pr_no);
        $this->assertSame('101-2026-10-352', $second->fresh()->pr_no);
        $this->assertSame('101-2026-08-350', $legacy->fresh()->pr_no);

        $nextYear = $this->pendingPr('General Fund');
        $this->patch(route('pr-numbering.pending.assign', $nextYear), ['pr_date' => '2027-01-01'])->assertSessionHasNoErrors();
        $this->assertSame('101-2027-01-001', $nextYear->fresh()->pr_no);
    }

    public function test_a_higher_saved_counter_is_preserved_and_sequences_can_exceed_999(): void
    {
        DB::table('pr_number_sequences')->insert(['fiscal_year' => 2026, 'prefix' => '101-2026-', 'last_sequence' => 999]);
        $pr = $this->pendingPr('General Fund');
        $this->patch(route('pr-numbering.pending.assign', $pr), ['pr_date' => '2026-09-15'])->assertSessionHasNoErrors();
        $this->assertSame('101-2026-09-1000', $pr->fresh()->pr_no);
    }

    public function test_posted_numbers_and_funds_cannot_override_the_saved_pr(): void
    {
        $pr = $this->pendingPr('General Fund');
        $this->patch(route('pr-numbering.pending.assign', $pr), [
            'pr_no' => '401-2026-09-999', 'fund_cluster' => 'Trust Fund', 'fund_source' => 'Trust Fund', 'pr_date' => '2026-09-15',
        ])->assertSessionHasNoErrors();
        $this->assertSame('101-2026-09-001', $pr->fresh()->pr_no);
        $this->assertSame('General Fund', $pr->fresh()->fund_cluster);
    }

    public function test_unknown_or_ambiguous_funds_require_correction_without_consuming_a_number(): void
    {
        foreach (['Unspecified fund', 'General Fund / Trust Fund'] as $fund) {
            $pr = $this->pendingPr($fund);
            $this->get(route('pr-numbering.pending.show', $pr))->assertOk()->assertSee('Return this PR for correction of its fund source');
            $this->patch(route('pr-numbering.pending.assign', $pr), ['pr_date' => '2026-09-15'])->assertSessionHasErrors('pr_no');
            $this->assertNull($pr->fresh()->pr_no);
        }
        $this->assertDatabaseCount('pr_number_sequences', 0);
    }

    public function test_invalid_dates_do_not_consume_a_number(): void
    {
        $pr = $this->pendingPr('General Fund');
        foreach (['', 'not a date', '2026-02-30', '2026-13-01'] as $date) {
            $this->patch(route('pr-numbering.pending.assign', $pr), ['pr_date' => $date])->assertSessionHasErrors('pr_date');
            $this->assertNull($pr->fresh()->pr_no);
        }
        $this->assertDatabaseCount('pr_number_sequences', 0);
    }

    public function test_repeated_submission_cannot_assign_another_number_to_the_same_pr(): void
    {
        $pr = $this->pendingPr('General Fund');
        $this->patch(route('pr-numbering.pending.assign', $pr), ['pr_date' => '2026-09-15'])->assertSessionHasNoErrors();
        $this->patch(route('pr-numbering.pending.assign', $pr), ['pr_date' => '2026-10-01'])->assertSessionHas('error');
        $this->assertSame('101-2026-09-001', $pr->fresh()->pr_no);
        $this->assertDatabaseHas('pr_number_sequences', ['prefix' => '101-2026-', 'last_sequence' => 1]);
    }

    public function test_previews_follow_the_date_without_reserving_a_number_and_stale_previews_are_safe(): void
    {
        $first = $this->pendingPr('Trust Fund');
        $second = $this->pendingPr('Trust Fund');
        $url = route('pr-numbering.pending.number-preview', $first);
        foreach (['2026-09-15' => '401-2026-09-001', '2026-10-01' => '401-2026-10-001', '2027-01-01' => '401-2027-01-001'] as $date => $number) {
            $this->getJson($url.'?pr_date='.$date)->assertOk()->assertJsonPath('number', $number);
        }
        $this->assertDatabaseCount('pr_number_sequences', 0);

        $this->patch(route('pr-numbering.pending.assign', $second), ['pr_date' => '2026-09-15'])->assertSessionHasNoErrors();
        $this->patch(route('pr-numbering.pending.assign', $first), ['pr_no' => '401-2026-09-001', 'pr_date' => '2026-09-15'])->assertSessionHasNoErrors();
        $this->assertSame('401-2026-09-002', $first->fresh()->pr_no);
        $this->getJson($url.'?pr_date=2026-09-15')->assertStatus(409);
    }

    public function test_a_failed_assignment_rolls_back_both_the_pr_and_counter(): void
    {
        $pr = $this->pendingPr('General Fund');
        $this->mock(SvpChainService::class, function ($mock): void {
            $mock->shouldReceive('findOrCreateFromPr')->once()->andThrow(new RuntimeException('Simulated routing failure'));
        });
        $this->withoutExceptionHandling();
        try {
            $this->patch(route('pr-numbering.pending.assign', $pr), ['pr_date' => '2026-09-15']);
            $this->fail('Expected the simulated routing failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated routing failure', $exception->getMessage());
        }
        $this->assertNull($pr->fresh()->pr_no);
        $this->assertSame(ProcurementDocument::PR_NUMBER_STATUS_PENDING_ASSIGNMENT, $pr->fresh()->pr_number_status);
        $this->assertDatabaseCount('pr_number_sequences', 0);
    }

    public function test_a_different_role_cannot_preview_or_assign_official_pr_numbers(): void
    {
        $pr = $this->pendingPr('General Fund');
        $this->originator->forceFill(['must_change_password' => false])->save();
        $this->actingAs($this->originator);
        $this->getJson(route('pr-numbering.pending.number-preview', $pr).'?pr_date=2026-09-15')
            ->assertRedirect(route('dashboard'))->assertSessionHas('error', 'Unauthorized access.');
        $this->patch(route('pr-numbering.pending.assign', $pr), ['pr_date' => '2026-09-15'])
            ->assertRedirect(route('dashboard'))->assertSessionHas('error', 'Unauthorized access.');
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
