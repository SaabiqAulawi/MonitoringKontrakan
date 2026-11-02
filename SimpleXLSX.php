<?php
// SimpleXLSX.php - Robust, zero-dependency XLSX reader for PHP
class SimpleXLSX {
    private array $sharedStrings = [];
    private array $sheets = [];

    public static function parse(string $filename): SimpleXLSX|false {
        $xlsx = new self();
        if ($xlsx->open($filename)) {
            return $xlsx;
        }
        return false;
    }

    public function open(string $filename): bool {
        $zip = new ZipArchive();
        if ($zip->open($filename) !== true) {
            return false;
        }

        // 1. Baca sharedStrings.xml jika tersedia
        $sharedXML = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXML !== false) {
            $xml = simplexml_load_string($sharedXML);
            if ($xml) {
                foreach ($xml->si as $si) {
                    $text = '';
                    if (isset($si->t)) {
                        $text = (string)$si->t;
                    } elseif (isset($si->r)) {
                        foreach ($si->r as $r) {
                            $text .= (string)$r->t;
                        }
                    }
                    $this->sharedStrings[] = $text;
                }
            }
        }

        // 2. Baca sheet1.xml
        $sheetXML = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXML === false) {
            $zip->close();
            return false;
        }

        $xml = simplexml_load_string($sheetXML);
        if (!$xml) {
            $zip->close();
            return false;
        }

        $rows = [];
        if (isset($xml->sheetData->row)) {
            foreach ($xml->sheetData->row as $rowNode) {
                $rowCells = [];
                $maxCol = 0;

                foreach ($rowNode->c as $cell) {
                    $ref = (string)$cell['r']; // misal 'A1', 'B2', 'AA10'
                    preg_match('/^([A-Z]+)(\d+)$/', $ref, $matches);
                    $colIndex = isset($matches[1]) ? self::colIndex($matches[1]) : count($rowCells);
                    
                    $val = '';
                    $type = (string)$cell['t'];

                    if ($type === 'inlineStr' && isset($cell->is->t)) {
                        $val = (string)$cell->is->t;
                    } elseif ($type === 's' && isset($cell->v)) {
                        $idx = (int)$cell->v;
                        $val = $this->sharedStrings[$idx] ?? '';
                    } elseif (isset($cell->v)) {
                        $val = (string)$cell->v;
                    }

                    $rowCells[$colIndex] = trim($val);
                    if ($colIndex > $maxCol) {
                        $maxCol = $colIndex;
                    }
                }

                // Normalisasi array kolom dari 0 sampai maxCol
                $normalizedRow = [];
                for ($c = 0; $c <= $maxCol; $c++) {
                    $normalizedRow[$c] = $rowCells[$c] ?? '';
                }

                // Simpan jika ada isi (tidak kosong semua)
                if (!empty(array_filter($normalizedRow, fn($v) => $v !== ''))) {
                    $rows[] = $normalizedRow;
                }
            }
        }

        $this->sheets[0] = $rows;
        $zip->close();
        return true;
    }

    public function rows(int $sheetIndex = 0): array {
        return $this->sheets[$sheetIndex] ?? [];
    }

    private static function colIndex(string $col): int {
        $len = strlen($col);
        $index = 0;
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($col[$i]) - ord('A') + 1);
        }
        return $index - 1; // 0-based
    }
}
