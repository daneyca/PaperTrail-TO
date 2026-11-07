<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_the_captcha_image_instead_of_an_undefined_code_variable(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('id="captcha-image"', false)
            ->assertSee(route('captcha.image'))
            ->assertDontSee('$securityCode');
    }

    public function test_captcha_endpoint_returns_an_image(): void
    {
        $response = $this->get(route('captcha.image'));

        $response->assertOk();
        $this->assertStringStartsWith('image/', (string) $response->headers->get('Content-Type'));
    }

    public function test_duplicate_office_duties_are_not_separate_login_roles(): void
    {
        foreach ([
            ['code' => 'budget_officer', 'name' => User::ROLE_BUDGET, 'route' => 'budget.dashboard'],
            ['code' => 'accounting_officer', 'name' => User::ROLE_ACCOUNTING, 'route' => 'accounting.dashboard'],
            ['code' => 'approving_authority', 'name' => User::ROLE_APPROVING_AUTHORITY, 'route' => 'approving-authority.dashboard'],
        ] as $role) {
            Role::updateOrCreate(['code' => $role['code']], [
                'name' => $role['name'],
                'dashboard_route' => $role['route'],
                'status' => Role::STATUS_ACTIVE,
                'is_system' => true,
            ]);
        }

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('value="'.User::ROLE_BUDGET.'"', false)
            ->assertDontSee('value="'.User::ROLE_ACCOUNTING.'"', false)
            ->assertDontSee('value="'.User::ROLE_APPROVING_AUTHORITY.'"', false);
    }

    public function test_linked_bac_workspaces_are_not_separate_login_roles(): void
    {
        foreach ([
            ['code' => 'bac_member', 'name' => User::ROLE_BAC_MEMBER, 'route' => 'bac-member.dashboard'],
            ['code' => 'bac_chair', 'name' => User::ROLE_BAC_CHAIR, 'route' => 'bac-chair.dashboard'],
            ['code' => 'bac_vice_chairperson', 'name' => User::ROLE_BAC_VICE_CHAIRPERSON, 'route' => 'bac-chair.dashboard'],
        ] as $role) {
            Role::updateOrCreate(['code' => $role['code']], [
                'name' => $role['name'],
                'dashboard_route' => $role['route'],
                'status' => Role::STATUS_ACTIVE,
                'is_system' => true,
            ]);
        }

        $response = $this->get(route('login'))->assertOk();

        foreach ([User::ROLE_BAC_MEMBER, User::ROLE_BAC_CHAIR, User::ROLE_BAC_VICE_CHAIRPERSON] as $role) {
            $response->assertDontSee('value="'.$role.'"', false);
        }
    }
}
