<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserHasPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BacSecretariatAccountRenumberingTest extends TestCase
{
    use RefreshDatabase;

    public function test_signer_and_posting_accounts_keep_their_records_when_ids_shift(): void
    {
        $signer = User::where('user_id', 'BACSEC-002')->firstOrFail();
        $posting = User::where('user_id', 'BACSEC-003')->firstOrFail();
        $signerId = $signer->id;
        $postingId = $posting->id;
        $signerPassword = $signer->password;
        $postingPassword = $posting->password;

        $posting->update(['must_change_password' => false]);
        $posting->update(['user_id' => 'BACSEC-004']);
        $signer->update(['user_id' => 'BACSEC-003']);

        $migration = require database_path('migrations/2026_10_18_000003_renumber_remaining_bac_secretariat_accounts.php');
        $migration->up();

        $this->assertSame($signerId, User::where('user_id', 'BACSEC-002')->firstOrFail()->id);
        $this->assertSame($postingId, User::where('user_id', 'BACSEC-003')->firstOrFail()->id);
        $this->assertSame($signerPassword, $signer->refresh()->password);
        $this->assertSame($postingPassword, $posting->refresh()->password);
        $this->assertFalse(User::where('user_id', 'BACSEC-004')->exists());

        $this->withoutMiddleware(EnsureUserHasPermission::class);
        $this->actingAs($posting)->get(route('bac-secretariat.svp-posting.pending'))->assertOk();
        $this->actingAs($signer)->get(route('bac-secretariat.svp-posting.pending'))->assertForbidden();
    }
}
