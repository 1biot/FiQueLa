<?php

namespace FQL\Traits;

use FQL\Exception;
use FQL\Interface;

trait Unionable
{
    /** @var array<int, array{type: string, query: Interface\Query}> */
    private array $unions = [];
    private bool $unionableBlocked = false;

    public function blockUnionable(): void
    {
        $this->unionableBlocked = true;
    }

    public function isUnionableEmpty(): bool
    {
        return $this->unions === [];
    }

    public function union(Interface\Query $query): Interface\Query
    {
        if ($this->unionableBlocked) {
            throw new Exception\QueryLogicException('UNION is not allowed in DESCRIBE mode');
        }

        $this->unions[] = ['type' => 'UNION', 'query' => $query];
        return $this;
    }

    public function unionAll(Interface\Query $query): Interface\Query
    {
        if ($this->unionableBlocked) {
            throw new Exception\QueryLogicException('UNION is not allowed in DESCRIBE mode');
        }

        $this->unions[] = ['type' => 'UNION ALL', 'query' => $query];
        return $this;
    }

    private function unionsToString(): string
    {
        $sql = '';
        foreach ($this->unions as $union) {
            $sql .= PHP_EOL . $union['type'] . PHP_EOL . $union['query'];
        }
        return $sql;
    }
}
