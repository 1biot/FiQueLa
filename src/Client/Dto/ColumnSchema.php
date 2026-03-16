<?php

namespace FQL\Client\Dto;

class ColumnSchema
{
    /**
     * @param string $column
     * @param array<string, int|float> $types
     * @param int $totalRows
     * @param int $totalTypes
     * @param string|null $dominant
     * @param bool $suspicious
     * @param float $confidence
     * @param float $completeness
     * @param bool $constant
     * @param bool $isEnum
     * @param bool $isUnique
     */
    public function __construct(
        public readonly string $column,
        public readonly array $types,
        public readonly int $totalRows,
        public readonly int $totalTypes,
        public readonly ?string $dominant,
        public readonly bool $suspicious,
        public readonly float $confidence,
        public readonly float $completeness,
        public readonly bool $constant,
        public readonly bool $isEnum,
        public readonly bool $isUnique
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            column: (string) ($data['column'] ?? ''),
            types: is_array($data['types'] ?? null) ? $data['types'] : [],
            totalRows: (int) ($data['totalRows'] ?? 0),
            totalTypes: (int) ($data['totalTypes'] ?? 0),
            dominant: $data['dominant'] ?? null,
            suspicious: (bool) ($data['suspicious'] ?? false),
            confidence: (float) ($data['confidence'] ?? 0.0),
            completeness: (float) ($data['completeness'] ?? 0.0),
            constant: (bool) ($data['constant'] ?? false),
            isEnum: (bool) ($data['isEnum'] ?? false),
            isUnique: (bool) ($data['isUnique'] ?? false)
        );
    }
}
