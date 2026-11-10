<?php

namespace Tests\Feature;

use App\Models\DocumentAttachment;
use App\Models\ObserverInvitation;
use App\Models\SignatureRequest;
use App\Models\SupplierSignatureRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ObserverInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_bacsec_003_can_open_and_save_the_official_editable_letter(): void
    {
        $this->actingAs($this->editor());
        $this->get(route('bac-secretariat.competitive-bidding.menu', ['task' => 'letter-of-invitation-to-observer']))
            ->assertRedirect(route('bac-secretariat.competitive-bidding.observers.index'));
        $this->get(route('bac-secretariat.competitive-bidding.observers.create'))
            ->assertOk()->assertSee('OFFICE OF THE BAC CHAIRPERSON')->assertSee('data-observer-editor', false)
            ->assertSee('name="letter[recipient_name]"', false)->assertSee('Add Schedule')->assertSee('Add Project')
            ->assertSee('name="letter[sections][1][schedules][0][projects][0][budget]"', false);

        $data = $this->letter();
        $this->post(route('bac-secretariat.competitive-bidding.observers.store'), ['letter' => $data])->assertSessionHasNoErrors();
        $invitation = ObserverInvitation::firstOrFail();
        $this->assertEquals($data, $invitation->payload);
        $this->get(route('bac-secretariat.competitive-bidding.observers.show', $invitation))
            ->assertOk()->assertSee('Observer Test Name')->assertSee('Signed / Received Copies');
        $this->get(route('bac-secretariat.competitive-bidding.observers.edit', $invitation))
            ->assertOk()->assertSee('Observer Test Name')->assertSee('January 23, 2027; 10:00 in the morning');
        $this->get(route('bac-secretariat.competitive-bidding.observers.print', $invitation))
            ->assertOk()->assertSee('1,999,896.15')->assertSee('Training Center')->assertDontSee('<textarea', false);
        $this->get(route('bac-secretariat.competitive-bidding.observers.index'))->assertOk()->assertSee('Observer Test Name');
    }

    public function test_updates_preserve_fields_and_reject_a_stale_tab(): void
    {
        $user = $this->editor();
        $invitation = ObserverInvitation::create(['prepared_by_user_id' => $user->id, 'payload' => $this->letter()]);
        $data = $this->letter();
        $data['recipient_name'] = 'Updated Observer';
        $data['sections'][1]['schedules'][] = ['when' => 'February 4, 2027; 10:00 in the morning', 'projects' => [['description' => 'Repair Project', 'budget_prefix' => 'with an ABC of Php', 'budget' => '299940.17']]];
        $this->actingAs($user)->put(route('bac-secretariat.competitive-bidding.observers.update', $invitation), ['letter' => $data, 'revision' => 1])->assertSessionHasNoErrors();
        $this->assertSame(2, $invitation->refresh()->revision);
        $this->assertCount(2, $invitation->payload['sections'][1]['schedules']);
        $data['recipient_name'] = 'Stale overwrite';
        $this->put(route('bac-secretariat.competitive-bidding.observers.update', $invitation), ['letter' => $data, 'revision' => 1])->assertSessionHasErrors('revision');
        $this->assertSame('Updated Observer', $invitation->refresh()->recipient());
    }

    public function test_physical_copies_are_private_and_do_not_create_signature_requests(): void
    {
        Storage::fake('local');
        $user = $this->editor();
        $invitation = ObserverInvitation::create(['prepared_by_user_id' => $user->id, 'payload' => $this->letter()]);
        $internal = SignatureRequest::count();
        $external = SupplierSignatureRequest::count();
        $this->actingAs($user)->from(route('bac-secretariat.competitive-bidding.observers.show', $invitation))
            ->post(route('bac-secretariat.competitive-bidding.observers.copies.store', $invitation), [
                'observer_name' => 'CSO Observer', 'copy_type' => 'received_copy', 'received_on' => '2026-11-10', 'notes' => 'Collected in person.',
                'copies' => [UploadedFile::fake()->image('received.jpg'), UploadedFile::fake()->image('signed.png')],
            ])->assertSessionHasNoErrors()->assertRedirect(route('bac-secretariat.competitive-bidding.observers.show', $invitation));
        $this->assertCount(2, $invitation->copies);
        $copy = $invitation->copies->first();
        Storage::disk('local')->assertExists($copy->file_path);
        $this->assertStringStartsWith('private/observer-invitations/', $copy->file_path);
        $this->assertTrue($copy->is_confidential);
        $this->assertSame(1, $copy->extracted_metadata['letter_revision']);
        $this->assertEquals($invitation->payload, $copy->extracted_metadata['letter_snapshot']);
        $this->assertSame('CSO Observer', $copy->extracted_metadata['observer_name']);
        $this->assertSame($internal, SignatureRequest::count());
        $this->assertSame($external, SupplierSignatureRequest::count());
        $this->get(route('bac-secretariat.competitive-bidding.observers.show', $invitation))->assertOk()->assertSee('CSO Observer')->assertSee('Received / stamped copy');
        $this->get(route('bac-secretariat.competitive-bidding.observers.copies.view', [$invitation, $copy]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('bac-secretariat.competitive-bidding.observers.copies.view', [$invitation, $copy, 'download' => 1]))->assertDownload($copy->displayName());
        $other = ObserverInvitation::create(['prepared_by_user_id' => $user->id, 'payload' => $this->letter()]);
        $this->get(route('bac-secretariat.competitive-bidding.observers.copies.view', [$other, $copy]))->assertNotFound();
        $this->delete(route('bac-secretariat.competitive-bidding.observers.copies.destroy', [$invitation, $copy]))->assertSessionHasNoErrors();
        $this->assertSoftDeleted('document_attachments', ['id' => $copy->id]);
        $this->assertSame(1, $invitation->copies()->count());
    }

    public function test_invalid_uploads_and_other_bac_accounts_are_rejected(): void
    {
        Storage::fake('local');
        $user = $this->editor();
        $invitation = ObserverInvitation::create(['prepared_by_user_id' => $user->id, 'payload' => $this->letter()]);
        $this->actingAs($user)->post(route('bac-secretariat.competitive-bidding.observers.copies.store', $invitation), [
            'observer_name' => 'Witness', 'copy_type' => 'signed_letter', 'copies' => [UploadedFile::fake()->create('unsafe.html', 1, 'text/html')],
        ])->assertSessionHasErrors('copies.0');
        $this->assertSame(0, $invitation->copies()->count());
        $other = User::where('user_id', 'BACSEC-001')->firstOrFail();
        $other->update(['status' => User::STATUS_ACTIVE, 'must_change_password' => false]);
        $this->actingAs($other)->get(route('bac-secretariat.competitive-bidding.observers.index'))->assertForbidden();
        $this->get(route('bac-secretariat.competitive-bidding.observers.create'))->assertForbidden();
        $this->get(route('bac-secretariat.competitive-bidding.observers.show', $invitation))->assertForbidden();
        $this->post(route('bac-secretariat.competitive-bidding.observers.store'), [])->assertForbidden();
        $this->post(route('bac-secretariat.competitive-bidding.observers.copies.store', $invitation), [])->assertForbidden();
    }

    private function editor(): User
    {
        $user = User::where('user_id', 'BACSEC-003')->firstOrFail();
        $user->update(['role' => User::ROLE_BAC_SECRETARIAT, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);

        return $user;
    }

    private function letter(): array
    {
        $data = ObserverInvitation::defaults();
        $data['recipient_name'] = 'Observer Test Name';
        $data['recipient_details'] = "CSO Member\nTomas Oppus, Southern Leyte";
        $data['signatory_name'] = 'Test BAC Chairperson';
        foreach ($data['sections'] as &$section) {
            $section['schedules'] = [['when' => 'January 23, 2027; 10:00 in the morning', 'projects' => [['description' => 'Training Center', 'budget_prefix' => 'with an ABC of Php', 'budget' => '1999896.15']]]];
        }

        return $data;
    }
}
