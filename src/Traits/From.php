<?php

namespace JQL\Traits;

trait From
{
    private ?string $streamSource = null;

    /**
     * @param string $query
     * @return self
     */
    public function from(string $query): self
    {
        $this->streamSource = $query;
        return $this;
    }

    public function fromToString(): string
    {
        return "\nFROM " . ($this->streamSource ?? '[json]');
    }
}
