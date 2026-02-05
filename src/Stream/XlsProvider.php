<?php

namespace FQL\Stream;

use FQL\Enum;
use FQL\Exception;
use FQL\Interface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

abstract class XlsProvider extends AbstractStream
{
    protected function __construct(private readonly string $xlsFilePath)
    {
    }

    public function getStream(?string $query): \ArrayIterator
    {
        return new \ArrayIterator(iterator_to_array($this->getStreamGenerator($query)));
    }

    /**
     * @throws Exception\UnableOpenFileException
     * @throws Exception\InvalidArgumentException
     */
    public function getStreamGenerator(?string $query): \Generator
    {
        $query = $query ?? Interface\Query::FROM_ALL;

        // Parse dot-notation query (Sheet.Cell) without loading the workbook
        [$sheetName, $startCell] = $this->parseQuery($query);

        [$startColumn, $startRow] = Coordinate::coordinateFromString($startCell);
        $startColumnIndex = Coordinate::columnIndexFromString($startColumn);
        $headerRow = (int) $startRow;

        try {
            $reader = IOFactory::createReaderForFile($this->xlsFilePath);
            $reader->setReadDataOnly(true);

            if (method_exists($reader, 'setReadEmptyCells')) {
                $reader->setReadEmptyCells(false);
            }

            // if user specified sheet, do not load others
            if ($sheetName !== null && method_exists($reader, 'setLoadSheetsOnly')) {
                $reader->setLoadSheetsOnly([$sheetName]);
            }

            if (!$reader instanceof Xlsx && !$reader instanceof Xls) {
                throw new Exception\InvalidArgumentException(
                    'Unsupported spreadsheet format.'
                );
            }

            // Metainfo without loading the whole workbook
            $info = $reader->listWorksheetInfo($this->xlsFilePath);
            $sheetInfo = $this->pickSheetInfo($info, $sheetName);
            $sheetNameResolved = (string) ($sheetInfo['worksheetName'] ?? '');

            if ($sheetNameResolved === '') {
                throw new Exception\InvalidArgumentException('Unable to resolve worksheet name.');
            }

            $highestRow = (int) ($sheetInfo['totalRows'] ?? 0);
            $highestColLetter = (string) ($sheetInfo['lastColumnLetter'] ?? 'A');
            $highestColIndex = Coordinate::columnIndexFromString($highestColLetter);

            // Chunk filter
            $filter = new class implements IReadFilter {
                private int $startRow = 1;
                private int $endRow = 1;

                public function setRows(int $startRow, int $chunkSize): void
                {
                    $this->startRow = $startRow;
                    $this->endRow = $startRow + $chunkSize - 1;
                }

                public function readCell($columnAddress, $row, $worksheetName = ''): bool
                {
                    return $row >= $this->startRow && $row <= $this->endRow;
                }
            };
            $reader->setReadFilter($filter);

            // 1) Header: load only 1 row
            $filter->setRows($headerRow, 1);
            $spreadsheet = $reader->load($this->xlsFilePath);
            $ws = $spreadsheet->getSheetByName($sheetNameResolved) ?? $spreadsheet->getActiveSheet();

            // find last header column index = last non-empty cell in header row
            $lastHeaderColIndex = null;
            for ($c = $startColumnIndex; $c <= $highestColIndex; $c++) {
                $v = $ws->getCell(Coordinate::stringFromColumnIndex($c) . $headerRow)->getValue();
                $v = $this->normalizeCellValue($v);
                if (!$this->isEmptyCellValue($v)) {
                    $lastHeaderColIndex = $c;
                }
            }

            if ($lastHeaderColIndex === null) {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                return;
            }

            // read headers
            $headers = [];
            for ($c = $startColumnIndex; $c <= $lastHeaderColIndex; $c++) {
                $v = $ws->getCell(Coordinate::stringFromColumnIndex($c) . $headerRow)->getValue();
                $headers[] = $this->normalizeHeaderValue($v, $c);
            }

            // release header load
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            gc_collect_cycles();

            // 2) Data: read in chunks, stop at first empty row
            $dataStartRow = $headerRow + 1;

            $startColLetter = Coordinate::stringFromColumnIndex($startColumnIndex);
            $lastColLetter  = Coordinate::stringFromColumnIndex($lastHeaderColIndex);

            // adaptive chunkSize according to number of columns (aiming for approximately ~600k cells/chunk)
            $colCount = max(1, $lastHeaderColIndex - $startColumnIndex + 1);
            $chunkSize = $this->computeAdaptiveChunkSize($colCount);

            $stop = false;

            for ($chunkStart = $dataStartRow; $chunkStart <= $highestRow; $chunkStart += $chunkSize) {
                $filter->setRows($chunkStart, $chunkSize);

                $spreadsheet = $reader->load($this->xlsFilePath);
                $ws = $spreadsheet->getSheetByName($sheetNameResolved) ?? $spreadsheet->getActiveSheet();

                $chunkEnd = min($chunkStart + $chunkSize - 1, $highestRow);

                // Block read the whole area: faster than getCell in nested loop
                $matrix = $ws->rangeToArray(
                    "{$startColLetter}{$chunkStart}:{$lastColLetter}{$chunkEnd}",
                    null,
                    true,
                    false,
                    false
                );

                foreach ($matrix as $rowValues) {
                    // PHASE 1: fast check for empty row (without normalization)
                    $isEmptyRow = true;
                    foreach ($rowValues as $v) {
                        if ($v instanceof \DateTimeInterface) {
                            $isEmptyRow = false;
                            break;
                        }
                        if ($v instanceof RichText) {
                            if ($v->getPlainText() !== '') {
                                $isEmptyRow = false;
                                break;
                            }
                            continue;
                        }
                        if ($v !== null && $v !== '') {
                            $isEmptyRow = false;
                            break;
                        }
                    }

                    if ($isEmptyRow) {
                        $stop = true;
                        break;
                    }

                    // PHASE 2: map + normalization (fast mode inside normalizeCellValue)
                    $rowData = [];
                    foreach ($headers as $i => $header) {
                        $rowData[$header] = $this->normalizeCellValue($rowValues[$i] ?? null);
                    }

                    yield $rowData;
                }

                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                gc_collect_cycles();

                if ($stop) {
                    break;
                }
            }
        } catch (\Throwable $throwable) {
            throw new Exception\UnableOpenFileException(
                sprintf('Unable to open XLS file: %s', $throwable->getMessage()),
                previous: $throwable
            );
        }
    }

    public function provideSource(): string
    {
        $params = [];
        if ($this->xlsFilePath !== '') {
            $params[] = basename($this->xlsFilePath);
        }

        return sprintf('[xls](%s)', implode(',', $params));
    }

    /**
     * Parse query:
     * - FROM_ALL => (null, A1)
     * - "SheetName" => (SheetName, A1)
     * - "B3" => (null, B3)
     * - "SheetName.B3" => (SheetName, B3)
     *
     * @return array{0: string|null, 1: string}
     * @throws Exception\InvalidArgumentException
     */
    private function parseQuery(?string $query): array
    {
        $query = trim((string) $query);
        $sheetName = null;
        $cell = 'A1';

        if ($query !== '' && $query !== Interface\Query::FROM_ALL) {
            $parts = explode('.', $query, 2);
            if (count($parts) === 2) {
                $sheetName = $parts[0] !== '' ? $parts[0] : null;
                $cell = $parts[1] !== '' ? $parts[1] : $cell;
            } else {
                $part = $parts[0];
                if ($this->isCellReference($part)) {
                    $cell = $part;
                } else {
                    $sheetName = $part;
                }
            }
        }

        if (!$this->isCellReference($cell)) {
            throw new Exception\InvalidArgumentException(sprintf('Invalid start cell "%s".', $cell));
        }

        return [$sheetName, strtoupper($cell)];
    }

    /**
     * @param array<int, array<string, mixed>> $info
     * @return array<string, mixed>
     * @throws Exception\InvalidArgumentException
     */
    private function pickSheetInfo(array $info, ?string $sheetName): array
    {
        if ($sheetName === null) {
            if (!isset($info[0])) {
                throw new Exception\InvalidArgumentException('No worksheets found.');
            }
            return $info[0];
        }

        foreach ($info as $i) {
            if (($i['worksheetName'] ?? null) === $sheetName) {
                return $i;
            }
        }

        throw new Exception\InvalidArgumentException(sprintf('Sheet "%s" not found.', $sheetName));
    }

    private function computeAdaptiveChunkSize(int $colCount): int
    {
        // aiming for approximately ~600k cells per chunk (compromise RAM vs I/O)
        $targetCells = 600_000;

        $chunk = intdiv($targetCells, max(1, $colCount));

        // reasonable limits:
        // - min 200 rows (to avoid huge overhead)
        // - max 20k rows (to avoid too large chunk even for narrow tables)
        if ($chunk < 200) {
            return 200;
        }
        if ($chunk > 20_000) {
            return 20_000;
        }
        return $chunk;
    }

    private function normalizeHeaderValue(mixed $value, int $columnIndex): string
    {
        $value = $this->normalizeCellValue($value);
        if ($this->isEmptyCellValue($value)) {
            return Coordinate::stringFromColumnIndex($columnIndex);
        }

        return is_string($value) ? $value : (string) $value;
    }

    /**
     * Quick normalization of cell value
     * - RichText to plain text
     * - DateTime to ISO format
     * - string: type only if it "looks like" null/bool/number/quoted
     *  (otherwise return string directly to save matchByString on common texts)
     */
    private function normalizeCellValue(mixed $value): mixed
    {
        if ($value instanceof RichText) {
            $value = $value->getPlainText();
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('c');
        }

        if (!is_string($value)) {
            return $value;
        }

        $len = strlen($value);
        if ($len === 0) {
            return $value;
        }

        // Quick reject: if it doesn't look like a typed value, don't call matchByString()
        $c0 = $value[0];
        $maybeTyped =
            $c0 === '"' || $c0 === "'" ||                 // quoted string
            $c0 === '-' || $c0 === '+' || $c0 === '.' || $c0 === ',' || // number-like
            ($c0 >= '0' && $c0 <= '9') ||                 // number-like
            $len === 4 || $len === 5;                     // null/true/false (case-insensitive)

        return $maybeTyped ? Enum\Type::matchByString($value) : $value;
    }

    private function isCellReference(string $value): bool
    {
        return (bool) preg_match('/^[A-Z]+\d+$/i', $value);
    }

    private function isEmptyCellValue(mixed $value): bool
    {
        if ($value instanceof RichText) {
            $value = $value->getPlainText();
        }

        if ($value instanceof \DateTimeInterface) {
            return false;
        }

        return $value === null || $value === '';
    }
}
