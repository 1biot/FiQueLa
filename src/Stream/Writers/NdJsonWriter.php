<?php

namespace FQL\Stream\Writers;

use FQL\Interface\Writer;
use FQL\Query\FileQuery;

class NdJsonWriter implements Writer
{
    /** @var resource|null */
    private $handle;

    public function __construct(private readonly FileQuery $fileQuery)
    {
        $handle = $this->fileQuery->file !== null
            ? fopen($this->fileQuery->file, 'wb')
            : null;
        $this->handle = is_resource($handle) ? $handle : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function write(array $row): void
    {
        if ($this->handle === null) {
            return;
        }

        $json = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }

        fwrite($this->handle, $json . PHP_EOL);
    }

    public function close(): void
    {
        if ($this->handle !== null) {
            fclose($this->handle);
            $this->handle = null;
        }
    }

    public function getFileQuery(): FileQuery
    {
        return $this->fileQuery->query === null
            ? $this->fileQuery->withQuery('*')
            : $this->fileQuery;
    }
}
