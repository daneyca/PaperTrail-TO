<?php

namespace Tests\Feature;

use App\Models\User;
use App\Http\Middleware\EnsureUserHasPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Bacsec001SvpResponsibilitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_bacsec_001_has_mpdo_preparation_and_existing_bac_tasks_in_svp_menu(): void
    {
        $user = $this->bacsec001();

        $this->actingAs($user)
            ->get(route('bac-secretariat.dashboard'))
            ->assertOk()
            ->assertSee('PR Copies')
            ->assertSee('BAC Resolutions')
            ->assertDontSee('Incoming Documents')
            ->assertDontSee('SVP Plans')
            ->assertSee('MPDO Documents')
            ->assertSee(route('head-office.rfqs.create'), false)
            ->assertSee(route('head-office.inspection.index'), false);
    }

    public function test_bacsec_001_can_review_pr_copies_and_resolutions_but_not_other_svp_tasks(): void
    {
        $this->withoutMiddleware(EnsureUserHasPermission::class);
        $this->actingAs($this->bacsec001());

        $this->get(route('bac-secretariat.pr.index'))
            ->assertOk()
            ->assertSee('Create BAC Resolution')
            ->assertSee(route('bac-secretariat.resolutions.create'), false);
        $this->get(route('bac-secretariat.resolutions.index'))
            ->assertOk()
            ->assertDontSee('Create BAC Resolution');
        $this->get(route('bac-secretariat.resolutions.create'))
            ->assertOk()
            ->assertSee('Select assigned PR copy')
            ->assertDontSee('Manual BAC Resolution');
        $this->get(route('bac-secretariat.incoming.index'))->assertForbidden();
        $this->get(route('bac-secretariat.rfqs.create'))->assertForbidden();
        $this->get(route('bac-secretariat.abstracts.create'))->assertForbidden();
        $this->get(route('bac-secretariat.purchase-orders.create'))->assertForbidden();
        $this->get(route('bac-secretariat.competitive-bidding.abstract-bids.as-read'))->assertOk();
    }

    private function bacsec001(): User
    {
        $user = User::where('user_id', 'BACSEC-001')->firstOrFail();
        $user->update([
            'role' => User::ROLE_BAC_SECRETARIAT,
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);

        return $user;
    }
}
