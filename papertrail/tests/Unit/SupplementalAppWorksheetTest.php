<?php

namespace Tests\Unit;

use App\Models\{Office, ProcurementDocument, SupplementalApp, User};
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class SupplementalAppWorksheetTest extends TestCase
{
    public function test_editable_worksheet_preserves_source_fields_items_and_budget(): void
    {
        $data = $this->worksheetData();
        $html = view('bac-secretariat.supplemental-apps.partials.official-form-editable', $data)->render();
        foreach (['source_pr_document_id', 'fiscal_year', 'requesting_office_id', 'requesting_office_name', 'title', 'purpose', 'justification', 'items[0][description]', 'items[0][quantity]', 'items[0][estimated_unit_cost]', 'items[0][remarks]'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $html);
        }
        $this->assertStringContainsString('221-2026-09-002', $html);
        $this->assertStringContainsString('Procurement Details', $html);
        $this->assertStringContainsString('Estimated Funding', $html);
        $this->assertStringContainsString('PHP 1,000.00', $html);
        $this->assertStringContainsString('Additional laboratory supplies', $html);
    }

    public function test_saved_and_print_worksheets_preserve_supplemental_details_and_signatories(): void
    {
        $data = $this->worksheetData();
        foreach (['bac-secretariat.supplemental-apps.partials.official-form', 'bac-secretariat.supplemental-apps.print'] as $view) {
            $html = view($view, $data)->render();
            foreach (['SAPP-2026-001', '221-2026-09-002', 'Additional laboratory supplies', 'School Office', 'Current fiscal year additions', 'PHP 1,000.00', 'Prepared by:', 'Recommended by:', 'Approved by:'] as $text) {
                $this->assertStringContainsString($text, $html);
            }
        }
    }

    private function worksheetData(): array
    {
        $office = (new Office(['name' => 'School Office']))->forceFill(['id' => 7]);
        $user = (new User(['name' => 'Worksheet Preparer', 'role' => User::ROLE_BAC_SECRETARIAT]))->forceFill(['id' => 10]);
        $source = (new ProcurementDocument(['pr_no' => '221-2026-09-002', 'fiscal_year' => 2026]))->forceFill(['id' => 12])->setRelation('submittingOffice', $office);
        $items = [['item_no' => '1', 'description' => 'Additional laboratory supplies', 'quantity' => 5, 'unit' => 'sets', 'estimated_unit_cost' => 200, 'estimated_total_cost' => 1000, 'remarks' => 'Additional purchase', 'source_pr_item_id' => 1]];
        $supplementalApp = (new SupplementalApp([
            'supplemental_app_number' => 'SAPP-2026-001', 'fiscal_year' => 2026, 'source_pr_document_id' => 12,
            'requesting_office_id' => 7, 'requesting_office_name' => 'School Office',
            'title' => 'Current fiscal year additions', 'purpose' => 'Additional laboratory supplies',
            'justification' => 'Additional requirements for the current fiscal year.', 'total_amount' => 1000,
        ]))->forceFill(['id' => 1, 'created_at' => '2026-11-11 08:00:00'])
            ->setRelation('sourcePrDocument', $source)->setRelation('requestingOffice', $office)
            ->setRelation('preparedBy', $user)->setRelation('submittedBy', null)->setRelation('acceptedBy', null)
            ->setRelation('items', collect(array_map(fn ($item) => (object) $item, $items)));

        return ['supplementalApp' => $supplementalApp, 'sourceDocument' => $source, 'items' => $items, 'offices' => collect([$office]), 'errors' => new ViewErrorBag];
    }
}
