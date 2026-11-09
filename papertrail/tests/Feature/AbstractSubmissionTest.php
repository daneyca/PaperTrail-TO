<?php

namespace Tests\Feature;

use App\Models\AbstractQuotation;
use App\Models\BacResolution;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\Rfq;
use App\Models\SignatureRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbstractSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_submission_shows_the_reason_and_retains_committee_entries(): void
    {
        [$staff, $rfq, $committee] = $this->source();
        $url = route('head-office.abstracts.create', ['source_rfq_id' => $rfq->id]);
        $payload = $this->payload($rfq, $committee);
        $payload['abstract_number'] = '';

        $this->actingAs($staff)->from($url)->post(route('head-office.abstracts.store'), $payload)
            ->assertRedirect($url)->assertSessionHasErrors('abstract_number')
            ->assertSessionHasInput('committee_json', $payload['committee_json']);
        $this->get($url)->assertOk()->assertSee('Abstract was not submitted.')
            ->assertSee('Enter the Abstract No. in the official form before submitting.')
            ->assertSee('Your entries have been kept.')
            ->assertSee('data-document-create-autoshow="true"', false)
            ->assertSee('Member Two Printed Name');
        $this->assertDatabaseCount('abstracts', 0);
        $this->assertDatabaseCount('signature_requests', 0);
    }

    public function test_successful_submission_saves_names_and_routes_to_every_selected_bac_account(): void
    {
        [$staff, $rfq, $committee, $signers] = $this->source();
        $this->actingAs($staff)->post(route('head-office.abstracts.store'), $this->payload($rfq, $committee))
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'Abstract submitted.');

        $abstract = AbstractQuotation::firstOrFail();
        $this->assertSame(AbstractQuotation::STATUS_SUBMITTED, $abstract->status);
        $this->assertNotNull($abstract->submitted_at);
        $this->assertEquals($committee, $abstract->committee_json);
        $this->assertDatabaseCount('signature_requests', 5);
        $this->get(route('head-office.abstracts.show', $abstract))->assertOk()
            ->assertSee('Abstract submitted successfully.')
            ->assertSee('sent to the selected BAC signatories for e-signatures')
            ->assertSee('Member Two Printed Name')->assertSee('BAC Member II');

        foreach ($signers as $signer) {
            $request = SignatureRequest::where('document_type', 'abstract')->where('document_id', $abstract->id)
                ->where('requested_to_user_id', $signer->id)->firstOrFail();
            $this->assertTrue($request->isOpen());
            $this->actingAs($signer)->get(route('signature-requests.index', ['status' => 'pending']))
                ->assertOk()->assertSee($abstract->document_reference_number);
            $this->get(route('signature-requests.show', $request))->assertOk()->assertSee('Member Two Printed Name');
        }
    }

    public function test_missing_signer_account_keeps_the_form_open_with_a_clear_error(): void
    {
        [$staff, $rfq, $committee] = $this->source();
        $committee['bac_member_2']['account_code'] = '';
        $payload = $this->payload($rfq, $committee);
        $url = route('head-office.abstracts.create', ['source_rfq_id' => $rfq->id]);
        $this->actingAs($staff)->from($url)->post(route('head-office.abstracts.store'), $payload)
            ->assertRedirect($url)->assertSessionHas('error')->assertSessionHasInput('committee_json', $payload['committee_json']);
        $this->get($url)->assertOk()->assertSee('Assign user accounts for BAC Member II')
            ->assertSee('data-document-create-autoshow="true"', false)->assertSee('Member Two Printed Name');
        $this->assertDatabaseCount('abstracts', 0);
    }

    private function payload(Rfq $rfq, array $committee): array
    {
        $people = '';
        foreach ($committee as $role => $entry) {
            $people .= '<div class="abstract-committee-person"><span class="abstract-cell abstract-committee-name" data-committee-role="'.e($role).'">'.e($entry['name']).'</span></div>';
        }
        return [
            'save_action' => 'submit', 'source_rfq_id' => $rfq->id, 'abstract_number' => 'AOQ-REGRESSION-001',
            'abstract_date' => '2026-11-09', 'project_name' => 'Office supplies',
            'document_html' => '<table id="abstractEditor"><tr><td>Office supplies</td></tr><tr><td><div class="abstract-committee-grid">'.$people.'</div></td></tr></table>',
            'document_text' => 'Office supplies', 'committee_json' => json_encode($committee),
            'items_json' => '[]', 'suppliers_json' => '{}', 'awards_json' => '[]',
        ];
    }

    private function source(): array
    {
        $office = Office::create(['code' => 'ABS-MAO', 'name' => 'Municipal Agriculture Office',
            'type' => Office::TYPE_END_USER, 'status' => Office::STATUS_ACTIVE, 'is_requesting_office' => true]);
        $staff = User::factory()->create(['user_id' => 'ABS-STAFF', 'role' => User::ROLE_OFFICE_PREPARER,
            'office_id' => $office->id, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);
        $pr = ProcurementDocument::create(['document_type' => 'PR', 'title' => 'Office supplies', 'fiscal_year' => 2026,
            'status' => ProcurementDocument::STATUS_READY_FOR_RFQ, 'submitting_office_id' => $office->id,
            'prepared_by_user_id' => $staff->id, 'submitted_by_user_id' => $staff->id]);
        $resolution = BacResolution::create(['source_pr_document_id' => $pr->id,
            'status' => BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR, 'prepared_by_user_id' => $staff->id]);
        $rfq = Rfq::create(['source_pr_document_id' => $pr->id, 'source_bac_resolution_id' => $resolution->id,
            'rfq_number' => 'RFQ-ABS-001', 'status' => Rfq::STATUS_QUOTED, 'prepared_by_user_id' => $staff->id]);
        $committee = [];
        $signers = [];
        foreach ([
            ['bac_chairperson', 'BACCHAIR-ABS-001', User::ROLE_BAC_CHAIR, 'BAC Chairperson', 'Chair Printed Name'],
            ['bac_vice_chairperson', 'BACVICE-ABS-001', User::ROLE_BAC_VICE_CHAIRPERSON, 'BAC Vice Chairperson', 'Vice Printed Name'],
            ['bac_member_1', 'BACMEM-ABS-001', User::ROLE_BAC_MEMBER, 'BAC Member I', 'Member One Printed Name'],
            ['bac_member_2', 'BACMEM-ABS-002', User::ROLE_BAC_MEMBER, 'BAC Member II', 'Member Two Printed Name'],
            ['bac_member_alternate', 'BACMEM-ABS-003', User::ROLE_BAC_MEMBER, 'BAC Member III', 'Member Three Printed Name'],
        ] as [$slot, $account, $role, $designation, $name]) {
            $signers[] = User::factory()->create(['user_id' => $account, 'name' => $name, 'role' => $role,
                'office_id' => $office->id, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);
            $committee[$slot] = ['name' => $name, 'account_code' => $account, 'designation' => $designation];
        }

        return [$staff, $rfq, $committee, $signers];
    }
}
