<?php

namespace Tests\Feature;

use App\Models\CompetitiveBiddingRecord;
use App\Models\CompetitiveBiddingSignatureDocument;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Services\CompetitiveBiddingSupportingDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechnicalChecklistSigningTest extends TestCase
{
    use RefreshDatabase;

    public function test_technical_checklist_shows_bac_signers_and_unboxed_requirements(): void
    {
        $preparer = $this->account('BACSEC-001', User::ROLE_BAC_SECRETARIAT);
        $this->account('BACCHAIR-001', User::ROLE_BAC_CHAIR);
        $this->account('BACVICE-001', User::ROLE_BAC_VICE_CHAIRPERSON);
        $this->account('BACMEM-001', User::ROLE_BAC_MEMBER);
        $this->account('BACMEM-002', User::ROLE_BAC_MEMBER);
        $this->account('BACMEM-003', User::ROLE_BAC_MEMBER);

        $this->actingAs($preparer)
            ->get(route('bac-secretariat.competitive-bidding.checklist.technical.template'))
            ->assertOk()
            ->assertSee('Submit for Signatures')
            ->assertSee('J. RESUS')
            ->assertSee('P. SALAN')
            ->assertSee('R. LAYO')
            ->assertSee('REQUIRED BID SECURITY')
            ->assertSee('BACMEM-001')
            ->assertSee('BACMEM-002')
            ->assertSee('BACMEM-003')
            ->assertSee('data-requirement-row', false)
            ->assertSee('data-cb-signatory-entry', false)
            ->assertDontSee('Joec Construction');
    }

    public function test_only_completed_complying_checklists_route_to_the_selected_bac_accounts(): void
    {
        $preparer = $this->account('BACSEC-001', User::ROLE_BAC_SECRETARIAT);
        $otherSecretariat = $this->account('BACSEC-002', User::ROLE_BAC_SECRETARIAT);
        $chair = $this->account('BACCHAIR-001', User::ROLE_BAC_CHAIR);
        $member = $this->account('BACMEM-001', User::ROLE_BAC_MEMBER);
        $record = app(CompetitiveBiddingSupportingDocumentService::class)->createRecord([
            'reference_number' => 'CB-2026-TECH-CHECK',
            'project_title' => 'Office Supplies',
            'status' => CompetitiveBiddingRecord::STATUS_OPEN,
            'created_by_user_id' => $preparer->id,
        ]);

        $requirementRows = implode('', array_fill(0, 9, '<div data-requirement-row><span class="initial-cell">ET</span></div>'));
        $payload = [
            'template_key' => 'technical_envelope_checklist',
            'document_title' => 'Untrusted title',
            'tracking_number' => $record->reference_number,
            'document_html' => '<section><input name="technical-checklist-result" value="complying" checked><div data-checklist-project>Office Supplies</div><div data-checklist-bidder>Supplier A</div><div data-checklist-abc>100000</div>'.$requirementRows.'</section>',
            'checklist_result' => 'complying',
            'signatories' => json_encode([
                ['slot' => 'chairperson', 'name' => $chair->name, 'role_key' => 'bac_chairperson', 'recipient_user_id' => $chair->id],
                ['slot' => 'member_1', 'name' => $member->name, 'role_key' => 'bac_member', 'recipient_user_id' => $member->id],
            ]),
        ];

        $this->actingAs($otherSecretariat)
            ->post(route('bac-secretariat.competitive-bidding.signature-documents.store'), $payload)
            ->assertForbidden();

        $this->actingAs($preparer)
            ->post(route('bac-secretariat.competitive-bidding.signature-documents.store'), array_replace($payload, ['checklist_result' => 'non_complying']))
            ->assertSessionHasErrors('checklist_result');

        $this->post(route('bac-secretariat.competitive-bidding.signature-documents.store'), array_replace($payload, [
            'document_html' => '<input name="technical-checklist-result" value="complying" checked><div data-checklist-project>Office Supplies</div><div data-checklist-bidder>Supplier A</div><div data-checklist-abc>100000</div>'.implode('', array_fill(0, 8, '<div data-requirement-row><span class="initial-cell">ET</span></div>')).'<div data-requirement-row><span class="initial-cell"></span></div>',
        ]))->assertSessionHasErrors('document_html');

        $this->post(route('bac-secretariat.competitive-bidding.signature-documents.store'), array_replace($payload, [
            'document_html' => '<input name="technical-checklist-result" value="complying" checked><div data-checklist-project>Office Supplies</div><div data-checklist-bidder>Supplier A</div><div data-checklist-abc>100000</div>'.implode('', array_fill(0, 8, '<div data-requirement-row><span class="initial-cell">ET</span></div>')),
        ]))->assertSessionHasErrors('document_html');

        $this->post(route('bac-secretariat.competitive-bidding.signature-documents.store'), array_replace($payload, [
            'signatories' => json_encode([
                ['slot' => 'member_1', 'name' => $member->name, 'role_key' => 'bac_member', 'recipient_user_id' => $member->id],
            ]),
        ]))->assertSessionHasErrors('signatories');

        $this->post(route('bac-secretariat.competitive-bidding.signature-documents.store'), array_replace($payload, [
            'signatories' => json_encode([
                ['slot' => 'chairperson', 'name' => $chair->name, 'role_key' => 'bac_chairperson', 'recipient_user_id' => $chair->id],
                ['slot' => 'member_1', 'name' => $member->name, 'role_key' => 'bac_member', 'recipient_user_id' => $otherSecretariat->id],
            ]),
        ]))->assertSessionHasErrors('signatories');

        $this->post(route('bac-secretariat.competitive-bidding.signature-documents.store'), $payload)
            ->assertRedirect();

        $document = CompetitiveBiddingSignatureDocument::query()->firstOrFail();
        $this->assertSame('Checklist of Technical Envelope Requirements', $document->document_title);
        $this->assertSame(2, SignatureRequest::query()->where('document_id', $document->id)->count());
        $this->assertTrue(SignatureRequest::query()
            ->where('document_id', $document->id)
            ->where('signatory_slot', 'chairperson')
            ->where('requested_to_user_id', $chair->id)
            ->exists());
        $this->assertTrue(SignatureRequest::query()
            ->where('document_id', $document->id)
            ->where('signatory_slot', 'member_1')
            ->where('requested_to_user_id', $member->id)
            ->exists());
    }

    private function account(string $userId, string $role): User
    {
        $user = User::where('user_id', $userId)->first()
            ?? User::factory()->create([
                'user_id' => $userId,
                'role' => $role,
                'office' => 'BAC',
                'status' => User::STATUS_ACTIVE,
            ]);
        $user->update([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);

        return $user->refresh();
    }
}
