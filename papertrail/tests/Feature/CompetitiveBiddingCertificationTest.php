<?php

namespace Tests\Feature;

use App\Models\CompetitiveBiddingCertificationDraft;
use App\Models\CompetitiveBiddingRecord;
use App\Models\CompetitiveBiddingSignatureDocument;
use App\Models\CompetitiveBiddingSupportingItem;
use App\Models\Office;
use App\Models\User;
use App\Services\CompetitiveBiddingSupportingDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitiveBiddingCertificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_bacsec_003_opens_the_official_certification_form(): void
    {
        $this->actingAs($this->editor());
        $record = $this->record();

        $this->get(route('bac-secretariat.competitive-bidding.menu', ['task' => 'bac-secretariat-head-certification']))
            ->assertOk()
            ->assertSee(route('bac-secretariat.competitive-bidding.certification.template'), false);

        $this->get(route('bac-secretariat.competitive-bidding.certification.template'))
            ->assertOk()
            ->assertSee('OFFICE OF THE BIDS AND AWARDS COMMITTEE')
            ->assertSee('THIS IS TO CERTIFY')
            ->assertSee('Noted by:')
            ->assertSee('size:A4 portrait', false)
            ->assertSee($record->reference_number)
            ->assertSee('455,994.50')
            ->assertSee('BACSEC-002')
            ->assertSee('BACCHAIR-001')
            ->assertSee('name="head_name"', false)
            ->assertSee('name="chair_title"', false)
            ->assertSee('name="project"', false)
            ->assertSee('name="published_on"', false)
            ->assertSee('placeholder="Enter name"', false);
    }

    public function test_draft_survives_reload_and_rejects_stale_updates(): void
    {
        $user = $this->editor();
        $record = $this->record();
        $this->actingAs($user);
        $payload = [
            'record_id' => $record->id,
            'published_on' => '2026-11-01',
            'budget_words' => 'Four Hundred Fifty-five Thousand Nine Hundred Ninety-four Pesos and 50/100',
        ];

        $this->postJson(route('bac-secretariat.competitive-bidding.certification.draft.store'), ['revision' => 0, 'draft' => $payload])
            ->assertOk()->assertJsonPath('revision', 1);
        $this->get(route('bac-secretariat.competitive-bidding.certification.template'))
            ->assertOk()->assertSee('value="2026-11-01"', false);

        $payload['published_on'] = '2026-10-01';
        $this->postJson(route('bac-secretariat.competitive-bidding.certification.draft.store'), ['revision' => 0, 'draft' => $payload])
            ->assertStatus(409);
        $this->assertSame('2026-11-01', CompetitiveBiddingCertificationDraft::where('user_id', $user->id)->firstOrFail()->payload['published_on']);
    }

    public function test_submission_routes_to_the_head_and_chair_and_joins_the_project_file_after_signing(): void
    {
        $editor = $this->editor();
        $head = $this->signer('BACSEC-002', User::ROLE_BAC_SECRETARIAT, 'BACSEC');
        $chair = $this->signer('BACCHAIR-001', User::ROLE_BAC_CHAIR, 'BAC');
        $record = $this->record();

        $response = $this->actingAs($editor)->post(route('bac-secretariat.competitive-bidding.certification.submit'), $this->formData($record));

        $document = CompetitiveBiddingSignatureDocument::where('template_key', 'bac_secretariat_head_certification')->firstOrFail();
        $response->assertRedirect(route('bac-secretariat.competitive-bidding.certification.show', $document));
        $this->assertSame($record->reference_number, $document->tracking_number);
        $this->assertStringContainsString('Procurement of Office Supplies', $document->document_html);
        $this->assertStringContainsString('455,994.50', $document->document_html);
        $this->assertStringContainsString('data-certification-signature-slot="head"', $document->document_html);
        $this->assertStringContainsString('data-certification-signature-slot="chair"', $document->document_html);
        $this->assertStringNotContainsString('name="head_name"', $document->document_html);
        $this->assertStringNotContainsString('name="project"', $document->document_html);
        $this->assertCount(2, $document->signatureRequests);
        $this->assertSame($head->id, $document->signatureRequests->firstWhere('signatory_slot', 'head')->requested_to_user_id);
        $this->assertSame($chair->id, $document->signatureRequests->firstWhere('signatory_slot', 'chair')->requested_to_user_id);

        $item = $record->projectItems()->where('requirement_key', 'bac_secretariat_head_certification')->firstOrFail();
        $this->assertSame(CompetitiveBiddingSupportingItem::STATUS_PENDING, $item->status);

        $this->actingAs($head)
            ->get(route('signature-requests.show', $document->signatureRequests->firstWhere('signatory_slot', 'head')))
            ->assertOk()->assertSee('THIS IS TO CERTIFY')->assertSee('Noted by:');

        $document->update(['status' => CompetitiveBiddingSignatureDocument::STATUS_FULLY_SIGNED]);
        $this->assertSame(CompetitiveBiddingSupportingItem::STATUS_ATTACHED, $item->refresh()->status);
        $this->assertSame($document->id, $item->source_id);
    }

    public function test_submission_requires_the_assigned_accounts_and_an_approved_budget(): void
    {
        $editor = $this->editor();
        $record = $this->record();
        User::where('user_id', 'BACSEC-002')->update(['status' => 'inactive']);
        $this->actingAs($editor);

        $this->post(route('bac-secretariat.competitive-bidding.certification.submit'), $this->formData($record))
            ->assertSessionHasErrors('head_name');

        $this->signer('BACSEC-002', User::ROLE_BAC_SECRETARIAT, 'BACSEC');
        $this->signer('BACCHAIR-001', User::ROLE_BAC_CHAIR, 'BAC');
        $record->update(['approved_budget' => null]);
        $this->post(route('bac-secretariat.competitive-bidding.certification.submit'), array_replace($this->formData($record), ['budget' => 455994.50]))
            ->assertSessionHasErrors('record_id');
    }

    public function test_other_secretariat_accounts_cannot_edit_the_certification(): void
    {
        $user = User::where('user_id', 'BACSEC-001')->firstOrFail();
        $user->update(['role' => User::ROLE_BAC_SECRETARIAT, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);
        $this->actingAs($user);

        $this->get(route('bac-secretariat.competitive-bidding.certification.template'))->assertForbidden();
        $this->postJson(route('bac-secretariat.competitive-bidding.certification.draft.store'), [])->assertForbidden();
        $this->post(route('bac-secretariat.competitive-bidding.certification.submit'), [])->assertForbidden();
    }

    private function editor(): User
    {
        $user = User::where('user_id', 'BACSEC-003')->firstOrFail();
        $user->update(['role' => User::ROLE_BAC_SECRETARIAT, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);

        return $user;
    }

    private function record(): CompetitiveBiddingRecord
    {
        return app(CompetitiveBiddingSupportingDocumentService::class)->createRecord([
            'reference_number' => 'CB-2026-001',
            'project_title' => 'Procurement of Office Supplies',
            'approved_budget' => 455994.50,
            'bid_opening_date' => '2026-11-22',
        ]);
    }

    private function formData(CompetitiveBiddingRecord $record): array
    {
        return [
            'record_id' => $record->id,
            'project' => $record->project_title,
            'budget' => $record->approved_budget,
            'published_on' => '2026-11-14',
            'closing_on' => '2026-11-22',
            'certified_on' => '2026-11-14',
            'budget_words' => 'Four Hundred Fifty-five Thousand Nine Hundred Ninety-four Pesos and 50/100',
            'head_name' => 'Meagan C. Matutes',
            'head_title' => 'BAC Secretariat',
            'chair_name' => 'Edmar T. Tambis',
            'chair_title' => 'BAC Chairperson',
        ];
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
