<?php

namespace App\Services\Templates;

use RuntimeException;
use ZipArchive;

class SimpleXlsxBuilder
{
    public function save(string $absolutePath, string $sheetName, array $rows, array $columnWidths = [], array $options = []): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP Zip extension is required to generate XLSX templates.');
        }

        $directory = dirname($absolutePath);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create template output directory.');
        }

        $zip = new ZipArchive();
        $images = $this->normalizeImages($options['images'] ?? []);

        if ($zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create XLSX template file.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml($images));
        $zip->addFromString('_rels/.rels', $this->relsXml());
        $zip->addFromString('docProps/app.xml', $this->appXml($sheetName));
        $zip->addFromString('docProps/core.xml', $this->coreXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());

        if ($images) {
            $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', $this->worksheetRelsXml());
            $zip->addFromString('xl/drawings/drawing1.xml', $this->drawingXml($images));
            $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels', $this->drawingRelsXml($images));

            foreach ($images as $index => $image) {
                $zip->addFile($image['path'], 'xl/media/image' . ($index + 1) . '.' . $image['extension']);
            }
        }

        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($rows, $columnWidths, $options, $images !== []));
        $zip->close();
    }

    private function sheetXml(array $rows, array $columnWidths, array $options, bool $hasDrawing): string
    {
        $cols = '';

        foreach ($columnWidths as $index => $width) {
            $column = $index + 1;
            $cols .= '<col min="' . $column . '" max="' . $column . '" width="' . (float) $width . '" customWidth="1"/>';
        }

        $xmlRows = '';
        $rowHeights = $options['rowHeights'] ?? [];

        foreach (array_values($rows) as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $cells = '';
            $height = isset($rowHeights[$rowNumber])
                ? ' ht="' . (float) $rowHeights[$rowNumber] . '" customHeight="1"'
                : '';

            foreach (array_values($row) as $columnIndex => $value) {
                $cellRef = $this->columnName($columnIndex + 1) . $rowNumber;
                $styleId = $this->styleForCell($rowNumber, $columnIndex + 1, $options);
                $style = $styleId !== null ? ' s="' . (int) $styleId . '"' : '';

                if (is_array($value) && isset($value['formula'])) {
                    $cells .= '<c r="' . $cellRef . '"' . $style . '><f>' . $this->escape((string) $value['formula']) . '</f></c>';
                    continue;
                }

                if ($value === '' || $value === null) {
                    $cells .= '<c r="' . $cellRef . '"' . $style . '/>';
                    continue;
                }

                if (is_int($value) || is_float($value)) {
                    $cells .= '<c r="' . $cellRef . '"' . $style . '><v>' . $value . '</v></c>';
                    continue;
                }

                $cells .= '<c r="' . $cellRef . '"' . $style . ' t="inlineStr"><is><t xml:space="preserve">'
                    . $this->escape((string) $value)
                    . '</t></is></c>';
            }

            $xmlRows .= '<row r="' . $rowNumber . '"' . $height . '>' . $cells . '</row>';
        }

        $mergeXml = '';
        $merges = $options['merges'] ?? [];

        if ($merges) {
            $mergeXml = '<mergeCells count="' . count($merges) . '">';
            foreach ($merges as $merge) {
                $mergeXml .= '<mergeCell ref="' . $this->escape($merge) . '"/>';
            }
            $mergeXml .= '</mergeCells>';
        }

        $pageSetup = $options['pageSetup'] ?? [];
        $orientation = $pageSetup['orientation'] ?? 'portrait';
        $fitToWidth = (int) ($pageSetup['fitToWidth'] ?? 1);
        $fitToHeight = (int) ($pageSetup['fitToHeight'] ?? 0);
        $paperSize = (int) ($pageSetup['paperSize'] ?? 9);
        $margins = $options['pageMargins'] ?? [
            'left' => 0.25,
            'right' => 0.25,
            'top' => 0.35,
            'bottom' => 0.35,
            'header' => 0.15,
            'footer' => 0.15,
        ];

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ($hasDrawing ? ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"' : '')
            . '>'
            . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . '<cols>' . $cols . '</cols>'
            . '<sheetData>' . $xmlRows . '</sheetData>'
            . $mergeXml
            . '<printOptions horizontalCentered="1"/>'
            . '<pageMargins left="' . (float) $margins['left'] . '" right="' . (float) $margins['right'] . '" top="' . (float) $margins['top'] . '" bottom="' . (float) $margins['bottom'] . '" header="' . (float) $margins['header'] . '" footer="' . (float) $margins['footer'] . '"/>'
            . '<pageSetup paperSize="' . $paperSize . '" orientation="' . $this->escape($orientation) . '" fitToWidth="' . $fitToWidth . '" fitToHeight="' . $fitToHeight . '"/>'
            . ($hasDrawing ? '<drawing r:id="rId1"/>' : '')
            . '</worksheet>';
    }

    private function styleForCell(int $row, int $column, array $options): ?int
    {
        $styles = $options['styles'] ?? [];

        if (isset($styles[$row][$column])) {
            return (int) $styles[$row][$column];
        }

        foreach ($options['rangeStyles'] ?? [] as $rangeStyle) {
            if ($this->cellInRange($row, $column, $rangeStyle['range'] ?? '')) {
                return (int) $rangeStyle['style'];
            }
        }

        if (isset(($options['rowStyles'] ?? [])[$row])) {
            return (int) $options['rowStyles'][$row];
        }

        if (isset(($options['columnStyles'] ?? [])[$column])) {
            return (int) $options['columnStyles'][$column];
        }

        return $row <= 8 ? 1 : null;
    }

    private function cellInRange(int $row, int $column, string $range): bool
    {
        if (! preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', strtoupper($range), $matches)) {
            return false;
        }

        $startColumn = $this->columnIndex($matches[1]);
        $startRow = (int) $matches[2];
        $endColumn = $this->columnIndex($matches[3]);
        $endRow = (int) $matches[4];

        return $row >= min($startRow, $endRow)
            && $row <= max($startRow, $endRow)
            && $column >= min($startColumn, $endColumn)
            && $column <= max($startColumn, $endColumn);
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

    private function columnIndex(string $name): int
    {
        $index = 0;

        foreach (str_split(strtoupper($name)) as $character) {
            $index = ($index * 26) + (ord($character) - 64);
        }

        return $index;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function contentTypesXml(array $images = []): string
    {
        $imageDefaults = '';
        $extensions = collect($images)->pluck('extension')->unique()->values();

        foreach ($extensions as $extension) {
            $contentType = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                default => null,
            };

            if ($contentType) {
                $imageDefaults .= '<Default Extension="' . $extension . '" ContentType="' . $contentType . '"/>';
            }
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . $imageDefaults
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . ($images ? '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' : '')
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    private function relsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $this->escape($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="7">'
            . '<font><sz val="11"/><name val="Arial"/></font>'
            . '<font><b/><sz val="11"/><name val="Arial"/></font>'
            . '<font><sz val="9"/><name val="Arial"/></font>'
            . '<font><b/><sz val="11"/><name val="Arial"/></font>'
            . '<font><b/><sz val="9"/><name val="Arial"/></font>'
            . '<font><sz val="8"/><name val="Arial"/></font>'
            . '<font><b/><sz val="8"/><name val="Arial"/></font>'
            . '</fonts>'
            . '<fills count="5">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFDDEBF7"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFFFFCC"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF7F7F7"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="4">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color auto="1"/></left><right style="thin"><color auto="1"/></right><top style="thin"><color auto="1"/></top><bottom style="thin"><color auto="1"/></bottom><diagonal/></border>'
            . '<border><left/><right/><top/><bottom style="thin"><color auto="1"/></bottom><diagonal/></border>'
            . '<border><left/><right/><top/><bottom style="medium"><color auto="1"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="17">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="6" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="6" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="5" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="5" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="5" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment horizontal="left" vertical="top" wrapText="1"/></xf>'
            . '<xf numFmtId="4" fontId="5" fillId="0" borderId="1" xfId="0" applyBorder="1" applyNumberFormat="1"><alignment horizontal="right" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="6" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="left" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="6" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="6" fillId="0" borderId="2" xfId="0" applyFont="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0"><alignment horizontal="left" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '</cellXfs>'
            . '</styleSheet>';
    }

    private function normalizeImages(array $images): array
    {
        $normalized = [];

        foreach ($images as $image) {
            $path = $image['path'] ?? null;

            if (! is_string($path) || ! is_file($path)) {
                continue;
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if (! in_array($extension, ['png', 'jpg', 'jpeg'], true)) {
                continue;
            }

            $normalized[] = [
                'path' => $path,
                'extension' => $extension,
                'from' => $image['from'] ?? ['col' => 0, 'row' => 0],
                'to' => $image['to'] ?? ['col' => 1, 'row' => 1],
                'name' => $image['name'] ?? 'Picture',
            ];
        }

        return $normalized;
    }

    private function worksheetRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>'
            . '</Relationships>';
    }

    private function drawingRelsXml(array $images): string
    {
        $relationships = '';

        foreach ($images as $index => $image) {
            $id = $index + 1;
            $relationships .= '<Relationship Id="rId' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image' . $id . '.' . $image['extension'] . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $relationships
            . '</Relationships>';
    }

    private function drawingXml(array $images): string
    {
        $anchors = '';

        foreach ($images as $index => $image) {
            $id = $index + 1;
            $from = $image['from'];
            $to = $image['to'];

            $anchors .= '<xdr:twoCellAnchor editAs="oneCell">'
                . '<xdr:from><xdr:col>' . (int) $from['col'] . '</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>' . (int) $from['row'] . '</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>'
                . '<xdr:to><xdr:col>' . (int) $to['col'] . '</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>' . (int) $to['row'] . '</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to>'
                . '<xdr:pic>'
                . '<xdr:nvPicPr><xdr:cNvPr id="' . $id . '" name="' . $this->escape($image['name']) . '"/><xdr:cNvPicPr/></xdr:nvPicPr>'
                . '<xdr:blipFill><a:blip r:embed="rId' . $id . '"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
                . '<xdr:spPr><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>'
                . '</xdr:pic><xdr:clientData/></xdr:twoCellAnchor>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . $anchors
            . '</xdr:wsDr>';
    }

    private function appXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>PaperTrail</Application><TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>'
            . $this->escape($sheetName)
            . '</vt:lpstr></vt:vector></TitlesOfParts></Properties>';
    }

    private function coreXml(): string
    {
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>PaperTrail</dc:creator><cp:lastModifiedBy>PaperTrail</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $timestamp . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $timestamp . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }
}
