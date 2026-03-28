<?php

namespace FQL\Traits;

use FQL\Exception;

trait Groupable
{
    /**
     * @var string[] $groupByFields
     */
    private array $groupByFields = [];
    private bool $groupableBlocked = false;

    public function blockGroupable(): void
    {
        $this->groupableBlocked = true;
    }

    public function isGroupableEmpty(): bool
    {
        return $this->groupByFields === [];
    }

    public function groupBy(string ...$fields): self
    {
        if ($this->groupableBlocked) {
            throw new Exception\QueryLogicException('GROUP BY is not allowed in DESCRIBE mode');
        }

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
