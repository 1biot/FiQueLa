<?php

namespace FQL\Utils;

use FQL\Interface\JoinHashmap;

/**
 * @phpstan-import-type StreamProviderArrayIteratorValue from \FQL\Results\Stream
 */
class InMemoryHashmap implements JoinHashmap
{
    /**
     * @var array<int|string, StreamProviderArrayIteratorValue[]> $data
     */
    private array $data = [];

    public function set(string|int $key, array $row): void
    {
        $this->data[$key][] = $row;
    }

    public function get(string|int $key): array
    {
        return $this->data[$key] ?? [];
    }

    public function getAll(): array
    {
        return $this->data;
    }

    public function has(string|int $key): bool
    {
        return isset($this->data[$key]);
    }

    public function getStructure(): array
    {
        return array_keys(current($this->data)[0] ?? []);
    }

    public function clear(): void
    {
        $this->data = [];
    }
}
