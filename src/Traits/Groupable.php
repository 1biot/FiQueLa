<?php

namespace FQL\Traits;

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
        return $this->groupByFields !== []
            ? PHP_EOL . sprintf('GROUP BY %s', implode(', ', $this->groupByFields))
            : '';
    }
}
