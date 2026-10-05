<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Ghi file .xlsx tối giản KHÔNG cần thư viện ngoài:
 * ZipArchive + OOXML cơ bản (1 sheet, ô chuỗi inlineStr, ô số <v>).
 * Đủ dùng cho báo cáo Excel mà Trợ Lý Admin xuất cho admin tải về.
 */
final class XlsxWriter
{
    /** @var array<int, array<int, mixed>> */
    private array $rows = [];

    public function __construct(private string $sheetName = 'Sheet1')
    {
        // Tên sheet bắt buộc ≤31 ký tự, cấm ký tự đặc biệt của Excel.
        $name = trim(preg_replace('~[/\\\\?*\\[\\]:]~', ' ', $this->sheetName) ?? '');
        $this->sheetName = $name !== '' ? mb_substr($name, 0, 31) : 'Sheet1';
    }

    /** Thêm một dòng (chuỗi → inlineStr; int/float → số). */
    public function addRow(array $cells): void
    {
        $this->rows[] = array_values($cells);
    }

    /**
     * Ghi file .xlsx.
     *
     * @throws \RuntimeException khi không tạo/ghi được file.
     */
    public function save(string $path): void
    {
        if ($this->rows === []) {
            throw new \RuntimeException('Không có dữ liệu để xuất Excel.');
        }

        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Không tạo được thư mục báo cáo: ' . $dir);
        }

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Không mở được file xlsx để ghi: ' . $path);
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypesXml());
        $zip->addFromString('_rels/.rels', self::relsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelsXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml());

        if (!$zip->close()) {
            throw new \RuntimeException('Ghi file xlsx thất bại: ' . $path);
        }
    }

    // -----------------------------------------------------------------
    // XML
    // -----------------------------------------------------------------

    private static function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';
    }

    private static function relsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::esc($this->sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function sheetXml(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($this->rows as $r => $cells) {
            $rowNum = $r + 1;
            $xml .= '<row r="' . $rowNum . '">';
            foreach ($cells as $c => $value) {
                $ref = self::colName($c) . $rowNum;
                if ((is_int($value) || is_float($value)) && is_numeric((string) $value)) {
                    $xml .= '<c r="' . $ref . '"><v>' . (string) $value . '</v></c>';
                } elseif ($value === null || $value === '') {
                    continue;
                } else {
                    $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">'
                        . self::esc((string) $value) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        return $xml . '</sheetData></worksheet>';
    }

    /** Tên cột Excel: 0 → A, 25 → Z, 26 → AA… */
    private static function colName(int $index): string
    {
        $name = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $name = chr(65 + $mod) . $name;
            $index = intdiv($index - $mod - 1, 26);
        }
        return $name;
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
