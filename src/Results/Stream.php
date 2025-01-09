<?php

namespace UQL\Results;

use UQL\Enum\Join;
use UQL\Enum\LogicalOperator;
use UQL\Enum\Operator;
use UQL\Enum\Sort;
use UQL\Exceptions\InvalidArgumentException;
use UQL\Functions\AggregateFunction;
use UQL\Functions\BaseFunction;
use UQL\Query\Query;
use UQL\Stream\Csv;
use UQL\Stream\Json;
use UQL\Stream\JsonStream;
use UQL\Stream\Neon;
use UQL\Stream\Xml;
use UQL\Stream\Yaml;
use UQL\Traits\Helpers\NestedArrayAccessor;
use UQL\Traits\Joinable;
use UQL\Traits\Select;

/**
 * @phpstan-type StreamProviderArrayIteratorValue array<int|string, array<int|string, mixed>|scalar|null>
 * @codingStandardsIgnoreStart
 * @phpstan-type StreamProviderArrayIterator \ArrayIterator<int|string, StreamProviderArrayIteratorValue>|\ArrayIterator<int, StreamProviderArrayIteratorValue>|\ArrayIterator<string, StreamProviderArrayIteratorValue>
 * @codingStandardsIgnoreEnd
 *
 * @phpstan-import-type Condition from Query
 * @phpstan-import-type ConditionGroup from Query
 * @phpstan-import-type JoinAbleArray from Joinable
 * @phpstan-import-type SelectedField from Select
 */
class Stream extends ResultsProvider
{
    use NestedArrayAccessor;

    /**
     * @param array<string, SelectedField> $selectedFields
     * @param array<Condition|ConditionGroup> $where
     * @param array<Condition|ConditionGroup> $havings
     * @param JoinAbleArray[] $joins
     * @param string[] $groupByFields
     * @param array<string, Sort> $orderings
     */
    public function __construct(
        private readonly Xml|Json|JsonStream|Yaml|Neon|Csv $stream,
        private readonly array $selectedFields,
        private readonly string $from,
        private readonly array $where,
        private readonly array $havings,
        private readonly array $joins,
        private readonly array $groupByFields,
        private readonly array $orderings,
        private readonly int|null $limit,
        private readonly int|null $offset
    ) {
    }

    public function getIterator(): \Traversable
    {
        yield from $this->buildStream();
    }

    /**
     * @return string[]
     */
    private function getAliasedFields(): array
    {
        $fields = [];
        foreach ($this->selectedFields as $finalField => $fieldData) {
            if ($fieldData['alias']) {
                $fields[] = $finalField;
            }
        }

        return $fields;
    }

    private function applyStreamSource(): \Generator
    {
        $streamSource = $this->from === Query::SELECT_ALL
            ? null
            : $this->from;
        return $this->stream->getStreamGenerator($streamSource);
    }

    /**
     * Applies all defined joins to the dataset.
     *
     * @param \Generator $data The primary data to join.
     * @return \ArrayIterator<int, StreamProviderArrayIteratorValue>|\Generator The joined dataset.
     */
    private function applyJoins(\Generator $data): \ArrayIterator|\Generator
    {
        foreach ($this->joins as $join) {
            $data = $this->applyJoin($data, $join);
        }

        return $data;
    }

    /**
     * Applies a single join to the dataset.
     * @param StreamProviderArrayIterator|\Generator $leftData The left dataset.
     * @param JoinAbleArray $join The join definition.
     * @return \ArrayIterator<int, StreamProviderArrayIteratorValue> The resulting dataset after the join.
     */
    private function applyJoin(iterable $leftData, array $join): \ArrayIterator
    {
        $rightData = iterator_to_array($join['table']->execute()->getProxy()->getIterator());
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

    private function getStream(): \Generator
    {
        $count = 0;
        $currentOffset = 0; // Number of already skipped records
        $groupedData = [];

        $applyLimitAtStream = $this->isLimitable() && !$this->isSortable() && !$this->isGroupable();

        $stream = $this->hasJoin()
            ? $this->applyJoins($this->applyStreamSource())
            : $this->applyStreamSource();

        foreach ($stream as $item) {
            if (!$this->evaluateWhereConditions($item)) {
                continue;
            }

            $resultItem = $this->applySelect($item);
            if ($this->isGroupable()) {
                $groupKey = $this->hasPhase('group') ? $this->createGroupKey($resultItem) : '*';
                $groupedData[$groupKey][] = $item;
                continue;
            }

            if (!$this->evaluateHavingConditions($resultItem)) {
                continue;
            }

            // Offset application
            if ($applyLimitAtStream && $this->offset !== null && $currentOffset < $this->offset) {
                $currentOffset++;
                continue;
            }

            yield $resultItem;

            $count++;
            if ($applyLimitAtStream && $this->limit !== null && $count >= $this->limit) {
                break;
            }
        }

        yield from $this->applyGrouping($groupedData);
    }

    private function buildStream(): \Generator
    {
        if (!$this->isSortable()) {
            return yield from $this->getStream();
        }

        return yield from $this->applyLimit($this->applySorting($this->getStream()));
    }

    /**
     * @param StreamProviderArrayIteratorValue $item
     */
    private function evaluateWhereConditions(array $item): bool
    {
        return $this->evaluateConditions('where', $item, true);
    }

    /**
     * @param StreamProviderArrayIteratorValue $item
     */
    private function evaluateHavingConditions(array $item): bool
    {
        $allowedFields = $this->getAliasedFields();
        $proxyItem = [];
        foreach ($allowedFields as $allowedField) {
            if (!isset($item[$allowedField])) {
                continue;
            }
            $proxyItem[$allowedField] = $item[$allowedField];
        }
        return $this->evaluateConditions('having', $proxyItem, false);
    }

    /**
     * @param StreamProviderArrayIteratorValue $item
     */
    private function evaluateConditions(string $context, array $item, bool $nestingValues): bool
    {
        $evaluatedGroup = $context === 'where' ? $this->where : $this->havings;
        if (empty($evaluatedGroup)) {
            return true;
        }

        return $this->evaluateGroup($item, $evaluatedGroup, $nestingValues);
    }

    /**
     * Evaluate group of conditions
     * @param StreamProviderArrayIteratorValue $item
     * @param array<Condition|ConditionGroup> $conditions
     * @return bool
     */
    private function evaluateGroup(array $item, array $conditions, bool $nestingValues): bool
    {
        $result = null;
        foreach ($conditions as $condition) {
            if (isset($condition['group'])) {
                // Recursive evaluate of nested group
                $groupResult = $this->evaluateGroup($item, $condition['group'], $nestingValues);
            } else {
                // Evaluate of simple condition
                $groupResult = $this->evaluateCondition(
                    $nestingValues
                        ? $this->accessNestedValue($item, $condition['key'])
                        : $item[$condition['key']]
                            ?? throw new InvalidArgumentException(sprintf("Field '%s' not found.", $condition['key'])),
                    $condition['operator'],
                    $condition['value']
                );
            }

            if ($condition['type'] === LogicalOperator::AND) {
                $result = $result === null ? $groupResult : $result && $groupResult;
            } elseif ($condition['type'] === LogicalOperator::OR) {
                $result = $result === null ? $groupResult : $result || $groupResult;
            }
        }

        return $result ?? true; // When we have no more conditions, returns true
    }

    /**
     * Evaluate of simple condition
     * @param mixed $value Concrete value
     * @param Operator $operator Operator
     * @param mixed $operand Comparison value
     * @return bool
     */
    private function evaluateCondition(mixed $value, Operator $operator, mixed $operand): bool
    {
        return $operator->evaluate($value, $operand);
    }

    /**
     * @param array<string|int, mixed> $item
     * @return array<string|int, mixed>
     */
    private function applySelect(array $item): array
    {
        if ($this->selectedFields === []) {
            return $item;
        }

        $result = [];
        foreach ($this->selectedFields as $finalField => $fieldData) {
            $fieldName = $finalField;
            if ($fieldData['function'] instanceof BaseFunction) {
                $result[$fieldName] = $fieldData['function']($item, $result);
                continue;
            }

            $result[$fieldName] = $this->accessNestedValue(
                $item,
                $fieldData['alias'] ? $fieldData['originField'] : $finalField,
                false
            );
        }

        return $result;
    }

    /**
     * @param array<string, StreamProviderArrayIteratorValue[]> $groupedData
     * @return \Generator
     */
    private function applyGrouping(array $groupedData): \Generator
    {
        foreach ($groupedData as $groupKey => $groupItems) {
            $aggregatedItem = $this->applySelect($groupItems[0]);
            $aggregatedItem = $this->applyAggregations($groupItems, $aggregatedItem); // Aggregate grouped items
            if (!$this->evaluateHavingConditions($aggregatedItem)) {
                continue; // Skip groups that do not satisfy HAVING
            }

            yield $aggregatedItem; // Return aggregated result
        }
    }

    /**
     * Aggregates grouped items.
     *
     * @param array<int, array<string, mixed>> $groupItems Grouped items for a single group
     * @param StreamProviderArrayIteratorValue $aggregatedItem Grouped items for a single group
     * @return array<string, mixed> Aggregated result
     */
    private function applyAggregations(array $groupItems, array $aggregatedItem): array
    {
        foreach ($this->selectedFields as $finalField => $fieldData) {
            $fieldName = $finalField;
            if ($fieldData['function'] instanceof AggregateFunction) {
                $aggregatedItem[$fieldName] = $fieldData['function']($groupItems);
            }
        }

        return $aggregatedItem;
    }

    /**
     * @param \Generator<StreamProviderArrayIteratorValue> $iterator
     * @return \Generator<StreamProviderArrayIteratorValue>
     */
    private function applySorting(\Generator $iterator): \Generator
    {
        if ($this->orderings === []) {
            return $iterator;
        }

        $data = iterator_to_array($iterator);
        foreach ($this->orderings as $field => $type) {
            switch ($type) {
                case Sort::ASC:
                    usort($data, fn($a, $b) => ($a[$field] ?? null) <=> ($b[$field] ?? null));
                    break;

                case Sort::DESC:
                    usort($data, fn($a, $b) => ($b[$field] ?? null) <=> ($a[$field] ?? null));
                    break;

                case Sort::NATSORT:
                    usort($data, function ($a, $b) use ($field) {
                        $valA = $a[$field] ?? '';
                        $valB = $b[$field] ?? '';
                        return strnatcmp((string)$valA, (string)$valB);
                    });
                    break;

                case Sort::SHUFFLE:
                    shuffle($data);
                    break;

                default:
                    throw new InvalidArgumentException(sprintf('Unsupported sort type: %s', $type->value));
            }
        }

        $stream = new \ArrayIterator($data);
        foreach ($stream as $item) {
            yield $item;
        }
    }

    private function applyLimit(\Generator $data): \Generator
    {
        $count = 0;
        $currentOffset = 0; // Number of already skipped records
        foreach ($data as $item) {
            if ($this->offset !== null && $currentOffset < $this->offset) {
                $currentOffset++;
                continue;
            }

            yield $item;

            $count++;
            if ($this->limit !== null && $count >= $this->limit) {
                break;
            }
        }
    }

    /**
     * Vytvoří klíč skupiny na základě GROUP BY polí.
     *
     * @param array<string, mixed> $item
     * @return string
     */
    private function createGroupKey(array $item): string
    {
        $keyParts = [];
        foreach ($this->groupByFields as $field) {
            $keyParts[] = $item[$field] ?? 'NULL';
        }
        return implode('|', $keyParts);
    }

    private function hasPhase(string $phase): bool
    {
        $phaseArray = [];
        if ($this->joins !== []) {
            $phaseArray[] = 'join';
        }

        if ($this->groupByFields !== []) {
            $phaseArray[] = 'group';
        }

        if ($this->orderings !== []) {
            $phaseArray[] = 'sort';
        }

        if ($this->limit !== null || $this->offset !== null) {
            $phaseArray[] = 'limit';
        }

        return in_array($phase, $phaseArray, true);
    }

    private function hasJoin(): bool
    {
        return $this->hasPhase('join');
    }

    private function isSortable(): bool
    {
        return $this->hasPhase('sort');
    }

    private function isGroupable(): bool
    {
        foreach ($this->selectedFields as $data) {
            if ($data['function'] instanceof AggregateFunction) {
                return true;
            }
        }

        return $this->hasPhase('group');
    }

    private function isLimitable(): bool
    {
        return $this->hasPhase('limit');
    }
}
