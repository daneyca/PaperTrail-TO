<?php

namespace Tests\Feature;

use App\Models\AnnualProcurementPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppFiscalYearSelectionTest extends TestCase
{
    use RefreshDatabase;

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
        $user = User::where('user_id', 'BACSEC-003')->firstOrFail();
        $user->update(['must_change_password' => false]);
        $this->actingAs($user);
    }

    private function plan(int $year, string $status): AnnualProcurementPlan
    {
        return AnnualProcurementPlan::create([
            'app_number' => 'APP-'.$year.'-0001', 'fiscal_year' => $year, 'title' => 'APP for '.$year,
            'status' => $status, 'prepared_by_user_id' => auth()->id(),
        ]);
    }

    public function test_create_starts_with_year_availability_instead_of_redirecting_to_this_years_app(): void
    {
        $this->plan(2070, AnnualProcurementPlan::STATUS_APPROVED);
        $before = AnnualProcurementPlan::count();
        $this->get(route('bac-secretariat.app.create'))->assertOk()
            ->assertViewIs('bac-secretariat.app.select-year')
            ->assertSee('Which year are you planning for?')->assertSee('2070 — APP exists')
            ->assertSee('2071 — Available to create')->assertSee('You can prepare next year', false)
            ->assertViewHas('plansByYear', fn ($plans) => $plans[2070]['status'] === 'approved'
                && $plans[2070]['supplemental_url'] === route('bac-secretariat.supplemental-apps.create', ['fiscal_year' => 2070]));
        $this->assertSame($before, AnnualProcurementPlan::count());
    }

    public function test_a_different_fiscal_year_can_be_prepared_in_advance_without_changing_the_existing_app(): void
    {
        $existing = $this->plan(2070, AnnualProcurementPlan::STATUS_APPROVED);
        $before = $existing->fresh()->getAttributes();
        $this->get(route('bac-secretariat.app.create', ['fiscal_year' => 2071]))->assertOk()
            ->assertViewIs('bac-secretariat.app.create')->assertViewHas('app', fn ($app) => (int) $app->fiscal_year === 2071
                && $app->status === AnnualProcurementPlan::STATUS_DRAFT && ! $app->exists)
            ->assertSee('value="2071"', false)->assertSee('Choose another fiscal year');
        $this->assertSame($before, $existing->fresh()->getAttributes());
        $this->assertFalse(AnnualProcurementPlan::where('fiscal_year', 2071)->exists());
    }

    public function test_existing_years_continue_their_draft_or_open_the_approved_app_without_duplicates(): void
    {
        $draft = $this->plan(2070, AnnualProcurementPlan::STATUS_DRAFT);
        $approved = $this->plan(2071, AnnualProcurementPlan::STATUS_APPROVED);
        $this->get(route('bac-secretariat.app.create', ['fiscal_year' => 2070]))->assertRedirect(route('bac-secretariat.app.edit', $draft));
        $this->get(route('bac-secretariat.app.create', ['fiscal_year' => 2071]))->assertRedirect(route('bac-secretariat.app.show', $approved))
            ->assertSessionHas('status', fn ($message) => str_contains($message, 'Supplemental APP') && ! str_contains($message, 'Add approved PPMPs'));
        $this->assertSame(1, AnnualProcurementPlan::where('fiscal_year', 2070)->count());
        $this->assertSame(1, AnnualProcurementPlan::where('fiscal_year', 2071)->count());
    }

    public function test_cancelled_app_does_not_block_replacement_and_submitted_app_is_not_labelled_approved(): void
    {
        $this->plan(2070, AnnualProcurementPlan::STATUS_CANCELLED);
        $submitted = $this->plan(2071, AnnualProcurementPlan::STATUS_SUBMITTED);
        $this->get(route('bac-secretariat.app.create'))->assertOk()
            ->assertViewHas('plansByYear', fn ($plans) => ! isset($plans[2070])
                && $plans[2071]['status'] === 'submitted' && $plans[2071]['supplemental_url'] === null);
        $this->get(route('bac-secretariat.app.create', ['fiscal_year' => 2070]))->assertOk()->assertViewIs('bac-secretariat.app.create');
        $this->get(route('bac-secretariat.app.create', ['fiscal_year' => 2071]))->assertRedirect(route('bac-secretariat.app.show', $submitted));
    }

    public function test_supplemental_link_keeps_the_selected_fiscal_year_and_number_prefix(): void
    {
        $this->get(route('bac-secretariat.supplemental-apps.create', ['fiscal_year' => 2070]))->assertOk()
            ->assertViewHas('supplementalApp', fn ($app) => (int) $app->fiscal_year === 2070
                && str_contains($app->supplemental_app_number, '2070'));
        $this->from(route('bac-secretariat.app.create'))->get(route('bac-secretariat.app.create', ['fiscal_year' => 9999]))
            ->assertRedirect(route('bac-secretariat.app.create'))->assertSessionHasErrors('fiscal_year');
    }
}
