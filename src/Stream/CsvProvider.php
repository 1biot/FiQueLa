<?php

namespace FQL\Stream;

use FQL\Enum;
use FQL\Exception;
use OpenSpout\Common\Exception\EncodingConversionException;
use OpenSpout\Reader\CSV\Options;
use OpenSpout\Reader\CSV\Reader;

/**
 * @phpstan-import-type StreamProviderArrayIterator from ArrayStreamProvider
 */
abstract class CsvProvider extends AbstractStream
{
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
     * @throws Exception\UnableOpenFileException
     */
    public function getStreamGenerator(?string $query): \Generator
    {
        $options = new Options();
        $options->FIELD_DELIMITER = $this->delimiter;
        if ($this->inputEncoding !== null && $this->inputEncoding !== '') {
            $options->ENCODING = $this->inputEncoding;
        }

        $reader = new Reader($options);
        try {
            $reader->open($this->csvFilePath);

            $sheet = null;
            foreach ($reader->getSheetIterator() as $csvSheet) {
                $sheet = $csvSheet;
                break;
            }

            if ($sheet === null) {
                return;
            }

            $headers = null;
            $headerCount = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $raw = $row->toArray();
                $values = [];
                foreach ($raw as $cellValue) {
                    $values[] = self::normalizeCellValue($cellValue);
                }

                if ($this->useHeader && $headers === null) {
                    // Remember the declared column names and skip emitting
                    // the header row — downstream wants associative rows only.
                    $headers = array_map(static fn ($v): string => (string) $v, $values);
                    $headerCount = count($headers);
                    continue;
                }

                if ($headers !== null) {
                    // Length-normalise against the header count so array_combine()
                    // (native C, ~2–3× faster than a PHP foreach for typical
                    // 10–50 column CSVs) can zip values with keys safely.
                    $valueCount = count($values);
                    if ($valueCount < $headerCount) {
                        $values = array_pad($values, $headerCount, null);
                    } elseif ($valueCount > $headerCount) {
                        $values = array_slice($values, 0, $headerCount);
                    }
                    yield array_combine($headers, $values);
                } else {
                    yield $values;
                }
            }
        } catch (EncodingConversionException $e) {
            throw new Exception\UnableOpenFileException(
                sprintf('Unable to decode CSV with encoding "%s": %s', $this->inputEncoding ?? 'UTF-8', $e->getMessage()),
                previous: $e
            );
        } catch (\Throwable $e) {
            throw new Exception\UnableOpenFileException(
                sprintf('Unexpected error: %s', $e->getMessage()),
                previous: $e
            );
        } finally {
            $reader->close();
        }
    }

    /**
     * Cheap "does this string look like a typed scalar literal?" heuristic.
     * Only strings starting with a quote / sign / digit / decimal separator
     * or strings that are 4–5 chars long (covering `null`, `true`, `false`)
     * are worth running through {@see Enum\Type::matchByString}. Everything
     * else — typical product names, free-form descriptions, category paths —
     * short-circuits to the raw string, saving the regex + in_array + numeric
     * probes inside `matchByString()` on the vast majority of cells in
     * text-heavy CSVs. Mirrors {@see SpreadsheetProvider::normalizeCellValue}.
     */
    private static function normalizeCellValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $len = strlen($value);
        if ($len === 0) {
            return $value;
        }

        $c0 = $value[0];
        $maybeTyped =
            $c0 === '"' || $c0 === "'"
            || $c0 === '-' || $c0 === '+' || $c0 === '.' || $c0 === ','
            || ($c0 >= '0' && $c0 <= '9')
            || $len === 4 || $len === 5;

        return $maybeTyped ? Enum\Type::matchByString($value) : $value;
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
