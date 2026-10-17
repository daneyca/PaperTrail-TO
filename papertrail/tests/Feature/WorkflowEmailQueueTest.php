<?php

namespace Tests\Feature;

use App\Jobs\SendWorkflowEmail;
use App\Models\User;
use App\Notifications\ProcurementWorkflowNotification;
use App\Services\NotificationDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WorkflowEmailQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_notification_is_saved_immediately_and_email_is_queued(): void
    {
        config()->set('queue.default', 'database');
        Queue::fake();

        $user = User::factory()->create([
            'user_id' => 'HEAD-001',
            'role' => User::ROLE_HEAD_OFFICE,
            'status' => User::STATUS_ACTIVE,
        ]);

        app(NotificationDispatchService::class)->notifyUser($user, 'Document Submitted', 'Your document was submitted.');

        $this->assertDatabaseHas('system_notifications', [
            'user_id' => $user->id,
            'title' => 'Document Submitted',
        ]);
        Queue::assertPushed(SendWorkflowEmail::class, fn (SendWorkflowEmail $job) => $job->recipientId === $user->id
            && $job->queue === 'mail');
    }

    public function test_worker_records_email_delivery_after_sending(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'user_id' => 'HEAD-001',
            'role' => User::ROLE_HEAD_OFFICE,
            'status' => User::STATUS_ACTIVE,
        ]);

        $notification = new ProcurementWorkflowNotification('Document Submitted', 'Your document was submitted.');
        (new SendWorkflowEmail($user->id, $notification))->handle();

        Notification::assertSentTo($user, ProcurementWorkflowNotification::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Email Notification Sent']);
    }
}
