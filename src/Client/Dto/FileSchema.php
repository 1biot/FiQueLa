<?php

namespace FQL\Client\Dto;

class FileSchema
{
    /**
     * @param string $uuid
     * @param string $name
     * @param string|null $encoding
     * @param string $type
     * @param int $size
     * @param string|null $delimiter
     * @param string|null $query
     * @param int $count
     * @param ColumnSchema[] $columns
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $name,
        public readonly ?string $encoding,
        public readonly string $type,
        public readonly int $size,
        public readonly ?string $delimiter,
        public readonly ?string $query,
        public readonly int $count,
        public readonly array $columns
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $columns = [];
        if (isset($data['columns']) && is_array($data['columns'])) {
            foreach ($data['columns'] as $column) {
                if (is_array($column)) {
                    $columns[] = ColumnSchema::fromArray($column);
                }
            }
        }

        return new self(
            uuid: (string) ($data['uuid'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            encoding: $data['encoding'] ?? null,
            type: (string) ($data['type'] ?? ''),
            size: (int) ($data['size'] ?? 0),
            delimiter: $data['delimiter'] ?? null,
            query: $data['query'] ?? null,
            count: (int) ($data['count'] ?? 0),
            columns: $columns
        );
    }
}
