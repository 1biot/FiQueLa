<?php

namespace FQL\Stream\Writers;

use FQL\Interface\Writer;
use FQL\Query\FileQuery;
use League\Csv;

class CsvWriter implements Writer
{
    private Csv\Writer $writer;

    /** @var string[]|null */
    private ?array $headers = null;

    public function __construct(private readonly FileQuery $fileQuery)
    {
        $this->writer = Csv\Writer::from($this->fileQuery->file ?? 'php://memory', 'w+');

        $delimiter = (string) $this->fileQuery->getParam('delimiter', ',');
        if ($delimiter !== '') {
            $this->writer->setDelimiter($delimiter);
        }

        $encoding = $this->fileQuery->getParam('encoding');
        if (is_string($encoding) && $encoding !== '' && strtolower($encoding) !== 'utf-8') {
            $this->writer->appendStreamFilterOnWrite(sprintf('convert.iconv.UTF-8/%s', $encoding));
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    public function write(array $row): void
    {
        if ($this->headers === null) {
            $this->headers = array_keys($row);
            $this->writer->insertOne($this->headers);
        }

        $ordered = [];
        foreach ($this->headers as $header) {
            $value = $row[$header] ?? null;
            $ordered[] = is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $this->writer->insertOne($ordered);
    }

    public function close(): void
    {
    }
}
