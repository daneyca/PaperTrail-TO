<?php

namespace Tests\Feature;

use App\Models\CompetitiveBiddingAbcDraft;
use App\Models\CompetitiveBiddingSignatureDocument;
use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitiveBiddingAbcTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_bacsec_003_opens_the_abc_template_from_competitive_bidding(): void
    {
        $this->actingAs($this->editor());

        $this->get(route('bac-secretariat.competitive-bidding.menu', ['task' => 'approved-budget-for-the-contract']))
            ->assertOk()->assertSee('Create / Open Template')
            ->assertSee(route('bac-secretariat.competitive-bidding.abc.template'), false);

        $this->get(route('bac-secretariat.competitive-bidding.abc.template'))
            ->assertOk()->assertSee('APPROVED BUDGET FOR THE CONTRACT')
            ->assertSee('ESTIMATED<br>DIRECT COST', false)
            ->assertSee('TOTAL MARK-UP')->assertSee('VAT')
            ->assertSee('name="prepared_name" value="" placeholder="Enter name"', false)
            ->assertSee('name="prepared_title" value="" placeholder="Enter position"', false)
            ->assertSee('id="abc-add">Add Row</button>', false)
            ->assertSee('size:A4 landscape', false)
            ->assertSee('BACSEC-002')->assertSee('BACCHAIR-001')->assertSee('MO-001');
    }

    public function test_abc_draft_survives_reload_and_rejects_stale_edits(): void
    {
        $this->actingAs($user = $this->editor());
        $url = route('bac-secretariat.competitive-bidding.abc.draft.store');
        $payload = ['project' => 'Office supplies', 'location' => 'Tomas Oppus', 'items' => [['description' => 'Acetate Plastic']]];
        $this->postJson($url, ['revision' => 0, 'draft' => $payload])->assertOk()->assertJsonPath('revision', 1);
        $this->get(route('bac-secretariat.competitive-bidding.abc.template'))->assertOk()->assertSee('Acetate Plastic')->assertSee('Draft saved');
        $payload['items'][0]['description'] = 'Old text';
        $this->postJson($url, ['revision' => 0, 'draft' => $payload])->assertStatus(409);
        $this->assertSame('Acetate Plastic', CompetitiveBiddingAbcDraft::where('user_id', $user->id)->firstOrFail()->payload['items'][0]['description']);
    }

    public function test_submission_calculates_cost_and_routes_only_three_available_signers(): void
    {
        $this->actingAs($this->editor());
        $this->signer('BACSEC-002', User::ROLE_BAC_SECRETARIAT, 'BACSEC');
        $this->signer('BACCHAIR-001', User::ROLE_BAC_CHAIR, 'BAC');
        $this->signer('MO-001', User::ROLE_HEAD_OFFICE, 'MO');

        $response = $this->post(route('bac-secretariat.competitive-bidding.abc.submit'), [
            'project' => 'Procurement of Office Supplies',
            'location' => 'Tomas Oppus, Southern Leyte',
            'items' => [[
                'item_no' => '1', 'description' => 'Acetate Plastic', 'quantity' => 3, 'unit' => 'Roll',
                'unit_cost' => 1250, 'total_cost' => 1,
            ]],
            'prepared_name' => 'Rocund P. Felicilda', 'prepared_title' => 'GSO',
            'checked_name' => 'Meagan C. Matutes', 'checked_title' => 'BAC Secretariat',
            'recommended_name' => 'Edmar T. Tambis', 'recommended_title' => 'BAC Chairperson',
            'approved_name' => 'Mayor', 'approved_title' => 'Municipal Mayor',
        ]);

        $document = CompetitiveBiddingSignatureDocument::where('template_key', 'approved_budget_contract')->firstOrFail();
        $response->assertRedirect(route('bac-secretariat.competitive-bidding.abc.show', $document));
        $this->assertCount(3, $document->signatureRequests);
        $this->assertEqualsCanonicalizing(['BACSEC-002', 'BACCHAIR-001', 'MO-001'], $document->signatureRequests->map(fn ($request) => $request->requestedTo->user_id)->all());
        $this->assertFalse($document->signatureRequests->contains('signatory_slot', 'prepared'));
        $this->assertStringContainsString('3,750.00', $document->document_html);
        $this->assertStringContainsString('data-abc-signature-slot="prepared"', $document->document_html);
        $this->get(route('bac-secretariat.competitive-bidding.abc.show', $document))->assertOk()->assertSee('no account assigned yet');
    }

    public function test_other_secretariat_account_cannot_edit_the_abc(): void
    {
        $user = User::where('user_id', 'BACSEC-001')->firstOrFail();
        $user->update(['role' => User::ROLE_BAC_SECRETARIAT, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);
        $this->actingAs($user);

        $this->get(route('bac-secretariat.competitive-bidding.abc.template'))->assertForbidden();
        $this->postJson(route('bac-secretariat.competitive-bidding.abc.draft.store'), [])->assertForbidden();
    }

    private function editor(): User
    {
        $user = User::where('user_id', 'BACSEC-003')->firstOrFail();
        $user->update(['role' => User::ROLE_BAC_SECRETARIAT, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);

        return $user;
    }

    private function signer(string $userId, string $role, string $officeCode): User
    {
        $office = Office::firstOrCreate(['code' => $officeCode], [
            'name' => $officeCode.' Test Office', 'type' => Office::TYPE_END_USER, 'status' => Office::STATUS_ACTIVE,
        ]);
        $user = User::firstOrCreate(['user_id' => $userId], [
            'name' => $userId.' Test Signer', 'email' => strtolower($userId).'@example.test',
            'password' => 'password', 'role' => $role, 'office_id' => $office->id, 'status' => User::STATUS_ACTIVE,
        ]);
        $user->update(['role' => $role, 'office_id' => $office->id, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);

        return $user;
    }
}
