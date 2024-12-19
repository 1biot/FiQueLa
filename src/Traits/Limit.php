<?php

namespace JQL\Traits;

trait Limit
{
    private ?int $limit = null;
    private ?int $offset = null;

    public function limit(int $limit, ?int $offset = null): self
    {
        $this->limit = $limit;
        if ($offset !== null) {
            $this->offset($offset);
        }
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
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
