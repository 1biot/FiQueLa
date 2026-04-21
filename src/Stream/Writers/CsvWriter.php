<?php

namespace FQL\Stream\Writers;

use FQL\Exception;
use FQL\Interface\Writer;
use FQL\Query\FileQuery;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Exception\EncodingConversionException;
use OpenSpout\Common\Helper\EncodingHelper;
use OpenSpout\Writer\CSV\Options;
use OpenSpout\Writer\CSV\Writer as CsvFileWriter;

class CsvWriter implements Writer
{
    private CsvFileWriter $writer;

    private EncodingHelper $encodingHelper;

    private ?string $targetEncoding = null;

    /** @var string[]|null */
    private ?array $headers = null;

    public function __construct(private readonly FileQuery $fileQuery)
    {
        $options = new Options();

        $delimiter = (string) $this->fileQuery->getParam('delimiter', ',');
        if ($delimiter !== '') {
            $options->FIELD_DELIMITER = $delimiter;
        }

        // Legacy (league/csv) writer never emitted the UTF-8 BOM.
        // OpenSpout defaults to adding it — opt out to keep byte-for-byte
        // compatibility with existing consumers / golden files.
        $options->SHOULD_ADD_BOM = false;

        $this->writer = new CsvFileWriter($options);
        $this->encodingHelper = EncodingHelper::factory();

        $encoding = $this->fileQuery->getParam('encoding');
        if (is_string($encoding) && $encoding !== '' && strtolower($encoding) !== 'utf-8') {
            $this->targetEncoding = $encoding;
        }

        $this->writer->openToFile($this->fileQuery->file ?? 'php://memory');
    }

    /**
     * @param array<string, mixed> $row
     */
    public function write(array $row): void
    {
        if ($this->headers === null) {
            $this->headers = array_keys($row);
            $this->writer->addRow(Row::fromValues($this->encodeCells($this->headers)));
        }

        $ordered = [];
        foreach ($this->headers as $header) {
            $value = $row[$header] ?? null;
            $ordered[] = is_scalar($value) || $value === null
                ? $value
                : json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $this->writer->addRow(Row::fromValues($this->encodeCells($ordered)));
    }

    /**
     * Converts every string cell from UTF-8 into the writer's target encoding
     * via OpenSpout's own {@see EncodingHelper} (iconv + mbstring fallback).
     * Non-string cells (int/float/bool/null) are passed through unchanged —
     * OpenSpout's {@see Row::fromValues()} typed them into the matching Cell
     * subclass and `fputcsv()` renders them in ASCII-safe form anyway.
     *
     * @param array<int, string|int|float|bool|null> $cells
     * @return array<int, string|int|float|bool|null>
     * @throws Exception\UnableOpenFileException
     */
    private function encodeCells(array $cells): array
    {
        if ($this->targetEncoding === null) {
            return $cells;
        }

        try {
            $encoded = [];
            foreach ($cells as $cell) {
                $encoded[] = is_string($cell)
                    ? $this->encodingHelper->attemptConversionFromUTF8($cell, $this->targetEncoding)
                    : $cell;
            }
            return $encoded;
        } catch (EncodingConversionException $e) {
            throw new Exception\UnableOpenFileException(
                sprintf('Unable to encode CSV row to "%s": %s', $this->targetEncoding, $e->getMessage()),
                previous: $e
            );
        }
    }

    public function close(): void
    {
        $this->writer->close();
    }

    public function getFileQuery(): FileQuery
    {
        return $this->fileQuery->query === null
            ? $this->fileQuery->withQuery('*')
            : $this->fileQuery;
    }
}
