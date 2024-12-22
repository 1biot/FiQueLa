<?php

namespace UQL\Traits;

use UQL\Query\Query;

trait From
{
    private ?string $from = null;

    /**
     * @param string $query
     * @return Query
     */
    public function from(string $query): Query
    {
        $this->from = $query;
        return $this;
    }

    private function getFrom(): ?string
    {
        return $this->from ?? Query::FROM_ALL;
    }

    private function fromToString(): string
    {
        return "\n" . Query::FROM . ' ' . ($this->from ?? Query::FROM_ALL);
    }
}
