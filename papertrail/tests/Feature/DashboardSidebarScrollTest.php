<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSidebarScrollTest extends TestCase
{
    use RefreshDatabase;

    public function test_bacsec_003_task_page_renders_sidebar_scroll_restoration(): void
    {
        $user = User::where('user_id', 'BACSEC-003')->firstOrFail();
        $user->update([
            'role' => User::ROLE_BAC_SECRETARIAT,
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->get(route('bac-secretariat.competitive-bidding.menu', ['task' => 'bac-secretariat-head-certification']))
            ->assertOk()
            ->assertSee('Certification of the Head of the BAC Secretariat')
            ->assertDontSee('9 actions')
            ->assertDontSee('For setup')
            ->assertSee('papertrail_sidebar_scroll_', false)
            ->assertSee('papertrail_sidebar_dropdowns_', false)
            ->assertSee('data-sidebar-key="Security &amp; Recovery"', false)
            ->assertSee("getComputedStyle(sidebarNav).overflowY", false)
            ->assertSee('sessionStorage.setItem(sidebarScrollStorageKey, String(sidebarScrollArea.scrollTop))', false)
            ->assertSee('saveSidebarDropdowns(dropdown)', false)
            ->assertSee('activeLink.getBoundingClientRect()', false);
    }
}
