<?php

namespace JQL\Traits;

use JQL\Query;

trait From
{
    private ?string $streamSource = null;

    /**
     * @param string $query
     * @return Query
     */
    public function from(string $query): Query
    {
        $this->streamSource = $query;
        return $this;
    }

    private function getStreamSource(): ?string
    {
        return $this->streamSource;
    }

    private function fromToString(): string
    {
        return "\nFROM " . ($this->streamSource ?? '[json]');
    }
}
