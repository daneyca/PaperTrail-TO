<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AnnualInvestmentProgramDraft;
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
            ->assertSee('class="aip-page-heading"', false)
            ->assertSee('class="aip-signatures-row"', false)
            ->assertSee('grid-template-columns:62mm 18mm minmax(0,1fr)', false)
            ->assertSee('data-aip-action="add-section" class="aip-main-only"', false)
            ->assertSee('id="aip-section-template"', false)
            ->assertSee('Add Subheading')
            ->assertSee('Recipient account')
            ->assertSee('BACCHAIR-001')
            ->assertSee('MBO-001')
            ->assertSee('MO-001')
            ->assertDontSee('name="signers[prepared]"', false)
            ->assertSee('name="prepared_name" value="" placeholder="Enter Name"', false)
            ->assertSee('name="prepared_title" value="" placeholder="Enter Position"', false)
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
        $this->postJson(route('bac-secretariat.competitive-bidding.annual-investment-program.draft.store'), [])->assertForbidden();
    }

    public function test_bacsec_003_can_save_and_reopen_an_unfinished_aip_draft(): void
    {
        $user = $this->bacsec003();
        $this->actingAs($user);
        $payload = [
            'year' => '2026',
            'municipality' => 'Tomas Oppus',
            'climate_change' => 0,
            'sections' => [
                ['key' => 'economic', 'type' => 'section', 'label' => 'ECONOMIC SERVICES', 'color' => '#94d6d1'],
                ['key' => 'agriculture', 'type' => 'subheading', 'label' => '', 'color' => '#c3dfca'],
            ],
            'items' => [
                ['section' => 'agriculture', 'description' => 'Seeds project', 'mooe' => '100'],
            ],
            'signers' => ['prepared' => null, 'reviewed' => null, 'approved' => null],
        ];

        $this->postJson(route('bac-secretariat.competitive-bidding.annual-investment-program.draft.store'), [
            'revision' => 0,
            'draft' => $payload,
        ])->assertOk()->assertJsonPath('revision', 1);

        $draft = AnnualInvestmentProgramDraft::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Seeds project', $draft->payload['items'][0]['description']);
        $this->assertSame('', $draft->payload['sections'][1]['label']);
        $this->assertSame(0, CompetitiveBiddingSignatureDocument::where('template_key', 'annual_investment_program')->count());

        $this->get(route('bac-secretariat.competitive-bidding.annual-investment-program.template'))
            ->assertOk()
            ->assertSee('Seeds project')
            ->assertSee('ECONOMIC SERVICES')
            ->assertSee('Saved draft');
    }

    public function test_aip_draft_revisions_prevent_overwriting_and_new_draft_clears_only_the_draft(): void
    {
        $user = $this->bacsec003();
        $this->actingAs($user);
        $url = route('bac-secretariat.competitive-bidding.annual-investment-program.draft.store');
        $payload = [
            'year' => '2026',
            'municipality' => 'Tomas Oppus',
            'sections' => [['key' => 'general', 'type' => 'section', 'label' => 'GENERAL SERVICES', 'color' => '#f4df9c']],
            'items' => [['section' => 'general', 'description' => 'First edit']],
        ];

        $this->postJson($url, ['revision' => 0, 'draft' => $payload])->assertOk()->assertJsonPath('revision', 1);
        $payload['items'][0]['description'] = 'Latest edit';
        $this->postJson($url, ['revision' => 1, 'draft' => $payload])->assertOk()->assertJsonPath('revision', 2);
        $payload['items'][0]['description'] = 'Stale edit';
        $this->postJson($url, ['revision' => 1, 'draft' => $payload])->assertStatus(409);
        $this->assertSame('Latest edit', AnnualInvestmentProgramDraft::where('user_id', $user->id)->firstOrFail()->payload['items'][0]['description']);

        $deleteUrl = route('bac-secretariat.competitive-bidding.annual-investment-program.draft.destroy');
        $this->deleteJson($deleteUrl, ['revision' => 1])->assertStatus(409);
        $this->deleteJson($deleteUrl, ['revision' => 2])->assertOk();
        $this->assertDatabaseMissing('annual_investment_program_drafts', ['user_id' => $user->id]);
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

    public function test_aip_exports_ordered_main_sections_and_subheadings_with_their_colors(): void
    {
        $this->actingAs($this->bacsec003());

        $response = $this->post(route('bac-secretariat.competitive-bidding.annual-investment-program.export'), [
            'year' => 2026,
            'municipality' => 'Tomas Oppus',
            'sections' => [
                ['key' => 'general', 'type' => 'section', 'label' => 'GENERAL SERVICES', 'color' => '#f4df9c'],
                ['key' => 'economic', 'type' => 'section', 'label' => 'ECONOMIC SERVICES', 'color' => '#94d6d1'],
                ['key' => 'agriculture', 'type' => 'subheading', 'label' => 'AGRICULTURAL SERVICES', 'color' => '#c3dfca'],
                ['key' => 'social', 'type' => 'section', 'label' => 'SOCIAL SERVICES', 'color' => '#b9deaa'],
                ['key' => 'unused', 'type' => 'section', 'label' => 'EMPTY SECTION', 'color' => '#aabbcc'],
            ],
            'items' => [
                ['section' => 'general', 'description' => 'General project', 'ps' => 100],
                ['section' => 'economic', 'description' => 'Economic project', 'co' => 200],
                ['section' => 'agriculture', 'description' => 'Agriculture project', 'mooe' => 300],
                ['section' => 'social', 'description' => 'Social project', 'mooe' => 400],
            ],
        ]);

        $response->assertOk();
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $styles = $zip->getFromName('xl/styles.xml');
        $this->assertIsString($sheet);
        $this->assertIsString($styles);
        $this->assertTrue(strpos($sheet, 'GENERAL SERVICES') < strpos($sheet, 'ECONOMIC SERVICES'));
        $this->assertTrue(strpos($sheet, 'ECONOMIC SERVICES') < strpos($sheet, 'AGRICULTURAL SERVICES'));
        $this->assertTrue(strpos($sheet, 'AGRICULTURAL SERVICES') < strpos($sheet, 'SOCIAL SERVICES'));
        $this->assertStringNotContainsString('EMPTY SECTION', $sheet);
        $this->assertStringContainsString('<v>1000</v>', $sheet);
        foreach (['FFF4DF9C', 'FF94D6D1', 'FFC3DFCA', 'FFB9DEAA'] as $color) {
            $this->assertStringContainsString($color, $styles);
        }
        $zip->close();
    }

    public function test_aip_printed_snapshot_keeps_parent_section_when_only_its_subheading_has_rows(): void
    {
        $this->actingAs($this->bacsec003());
        $this->signer('BACCHAIR-001', User::ROLE_BAC_CHAIR, 'BAC');
        $this->signer('MBO-001', User::ROLE_HEAD_OFFICE, 'MBO');
        $this->signer('MO-001', User::ROLE_HEAD_OFFICE, 'MO');

        $this->post(route('bac-secretariat.competitive-bidding.annual-investment-program.submit'), [
            'year' => 2026,
            'municipality' => 'Tomas Oppus',
            'sections' => [
                ['key' => 'economic', 'type' => 'section', 'label' => 'ECONOMIC SERVICES', 'color' => '#94d6d1'],
                ['key' => 'agriculture', 'type' => 'subheading', 'label' => 'AGRICULTURAL SERVICES', 'color' => '#c3dfca'],
                ['key' => 'other', 'type' => 'section', 'label' => 'OTHER SERVICES', 'color' => '#f4df9c'],
            ],
            'items' => [
                ['section' => 'economic', 'description' => ''],
                ['section' => 'agriculture', 'description' => 'Seeds project', 'mooe' => 500],
                ['section' => 'other', 'description' => ''],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $html = CompetitiveBiddingSignatureDocument::where('template_key', 'annual_investment_program')->firstOrFail()->document_html;
        $this->assertStringContainsString('class="aip-snapshot-heading-row"', $html);
        $this->assertStringContainsString('class="aip-snapshot-signatures-row"', $html);
        $this->assertStringContainsString('ECONOMIC SERVICES', $html);
        $this->assertStringContainsString('AGRICULTURAL SERVICES', $html);
        $this->assertStringContainsString('Seeds project', $html);
        $this->assertStringNotContainsString('OTHER SERVICES', $html);
    }

    public function test_aip_rejects_rows_in_missing_sections_and_subheadings_without_a_main_section(): void
    {
        $this->actingAs($this->bacsec003());
        $data = [
            'year' => 2026,
            'municipality' => 'Tomas Oppus',
            'sections' => [['key' => 'general', 'type' => 'section', 'label' => 'GENERAL SERVICES', 'color' => '#f4df9c']],
            'items' => [['section' => 'missing', 'description' => 'Test project']],
        ];

        $this->post(route('bac-secretariat.competitive-bidding.annual-investment-program.export'), $data)
            ->assertSessionHasErrors('items');

        $data['sections'][0]['type'] = 'subheading';
        $data['items'][0]['section'] = 'general';
        $this->post(route('bac-secretariat.competitive-bidding.annual-investment-program.export'), $data)
            ->assertSessionHasErrors('sections');
    }

    public function test_aip_is_routed_to_the_three_assigned_accounts_with_only_filled_sections(): void
    {
        $this->actingAs($this->bacsec003());
        $planning = $this->signer('BACCHAIR-001', User::ROLE_BAC_CHAIR, 'BAC');
        $budget = $this->signer('MBO-001', User::ROLE_HEAD_OFFICE, 'MBO');
        $mayor = $this->signer('MO-001', User::ROLE_HEAD_OFFICE, 'MO');
        $outsider = $this->signer('MEO-001', User::ROLE_HEAD_OFFICE, 'MEO');

        $response = $this->post(route('bac-secretariat.competitive-bidding.annual-investment-program.submit'), [
            'year' => 2026,
            'municipality' => 'Tomas Oppus',
            'section_labels' => ['general' => 'GENERAL SERVICES', 'other' => 'OTHER SERVICES'],
            'section_colors' => ['general' => '#f4df9c', 'other' => '#aabbcc'],
            'items' => [
                ['section' => 'general', 'description' => 'General project', 'mooe' => 100],
                ['section' => 'other', 'description' => '', 'mooe' => ''],
            ],
            'prepared_title' => 'Acting Local Planning and Development Coordinator',
            'signers' => ['prepared' => $outsider->id, 'reviewed' => $outsider->id, 'approved' => $outsider->id],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $document = CompetitiveBiddingSignatureDocument::where('template_key', 'annual_investment_program')->firstOrFail();
        $response->assertRedirect(route('bac-secretariat.competitive-bidding.annual-investment-program.show', $document));
        $this->assertStringContainsString('GENERAL SERVICES', $document->document_html);
        $this->assertStringNotContainsString('OTHER SERVICES', $document->document_html);
        $this->assertSame('pending_signatures', $document->status);
        $this->assertSame(3, $document->signatureRequests()->count());
        $this->assertSame('Acting Local Planning and Development Coordinator', $document->signatories[0]['title']);

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

    public function test_aip_rejects_unavailable_recipient_and_empty_document(): void
    {
        $this->actingAs($this->bacsec003());
        $this->signer('BACCHAIR-001', User::ROLE_BAC_CHAIR, 'BAC');
        $this->signer('MBO-001', User::ROLE_HEAD_OFFICE, 'MBO');
        $mayor = $this->signer('MO-001', User::ROLE_HEAD_OFFICE, 'MO');
        $mayor->update(['status' => User::STATUS_INACTIVE]);

        $data = [
            'year' => 2026,
            'municipality' => 'Tomas Oppus',
            'section_labels' => ['general' => 'GENERAL SERVICES', 'other' => 'OTHER SERVICES'],
            'section_colors' => ['general' => '#f4df9c', 'other' => '#f4df9c'],
            'items' => [['section' => 'general', 'description' => 'General project']],
        ];

        $this->from(route('bac-secretariat.competitive-bidding.annual-investment-program.template'))
            ->post(route('bac-secretariat.competitive-bidding.annual-investment-program.submit'), $data)
            ->assertRedirect()->assertSessionHasErrors('signers.approved');

        $mayor->update(['status' => User::STATUS_ACTIVE]);
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
