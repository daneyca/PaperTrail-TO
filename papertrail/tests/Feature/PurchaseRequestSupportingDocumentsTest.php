<?php

namespace Tests\Feature;

use App\Models\ElectronicSignature;
use App\Models\Office;
use App\Models\Permission;
use App\Models\ProcurementDocument;
use App\Models\Role;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Services\BacsecMpdoWorkspaceService;
use App\Services\DocumentResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseRequestSupportingDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_staff_can_upload_to_an_unsigned_submitted_pr_and_files_lock_after_the_first_signature(): void
    {
        $staff = $this->staff('PRFILES');
        $document = $this->document($staff);
        $url = route('document-attachments.store', ['documentType' => 'purchase_request', 'documentId' => $document->id]);
        $this->actingAs($staff)->get(route('head-office.pr.show', $document))->assertOk()
            ->assertSee('class="attachments-upload-form"', false);
        $this->post($url, ['attachment' => UploadedFile::fake()->create('price-reference.pdf', 20, 'application/pdf')])
            ->assertSessionHasNoErrors()->assertRedirect();
        $attachment = $document->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->file_path);
        $this->assertSame($staff->id, $attachment->uploaded_by_user_id);
        $this->assertSame(ProcurementDocument::STATUS_PR_PENDING_SIGNATORIES, $document->refresh()->status);
        $this->patch(route('document-attachments.update', $attachment), ['description' => 'Planning reference'])->assertRedirect();
        $this->get(route('document-attachments.download', $attachment))->assertOk()->assertDownload('price-reference.pdf');

        SignatureRequest::create(['request_uuid' => (string) Str::uuid(), 'document_type' => 'purchase_request',
            'document_id' => $document->id, 'requested_to_user_id' => $staff->id, 'is_required' => true,
            'status' => SignatureRequest::STATUS_SIGNED, 'signed_at' => now()]);
        $this->get(route('head-office.pr.show', $document))->assertOk()
            ->assertDontSee('class="attachments-upload-form"', false)->assertSee('before the first signature');
        $this->post($url, ['attachment' => UploadedFile::fake()->create('late.pdf', 20, 'application/pdf')])->assertForbidden();
        $this->patch(route('document-attachments.update', $attachment), ['description' => 'Changed'])->assertForbidden();
        $this->delete(route('document-attachments.destroy', $attachment))->assertForbidden();
        $this->get(route('document-attachments.download', $attachment))->assertOk();
        $this->assertSame('Planning reference', $attachment->refresh()->description);
        $this->assertSame(1, $document->attachments()->count());
    }

    public function test_office_scope_and_signed_documents_are_protected_while_draft_and_returned_prs_allow_files(): void
    {
        $staff = $this->staff('OWNER');
        $other = $this->staff('OTHERPR');
        $document = $this->document($staff);
        $resolver = app(DocumentResolverService::class);
        $url = route('document-attachments.store', ['documentType' => 'purchase_request', 'documentId' => $document->id]);
        $this->actingAs($other)->post($url, ['attachment' => UploadedFile::fake()->create('foreign.pdf', 20, 'application/pdf')])->assertForbidden();
        $signature = ElectronicSignature::create([
            'signature_uuid' => (string) Str::uuid(), 'signature_code' => 'PR-FILE-TEST',
            'document_type' => 'purchase_request', 'document_id' => $document->id, 'document_label' => 'Purchase Request',
            'signer_user_id' => $staff->id, 'signer_name' => $staff->name, 'signer_role' => $staff->role,
            'signature_action' => 'confirmed', 'signature_status' => ElectronicSignature::STATUS_SIGNED, 'signed_at' => now(),
        ]);
        $this->assertFalse($resolver->canUpload($staff, $document));
        $this->actingAs($staff)->post($url, ['attachment' => UploadedFile::fake()->create('signed.pdf', 20, 'application/pdf')])->assertForbidden();
        $signature->update(['signature_status' => ElectronicSignature::STATUS_INVALIDATED]);
        $this->assertTrue($resolver->canUpload($staff, $document));
        foreach ([ProcurementDocument::STATUS_PR_DRAFT, ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT] as $status) {
            $document->update(['status' => $status]);
            $this->assertTrue($resolver->canUpload($staff, $document));
            $this->post($url, ['attachment' => UploadedFile::fake()->create('reference.pdf', 20, 'application/pdf')])->assertRedirect()->assertSessionHasNoErrors();
        }
        $document->update(['status' => ProcurementDocument::STATUS_PR_SIGNATORIES_COMPLETED]);
        $this->assertFalse($resolver->canUpload($staff, $document));
    }

    public function test_bacsec_003_uses_the_mpdo_preparer_for_pending_pr_files(): void
    {
        $this->seed();
        $account = User::where('user_id', 'BACSEC-003')->firstOrFail();
        $account->update(['must_change_password' => false]);
        $actor = app(BacsecMpdoWorkspaceService::class)->ensure($account);
        $document = $this->document($actor);
        $this->actingAs($account)->post(route('document-attachments.store', ['documentType' => 'purchase_request', 'documentId' => $document->id]),
            ['attachment' => UploadedFile::fake()->create('mpdo-reference.pdf', 20, 'application/pdf')])->assertRedirect()->assertSessionHasNoErrors();
        $attachment = $document->attachments()->firstOrFail();
        $this->assertSame($actor->id, $attachment->uploaded_by_user_id);
        $this->assertSame($actor->office_id, $attachment->office_id);
        $this->get(route('document-attachments.download', $attachment))->assertOk();
        $this->patch(route('document-attachments.update', $attachment), ['description' => 'MPDO reference'])->assertRedirect();
        $this->delete(route('document-attachments.destroy', $attachment))->assertRedirect();
        $this->assertSoftDeleted($attachment);
        $this->assertAuthenticatedAs($account);
    }

    private function staff(string $code): User
    {
        $office = Office::create(['code' => $code, 'name' => $code . ' Office', 'type' => Office::TYPE_END_USER,
            'is_requesting_office' => true, 'status' => Office::STATUS_ACTIVE]);
        $role = Role::where('code', 'office_preparer')->firstOrFail();
        foreach (['pr.view', 'documents.track'] as $key) {
            $permission = Permission::firstOrCreate(['key' => $key], ['name' => $key, 'group' => 'PR']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        return User::factory()->create(['user_id' => $code . '-STAFF', 'role' => User::ROLE_OFFICE_PREPARER,
            'role_id' => $role->id, 'office_id' => $office->id, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);
    }

    private function document(User $staff): ProcurementDocument
    {
        return ProcurementDocument::create(['document_type' => 'PR', 'title' => 'Office supplies', 'fiscal_year' => 2026,
            'status' => ProcurementDocument::STATUS_PR_PENDING_SIGNATORIES, 'submitting_office_id' => $staff->office_id,
            'current_office_id' => $staff->office_id, 'prepared_by_user_id' => $staff->id, 'submitted_by_user_id' => $staff->id]);
    }
}
