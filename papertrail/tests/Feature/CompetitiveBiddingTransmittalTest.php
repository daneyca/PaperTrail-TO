<?php

namespace Tests\Feature;

use App\Models\CompetitiveBiddingRecord;
use App\Models\CompetitiveBiddingSignatureDocument;
use App\Models\CompetitiveBiddingSupportingItem;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Services\CompetitiveBiddingSupportingDocumentService;
use App\Services\CompetitiveBiddingTransmittalService;
use App\Services\ElectronicSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompetitiveBiddingTransmittalTest extends TestCase
{
    use RefreshDatabase;

    public function test_checklist_tracks_signed_system_documents_and_verified_hard_copy_uploads(): void
    {
        Storage::fake('local');
        $bacsec001 = $this->bacsec001();
        $record = $this->record($bacsec001);
        $projectItem = $record->projectItems()->where('requirement_key', 'bid_evaluation_report')->firstOrFail();
        $supplier = app(CompetitiveBiddingSupportingDocumentService::class)->addSupplier($record, [
            'supplier_name' => 'Southern Trading',
            'created_by_user_id' => $bacsec001->id,
        ]);
        $supplierItem = $supplier->supportingItems()->where('requirement_key', 'business_permit')->firstOrFail();

        $checklist = app(CompetitiveBiddingTransmittalService::class)->checklist($record);
        $this->assertFalse($checklist->firstWhere('item_id', $projectItem->id)['ready']);
        $this->assertFalse($checklist->firstWhere('item_id', $supplierItem->id)['ready']);

        $document = CompetitiveBiddingSignatureDocument::create([
            'template_key' => 'bid_evaluation_report',
            'document_title' => 'Bid Evaluation Report',
            'tracking_number' => $record->reference_number,
            'document_html' => '<main>Bid Evaluation Report</main>',
            'signatories' => [],
            'prepared_by_user_id' => $bacsec001->id,
        ]);
        $document->update(['status' => CompetitiveBiddingSignatureDocument::STATUS_FULLY_SIGNED]);

        $this->actingAs($bacsec001)->post(
            route('bac-secretariat.competitive-bidding.supporting-documents.attachments.store', [$record, $supplierItem]),
            ['attachment' => UploadedFile::fake()->create('permit.pdf', 20, 'application/pdf')]
        )->assertRedirect();

        $checklist = app(CompetitiveBiddingTransmittalService::class)->checklist($record);
        $this->assertTrue($checklist->firstWhere('item_id', $projectItem->id)['ready']);
        $this->assertFalse($checklist->firstWhere('item_id', $supplierItem->id)['ready']);

        $this->actingAs($bacsec001)->patch(
            route('bac-secretariat.competitive-bidding.supporting-documents.items.update', [$record, $supplierItem]),
            ['status' => CompetitiveBiddingSupportingItem::STATUS_VERIFIED]
        )->assertRedirect();

        $checklist = app(CompetitiveBiddingTransmittalService::class)->checklist($record);
        $this->assertTrue($checklist->firstWhere('item_id', $supplierItem->id)['ready']);
        $this->assertSame('Southern Trading', $checklist->firstWhere('item_id', $supplierItem->id)['supplier_name']);

        $document->update(['status' => CompetitiveBiddingSignatureDocument::STATUS_RETURNED]);
        $checklist = app(CompetitiveBiddingTransmittalService::class)->checklist($record);
        $this->assertFalse($checklist->firstWhere('item_id', $projectItem->id)['ready']);
    }

    public function test_bacsec001_can_send_only_ready_attachments_to_the_mayors_account(): void
    {
        Storage::fake('local');
        $bacsec001 = $this->bacsec001();
        $record = $this->record($bacsec001);
        $mayor = $this->mayor();
        $item = $record->projectItems()->where('requirement_key', 'annual_investment_program')->firstOrFail();

        $this->actingAs($bacsec001)
            ->get(route('bac-secretariat.competitive-bidding.transmittal.create', $record))
            ->assertOk()
            ->assertSee('Attachment Status')
            ->assertSee('Annual Investment Program (AIP)');

        $this->from(route('bac-secretariat.competitive-bidding.transmittal.create', $record))
            ->post(route('bac-secretariat.competitive-bidding.transmittal.store', $record), $this->form($mayor))
            ->assertSessionHasErrors('confirm_packet');

        $this->actingAs($bacsec001)->post(
            route('bac-secretariat.competitive-bidding.supporting-documents.attachments.store', [$record, $item]),
            ['attachment' => UploadedFile::fake()->create('final-aip.pdf', 20, 'application/pdf')]
        )->assertRedirect();
        $this->actingAs($bacsec001)->patch(
            route('bac-secretariat.competitive-bidding.supporting-documents.items.update', [$record, $item]),
            ['status' => CompetitiveBiddingSupportingItem::STATUS_VERIFIED]
        )->assertRedirect();

        $response = $this->actingAs($bacsec001)->post(
            route('bac-secretariat.competitive-bidding.transmittal.store', $record),
            $this->form($mayor)
        );

        $document = CompetitiveBiddingSignatureDocument::where('template_key', 'coa_transmittal')->firstOrFail();
        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('bac-secretariat.competitive-bidding.transmittal.show', [$record, $document]));
        $this->assertSame($record->reference_number, $document->tracking_number);
        $this->assertStringContainsString('Annual Investment Program (AIP)', $document->document_html);
        $this->assertStringNotContainsString('Annual Procurement Plan (APP)', $document->document_html);
        $this->assertStringContainsString('Procurement of Office Supplies', $document->document_html);

        $signatureRequest = $document->signatureRequests()->firstOrFail();
        $this->assertSame($mayor->id, $signatureRequest->requested_to_user_id);
        $this->assertSame('mayor', $signatureRequest->signatory_slot);
        $this->assertContains($signatureRequest->status, [SignatureRequest::STATUS_PENDING, SignatureRequest::STATUS_NOTIFIED]);

        $snapshot = $document->document_html;
        $appItem = $record->projectItems()->where('requirement_key', 'annual_procurement_plan')->firstOrFail();
        $this->actingAs($bacsec001)->post(
            route('bac-secretariat.competitive-bidding.supporting-documents.attachments.store', [$record, $appItem]),
            ['attachment' => UploadedFile::fake()->create('final-app.pdf', 20, 'application/pdf')]
        )->assertRedirect();
        $this->actingAs($bacsec001)->patch(
            route('bac-secretariat.competitive-bidding.supporting-documents.items.update', [$record, $appItem]),
            ['status' => CompetitiveBiddingSupportingItem::STATUS_VERIFIED]
        )->assertRedirect();
        $this->actingAs($bacsec001)
            ->get(route('bac-secretariat.competitive-bidding.transmittal.create', $record))
            ->assertOk()
            ->assertSee('2 in file / 25 listed');
        $this->assertSame($snapshot, $document->fresh()->document_html);

        $this->actingAs($mayor)
            ->get(route('signature-requests.show', $signatureRequest))
            ->assertOk()
            ->assertSee('COA cover letter')
            ->assertSee('data-coa-signature-slot="mayor"', false)
            ->assertSee('Annual Investment Program (AIP)');
        $this->assertTrue(app(ElectronicSignatureService::class)->canSign(
            $mayor, $document, 'competitive_bidding_document', 'confirmed', $signatureRequest,
        ));

        $this->actingAs($bacsec001)
            ->get(route('bac-secretariat.competitive-bidding.transmittal.show', [$record, $document]))
            ->assertOk()
            ->assertDontSee('onclick="window.print()"', false);
    }

    public function test_extra_physical_attachments_require_confirmation_and_wrong_mayor_is_rejected(): void
    {
        $bacsec001 = $this->bacsec001();
        $record = $this->record($bacsec001);
        $mayor = $this->mayor();
        $data = $this->form($mayor);
        $data['additional_attachments'] = 'MDC Resolution No. 2026-01';

        $this->actingAs($bacsec001)
            ->from(route('bac-secretariat.competitive-bidding.transmittal.create', $record))
            ->post(route('bac-secretariat.competitive-bidding.transmittal.store', $record), $data)
            ->assertSessionHasErrors('confirm_additional');

        $data['confirm_additional'] = '1';
        $data['mayor_user_id'] = $bacsec001->id;
        $this->actingAs($bacsec001)
            ->post(route('bac-secretariat.competitive-bidding.transmittal.store', $record), $data)
            ->assertSessionHasErrors('mayor_user_id');

        $data['mayor_user_id'] = $mayor->id;
        $this->actingAs($bacsec001)
            ->post(route('bac-secretariat.competitive-bidding.transmittal.store', $record), $data)
            ->assertSessionHasNoErrors();

        $document = CompetitiveBiddingSignatureDocument::where('template_key', 'coa_transmittal')->firstOrFail();
        $this->assertStringContainsString('MDC Resolution No. 2026-01', $document->document_html);
    }

    public function test_other_accounts_cannot_prepare_or_open_another_records_letter(): void
    {
        $bacsec001 = $this->bacsec001();
        $record = $this->record($bacsec001);
        $mayor = $this->mayor();
        $otherRecord = app(CompetitiveBiddingSupportingDocumentService::class)->createRecord([
            'reference_number' => 'CB-2026-002',
            'project_title' => 'Different Project',
            'status' => CompetitiveBiddingRecord::STATUS_OPEN,
            'created_by_user_id' => $bacsec001->id,
        ]);

        $bacsec003 = User::where('user_id', 'BACSEC-003')->firstOrFail();
        $bacsec003->update(['role' => User::ROLE_BAC_SECRETARIAT, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);
        $this->actingAs($bacsec003)
            ->get(route('bac-secretariat.competitive-bidding.transmittal.create', $record))
            ->assertForbidden();
        $this->actingAs($bacsec003)
            ->post(route('bac-secretariat.competitive-bidding.transmittal.store', $record), $this->form($mayor))
            ->assertForbidden();

        $this->actingAs($bacsec001)->post(
            route('bac-secretariat.competitive-bidding.transmittal.store', $record),
            [...$this->form($mayor), 'additional_attachments' => 'Physical appendix', 'confirm_additional' => '1']
        )->assertSessionHasNoErrors();
        $document = CompetitiveBiddingSignatureDocument::where('template_key', 'coa_transmittal')->firstOrFail();

        $this->actingAs($bacsec001)
            ->get(route('bac-secretariat.competitive-bidding.transmittal.show', [$otherRecord, $document]))
            ->assertNotFound();
        $this->actingAs($bacsec003)
            ->get(route('bac-secretariat.competitive-bidding.transmittal.show', [$record, $document]))
            ->assertForbidden();

        $mayorRequest = $document->signatureRequests()->firstOrFail();
        $this->actingAs($mayor);
        $declined = app(ElectronicSignatureService::class)->declineSignature(
            $document, $mayor, 'competitive_bidding_document', 'confirmed', 'Please update the recipient.', null, $mayorRequest,
        );
        $this->assertTrue($declined['ok']);
        $this->assertSame(CompetitiveBiddingSignatureDocument::STATUS_RETURNED, $document->fresh()->status);
    }

    private function bacsec001(): User
    {
        $user = User::where('user_id', 'BACSEC-001')->firstOrFail();
        $user->update(['role' => User::ROLE_BAC_SECRETARIAT, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);

        return $user;
    }

    private function mayor(): User
    {
        $user = User::firstOrCreate(['user_id' => 'HOPE-001'], [
            'name' => 'Test Municipal Mayor',
            'email' => 'hope-001@example.test',
            'password' => 'password',
            'role' => User::ROLE_APPROVING_AUTHORITY,
            'status' => User::STATUS_ACTIVE,
        ]);
        $user->update(['role' => User::ROLE_APPROVING_AUTHORITY, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);

        return $user;
    }

    private function record(User $user): CompetitiveBiddingRecord
    {
        return app(CompetitiveBiddingSupportingDocumentService::class)->createRecord([
            'reference_number' => 'CB-2026-001',
            'project_title' => 'Procurement of Office Supplies',
            'status' => CompetitiveBiddingRecord::STATUS_OPEN,
            'created_by_user_id' => $user->id,
        ]);
    }

    private function form(User $mayor): array
    {
        return [
            'letter_date' => '2026-11-04',
            'recipient_name' => 'Audit Team Leader',
            'recipient_title' => 'State Auditor',
            'recipient_office' => 'Commission on Audit',
            'recipient_address' => 'Southern Leyte',
            'salutation' => 'Dear Auditor:',
            'project_title' => 'Procurement of Office Supplies',
            'opening' => 'The undersigned respectfully submits the supporting documents for',
            'closing' => 'Anticipating your favorable action.',
            'confirm_packet' => '1',
            'mayor_user_id' => $mayor->id,
        ];
    }
}
