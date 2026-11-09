<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfqExistingRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_numbered_rfq_is_visible_and_can_be_opened_from_the_creation_screen(): void
    {
        $user = $this->headOffice('RFQ-HEAD');
        $rfq = Rfq::create([
            'status' => Rfq::STATUS_SUBMITTED, 'prepared_by_user_id' => $user->id,
            'rfq_number' => 'RFQ-2026-002', 'rfq_date' => '2026-11-09', 'control_assigned_at' => now(),
        ]);

        $this->actingAs($user)->get(route('head-office.rfqs.create'))
            ->assertOk()->assertSee('Existing RFQs')->assertSee('RFQ-2026-002')
            ->assertSee($rfq->document_reference_number)->assertSee('Number and Date Assigned')
            ->assertSee(route('head-office.rfqs.show', $rfq), false)->assertSee('Open RFQ');
        $this->get(route('head-office.rfqs.show', $rfq))->assertOk()->assertSee('RFQ-2026-002');
    }

    public function test_existing_rfqs_from_another_office_are_not_listed_or_accessible(): void
    {
        $owner = $this->headOffice('RFQ-OWNER');
        $other = $this->headOffice('RFQ-OTHER');
        $rfq = Rfq::create([
            'status' => Rfq::STATUS_SUBMITTED, 'prepared_by_user_id' => $owner->id,
            'rfq_number' => 'OTHER-OFFICE-RFQ', 'rfq_date' => '2026-11-09',
        ]);
        $this->actingAs($other)->get(route('head-office.rfqs.create'))
            ->assertOk()->assertDontSee('OTHER-OFFICE-RFQ');
        $this->get(route('head-office.rfqs.show', $rfq))->assertForbidden();
    }

    private function headOffice(string $code): User
    {
        $office = Office::create([
            'code' => $code, 'name' => $code, 'type' => Office::TYPE_END_USER,
            'status' => Office::STATUS_ACTIVE, 'is_requesting_office' => true,
        ]);

        return User::factory()->create([
            'user_id' => $code, 'role' => User::ROLE_HEAD_OFFICE, 'office_id' => $office->id,
            'status' => User::STATUS_ACTIVE, 'must_change_password' => false,
        ]);
    }
}
