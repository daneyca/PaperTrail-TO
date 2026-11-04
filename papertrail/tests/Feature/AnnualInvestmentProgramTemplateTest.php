<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\CompetitiveBiddingSignatureDocument;
use App\Models\ElectronicSignature;
use App\Models\Office;
use App\Models\SignatureRequest;
use App\Services\SignatureRequestService;
use App\Services\ElectronicSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class AnnualInvestmentProgramTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_bacsec_003_can_open_the_aip_template_from_its_menu(): void
    {
        $this->actingAs($this->bacsec003());

        $this->get(route('bac-secretariat.competitive-bidding.menu', ['task' => 'annual-investment-program']))
            ->assertOk()
            ->assertSee('Create / Open Template')
            ->assertSee(route('bac-secretariat.competitive-bidding.annual-investment-program.template'), false);

        $this->get(route('bac-secretariat.competitive-bidding.annual-investment-program.template'))
            ->assertOk()
            ->assertSee('Annual Investment Program (AIP)')
            ->assertSee('Climate Change<br>Adaptation', false)
            ->assertSee('size:A4 landscape', false)
            ->assertSee('.aip-sheet, .aip-sheet * { visibility:visible !important; }', false)
            ->assertSee('name="section_labels[other]"', false)
            ->assertSee('name="section_colors[other]"', false)
            ->assertSee('data-add-aip-row="general"', false)
            ->assertSee('Prepared by:')
            ->assertSee('Save Excel');
    }

    public function test_other_bac_secretariat_account_cannot_open_or_export_the_aip_template(): void
    {
        $user = User::where('user_id', 'BACSEC-001')->firstOrFail();
        $user->update(['role' => User::ROLE_BAC_SECRETARIAT, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);
        $this->actingAs($user);

        $this->get(route('bac-secretariat.competitive-bidding.annual-investment-program.template'))->assertForbidden();
        $this->post(route('bac-secretariat.competitive-bidding.annual-investment-program.export'))->assertForbidden();
    }

    public function test_aip_excel_export_has_the_official_columns_and_calculated_total(): void
    {
        $this->actingAs($this->bacsec003());

        $response = $this->post(route('bac-secretariat.competitive-bidding.annual-investment-program.export'), [
            'year' => 2026,
            'municipality' => 'Tomas Oppus',
            'section_labels' => ['general' => 'GENERAL SERVICES', 'other' => 'ECONOMIC DEVELOPMENT'],
            'section_colors' => ['general' => '#f4df9c', 'other' => '#aabbcc'],
            'items' => [
                [
                    'section' => 'general',
                    'reference' => '1000-3-01-001-001',
                    'description' => 'Test project',
                    'ps' => 100,
                    'mooe' => 250,
                    'co' => 50,
                ],
                [
                    'section' => 'general',
                    'description' => 'Second general project',
                    'mooe' => 75,
                ],
                [
                    'section' => 'other',
                    'description' => 'Other project',
                    'co' => 200,
                ],
            ],
        ]);

        $response->assertOk()->assertDownload('Tomas-Oppus-AIP-2026.xlsx');
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertIsString($sheet);
        $this->assertStringContainsString('Annual Investment Program (AIP)', $sheet);
        $this->assertStringContainsString('Climate Change Adaptation', $sheet);
        $this->assertStringContainsString('Test project', $sheet);
        $this->assertStringContainsString('Second general project', $sheet);
        $this->assertStringContainsString('ECONOMIC DEVELOPMENT', $sheet);
        $this->assertStringContainsString('<v>400</v>', $sheet);
        $this->assertStringContainsString('<v>675</v>', $sheet);
        $styles = $zip->getFromName('xl/styles.xml');
        $this->assertStringContainsString('FFAABBCC', $styles);
        $this->assertStringContainsString('FFF4DF9C', $styles);
        $zip->close();
    }

    public function test_blank_section_is_omitted_from_excel(): void
    {
        $this->actingAs($this->bacsec003());

        $response = $this->post(route('bac-secretariat.competitive-bidding.annual-investment-program.export'), [
            'year' => 2026,
            'municipality' => 'Tomas Oppus',
            'section_labels' => ['general' => 'GENERAL SERVICES', 'other' => 'OTHER SERVICES'],
            'section_colors' => ['general' => '#f4df9c', 'other' => '#f4df9c'],
            'items' => [
                ['section' => 'general', 'description' => 'General project', 'mooe' => 100],
                ['section' => 'other', 'description' => '', 'mooe' => ''],
            ],
        ]);

        $response->assertOk();
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('GENERAL SERVICES', $sheet);
        $this->assertStringNotContainsString('OTHER SERVICES', $sheet);
        $zip->close();
    }

    public function test_aip_is_routed_to_the_three_selected_accounts_with_only_filled_sections(): void
    {
        $this->actingAs($this->bacsec003());
        $planning = $this->signer('MPDO-001', User::ROLE_HEAD_OFFICE, 'MPDO');
        $budget = $this->signer('BUDGET-001', User::ROLE_BUDGET, 'MBO');
        $mayor = $this->signer('HOPE-001', User::ROLE_APPROVING_AUTHORITY, 'MO');

        $response = $this->post(route('bac-secretariat.competitive-bidding.annual-investment-program.submit'), [
            'year' => 2026,
            'municipality' => 'Tomas Oppus',
            'section_labels' => ['general' => 'GENERAL SERVICES', 'other' => 'OTHER SERVICES'],
            'section_colors' => ['general' => '#f4df9c', 'other' => '#aabbcc'],
            'items' => [
                ['section' => 'general', 'description' => 'General project', 'mooe' => 100],
                ['section' => 'other', 'description' => '', 'mooe' => ''],
            ],
            'signers' => ['prepared' => $planning->id, 'reviewed' => $budget->id, 'approved' => $mayor->id],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $document = CompetitiveBiddingSignatureDocument::where('template_key', 'annual_investment_program')->firstOrFail();
        $response->assertRedirect(route('bac-secretariat.competitive-bidding.annual-investment-program.show', $document));
        $this->assertStringContainsString('GENERAL SERVICES', $document->document_html);
        $this->assertStringNotContainsString('OTHER SERVICES', $document->document_html);
        $this->assertSame('pending_signatures', $document->status);
        $this->assertSame(3, $document->signatureRequests()->count());

        foreach (['prepared' => $planning, 'reviewed' => $budget, 'approved' => $mayor] as $slot => $user) {
            $request = $document->signatureRequests()->where('signatory_slot', $slot)->firstOrFail();
            $this->assertSame($user->id, $request->requested_to_user_id);
            $this->assertContains($request->status, [SignatureRequest::STATUS_PENDING, SignatureRequest::STATUS_NOTIFIED]);
            $this->assertTrue(app(SignatureRequestService::class)->requestsForUser($user)->whereKey($request->id)->exists());
        }

        $preparedRequest = $document->signatureRequests()->where('signatory_slot', 'prepared')->firstOrFail();
        $this->assertFalse(app(SignatureRequestService::class)->requestsForUser($mayor)->whereKey($preparedRequest->id)->exists());

        $signature = new ElectronicSignature([
            'document_type' => 'competitive_bidding_document',
            'document_id' => $document->id,
            'signer_user_id' => $planning->id,
        ]);
        $this->assertTrue(app(ElectronicSignatureService::class)->canViewSignature($this->bacsec003(), $signature));
        $this->assertTrue(app(ElectronicSignatureService::class)->canViewSignature($mayor, $signature));
        $outsider = $this->signer('MEO-001', User::ROLE_HEAD_OFFICE, 'MEO');
        $this->assertFalse(app(ElectronicSignatureService::class)->canViewSignature($outsider, $signature));

        $this->get(route('bac-secretariat.competitive-bidding.annual-investment-program.show', $document))
            ->assertOk()
            ->assertSee('General project')
            ->assertSee('[id^="aip-signed-preview-"] .aip-snapshot * { visibility:visible !important; }', false);
        $this->get(route('bac-secretariat.competitive-bidding.menu', ['task' => 'annual-investment-program']))
            ->assertOk()->assertSee($document->displayNumber());

        $planning->update(['must_change_password' => false]);
        $this->actingAs($planning)
            ->get(route('signature-requests.show', $preparedRequest))
            ->assertOk()->assertSee('General project')->assertSee('data-aip-signature-slot="prepared"', false);
        $this->actingAs($mayor)
            ->get(route('signature-requests.show', $preparedRequest))
            ->assertForbidden();

        $declined = app(ElectronicSignatureService::class)->declineSignature(
            $document, $planning, 'competitive_bidding_document', 'confirmed', 'Please correct the AIP amount.', null, $preparedRequest,
        );
        $this->assertTrue($declined['ok']);
        $this->assertSame(CompetitiveBiddingSignatureDocument::STATUS_RETURNED, $document->fresh()->status);
        $this->assertSame(2, $document->signatureRequests()->where('status', SignatureRequest::STATUS_CANCELLED)->count());
    }

    public function test_aip_rejects_wrong_signer_and_empty_document(): void
    {
        $this->actingAs($this->bacsec003());
        $planning = $this->signer('MPDO-001', User::ROLE_HEAD_OFFICE, 'MPDO');
        $budget = $this->signer('BUDGET-001', User::ROLE_BUDGET, 'MBO');
        $wrongMayor = $this->bacsec003();

        $data = [
            'year' => 2026,
            'municipality' => 'Tomas Oppus',
            'section_labels' => ['general' => 'GENERAL SERVICES', 'other' => 'OTHER SERVICES'],
            'section_colors' => ['general' => '#f4df9c', 'other' => '#f4df9c'],
            'items' => [['section' => 'general', 'description' => 'General project']],
            'signers' => ['prepared' => $planning->id, 'reviewed' => $budget->id, 'approved' => $wrongMayor->id],
        ];

        $this->from(route('bac-secretariat.competitive-bidding.annual-investment-program.template'))
            ->post(route('bac-secretariat.competitive-bidding.annual-investment-program.submit'), $data)
            ->assertRedirect()->assertSessionHasErrors('signers.approved');

        $data['items'] = [['section' => 'general', 'description' => '']];
        $this->from(route('bac-secretariat.competitive-bidding.annual-investment-program.template'))
            ->post(route('bac-secretariat.competitive-bidding.annual-investment-program.submit'), $data)
            ->assertRedirect()->assertSessionHasErrors('items');

        $this->assertSame(0, CompetitiveBiddingSignatureDocument::where('template_key', 'annual_investment_program')->count());
    }

    private function bacsec003(): User
    {
        $user = User::where('user_id', 'BACSEC-003')->firstOrFail();
        $user->update(['role' => User::ROLE_BAC_SECRETARIAT, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);

        return $user;
    }

    private function signer(string $userId, string $role, string $officeCode): User
    {
        $office = Office::firstOrCreate(['code' => $officeCode], [
            'name' => $officeCode.' Test Office',
            'type' => Office::TYPE_END_USER,
            'status' => Office::STATUS_ACTIVE,
        ]);
        $user = User::firstOrCreate(['user_id' => $userId], [
            'name' => $userId.' Test Signer',
            'email' => strtolower($userId).'@example.test',
            'password' => 'password',
            'role' => $role,
            'office_id' => $office->id,
            'status' => User::STATUS_ACTIVE,
        ]);
        $user->update([
            'role' => $role,
            'office_id' => $office->id,
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);

        return $user;
    }
}
