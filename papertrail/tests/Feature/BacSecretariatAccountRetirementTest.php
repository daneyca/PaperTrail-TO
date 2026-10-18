<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BacSecretariatAccountRetirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_bacsec_002_keeps_its_identity_and_legacy_account_cannot_access_the_app(): void
    {
        $legacy = User::where('user_id', 'BACSEC-001')->firstOrFail();
        $replacement = User::where('user_id', 'BACSEC-002')->firstOrFail();
        $replacement->update([
            'name' => 'Current BAC Secretariat Officer',
            'must_change_password' => false,
        ]);
        $replacementId = $replacement->id;
        $replacementPassword = $replacement->password;

        $migration = require database_path('migrations/2026_10_18_000002_retire_legacy_bac_secretariat_account.php');
        $migration->up();

        $this->assertSame($replacementId, User::where('user_id', 'BACSEC-001')->firstOrFail()->id);
        $this->assertSame($replacementPassword, $replacement->refresh()->password);
        $this->assertSame('Current BAC Secretariat Officer', $replacement->name);
        $this->assertSame(User::STATUS_INACTIVE, $legacy->refresh()->status);
        $this->assertSame('BACSEC-RETIRED-001', $legacy->user_id);
        $this->assertFalse(User::where('user_id', 'BACSEC-002')->exists());
        $this->assertSame('BACSEC-001', config('papertrail_signatures.bac_resolution.signers.0.account_code'));

        $this->actingAs($legacy)->get(route('bac-secretariat.dashboard'))->assertRedirect(route('login'));

        $this->actingAs($replacement)->get(route('bac-secretariat.dashboard'))
            ->assertOk()
            ->assertSee('Planning')
            ->assertSee('Small Value Procurement')
            ->assertSee('Competitive Bidding');
    }
}
