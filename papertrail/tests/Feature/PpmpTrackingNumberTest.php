<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserHasPermission;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Services\DocumentReferenceNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PpmpTrackingNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_draft_assigns_a_unique_stable_tracking_number(): void
    {
        $user = $this->officeUser();
        $this->actingAs($user)->post(route('head-office.ppmp.store'), $this->payload('draft'))
            ->assertSessionHasNoErrors()->assertRedirect();
        $document = ProcurementDocument::where('document_type', 'PPMP')->firstOrFail();
        $number = $document->tracking_number;
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-PPMP-NUMBER-TEST-0001$/', $number);
        $this->assertSame($number, $document->displayNumber());
        $this->assertSame(ProcurementDocument::STATUS_PPMP_DRAFT, $document->status);

        $this->actingAs($user)->get(route('head-office.ppmp.index'))->assertOk()
            ->assertSee($number)->assertDontSee('Document #' . $document->id);

        $this->post(route('head-office.ppmp.store'), $this->payload('draft'))->assertSessionHasNoErrors()->assertRedirect();
        $next = ProcurementDocument::where('document_type', 'PPMP')->latest('id')->firstOrFail();
        $this->assertNotSame($number, $next->tracking_number);
        app(DocumentReferenceNumberService::class)->assign($document, DocumentReferenceNumberService::TYPE_PPMP, now()->addMonth());
        $this->assertSame($number, $document->tracking_number);
    }

    public function test_sending_for_signature_uses_the_tracking_number_from_first_save(): void
    {
        $user = $this->officeUser();
        $this->actingAs($user)->post(route('head-office.ppmp.store'), $this->payload('submit'))
            ->assertSessionHasNoErrors()->assertRedirect();
        $document = ProcurementDocument::where('document_type', 'PPMP')->firstOrFail();
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-PPMP-NUMBER-TEST-0001$/', $document->tracking_number);
        $this->assertSame(ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES, $document->status);
        $requests = SignatureRequest::forDocument('ppmp', $document->id)->get();
        $this->assertNotEmpty($requests);
        foreach ($requests as $request) {
            $this->assertSame($document->tracking_number, $request->tracking_number);
        }
    }

    public function test_backfill_numbers_existing_ppmps_without_renumbering_existing_references(): void
    {
        $user = $this->officeUser();
        $numbered = ProcurementDocument::create([
            'document_type' => 'PPMP', 'status' => ProcurementDocument::STATUS_PPMP_DRAFT,
            'title' => 'Already numbered', 'submitting_office_id' => $user->office_id, 'fiscal_year' => 2026,
        ]);
        $originalNumber = $numbered->tracking_number;
        $updatedAt = '2026-11-09 10:00:00';
        $id = DB::table('procurement_documents')->insertGetId([
            'document_type' => 'PPMP', 'tracking_number' => null, 'document_reference_number' => null,
            'title' => 'Old pending PPMP', 'status' => ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES,
            'submitting_office_id' => $user->office_id, 'fiscal_year' => 2026,
            'created_at' => $updatedAt, 'updated_at' => $updatedAt,
        ]);
        $legacyId = DB::table('procurement_documents')->insertGetId([
            'document_type' => 'PPMP', 'tracking_number' => 'PPMP-2026-LEGACY-0001', 'title' => 'Legacy number',
            'status' => ProcurementDocument::STATUS_PPMP_DRAFT, 'fiscal_year' => 2026, 'created_at' => $updatedAt, 'updated_at' => $updatedAt,
        ]);
        $requestId = DB::table('signature_requests')->insertGetId([
            'request_uuid' => (string) Str::uuid(), 'document_type' => 'ppmp', 'document_id' => $id,
            'tracking_number' => 'Document #' . $id, 'status' => 'notified', 'created_at' => $updatedAt, 'updated_at' => $updatedAt,
        ]);

        $migration = require database_path('migrations/2026_11_10_000001_number_existing_unnumbered_ppmps.php');
        $migration->up();
        $document = ProcurementDocument::findOrFail($id);
        $this->assertMatchesRegularExpression('/^2026-11-PPMP-NUMBER-TEST-\d{4}$/', $document->tracking_number);
        $this->assertSame(ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES, $document->status);
        $this->assertSame($updatedAt, $document->updated_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('signature_requests', ['id' => $requestId, 'tracking_number' => $document->tracking_number, 'status' => 'notified']);
        $this->assertSame($originalNumber, $numbered->refresh()->tracking_number);
        $this->assertDatabaseHas('procurement_documents', ['id' => $legacyId, 'tracking_number' => 'PPMP-2026-LEGACY-0001', 'document_reference_number' => null]);

        $migration->up();
        $this->assertSame($document->tracking_number, $document->refresh()->tracking_number);
    }

    public function test_each_office_has_its_own_yearly_sequence_that_continues_across_months_and_deletions(): void
    {
        $user = $this->officeUser();
        $other = Office::create(['code' => 'MPDO', 'name' => 'Planning Office', 'type' => Office::TYPE_END_USER]);
        $first = $this->numberedDocument($user->office_id, '2026-01-05');
        $otherFirst = $this->numberedDocument($other->id, '2026-11-01');
        $second = $this->numberedDocument($user->office_id, '2026-11-02');
        $this->assertSame('2026-01-PPMP-NUMBER-TEST-0001', $first->tracking_number);
        $this->assertSame('2026-11-PPMP-MPDO-0001', $otherFirst->tracking_number);
        $this->assertSame('2026-11-PPMP-NUMBER-TEST-0002', $second->tracking_number);
        $second->delete();
        $this->assertSame('2026-12-PPMP-NUMBER-TEST-0003', $this->numberedDocument($user->office_id, '2026-12-01')->tracking_number);
        $this->assertSame('2027-01-PPMP-NUMBER-TEST-0001', $this->numberedDocument($user->office_id, '2027-01-01')->tracking_number);
        $this->assertSame('2026-11-PR-0001', $this->numberedDocument($user->office_id, '2026-11-01', 'PR')->tracking_number);
        $this->assertSame('2026-11-PR-0002', $this->numberedDocument($other->id, '2026-11-02', 'PR')->tracking_number);
    }

    public function test_office_number_migration_updates_existing_references_and_keeps_signed_snapshots_intact(): void
    {
        $user = $this->officeUser();
        $oldReference = '2026-11-PPMP-0003';
        $stamp = '2026-11-09 10:00:00';
        $id = DB::table('procurement_documents')->insertGetId([
            'document_type' => 'PPMP', 'tracking_number' => $oldReference, 'document_reference_number' => $oldReference,
            'ppmp_no' => $oldReference, 'sequence_number' => 3, 'created_year' => 2026, 'created_month' => 11,
            'title' => 'Existing PPMP', 'status' => ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES,
            'submitting_office_id' => $user->office_id, 'fiscal_year' => 2026, 'created_at' => $stamp, 'updated_at' => $stamp,
        ]);
        $requestId = DB::table('signature_requests')->insertGetId([
            'request_uuid' => (string) Str::uuid(), 'document_type' => 'ppmp', 'document_id' => $id,
            'tracking_number' => $oldReference, 'status' => 'signed', 'created_at' => $stamp, 'updated_at' => $stamp,
        ]);
        $signatureId = DB::table('electronic_signatures')->insertGetId([
            'signature_code' => 'OFFICE-REFERENCE-TEST', 'signature_request_id' => $requestId,
            'document_type' => 'ppmp', 'document_id' => $id, 'tracking_number' => $oldReference,
            'signature_status' => 'signed', 'created_at' => $stamp, 'updated_at' => $stamp,
        ]);
        $snapshotHash = hash('sha256', 'original signed content');
        $snapshotId = DB::table('signed_document_snapshots')->insertGetId([
            'electronic_signature_id' => $signatureId, 'document_type' => 'ppmp', 'document_id' => $id,
            'tracking_number' => $oldReference, 'text_snapshot' => 'original signed content', 'snapshot_hash' => $snapshotHash,
            'created_at' => $stamp, 'updated_at' => $stamp,
        ]);
        $pr = $this->numberedDocument($user->office_id, '2026-11-01', 'PR');
        $prReference = $pr->tracking_number;
        $migration = require database_path('migrations/2026_11_10_000000_scope_ppmp_tracking_numbers_by_office.php');
        $migration->up();
        $newReference = '2026-11-PPMP-NUMBER-TEST-0001';
        $this->assertDatabaseHas('procurement_documents', ['id' => $id, 'tracking_number' => $newReference,
            'document_reference_number' => $newReference, 'ppmp_no' => $newReference, 'sequence_number' => 1, 'updated_at' => $stamp,
            'status' => ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES]);
        $this->assertDatabaseHas('signature_requests', ['id' => $requestId, 'tracking_number' => $newReference, 'status' => 'signed']);
        $this->assertDatabaseHas('electronic_signatures', ['id' => $signatureId, 'tracking_number' => $oldReference, 'signature_status' => 'signed']);
        $this->assertDatabaseHas('signed_document_snapshots', ['id' => $snapshotId, 'tracking_number' => $oldReference,
            'text_snapshot' => 'original signed content', 'snapshot_hash' => $snapshotHash]);
        $this->assertSame($prReference, $pr->refresh()->tracking_number);
        $second = $this->numberedDocument($user->office_id, '2026-11-10');
        $this->assertSame('2026-11-PPMP-NUMBER-TEST-0002', $second->tracking_number);
        $migration->up();
        $this->assertSame($second->tracking_number, $second->refresh()->tracking_number);
        $this->assertSame('2026-11-PPMP-NUMBER-TEST-0003', $this->numberedDocument($user->office_id, '2026-11-11')->tracking_number);
    }

    public function test_long_office_codes_fit_tracking_and_audit_reference_columns(): void
    {
        $user = $this->officeUser();
        $office = $user->assignedOffice;
        $office->update(['code' => str_repeat('A', 50)]);
        $document = $this->numberedDocument($office->id, '2026-11-01');
        $this->assertSame('2026-11-PPMP-' . str_repeat('A', 50) . '-0001', $document->tracking_number);
        \App\Services\AuditLogger::log('PPMP Numbering', 'Test Reference', 'Office PPMP created.', $document);
        $this->assertDatabaseHas('audit_logs', ['document_id' => $document->id, 'document_reference_number' => $document->tracking_number]);
    }

    private function numberedDocument(int $officeId, string $date, string $type = 'PPMP'): ProcurementDocument
    {
        $document = new ProcurementDocument(['document_type' => $type, 'title' => 'Office planning document',
            'status' => ProcurementDocument::STATUS_PPMP_DRAFT, 'submitting_office_id' => $officeId,
            'fiscal_year' => (int) substr($date, 0, 4)]);
        $document->forceFill(['created_at' => $date . ' 10:00:00'])->save();
        return $document;
    }

    private function officeUser(): User
    {
        $this->withoutMiddleware(EnsureUserHasPermission::class);
        $office = Office::create(['code' => 'NUMBER-TEST', 'name' => 'Number Test Office', 'type' => Office::TYPE_END_USER, 'status' => Office::STATUS_ACTIVE, 'is_requesting_office' => true]);
        return User::factory()->create(['user_id' => 'HEAD-NUMBER', 'role' => User::ROLE_HEAD_OFFICE, 'office_id' => $office->id, 'status' => User::STATUS_ACTIVE]);
    }

    private function payload(string $action): array
    {
        return ['save_action' => $action, 'fiscal_year' => 2026, 'ppmp_plan_type' => 'indicative',
            'items' => [['general_description' => 'Office supplies', 'project_type' => 'Goods', 'quantity_size' => '10 reams',
                'procurement_mode' => 'Small Value', 'source_of_funds' => 'General Fund', 'estimated_budget' => '40000']],
        ];
    }
}
