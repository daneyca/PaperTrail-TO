<?php

namespace Tests\Feature;

use App\Models\{AnnualProcurementPlan, BacResolution, CompetitiveBiddingSignatureDocument, Office, ProcurementDocument, PurchaseOrder, Rfq, SignatureRequest, SvpProcurementChain, User};
use App\Services\StatusTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = parent::createApplication();
        // This suite must never migrate a development or production database.
        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql.database', 'papertrail_testing');
        return $app;
    }

    public function test_every_account_role_has_a_working_tracking_menu(): void
    {
        foreach ([
            ['TRACK-ADMIN', User::ROLE_ADMIN], ['TRACK-HO', User::ROLE_HEAD_OFFICE],
            ['TRACK-STAFF', User::ROLE_OFFICE_PREPARER], ['TRACK-BUDGET', User::ROLE_BUDGET],
            ['TRACK-ACCOUNTING', User::ROLE_ACCOUNTING], ['BACSEC-001', User::ROLE_BAC_SECRETARIAT],
            ['BACSEC-002', User::ROLE_BAC_SECRETARIAT], ['BACSEC-003', User::ROLE_BAC_SECRETARIAT],
            ['TRACK-MEMBER', User::ROLE_BAC_MEMBER], ['TRACK-CHAIR', User::ROLE_BAC_CHAIR],
            ['TRACK-VICE', User::ROLE_BAC_VICE_CHAIRPERSON], ['TRACK-HOPE', User::ROLE_APPROVING_AUTHORITY],
            ['TRACK-PC', User::ROLE_PROCUREMENT_CONTROLLER],
        ] as [$code, $role]) {
            $user = $this->account($code, $role);
            $this->actingAs($user)->get(route('status-tracking.index'))->assertOk()
                ->assertSee('Tracked Documents')->assertSee('href="'.route('status-tracking.index').'"', false);
        }
    }

    public function test_created_and_processed_documents_are_visible_without_exposing_department_peers_records(): void
    {
        $office = Office::create(['code' => 'TRACK-A', 'name' => 'Tracking Office A', 'type' => Office::TYPE_END_USER, 'status' => Office::STATUS_ACTIVE]);
        $otherOffice = Office::create(['code' => 'TRACK-B', 'name' => 'Tracking Office B', 'type' => Office::TYPE_END_USER, 'status' => Office::STATUS_ACTIVE]);
        $user = $this->account('TRACK-OWNER', User::ROLE_HEAD_OFFICE, $office->id);
        $pr = $this->procurement('VISIBLE-PR', ['prepared_by_user_id' => $user->id, 'submitting_office_id' => $office->id]);
        $ppmp = $this->procurement('VISIBLE-PPMP', ['document_type' => 'PPMP', 'status' => ProcurementDocument::STATUS_PPMP_DRAFT, 'submitting_office_id' => $office->id]);
        $ppmp->routingHistories()->create(['action' => 'Draft saved', 'action_by_user_id' => $user->id, 'status_to' => ProcurementDocument::STATUS_PPMP_DRAFT, 'comments' => 'Recorded tracking remarks.', 'action_at' => now()]);
        $hidden = $this->procurement('PRIVATE-OTHER-OFFICE', ['submitting_office_id' => $otherOffice->id]);
        $sameOffice = $this->procurement('PRIVATE-SAME-OFFICE', ['submitting_office_id' => $office->id]);
        $app = AnnualProcurementPlan::withoutEvents(fn () => AnnualProcurementPlan::create(['title' => 'VISIBLE-APP', 'app_number' => 'APP-TRACK-TEST', 'fiscal_year' => 2026, 'office_id' => $office->id, 'prepared_by_user_id' => $user->id, 'status' => 'draft']));

        $this->actingAs($user)->get(route('status-tracking.index'))->assertOk()
            ->assertSee('VISIBLE-PR')->assertSee('VISIBLE-PPMP')->assertSee('VISIBLE-APP')->assertDontSee('PRIVATE-OTHER-OFFICE')->assertDontSee('PRIVATE-SAME-OFFICE');
        $this->get(route('status-tracking.show', ['procurement_document', $pr]))->assertOk()->assertSee('PR Progress');
        $this->get(route('status-tracking.show', ['procurement_document', $ppmp]))->assertOk()->assertSee('Activity Timeline')->assertSee('Recorded tracking remarks.');
        $this->get(route('status-tracking.show', ['app', $app]))->assertOk()->assertSee('Activity Timeline');
        $this->get(route('status-tracking.show', ['procurement_document', $hidden]))->assertForbidden();
        $this->get(route('status-tracking.show', ['procurement_document', $sameOffice]))->assertForbidden();
    }

    public function test_accounts_without_an_office_do_not_see_unrelated_unassigned_documents(): void
    {
        $user = $this->account('TRACK-NO-OFFICE', User::ROLE_HEAD_OFFICE);
        $this->procurement('PRIVATE-UNASSIGNED');
        $listing = app(StatusTrackingService::class)->listing($user, []);
        $this->assertSame(0, $listing['documents']->total());
        $this->assertCount(0, $listing['types']);
    }

    public function test_a_reviewer_keeps_tracking_access_after_the_document_moves_on(): void
    {
        $budget = $this->account('TRACK-REVIEWER', User::ROLE_BUDGET);
        $this->procurement('REVIEWED-MOVED-ON', ['status' => ProcurementDocument::STATUS_READY_FOR_RFQ, 'budget_reviewed_by_user_id' => $budget->id]);
        $this->procurement('AWAITING-BUDGET', ['status' => ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW, 'assigned_to_user_id' => $budget->id]);
        $this->procurement('UNASSIGNED-BUDGET', ['status' => ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW]);
        $this->procurement('PRIVATE-DRAFT');
        $this->actingAs($budget)->get(route('status-tracking.index'))->assertOk()
            ->assertSee('REVIEWED-MOVED-ON')->assertSee('AWAITING-BUDGET')->assertDontSee('PRIVATE-DRAFT')->assertDontSee('UNASSIGNED-BUDGET');
    }

    public function test_competitive_app_tracking_is_available_to_its_preparer_and_assigned_signatory(): void
    {
        $preparer = $this->account('BACSEC-003', User::ROLE_BAC_SECRETARIAT);
        $signer = $this->account('TRACK-SIGNER', User::ROLE_HEAD_OFFICE);
        $unrelated = $this->account('TRACK-STRANGER', User::ROLE_HEAD_OFFICE);
        $document = CompetitiveBiddingSignatureDocument::create([
            'template_key' => 'annual_procurement_plan', 'document_title' => 'TRACK-COMPETITIVE-APP',
            'tracking_number' => 'CB-APP-TRACK-001', 'document_html' => '<script>privateSource()</script>',
            'signatories' => [], 'prepared_by_user_id' => $preparer->id,
        ]);
        SignatureRequest::create([
            'request_uuid' => (string) \Illuminate\Support\Str::uuid(), 'document_type' => 'competitive_bidding_document',
            'document_id' => $document->id, 'document_label' => 'Annual Procurement Plan',
            'requested_by_user_id' => $preparer->id, 'requested_to_user_id' => $signer->id,
            'signatory_slot' => 'approval', 'signatory_label' => 'Approved by', 'status' => SignatureRequest::STATUS_PENDING,
        ]);
        foreach ([$preparer, $signer] as $user) {
            $this->actingAs($user)->get(route('status-tracking.index', ['type' => 'APP']))->assertOk()->assertSee('TRACK-COMPETITIVE-APP');
            $this->get(route('status-tracking.show', ['competitive_bidding_document', $document]))->assertOk()
                ->assertSee('Signatory workflow')->assertSee($signer->name)->assertDontSee('privateSource');
        }
        $this->actingAs($unrelated)->get(route('status-tracking.index'))->assertOk()->assertDontSee('TRACK-COMPETITIVE-APP');
        $this->get(route('status-tracking.show', ['competitive_bidding_document', $document]))->assertForbidden();
    }

    public function test_search_type_and_status_filters_apply_only_to_authorized_records(): void
    {
        $user = $this->account('TRACK-FILTER', User::ROLE_HEAD_OFFICE);
        $this->procurement('FILTER-TARGET', ['prepared_by_user_id' => $user->id]);
        $this->procurement('FILTER-OTHER-TYPE', ['document_type' => 'PPMP', 'prepared_by_user_id' => $user->id]);
        $this->procurement('FILTER-PRIVATE');
        $this->actingAs($user)->get(route('status-tracking.index', ['search' => 'FILTER', 'type' => 'PR', 'status' => 'pr_draft']))
            ->assertOk()->assertSee('FILTER-TARGET')->assertDontSee('FILTER-OTHER-TYPE')->assertDontSee('FILTER-PRIVATE');
        $this->get(route('status-tracking.show', ['unsupported-type', 1]))->assertNotFound();
        $this->post(route('status-tracking.index'))->assertStatus(405);
    }

    public function test_a_record_without_a_source_pr_cannot_pick_up_an_unrelated_svp_chain(): void
    {
        $user = $this->account('TRACK-RFQ', User::ROLE_HEAD_OFFICE);
        $rfq = Rfq::withoutEvents(fn () => Rfq::create(['rfq_number' => 'RFQ-TRACK-ORPHAN', 'status' => 'draft', 'prepared_by_user_id' => $user->id]));
        $chain = SvpProcurementChain::create(['chain_number' => 'PRIVATE-ORPHAN-CHAIN', 'current_stage' => 'PRIVATE-CHAIN-STAGE']);
        $chain->events()->create(['action' => 'PRIVATE-CHAIN-EVENT', 'stage' => 'PRIVATE-CHAIN-STAGE']);
        $this->actingAs($user)->get(route('status-tracking.show', ['rfq', $rfq]))->assertOk()
            ->assertDontSee('PRIVATE-CHAIN-EVENT')->assertDontSee('PRIVATE-CHAIN-STAGE');
    }

    public function test_an_app_displays_the_actual_pending_signatory_as_its_handler(): void
    {
        $owner = $this->account('TRACK-APP-OWNER', User::ROLE_HEAD_OFFICE);
        $signer = $this->account('TRACK-APP-HOPE', User::ROLE_APPROVING_AUTHORITY);
        $app = AnnualProcurementPlan::withoutEvents(fn () => AnnualProcurementPlan::create([
            'title' => 'APP SIGNING TEST', 'fiscal_year' => 2026, 'app_number' => 'APP-SIGNING-TEST',
            'status' => AnnualProcurementPlan::STATUS_SUBMITTED, 'prepared_by_user_id' => $owner->id,
        ]));
        SignatureRequest::create([
            'request_uuid' => (string) \Illuminate\Support\Str::uuid(), 'document_type' => 'app',
            'document_id' => $app->id, 'signatory_slot' => 'approved_by', 'status' => SignatureRequest::STATUS_PENDING,
            'requested_by_user_id' => $owner->id, 'requested_to_user_id' => $signer->id,
        ]);
        $service = app(StatusTrackingService::class);
        $record = $service->find($owner, 'app', $app->id);
        $this->assertSame($signer->name, $service->details($record, 'app')['current_handler']);
        $this->assertSame($signer->name, $service->listing($owner, [])['documents']->first()->handler);
    }

    public function test_bacsec_can_track_its_bac_resolution_with_a_linked_workflow_at_any_stage(): void
    {
        $this->withoutExceptionHandling([\Symfony\Component\HttpKernel\Exception\HttpException::class]);
        $owner = $this->account('BACSEC-001', User::ROLE_BAC_SECRETARIAT);
        $peer = $this->account('BACSEC-003', User::ROLE_BAC_SECRETARIAT);
        $chair = $this->account('TRACK-RESOLUTION-CHAIR', User::ROLE_BAC_CHAIR);
        $source = $this->procurement('PRIVATE-SOURCE-PR', ['prepared_by_user_id' => $peer->id]);
        $resolution = BacResolution::withoutEvents(fn () => BacResolution::create([
            'resolution_number' => 'BAC-TRACK-REPRO', 'document_reference_number' => 'BAC-TRACK-REPRO',
            'title' => 'Resolution movement test', 'source_pr_document_id' => $source->id,
            'fiscal_year' => 2026, 'status' => BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR,
            'requesting_office_name' => 'BAC Secretariat', 'prepared_by_user_id' => $owner->id, 'submitted_at' => now(),
        ]));
        $chain = SvpProcurementChain::create(['chain_number' => 'SVP-RESOLUTION-TRACK', 'source_pr_document_id' => $source->id,
            'bac_resolution_id' => $resolution->id, 'current_stage' => SvpProcurementChain::STAGE_BAC_RESOLUTION]);
        $chain->events()->create(['document_type' => 'BAC Resolution', 'document_id' => $resolution->id,
            'action' => 'Resolution sent for signature', 'performed_by_user_id' => $owner->id,
            'stage' => SvpProcurementChain::STAGE_BAC_RESOLUTION, 'status' => $resolution->status]);
        $chain->events()->create(['document_type' => 'PR', 'document_id' => $source->id,
            'action' => 'PRIVATE SOURCE PR EVENT', 'stage' => 'PRIVATE SOURCE PR STAGE', 'performed_by_user_id' => $peer->id]);
        SignatureRequest::create(['request_uuid' => (string) \Illuminate\Support\Str::uuid(), 'document_type' => 'bac_resolution',
            'document_id' => $resolution->id, 'signatory_slot' => 'bac_chairperson', 'status' => SignatureRequest::STATUS_PENDING,
            'requested_by_user_id' => $owner->id, 'requested_to_user_id' => $chair->id]);

        $this->actingAs($owner);
        foreach (array_merge(SvpProcurementChain::workflowStages(), ['Unrecognized workflow stage']) as $stage) {
            $chain->update(['current_stage' => $stage]);
            $this->get(route('status-tracking.show', ['bac_resolution', $resolution]))->assertOk()
                ->assertSee('BAC-TRACK-REPRO')->assertSee('Resolution Sent For Signature')->assertSee($chair->name)
                ->assertDontSee('PRIVATE SOURCE PR EVENT')->assertDontSee('PRIVATE-SOURCE-PR')
                ->assertViewHas('tracking', fn ($tracking) => $tracking['current_stage'] === 'BAC Resolution');
            $linkedTracking = app(\App\Services\DocumentTrackingService::class)->buildTrackingPayload($resolution);
            $this->assertIsString($linkedTracking['current_holder']);
            $this->assertNotSame('', $linkedTracking['current_holder']);
        }
        $this->actingAs($peer)->get(route('status-tracking.show', ['bac_resolution', $resolution]))->assertForbidden();
    }

    public function test_pagination_counts_only_visible_documents(): void
    {
        $owner = $this->account('TRACK-PAGING', User::ROLE_HEAD_OFFICE);
        for ($index = 1; $index <= 16; $index++) {
            $this->procurement('PAGING-'.$index, ['prepared_by_user_id' => $owner->id]);
        }
        $this->procurement('PAGING-PRIVATE');
        $listing = app(StatusTrackingService::class)->listing($owner, []);
        $this->assertSame(16, $listing['documents']->total());
        $this->assertCount(15, $listing['documents']);
        $this->assertSame(2, $listing['documents']->lastPage());
    }

    public function test_involvement_in_a_source_pr_does_not_expose_other_accounts_linked_documents(): void
    {
        $bac = $this->account('TRACK-BAC', User::ROLE_BAC_SECRETARIAT);
        $private = $this->procurement('PRIVATE-SOURCE');
        $visible = $this->procurement('VISIBLE-SOURCE', ['status' => ProcurementDocument::STATUS_SUBMITTED_TO_BAC_SECRETARIAT, 'assigned_to_user_id' => $bac->id]);
        $private->routingHistories()->create(['action' => 'Private source action', 'status_to' => $private->status, 'comments' => 'PRIVATE-SOURCE-REMARK', 'action_at' => now()]);
        $privateRfq = Rfq::withoutEvents(fn () => Rfq::create(['rfq_number' => 'PRIVATE-LINKED-RFQ', 'status' => 'draft', 'source_pr_document_id' => $visible->id]));
        $ownRfq = Rfq::withoutEvents(fn () => Rfq::create(['rfq_number' => 'VISIBLE-LINKED-RFQ', 'status' => 'draft', 'source_pr_document_id' => $private->id, 'prepared_by_user_id' => $bac->id]));
        $this->actingAs($bac)->get(route('status-tracking.index'))->assertOk()->assertSee('VISIBLE-LINKED-RFQ')->assertDontSee('PRIVATE-LINKED-RFQ');
        $this->get(route('status-tracking.show', ['rfq', $privateRfq]))->assertForbidden();
        $this->get(route('status-tracking.show', ['rfq', $ownRfq]))->assertOk()->assertDontSee('PRIVATE-SOURCE-REMARK')->assertDontSee('Private source action');
    }

    public function test_bac_accounts_do_not_gain_visibility_from_their_shared_department_or_role(): void
    {
        $office = Office::create(['code' => 'TRACK-BAC', 'name' => 'BAC Secretariat', 'type' => Office::TYPE_PROCUREMENT, 'status' => Office::STATUS_ACTIVE]);
        $user = $this->account('BACSEC-003', User::ROLE_BAC_SECRETARIAT, $office->id);
        $peer = $this->account('BACSEC-001', User::ROLE_BAC_SECRETARIAT, $office->id);
        $assigned = $this->procurement('DARIUS-ASSIGNED-PPMP', ['document_type' => 'PPMP', 'status' => ProcurementDocument::STATUS_PENDING_PPMP_REVIEW, 'assigned_to_user_id' => $user->id, 'current_office_id' => $office->id]);
        $private = $this->procurement('PEER-ASSIGNED-PR', ['status' => ProcurementDocument::STATUS_SUBMITTED_TO_BAC_SECRETARIAT, 'assigned_to_user_id' => $peer->id, 'current_office_id' => $office->id]);
        $roleOnly = $this->procurement('ROLE-ONLY-PPMP', ['document_type' => 'PPMP', 'status' => ProcurementDocument::STATUS_PENDING_PPMP_REVIEW, 'current_office_id' => $office->id]);
        SignatureRequest::create(['request_uuid' => (string) \Illuminate\Support\Str::uuid(), 'document_type' => 'ppmp', 'document_id' => $roleOnly->id, 'signatory_slot' => 'review', 'requested_to_role' => 'bac_secretariat', 'requested_to_office_id' => $office->id, 'status' => SignatureRequest::STATUS_PENDING]);
        $this->actingAs($user)->get(route('status-tracking.index'))->assertOk()->assertSee('DARIUS-ASSIGNED-PPMP')->assertDontSee('PEER-ASSIGNED-PR')->assertDontSee('ROLE-ONLY-PPMP');
        $this->get(route('status-tracking.show', ['procurement_document', $assigned]))->assertOk();
        $this->get(route('status-tracking.show', ['procurement_document', $private]))->assertForbidden();
        $this->get(route('status-tracking.show', ['procurement_document', $roleOnly]))->assertForbidden();
    }

    public function test_recorded_processing_history_retains_access_after_reassignment(): void
    {
        $user = $this->account('TRACK-HISTORY', User::ROLE_BAC_SECRETARIAT);
        $peer = $this->account('TRACK-HISTORY-PEER', User::ROLE_BAC_SECRETARIAT);
        $processed = $this->procurement('HISTORICALLY-PROCESSED', ['assigned_to_user_id' => $peer->id]);
        $processed->routingHistories()->create(['action' => 'Reviewed and forwarded', 'status_to' => $processed->status, 'action_by_user_id' => $user->id, 'action_at' => now()]);
        $rfq = Rfq::withoutEvents(fn () => Rfq::create(['rfq_number' => 'PERSONALLY-CONTROLLED-RFQ', 'status' => 'submitted', 'control_assigned_by_user_id' => $user->id]));
        $this->actingAs($user)->get(route('status-tracking.index'))->assertOk()->assertSee('HISTORICALLY-PROCESSED')->assertSee('PERSONALLY-CONTROLLED-RFQ');
        $this->get(route('status-tracking.show', ['procurement_document', $processed]))->assertOk();
        $this->get(route('status-tracking.show', ['rfq', $rfq]))->assertOk();
    }

    public function test_pending_control_requests_are_visible_only_to_the_named_controller_and_actual_participants(): void
    {
        $controller = $this->account(User::PROCUREMENT_CONTROLLER_USER_ID, User::ROLE_PROCUREMENT_CONTROLLER);
        $peer = $this->account('TRACK-PC-PEER', User::ROLE_PROCUREMENT_CONTROLLER);
        $rfq = Rfq::withoutEvents(fn () => Rfq::create(['rfq_number' => 'DIRECTED-CONTROL-RFQ', 'status' => Rfq::STATUS_PENDING_CONTROL]));
        $po = PurchaseOrder::withoutEvents(fn () => PurchaseOrder::create(['po_number' => 'DIRECTED-CONTROL-PO', 'fiscal_year' => 2026, 'status' => PurchaseOrder::STATUS_PENDING_CONTROL]));
        $this->actingAs($controller)->get(route('status-tracking.index'))->assertOk()->assertSee('DIRECTED-CONTROL-RFQ')->assertSee('DIRECTED-CONTROL-PO');
        $this->get(route('status-tracking.show', ['rfq', $rfq]))->assertOk();
        $this->get(route('status-tracking.show', ['purchase_order', $po]))->assertOk();
        $this->actingAs($peer)->get(route('status-tracking.index'))->assertOk()->assertDontSee('DIRECTED-CONTROL-RFQ')->assertDontSee('DIRECTED-CONTROL-PO');
        $this->get(route('status-tracking.show', ['rfq', $rfq]))->assertForbidden();
    }

    public function test_tracking_requires_a_signed_in_account(): void
    {
        $this->get(route('status-tracking.index'))->assertRedirect(route('login'));
        $this->get(route('status-tracking.show', ['app', 1]))->assertRedirect(route('login'));
    }

    private function account(string $code, string $role, ?int $office = null): User
    {
        $user = User::where('user_id', $code)->first();
        if (! $user) {
            $user = User::factory()->create(['user_id' => $code, 'role' => $role, 'status' => User::STATUS_ACTIVE, 'office_id' => $office, 'must_change_password' => false]);
        } else {
            $user->forceFill(['role' => $role, 'role_id' => null, 'status' => User::STATUS_ACTIVE, 'office_id' => $office, 'must_change_password' => false])->save();
        }
        return $user;
    }

    private function procurement(string $number, array $attributes = []): ProcurementDocument
    {
        return ProcurementDocument::withoutEvents(fn () => ProcurementDocument::create(array_merge([
            'tracking_number' => $number, 'document_reference_number' => $number, 'title' => $number,
            'document_type' => 'PR', 'fiscal_year' => 2026, 'status' => ProcurementDocument::STATUS_PR_DRAFT, 'stage' => 'PR Preparation',
        ], $attributes)));
    }
}
