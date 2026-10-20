<?php

namespace Tests\Feature;

use App\Models\CompetitiveBiddingRecord;
use App\Models\CompetitiveBiddingSignatureDocument;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Services\CompetitiveBiddingSupportingDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialChecklistSigningTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_checklist_uses_the_official_sheet_and_bac_accounts(): void
    {
        $preparer = $this->account('BACSEC-001', User::ROLE_BAC_SECRETARIAT);
        $this->account('BACCHAIR-001', User::ROLE_BAC_CHAIR);
        $this->account('BACVICE-001', User::ROLE_BAC_VICE_CHAIRPERSON);
        $this->account('BACMEM-001', User::ROLE_BAC_MEMBER);
        $this->account('BACMEM-002', User::ROLE_BAC_MEMBER);
        $this->account('BACMEM-003', User::ROLE_BAC_MEMBER);

        $response = $this->actingAs($preparer)
            ->get(route('bac-secretariat.competitive-bidding.checklist.financial.template'));

        $response->assertOk()
            ->assertSee('J. RESUS')
            ->assertSee('P. SALAN')
            ->assertSee('R. LAYO')
            ->assertSee('Duly signed Bid Form as prescribe.')
            ->assertSee('Latest Income Tax Return')
            ->assertSee('opened only if the bidder has complied')
            ->assertSee('BACMEM-001')
            ->assertSee('BACMEM-002')
            ->assertSee('BACMEM-003')
            ->assertSee('Submit for Signatures')
            ->assertDontSee('Felicity Dream Square')
            ->assertDontSee('Canlupao Hardware');

        $this->assertSame(3, substr_count($response->getContent(), 'data-requirement-row>'));
    }

    public function test_only_completed_complying_financial_checklists_route_to_selected_bac_accounts(): void
    {
        $preparer = $this->account('BACSEC-001', User::ROLE_BAC_SECRETARIAT);
        $otherSecretariat = $this->account('BACSEC-002', User::ROLE_BAC_SECRETARIAT);
        $chair = $this->account('BACCHAIR-001', User::ROLE_BAC_CHAIR);
        $member = $this->account('BACMEM-001', User::ROLE_BAC_MEMBER);
        $record = app(CompetitiveBiddingSupportingDocumentService::class)->createRecord([
            'reference_number' => 'CB-2026-FIN-CHECK',
            'project_title' => 'Office Supplies',
            'status' => CompetitiveBiddingRecord::STATUS_OPEN,
            'created_by_user_id' => $preparer->id,
        ]);

        $row = '<div data-requirement-row><span class="initial-cell">ET</span></div>';
        $document = '<section><input name="financial-checklist-result" value="complying" checked><div data-checklist-project>Office Supplies</div><div data-checklist-bidder>Supplier A</div>%s</section>';
        $payload = [
            'template_key' => 'financial_envelope_checklist',
            'document_title' => 'Untrusted title',
            'tracking_number' => $record->reference_number,
            'document_html' => sprintf($document, str_repeat($row, 3)),
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
            'document_html' => sprintf($document, str_repeat($row, 2)),
        ]))->assertSessionHasErrors('document_html');

        $this->post(route('bac-secretariat.competitive-bidding.signature-documents.store'), array_replace($payload, [
            'document_html' => sprintf($document, str_repeat($row, 2).'<div data-requirement-row><span class="initial-cell"></span></div>'),
        ]))->assertSessionHasErrors('document_html');

        $this->post(route('bac-secretariat.competitive-bidding.signature-documents.store'), $payload)
            ->assertRedirect();

        $saved = CompetitiveBiddingSignatureDocument::query()->firstOrFail();
        $this->assertSame('Checklist of Financial Envelope Requirements', $saved->document_title);
        $this->assertSame(2, SignatureRequest::query()->where('document_id', $saved->id)->count());
        $this->assertTrue(SignatureRequest::query()
            ->where('document_id', $saved->id)
            ->where('signatory_slot', 'chairperson')
            ->where('requested_to_user_id', $chair->id)
            ->exists());
        $this->assertTrue(SignatureRequest::query()
            ->where('document_id', $saved->id)
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
