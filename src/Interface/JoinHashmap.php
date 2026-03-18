<?php

namespace FQL\Interface;

/**
 * @phpstan-import-type StreamProviderArrayIteratorValue from \FQL\Results\Stream
*/
interface JoinHashmap
{
    /**
     * @param string|int $key
     * @param StreamProviderArrayIteratorValue $row
     * @return void
     */
    public function set(string|int $key, array $row): void;

    /**
     * @return StreamProviderArrayIteratorValue[]
     */
    public function get(string|int $key): array;

    /**
     * @return array<int|string, StreamProviderArrayIteratorValue[]>
     */
    public function getAll(): array;

    public function has(string|int $key): bool;

    /**
     * @return array<int, int|string>
     */
    public function getStructure(): array;
    public function clear(): void;
}
