<?php

namespace FQL\Stream;

use FQL\Enum;
use FQL\Exception;
use FQL\Interface;
use OpenSpout\Reader\ReaderInterface;

abstract class SpreadsheetProvider extends AbstractStream
{
    protected function __construct(private readonly string $filePath)
    {
    }

    /**
     * Create the appropriate OpenSpout reader for the file format.
     *
     * @phpstan-ignore missingType.generics
     */
    abstract protected function createReader(): ReaderInterface;

    /**
     * Return the format tag used in provideSource(), e.g. "xls" or "ods".
     */
    abstract protected function formatTag(): string;

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
        [$sheetIdentifier, $startCell] = $this->parseQuery($query);

        [$startColumnIndex, $startRow] = $this->parseCellReference($startCell);

        $reader = null;
        try {
            $reader = $this->createReader();
            $reader->open($this->filePath);

            // Find the target sheet
            $targetSheet = null;
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheetIdentifier === null) {
                    // No sheet specified — use first sheet
                    $targetSheet = $sheet;
                    break;
                }

                // Try match by name first
                if ($sheet->getName() === $sheetIdentifier) {
                    $targetSheet = $sheet;
                    break;
                }

                // Try match by numeric index (1-based)
                if (is_numeric($sheetIdentifier) && $sheet->getIndex() === ((int) $sheetIdentifier - 1)) {
                    $targetSheet = $sheet;
                    break;
                }
            }

            if ($targetSheet === null) {
                throw new Exception\UnableOpenFileException(
                    sprintf(
                        'Unable to open spreadsheet: Sheet "%s" not found.',
                        $sheetIdentifier ?? '(default)'
                    )
                );
            }

            $headers = null;
            $headerCount = 0;
            $currentRow = 0;

            foreach ($targetSheet->getRowIterator() as $row) {
                $currentRow++;

                // Skip rows before the start row
                if ($currentRow < $startRow) {
                    continue;
                }

                $cells = $row->getCells();

                // Header row
                if ($headers === null) {
                    $headers = [];
                    $lastNonEmptyIndex = null;

                    // Find last non-empty header cell starting from startColumnIndex
                    for ($c = $startColumnIndex; $c < count($cells); $c++) {
                        $v = $this->normalizeCellValue($cells[$c]->getValue());
                        if (!$this->isEmptyCellValue($v)) {
                            $lastNonEmptyIndex = $c;
                        }
                    }

                    if ($lastNonEmptyIndex === null) {
                        // No headers found — empty sheet
                        return;
                    }

                    // Read header values
                    for ($c = $startColumnIndex; $c <= $lastNonEmptyIndex; $c++) {
                        if ($c < count($cells)) {
                            $v = $this->normalizeCellValue($cells[$c]->getValue());
                            $headers[] = $this->normalizeHeaderValue(
                                $v,
                                $c
                            );
                        } else {
                            $headers[] = self::columnIndexToLetter($c);
                        }
                    }

                    $headerCount = count($headers);
                    continue;
                }

                // Data rows: check if entire row is empty (within header column range)
                $isEmptyRow = true;
                for ($c = $startColumnIndex; $c < $startColumnIndex + $headerCount; $c++) {
                    if ($c < count($cells)) {
                        $v = $cells[$c]->getValue();
                        if ($v instanceof \DateTimeInterface) {
                            $isEmptyRow = false;
                            break;
                        }
                        if ($v !== null && $v !== '') {
                            $isEmptyRow = false;
                            break;
                        }
                    }
                }

                // Stop at first empty row
                if ($isEmptyRow) {
                    break;
                }

                // Map cells to header keys
                $rowData = [];
                /** @var non-empty-list<string> $headers */
                for ($i = 0; $i < $headerCount; $i++) {
                    $cellIndex = $startColumnIndex + $i;
                    $value = ($cellIndex < count($cells)) ? $cells[$cellIndex]->getValue() : null;
                    $rowData[$headers[$i]] = $this->normalizeCellValue($value);
                }

                yield $rowData;
            }
        } catch (Exception\UnableOpenFileException $e) {
            throw $e;
        } catch (\Throwable $throwable) {
            throw new Exception\UnableOpenFileException(
                sprintf('Unable to open spreadsheet: %s', $throwable->getMessage()),
                previous: $throwable
            );
        } finally {
            $reader?->close();
        }
    }

    public function provideSource(): string
    {
        $params = [];
        if ($this->filePath !== '') {
            $params[] = basename($this->filePath);
        }

        return sprintf('%s(%s)', $this->formatTag(), implode(', ', $params));
    }

    /**
     * Parse query:
     * - FROM_ALL => (null, A1)
     * - "SheetName" => (SheetName, A1)
     * - "B3" => (null, B3)
     * - "SheetName.B3" => (SheetName, B3)
     * - "1" => (1, A1) — numeric sheet index (1-based)
     * - "1.B3" => (1, B3) — numeric sheet index with cell
     * - "Sheet 1.B3" => (Sheet 1, B3) — sheet name with spaces
     *
     * @return array{0: string|null, 1: string}
     * @throws Exception\InvalidArgumentException
     */
    private function parseQuery(?string $query): array
    {
        $query = trim((string) $query);
        $sheetIdentifier = null;
        $cell = 'A1';

        if ($query !== '' && $query !== Interface\Query::FROM_ALL) {
            // Find the last dot that might separate sheet name from cell reference
            $lastDotPos = strrpos($query, '.');
            if ($lastDotPos !== false) {
                $possibleCell = substr($query, $lastDotPos + 1);
                $possibleSheet = substr($query, 0, $lastDotPos);

                if ($this->isCellReference($possibleCell)) {
                    $sheetIdentifier = $possibleSheet !== '' ? $possibleSheet : null;
                    $cell = $possibleCell;
                } else {
                    // The whole thing is a sheet name (no cell reference after the last dot)
                    $sheetIdentifier = $query;
                }
            } else {
                // No dot at all — could be a cell reference or a sheet name/index
                if ($this->isCellReference($query)) {
                    $cell = $query;
                } else {
                    $sheetIdentifier = $query;
                }
            }
        }

        if (!$this->isCellReference($cell)) {
            throw new Exception\InvalidArgumentException(sprintf('Invalid start cell "%s".', $cell));
        }

        return [$sheetIdentifier, strtoupper($cell)];
    }

    /**
     * Parse a cell reference like "A1", "G14" into [columnIndex (0-based), rowNumber (1-based)]
     *
     * @return array{0: int, 1: int}
     */
    private function parseCellReference(string $cell): array
    {
        if (!preg_match('/^([A-Z]+)(\d+)$/i', $cell, $matches)) {
            return [0, 1];
        }
        $columnLetters = strtoupper((string) $matches[1]);
        $row = (int) $matches[2];

        $columnIndex = 0;
        $len = strlen($columnLetters);
        for ($i = 0; $i < $len; $i++) {
            $columnIndex = $columnIndex * 26 + (ord($columnLetters[$i]) - ord('A') + 1);
        }
        // Convert to 0-based
        $columnIndex--;

        return [$columnIndex, $row];
    }

    /**
     * Convert 0-based column index to letter (0 => A, 1 => B, 25 => Z, 26 => AA, ...)
     */
    private static function columnIndexToLetter(int $index): string
    {
        $letter = '';
        $index++; // Convert to 1-based
        while ($index > 0) {
            $index--;
            $letter = chr(ord('A') + ($index % 26)) . $letter;
            $index = intdiv($index, 26);
        }
        return $letter;
    }

    private function normalizeHeaderValue(mixed $value, int $columnIndex): string
    {
        if ($this->isEmptyCellValue($value)) {
            return self::columnIndexToLetter($columnIndex);
        }

        return is_string($value) ? $value : (string) $value;
    }

    /**
     * Quick normalization of cell value
     * - DateTime to ISO format
     * - string: type only if it "looks like" null/bool/number/quoted
     *  (otherwise return string directly to save matchByString on common texts)
     */
    private function normalizeCellValue(mixed $value): mixed
    {
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
        if ($value instanceof \DateTimeInterface) {
            return false;
        }

        return $value === null || $value === '';
    }
}
