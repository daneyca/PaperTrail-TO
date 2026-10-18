<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileNamePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_names_survive_login_and_reseeding(): void
    {
        $headOffice = User::factory()->create([
            'user_id' => 'MCR-001',
            'name' => 'Municipal Civil Registrar',
            'role' => User::ROLE_HEAD_OFFICE,
            'password' => 'password123',
            'status' => User::STATUS_ACTIVE,
        ]);
        $workflow = User::where('user_id', 'BACSEC-003')->firstOrFail();

        $this->actingAs($headOffice)
            ->patch(route('profile.update'), ['name' => 'Maria Santos'])
            ->assertRedirect(route('profile.show'));

        $this->assertDatabaseHas('users', ['user_id' => 'MCR-001', 'name' => 'Maria Santos']);

        $workflow->update(['name' => 'Darius Malabad']);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', ['user_id' => 'MCR-001', 'name' => 'Maria Santos']);
        $this->assertDatabaseHas('users', ['user_id' => 'BACSEC-003', 'name' => 'Darius Malabad']);

        $this->post(route('logout'))->assertRedirect(route('login'));

        $headOffice->refresh();

        $this->withSession(['login_captcha_hash' => Hash::make('ABCDEF')])
            ->post(route('login.store'), [
                'role' => User::ROLE_HEAD_OFFICE,
                'office' => (string) $headOffice->office_id,
                'user_id' => 'MCR-001',
                'password' => 'password123',
                'security_code' => 'ABCDEF',
            ])
            ->assertRedirect(route('head-office.dashboard'));

        $this->get(route('profile.show'))->assertOk()->assertSee('Maria Santos');
    }
}
