<?php

namespace Tests\Feature;

use App\Models\CompetitiveBiddingRecord;
use App\Models\CompetitiveBiddingSignatureDocument;
use App\Models\CompetitiveBiddingSupportingItem;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Services\CompetitiveBiddingSupportingDocumentService;
use App\Services\SignatureRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreBidConferenceMinutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_bacsec_002_can_open_the_official_minutes_template(): void
    {
        $preparer = $this->bacSecretariat('BACSEC-002');
        $other = $this->bacSecretariat('BACSEC-001');
        $this->bacMember('BACMEM-001');
        $this->bacMember('BACMEM-002');
        $this->bacMember('BACMEM-003');

        $this->actingAs($preparer)
            ->get(route('bac-secretariat.competitive-bidding.pre-bid-minutes.template'))
            ->assertOk()
            ->assertSee('Minutes of the Pre-Bid Conference')
            ->assertSee('PROCUREMENT OF MEDICINES')
            ->assertSee('BAC Member')
            ->assertSee('Signer account')
            ->assertSee('BACMEM-001')
            ->assertSee('BACMEM-002')
            ->assertSee('BACMEM-003')
            ->assertSee('Approved by:')
            ->assertSee('Submit for Signatures')
            ->assertSee('data-cb-routing-dialog="pre_bid_conference_minutes"', false)
            ->assertSee('data-pre-bid-save-word', false)
            ->assertSee('pre-bid-conference-minutes.doc')
            ->assertDontSee('Print / Save PDF')
            ->assertDontSee('Forward Signatories');

        $this->actingAs($other)
            ->get(route('bac-secretariat.competitive-bidding.pre-bid-minutes.template'))
            ->assertForbidden();
    }

    public function test_bacsec_002_can_route_minutes_and_the_signed_copy_reaches_the_bidding_file(): void
    {
        $preparer = $this->bacSecretariat('BACSEC-002');
        $reviewer = $this->bacSecretariat('BACSEC-001');
        $perpetua = $this->bacMember('BACMEM-001');
        $rogelio = $this->bacMember('BACMEM-002');
        $jessa = $this->bacMember('BACMEM-003');
        $record = app(CompetitiveBiddingSupportingDocumentService::class)->createRecord([
            'reference_number' => 'CB-2026-PRE-BID',
            'project_title' => 'Procurement of Medicines',
            'status' => CompetitiveBiddingRecord::STATUS_OPEN,
            'created_by_user_id' => $reviewer->id,
        ]);

        $item = $record->projectItems()->where('requirement_key', 'pre_bid_conference_minutes')->firstOrFail();
        $this->assertSame('BACSEC-002', $item->document_source);
        $this->assertFalse($item->is_required);
        $this->assertTrue($record->projectItems()->where('requirement_key', 'bid_opening_minutes_attendance')->exists());

        $payload = [
            'template_key' => 'pre_bid_conference_minutes',
            'document_title' => 'Untrusted title',
            'tracking_number' => $record->reference_number,
            'document_html' => '<main>Minutes of the Pre-Bid Conference</main>',
            'signatories' => json_encode([
                ['slot' => 'prepared_by', 'name' => $preparer->name, 'role_key' => 'bac_secretariat'],
                ['slot' => 'chairperson', 'name' => 'EDMAR T. TAMBIS', 'role_key' => 'bac_chairperson'],
                ['slot' => 'member_1', 'name' => 'JESSA G. RESUS', 'role_key' => 'bac_member', 'recipient_user_id' => $jessa->id],
                ['slot' => 'member_2', 'name' => 'ROGELIO A. LAYO', 'role_key' => 'bac_member', 'recipient_user_id' => $rogelio->id],
                ['slot' => 'member_3', 'name' => 'PERPETUA D. SALAN', 'role_key' => 'bac_member', 'recipient_user_id' => $perpetua->id],
            ]),
        ];

        $this->actingAs($reviewer)
            ->post(route('bac-secretariat.competitive-bidding.signature-documents.store'), $payload)
            ->assertForbidden();

        $duplicateMember = $payload;
        $signatories = json_decode($payload['signatories'], true);
        $signatories[2]['recipient_user_id'] = $rogelio->id;
        $duplicateMember['signatories'] = json_encode($signatories);

        $this->actingAs($preparer)
            ->post(route('bac-secretariat.competitive-bidding.signature-documents.store'), $duplicateMember)
            ->assertSessionHasErrors('signatories');

        $unassignedMember = $payload;
        $signatories[2]['recipient_user_id'] = null;
        $unassignedMember['signatories'] = json_encode($signatories);

        $this->actingAs($preparer)
            ->post(route('bac-secretariat.competitive-bidding.signature-documents.store'), $unassignedMember)
            ->assertSessionHasErrors('signatories');

        $nonMember = $payload;
        $signatories[2]['recipient_user_id'] = $reviewer->id;
        $nonMember['signatories'] = json_encode($signatories);

        $this->actingAs($preparer)
            ->post(route('bac-secretariat.competitive-bidding.signature-documents.store'), $nonMember)
            ->assertSessionHasErrors('signatories');

        $jessa->update(['status' => User::STATUS_INACTIVE]);

        $this->actingAs($preparer)
            ->post(route('bac-secretariat.competitive-bidding.signature-documents.store'), $payload)
            ->assertSessionHasErrors('signatories');

        $jessa->update(['status' => User::STATUS_ACTIVE]);

        $signatories = json_decode($payload['signatories'], true);
        $signatories[2]['name'] = 'JESSA G. RESUS JR.';
        $payload['signatories'] = json_encode($signatories);

        $this->actingAs($preparer)
            ->post(route('bac-secretariat.competitive-bidding.signature-documents.store'), $payload)
            ->assertRedirect();

        $document = CompetitiveBiddingSignatureDocument::query()->firstOrFail();
        $this->assertSame('Minutes of the Pre-Bid Conference', $document->document_title);
        $this->assertSame('JESSA G. RESUS JR.', $document->signatories[2]['name']);
        $this->assertTrue(SignatureRequest::query()
            ->where('document_id', $document->id)
            ->where('signatory_slot', 'prepared_by')
            ->where('requested_to_user_id', $preparer->id)
            ->exists());

        $signatureRequests = app(SignatureRequestService::class);

        foreach ([
            'member_1' => $jessa,
            'member_2' => $rogelio,
            'member_3' => $perpetua,
        ] as $slot => $recipient) {
            $request = SignatureRequest::query()
                ->where('document_id', $document->id)
                ->where('signatory_slot', $slot)
                ->firstOrFail();

            $this->assertSame($recipient->id, $request->requested_to_user_id);
            $this->assertSame(User::ROLE_BAC_MEMBER, $request->requested_to_role);
            $this->assertSame('BAC Member', $request->metadata['display_role_label']);
            $this->assertTrue($signatureRequests->userCanAccess($request, $recipient));

            foreach ([$jessa, $rogelio, $perpetua] as $otherMember) {
                if ($otherMember->id !== $recipient->id) {
                    $this->assertFalse($signatureRequests->userCanAccess($request, $otherMember));
                }
            }
        }

        $document->update(['status' => CompetitiveBiddingSignatureDocument::STATUS_FULLY_SIGNED]);

        $item->refresh();
        $this->assertSame(CompetitiveBiddingSupportingItem::STATUS_ATTACHED, $item->status);
        $this->assertSame($document->id, $item->source_id);
        $this->assertSame(CompetitiveBiddingSignatureDocument::class, $item->source_type);
    }

    private function bacSecretariat(string $userId): User
    {
        $user = User::where('user_id', $userId)->firstOrFail();
        $user->update([
            'role' => User::ROLE_BAC_SECRETARIAT,
            'office' => 'BAC Secretariat',
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);

        return $user->refresh();
    }

    private function bacMember(string $userId): User
    {
        $user = User::where('user_id', $userId)->first()
            ?? User::factory()->create([
                'user_id' => $userId,
                'role' => User::ROLE_BAC_MEMBER,
                'office' => 'BAC',
                'status' => User::STATUS_ACTIVE,
            ]);
        $user->update([
            'role' => User::ROLE_BAC_MEMBER,
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);

        return $user->refresh();
    }
}
