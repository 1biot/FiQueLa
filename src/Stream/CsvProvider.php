<?php

namespace UQL\Stream;

use League\Csv\Bom;
use League\Csv\CharsetConverter;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Reader;
use League\Csv\UnavailableFeature;
use League\Csv\UnavailableStream;

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
     * @throws UnavailableStream
     * @throws InvalidArgument
     * @throws UnavailableFeature
     * @throws Exception
     */
    public function getStream(?string $query): ?\ArrayIterator
    {
        $generator = $this->getStreamGenerator($query);
        return $generator ? new \ArrayIterator(iterator_to_array($generator)) : null;
    }

    /**
     * @throws InvalidArgument
     * @throws UnavailableStream
     * @throws UnavailableFeature
     * @throws Exception
     */
    public function getStreamGenerator(?string $query): ?\Generator
    {
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

        foreach ($csv->getRecords() as $row) {
            yield $row;
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
}
