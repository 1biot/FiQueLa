<?php

namespace FQL\Stream\Writers;

use FQL\Interface\Writer;
use FQL\Query\FileQuery;

class JsonWriter implements Writer
{
    /** @var array<int, array<string, mixed>> */
    private array $rows = [];

    public function __construct(private readonly FileQuery $fileQuery)
    {
    }

    /**
     * @param array<string, mixed> $row
     */
    public function write(array $row): void
    {
        $this->rows[] = $row;
    }

    public function close(): void
    {
        $data = $this->rows;
        if ($this->fileQuery->query !== null) {
            $segments = array_values(array_filter(explode('.', $this->fileQuery->query), static fn (string $part) => $part !== ''));
            for ($i = count($segments) - 1; $i >= 0; $i--) {
                $data = [$segments[$i] => $data];
            }
        }

        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false || $this->fileQuery->file === null) {
            return;
        }

        file_put_contents($this->fileQuery->file, $encoded . PHP_EOL);
    }
}
