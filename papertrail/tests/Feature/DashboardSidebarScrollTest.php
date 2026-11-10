<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\SignatureRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardSidebarScrollTest extends TestCase
{
    use RefreshDatabase;

    public function test_svp_plans_has_a_parent_dot_while_ppmp_reviews_are_pending(): void
    {
        $user = User::where('user_id', 'BACSEC-003')->firstOrFail();
        $user->update(['role' => User::ROLE_BAC_SECRETARIAT, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);
        $document = ProcurementDocument::create(['document_type' => 'PPMP', 'title' => 'Pending PPMP review', 'fiscal_year' => 2026,
            'status' => ProcurementDocument::STATUS_PENDING_PPMP_REVIEW, 'submitting_office_id' => $user->office_id,
            'assigned_to_user_id' => $user->id]);
        $response = $this->actingAs($user)->get(route('bac-secretariat.dashboard'))->assertOk();
        $xpath = $this->sidebarXpath($response->getContent());
        $this->assertSame(1, $xpath->query('//div[@data-sidebar-key="SVP Plans"]/button/span[contains(@class,"sidebar-pending-dot")]')->length);
        $this->assertSame('1', trim($xpath->query('//div[@data-sidebar-key="SVP Plans"]//a[span[normalize-space(.)="PPMP Review"]]/span[contains(@class,"sidebar-count-badge")]')->item(0)->textContent));
        $this->assertSame(0, $xpath->query('//div[@data-sidebar-key="Signatures"]/button/span[contains(@class,"sidebar-pending-dot")]')->length);
        $document->update(['status' => ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION]);
        $xpath = $this->sidebarXpath($this->get(route('bac-secretariat.dashboard'))->assertOk()->getContent());
        $this->assertSame(0, $xpath->query('//div[@data-sidebar-key="SVP Plans"]/button/span[contains(@class,"sidebar-pending-dot")]')->length);
    }

    public function test_signature_dot_follows_the_pending_submenu_count(): void
    {
        $office = Office::create(['code' => 'DOTS', 'name' => 'Dot Test Office', 'type' => Office::TYPE_END_USER,
            'is_requesting_office' => true, 'status' => Office::STATUS_ACTIVE]);
        $user = User::factory()->create(['user_id' => 'DOTS-001', 'role' => User::ROLE_HEAD_OFFICE,
            'office_id' => $office->id, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);
        $document = ProcurementDocument::create(['document_type' => 'PR', 'title' => 'For signature', 'fiscal_year' => 2026,
            'status' => ProcurementDocument::STATUS_PR_PENDING_SIGNATORIES, 'submitting_office_id' => $office->id]);
        $request = SignatureRequest::create(['request_uuid' => (string) Str::uuid(), 'document_type' => 'purchase_request',
            'document_id' => $document->id, 'requested_to_user_id' => $user->id, 'is_required' => true,
            'status' => SignatureRequest::STATUS_NOTIFIED]);
        $xpath = $this->sidebarXpath($this->actingAs($user)->get(route('head-office.dashboard'))->assertOk()->getContent());
        $this->assertSame(1, $xpath->query('//div[@data-sidebar-key="Signatures"]/button/span[contains(@class,"sidebar-pending-dot")]')->length);
        $this->assertSame('1', trim($xpath->query('//div[@data-sidebar-key="Signatures"]//a[span[normalize-space(.)="Pending Signatures"]]/span[contains(@class,"sidebar-count-badge")]')->item(0)->textContent));
        $request->update(['status' => SignatureRequest::STATUS_SIGNED, 'signed_at' => now()]);
        $xpath = $this->sidebarXpath($this->get(route('head-office.dashboard'))->assertOk()->getContent());
        $this->assertSame(0, $xpath->query('//div[@data-sidebar-key="Signatures"]/button/span[contains(@class,"sidebar-pending-dot")]')->length);
    }

    private function sidebarXpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        return new \DOMXPath($dom);
    }

    public function test_bacsec_menus_hide_competitive_bidding_and_preserve_svp_assignments(): void
    {
        foreach (['BACSEC-001', 'BACSEC-002', 'BACSEC-003'] as $userId) {
            $user = User::where('user_id', $userId)->firstOrFail();
            $user->update([
                'role' => User::ROLE_BAC_SECRETARIAT,
                'status' => User::STATUS_ACTIVE,
                'must_change_password' => false,
            ]);

            $response = $this->actingAs($user)->get(route('bac-secretariat.dashboard'))->assertOk();
            $response->assertDontSee('data-sidebar-key="Competitive Bidding"', false)
                ->assertSee(route('status-tracking.index'), false);

            if ($userId !== 'BACSEC-003') {
                $response->assertDontSee('SVP Plans');
            } else {
                $response->assertSee('SVP Plans');
            }
        }
    }

    public function test_bacsec_003_task_page_renders_sidebar_scroll_restoration(): void
    {
        $user = User::where('user_id', 'BACSEC-003')->firstOrFail();
        $user->update([
            'role' => User::ROLE_BAC_SECRETARIAT,
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->get(route('bac-secretariat.competitive-bidding.menu', ['task' => 'bac-secretariat-head-certification']))
            ->assertOk()
            ->assertSee('Certification of the Head of the BAC Secretariat')
            ->assertDontSee('9 actions')
            ->assertDontSee('For setup')
            ->assertSee('papertrail_sidebar_scroll_', false)
            ->assertSee('papertrail_sidebar_dropdowns_', false)
            ->assertSee('data-sidebar-key="Security &amp; Recovery"', false)
            ->assertSee("getComputedStyle(sidebarNav).overflowY", false)
            ->assertSee('sessionStorage.setItem(sidebarScrollStorageKey, String(sidebarScrollArea.scrollTop))', false)
            ->assertSee('saveSidebarDropdowns(dropdown)', false)
            ->assertSee('activeLink.getBoundingClientRect()', false);
    }
}
