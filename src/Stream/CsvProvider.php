<?php

namespace FQL\Stream;

use FQL\Enum;
use FQL\Exception;
use League\Csv;

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
        try {
            $csv = Csv\Reader::from($this->csvFilePath);
            $csv->setDelimiter($this->delimiter);
            if ($this->inputEncoding !== null && $this->inputEncoding !== '' && $this->inputEncoding !== 'UTF-8') {
                $csv->appendStreamFilterOnRead(sprintf('convert.iconv.%s/UTF-8', $this->inputEncoding));
            }

            if ($this->useHeader) {
                $csv->setHeaderOffset(0);
            }

            $encoder = new Csv\CharsetConverter();
            $encoder->inputEncoding('ASCII');
            $encoder->outputEncoding('UTF-8');

            foreach ($csv->getRecords() as $row) {
                yield array_map(fn ($value) => is_string($value) ? Enum\Type::matchByString($value) : $value, $row);
            }
        } catch (\Exception $e) {
            throw new Exception\UnableOpenFileException(
                sprintf('Unexpected error: %s', $e->getMessage()),
                previous: $e
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

        if ($this->inputEncoding !== null) {
            $params[] = $this->inputEncoding;
        }

        if ($this->delimiter !== ',') {
            $params[] = sprintf('"%s"', $this->delimiter);
        }

        return sprintf('[csv](%s)', implode(',', $params));
    }
}
