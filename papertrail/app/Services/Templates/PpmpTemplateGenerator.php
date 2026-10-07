<?php

namespace App\Services\Templates;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

class PpmpTemplateGenerator
{
    public const OFFICIAL_TEMPLATE_PATH = 'templates/ppmp/ppmp-editable-template.xlsx';

    public function __construct(private SimpleXlsxBuilder $builder)
    {
    }

    public function generate(?User $user = null): array
    {
        $path = 'generated-templates/ppmp/ppmp_editable_template_' . now()->format('YmdHis') . '.xlsx';
        $this->saveOfficialTemplate($path, $user);

        return [
            'path' => $path,
            'filename' => 'PPMP_Editable_Template_' . now()->year . '.xlsx',
            'format' => 'xlsx',
        ];
    }

    public function ensureOfficialTemplate(?User $user = null): array
    {
        if (! Storage::disk('local')->exists(self::OFFICIAL_TEMPLATE_PATH)) {
            $this->saveOfficialTemplate(self::OFFICIAL_TEMPLATE_PATH, $user);
        }

        return [
            'path' => self::OFFICIAL_TEMPLATE_PATH,
            'filename' => 'PPMP_Editable_Template_' . now()->year . '.xlsx',
            'format' => 'xlsx',
        ];
    }

    private function saveOfficialTemplate(string $path, ?User $user = null): void
    {
        $rows = $this->officialRows($user);
        $options = $this->officialOptions();

        $this->builder->save(
            Storage::disk('local')->path($path),
            'PPMP Form',
            $rows,
            [18, 20, 42, 18, 16, 16, 16, 18, 16, 18, 16, 16],
            $options,
        );
    }

    private function officialRows(?User $user = null): array
    {
        $office = $user?->assignedOffice?->name ?? $user?->office ?? '';
        $office = trim((string) $office);
        $rows = [];

        $rows[] = ['', '', '', 'Republic of the Philippines', '', '', '', '', '', '', '', ''];
        $rows[] = ['', '', '', 'Province of Southern Leyte', '', '', '', '', '', '', '', ''];
        $rows[] = ['', '', '', 'MUNICIPALITY OF TOMAS OPPUS', '', '', '', '', '', '', '', ''];
        $rows[] = array_fill(0, 12, '');
        $rows[] = ['PROJECT PROCUREMENT PLAN (PPMP) NO.', '', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = ['[ ] INDICATIVE', '', '', '', '', '[ ] FINAL', '', '', '', '', '', ''];
        $rows[] = array_fill(0, 12, '');
        $rows[] = ['Fiscal Year :', now()->year, '', '', '', '', '', '', '', '', '', ''];
        $rows[] = ['End-User or Implementing Unit :', $office, '', '', '', '', '', '', '', '', '', ''];
        $rows[] = array_fill(0, 12, '');
        $rows[] = [
            'PROCUREMENT PROJECT DETAILS',
            '',
            '',
            '',
            '',
            'PROJECTED TIMELINE (MM/YYYY)',
            '',
            '',
            'FUNDING DETAILS',
            '',
            '',
            '',
        ];
        $rows[] = [
            'General Description and Objective of the Project to be Procured',
            'Type of Project to be Procured (whether Goods, Infrastructure, and Consulting Services)',
            'Quantity and Size of the Project to be Procured',
            'Recommended Mode of Procurement',
            'Pre-Procurement Conference, if applicable (Yes/No)',
            'Start of Procurement Activity',
            'End of Procurement Activity',
            'Expected Delivery/Implementation Period',
            'Source of Funds',
            'Estimated Budget / Authorized Budget Allocation',
            'ATTACHED SUPPORTING DOCUMENTS',
            'REMARKS',
        ];
        $rows[] = array_map(fn (int $column) => 'Column ' . $column, range(1, 12));

        for ($row = 0; $row < 18; $row++) {
            $rows[] = array_fill(0, 12, '');
        }

        $rows[] = ['', '', '', '', '', '', '', '', 'TOTAL BUDGET:', 0.00, '', ''];
        $rows[] = ['Note: Fill this official editable PPMP template and upload it back to PaperTrail. Uploaded templates are imported as Draft only.', '', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = array_fill(0, 12, '');
        $rows[] = array_fill(0, 12, '');
        $rows[] = ['Prepared by:', '', '', '', '', '', 'Submitted by:', '', '', '', '', ''];
        $rows[] = array_fill(0, 12, '');
        $rows[] = ['', '', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = ['Signature Over Printed Name', '', '', '', '', '', 'Signature Over Printed Name', '', '', '', '', ''];
        $rows[] = ['Position/Designation', '', '', '', '', '', 'Position/Designation', '', '', '', '', ''];
        $rows[] = ['(End-User or Implementing Unit)', '', '', '', '', '', '(Head of the End-User or Implementing Unit)', '', '', '', '', ''];
        $rows[] = array_fill(0, 12, '');
        $rows[] = ['Date:', '', '', '', '', '', 'Date:', '', '', '', '', ''];

        return $rows;
    }

    private function officialOptions(): array
    {
        $styles = [];

        foreach (range(1, 12) as $column) {
            $styles[1][$column] = 11;
            $styles[2][$column] = 11;
            $styles[3][$column] = 12;
            $styles[5][$column] = 10;
            $styles[6][$column] = 15;
            $styles[8][$column] = 14;
            $styles[9][$column] = 14;
            $styles[11][$column] = 2;
            $styles[12][$column] = 3;
            $styles[13][$column] = 4;
            $styles[32][$column] = $column === 10 ? 7 : 9;
            $styles[33][$column] = 14;
            $styles[36][$column] = 14;
            $styles[38][$column] = in_array($column, [1, 2, 3, 7, 8, 9], true) ? 13 : 14;
            $styles[39][$column] = in_array($column, [1, 2, 3, 7, 8, 9], true) ? 13 : 14;
            $styles[40][$column] = 14;
            $styles[42][$column] = in_array($column, [1, 2, 3, 7, 8, 9], true) ? 13 : 14;
        }

        for ($row = 14; $row <= 31; $row++) {
            foreach (range(1, 12) as $column) {
                $styles[$row][$column] = $column === 10 ? 7 : ($column === 1 || $column === 3 ? 6 : 5);
            }
        }

        return [
            'styles' => $styles,
            'merges' => [
                'D1:I1',
                'D2:I2',
                'D3:I3',
                'A5:L5',
                'A6:E6',
                'F6:L6',
                'B8:C8',
                'B9:E9',
                'A11:E11',
                'F11:H11',
                'I11:J11',
                'K11:K12',
                'L11:L12',
                'A33:L33',
                'A36:C36',
                'G36:I36',
                'A38:C38',
                'G38:I38',
                'A39:C39',
                'G39:I39',
                'A40:C40',
                'G40:I40',
                'A42:C42',
                'G42:I42',
            ],
            'rowHeights' => [
                1 => 15,
                2 => 15,
                3 => 16,
                5 => 21,
                6 => 18,
                11 => 16,
                12 => 58,
                13 => 16,
                33 => 18,
            ] + array_fill_keys(range(14, 31), 24),
            'pageSetup' => [
                'orientation' => 'landscape',
                'fitToWidth' => 1,
                'fitToHeight' => 0,
            ],
            'pageMargins' => [
                'left' => 0.2,
                'right' => 0.2,
                'top' => 0.25,
                'bottom' => 0.25,
                'header' => 0.1,
                'footer' => 0.1,
            ],
            'images' => $this->templateImages(),
        ];
    }

    private function templateImages(): array
    {
        $images = [];
        $lguLogo = public_path('images/logos/lgu-logo.png');
        $bagongLogo = public_path('images/logos/bagongpilipinas.jpg');

        if (is_file($lguLogo)) {
            $images[] = [
                'path' => $lguLogo,
                'from' => ['col' => 1, 'row' => 0],
                'to' => ['col' => 2, 'row' => 4],
                'name' => 'LGU Logo',
            ];
        }

        if (is_file($bagongLogo)) {
            $images[] = [
                'path' => $bagongLogo,
                'from' => ['col' => 9, 'row' => 0],
                'to' => ['col' => 11, 'row' => 4],
                'name' => 'Bagong Pilipinas',
            ];
        }

        return $images;
    }
}
