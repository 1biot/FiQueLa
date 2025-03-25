<?php

namespace FQL\Results;

use FQL\Conditions\BaseConditionGroup;
use FQL\Conditions\Condition;
use FQL\Enum;
use FQL\Exception;
use FQL\Functions\Core\AggregateFunction;
use FQL\Functions\Core\BaseFunction;
use FQL\Functions\Core\NoFieldFunction;
use FQL\Interface\Query;
use FQL\Stream\Csv;
use FQL\Stream\Json;
use FQL\Stream\JsonStream;
use FQL\Stream\Neon;
use FQL\Stream\Xml;
use FQL\Stream\Yaml;
use FQL\Traits;

/**
 * @phpstan-type StreamProviderArrayIteratorValue array<int|string, array<int|string, mixed>|scalar|null>
 * @codingStandardsIgnoreStart
 * @phpstan-type StreamProviderArrayIterator \ArrayIterator<int|string, StreamProviderArrayIteratorValue>|\ArrayIterator<int, StreamProviderArrayIteratorValue>|\ArrayIterator<string, StreamProviderArrayIteratorValue>
 * @codingStandardsIgnoreEnd
 *
 * @phpstan-import-type JoinAbleArray from Traits\Joinable
 * @phpstan-import-type SelectedField from Traits\Select
 */
class Stream extends ResultsProvider
{
    use Traits\Helpers\NestedArrayAccessor;

    /** @var array<string, float> */
    private array $avgCache = [];

    /** @var array<string, float> */
    private array $sumCache = [];

    /** @var array<string, float> */
    private array $minCache = [];

    /** @var array<string, float> */
    private array $maxCache = [];

    private ?int $innerCounter = null;

    /**
     * @implements \FQL\Interface\Stream<Xml|Json|JsonStream|Yaml|Neon|Csv>
     * @param array<string, SelectedField> $selectedFields
     * @param JoinAbleArray[] $joins
     * @param string[] $groupByFields
     * @param array<string, Enum\Sort> $orderings
     */
    public function __construct(
        private readonly \FQL\Interface\Stream $stream,
        private readonly bool $distinct,
        private readonly array $selectedFields,
        private readonly string $from,
        private readonly BaseConditionGroup $where,
        private readonly BaseConditionGroup $havings,
        private readonly array $joins,
        private readonly array $groupByFields,
        private readonly array $orderings,
        private readonly int|null $limit,
        private readonly int|null $offset
    ) {
    }

    /**
     * @return \Generator<StreamProviderArrayIteratorValue>
     * @throws Exception\InvalidArgumentException
     * @throws Exception\UnableOpenFileException
     */
    public function getIterator(): \Traversable
    {
        yield from $this->buildStream();
    }

    public function count(): int
    {
        if ($this->innerCounter === null) {
            $this->innerCounter = parent::count();
        }
        return $this->innerCounter;
    }

    public function avg(string $key, int $decimalPlaces = 2): float
    {
        if (!isset($this->avgCache[$key])) {
            $this->avgCache[$key] = $this->sum($key) / $this->count();
        }
        return round($this->avgCache[$key], $decimalPlaces);
    }

    public function sum(string $key): float
    {
        if (!isset($this->sumCache[$key])) {
            $this->sumCache[$key] = parent::sum($key);
        }
        return $this->sumCache[$key];
    }

    public function min(string $key): float
    {
        if (!isset($this->minCache[$key])) {
            $this->minCache[$key] = parent::min($key);
        }
        return $this->minCache[$key];
    }

    public function max(string $key): float
    {
        if (!isset($this->maxCache[$key])) {
            $this->maxCache[$key] = parent::max($key);
        }
        return $this->maxCache[$key];
    }

    /**
     * @return \Generator<StreamProviderArrayIteratorValue>
     */
    private function applyStreamSource(): \Traversable
    {
        $streamSource = $this->from === Query::SELECT_ALL
            ? null
            : $this->from;
        return $this->stream->getStreamGenerator($streamSource);
    }

    /**
     * Applies all defined joins to the dataset.
     * @param \Traversable<StreamProviderArrayIteratorValue> $data The primary data to join.
     * @return \Traversable<StreamProviderArrayIteratorValue> The joined dataset.
     */
    private function applyJoins(\Traversable $data): \Traversable
    {
        foreach ($this->joins as $join) {
            $data = $this->applyJoin($data, $join);
        }
        return $data;
    }

    /**
     * Applies a single join to the dataset.
     * @param \Traversable<StreamProviderArrayIteratorValue> $leftData The left dataset.
     * @param JoinAbleArray $join The join definition.
     * @return \Traversable<StreamProviderArrayIteratorValue> The resulting dataset after the join.
     */
    private function applyJoin(\Traversable $leftData, array $join): \Traversable
    {
        $rightData = $join['table']->execute(self::class)->getIterator();
        $alias = $join['alias'];
        $leftKey = $join['leftKey'];
        $rightKey = $join['rightKey'];
        $operator = $join['operator'] ?? Enum\Operator::EQUAL;
        $type = $join['type'];

        // Build a hashmap for the right table
        $hashmap = [];
        foreach ($rightData as $row) {
            $key = $row[$rightKey] ?? null;
            if ($key !== null) {
                $hashmap[$key][] = $row;
            }
        }

        // Get the structure of the right table from the hashmap
        $rightStructure = array_keys(current($hashmap)[0] ?? []);
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
                        yield $joinedRow;
                    }
                }
            } elseif ($type === Enum\Join::LEFT) {
                // Handle LEFT JOIN (no match)
                $nullRow = array_fill_keys($rightStructure, null);
                /** @var StreamProviderArrayIteratorValue $joinedRow */
                $joinedRow = $alias
                    ? array_merge($leftRow, [$alias => $nullRow])
                    : array_merge($leftRow, $nullRow);

                yield $joinedRow;
            }

            if ($leftKeyValue !== null && isset($hashmap[$leftKeyValue])) {
                unset($hashmap[$leftKeyValue]); // remove the used key from the hashmap
            }
        }
    }

    /**
     * @implements \Traversable<StreamProviderArrayIteratorValue>
     * @return \Generator<StreamProviderArrayIteratorValue>
     * @throws Exception\UnableOpenFileException
     * @throws Exception\InvalidArgumentException
     */
    private function buildStream(): \Traversable
    {
        $stream = $this->hasJoin()
            ? $this->applyJoins($this->applyStreamSource())
            : $this->applyStreamSource();

        if ($this->isGroupable()) {
            if (!$this->isSortable()) {
                return yield from $this->applyGrouping($stream); // apply limit and offset automatically
            }

            $stream = $this->applyGrouping($stream);
        } else {
            $stream = $this->applyBaseStream($stream);
        }

        if (!$this->isSortable()) {
            return yield from $stream; // apply limit and offset automatically
        } elseif (!$this->isLimitable()) {
            return yield from $this->applySorting($stream);
        }

        return yield from $this->applyLimit($this->applySorting($stream));
    }

    /**
     * @param non-empty-lowercase-string $context
     * @param StreamProviderArrayIteratorValue $item
     */
    private function evaluateConditions(string $context, array $item): bool
    {
        $evaluateGroup = $context === Condition::WHERE ? $this->where : $this->havings;
        return $evaluateGroup->evaluate($item, $context === Condition::WHERE);
    }

    /**
     * @param array<string|int, mixed> $item
     * @return array<string|int, mixed>
     * @throws Exception\InvalidArgumentException
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
            } elseif ($fieldData['function'] instanceof NoFieldFunction) {
                $result[$fieldName] = $fieldData['function']();
                continue;
            } elseif ($fieldData['function'] instanceof AggregateFunction) {
                $result[$finalField] = $item[$finalField] ?? null;
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
     * @param \Traversable<StreamProviderArrayIteratorValue> $stream
     * @return \Traversable<StreamProviderArrayIteratorValue>
     * @throws Exception\InvalidArgumentException
     */
    private function applyBaseStream(\Traversable $stream): \Traversable
    {
        $count = 0;
        $currentOffset = 0; // Number of already skipped records
        $applyLimitAtStream = $this->isLimitable() && !$this->isSortable();

        foreach ($stream as $item) {
            if (!$this->evaluateConditions(Condition::WHERE, $item)) {
                continue;
            }

            $resultItem = $this->applySelect($item);
            if (!$this->evaluateConditions(Condition::HAVING, $resultItem)) {
                continue; // Skip resultItem that do not satisfy HAVING
            }

            if ($this->distinct) {
                $hash = md5(serialize($resultItem));
                if (isset($seen[$hash])) {
                    continue;
                }
                $seen[$hash] = true;
            }

            // Offset application
            if ($applyLimitAtStream && $this->offset !== null && $currentOffset < $this->offset) {
                $currentOffset++;
                continue;
            }

            yield $resultItem; // Return result

            $count++;
            if ($applyLimitAtStream && $this->limit !== null && $count >= $this->limit) {
                break;
            }
        }
    }

    /**
     * @param \Traversable<StreamProviderArrayIteratorValue> $stream
     * @return \Generator<StreamProviderArrayIteratorValue>
     * @throws Exception\InvalidArgumentException
     */
    private function applyGrouping(\Traversable $stream): \Traversable
    {
        $groupedData = [];
        $groupKey = Query::SELECT_ALL;
        foreach ($stream as $item) {
            if (!$this->evaluateConditions(Condition::WHERE, $item)) {
                continue;
            } elseif ($this->hasPhase('group')) {
                $groupKey = $this->createGroupKey($item);
            }

            $groupedData[$groupKey][] = $item;
        }

        if ($groupKey === Query::SELECT_ALL) {
            // Aggregate grouped items
            $aggregatedItem = $this->applyAggregations($groupedData[Query::SELECT_ALL]);
            if ($this->evaluateConditions(Condition::HAVING, $aggregatedItem)) {
                return yield $aggregatedItem;
            }
        }

        $count = 0;
        $currentOffset = 0; // Number of already skipped records
        $applyLimitAtStream = $this->isLimitable() && !$this->isSortable();
        foreach ($groupedData as $groupItems) {
            // Aggregate grouped items
            $aggregatedItem = $this->applyAggregations($groupItems);
            if (!$this->evaluateConditions(Condition::HAVING, $aggregatedItem)) {
                continue; // Skip groups that do not satisfy HAVING
            }

            // Offset application
            if ($applyLimitAtStream && $this->offset !== null && $currentOffset < $this->offset) {
                $currentOffset++;
                continue;
            }

            yield $aggregatedItem; // Return aggregated result

            $count++;
            if ($applyLimitAtStream && $this->limit !== null && $count >= $this->limit) {
                break;
            }
        }
    }

    /**
     * Aggregates grouped items.
     *
     * @param array<int, array<string, mixed>> $groupItems Grouped items for a single group
     * @return array<string, mixed> Aggregated result
     */
    private function applyAggregations(array $groupItems): array
    {
        $aggregatedItem = $groupItems[0];
        foreach ($this->selectedFields as $finalField => $fieldData) {
            if ($fieldData['function'] instanceof AggregateFunction) {
                $aggregatedItem[$finalField] = $fieldData['function']($groupItems);
            }
        }

        return $this->applySelect($aggregatedItem);
    }

    /**
     * @param \Generator<StreamProviderArrayIteratorValue> $iterator
     * @return \Generator<StreamProviderArrayIteratorValue>
     * @throws Exception\SortException
     */
    private function applySorting(\Generator $iterator): \Generator
    {
        if ($this->orderings === []) {
            return $iterator;
        }

        $data = iterator_to_array($iterator);
        usort($data, function ($a, $b): int {
            foreach ($this->orderings as $field => $type) {
                $valA = $a[$field] ?? null;
                $valB = $b[$field] ?? null;

                $cmp = match ($type) {
                    Enum\Sort::ASC => ($valA <=> $valB),
                    Enum\Sort::DESC => ($valB <=> $valA),
                };

                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            return 0;
        });

        foreach ($data as $item) {
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
     * Creates a group key based on GROUP BY fields.
     * @param array<string, mixed> $item
     * @return string
     */
    private function createGroupKey(array $item): string
    {
        $keyParts = [];
        foreach ($this->groupByFields as $field) {
            $keyParts[] = $this->accessNestedValue($item, $field, false) ?? '';
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

    public function hasJoin(): bool
    {
        return $this->hasPhase('join');
    }

    public function isSortable(): bool
    {
        return $this->hasPhase('sort');
    }

    public function isGroupable(): bool
    {
        foreach ($this->selectedFields as $data) {
            if ($data['function'] instanceof AggregateFunction) {
                return true;
            }
        }

        return $this->hasPhase('group');
    }

    public function isLimitable(): bool
    {
        return $this->hasPhase('limit');
    }
}
