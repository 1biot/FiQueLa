<?php

namespace UQL\Traits;

use UQL\Helpers\ArrayHelper;
use UQL\Query\Query;

trait Limit
{
    private ?int $limit = null;
    private ?int $offset = null;

    public function limit(int $limit, ?int $offset = null): Query
    {
        $this->limit = $limit;
        if ($offset !== null) {
            $this->offset($offset);
        }
        return $this;
    }

    public function offset(int $offset): Query
    {
        $this->offset = $offset;
        return $this;
    }

    public function page(int $page, int $perPage = Query::PER_PAGE_DEFAULT): Query
    {
        return $this->offset(($page - 1) * $perPage)
            ->limit($perPage);
    }

    private function getLimit(): ?int
    {
        return $this->limit;
    }

    private function getOffset(): ?int
    {
        return $this->offset;
    }

    /**
     * @param class-string|null $dto
     */
    private function applyLimit(\Generator $data, ?string $dto = null): \Generator
    {
        $count = 0;
        $currentOffset = 0; // Number of already skipped records
        foreach ($data as $item) {
            if ($this->getOffset() !== null && $currentOffset < $this->getOffset()) {
                $currentOffset++;
                continue;
            }

            if ($dto !== null) {
                $item = ArrayHelper::mapArrayToObject($item, $dto);
            }

            yield $item;

            $count++;
            if ($this->getLimit() !== null && $count >= $this->getLimit()) {
                break;
            }
        }
    }

    private function limitToString(): string
    {
        return $this->limit ? ("\n" . Query::LIMIT . ' ' . $this->limit) : '';
    }

    private function offsetToString(): string
    {
        return $this->offset ? "\n" . Query::OFFSET . ' ' . $this->offset : '';
    }
}
