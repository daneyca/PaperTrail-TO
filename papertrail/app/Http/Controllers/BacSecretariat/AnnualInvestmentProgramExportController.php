<?php

namespace App\Http\Controllers\BacSecretariat;

use App\Http\Controllers\Controller;
use App\Services\AnnualInvestmentProgramService;
use App\Services\Templates\SimpleXlsxBuilder;
use Illuminate\Http\Request;

class AnnualInvestmentProgramExportController extends Controller
{
    public function __invoke(Request $request, SimpleXlsxBuilder $xlsx, AnnualInvestmentProgramService $aip)
    {
        abort_unless($request->user()?->user_id === 'BACSEC-003', 403);

        $data = $aip->validateDocument($request);

        $year = (int) $data['year'];
        $rows = [
            ["CY {$year} Annual Investment Program (AIP)"],
            ['By Program / Project / Activity by Sector'],
            ["As of January 1 to December 31, {$year}"],
            ['Municipality: '.$data['municipality']],
            [! empty($data['climate_change']) ? '[X] Climate Change Expenditure' : '[ ] Climate Change Expenditure'],
            ['AIP REFERENCE CODE', 'PROGRAM / PROJECT / ACTIVITY DESCRIPTION', 'Implementing Office / Department', 'Schedule of Implementation', '', 'Expected Output', 'FUNDING SOURCE', 'AMOUNT (in thousand pesos)', '', '', '', 'AMOUNT OF Climate Change Expenditures (in thousand pesos)', '', 'CC Typology Code'],
            ['', '', '', 'Starting Date', 'Completion Date', '', '', 'Personal Services (PS)', 'Maintenance and Other Operating Expenses (MOOE)', 'Capital Outlay (CO)', 'TOTAL', 'Climate Change Adaptation', 'Climate Change Mitigation', ''],
            array_map(fn ($number) => "({$number})", range(1, 14)),
        ];

        $rowStyles = [6 => 3, 7 => 3, 8 => 3];
        $merges = ['A1:N1', 'A2:N2', 'A3:N3', 'A4:N4', 'A5:N5', 'D6:E6', 'H6:K6', 'L6:M6'];
        $totals = array_fill(0, 5, 0.0);

        $visibleSections = $aip->visibleSections($data);
        foreach ($visibleSections as $sectionIndex => $section) {
            $rows[] = [$section['label']];
            $sectionRow = count($rows);
            $rowStyles[$sectionRow] = 17 + $sectionIndex;
            $merges[] = "A{$sectionRow}:N{$sectionRow}";

            foreach ($section['items'] as $item) {
                $ps = (float) ($item['ps'] ?? 0);
                $mooe = (float) ($item['mooe'] ?? 0);
                $co = (float) ($item['co'] ?? 0);
                $adaptation = (float) ($item['adaptation'] ?? 0);
                $mitigation = (float) ($item['mitigation'] ?? 0);
                $amount = $ps + $mooe + $co;
                foreach ([$ps, $mooe, $co, $amount, $adaptation + $mitigation] as $index => $value) {
                    $totals[$index] += $value;
                }

                $rows[] = [
                    $item['reference'] ?? '', $item['description'] ?? '', $item['office'] ?? '',
                    $item['start'] ?? '', $item['completion'] ?? '', $item['output'] ?? '',
                    $item['funding'] ?? '',
                    $ps ?: '', $mooe ?: '', $co ?: '', $amount ?: '',
                    $adaptation ?: '', $mitigation ?: '', $item['typology'] ?? '',
                ];
                $rowStyles[count($rows)] = 6;
            }
        }

        $rows[] = ['', 'TOTAL', '', '', '', '', '', ...array_slice($totals, 0, 4), '', '', ''];
        $rowStyles[count($rows)] = 8;
        $rows[] = [];
        $rows[] = ['Prepared by:', '', '', '', 'Reviewed by:', '', '', '', '', 'Approved by:'];
        $rows[] = [$data['prepared_name'] ?? '', '', '', '', $data['reviewed_name'] ?? '', '', '', '', '', $data['approved_name'] ?? ''];
        $rows[] = [$data['prepared_title'] ?? '', '', '', '', $data['reviewed_title'] ?? '', '', '', '', '', $data['approved_title'] ?? ''];
        $rows[] = ['Date: '.($data['prepared_date'] ?? ''), '', '', '', 'Date: '.($data['reviewed_date'] ?? ''), '', '', '', '', 'Date: '.($data['approved_date'] ?? '')];

        for ($row = count($rows) - 3; $row <= count($rows); $row++) {
            array_push($merges, "A{$row}:D{$row}", "E{$row}:I{$row}", "J{$row}:N{$row}");
        }

        $path = tempnam(sys_get_temp_dir(), 'aip_');
        $xlsx->save($path, 'Annual Investment Program', $rows,
            [18, 42, 23, 17, 17, 36, 16, 19, 22, 19, 21, 20, 20, 19],
            [
                'merges' => $merges,
                'rowStyles' => $rowStyles,
                'customFillColors' => array_map(fn (array $section) => substr($section['color'], 1), $visibleSections),
                'pageSetup' => ['orientation' => 'landscape', 'paperSize' => 8, 'fitToWidth' => 1, 'fitToHeight' => 0],
            ]);

        return response()->download($path, "Tomas-Oppus-AIP-{$year}.xlsx")
            ->deleteFileAfterSend(true);
    }
}
