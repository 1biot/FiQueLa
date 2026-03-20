<?php

namespace FQL\Traits;

use FQL\Interface;

trait Unionable
{
    /** @var array<int, array{type: string, query: Interface\Query}> */
    private array $unions = [];

    public function union(Interface\Query $query): Interface\Query
    {
        $this->unions[] = ['type' => 'UNION', 'query' => $query];
        return $this;
    }

    public function unionAll(Interface\Query $query): Interface\Query
    {
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
