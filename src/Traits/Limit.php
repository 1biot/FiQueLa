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
        return $this->limit ? ("\n" . Query::LIMIT . ' ' . $this->limit) : '';
    }

    private function offsetToString(): string
    {
        return $this->offset ? "\n" . Query::OFFSET . ' ' . $this->offset : '';
    }
}
