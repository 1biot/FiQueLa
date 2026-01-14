<?php

namespace FQL\Stream;

use FQL\Enum;
use FQL\Exception;
use FQL\Interface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

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
        $spreadsheet = $this->loadSpreadsheet();
        [$worksheet, $startCell] = $this->resolveWorksheetAndCell($spreadsheet, $query);

        [$startColumn, $startRow] = Coordinate::coordinateFromString($startCell);
        $startColumnIndex = Coordinate::columnIndexFromString($startColumn);
        $headerRow = (int) $startRow;

        $lastColumnIndex = $this->resolveLastHeaderColumnIndex($worksheet, $startColumnIndex, $headerRow);
        if ($lastColumnIndex === null) {
            return;
        }

        $headers = $this->readHeaders($worksheet, $startColumnIndex, $lastColumnIndex, $headerRow);

        $row = $headerRow + 1;
        while (true) {
            $rowValues = [];
            $isEmptyRow = true;

            for ($column = $startColumnIndex; $column <= $lastColumnIndex; $column++) {
                $cellValue = $worksheet->getCell(Coordinate::stringFromColumnIndex($column) . $row)->getValue();
                $normalizedValue = $this->normalizeCellValue($cellValue);
                if (!$this->isEmptyCellValue($normalizedValue)) {
                    $isEmptyRow = false;
                }
                $rowValues[] = $normalizedValue;
            }

            if ($isEmptyRow) {
                break;
            }

            $rowData = [];
            foreach ($headers as $index => $header) {
                $rowData[$header] = $rowValues[$index] ?? null;
            }

            yield $rowData;
            $row++;
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
     * @throws Exception\UnableOpenFileException
     */
    private function loadSpreadsheet(): Spreadsheet
    {
        try {
            $reader = IOFactory::createReaderForFile($this->xlsFilePath);
            $reader->setReadDataOnly(true);
            return $reader->load($this->xlsFilePath);
        } catch (\Throwable $throwable) {
            throw new Exception\UnableOpenFileException(
                sprintf('Unable to open XLS file: %s', $throwable->getMessage()),
                previous: $throwable
            );
        }
    }

    /**
     * @return array{0: Worksheet, 1: string}
     * @throws Exception\InvalidArgumentException
     */
    private function resolveWorksheetAndCell(Spreadsheet $spreadsheet, string $query): array
    {
        $query = trim($query);
        $sheetName = null;
        $cell = 'A1';

        if ($query !== '' && $query !== Interface\Query::FROM_ALL) {
            $parts = explode('.', $query, 2);
            if (count($parts) === 2) {
                $sheetName = $parts[0];
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

        $worksheet = $sheetName !== null
            ? $spreadsheet->getSheetByName($sheetName)
            : $spreadsheet->getActiveSheet();

        if ($worksheet === null) {
            throw new Exception\InvalidArgumentException(sprintf('Sheet "%s" not found.', $sheetName));
        }

        if (!$this->isCellReference($cell)) {
            throw new Exception\InvalidArgumentException(sprintf('Invalid start cell "%s".', $cell));
        }

        return [$worksheet, strtoupper($cell)];
    }

    private function resolveLastHeaderColumnIndex(Worksheet $worksheet, int $startColumnIndex, int $headerRow): ?int
    {
        $highestColumn = $worksheet->getHighestColumn($headerRow);
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        $lastColumnIndex = null;

        for ($column = $startColumnIndex; $column <= $highestColumnIndex; $column++) {
            $cellValue = $worksheet->getCell(Coordinate::stringFromColumnIndex($column) . $headerRow)->getValue();
            if (!$this->isEmptyCellValue($cellValue)) {
                $lastColumnIndex = $column;
            }
        }

        return $lastColumnIndex;
    }

    /**
     * @return string[]
     */
    private function readHeaders(
        Worksheet $worksheet,
        int $startColumnIndex,
        int $lastColumnIndex,
        int $headerRow
    ): array {
        $headers = [];
        for ($column = $startColumnIndex; $column <= $lastColumnIndex; $column++) {
            $cellValue = $worksheet->getCell(Coordinate::stringFromColumnIndex($column) . $headerRow)->getValue();
            $headers[] = $this->normalizeHeaderValue($cellValue, $column);
        }

        return $headers;
    }

    private function normalizeHeaderValue(mixed $value, int $columnIndex): string
    {
        $value = $this->normalizeCellValue($value);
        if ($this->isEmptyCellValue($value)) {
            return Coordinate::stringFromColumnIndex($columnIndex);
        }

        return is_string($value) ? $value : (string) $value;
    }

    private function normalizeCellValue(mixed $value): mixed
    {
        if ($value instanceof RichText) {
            $value = $value->getPlainText();
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('c');
        }

        if (is_string($value)) {
            return Enum\Type::matchByString($value);
        }

        return $value;
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
