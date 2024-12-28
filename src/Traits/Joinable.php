<?php

namespace UQL\Traits;

use UQL\Enum\Join;
use UQL\Enum\Operator;
use UQL\Exceptions\InvalidArgumentException;
use UQL\Query\Provider;
use UQL\Query\Query;

/**
 * @phpstan-import-type StreamProviderArrayIterator from Provider
 * @phpstan-import-type StreamProviderArrayIteratorValue from Provider
 * @codingStandardsIgnoreStart
 * @phpstan-type JoinAbleArray array{type: Join, table: Query, alias: string, leftKey: ?string, operator: ?Operator, rightKey: ?string}
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
        $this->addJoin($query, Join::INNER, $alias);
        return $this;
    }

    public function leftJoin(Query $query, string $alias): Query
    {
        $this->addJoin($query, Join::LEFT, $alias);
        return $this;
    }

    public function on(string $leftKey, Operator $operator, string $rightKey): Query
    {
        $joinKey = array_key_last($this->joins);
        if ($joinKey === null) {
            throw new InvalidArgumentException('Cannot use alias without a field');
        }

        $join = &$this->joins[$joinKey];
        $join['leftKey'] = $leftKey;
        $join['operator'] = $operator;
        $join['rightKey'] = $rightKey;

        return $this;
    }

    private function addJoin(Query $query, Join $type, string $alias): void
    {
        if ($alias === '') {
            throw new InvalidArgumentException('Alias cannot be empty');
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
     * Applies all defined joins to the dataset.
     *
     * @param StreamProviderArrayIterator|\Generator $data The primary data to join.
     * @return StreamProviderArrayIterator|\Generator The joined dataset.
     */
    private function applyJoins(iterable $data): \ArrayIterator|\Generator
    {
        if ($this->joinApplied) {
            return $data;
        }

        foreach ($this->joins as $join) {
            $data = $this->applyJoin($data, $join);
        }

        $this->joinApplied = true;
        return $data;
    }

    /**
     * Applies a single join to the dataset.
     *
     * @param StreamProviderArrayIterator|\Generator $leftData The left dataset.
     * @param JoinAbleArray $join The join to apply.
     * @return StreamProviderArrayIterator The resulting dataset after the join.
     */
    private function applyJoin(iterable $leftData, array $join): \ArrayIterator
    {
        $rightData = iterator_to_array($join['table']->fetchAll());
        $alias = $join['alias'];
        $leftKey = $join['leftKey'];
        $rightKey = $join['rightKey'];
        $operator = $join['operator'] ?? Operator::EQUAL;
        $type = $join['type'];

        // Build a hashmap for the right table
        $hashmap = [];
        foreach ($rightData as $row) {
            $key = $row[$rightKey] ?? null;
            if ($key !== null) {
                $hashmap[$key][] = $row;
            }
        }

        $result = [];
        foreach ($leftData as $leftRow) {
            $leftKeyValue = $leftRow[$leftKey] ?? null;

            if ($leftKeyValue !== null && isset($hashmap[$leftKeyValue])) {
                // Handle matches (n * n)
                foreach ($hashmap[$leftKeyValue] as $rightRow) {
                    /** @var StreamProviderArrayIteratorValue $joinedRow */
                    $joinedRow = $alias
                        ? array_merge($leftRow, [$alias => $rightRow])
                        : array_merge($leftRow, $rightRow);

                    if ($operator->evaluate($leftKeyValue, $rightRow[$rightKey] ?? null)) {
                        $result[] = $joinedRow;
                    }
                }
            } elseif ($type === Join::LEFT) {
                // Handle LEFT JOIN (no match)
                $nullRow = array_fill_keys(array_keys($rightData[0] ?? []), null);
                /** @var StreamProviderArrayIteratorValue $joinedRow */
                $joinedRow = $alias
                    ? array_merge($leftRow, [$alias => $nullRow])
                    : array_merge($leftRow, $nullRow);

                $result[] = $joinedRow;
            }
        }

        return new \ArrayIterator($result);
    }

    /**
     * Generates a SQL-like string for all defined joins.
     *
     * @return string The SQL-like representation of joins.
     */
    private function joinsToString(): string
    {
        $joinStrings = [];

        foreach ($this->joins as $join) {
            $tableString = sprintf("\n(\n%s\n)", $join['table']->test());
            $alias = $join['alias'] ? ' AS ' . $join['alias'] : '';
            $condition = $join['leftKey'] && $join['rightKey']
                ? sprintf('%s %s %s', $join['leftKey'], $join['operator']->value, $join['rightKey'])
                : '[No Condition]';

            $joinStrings[] = sprintf(
                "\n%s %s%s ON %s",
                strtoupper($join['type']->value),
                $tableString,
                $alias,
                $condition
            );
        }

        return implode("\n", $joinStrings);
    }
}
