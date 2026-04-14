<?php

namespace FQL\Traits;

use FQL\Enum;
use FQL\Exception;
use FQL\Exception\InvalidFormatException;
use FQL\Interface\Query;
use FQL\Stream\ArrayStreamProvider;

/**
 * @phpstan-import-type StreamProviderArrayIterator from ArrayStreamProvider
 * @phpstan-import-type StreamProviderArrayIteratorValue from ArrayStreamProvider
 * @phpstan-type JoinAbleArray array{
 *     type: Enum\Join,
 *     table: Query,
 *     alias: string,
 *     leftKey: ?string,
 *     operator: ?Enum\Operator,
 *     rightKey: ?string
 * }
*/
trait Joinable
{
    /**
     * List of joins
     * @var JoinAbleArray[]
     */
    private array $joins = [];
    private bool $joinApplied = false;
    private bool $joinableBlocked = false;

    public function blockJoinable(): void
    {
        $this->joinableBlocked = true;
    }

    public function isJoinableEmpty(): bool
    {
        return $this->joins === [];
    }

    public function join(Query $query, string $alias = ''): Query
    {
        $this->innerJoin($query, $alias);
        return $this;
    }

    public function innerJoin(Query $query, string $alias = ''): Query
    {
        $this->addJoin($query, Enum\Join::INNER, $alias);
        return $this;
    }

    public function leftJoin(Query $query, string $alias = ''): Query
    {
        $this->addJoin($query, Enum\Join::LEFT, $alias);
        return $this;
    }

    public function rightJoin(Query $query, string $alias = ''): Query
    {
        $this->addJoin($query, Enum\Join::RIGHT, $alias);
        return $this;
    }

    public function fullJoin(Query $query, string $alias = ''): Query
    {
        $this->addJoin($query, Enum\Join::FULL, $alias);
        return $this;
    }

    public function on(string $leftKey, Enum\Operator $operator, string $rightKey): Query
    {
        $joinKey = array_key_last($this->joins);
        if ($joinKey === null) {
            throw new Exception\JoinException('Cannot use "ON" condition without a join');
        }

        $join = &$this->joins[$joinKey];
        $join['leftKey'] = $leftKey;
        $join['operator'] = $operator;
        $join['rightKey'] = $rightKey;

        return $this;
    }

    private function asJoin(string $alias): void
    {
        if ($alias === '') {
            throw new Exception\AliasException('JOIN alias cannot be empty');
        }

        $joinKey = array_key_last($this->joins);
        if ($joinKey === null) {
            throw new Exception\AliasException('Cannot use alias without a join');
        }

        $this->joins[$joinKey]['alias'] = $alias;
    }

    private function addJoin(Query $query, Enum\Join $type, string $alias): void
    {
        if ($this->joinableBlocked) {
            throw new Exception\QueryLogicException('JOIN is not allowed in DESCRIBE mode');
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
     * @throws InvalidFormatException
     */
    private function joinsToString(): string
    {
        $joinStrings = [];
        foreach ($this->joins as $join) {
            /** @var Query $table */
            $table = $join['table'];
            if ($join['alias'] === '') {
                throw new Exception\JoinException(
                    'Join alias is required. Use ->as(\'alias\') or pass alias as second parameter to join()'
                );
            }

            if ($table->isSimpleQuery()) {
                $tableString = PHP_EOL . "\t" . $table->provideFileQuery(true);
            } else {
                $tableString = PHP_EOL . sprintf(
                    '(' . PHP_EOL . "\t%s" . PHP_EOL . ')',
                    str_replace(PHP_EOL, PHP_EOL . "\t", (string) $table)
                );
            }

            $alias = ' AS ' . $join['alias'];
            $condition = $join['leftKey'] && $join['rightKey']
                ? sprintf('%s %s %s', $join['leftKey'], $join['operator']->value ?? '', $join['rightKey'])
                : '[No Condition]';

            $joinStrings[] = PHP_EOL . sprintf(
                '%s %s%s ON %s',
                strtoupper($join['type']->value),
                $tableString,
                $alias,
                $condition
            );
        }

        return implode("\n", $joinStrings);
    }
}
