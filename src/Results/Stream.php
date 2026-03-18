<?php

namespace FQL\Results;

use FQL\Conditions\BaseConditionGroup;
use FQL\Conditions\Condition;
use FQL\Enum;
use FQL\Exception;
use FQL\Functions\Core\AggregateFunction;
use FQL\Functions\Core\BaseFunction;
use FQL\Functions\Core\BaseFunctionByReference;
use FQL\Functions\Core\NoFieldFunction;
use FQL\Interface\JoinHashmap;
use FQL\Interface\Query;
use FQL\Stream\Csv;
use FQL\Stream\Json;
use FQL\Stream\JsonStream;
use FQL\Stream\Neon;
use FQL\Stream\Xml;
use FQL\Stream\Yaml;
use FQL\Traits;
use FQL\Traits\Helpers\EnhancedNestedArrayAccessor;
use FQL\Utils\InMemoryHashmap;

/**
 * @phpstan-type StreamProviderArrayIteratorValue array<int|string, array<int|string, mixed>|scalar|null>
 * @codingStandardsIgnoreStart
 * @phpstan-type StreamProviderArrayIterator \ArrayIterator<int|string, StreamProviderArrayIteratorValue>|\ArrayIterator<int, StreamProviderArrayIteratorValue>|\ArrayIterator<string, StreamProviderArrayIteratorValue>
 * @codingStandardsIgnoreEnd
 *
 * @phpstan-import-type JoinAbleArray from Traits\Joinable
 * @phpstan-import-type SelectedField from Traits\Select
 * @phpstan-import-type ExplainResultArray from Traits\Explain
 */
class Stream extends ResultsProvider
{
    use Traits\Helpers\EnhancedNestedArrayAccessor;
    use Traits\Helpers\StringOperations;

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
     * @param string[] $excludedFields
     * @param JoinAbleArray[] $joins
     * @param string[] $groupByFields
     * @param array<string, Enum\Sort> $orderings
     */
    public function __construct(
        private readonly \FQL\Interface\Stream $stream,
        private readonly bool $distinct,
        private readonly array $selectedFields,
        private readonly array $excludedFields,
        private readonly string $from,
        private readonly BaseConditionGroup $where,
        private readonly BaseConditionGroup $havings,
        private readonly array $joins,
        private readonly array $groupByFields,
        private readonly array $orderings,
        private readonly int|null $limit,
        private readonly int|null $offset,
        private JoinHashMap $joinHashMap = new InMemoryHashmap()
    ) {
    }

    public function setJoinHashMap(JoinHashMap $joinHashMap): void
    {
        $this->joinHashMap = $joinHashMap;
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
     * @return array<int, ExplainResultArray>
     */
    public function explain(bool $analyze = false): array
    {
        $rows = $analyze
            ? $this->buildExplainAnalyzeRows()
            : $this->buildExplainPlanRows();

        return $this->finalizeExplainRows($rows);
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
     * @return array<int, ExplainResultArray>
     */
    private function buildExplainPlanRows(): array
    {
        $rows = [];
        $rows[] = $this->createExplainRow('stream', $this->getStreamNote(), false, false);

        if ($this->hasJoin()) {
            foreach ($this->joins as $join) {
                $rows[] = $this->createExplainRow('join', $this->getJoinNote($join), false, true);
            }
        }

        if ($this->hasWhereConditions()) {
            $rows[] = $this->createExplainRow('where', $this->getWhereNote(), false, true);
        }

        if ($this->isGroupable()) {
            $rows[] = $this->createExplainRow('group', $this->getGroupNote(), false, true);
        }

        if ($this->hasHavingConditions()) {
            $rows[] = $this->createExplainRow('having', $this->getHavingNote(), false, true);
        }

        if ($this->isSortable()) {
            $rows[] = $this->createExplainRow('sort', $this->getSortNote(), false, true);
        }

        if ($this->isLimitable()) {
            $rows[] = $this->createExplainRow(
                'limit',
                $this->getLimitNote($this->isLimitAppliedInStream()),
                false,
                true
            );
        }

        return $rows;
    }

    /**
     * @return array<int, ExplainResultArray>
     */
    private function buildExplainAnalyzeRows(): array
    {
        $rows = [];
        $streamRow = $this->addExplainRow($rows, 'stream', $this->getStreamNote(), true, false);

        $stream = $this->applyStreamSourceExplain($rows, $streamRow);

        if ($this->hasJoin()) {
            foreach ($this->joins as $join) {
                $joinRow = $this->addExplainRow($rows, 'join', $this->getJoinNote($join), true, true);
                $stream = $this->applyJoinExplain($stream, $rows, $joinRow, $join);
            }
        }

        $whereRow = $this->hasWhereConditions()
            ? $this->addExplainRow($rows, 'where', $this->getWhereNote(), true, true)
            : null;

        $havingRow = $this->hasHavingConditions()
            ? $this->addExplainRow($rows, 'having', $this->getHavingNote(), true, true)
            : null;

        $limitAtStream = $this->isLimitAppliedInStream();
        $limitRow = $this->isLimitable() && $limitAtStream
            ? $this->addExplainRow($rows, 'limit', $this->getLimitNote(true), true, true)
            : null;

        if ($this->isGroupable()) {
            $groupRow = $this->addExplainRow($rows, 'group', $this->getGroupNote(), true, true);
            $stream = $this->applyGroupingExplain(
                $stream,
                $rows,
                $whereRow,
                $groupRow,
                $havingRow,
                $limitRow,
                $limitAtStream
            );
        } else {
            $stream = $this->applyBaseStreamExplain(
                $stream,
                $rows,
                $whereRow,
                $havingRow,
                $limitRow,
                $limitAtStream
            );
        }

        if ($this->isSortable()) {
            $sortRow = $this->addExplainRow($rows, 'sort', $this->getSortNote(), true, true);
            $stream = $this->applySortingExplain($stream, $rows, $sortRow);

            if ($this->isLimitable()) {
                $limitRow = $this->addExplainRow($rows, 'limit', $this->getLimitNote(false), true, true);
                $stream = $this->applyLimitExplain($stream, $rows, $limitRow);
            }
        }

        foreach ($stream as $item) {
            unset($item);
        }

        return $rows;
    }

    private function hasWhereConditions(): bool
    {
        return count($this->where) > 0;
    }

    private function hasHavingConditions(): bool
    {
        return count($this->havings) > 0;
    }

    private function isLimitAppliedInStream(): bool
    {
        return $this->isLimitable() && !$this->isSortable();
    }

    private function getStreamNote(): string
    {
        $source = $this->stream->provideSource();
        if ($this->from === Query::SELECT_ALL || $this->from === '') {
            return sprintf('read source %s', $source);
        }

        return sprintf('read source %s.%s', $source, $this->from);
    }

    /**
     * @param JoinAbleArray $join
     */
    private function getJoinNote(array $join): string
    {
        $aliasNote = ' AS ' . $join['alias'];
        $operator = ($join['operator'] ?? Enum\Operator::EQUAL)->value;
        $leftKey = $join['leftKey'] ?? '';
        $rightKey = $join['rightKey'] ?? '';
        $condition = $leftKey !== '' && $rightKey !== ''
            ? sprintf('%s %s %s', $leftKey, $operator, $rightKey)
            : '[No Condition]';

        $source = (string) $join['table']->provideFileQuery();
        return sprintf('%s%s ON %s (%s)', $join['type']->value, $aliasNote, $condition, $source);
    }

    private function getWhereNote(): string
    {
        return 'filtered (where)';
    }

    private function getHavingNote(): string
    {
        return 'filtered (having)';
    }

    private function getGroupNote(): string
    {
        if ($this->groupByFields !== []) {
            return sprintf('group by %s', implode(', ', $this->groupByFields));
        }

        return 'aggregate';
    }

    private function getSortNote(): string
    {
        if ($this->orderings === []) {
            return 'order by';
        }

        $parts = [];
        foreach ($this->orderings as $field => $direction) {
            $parts[] = sprintf('%s %s', $field, $direction->value);
        }

        return sprintf('order by %s', implode(', ', $parts));
    }

    private function getLimitNote(bool $streamLimit): string
    {
        $parts = [];
        if ($this->offset !== null) {
            $parts[] = sprintf('offset %d', $this->offset);
        }
        if ($this->limit !== null) {
            $parts[] = sprintf('limit %d', $this->limit);
        }

        if ($parts === []) {
            $parts[] = 'limit';
        }

        $note = implode(', ', $parts);
        return $streamLimit ? $note . ' (stream)' : $note;
    }

    /**
     * @param array<int, ExplainResultArray> $rows
     * @return array<int, ExplainResultArray>
     */
    private function finalizeExplainRows(array $rows): array
    {
        $totalTime = 0.0;
        foreach ($rows as $row) {
            if ($row['time_ms'] !== null) {
                $totalTime += (float) $row['time_ms'];
            }
        }

        foreach ($rows as $index => $row) {
            if ($row['rows_in'] !== null && $row['rows_out'] !== null) {
                $rows[$index]['filtered'] = $row['rows_in'] - $row['rows_out'];
            }

            if ($row['time_ms'] !== null) {
                $duration = $totalTime > 0.0
                    ? ((float) $row['time_ms'] / $totalTime) * 100
                    : 0.0;
                $rows[$index]['duration_pct'] = round($duration, 3);
            }

            if ($row['time_ms'] !== null) {
                $rows[$index]['time_ms'] = round((float) $row['time_ms'], 3);
            }
        }

        return $rows;
    }

    /**
     * @return ExplainResultArray
     */
    private function createExplainRow(string $phase, string $note, bool $withMetrics, bool $hasInput): array
    {
        return [
            'phase' => $phase,
            'rows_in' => $withMetrics && $hasInput ? 0 : null,
            'rows_out' => $withMetrics ? 0 : null,
            'filtered' => null,
            'time_ms' => $withMetrics ? 0.0 : null,
            'duration_pct' => null,
            'note' => $note,
        ];
    }

    /**
     * @param array<int, ExplainResultArray> $rows
     */
    private function addExplainRow(
        array &$rows,
        string $phase,
        string $note,
        bool $withMetrics,
        bool $hasInput
    ): int {
        $rows[] = $this->createExplainRow($phase, $note, $withMetrics, $hasInput);
        return array_key_last($rows);
    }

    /**
     * @param array<int, ExplainResultArray> $rows
     * @param int $rowIndex
     * @return \Traversable<StreamProviderArrayIteratorValue>
     */
    private function applyStreamSourceExplain(array &$rows, int $rowIndex): \Traversable
    {
        $start = microtime(true);
        foreach ($this->applyStreamSource() as $item) {
            $rows[$rowIndex]['rows_out']++;
            yield $item;
        }
        $rows[$rowIndex]['time_ms'] = $this->elapsedMs($start);
    }

    /**
     * @param \Traversable<StreamProviderArrayIteratorValue> $leftData
     * @param array<int, ExplainResultArray> $rows
     * @param JoinAbleArray $join
     * @return \Traversable<StreamProviderArrayIteratorValue>
     */
    private function applyJoinExplain(\Traversable $leftData, array &$rows, int $rowIndex, array $join): \Traversable
    {
        $input = $this->wrapInputWithCounter($leftData, $rows, $rowIndex);
        $start = microtime(true);
        foreach ($this->applyJoin($input, $join) as $item) {
            $rows[$rowIndex]['rows_out']++;
            yield $item;
        }
        $rows[$rowIndex]['time_ms'] = $this->elapsedMs($start);
    }

    /**
     * @param \Traversable<StreamProviderArrayIteratorValue> $stream
     * @param array<int, ExplainResultArray> $rows
     * @return \Generator<StreamProviderArrayIteratorValue>
     */
    private function applyBaseStreamExplain(
        \Traversable $stream,
        array &$rows,
        ?int $whereRow,
        ?int $havingRow,
        ?int $limitRow,
        bool $limitAtStream
    ): \Traversable {
        $count = 0;
        $currentOffset = 0;
        $whereTime = 0.0;
        $havingTime = 0.0;
        $limitTime = 0.0;
        $seen = [];

        foreach ($stream as $item) {
            if ($whereRow !== null) {
                $rows[$whereRow]['rows_in']++;
                $start = microtime(true);
                $whereResult = $this->evaluateConditions(Condition::WHERE, $item);
                $whereTime += $this->elapsedMs($start);
                if (!$whereResult) {
                    continue;
                }
                $rows[$whereRow]['rows_out']++;
            } elseif (!$this->evaluateConditions(Condition::WHERE, $item)) {
                continue;
            }

            $resultItem = $this->applySelect($item);

            if ($havingRow !== null) {
                $rows[$havingRow]['rows_in']++;
                $start = microtime(true);
                $havingResult = $this->evaluateConditions(Condition::HAVING, $resultItem);
                $havingTime += $this->elapsedMs($start);
                if (!$havingResult) {
                    continue;
                }
                $rows[$havingRow]['rows_out']++;
            } elseif (!$this->evaluateConditions(Condition::HAVING, $resultItem)) {
                continue;
            }

            if ($this->distinct) {
                $hash = md5(serialize($resultItem));
                if (isset($seen[$hash])) {
                    continue;
                }
                $seen[$hash] = true;
            }

            if ($limitRow !== null) {
                $rows[$limitRow]['rows_in']++;
                $start = microtime(true);
                if ($limitAtStream && $this->offset !== null && $currentOffset < $this->offset) {
                    $currentOffset++;
                    $limitTime += $this->elapsedMs($start);
                    continue;
                }
                $limitTime += $this->elapsedMs($start);
            } elseif ($limitAtStream && $this->offset !== null && $currentOffset < $this->offset) {
                $currentOffset++;
                continue;
            }

            yield $this->applyExcludeFromSelect($resultItem);

            if ($limitRow !== null) {
                $rows[$limitRow]['rows_out']++;
            }

            $count++;
            if ($limitAtStream && $this->limit !== null && $count >= $this->limit) {
                break;
            }
        }

        if ($whereRow !== null) {
            $rows[$whereRow]['time_ms'] = $whereTime;
        }
        if ($havingRow !== null) {
            $rows[$havingRow]['time_ms'] = $havingTime;
        }
        if ($limitRow !== null) {
            $rows[$limitRow]['time_ms'] = $limitTime;
        }
    }

    /**
     * @param \Traversable<StreamProviderArrayIteratorValue> $stream
     * @param array<int, ExplainResultArray> $rows
     * @return \Traversable<StreamProviderArrayIteratorValue>
     */
    private function applyGroupingExplain(
        \Traversable $stream,
        array &$rows,
        ?int $whereRow,
        int $groupRow,
        ?int $havingRow,
        ?int $limitRow,
        bool $limitAtStream
    ): \Traversable {
        $groupedData = [];
        $groupKey = Query::SELECT_ALL;
        $aggregateFunctions = $this->getAggregateFunctions();
        $incrementalAggregates = $aggregateFunctions;
        $whereTime = 0.0;
        $groupTime = 0.0;

        foreach ($stream as $item) {
            if ($whereRow !== null) {
                $rows[$whereRow]['rows_in']++;
                $start = microtime(true);
                $whereResult = $this->evaluateConditions(Condition::WHERE, $item);
                $whereTime += $this->elapsedMs($start);
                if (!$whereResult) {
                    continue;
                }
                $rows[$whereRow]['rows_out']++;
            } elseif (!$this->evaluateConditions(Condition::WHERE, $item)) {
                continue;
            }

            $rows[$groupRow]['rows_in']++;
            $startGroup = microtime(true);
            if ($this->hasPhase('group')) {
                $groupKey = $this->createGroupKey($item);
            }

            if (!isset($groupedData[$groupKey])) {
                $groupedData[$groupKey] = $this->createGroupState(
                    $item,
                    $incrementalAggregates
                );
                $groupTime += $this->elapsedMs($startGroup);
                continue;
            }

            foreach ($incrementalAggregates as $finalField => $function) {
                $groupedData[$groupKey]['accumulators'][$finalField] = $function->accumulate(
                    $groupedData[$groupKey]['accumulators'][$finalField],
                    $item
                );
            }
            $groupTime += $this->elapsedMs($startGroup);
        }

        if ($whereRow !== null) {
            $rows[$whereRow]['time_ms'] = $whereTime;
        }

        $rows[$groupRow]['rows_out'] = count($groupedData);
        $rows[$groupRow]['time_ms'] = $groupTime;

        if ($groupKey === Query::SELECT_ALL) {
            if (empty($groupedData[Query::SELECT_ALL] ?? null)) {
                return yield from [];
            }

            $aggregatedItem = $this->applyAggregations($groupedData[Query::SELECT_ALL], $aggregateFunctions);

            $havingTime = 0.0;
            if ($havingRow !== null) {
                $rows[$havingRow]['rows_in']++;
                $start = microtime(true);
                $havingResult = $this->evaluateConditions(Condition::HAVING, $aggregatedItem);
                $havingTime += $this->elapsedMs($start);
                if (!$havingResult) {
                    $rows[$havingRow]['time_ms'] = $havingTime;
                    return yield from [];
                }
                $rows[$havingRow]['rows_out']++;
            } elseif (!$this->evaluateConditions(Condition::HAVING, $aggregatedItem)) {
                return yield from [];
            }

            if ($havingRow !== null) {
                $rows[$havingRow]['time_ms'] = $havingTime;
            }

            if ($limitRow !== null) {
                $rows[$limitRow]['rows_in']++;
                $rows[$limitRow]['rows_out']++;
            }

            return yield $this->applyExcludeFromSelect($aggregatedItem);
        }

        $count = 0;
        $currentOffset = 0;
        $havingTime = 0.0;
        $limitTime = 0.0;
        foreach ($groupedData as $groupState) {
            $aggregatedItem = $this->applyAggregations($groupState, $aggregateFunctions);
            if ($havingRow !== null) {
                $rows[$havingRow]['rows_in']++;
                $start = microtime(true);
                $havingResult = $this->evaluateConditions(Condition::HAVING, $aggregatedItem);
                $havingTime += $this->elapsedMs($start);
                if (!$havingResult) {
                    continue;
                }
                $rows[$havingRow]['rows_out']++;
            } elseif (!$this->evaluateConditions(Condition::HAVING, $aggregatedItem)) {
                continue;
            }

            if ($limitRow !== null) {
                $rows[$limitRow]['rows_in']++;
                $start = microtime(true);
                if ($limitAtStream && $this->offset !== null && $currentOffset < $this->offset) {
                    $currentOffset++;
                    $limitTime += $this->elapsedMs($start);
                    continue;
                }
                $limitTime += $this->elapsedMs($start);
            } elseif ($limitAtStream && $this->offset !== null && $currentOffset < $this->offset) {
                $currentOffset++;
                continue;
            }

            yield $this->applyExcludeFromSelect($aggregatedItem);

            if ($limitRow !== null) {
                $rows[$limitRow]['rows_out']++;
            }

            $count++;
            if ($limitAtStream && $this->limit !== null && $count >= $this->limit) {
                break;
            }
        }

        if ($havingRow !== null) {
            $rows[$havingRow]['time_ms'] = $havingTime;
        }
        if ($limitRow !== null) {
            $rows[$limitRow]['time_ms'] = $limitTime;
        }
    }

    /**
     * @param \Traversable<StreamProviderArrayIteratorValue> $iterator
     * @param array<int, ExplainResultArray> $rows
     * @return \Traversable<StreamProviderArrayIteratorValue>
     */
    private function applySortingExplain(\Traversable $iterator, array &$rows, int $rowIndex): \Traversable
    {
        $input = $this->wrapInputWithCounter($iterator, $rows, $rowIndex);
        $start = microtime(true);
        if ($this->orderings === []) {
            foreach ($input as $item) {
                $rows[$rowIndex]['rows_out']++;
                yield $item;
            }
            $rows[$rowIndex]['time_ms'] = $this->elapsedMs($start);
            return;
        }

        $data = iterator_to_array($input);
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

        $rows[$rowIndex]['rows_out'] = count($data);
        foreach ($data as $item) {
            yield $item;
        }
        $rows[$rowIndex]['time_ms'] = $this->elapsedMs($start);
    }

    /**
     * @param \Traversable<StreamProviderArrayIteratorValue> $data
     * @param array<int, ExplainResultArray> $rows
     * @return \Traversable<StreamProviderArrayIteratorValue>
     */
    private function applyLimitExplain(\Traversable $data, array &$rows, int $rowIndex): \Traversable
    {
        $count = 0;
        $currentOffset = 0;
        $start = microtime(true);
        foreach ($this->wrapInputWithCounter($data, $rows, $rowIndex) as $item) {
            if ($this->offset !== null && $currentOffset < $this->offset) {
                $currentOffset++;
                continue;
            }

            $rows[$rowIndex]['rows_out']++;
            yield $item;

            $count++;
            if ($this->limit !== null && $count >= $this->limit) {
                break;
            }
        }
        $rows[$rowIndex]['time_ms'] = $this->elapsedMs($start);
    }

    /**
     * @param \Traversable<StreamProviderArrayIteratorValue> $input
     * @param array<int, ExplainResultArray> $rows
     * @return \Generator<StreamProviderArrayIteratorValue>
     */
    private function wrapInputWithCounter(\Traversable $input, array &$rows, int $rowIndex): \Generator
    {
        foreach ($input as $item) {
            $rows[$rowIndex]['rows_in']++;
            yield $item;
        }
    }

    private function elapsedMs(float $start): float
    {
        return (microtime(true) - $start) * 1000;
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
        // Always execute right side (needed in any case)
        $rightData = $join['table']->execute(self::class)->getIterator();
        $alias = $join['alias'];
        $leftKey = $join['leftKey'] ?? '';
        $rightKey = $join['rightKey'] ?? '';
        $leftKey = $this->isBacktick($leftKey) ? $this->removeQuotes($leftKey) : $leftKey;
        $rightKey = $this->isBacktick($rightKey) ? $this->removeQuotes($rightKey) : $rightKey;
        $operator = $join['operator'] ?? Enum\Operator::EQUAL;
        $type = $join['type'];

        // If RIGHT JOIN, swap sides and keys AFTER both are Traversable
        if ($type === Enum\Join::RIGHT) {
            $temp = $leftData;
            $leftData = $rightData;
            $rightData = $temp;

            $tempKey = $leftKey;
            $leftKey = $rightKey;
            $rightKey = $tempKey;

            $type = Enum\Join::LEFT; // treat as LEFT join from swapped view
        }

        // Build a hashmap for the right table
        foreach ($rightData as $row) {
            $key = $row[$rightKey] ?? null;
            if (is_int($key) || is_string($key)) {
                $this->joinHashMap->set($key, $row);
            }
        }

        unset($rightData);

        // Get the structure of the right table from the hashmap
        $rightStructure = $this->joinHashMap->getStructure();
        $usedRightKeys = [];

        foreach ($leftData as $leftRow) {
            $leftKeyValue = $leftRow[$leftKey] ?? null;
            if ((is_int($leftKeyValue) || is_string($leftKeyValue)) && $this->joinHashMap->has($leftKeyValue)) {
                // Handle matches (n * n)
                foreach ($this->joinHashMap->get($leftKeyValue) as $rightRow) {
                    /** @var StreamProviderArrayIteratorValue $joinedRow */
                    $joinedRow = $alias
                        ? array_merge($leftRow, [$alias => $rightRow])
                        : array_merge($leftRow, $rightRow);

                    if ($operator->evaluate($leftKeyValue, $rightRow[$rightKey] ?? null)) {
                        yield $joinedRow;
                        $usedRightKeys[$leftKeyValue] = true;
                    }
                }
            } elseif ($type === Enum\Join::LEFT || $type === Enum\Join::FULL) {
                // Emit unmatched left row (null right side)
                $nullRow = array_fill_keys($rightStructure, null);
                /** @var StreamProviderArrayIteratorValue $joinedRow */
                $joinedRow = $alias
                    ? array_merge($leftRow, [$alias => $nullRow])
                    : array_merge($leftRow, $nullRow);

                yield $joinedRow;
            }
        }

        // Emit unmatched right rows (FULL JOIN only)
        if ($type === Enum\Join::FULL) {
            foreach ($this->joinHashMap->getAll() as $key => $rightRows) {
                if (!isset($usedRightKeys[$key])) {
                    foreach ($rightRows as $rightRow) {
                        $nullRow = array_fill_keys(array_keys($leftRow ?? []), null);
                        $joinedRow = $alias
                            ? array_merge($nullRow, [$alias => $rightRow])
                            : array_merge($nullRow, $rightRow);

                        yield $joinedRow;
                    }
                }
            }
        }
    }

    /**
     * @implements \Traversable<StreamProviderArrayIteratorValue>
     * @return \Generator<StreamProviderArrayIteratorValue>
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
     * @param StreamProviderArrayIteratorValue $item
     * @return array<int|string, mixed>
     * @throws Exception\InvalidArgumentException
     */
    private function applySelect(array $item): array
    {
        $result = [];
        if ($this->selectedFields === []) {
            $result = $item;
        }

        foreach ($this->selectedFields as $finalField => $fieldData) {
            $fieldName = ($this->isQuoted($finalField) || $this->isBacktick($finalField))
                ? $this->removeQuotes($finalField)
                : $finalField;
            if ($fieldName === Query::SELECT_ALL) {
                $result = array_merge($result, $item);
                continue;
            } elseif ($fieldData['function'] instanceof BaseFunction) {
                $result[$fieldName] = $fieldData['function']($item, $result);
                continue;
            } elseif ($fieldData['function'] instanceof BaseFunctionByReference) {
                $fieldData['function']($item, $result);
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
                $fieldData['originField'],
                false
            ) ?? $this->accessNestedValue($result, $fieldData['originField'], false)
                ?? (
                    $this->isQuoted($fieldData['originField'])
                        ? Enum\Type::matchByString($this->removeQuotes($fieldData['originField']))
                        : null
                );
        }

        return $result;
    }

    /**
     * @param array<string|int, mixed> $item
     * @return array<string|int, mixed>
     */
    private function applyExcludeFromSelect(array $item): array
    {
        // Exclude fields
        foreach ($this->excludedFields as $excludedField) {
            $this->removeNestedValue($item, $excludedField);
        }

        return $item;
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

            yield $this->applyExcludeFromSelect($resultItem); // Return result

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
        $aggregateFunctions = $this->getAggregateFunctions();
        $incrementalAggregates = $aggregateFunctions;
        foreach ($stream as $item) {
            if (!$this->evaluateConditions(Condition::WHERE, $item)) {
                continue;
            } elseif ($this->hasPhase('group')) {
                $groupKey = $this->createGroupKey($item);
            }

            if (!isset($groupedData[$groupKey])) {
                $groupedData[$groupKey] = $this->createGroupState(
                    $item,
                    $incrementalAggregates
                );
                continue;
            }

            foreach ($incrementalAggregates as $finalField => $function) {
                $groupedData[$groupKey]['accumulators'][$finalField] = $function->accumulate(
                    $groupedData[$groupKey]['accumulators'][$finalField],
                    $item
                );
            }
        }

        if ($groupKey === Query::SELECT_ALL) {
            if (empty($groupedData[Query::SELECT_ALL] ?? null)) {
                return yield from [];
            }

            // Aggregate grouped items
            $aggregatedItem = $this->applyAggregations($groupedData[Query::SELECT_ALL], $aggregateFunctions);
            if ($this->evaluateConditions(Condition::HAVING, $aggregatedItem)) {
                return yield $this->applyExcludeFromSelect($aggregatedItem); // Return result
            }
        }

        $count = 0;
        $currentOffset = 0; // Number of already skipped records
        $applyLimitAtStream = $this->isLimitable() && !$this->isSortable();
        foreach ($groupedData as $groupState) {
            // Aggregate grouped items
            $aggregatedItem = $this->applyAggregations($groupState, $aggregateFunctions);
            if (!$this->evaluateConditions(Condition::HAVING, $aggregatedItem)) {
                continue; // Skip groups that do not satisfy HAVING
            }

            // Offset application
            if ($applyLimitAtStream && $this->offset !== null && $currentOffset < $this->offset) {
                $currentOffset++;
                continue;
            }

            yield $this->applyExcludeFromSelect($aggregatedItem); // Return aggregated result

            $count++;
            if ($applyLimitAtStream && $this->limit !== null && $count >= $this->limit) {
                break;
            }
        }
    }

    /**
     * Aggregates grouped items.
     *
     * @param array{firstItem: array<int|string, mixed>, accumulators: array<string, mixed>} $groupState
     * @param array<string, AggregateFunction> $aggregateFunctions
     * @return array<int|string, mixed> Aggregated result
     */
    private function applyAggregations(array $groupState, array $aggregateFunctions): array
    {
        $aggregatedItem = $groupState['firstItem'];
        foreach ($aggregateFunctions as $finalField => $function) {
            $accumulator = $groupState['accumulators'][$finalField] ?? $function->initAccumulator();
            $aggregatedItem[$finalField] = $function->finalize($accumulator);
        }

        return $this->applySelect($aggregatedItem);
    }

    /**
     * @return array<string, AggregateFunction>
     */
    private function getAggregateFunctions(): array
    {
        $aggregateFunctions = [];
        foreach ($this->selectedFields as $finalField => $fieldData) {
            if ($fieldData['function'] instanceof AggregateFunction) {
                $aggregateFunctions[$finalField] = $fieldData['function'];
            }
        }

        return $aggregateFunctions;
    }

    /**
     * @param StreamProviderArrayIteratorValue $item
     * @param array<string, AggregateFunction> $incrementalAggregates
     * @return array{firstItem: array<int|string, mixed>, accumulators: array<string, mixed>}
     */
    private function createGroupState(
        array $item,
        array $incrementalAggregates
    ): array {
        $state = [
            'firstItem' => $item,
            'accumulators' => [],
        ];

        foreach ($incrementalAggregates as $finalField => $function) {
            $accumulator = $function->initAccumulator();
            $state['accumulators'][$finalField] = $function->accumulate($accumulator, $item);
        }

        return $state;
    }

    /**
     * @param \Traversable<StreamProviderArrayIteratorValue> $iterator
     * @return \Traversable<StreamProviderArrayIteratorValue>
     * @throws Exception\SortException
     */
    private function applySorting(\Traversable $iterator): \Traversable
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


    /**
     * @param \Traversable<StreamProviderArrayIteratorValue> $data
     * @return \Generator<StreamProviderArrayIteratorValue>
     */
    private function applyLimit(\Traversable $data): \Generator
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
     * @param StreamProviderArrayIteratorValue $item
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
