<?php

namespace FQL\Interface;

use FQL\Query\FileQuery;

interface Writer
{
    /**
     * Write a single row.
     *
     * @param array<string, mixed> $row
     */
    public function write(array $row): void;

    /**
     * Finalize and close the file.
     */
    public function close(): void;

    /**
     * Returns effective FileQuery with default query applied (for reading back the written file).
     */
    public function getFileQuery(): FileQuery;
}
