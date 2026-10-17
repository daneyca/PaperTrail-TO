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

    public function test_budget_and_accounting_officers_are_available_as_login_roles(): void
    {
        foreach ([
            ['code' => 'budget_officer', 'name' => User::ROLE_BUDGET, 'route' => 'budget.dashboard'],
            ['code' => 'accounting_officer', 'name' => User::ROLE_ACCOUNTING, 'route' => 'accounting.dashboard'],
        ] as $role) {
            Role::create([
                'code' => $role['code'],
                'name' => $role['name'],
                'dashboard_route' => $role['route'],
                'status' => Role::STATUS_ACTIVE,
                'is_system' => true,
            ]);
        }

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(User::ROLE_BUDGET)
            ->assertSee(User::ROLE_ACCOUNTING);
    }
}
