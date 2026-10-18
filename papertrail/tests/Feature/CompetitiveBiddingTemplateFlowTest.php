<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitiveBiddingTemplateFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_bac_secretariat_templates_use_content_flow_and_repeated_print_margins(): void
    {
        $user = User::where('user_id', 'BACSEC-002')->firstOrFail();
        $user->update([
            'role' => User::ROLE_BAC_SECRETARIAT,
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);
        $this->actingAs($user);

        foreach ([
            'pre-bid-minutes' => '25.4mm 19.05mm',
            'bid-evaluation' => '18mm 22mm 16mm',
            'post-qualification-evaluation' => '17mm 22mm 16mm',
            'post-qualification-report-twg' => '18mm 24mm 16mm',
            'notice-of-post-qualification' => '23mm 27mm 20mm',
            'bac-resolution-on-post-qualification' => '20mm 27mm 18mm',
            'notice-of-award' => '22mm 27mm 20mm',
            'contract-agreement' => '20mm 25mm 18mm',
            'notice-to-proceed' => '24mm 30mm 20mm',
        ] as $template => $inset) {
            $response = $this->get(route("bac-secretariat.competitive-bidding.{$template}.template"));

            $response->assertOk()
                ->assertSee('data-competitive-document', false)
                ->assertSee('display: contents !important;', false)
                ->assertSee("margin: {$inset};", false);
        }

        $this->get(route('bac-secretariat.competitive-bidding.contract-agreement.template'))
            ->assertDontSee('consists of 3 pages');

        $this->get(route('bac-secretariat.competitive-bidding.checklist.technical.template'))
            ->assertOk()
            ->assertSee('margin: 14mm 18mm 12mm;', false)
            ->assertDontSee('technical-page-break');

        $this->get(route('bac-secretariat.competitive-bidding.checklist.financial.template'))
            ->assertOk()
            ->assertSee('margin: 12mm 13mm 10mm;', false);

        foreach (['as-read', 'as-calculated'] as $abstract) {
            $this->get(route("bac-secretariat.competitive-bidding.abstract-bids.{$abstract}.template"))
                ->assertOk()
                ->assertSee('size: A4 landscape;', false);
        }
    }
}
