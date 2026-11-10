<?php

namespace Tests\Unit;

use App\Models\ProcurementDocument;
use App\Services\OfficialPrNumberService;
use Tests\TestCase;

class OfficialPrNumberServiceTest extends TestCase
{
    public function test_fund_code_comes_from_the_pr_fund_cluster(): void
    {
        $service = new OfficialPrNumberService;
        foreach (['General Fund' => '101', 'general fund (development)' => '101', 'GF' => '101', 'Trust Fund' => '401', '401' => '401', 'Special Fund' => '221', 'Special Education Fund' => '221', 'SEF' => '221', 'School Fund' => '221'] as $fund => $code) {
            $this->assertSame($code, $service->fundCode(new ProcurementDocument(['fund_cluster' => (string) $fund])));
        }
        $this->assertNull($service->fundCode(new ProcurementDocument(['fund_cluster' => 'General Fund / Trust Fund'])));
        $this->assertNull($service->fundCode(new ProcurementDocument(['fund_cluster' => 'Unknown fund'])));
        $this->assertNull($service->fundCode(new ProcurementDocument));
    }

    public function test_examples_use_the_fund_and_official_date(): void
    {
        $service = new OfficialPrNumberService;
        foreach (['General Fund' => '101', 'Trust Fund' => '401', 'Special Fund' => '221'] as $fund => $code) {
            $this->assertSame($code.'-2026-09-001', $service->example(new ProcurementDocument(['fund_cluster' => $fund]), '2026-09-15'));
        }
    }

    public function test_the_lgu_examples_are_valid_and_sequences_can_exceed_999(): void
    {
        $service = new OfficialPrNumberService;
        foreach (['General Fund' => '101-2026-09-350', 'Trust Fund' => '401-2026-09-010', 'Special Education Fund' => '221-2026-09-002'] as $fund => $number) {
            $this->assertNull($service->validationError(new ProcurementDocument(['fund_cluster' => $fund]), $number, '2026-09-15'));
        }
        $this->assertNull($service->validationError(new ProcurementDocument(['fund_cluster' => 'General Fund']), '101-2026-09-1000', '2026-09-15'));
    }

    public function test_wrong_fund_date_and_zero_sequences_are_rejected(): void
    {
        $service = new OfficialPrNumberService;
        $pr = new ProcurementDocument(['fund_cluster' => 'General Fund']);
        foreach (['401-2026-09-010', 'PR-2026-001', '101-2026-13-001', '101-2026-09-01', '101-2026-09-000', '101-2025-09-001', '101-2026-08-001'] as $number) {
            $this->assertNotNull($service->validationError($pr, $number, '2026-09-15'));
        }
        $this->assertNotNull($service->validationError(new ProcurementDocument, '101-2026-09-001', '2026-09-15'));
    }
}
