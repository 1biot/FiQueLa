<?php

namespace UQL\Stream;

use League\Csv\CharsetConverter;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Reader;
use League\Csv\UnavailableFeature;
use League\Csv\UnavailableStream;
use UQL\Exceptions\UnableOpenFileException;

/**
 * @implements Stream<\Generator>
 */
abstract class CsvProvider extends StreamProvider implements Stream
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
     * @throws UnableOpenFileException
     */
    public function getStream(?string $query): ?\ArrayIterator
    {
        $generator = $this->getStreamGenerator($query);
        return $generator ? new \ArrayIterator(iterator_to_array($generator)) : null;
    }

    /**
     * @throws UnableOpenFileException
     */
    public function getStreamGenerator(?string $query): ?\Generator
    {
        try {
            $csv = Reader::createFromPath($this->csvFilePath);
            $csv->setDelimiter($this->delimiter);
            if ($this->inputEncoding !== null && $this->inputEncoding !== '' && $this->inputEncoding !== 'UTF-8') {
                $csv->addStreamFilter(sprintf('convert.iconv.%s/UTF-8', $this->inputEncoding));
            }

            if ($this->useHeader) {
                $csv->setHeaderOffset(0);
            }

            $encoder = new CharsetConverter();
            $encoder->inputEncoding('ASCII');
            $encoder->outputEncoding('UTF-8');

            yield from $csv->getRecords();
        } catch (\Exception $e) {
            throw new UnableOpenFileException(sprintf('Unexpected error: %s', $e->getMessage()), previous: $e);
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
        $source = '';
        if ($this->csvFilePath !== '') {
            if (pathinfo($this->csvFilePath, PATHINFO_EXTENSION) !== 'csv') {
                $source .= 'csv://';
            }
            $source .= basename($this->csvFilePath);
            $source = sprintf('[%s]', $source);
            if ($this->inputEncoding !== null && $this->inputEncoding !== '') {
                $source .= "({$this->inputEncoding})";
            }
        }
        return $source;
    }
}
