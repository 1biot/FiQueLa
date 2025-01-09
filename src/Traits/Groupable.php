<?php

namespace UQL\Traits;

trait Groupable
{
    /**
     * @var string[] $groupByFields
     */
    private array $groupByFields = [];

    public function groupBy(string ...$fields): self
    {
        $this->groupByFields = array_merge($this->groupByFields, $fields);
        return $this;
    }

    public function groupByToString(): string
    {
        return $this->groupByFields !== [] ? sprintf(PHP_EOL . "GROUP BY %s", implode(', ', $this->groupByFields)) : '';
    }
}
