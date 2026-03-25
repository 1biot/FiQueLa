<?php

namespace FQL\Stream\Writers;

use FQL\Interface\Writer;
use FQL\Query\FileQuery;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\ODS\Writer as OdsFileWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxFileWriter;

abstract class AbstractSpreadsheetWriter implements Writer
{
    protected XlsxFileWriter|OdsFileWriter $writer;

    /** @var string[]|null */
    private ?array $headers = null;
    private bool $initialized = false;
    private string $sheetName;
    private int $startColumnIndex;
    private int $startRow;

    public function __construct(protected readonly FileQuery $fileQuery)
    {
        [$this->sheetName, $startCell] = $this->parseQuery($this->fileQuery->query);
        [$this->startColumnIndex, $this->startRow] = $this->parseCellReference($startCell);

        $this->writer = $this->createWriter();
        $this->writer->openToFile($this->fileQuery->file ?? 'php://memory');

        if ($this->sheetName !== '') {
            $this->writer->getCurrentSheet()->setName($this->sheetName);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    public function write(array $row): void
    {
        if (!$this->initialized) {
            $this->initializeStartPosition();
            $this->initialized = true;
        }

        if ($this->headers === null) {
            $this->headers = array_keys($row);
            $this->writer->addRow($this->buildRow($this->headers));
        }

        $ordered = [];
        foreach ($this->headers as $header) {
            $value = $row[$header] ?? null;
            $ordered[] = is_scalar($value) || $value === null
                ? $value
                : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $this->writer->addRow($this->buildRow($ordered));
    }

    public function close(): void
    {
        if (!$this->initialized) {
            $this->initializeStartPosition();
            $this->initialized = true;
        }

        $this->writer->close();
    }

    abstract protected function createWriter(): XlsxFileWriter|OdsFileWriter;

    /**
     * @param array<int, mixed> $values
     */
    private function buildRow(array $values): Row
    {
        $cells = [];
        for ($i = 0; $i < $this->startColumnIndex; $i++) {
            $cells[] = Cell::fromValue('');
        }

        foreach ($values as $value) {
            $cells[] = Cell::fromValue($value);
        }

        return new Row($cells);
    }

    private function initializeStartPosition(): void
    {
        for ($row = 1; $row < $this->startRow; $row++) {
            $emptyRow = [];
            for ($column = 0; $column <= $this->startColumnIndex; $column++) {
                $emptyRow[] = Cell::fromValue('');
            }
            $this->writer->addRow(new Row($emptyRow));
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseQuery(?string $query): array
    {
        if ($query === null || $query === '') {
            return ['', 'A1'];
        }

        $parts = array_values(array_filter(explode('.', $query), static fn (string $part) => $part !== ''));
        $sheet = $parts[0] ?? '';
        $cell = isset($parts[1]) && $this->isCellReference($parts[1]) ? strtoupper($parts[1]) : 'A1';

        if ($sheet !== '' && $this->isCellReference($sheet) && !isset($parts[1])) {
            return ['', strtoupper($sheet)];
        }

        return [$sheet, $cell];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function parseCellReference(string $cell): array
    {
        if (!preg_match('/^([A-Z]+)(\d+)$/', strtoupper($cell), $matches)) {
            return [0, 1];
        }

        $columnLetters = $matches[1];
        $row = (int) $matches[2];
        $columnIndex = 0;

        for ($i = 0, $len = strlen($columnLetters); $i < $len; $i++) {
            $columnIndex = $columnIndex * 26 + (ord($columnLetters[$i]) - ord('A') + 1);
        }

        return [$columnIndex - 1, $row];
    }

    private function isCellReference(string $value): bool
    {
        return (bool) preg_match('/^[A-Z]+\d+$/i', $value);
    }
}
