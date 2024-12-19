<?php

namespace JQL\Traits;

use JQL\Query;

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
        return $this->limit ? "\nLIMIT " . $this->limit : '';
    }

    private function offsetToString(): string
    {
        return $this->offset ? "\nOFFSET " . $this->offset : '';
    }
}
