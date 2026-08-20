<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class SimpleXlsxWriter
{
    private array $sheets = [];

    public function addSheet(string $name, array $rows, array $widths = [], array $dataBarColumns = []): self
    {
        $this->sheets[] = compact('name', 'rows', 'widths', 'dataBarColumns');
        return $this;
    }

    public function save(string $path): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Az Excel-fájl nem hozható létre.');
        }
        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());
        foreach ($this->sheets as $index => $sheet) {
            $zip->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', $this->worksheet($sheet));
        }
        $zip->close();
    }

    private function worksheet(array $sheet): string
    {
        $rowsXml = '';
        foreach ($sheet['rows'] as $rowIndex => $row) {
            $number = $rowIndex + 1;
            $cells = '';
            foreach (array_values($row) as $columnIndex => $value) {
                $ref = $this->column($columnIndex + 1).$number;
                $style = $rowIndex === 0 ? 1 : ($rowIndex === 1 ? 2 : 0);
                if (is_int($value) || is_float($value)) {
                    $cells .= '<c r="'.$ref.'" s="'.$style.'"><v>'.$value.'</v></c>';
                } else {
                    $cells .= '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->xml((string) $value).'</t></is></c>';
                }
            }
            $rowAttributes = $rowIndex === 0 ? ' ht="26" customHeight="1"' : '';
            $rowsXml .= '<row r="'.$number.'"'.$rowAttributes.'>'.$cells.'</row>';
        }
        $columns = '';
        foreach ($sheet['widths'] as $index => $width) {
            $column = $index + 1;
            $columns .= '<col min="'.$column.'" max="'.$column.'" width="'.$width.'" customWidth="1"/>';
        }
        $maxRow = max(1, count($sheet['rows']));
        $conditions = '';
        foreach ($sheet['dataBarColumns'] as $column) {
            $letter = is_int($column) ? $this->column($column) : $column;
            $conditions .= '<conditionalFormatting sqref="'.$letter.'3:'.$letter.$maxRow.'"><cfRule type="dataBar" priority="1"><dataBar><cfvo type="min" val="0"/><cfvo type="max" val="0"/><color rgb="FFDE9C28"/></dataBar></cfRule></conditionalFormatting>';
        }
        $columnCounts = array_map(static fn (array $row): int => count($row), $sheet['rows']);
        $titleColumnCount = $columnCounts ? max(1, max($columnCounts)) : 1;
        $titleMerge = $titleColumnCount > 1
            ? '<mergeCells count="1"><mergeCell ref="A1:'.$this->column($titleColumnCount).'1"/></mergeCells>'
            : '';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="2" topLeftCell="A3" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .($columns ? '<cols>'.$columns.'</cols>' : '')
            .'<sheetData>'.$rowsXml.'</sheetData><autoFilter ref="A2:'.$this->column(max(1, count($sheet['rows'][1] ?? []))).$maxRow.'"/>'
            .$titleMerge.$conditions.'</worksheet>';
    }

    private function workbook(): string
    {
        $sheets = '';
        foreach ($this->sheets as $index => $sheet) {
            $sheets .= '<sheet name="'.$this->xml(substr($sheet['name'], 0, 31)).'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheets.'</sheets></workbook>';
    }

    private function workbookRels(): string
    {
        $rels = '';
        foreach ($this->sheets as $index => $_) {
            $rels .= '<Relationship Id="rId'.($index + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($index + 1).'.xml"/>';
        }
        $rels .= '<Relationship Id="rId'.(count($this->sheets) + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>';
    }

    private function contentTypes(): string
    {
        $overrides = '';
        foreach ($this->sheets as $index => $_) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.($index + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$overrides.'</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="16"/><color rgb="FF17264B"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF17264B"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private function column(int $number): string
    {
        $result = '';
        while ($number > 0) { $number--; $result = chr(65 + ($number % 26)).$result; $number = intdiv($number, 26); }
        return $result;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
