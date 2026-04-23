<?php

namespace FQL\Stream;

use FQL\Exception;

/**
 * @phpstan-import-type StreamProviderArrayIterator from ArrayStreamProvider
 */
abstract class CsvProvider extends AbstractStream
{
    /** Byte-order-mark signatures we strip from the very start of a stream. */
    private const BOM_SIGNATURES = [
        "\xEF\xBB\xBF",          // UTF-8
        "\xFF\xFE\x00\x00",      // UTF-32 LE (must be tested before UTF-16 LE)
        "\x00\x00\xFE\xFF",      // UTF-32 BE
        "\xFF\xFE",              // UTF-16 LE
        "\xFE\xFF",              // UTF-16 BE
    ];

    protected bool $useHeader = true;
    private ?string $inputEncoding = null;

    protected function __construct(private readonly string $csvFilePath, private string $delimiter = ',')
    {
    }

    public function setDelimiter(string $delimiter): self
    {
        $this->delimiter = $delimiter;
        return $this;
    }

    public function useHeader(bool $useHeader): self
    {
        $this->useHeader = $useHeader;
        return $this;
    }

    public function setInputEncoding(string $encoding): self
    {
        $this->inputEncoding = $encoding;
        return $this;
    }

    /**
     * @return StreamProviderArrayIterator
     * @throws Exception\UnableOpenFileException
     */
    public function getStream(?string $query): \ArrayIterator
    {
        return new \ArrayIterator(iterator_to_array($this->getStreamGenerator($query)));
    }

    /**
     * Stream-reads the CSV file row-by-row via native `fgetcsv` — no library
     * wrapping, no per-cell object allocations. Encoding conversion uses the
     * PHP stream filter API (`convert.iconv.<src>/UTF-8`) so the iconv work
     * happens once per chunk in C rather than once per cell in user land.
     *
     * BOM handling: the four common UTF BOMs are detected on the raw bytes
     * before the iconv filter attaches and the file pointer is advanced past
     * them; the first data row therefore never contains a stray BOM in its
     * leading header key.
     *
     * Type coercion is **not** performed here: cells flow out as raw strings
     * and get typed lazily in `Enum\Operator::evaluate()` when compared.
     *
     * @throws Exception\UnableOpenFileException
     */
    public function getStreamGenerator(?string $query): \Generator
    {
        $handle = @fopen($this->csvFilePath, 'r');
        if ($handle === false) {
            throw new Exception\UnableOpenFileException(
                sprintf('Unable to open CSV file "%s".', $this->csvFilePath)
            );
        }

        try {
            $this->skipBom($handle);
            $this->appendEncodingFilter($handle);

            $headers = null;
            $headerCount = 0;

            while (($row = fgetcsv($handle, 0, $this->delimiter, '"', '')) !== false) {
                // fgetcsv returns `[null]` for fully empty lines when the
                // strict mode is off — drop them for consistency with the
                // previous implementation.
                if ($row === [null]) {
                    continue;
                }

                if ($this->useHeader && $headers === null) {
                    $headers = array_map(static fn ($v): string => (string) $v, $row);
                    $headerCount = count($headers);
                    continue;
                }

                if ($headers !== null) {
                    // Length-normalise against the header count so
                    // `array_combine` (native C, ~2–3× faster than a PHP
                    // foreach on 10–50 column CSVs) can zip values with keys.
                    $valueCount = count($row);
                    if ($valueCount < $headerCount) {
                        $row = array_pad($row, $headerCount, null);
                    } elseif ($valueCount > $headerCount) {
                        $row = array_slice($row, 0, $headerCount);
                    }
                    yield array_combine($headers, $row);
                } else {
                    yield $row;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Peeks at the first four bytes and moves the file pointer past any
     * recognised BOM. Called before the iconv filter is attached so the BOM
     * bytes are compared in their native encoding.
     *
     * @param resource $handle
     */
    private function skipBom($handle): void
    {
        $prefix = (string) fread($handle, 4);
        foreach (self::BOM_SIGNATURES as $bom) {
            if (str_starts_with($prefix, $bom)) {
                fseek($handle, strlen($bom));
                return;
            }
        }
        // No BOM — rewind so we don't swallow the first 4 bytes of content.
        rewind($handle);
    }

    /**
     * Attaches a PHP stream filter that transcodes non-UTF-8 input to UTF-8
     * on the fly. No-op when the input is already UTF-8 or no encoding was
     * configured.
     *
     * @param resource $handle
     * @throws Exception\UnableOpenFileException
     */
    private function appendEncodingFilter($handle): void
    {
        if ($this->inputEncoding === null || $this->inputEncoding === '') {
            return;
        }
        if (strcasecmp($this->inputEncoding, 'UTF-8') === 0) {
            return;
        }
        $filter = @stream_filter_append(
            $handle,
            sprintf('convert.iconv.%s/UTF-8', $this->inputEncoding),
            STREAM_FILTER_READ
        );
        if ($filter === false) {
            throw new Exception\UnableOpenFileException(
                sprintf('Unsupported input encoding "%s" for iconv conversion.', $this->inputEncoding)
            );
        }
    }

    public function isUseHeader(): bool
    {
        return $this->useHeader;
    }

    public function getInputEncoding(): ?string
    {
        return $this->inputEncoding;
    }

    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    public function getCsvFilePath(): string
    {
        return $this->csvFilePath;
    }

    public function provideSource(): string
    {
        $params = [];
        if ($this->csvFilePath !== '') {
            $params[] = basename($this->csvFilePath);
        }

        $hasNonDefaultEncoding = $this->inputEncoding !== null && strtolower($this->inputEncoding) !== 'utf-8';
        $hasNonDefaultDelimiter = $this->delimiter !== ',';
        $hasNonDefaultHeader = !$this->useHeader;

        if ($hasNonDefaultHeader) {
            $params[] = sprintf('"%s"', $this->inputEncoding ?? 'utf-8');
            $params[] = sprintf('"%s"', $this->delimiter);
            $params[] = '"0"';
        } elseif ($hasNonDefaultDelimiter) {
            $params[] = sprintf('"%s"', $this->inputEncoding ?? 'utf-8');
            $params[] = sprintf('"%s"', $this->delimiter);
        } elseif ($hasNonDefaultEncoding) {
            $params[] = sprintf('"%s"', $this->inputEncoding);
        }

        return sprintf('csv(%s)', implode(', ', $params));
    }
}
