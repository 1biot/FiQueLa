<?php

namespace FQL\Interface;

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
}
