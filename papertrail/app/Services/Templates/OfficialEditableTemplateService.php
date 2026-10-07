<?php

namespace App\Services\Templates;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class OfficialEditableTemplateService
{
    public const ALIASES = [
        'po' => 'purchase_order',
    ];

    public const DEFINITIONS = [
        'ppmp' => [
            'label' => 'PPMP',
            'format' => 'xlsx',
            'path' => PpmpTemplateGenerator::OFFICIAL_TEMPLATE_PATH,
            'filename_prefix' => 'PPMP_Editable_Template',
        ],
        'app' => [
            'label' => 'APP',
            'format' => 'xlsx',
            'path' => 'templates/app/app-editable-template.xlsx',
            'filename_prefix' => 'APP_Editable_Template',
        ],
        'supplemental_app' => [
            'label' => 'Supplemental APP',
            'format' => 'xlsx',
            'path' => 'templates/supplemental-app/supplemental-app-editable-template.xlsx',
            'filename_prefix' => 'Supplemental_APP_Editable_Template',
        ],
        'pr' => [
            'label' => 'Purchase Request',
            'format' => 'xlsx',
            'path' => 'templates/pr/pr-editable-template.xlsx',
            'filename_prefix' => 'PR_Editable_Template',
        ],
        'rfq' => [
            'label' => 'RFQ',
            'format' => 'xlsx',
            'path' => 'templates/rfq/rfq-editable-template.xlsx',
            'filename_prefix' => 'RFQ_Editable_Template',
        ],
        'abstract' => [
            'label' => 'Abstract of Quotations',
            'format' => 'xlsx',
            'path' => 'templates/abstract/abstract-editable-template.xlsx',
            'filename_prefix' => 'Abstract_Editable_Template',
        ],
        'purchase_order' => [
            'label' => 'Purchase Order',
            'format' => 'xlsx',
            'path' => 'templates/purchase-order/purchase-order-editable-template.xlsx',
            'filename_prefix' => 'Purchase_Order_Editable_Template',
        ],
        'bac_resolution' => [
            'label' => 'BAC Resolution',
            'format' => 'docx',
            'path' => 'templates/bac-resolution/bac-resolution-editable-template.docx',
            'filename_prefix' => 'BAC_Resolution_Editable_Template',
        ],
        'received_bac_resolution' => [
            'label' => 'Received BAC Resolution',
            'format' => 'docx',
            'path' => 'templates/received-bac-resolution/received-bac-resolution-editable-template.docx',
            'filename_prefix' => 'Received_BAC_Resolution_Editable_Template',
        ],
        'inspection_acceptance' => [
            'label' => 'Inspection / Acceptance',
            'format' => 'xlsx',
            'path' => 'templates/inspection-acceptance/inspection-acceptance-editable-template.xlsx',
            'filename_prefix' => 'Inspection_Acceptance_Editable_Template',
        ],
    ];

    public function __construct(
        private SimpleXlsxBuilder $xlsx,
        private SimpleDocxBuilder $docx,
        private PpmpTemplateGenerator $ppmp,
    ) {
    }

    public function definitions(): array
    {
        return self::DEFINITIONS;
    }

    public function has(string $documentType): bool
    {
        return (bool) $this->canonicalType($documentType);
    }

    public function label(string $documentType): string
    {
        $canonical = $this->canonicalType($documentType);

        return $canonical
            ? self::DEFINITIONS[$canonical]['label']
            : str($documentType)->replace('_', ' ')->title()->toString();
    }

    public function format(string $documentType): string
    {
        return $this->definition($documentType)['format'];
    }

    public function path(string $documentType): string
    {
        return $this->definition($documentType)['path'];
    }

    public function directory(string $documentType): string
    {
        return dirname($this->path($documentType));
    }

    public function exists(string $documentType): bool
    {
        return Storage::disk('local')->exists($this->path($documentType));
    }

    public function downloadFilename(string $documentType, ?int $fiscalYear = null): string
    {
        $definition = $this->definition($documentType);
        $year = $fiscalYear ?: now()->year;

        if ($year < 2000 || $year > 2100) {
            $year = now()->year;
        }

        return $definition['filename_prefix'] . '_' . $year . '.' . $definition['format'];
    }

    public function routeKey(string $documentType): string
    {
        $canonical = $this->canonicalType($documentType);

        return str_replace('_', '-', $canonical ?: $documentType);
    }

    public function canonicalType(string $documentType): ?string
    {
        $normalized = strtolower(str_replace('-', '_', $documentType));
        $canonical = self::ALIASES[$normalized] ?? $normalized;

        return isset(self::DEFINITIONS[$canonical]) ? $canonical : null;
    }

    public function ensureAll(?User $user = null): void
    {
        foreach (array_keys(self::DEFINITIONS) as $documentType) {
            $this->ensure($documentType, $user);
        }
    }

    public function ensure(string $documentType, ?User $user = null): array
    {
        $definition = $this->definition($documentType);

        if (! $this->exists($documentType)) {
            match ($documentType) {
                'ppmp' => $this->ppmp->ensureOfficialTemplate($user),
                'app' => $this->writeAppTemplate(),
                'supplemental_app' => $this->writeSupplementalAppTemplate(),
                'pr' => $this->writePurchaseRequestTemplate($user),
                'rfq' => $this->writeRfqTemplate(),
                'abstract' => $this->writeAbstractTemplate(),
                'purchase_order' => $this->writePurchaseOrderTemplate(),
                'bac_resolution' => $this->writeBacResolutionTemplate(),
                'received_bac_resolution' => $this->writeBacResolutionTemplate('received_bac_resolution'),
                'inspection_acceptance' => $this->writeInspectionAcceptanceTemplate(),
                default => throw new InvalidArgumentException('Unsupported editable template type.'),
            };
        }

        return [
            'path' => $definition['path'],
            'filename' => $this->downloadFilename($documentType),
            'format' => $definition['format'],
        ];
    }

    private function definition(string $documentType): array
    {
        $canonical = $this->canonicalType($documentType);

        if (! $canonical) {
            throw new InvalidArgumentException('Unsupported editable template type.');
        }

        return self::DEFINITIONS[$canonical];
    }

    private function writeAppTemplate(): void
    {
        $rows = $this->officialHeaderRows(12, 'ANNUAL PROCUREMENT PLAN');
        $rows[] = ['[ ] INDICATIVE', '', '', '[ ] FINAL', '', '', '[ ] UPDATE (Version No. ___)', '', '', '', '', ''];
        $rows[] = [];
        $rows[] = ['PROCUREMENT PROJECT DETAILS', '', '', '', 'PROJECTED TIMELINE (MM/YYYY)', '', '', '', 'FUNDING DETAILS', '', '', ''];
        $rows[] = [
            'Project Title',
            "End-User or\nImplementing\nUnit",
            "General Description\nof the Project",
            "Mode of\nProcurement",
            "To be covered by\nan Early Procurement\nActivity (yes/No)",
            "Criteria for Bid\nEvaluation (Including\nSustainability and\nDomestic Bidder\nPreference)",
            "Start of\nProcurement\nActivity",
            "End of\nProcurement\nActivity",
            "Source of\nFunds",
            "Estimated Budget /\nAuthorized Budget\nAllocation (Php)",
            "Procurement\nStrategy or\nTools",
            "Remarks\n(Other relevant description\nprocurement project, if any)",
        ];
        $rows[] = $this->columnLabels(12);
        $rows[] = ['General Requirements'];
        $rows[] = array_fill(0, 12, '');
        $rows[] = array_fill(0, 12, '');
        $rows[] = ['Miscellaneous Items (for Direct Acquisition only) Sec. 32.2 of RA No. 12009'];
        $rows[] = array_fill(0, 12, '');
        $rows[] = array_fill(0, 12, '');
        $rows[] = ['Common Use Supplies and Equipment (CSE) to be purchased from PS-DBM (kindly indicate the summary total amount only)'];
        $rows[] = array_fill(0, 12, '');
        $rows[] = array_fill(0, 12, '');
        $rows[] = ['Note: Insert additional rows as necessary'];
        $rows[] = ['', '', '', '', '', '', '', 'Total Amount of Estimated Budget for EPA Projects:', '', '', '', ''];
        $rows[] = ['', '', '', '', '', '', '', 'Total Amount of CSE to be purchased from PS-DBM:', '', '', '', ''];
        $rows[] = ['', '', '', '', '', '', '', 'Total Amount of Estimated Budget:', '', '', '', ''];
        $rows = array_merge($rows, $this->threeSignatureRows());

        $this->saveOfficialXlsx('app', 'APP', $rows, [15, 13, 18, 13, 14, 21, 13, 13, 13, 16, 15, 16], $this->appOptions());
    }

    private function writeSupplementalAppTemplate(): void
    {
        $rows = $this->officialHeaderRows(7, 'SUPPLEMENTAL ANNUAL PROCUREMENT PLAN');
        $rows[] = ['Supplemental APP No.:', '', 'SAIP-____-001', '', '', '', ''];
        $rows[] = [];
        $rows[] = ['Fiscal Year', '', now()->year, 'Source PR No.', '', 'Not linked', ''];
        $rows[] = ['Requesting Office', '', '', 'Requesting Office Name', '', '', ''];
        $rows[] = ['Title', '', '', '', '', '', ''];
        $rows[] = ['Purpose', '', '', '', '', '', ''];
        $rows[] = ['', '', '', '', '', '', ''];
        $rows[] = ['Justification', '', '', '', '', '', ''];
        $rows[] = ['', '', '', '', '', '', ''];
        $rows[] = ['Item No.', 'Description', 'Quantity', 'Unit', 'Estimated Unit Cost', 'Estimated Total Cost', 'Remarks'];
        for ($index = 1; $index <= 8; $index++) {
            $rows[] = [$index, '', '', '', '', ['formula' => 'C' . (count($rows) + 1) . '*E' . (count($rows) + 1)], ''];
        }
        $rows[] = ['', '', '', '', 'TOTAL SUPPLEMENTAL APP BUDGET:', ['formula' => 'SUM(F13:F20)'], ''];
        $rows = array_merge($rows, $this->threeSignatureRows(7));

        $this->saveOfficialXlsx('supplemental_app', 'Supplemental APP', $rows, [10, 34, 11, 11, 17, 17, 18], $this->portraitOptions(7));
    }

    private function writePurchaseRequestTemplate(?User $user = null): void
    {
        $office = $user?->assignedOffice?->name ?? $user?->office ?? '';
        $rows = $this->officialHeaderRows(6, 'PURCHASE REQUEST');
        $rows[] = ['Entity Name / Office', $office, 'Fund Cluster', 'General Fund', 'Date', now()->format('M d, Y')];
        $rows[] = ['Office / Section', '', 'PR No.', 'To be assigned', 'Responsibility Center Code', ''];
        $rows[] = ['SAI No.', '', 'ALOBS No.', '', 'Date', ''];
        $rows[] = ['Stock / Property No.', 'Unit', 'Item Description', 'Quantity', 'Unit Cost', 'Total Cost'];
        for ($index = 0; $index < 18; $index++) {
            $row = count($rows) + 1;
            $rows[] = ['', '', '', '', '', ['formula' => 'D' . $row . '*E' . $row]];
        }
        $rows[] = ['', '', '', '', 'TOTAL ESTIMATED COST', ['formula' => 'SUM(F11:F28)']];
        $rows[] = ['Purpose:', '', '', '', '', ''];
        $rows[] = ['Certification (Sec. 7.8 of IRR of RA 12009)', 'Certified that the following items are in accordance with approved Procurement Plan.', '', 'Requested by:', '', 'Approved by:'];
        $rows[] = ['Signature:', '', '', '', '', ''];
        $rows[] = ['Printed Name:', 'MEAGAN C. MATUTES', 'ENGR. AURELIO H. SUNGA, JR.', $user?->name ?? '', 'JESSICA MARIE G. ESCANO', ''];
        $rows[] = ['Designation:', 'BAC Secretariat', 'Municipal Budget Officer', $user?->role ?? 'Head of Office / End User', 'Municipal Mayor', ''];
        $rows[] = ['Date:', '', '', '', '', ''];

        $this->saveOfficialXlsx('pr', 'Purchase Request', $rows, [18, 18, 45, 12, 16, 16], $this->portraitOptions(6));
    }

    private function writeRfqTemplate(): void
    {
        $rows = $this->officialHeaderRows(6, 'REQUEST FOR QUOTATION');
        $rows[] = ['', '', '', 'Date:', now()->toDateString(), ''];
        $rows[] = ['', '', '', 'Quotation No.:', '', ''];
        $rows[] = ['Supplier Name', '', '', '', '', ''];
        $rows[] = ['Supplier Address', '', '', '', '', ''];
        $rows[] = ['Please quote your lowest price on the item listed below, subject to the General Conditions stated herein under.'];
        $rows[] = ['', '', '', 'ROLAND P. FELICILDA', '', ''];
        $rows[] = ['', '', '', 'Procurement Officer', '', ''];
        $rows[] = ['TERM'];
        $rows[] = ['1.', 'Delivery period within ______ calendar days.', '', '', '', ''];
        $rows[] = ['2.', 'Warranty shall be a period of six (6) months for supplies and materials. One (1) year for equipment, from date of acceptance.', '', '', '', ''];
        $rows[] = ['3.', 'Price validity shall be for a period of sixty (60) calendar days.', '', '', '', ''];
        $rows[] = ['4.', 'PhilGEPS Registration Certificate/Number shall be provided in the quotation.', '', '', '', ''];
        $rows[] = ['5.', "Mayor's Business Permit shall be attached upon submission of the quotation.", '', '', '', ''];
        $rows[] = ['ABC:', '', '', '', '', ''];
        $rows[] = ['Purpose:', '', '', '', '', ''];
        $rows[] = ['ITEM NO.', 'DESCRIPTION', 'QTY', 'UNIT OF ISSUE', 'UNIT PRICE', 'TOTAL'];
        for ($index = 1; $index <= 24; $index++) {
            $row = count($rows) + 1;
            $rows[] = [$index, '', '', '', '', ['formula' => 'C' . $row . '*E' . $row]];
        }
        $rows[] = ['Printed Name & Signature', '', '', 'PhilGEPS Type of Registration:', '', ''];
        $rows[] = ['Tel No. / Cellphone No.', '', '', 'PhilGEPS Registration Number', '', ''];
        $rows[] = ['TIN No.', '', '', 'Date', '', ''];

        $this->saveOfficialXlsx('rfq', 'RFQ', $rows, [12, 48, 10, 16, 16, 16], $this->portraitOptions(6));
    }

    private function writeAbstractTemplate(): void
    {
        $rows = $this->officialHeaderRows(10, 'ABSTRACT OF QUOTATIONS/CANVASS');
        $rows[] = ['', '', '', '', '', '', '', 'No.', '', ''];
        $rows[] = ['', '', '', '', '', '', '', 'Date', now()->toDateString(), ''];
        $rows[] = ['Name of the Project:', '', '', '', '', '', '', '', '', ''];
        $rows[] = ['Implementing Office:', '', '', '', '', '', '', '', '', ''];
        $rows[] = ['Approved Budget for the Contract:', '', '', '', '', '', '', '', '', ''];
        $rows[] = [];
        $rows[] = ['ITEM NO.', 'NAME OF GOODS/SERVICES', 'QUANTITY', 'UNIT OF MEASURE', "1\nAMOUNT / UNIT", "2\nAMOUNT / UNIT", "3\nAMOUNT / UNIT", "4\nAMOUNT / UNIT", "5\nAMOUNT / UNIT", 'TOTAL LOWEST PRICE'];
        for ($index = 1; $index <= 22; $index++) {
            $rows[] = [$index, '', '', '', '', '', '', '', '', ''];
        }
        $rows[] = ['', '', '', 'TOTAL', '', '', '', '', '', ''];
        $rows[] = ['Note:'];
        $rows[] = ['1', 'Items No. ____ awarded to ____ being quoted the lowest price.', '', '', '', '', '', '', '', ''];
        $rows[] = ['2', 'Items No. ____ awarded to ____ being quoted the lowest price.', '', '', '', '', '', '', '', ''];
        $rows[] = ['3', 'Items No. ____ awarded to ____ being quoted the lowest price.', '', '', '', '', '', '', '', ''];
        $rows[] = ['Certification: We hereby certify that the above procurement is in accordance with the approved revised procurement plan for 202__.'];
        $rows[] = ['APPROVED BY COMMITTEE ON BIDS AND AWARDS:'];
        $rows[] = ['', '', 'BAC Chairperson', '', '', '', 'BAC Vice Chairperson', '', '', ''];
        $rows[] = ['BAC Member', '', 'BAC Member', '', 'BAC Member', '', 'BAC Member-Alternate', '', '', ''];

        $this->saveOfficialXlsx('abstract', 'Abstract', $rows, [8, 34, 10, 15, 13, 13, 13, 13, 13, 18], $this->portraitOptions(10));
    }

    private function writePurchaseOrderTemplate(): void
    {
        $rows = [
            ['Annex 29', '', '', '', '', ''],
            ['', '', 'PURCHASE ORDER', '', '', ''],
            ['', '', 'TOMAS OPPUS', '', '', ''],
            ['', '', 'LGU', '', '', ''],
            [],
            ['Supplier:', '', '', 'P.O. No.:', '', ''],
            ['Address:', '', '', 'Date:', '', ''],
            ['', '', '', 'Mode of Procurement:', '', ''],
            ['', '', '', 'PR No./s:', '', ''],
            ['Gentlemen:'],
            ['Please furnish this office the following articles subject to the terms and conditions contained herein:'],
            ['Place of Delivery:', '', '', 'Delivery Term:', '', ''],
            ['Date of Delivery:', '', '', 'Payment Term:', '', ''],
            ['ITEM NO.', 'QTY', 'UNIT', 'DESCRIPTION', 'UNIT COST', 'TOTAL'],
        ];
        for ($index = 1; $index <= 25; $index++) {
            $row = count($rows) + 1;
            $rows[] = [$index, '', '', '', '', ['formula' => 'B' . $row . '*E' . $row]];
        }
        $rows[] = ['(Total Amount in Words)', '', '', '', 'TOTAL', ['formula' => 'SUM(F15:F39)']];
        $rows[] = ['In case of failure to make the full delivery within the time specified above, a penalty of one-tenth (1/10) of one percent for every day of delay shall be imposed.'];
        $rows[] = ['Conforme:', '', '', 'Very truly yours,', '', ''];
        $rows[] = ['Supplier Representative', '', '', 'Authorized Official', '', ''];
        $rows[] = ['Funds Available:', '', '', 'ALOBS No.:', '', ''];
        $rows[] = ['', '', '', 'Amount:', '', ''];
        $rows[] = ['Municipal Accountant', '', '', '', '', ''];

        $this->saveOfficialXlsx('purchase_order', 'Purchase Order', $rows, [10, 10, 12, 44, 15, 15], $this->portraitOptions(6));
    }

    private function writeInspectionAcceptanceTemplate(): void
    {
        $rows = $this->officialHeaderRows(6, 'INSPECTION / ACCEPTANCE RECORD');
        $rows[] = ['PO Number', '', 'Supplier', '', 'Inspection Date', now()->toDateString()];
        $rows[] = ['Delivery Receipt No.', '', 'Invoice No.', '', 'Acceptance Date', ''];
        $rows[] = ['Quantity Condition', 'Complete', 'Quality Condition', 'Conforming', '', ''];
        $rows[] = ['Inspection Findings', '', '', '', '', ''];
        $rows[] = ['Remarks', '', '', '', '', ''];
        $rows[] = ['Item No.', 'Description', 'Qty', 'Unit', 'Unit Cost', 'Total'];
        for ($index = 1; $index <= 12; $index++) {
            $rows[] = [$index, '', '', '', '', ''];
        }
        $rows[] = ['Inspected By', '', '', 'Accepted By', '', ''];

        $this->saveOfficialXlsx('inspection_acceptance', 'Inspection Acceptance', $rows, [12, 42, 10, 12, 16, 16], $this->portraitOptions(6));
    }

    private function writeBacResolutionTemplate(string $documentType = 'bac_resolution'): void
    {
        $this->docx->save(Storage::disk('local')->path($this->path($documentType)), [
            ['text' => 'Republic of the Philippines', 'align' => 'center', 'size' => 22],
            ['text' => 'Province of Southern Leyte', 'align' => 'center', 'size' => 22],
            ['text' => 'MUNICIPALITY OF TOMAS OPPUS', 'align' => 'center', 'bold' => true, 'size' => 22],
            ['text' => 'BIDS AND AWARDS COMMITTEE', 'align' => 'center', 'bold' => true, 'size' => 22],
            ['text' => 'BAC RESOLUTION NO. ________', 'align' => 'center', 'bold' => true, 'underline' => true, 'size' => 22],
            ['text' => 'A RESOLUTION RECOMMENDING THE APPROVAL OF __________________________ FOR THE PROCUREMENT OF THE PROJECT "__________________________"', 'align' => 'center', 'bold' => true, 'size' => 22],
            ['text' => 'WHEREAS, the Local Government Unit of Tomas Oppus, Southern Leyte intended to procure the project titled "__________________________" with an Approved Budget for the Contract (ABC) of PHP __________ under PhilGEPS Reference No. __________ and Solicitation No. __________;', 'align' => 'both', 'size' => 22],
            ['text' => 'WHEREAS, in response to the procurement activity, __________________________ submitted the required documents for the aforementioned project;', 'align' => 'both', 'size' => 22],
            ['text' => 'WHEREAS, on ____________________, the Bids and Awards Committee (BAC) reviewed the procurement proceedings and supporting documents for the said project;', 'align' => 'both', 'size' => 22],
            ['text' => 'NOW, THEREFORE, We, the Members of the Bids and Awards Committee, hereby RESOLVE as it is hereby RESOLVED:', 'align' => 'both', 'size' => 22],
            ['text' => '1. To RECOMMEND the approval of __________________________ in favor of __________________________ for the project "__________________________" in the amount of PHP __________;', 'align' => 'both', 'size' => 22],
            ['text' => '2. To forward this Resolution to the Local Chief Executive for final approval and appropriate action.', 'align' => 'both', 'size' => 22],
            ['text' => 'RESOLVED this ____ day of ________________ 20____, at the BAC Office, LGU Tomas Oppus, Southern Leyte.', 'align' => 'both', 'size' => 22],
            ['type' => 'table', 'rows' => [
                ['BAC Vice Chairperson', 'BAC Member', 'BAC Member', 'BAC Member'],
                ['BAC Chairperson', 'Approved By', 'Date Approved', ''],
            ]],
        ]);
    }

    private function saveOfficialXlsx(string $documentType, string $sheetName, array $rows, array $columnWidths, array $options): void
    {
        $this->xlsx->save(
            Storage::disk('local')->path($this->path($documentType)),
            $sheetName,
            $this->normalizeRows($rows, count($columnWidths)),
            $columnWidths,
            $options,
        );
    }

    private function officialHeaderRows(int $columnCount, string $title): array
    {
        return [
            array_fill(0, $columnCount, ''),
            array_merge([''], ['Republic of the Philippines'], array_fill(0, max(0, $columnCount - 2), '')),
            array_merge([''], ['Province of Southern Leyte'], array_fill(0, max(0, $columnCount - 2), '')),
            array_merge([''], ['MUNICIPALITY OF TOMAS OPPUS'], array_fill(0, max(0, $columnCount - 2), '')),
            array_fill(0, $columnCount, ''),
            array_merge([''], [$title], array_fill(0, max(0, $columnCount - 2), '')),
        ];
    }

    private function columnLabels(int $count): array
    {
        return array_map(fn (int $number) => 'Column ' . $number, range(1, $count));
    }

    private function threeSignatureRows(int $columnCount = 12): array
    {
        $blank = array_fill(0, $columnCount, '');

        return [
            $blank,
            ['Prepared By:', '', '', 'Recommended by:', '', '', '', '', 'Approved By:', '', '', ''],
            ['JOBELLE A. SOLER', '', '', 'By the Authority of the Bids and Awards Com.', '', '', '', '', 'AURELIO H. SUNGA, JR.', '', '', ''],
            ['Signature over Printed Name', '', '', 'Signature over Printed Name', '', '', '', '', 'Signature over Printed Name', '', '', ''],
            ['Administrative Aide 1', '', '', '', '', '', '', '', 'Municipal Budget Officer', '', '', ''],
            ['Position/Designation', '', '', 'Position/Designation', '', '', '', '', 'Position/Designation', '', '', ''],
            ['Bids and Awards Committee Secretariat', '', '', 'Bids and Awards Committee Secretariat', '', '', '', '', 'Bids and Awards Committee Secretariat', '', '', ''],
            ['Date: __________________', '', '', 'Date: __________________', '', '', '', '', 'Date: __________________', '', '', ''],
        ];
    }

    private function appOptions(): array
    {
        $options = $this->landscapeOptions(12);
        $options['merges'] = array_merge($options['merges'], [
            'B2:K2', 'B3:K3', 'B4:K4', 'B6:K6',
            'A9:D9', 'E9:H9', 'I9:J9',
            'A12:L12', 'A15:L15', 'A18:L18',
        ]);

        return $options;
    }

    private function portraitOptions(int $columnCount): array
    {
        return $this->baseOptions($columnCount, 'portrait');
    }

    private function landscapeOptions(int $columnCount): array
    {
        return $this->baseOptions($columnCount, 'landscape');
    }

    private function baseOptions(int $columnCount, string $orientation): array
    {
        $styles = [];
        $rowStyles = [
            2 => 15,
            3 => 15,
            4 => 16,
            6 => 16,
        ];

        for ($row = 1; $row <= 80; $row++) {
            for ($column = 1; $column <= $columnCount; $column++) {
                if ($row <= 7) {
                    continue;
                }

                $styles[$row][$column] = in_array($row, [8, 9, 10, 11], true) ? 5 : 6;
            }
        }

        return [
            'styles' => $styles,
            'rowStyles' => $rowStyles,
            'rangeStyles' => [
                ['range' => 'A8:' . $this->columnName($columnCount) . '10', 'style' => 3],
            ],
            'merges' => [
                'B2:' . $this->columnName(max(2, $columnCount - 1)) . '2',
                'B3:' . $this->columnName(max(2, $columnCount - 1)) . '3',
                'B4:' . $this->columnName(max(2, $columnCount - 1)) . '4',
                'B6:' . $this->columnName(max(2, $columnCount - 1)) . '6',
            ],
            'rowHeights' => [
                8 => 18,
                9 => 48,
                10 => 18,
            ],
            'pageSetup' => [
                'orientation' => $orientation,
                'paperSize' => 9,
                'fitToWidth' => 1,
                'fitToHeight' => 0,
            ],
            'pageMargins' => [
                'left' => 0.25,
                'right' => 0.25,
                'top' => 0.3,
                'bottom' => 0.35,
                'header' => 0.15,
                'footer' => 0.15,
            ],
            'images' => $this->templateImages($columnCount),
        ];
    }

    private function templateImages(int $columnCount): array
    {
        $images = [];
        $logo = public_path('images/logos/lgu-logo.png');
        $bagong = public_path('images/logos/bagongpilipinas.jpg');

        if (is_file($logo)) {
            $images[] = [
                'path' => $logo,
                'name' => 'LGU Logo',
                'from' => ['col' => 0, 'row' => 0],
                'to' => ['col' => 1, 'row' => 4],
            ];
        }

        if (is_file($bagong)) {
            $images[] = [
                'path' => $bagong,
                'name' => 'Bagong Pilipinas Logo',
                'from' => ['col' => max(1, $columnCount - 2), 'row' => 0],
                'to' => ['col' => $columnCount - 1, 'row' => 4],
            ];
        }

        return $images;
    }

    private function normalizeRows(array $rows, int $columnCount): array
    {
        return array_map(function (array $row) use ($columnCount) {
            $normalized = array_values($row);

            while (count($normalized) < $columnCount) {
                $normalized[] = '';
            }

            return array_slice($normalized, 0, $columnCount);
        }, $rows);
    }

    private function columnName(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;
    }
}
