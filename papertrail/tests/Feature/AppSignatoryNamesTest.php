<?php

namespace Tests\Feature;

use App\Http\Controllers\BacSecretariat\AnnualProcurementPlanController;
use App\Models\AnnualProcurementPlan;
use ReflectionMethod;
use Tests\TestCase;

class AppSignatoryNamesTest extends TestCase
{
    public function test_new_signatories_have_blank_names_and_keep_budget_routing(): void
    {
        $method = new ReflectionMethod(AnnualProcurementPlanController::class, 'signatories');
        $method->setAccessible(true);
        $signatories = $method->invoke(app(AnnualProcurementPlanController::class), [
            'approved_by' => ['title' => 'Budget Officer', 'title_manually_selected' => true],
        ]);

        foreach ($signatories as $signatory) {
            $this->assertEmpty($signatory['name']);
        }
        $this->assertSame('MBO-001', $signatories['approved_by']['account_code']);
    }

    public function test_editor_shows_placeholders_without_default_names(): void
    {
        $html = $this->renderForm('create');

        $this->assertSame(3, substr_count($html, 'data-placeholder="Enter Name"'));
        foreach (['JOBELLE A. SOLER', 'EDMAR PETER T. TAMBIS', 'AURELIO H. SUNGA, JR.'] as $name) {
            $this->assertStringNotContainsString($name, $html);
        }
    }

    public function test_saved_names_are_preserved_in_edit_and_print_views(): void
    {
        $signatories = ['prepared_by' => ['name' => 'Saved Signatory Name']];

        $this->assertStringContainsString('Saved Signatory Name', $this->renderForm('edit', $signatories));
        $printed = $this->renderForm('print', $signatories);
        $this->assertStringContainsString('Saved Signatory Name', $printed);
        $this->assertStringNotContainsString('data-placeholder="Enter Name"', $printed);
        $this->assertStringNotContainsString('data-placeholder="Enter Name"', $this->renderForm('edit', [], true));
    }

    private function renderForm(string $mode, array $signatories = [], bool $locked = false): string
    {
        $app = new AnnualProcurementPlan([
            'fiscal_year' => 2026,
            'items_json' => [],
            'signatories_json' => $signatories,
        ]);

        return view('bac-secretariat.app.partials.app-landscape-form', [
            'app' => $app,
            'mode' => $mode,
            'signatoriesLocked' => $locked,
        ])->render();
    }
}
