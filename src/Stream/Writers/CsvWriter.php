<?php

namespace FQL\Stream\Writers;

use FQL\Exception;
use FQL\Interface\Writer;
use FQL\Query\FileQuery;

/**
 * Native CSV writer built on top of `fopen` + `fputcsv` — deliberately
 * dependency-free so the hot-path row emission stays as close to bare-metal
 * PHP as possible. Encoding, delimiter, enclosure and BOM emission are all
 * controlled via FileQuery parameters.
 */
class CsvWriter implements Writer
{
    /** @var resource */
    private $handle;

    private readonly string $delimiter;
    private readonly string $enclosure;
    private readonly ?string $targetEncoding;
    private readonly bool $emitBom;

    /** @var string[]|null */
    private ?array $headers = null;

    /**
     * @throws Exception\UnableOpenFileException
     */
    public function __construct(private readonly FileQuery $fileQuery)
    {
        $this->delimiter = $this->readSingleChar('delimiter', ',');
        $this->enclosure = $this->readSingleChar('enclosure', '"');

        $encoding = $this->fileQuery->getParam('encoding');
        $this->targetEncoding = (is_string($encoding) && $encoding !== '' && strcasecmp($encoding, 'UTF-8') !== 0)
            ? $encoding
            : null;

        $bomFlag = $this->fileQuery->getParam('bom');
        $this->emitBom = $bomFlag !== null && !in_array(
            is_string($bomFlag) ? strtolower($bomFlag) : $bomFlag,
            ['0', 'false', 'off', 'no', false, 0],
            true
        );

        $target = $this->fileQuery->file ?? 'php://memory';
        $handle = @fopen($target, 'w');
        if ($handle === false) {
            throw new Exception\UnableOpenFileException(
                sprintf('Unable to open CSV target "%s" for writing.', $target)
            );
        }
        $this->handle = $handle;

        if ($this->emitBom && $this->targetEncoding === null) {
            // UTF-8 BOM — opt-in only; prior library behaviour did not emit one.
            fwrite($this->handle, "\xEF\xBB\xBF");
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    public function write(array $row): void
    {
        $headers = $this->headers;
        if ($headers === null) {
            $headers = array_keys($row);
            $this->headers = $headers;
            $this->writeRow($headers);
        }

        $ordered = [];
        foreach ($headers as $header) {
            $value = $row[$header] ?? null;
            $ordered[] = is_scalar($value) || $value === null
                ? $value
                : json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $this->writeRow($ordered);
    }

    public function close(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    public function getFileQuery(): FileQuery
    {
        return $this->fileQuery->query === null
            ? $this->fileQuery->withQuery('*')
            : $this->fileQuery;
    }

    /**
     * Serialises a row with `fputcsv` after an optional UTF-8 → target
     * encoding conversion per cell. Per-cell iconv sounds expensive but it
     * runs only over the cells the caller actually emits (write-side volume
     * is typically orders of magnitude smaller than read-side), and avoids
     * layering a `php://filter` wrapper on the file pointer that's hard to
     * reason about for consumers.
     *
     * @param array<int, string|int|float|bool|null> $cells
     * @throws Exception\UnableOpenFileException
     */
    private function writeRow(array $cells): void
    {
        if ($this->targetEncoding !== null) {
            foreach ($cells as $idx => $cell) {
                if (!is_string($cell)) {
                    continue;
                }
                $converted = @iconv('UTF-8', $this->targetEncoding, $cell);
                if ($converted === false) {
                    throw new Exception\UnableOpenFileException(
                        sprintf(
                            'Unable to transcode CSV cell from UTF-8 to "%s".',
                            $this->targetEncoding
                        )
                    );
                }
                $cells[$idx] = $converted;
            }
        }

        if (fputcsv($this->handle, $cells, $this->delimiter, $this->enclosure, '') === false) {
            throw new Exception\UnableOpenFileException('Failed to write CSV row.');
        }
    }

    /**
     * Reads a single-character FileQuery parameter, defaulting to `$default`
     * if the value is missing or empty. Values longer than a single byte
     * are truncated — delimiter / enclosure are by convention one byte in
     * standard CSV dialects.
     */
    private function readSingleChar(string $name, string $default): string
    {
        $value = $this->fileQuery->getParam($name);
        if (!is_string($value) || $value === '') {
            return $default;
        }
        return $value[0];
    }
}
