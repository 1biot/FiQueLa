<?php

namespace FQL\Client\Dto;

class Pagination
{
    public function __construct(
        public readonly int $page,
        public readonly int $pageCount,
        public readonly int $itemCount,
        public readonly int $itemsPerPage,
        public readonly int $offset
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            page: (int) ($data['page'] ?? 1),
            pageCount: (int) ($data['pageCount'] ?? 1),
            itemCount: (int) ($data['itemCount'] ?? 0),
            itemsPerPage: (int) ($data['itemsPerPage'] ?? 0),
            offset: (int) ($data['offset'] ?? 0)
        );
    }

    public function hasMultiplePages(): bool
    {
        return $this->pageCount > 1;
    }
}
