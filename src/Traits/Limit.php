<?php

namespace UQL\Traits;

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

    private function limitToString(): string
    {
        return $this->limit ? (PHP_EOL . Query::LIMIT . ' ' . $this->limit) : '';
    }

    private function offsetToString(): string
    {
        return $this->offset ? PHP_EOL .  Query::OFFSET . ' ' . $this->offset : '';
    }
}
