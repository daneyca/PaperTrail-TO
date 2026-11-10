<?php

namespace Tests\Feature;

use App\Models\DocumentAttachment;
use App\Models\Office;
use App\Models\Permission;
use App\Models\ProcurementDocument;
use App\Models\Role;
use App\Models\User;
use App\Services\BacsecMpdoWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PpmpSupportingDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_staff_can_upload_files_when_creating_a_draft_or_continuing_for_signatures(): void
    {
        $staff = $this->staff();
        User::factory()->create(['user_id' => 'PLAN-001', 'role' => User::ROLE_HEAD_OFFICE,
            'office_id' => $staff->office_id, 'status' => User::STATUS_ACTIVE]);
        $this->actingAs($staff)->get(route('head-office.ppmp.create'))->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)->assertSee('name="attachments[]"', false);

        foreach (['draft', 'submit'] as $action) {
            $this->post(route('head-office.ppmp.store'), [
                'fiscal_year' => 2026, 'ppmp_plan_type' => 'indicative', 'save_action' => $action,
                'items' => [['general_description' => 'Office supplies', 'estimated_budget' => 40000]],
                'attachments' => [UploadedFile::fake()->create('cost-estimate.pdf', 20, 'application/pdf'),
                    UploadedFile::fake()->image('price-reference.png')],
            ])->assertSessionHasNoErrors()->assertRedirect()
                ->assertSessionHas('ppmp_editor_clear_key', 'papertrail:ppmp:' . $staff->id . ':' . $staff->office_id . ':new');
            $document = ProcurementDocument::where('prepared_by_user_id', $staff->id)->latest('id')->firstOrFail();
            $this->assertSame($action === 'draft' ? ProcurementDocument::STATUS_PPMP_DRAFT : ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES, $document->status);
            $this->assertCount(2, $document->attachments);
            foreach ($document->attachments as $attachment) {
                Storage::disk('local')->assertExists($attachment->file_path);
                $this->assertSame($staff->id, $attachment->uploaded_by_user_id);
                $this->assertSame($staff->office_id, $attachment->office_id);
                $this->assertSame($document->tracking_number, $attachment->tracking_number);
            }
            $this->get(route('head-office.ppmp.show', $document))->assertOk()->assertSee('cost-estimate.pdf')
                ->assertSee('sessionStorage.removeItem("papertrail:ppmp:' . $staff->id . ':' . $staff->office_id . ':new")', false);
        }
    }

    public function test_staff_can_add_download_and_remove_draft_files_but_cannot_change_signed_files_or_other_offices(): void
    {
        $staff = $this->staff();
        $document = $this->document($staff);
        $uploadUrl = route('document-attachments.store', ['documentType' => 'ppmp', 'documentId' => $document->id]);
        $this->actingAs($staff)->get(route('head-office.ppmp.show', $document))->assertOk()->assertSee('class="attachments-upload-form"', false);
        $this->post($uploadUrl, ['attachment' => UploadedFile::fake()->create('planning.pdf', 20, 'application/pdf')])
            ->assertSessionHasNoErrors()->assertRedirect();
        $attachment = $document->attachments()->firstOrFail();
        $this->get(route('document-attachments.download', $attachment))->assertOk()->assertDownload('planning.pdf');
        $this->patch(route('document-attachments.update', $attachment), ['description' => 'Budget estimate'])->assertRedirect();
        $this->assertSame('Budget estimate', $attachment->refresh()->description);
        $this->patch(route('head-office.ppmp.update', $document), [
            'fiscal_year' => 2026, 'ppmp_plan_type' => 'indicative', 'save_action' => 'draft',
        ])->assertSessionHasNoErrors()->assertRedirect()
            ->assertSessionHas('ppmp_editor_clear_key', 'papertrail:ppmp:' . $staff->id . ':' . $staff->office_id . ':' . $document->id);

        foreach ([ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES, ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED,
            ProcurementDocument::STATUS_PENDING_PPMP_REVIEW, ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION] as $status) {
            $document->update(['status' => $status]);
            $this->post($uploadUrl, ['attachment' => UploadedFile::fake()->create('late.pdf', 20, 'application/pdf')])->assertForbidden();
            $this->patch(route('document-attachments.update', $attachment), ['description' => 'Changed'])->assertForbidden();
            $this->delete(route('document-attachments.destroy', $attachment))->assertForbidden();
        }
        $this->get(route('head-office.ppmp.show', $document))->assertOk()
            ->assertSee('Uploads are locked once the PPMP is sent for signatures.')->assertDontSee('class="attachments-upload-form"', false);

        $otherStaff = $this->staff('OTHERPLAN');
        $this->actingAs($otherStaff)->post($uploadUrl, ['attachment' => UploadedFile::fake()->create('other.pdf', 20, 'application/pdf')])->assertForbidden();
        $this->get(route('document-attachments.download', $attachment))->assertForbidden();
        $document->update(['status' => ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT]);
        $this->actingAs($staff)->delete(route('document-attachments.destroy', $attachment))->assertRedirect();
        $this->assertSoftDeleted($attachment);
    }

    public function test_invalid_files_are_rejected_before_creating_a_ppmp(): void
    {
        $staff = $this->staff();
        $count = ProcurementDocument::count();
        foreach ([UploadedFile::fake()->create('script.exe', 20, 'application/octet-stream'),
            UploadedFile::fake()->create('oversized.pdf', 10241, 'application/pdf')] as $file) {
            $this->actingAs($staff)->post(route('head-office.ppmp.store'), [
                'fiscal_year' => 2026, 'ppmp_plan_type' => 'indicative', 'save_action' => 'draft', 'attachments' => [$file],
            ])->assertSessionHasErrors('attachments.0')->assertSessionMissing('ppmp_editor_clear_key');
        }
        $this->assertSame($count, ProcurementDocument::count());
        $this->assertSame([], Storage::disk('local')->allFiles('document-attachments'));
    }

    public function test_bacsec_003_uploads_and_manages_mpdo_files_using_the_correct_preparer_account(): void
    {
        $this->seed();
        $account = User::where('user_id', 'BACSEC-003')->firstOrFail();
        $account->update(['must_change_password' => false]);
        $actor = app(BacsecMpdoWorkspaceService::class)->ensure($account);
        $document = $this->document($actor);
        $this->actingAs($account)->post(route('document-attachments.store', ['documentType' => 'ppmp', 'documentId' => $document->id]),
            ['attachment' => UploadedFile::fake()->create('mpdo-planning.pdf', 20, 'application/pdf')])
            ->assertSessionHasNoErrors()->assertRedirect();
        $attachment = $document->attachments()->firstOrFail();
        $this->assertSame($actor->id, $attachment->uploaded_by_user_id);
        $this->assertSame($actor->office_id, $attachment->office_id);
        $this->get(route('document-attachments.download', $attachment))->assertOk()->assertDownload('mpdo-planning.pdf');
        $this->patch(route('document-attachments.update', $attachment), ['description' => 'MPDO estimate'])->assertRedirect();
        $this->delete(route('document-attachments.destroy', $attachment))->assertRedirect();
        $this->assertSoftDeleted($attachment);
        $this->assertAuthenticatedAs($account);
    }

    private function staff(string $code = 'PLAN'): User
    {
        $office = Office::create(['code' => $code, 'name' => $code . ' Office', 'type' => Office::TYPE_END_USER,
            'is_requesting_office' => true, 'status' => Office::STATUS_ACTIVE]);
        $role = Role::where('code', 'office_preparer')->firstOrFail();
        foreach (['ppmp.view', 'ppmp.submit', 'documents.submit', 'documents.edit.own'] as $key) {
            $permission = Permission::firstOrCreate(['key' => $key], ['name' => $key, 'group' => 'PPMP']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        return User::factory()->create(['user_id' => $code . '-STAFF', 'role' => User::ROLE_OFFICE_PREPARER,
            'role_id' => $role->id, 'office_id' => $office->id, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);
    }

    private function document(User $staff): ProcurementDocument
    {
        return ProcurementDocument::create(['document_type' => 'PPMP', 'title' => 'Planning references', 'fiscal_year' => 2026,
            'status' => ProcurementDocument::STATUS_PPMP_DRAFT, 'submitting_office_id' => $staff->office_id,
            'current_office_id' => $staff->office_id, 'submitted_by_user_id' => $staff->id, 'prepared_by_user_id' => $staff->id]);
    }
}
