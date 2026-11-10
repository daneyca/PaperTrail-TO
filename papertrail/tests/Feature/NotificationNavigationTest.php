<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserHasPermission;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\SignatureRequest;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_a_notification_marks_it_read_and_opens_its_signature_page(): void
    {
        [$user, $signatureRequest, $notification] = $this->signatureNotification();
        $this->actingAs($user)->get(route('notifications.show', $notification))
            ->assertRedirect(route('signature-requests.show', $signatureRequest));
        $readAt = $notification->refresh()->read_at;
        $this->assertNotNull($readAt);
        $this->get(route('signature-requests.show', $signatureRequest))->assertOk();
        $this->get(route('notifications.show', $notification))
            ->assertRedirect(route('signature-requests.show', $signatureRequest));
        $this->assertEquals($readAt, $notification->refresh()->read_at);
    }

    public function test_notification_details_remain_an_optional_separate_page(): void
    {
        [$user, $signatureRequest, $notification] = $this->signatureNotification();
        $this->actingAs($user)->get(route('notifications.details', $notification))->assertOk()
            ->assertSee('Notification Detail')->assertSee('Open Related Page');
        $this->assertNotNull($notification->refresh()->read_at);
        $this->get(route('notifications.index'))->assertOk()
            ->assertSee(route('notifications.show', $notification), false)
            ->assertSee(route('notifications.details', $notification), false);
    }

    public function test_notifications_without_a_destination_show_their_message(): void
    {
        $user = $this->user();
        $notification = SystemNotification::create(['user_id' => $user->id, 'title' => 'System notice',
            'message' => 'An informational notice.', 'type' => 'info']);
        $this->actingAs($user)->get(route('notifications.show', $notification))->assertOk()
            ->assertSee('An informational notice.')->assertDontSee('Open Related Page');
        $this->assertNotNull($notification->refresh()->read_at);
    }

    public function test_deleted_related_documents_fall_back_to_notification_details(): void
    {
        [$user, $signatureRequest, $notification] = $this->signatureNotification();
        $signatureRequest->delete();
        $this->actingAs($user)->get(route('notifications.show', $notification))->assertOk()
            ->assertSee('The related page is unavailable.')->assertDontSee('Open Related Page');
        $this->assertNotNull($notification->refresh()->read_at);
    }

    public function test_invalid_or_expired_links_fall_back_instead_of_redirecting(): void
    {
        $user = $this->user();
        foreach (['javascript:alert(1)', '/no-longer-existing-page', route('signature-requests.show', 99999999)] as $url) {
            $notification = SystemNotification::create(['user_id' => $user->id, 'title' => 'Old notice',
                'message' => 'Original notification message.', 'type' => 'info', 'action_url' => $url]);
            $this->actingAs($user)->get(route('notifications.show', $notification))->assertOk()
                ->assertSee('The related page is unavailable.')->assertSee('Original notification message.')
                ->assertDontSee('Open Related Page');
        }
    }

    public function test_notification_links_cannot_redirect_back_into_a_notification_loop(): void
    {
        $user = $this->user();
        $notification = SystemNotification::create(['user_id' => $user->id, 'title' => 'Loop notice', 'message' => 'Notice', 'type' => 'info']);
        $notification->update(['action_url' => route('notifications.show', $notification)]);
        $this->actingAs($user)->get(route('notifications.show', $notification))->assertOk()
            ->assertSee('The related page is unavailable.');
    }

    public function test_links_saved_with_an_old_domain_use_the_current_application(): void
    {
        [$user, $signatureRequest, $notification] = $this->signatureNotification();
        $path = route('signature-requests.show', $signatureRequest, false);
        $notification->update(['action_url' => 'https://previous-domain.example' . $path . '?tab=remarks#signature']);
        $this->actingAs($user)->get(route('notifications.show', $notification))
            ->assertRedirect(url($path) . '?tab=remarks#signature');
    }

    public function test_another_users_notification_cannot_be_opened_or_marked_read(): void
    {
        [$owner, $signatureRequest, $notification] = $this->signatureNotification();
        $other = User::factory()->create(['user_id' => 'HEAD-OTHER-NOTICE', 'role' => User::ROLE_HEAD_OFFICE, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);
        $this->actingAs($other)->get(route('notifications.show', $notification))->assertForbidden();
        $this->get(route('notifications.details', $notification))->assertForbidden();
        $this->patch(route('notifications.read', $notification))->assertForbidden();
        $this->assertNull($notification->refresh()->read_at);
    }

    private function user(): User
    {
        $this->withoutMiddleware(EnsureUserHasPermission::class);
        $office = Office::create(['code' => 'NOTICE-TEST', 'name' => 'Notification Test Office', 'type' => Office::TYPE_END_USER,
            'status' => Office::STATUS_ACTIVE, 'is_requesting_office' => true]);
        return User::factory()->create(['user_id' => 'HEAD-NOTICE', 'role' => User::ROLE_HEAD_OFFICE, 'office_id' => $office->id,
            'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);
    }

    private function signatureNotification(): array
    {
        $user = $this->user();
        $document = ProcurementDocument::create(['document_type' => 'PPMP', 'title' => 'Notification PPMP', 'fiscal_year' => 2026,
            'status' => ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES, 'submitting_office_id' => $user->office_id,
            'prepared_by_user_id' => $user->id, 'submitted_by_user_id' => $user->id]);
        $signatureRequest = SignatureRequest::create(['document_type' => 'ppmp', 'document_id' => $document->id,
            'document_label' => 'PPMP', 'requested_to_user_id' => $user->id, 'status' => SignatureRequest::STATUS_PENDING]);
        $notification = SystemNotification::create(['user_id' => $user->id, 'title' => 'Document Requires Your Signature',
            'message' => 'Review and sign this PPMP.', 'type' => 'info', 'related_type' => SignatureRequest::class,
            'related_id' => $signatureRequest->id, 'action_url' => route('signature-requests.show', $signatureRequest)]);
        return [$user, $signatureRequest, $notification];
    }
}
