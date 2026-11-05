<?php

namespace Tests\Feature;

use App\Models\CompetitiveBiddingAppDraft;
use App\Models\CompetitiveBiddingSignatureDocument;
use App\Models\Office;
use App\Models\SignatureRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitiveBiddingAnnualProcurementPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_bacsec_003_opens_the_separate_competitive_bidding_app_template(): void
    {
        $this->actingAs($this->editor());

        $this->get(route('bac-secretariat.competitive-bidding.menu', ['task' => 'annual-procurement-plan']))
            ->assertOk()->assertSee('Create / Open Template')
            ->assertSee(route('bac-secretariat.competitive-bidding.annual-procurement-plan.template'), false);

        $this->get(route('bac-secretariat.competitive-bidding.annual-procurement-plan.template'))
            ->assertOk()->assertSee('Annual Procurement Plan (APP) for CY')
            ->assertSee('Schedule for Each Procurement Activity')
            ->assertSee('Estimated Budget (PhP)')
            ->assertSee('size:A4 landscape', false)
            ->assertSee('class="cb-app-row-controls no-print"', false)
            ->assertSee('id="cb-app-add">Add Row</button>', false)
            ->assertSee('name="prepared_name" value="" placeholder="Enter name"', false)
            ->assertSee('BACSEC-002')->assertSee('BACCHAIR-001')->assertSee('MBO-001')->assertSee('MO-001');
    }

    public function test_draft_survives_reload_and_rejects_an_old_revision(): void
    {
        $user = $this->editor();
        $this->actingAs($user);
        $url = route('bac-secretariat.competitive-bidding.annual-procurement-plan.draft.store');
        $draft = ['year' => '2026', 'items' => [['project' => 'Office Supplies', 'budget' => '500000']]];

        $this->postJson($url, ['revision' => 0, 'draft' => $draft])->assertOk()->assertJsonPath('revision', 1);
        $this->get(route('bac-secretariat.competitive-bidding.annual-procurement-plan.template'))
            ->assertOk()->assertSee('Office Supplies')->assertSee('Draft saved');

        $draft['items'][0]['project'] = 'Old edit';
        $this->postJson($url, ['revision' => 0, 'draft' => $draft])->assertStatus(409);
        $this->assertSame('Office Supplies', CompetitiveBiddingAppDraft::where('user_id', $user->id)->firstOrFail()->payload['items'][0]['project']);
    }

    public function test_submission_routes_the_app_to_four_distinct_signing_accounts(): void
    {
        $this->actingAs($this->editor());
        $this->signer('BACSEC-002', User::ROLE_BAC_SECRETARIAT, 'BACSEC');
        $this->signer('BACCHAIR-001', User::ROLE_BAC_CHAIR, 'BAC');
        $this->signer('MBO-001', User::ROLE_HEAD_OFFICE, 'MBO');
        $this->signer('MO-001', User::ROLE_HEAD_OFFICE, 'MO');
        $response = $this->post(route('bac-secretariat.competitive-bidding.annual-procurement-plan.submit'), [
            'year' => 2026,
            'items' => [[
                'code' => '2026-02', 'project' => 'Office Supplies', 'end_user' => 'Mayor Office',
                'mode' => 'Public Bidding', 'ads' => 'January', 'bids' => 'February',
                'award' => 'March', 'contract' => 'April', 'funds' => 'General Fund',
                'budget' => 500000, 'personnel' => 0, 'mooe' => 500000, 'co' => 0,
                'remarks' => 'Quarterly supplies',
            ]],
            'prepared_title' => 'BAC Secretariat Head',
            'recommended_title' => 'BAC Chairperson',
            'certified_title' => 'Municipal Budget Officer',
            'approved_title' => 'Municipal Mayor',
        ]);

        $document = CompetitiveBiddingSignatureDocument::where('template_key', 'annual_procurement_plan')->firstOrFail();
        $response->assertRedirect(route('bac-secretariat.competitive-bidding.annual-procurement-plan.show', $document));
        $this->assertCount(4, $document->signatureRequests);
        foreach (['BACSEC-002', 'BACCHAIR-001', 'MBO-001', 'MO-001'] as $account) {
            $this->assertTrue($document->signatureRequests->contains(fn (SignatureRequest $request) => $request->requested_to_user_id === User::where('user_id', $account)->firstOrFail()->id));
        }
        $this->assertStringContainsString('500,000.00', $document->document_html);
        $this->assertStringContainsString('data-app-signature-slot="approved"', $document->document_html);
    }

    public function test_other_secretariat_accounts_cannot_edit_this_app(): void
    {
        $user = User::where('user_id', 'BACSEC-001')->firstOrFail();
        $user->update(['role' => User::ROLE_BAC_SECRETARIAT, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);
        $this->actingAs($user);

        $this->get(route('bac-secretariat.competitive-bidding.annual-procurement-plan.template'))->assertForbidden();
        $this->postJson(route('bac-secretariat.competitive-bidding.annual-procurement-plan.draft.store'), [])->assertForbidden();
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
