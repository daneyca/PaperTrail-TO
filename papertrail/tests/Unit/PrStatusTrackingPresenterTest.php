<?php

namespace Tests\Unit;

use App\Models\DocumentRoutingHistory;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\PrStatusTrackingPresenter;
use Tests\TestCase;

class PrStatusTrackingPresenterTest extends TestCase
{
    public function test_pending_number_assignment_shows_completed_signatures_and_renders_the_dashboard(): void
    {
        $document = $this->document(ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT);
        $tracking = app(PrStatusTrackingPresenter::class)->present($document);

        $this->assertSame(50, $tracking['percent']);
        $this->assertSame('completed', $tracking['steps'][1]['state']);
        $this->assertSame('current', $tracking['steps'][2]['state']);
        $this->assertSame('Procurement Controller', $tracking['handler']);

        $html = view('components.documents.pr-status-dashboard', compact('tracking'))->render();
        $this->assertStringContainsString('Activity Timeline', $html);
        $this->assertStringContainsString('Document Route', $html);
        $this->assertStringContainsString('2 of 4 steps', $html);
        $this->assertStringContainsString('Assign the official PR number.', $html);
    }

    public function test_normal_numbered_return_to_requesting_office_is_not_a_correction(): void
    {
        $document = $this->document(ProcurementDocument::STATUS_PR_NUMBER_ASSIGNED_RETURNED_TO_END_USER);
        $document->pr_number_status = ProcurementDocument::PR_NUMBER_STATUS_ASSIGNED;
        $document->pr_no = 'PR-2026-11-009';
        $tracking = app(PrStatusTrackingPresenter::class)->present($document);

        $this->assertSame('In progress', $tracking['overall']);
        $this->assertSame(75, $tracking['percent']);
        $this->assertStringContainsString('Submit the numbered Purchase Request', $tracking['next']);
    }

    public function test_cancellation_has_no_upcoming_action_and_empty_history_renders(): void
    {
        $document = $this->document('pr_cancelled');
        $tracking = app(PrStatusTrackingPresenter::class)->present($document);

        $this->assertFalse($tracking['has_next']);
        $this->assertSame('Cancelled', $tracking['overall']);
        $html = view('components.documents.pr-status-dashboard', compact('tracking'))->render();
        $this->assertStringContainsString('No routing history has been recorded yet.', $html);
    }

    private function document(string $status): ProcurementDocument
    {
        return (new ProcurementDocument)->forceFill([
            'id' => 12, 'document_type' => 'PR', 'tracking_number' => '2026-11-PR-0012',
            'status' => $status, 'stage' => 'PR Number Assignment',
            'created_at' => '2026-11-10 04:45:00', 'updated_at' => '2026-11-10 05:07:00',
        ])->setRelation('routingHistories', collect())
            ->setRelation('submittingOffice', new Office(['name' => 'Requesting Office']))
            ->setRelation('currentOffice', new Office(['name' => 'Municipal Engineering Office']))
            ->setRelation('assignedTo', new User(['name' => 'Procurement Controller']));
    }
}
