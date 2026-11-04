<?php

namespace Tests\Feature;

use App\Models\ProcurementDocument;
use App\Models\Rfq;
use App\Models\User;
use App\Services\GlobalSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_search_records(): void
    {
        $this->get(route('search.index', ['q' => 'medicine']))->assertRedirect(route('login'));
        $this->get(route('search.suggestions', ['q' => 'medicine']))->assertRedirect(route('login'));
    }

    public function test_head_office_search_only_returns_own_records(): void
    {
        $user = User::factory()->create([
            'user_id' => 'HEAD-SEARCH-001',
            'role' => User::ROLE_HEAD_OFFICE,
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);
        $other = User::factory()->create([
            'user_id' => 'HEAD-SEARCH-002',
            'role' => User::ROLE_HEAD_OFFICE,
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);

        $own = ProcurementDocument::query()->create([
            'tracking_number' => 'PT-MEDICINE-OWN',
            'document_type' => 'PR',
            'title' => 'Medicine for clinic',
            'fiscal_year' => 2026,
            'status' => ProcurementDocument::STATUS_PR_DRAFT,
            'prepared_by_user_id' => $user->id,
        ]);
        ProcurementDocument::query()->create([
            'tracking_number' => 'PT-MEDICINE-OTHER',
            'document_type' => 'PR',
            'title' => 'Medicine for another office',
            'fiscal_year' => 2026,
            'status' => ProcurementDocument::STATUS_PR_DRAFT,
            'prepared_by_user_id' => $other->id,
        ]);

        $this->assertSame('head-office', $user->roleSlug());
        $this->assertTrue(ProcurementDocument::query()->whereKey($own->id)->exists());
        $this->assertNotEmpty(app(GlobalSearchService::class)->search($user, 'medicine')->all());

        $this->actingAs($user)
            ->get(route('search.index', ['q' => 'medicine']))
            ->assertOk()
            ->assertSee('Medicine for clinic')
            ->assertDontSee('Medicine for another office')
            ->assertSee(route('head-office.documents.show', $own), false);

        $this->getJson(route('search.suggestions', ['q' => 'medicine']))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.title', 'Medicine for clinic');
    }

    public function test_short_query_returns_no_results(): void
    {
        $user = User::factory()->create([
            'user_id' => 'HEAD-SEARCH-003',
            'role' => User::ROLE_HEAD_OFFICE,
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->getJson(route('search.suggestions', ['q' => 'a']))
            ->assertOk()
            ->assertJsonCount(0, 'results');

        $this->get(route('search.index') . '?q[]=invalid')
            ->assertOk()
            ->assertSee('Enter at least 2 characters');
    }

    public function test_bac_member_search_only_returns_assigned_documents(): void
    {
        $member = User::factory()->create([
            'user_id' => 'BACMEM-SEARCH-001',
            'role' => User::ROLE_BAC_MEMBER,
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);
        $other = User::factory()->create([
            'user_id' => 'BACMEM-SEARCH-002',
            'role' => User::ROLE_BAC_MEMBER,
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);

        foreach ([$member, $other] as $index => $assignee) {
            ProcurementDocument::query()->create([
                'tracking_number' => 'PT-GENERATORS-' . $index,
                'document_type' => 'PR',
                'title' => 'Generators for unit ' . $index,
                'fiscal_year' => 2026,
                'status' => ProcurementDocument::STATUS_PENDING_BAC_MEMBER_REVIEW,
                'assigned_to_user_id' => $assignee->id,
            ]);
        }

        $this->actingAs($member)
            ->getJson(route('search.suggestions', ['q' => 'Generators']))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.title', 'Generators for unit 0');
    }

    public function test_head_office_can_search_own_rfq_by_supplier(): void
    {
        $user = User::factory()->create([
            'user_id' => 'HEAD-SEARCH-RFQ',
            'role' => User::ROLE_HEAD_OFFICE,
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);
        Rfq::query()->create([
            'rfq_number' => 'RFQ-SUPPLIER-SEARCH',
            'supplier_name' => 'Northern Supplies',
            'status' => Rfq::STATUS_DRAFT,
            'prepared_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->getJson(route('search.suggestions', ['q' => 'Northern']))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.type', 'RFQ');
    }
}
