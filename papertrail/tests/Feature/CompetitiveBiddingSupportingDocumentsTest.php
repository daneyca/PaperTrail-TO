<?php

namespace Tests\Feature;

use App\Models\CompetitiveBiddingRecord;
use App\Models\CompetitiveBiddingSignatureDocument;
use App\Models\CompetitiveBiddingSupplier;
use App\Models\CompetitiveBiddingSupportingItem;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\CompetitiveBiddingSupportingDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompetitiveBiddingSupportingDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_bacsec_001_creates_a_separate_supporting_document_file(): void
    {
        $user = $this->bacSecretariatUser('BACSEC-001');

        $response = $this->actingAs($user)->post(route('bac-secretariat.competitive-bidding.supporting-documents.store'), [
            'reference_number' => 'CB-2026-001',
            'project_title' => 'Procurement of Office Supplies',
            'approved_budget' => 450000,
            'bid_opening_date' => '2026-10-20',
        ]);

        $record = CompetitiveBiddingRecord::where('reference_number', 'CB-2026-001')->firstOrFail();

        $response->assertRedirect(route('bac-secretariat.competitive-bidding.supporting-documents.show', $record));
        $this->assertSame(24, $record->projectItems()->count());
        $this->assertTrue(Route::has('bac-secretariat.competitive-bidding.checklist.technical'));
        $this->assertTrue(Route::has('bac-secretariat.competitive-bidding.checklist.financial'));

        $this->actingAs($user)
            ->get(route('bac-secretariat.competitive-bidding.supporting-documents.show', $record))
            ->assertOk()
            ->assertSee('Project and BAC Documents')
            ->assertSee('Completed Checklist of Technical and Financial Requirements');
    }

    public function test_each_supplier_receives_its_own_attachment_guide(): void
    {
        $user = $this->bacSecretariatUser('BACSEC-001');
        $record = $this->createRecord($user);

        $response = $this->actingAs($user)->post(
            route('bac-secretariat.competitive-bidding.supporting-documents.suppliers.store', $record),
            ['supplier_name' => 'Southern Office Supplies']
        );

        $supplier = $record->suppliers()->firstOrFail();

        $response->assertRedirect();
        $this->assertSame(20, $supplier->supportingItems()->count());
        $this->assertFalse($supplier->supportingItems()->where('guide_number', '20')->exists());
        $this->assertTrue($supplier->supportingItems()->where('guide_number', '38')->firstOrFail()->isPostAwardConditional());
    }

    public function test_attachment_is_required_before_a_guide_item_can_be_verified(): void
    {
        Storage::fake('local');
        $user = $this->bacSecretariatUser('BACSEC-001');
        $record = $this->createRecord($user);
        $item = $record->projectItems()->firstOrFail();

        $this->actingAs($user)->patch(
            route('bac-secretariat.competitive-bidding.supporting-documents.items.update', [$record, $item]),
            ['status' => CompetitiveBiddingSupportingItem::STATUS_VERIFIED]
        )->assertSessionHasErrors('status');

        $this->actingAs($user)->post(
            route('bac-secretariat.competitive-bidding.supporting-documents.attachments.store', [$record, $item]),
            ['attachment' => UploadedFile::fake()->create('aip-2026.pdf', 120, 'application/pdf')]
        )->assertRedirect();

        $this->assertSame(CompetitiveBiddingSupportingItem::STATUS_ATTACHED, $item->refresh()->status);

        $this->actingAs($user)->patch(
            route('bac-secretariat.competitive-bidding.supporting-documents.items.update', [$record, $item]),
            ['status' => CompetitiveBiddingSupportingItem::STATUS_VERIFIED]
        )->assertRedirect();

        $this->assertSame(CompetitiveBiddingSupportingItem::STATUS_VERIFIED, $item->refresh()->status);
    }

    public function test_missing_supplier_document_is_flagged_and_performance_bond_activates_after_award(): void
    {
        $user = $this->bacSecretariatUser('BACSEC-001');
        $record = $this->createRecord($user);
        $supplier = app(CompetitiveBiddingSupportingDocumentService::class)->addSupplier($record, [
            'supplier_name' => 'Ballpen Trading',
            'review_status' => CompetitiveBiddingSupplier::STATUS_UNDER_REVIEW,
            'created_by_user_id' => $user->id,
        ]);
        $supplier->load('supportingItems.supplier');
        $performanceBond = $supplier->supportingItems->firstWhere('requirement_key', 'performance_bond');

        $this->assertFalse($performanceBond->isEffectivelyRequired());

        $missingItem = $supplier->supportingItems->firstWhere('requirement_key', 'business_permit');
        $missingItem->update([
            'status' => CompetitiveBiddingSupportingItem::STATUS_MISSING,
            'remarks' => 'No valid permit submitted.',
        ]);

        $summary = app(CompetitiveBiddingSupportingDocumentService::class)->supplierSummary($supplier->fresh('supportingItems.supplier'));
        $this->assertSame('For Disqualification Review', $summary['review_label']);

        $supplier->update(['review_status' => CompetitiveBiddingSupplier::STATUS_AWARDED]);
        $this->assertTrue($performanceBond->fresh('supplier')->isEffectivelyRequired());
    }

    public function test_other_bac_secretariat_accounts_cannot_manage_the_bacsec_001_file(): void
    {
        $user = $this->bacSecretariatUser('BACSEC-003');

        $this->actingAs($user)
            ->get(route('bac-secretariat.competitive-bidding.supporting-documents.index'))
            ->assertForbidden();
    }

    public function test_bacsec_003_submits_a_project_document_to_bacsec_001_for_checking(): void
    {
        Storage::fake('local');
        $bacsec001 = $this->bacSecretariatUser('BACSEC-001');
        $bacsec003 = $this->bacSecretariatUser('BACSEC-003');
        $record = $this->createRecord($bacsec001);
        $item = $record->projectItems()->where('requirement_key', 'annual_investment_program')->firstOrFail();

        $this->actingAs($bacsec003)
            ->get(route('bac-secretariat.competitive-bidding.supporting-submissions.index'))
            ->assertOk()
            ->assertSee('Submit Bidding Documents')
            ->assertSee($record->reference_number);

        $this->actingAs($bacsec003)->post(
            route('bac-secretariat.competitive-bidding.supporting-submissions.store', [$record, $item]),
            ['attachment' => UploadedFile::fake()->create('final-aip.pdf', 120, 'application/pdf')]
        )->assertRedirect();

        $item->refresh();
        $this->assertSame(CompetitiveBiddingSupportingItem::STATUS_ATTACHED, $item->status);
        $this->assertSame($bacsec003->id, $item->submitted_by_user_id);
        $this->assertSame(1, $item->source_version);
        $this->assertTrue($item->attachments()->exists());
        $this->assertTrue(SystemNotification::where('user_id', $bacsec001->id)->where('related_id', $item->id)->exists());

        $this->actingAs($bacsec003)
            ->get(route('bac-secretariat.competitive-bidding.supporting-submissions.show', $record))
            ->assertOk()
            ->assertSee('BACSEC-003 Submission Queue')
            ->assertSee('final-aip.pdf');

        $this->actingAs($bacsec001)
            ->get(route('bac-secretariat.competitive-bidding.supporting-documents.show', $record))
            ->assertOk()
            ->assertSee('BACSEC-003')
            ->assertSee('Version 1');

        $this->actingAs($bacsec001)->patch(
            route('bac-secretariat.competitive-bidding.supporting-documents.items.update', [$record, $item]),
            [
                'status' => CompetitiveBiddingSupportingItem::STATUS_FOR_CORRECTION,
                'remarks' => 'Replace the unsigned final page.',
            ]
        )->assertRedirect();

        $this->assertTrue(SystemNotification::query()
            ->where('user_id', $bacsec003->id)
            ->where('related_id', $item->id)
            ->where('title', 'Supporting document needs attention')
            ->exists());

        $this->actingAs($bacsec003)
            ->get(route('bac-secretariat.competitive-bidding.supporting-submissions.show', $record))
            ->assertOk()
            ->assertSee('Replace the unsigned final page.');
    }

    public function test_bacsec_003_cannot_submit_a_bacsec_001_owned_requirement(): void
    {
        Storage::fake('local');
        $bacsec001 = $this->bacSecretariatUser('BACSEC-001');
        $bacsec003 = $this->bacSecretariatUser('BACSEC-003');
        $record = $this->createRecord($bacsec001);
        $item = $record->projectItems()->where('requirement_key', 'bid_evaluation_report')->firstOrFail();

        $this->actingAs($bacsec003)->post(
            route('bac-secretariat.competitive-bidding.supporting-submissions.store', [$record, $item]),
            ['attachment' => UploadedFile::fake()->create('report.pdf', 120, 'application/pdf')]
        )->assertForbidden();
    }

    public function test_fully_signed_system_document_is_linked_to_the_matching_bidding_file(): void
    {
        $bacsec001 = $this->bacSecretariatUser('BACSEC-001');
        $record = $this->createRecord($bacsec001);
        $item = $record->projectItems()->where('requirement_key', 'bid_evaluation_report')->firstOrFail();
        $document = CompetitiveBiddingSignatureDocument::create([
            'template_key' => 'bid_evaluation_report',
            'document_title' => 'Bid Evaluation Report',
            'tracking_number' => $record->reference_number,
            'document_html' => '<main>Bid Evaluation Report</main>',
            'signatories' => [],
            'status' => CompetitiveBiddingSignatureDocument::STATUS_PENDING_SIGNATURES,
            'prepared_by_user_id' => $bacsec001->id,
        ]);

        $document->update(['status' => CompetitiveBiddingSignatureDocument::STATUS_FULLY_SIGNED]);

        $item->refresh();
        $this->assertSame(CompetitiveBiddingSupportingItem::STATUS_ATTACHED, $item->status);
        $this->assertSame(CompetitiveBiddingSignatureDocument::class, $item->source_type);
        $this->assertSame($document->id, $item->source_id);
        $this->assertTrue($item->hasSubmittedEvidence());

        $this->actingAs($bacsec001)
            ->get(route('bac-secretariat.competitive-bidding.supporting-documents.system-document', [$record, $item]))
            ->assertOk()
            ->assertSee('Bid Evaluation Report');

        $this->actingAs($bacsec001)->patch(
            route('bac-secretariat.competitive-bidding.supporting-documents.items.update', [$record, $item]),
            ['status' => CompetitiveBiddingSupportingItem::STATUS_VERIFIED]
        )->assertRedirect();

        $this->assertSame(CompetitiveBiddingSupportingItem::STATUS_VERIFIED, $item->refresh()->status);
    }

    private function bacSecretariatUser(string $userId): User
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

    private function createRecord(User $user): CompetitiveBiddingRecord
    {
        return app(CompetitiveBiddingSupportingDocumentService::class)->createRecord([
            'reference_number' => 'CB-' . fake()->unique()->numerify('2026-###'),
            'project_title' => 'Procurement of Office Supplies',
            'status' => CompetitiveBiddingRecord::STATUS_OPEN,
            'created_by_user_id' => $user->id,
        ]);
    }
}
