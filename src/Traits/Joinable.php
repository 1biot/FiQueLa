<?php

namespace UQL\Traits;

use UQL\Enum;
use UQL\Exceptions;
use UQL\Query\Query;
use UQL\Stream\ArrayStreamProvider;

/**
 * @phpstan-import-type StreamProviderArrayIterator from ArrayStreamProvider
 * @phpstan-import-type StreamProviderArrayIteratorValue from ArrayStreamProvider
 * @codingStandardsIgnoreStart
 * @phpstan-type JoinAbleArray array{
 *     type: Enum\Join,
 *     table: Query,
 *     alias: string,
 *     leftKey: ?string,
 *     operator: ?Enum\Operator,
 *     rightKey: ?string
 * }
 * @codingStandardsIgnoreEnd
*/
trait Joinable
{
    /**
     * List of joins
     * @var JoinAbleArray[]
     */
    private array $joins = [];
    private bool $joinApplied = false;

    public function join(Query $query, string $alias): Query
    {
        $this->innerJoin($query, $alias);
        return $this;
    }

    public function innerJoin(Query $query, string $alias): Query
    {
        $this->addJoin($query, Enum\Join::INNER, $alias);
        return $this;
    }

    public function leftJoin(Query $query, string $alias): Query
    {
        $this->addJoin($query, Enum\Join::LEFT, $alias);
        return $this;
    }

    public function on(string $leftKey, Enum\Operator $operator, string $rightKey): Query
    {
        $joinKey = array_key_last($this->joins);
        if ($joinKey === null) {
            throw new Exceptions\JoinException('Cannot use "ON" condition without a join');
        }

        $join = &$this->joins[$joinKey];
        $join['leftKey'] = $leftKey;
        $join['operator'] = $operator;
        $join['rightKey'] = $rightKey;

        return $this;
    }

    private function addJoin(Query $query, Enum\Join $type, string $alias): void
    {
        if ($alias === '') {
            throw new Exceptions\JoinException('Set alias for join');
        }

        $this->joins[] = [
            'type' => $type,
            'table' => $query,
            'alias' => $alias,
            'leftKey' => null,
            'operator' => null,
            'rightKey' => null,
        ];
    }

    /**
     * Generates a SQL-like string for all defined joins.
     * @return string The SQL-like representation of joins.
     */
    private function joinsToString(): string
    {
        $joinStrings = [];

        foreach ($this->joins as $join) {
            $tableString = sprintf(
                PHP_EOL . '(' . PHP_EOL . "\t%s" . PHP_EOL . ')',
                str_replace(PHP_EOL, PHP_EOL . "\t", $join['table']->test())
            );
            $alias = $join['alias'] ? ' AS ' . $join['alias'] : '';
            $condition = $join['leftKey'] && $join['rightKey']
                ? sprintf('%s %s %s', $join['leftKey'], $join['operator']->value, $join['rightKey'])
                : '[No Condition]';

            $joinStrings[] = sprintf(
                PHP_EOL . "%s %s%s ON %s",
                strtoupper($join['type']->value),
                $tableString,
                $alias,
                $condition
            );
        }

        return implode("\n", $joinStrings);
    }
}
