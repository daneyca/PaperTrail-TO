<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserHasPermission;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class HeadOfficePpmpDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_ppmp_cannot_be_deleted_even_before_bac_submission(): void
    {
        $this->withoutMiddleware(EnsureUserHasPermission::class);
        $office = Office::create(['code' => 'DELETE-TEST', 'name' => 'Delete Test Office', 'type' => Office::TYPE_END_USER, 'status' => Office::STATUS_ACTIVE, 'is_requesting_office' => true]);
        $user = User::factory()->create(['user_id' => 'HEAD-DELETE', 'role' => User::ROLE_HEAD_OFFICE, 'office_id' => $office->id, 'status' => User::STATUS_ACTIVE]);
        $documentId = $this->createPpmp($office, ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED);
        $requestId = DB::table('signature_requests')->insertGetId([
            'request_uuid' => (string) Str::uuid(),
            'document_type' => 'ppmp',
            'document_id' => $documentId,
            'status' => 'signed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $signatureId = DB::table('electronic_signatures')->insertGetId([
            'signature_code' => 'TEST-PPMP-DELETE',
            'signature_request_id' => $requestId,
            'document_type' => 'ppmp',
            'document_id' => $documentId,
            'signature_status' => 'signed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('signed_document_snapshots')->insert([
            'electronic_signature_id' => $signatureId,
            'document_type' => 'ppmp',
            'document_id' => $documentId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->get(route('head-office.ppmp.index'))
            ->assertOk()
            ->assertDontSee('data-confirm-title="Delete PPMP?"', false);

        $this->actingAs($user)->delete(route('head-office.ppmp.destroy', $documentId))
            ->assertRedirect(route('head-office.ppmp.index'));

        $this->assertDatabaseHas('procurement_documents', ['id' => $documentId]);
        $this->assertDatabaseHas('signature_requests', ['id' => $requestId]);
        $this->assertDatabaseHas('electronic_signatures', ['id' => $signatureId]);
        $this->assertDatabaseHas('signed_document_snapshots', ['electronic_signature_id' => $signatureId]);
    }

    public function test_ppmp_already_submitted_to_bac_cannot_be_deleted(): void
    {
        $this->withoutMiddleware(EnsureUserHasPermission::class);
        $office = Office::create(['code' => 'BAC-TEST', 'name' => 'BAC Test Office', 'type' => Office::TYPE_END_USER, 'status' => Office::STATUS_ACTIVE, 'is_requesting_office' => true]);
        $user = User::factory()->create(['user_id' => 'HEAD-BAC-TEST', 'role' => User::ROLE_HEAD_OFFICE, 'office_id' => $office->id, 'status' => User::STATUS_ACTIVE]);
        $documentId = $this->createPpmp($office, ProcurementDocument::STATUS_PENDING_PPMP_REVIEW);

        $this->actingAs($user)->delete(route('head-office.ppmp.destroy', $documentId))
            ->assertRedirect(route('head-office.ppmp.index'));

        $this->assertDatabaseHas('procurement_documents', ['id' => $documentId]);
    }

    private function createPpmp(Office $office, string $status): int
    {
        return DB::table('procurement_documents')->insertGetId([
            'tracking_number' => 'PPMP-DELETE-' . Str::random(8),
            'document_type' => 'PPMP',
            'title' => 'Test PPMP',
            'fiscal_year' => 2026,
            'status' => $status,
            'submitting_office_id' => $office->id,
            'total_amount' => 2324,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
