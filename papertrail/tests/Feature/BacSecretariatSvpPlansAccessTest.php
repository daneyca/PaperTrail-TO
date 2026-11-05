<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BacSecretariatSvpPlansAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_bacsec_003_can_open_svp_plans_as_bac_secretariat(): void
    {
        foreach (['BACSEC-001', 'BACSEC-002'] as $userId) {
            $user = User::where('user_id', $userId)->firstOrFail();
            $user->update(['must_change_password' => false]);

            $this->actingAs($user)->get(route('bac-secretariat.ppmp.menu'))->assertForbidden();
            $this->actingAs($user)->get(route('bac-secretariat.apps.menu'))->assertForbidden();
            $this->actingAs($user)->get(route('bac-secretariat.supplemental-apps.menu'))->assertForbidden();
            $this->actingAs($user)->get(route('bac-secretariat.app.index'))->assertForbidden();
            $this->actingAs($user)->get(route('bac-secretariat.ppmp.index'))->assertForbidden();
            $this->actingAs($user)->get(route('bac-secretariat.supplemental-apps.index'))->assertForbidden();
        }

        $planner = User::where('user_id', 'BACSEC-003')->firstOrFail();
        $planner->update(['must_change_password' => false]);

        $this->actingAs($planner)->get(route('bac-secretariat.ppmp.menu'))->assertOk();
        $this->actingAs($planner)->get(route('bac-secretariat.app.index'))->assertOk();

        $chair = User::factory()->create([
            'user_id' => 'TEST-BAC-CHAIR',
            'role' => User::ROLE_BAC_CHAIR,
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);

        $this->actingAs($chair)->get(route('bac-secretariat.app.index'))->assertOk();
    }
}
