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
use FQL\Query\FileQuery;
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
        private readonly ?FileQuery $into = null,
        private JoinHashMap $joinHashMap = new InMemoryHashmap(),
        /** @var array<int, array{type: string, query: Query}> */
        private readonly array $unions = [],
        private ?ExplainCollector $collector = null
    ) {
    }

    private string $collectorPrefix = '';

    public function setCollector(ExplainCollector $collector, string $prefix = ''): void
    {
        $this->collector = $collector;
        $this->collectorPrefix = $prefix;
    }

    private function prefixPhase(string $phase): string
    {
        return $this->collectorPrefix !== ''
            ? $this->collectorPrefix . '_' . $phase
            : $phase;
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
        if (!$analyze) {
            $joinNotes = [];
            foreach ($this->joins as $join) {
                $joinNotes[] = $this->getJoinNote($join);
            }

            $collector = new ExplainCollector();
            return $collector->buildPlan(
                streamNote: $this->getStreamNote(),
                hasJoin: $this->hasJoin(),
                joinNotes: $joinNotes,
                hasWhere: $this->hasWhereConditions(),
                whereNote: $this->getWhereNote(),
                isGroupable: $this->isGroupable(),
                groupNote: $this->getGroupNote(),
                hasHaving: $this->hasHavingConditions(),
                havingNote: $this->getHavingNote(),
                isSortable: $this->isSortable(),
                sortNote: $this->getSortNote(),
                isLimitable: $this->isLimitable(),
                limitNote: $this->getLimitNote($this->isLimitAppliedInStream()),
                unions: $this->unions,
                into: $this->getInto()
            );
        }

        // ANALYZE: set collector, run the stream, collect metrics
        $this->collector = new ExplainCollector();
        if ($this->hasInto()) {
            $into = $this->getInto();
            if ($into !== null) {
                $this->into($into);
            }
        } else {
            foreach ($this->buildStream() as $_) {
                // consume all rows so collector can gather metrics
            }
        }

        assert($this->collector !== null);
        return $this->collector->finalize();
    }

    /**
     * @throws Exception\FileQueryException
     * @throws Exception\InvalidFormatException
     * @throws Exception\InvalidArgumentException
     */
    public function into(FileQuery|string $fileQuery): ?string
    {
        if ($this->collector === null) {
            return parent::into($fileQuery);
        }

        if (is_string($fileQuery)) {
            $fileQuery = new FileQuery($fileQuery);
        }

        if ($fileQuery->file === null) {
            throw new Exception\InvalidArgumentException('Missing target file in INTO file query.');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'fql_into_');
        if ($tempFile === false) {
            throw new Exception\InvalidArgumentException('Unable to create temporary file for INTO analyze.');
        }

        if (file_exists($tempFile)) {
            unlink($tempFile);
        }

        $targetFileQuery = $fileQuery;
        $phaseFileQuery = $fileQuery->withFile($tempFile);
        $startedAt = microtime(true);
        $writeException = null;

        try {
            parent::into($phaseFileQuery);
        } catch (\Throwable $e) {
            $writeException = $e;
        } finally {
            $intoIdx = $this->collector->addPhase(
                'into',
                sprintf('write to %s', $targetFileQuery),
                true
            );

            $elapsedMs = (microtime(true) - $startedAt) * 1000;
            $this->collector->addTime($intoIdx, $elapsedMs);
            $this->collector->setIncrementIn($intoIdx, $this->getLastIntoWriteCount());
            $this->collector->setIncrementOut($intoIdx, $this->getLastIntoWriteCount());
            $this->collector->recordMemPeak($intoIdx);

            if (file_exists($tempFile)) {
                unlink($tempFile);
            }

            if ($writeException !== null) {
                throw $writeException;
            }
        }

        return null;
    }

    private function hasInto(): bool
    {
        return $this->into !== null;
    }

    private function getInto(): ?FileQuery
    {
        return $this->into;
    }

    /**
     * @return \Traversable<StreamProviderArrayIteratorValue>
     */
    private function applyStreamSource(?int $streamIdx = null): \Traversable
    {
        $streamSource = $this->from === Query::SELECT_ALL
            ? null
            : $this->from;

        if ($streamIdx === null) {
            return $this->stream->getStreamGenerator($streamSource);
        }

        return $this->applyStreamSourceInstrumented($streamSource, $streamIdx);
    }

    /**
     * @return \Generator<StreamProviderArrayIteratorValue>
     */
    private function applyStreamSourceInstrumented(?string $streamSource, int $streamIdx): \Traversable
    {
        assert($this->collector !== null);
        $this->collector->startTimer($streamIdx);

        foreach ($this->stream->getStreamGenerator($streamSource) as $item) {
            $this->collector->incrementOut($streamIdx);
            yield $item;
        }

        $this->collector->stopTimer($streamIdx);
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
     * Applies all defined joins to the dataset.
     * @param \Traversable<StreamProviderArrayIteratorValue> $data The primary data to join.
     * @param int[] $joinIndices Pre-allocated collector indices for each join.
     * @return \Traversable<StreamProviderArrayIteratorValue> The joined dataset.
     */
    private function applyJoins(\Traversable $data, array $joinIndices = []): \Traversable
    {
        foreach ($this->joins as $i => $join) {
            $data = $this->applyJoinInstrumented($data, $join, $joinIndices[$i] ?? null);
        }
        return $data;
    }

    /**
     * Wraps applyJoin with collector instrumentation.
     * @param \Traversable<StreamProviderArrayIteratorValue> $leftData
     * @param JoinAbleArray $join
     * @param int|null $joinIdx Pre-allocated collector index for this join phase.
     * @return \Traversable<StreamProviderArrayIteratorValue>
     */
    private function applyJoinInstrumented(\Traversable $leftData, array $join, ?int $joinIdx = null): \Traversable
    {
        if ($this->collector === null || $joinIdx === null) {
            yield from $this->applyJoin($leftData, $join);
            return;
        }

        $this->collector->startTimer($joinIdx);

        // Count input rows
        $c = $this->collector;
        $countedInput = (function () use ($leftData, $joinIdx, $c): \Generator {
            foreach ($leftData as $item) {
                $c->incrementIn($joinIdx);
                yield $item;
            }
        })();

        foreach ($this->applyJoin($countedInput, $join) as $item) {
            $this->collector->incrementOut($joinIdx);
            yield $item;
        }

        $this->collector->stopTimer($joinIdx);
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
        $c = $this->collector;
        $applyLimitAtStream = $this->isLimitable() && !$this->isSortable();

        // Pre-register all phases eagerly in logical order
        $streamIdx = $c?->addPhase($this->prefixPhase('stream'), $this->getStreamNote(), false);

        /** @var int[] $joinIndices */
        $joinIndices = [];
        if ($c !== null && $this->hasJoin()) {
            foreach ($this->joins as $join) {
                $joinIndices[] = $c->addPhase($this->prefixPhase('join'), $this->getJoinNote($join), true);
            }
        }

        $whereIdx = ($c !== null && $this->hasWhereConditions())
            ? $c->addPhase($this->prefixPhase('where'), $this->getWhereNote(), true)
            : null;

        $groupIdx = ($c !== null && $this->isGroupable())
            ? $c->addPhase($this->prefixPhase('group'), $this->getGroupNote(), true)
            : null;

        $havingIdx = ($c !== null && $this->hasHavingConditions())
            ? $c->addPhase($this->prefixPhase('having'), $this->getHavingNote(), true)
            : null;

        $sortIdx = ($c !== null && $this->isSortable())
            ? $c->addPhase($this->prefixPhase('sort'), $this->getSortNote(), true)
            : null;

        $limitIdx = ($c !== null && $this->isLimitable())
            ? $c->addPhase($this->prefixPhase('limit'), $this->getLimitNote($applyLimitAtStream), true)
            : null;

        // Build generator chain
        $stream = $this->hasJoin()
            ? $this->applyJoins($this->applyStreamSource($streamIdx), $joinIndices)
            : $this->applyStreamSource($streamIdx);

        if ($this->isGroupable()) {
            if (!$this->isSortable()) {
                return yield from $this->applyUnions(
                    $this->applyGrouping($stream, $whereIdx, $groupIdx, $havingIdx, $limitIdx)
                );
            }

            $stream = $this->applyGrouping($stream, $whereIdx, $groupIdx, $havingIdx, null);
        } else {
            $stream = $this->applyBaseStream(
                $stream,
                $whereIdx,
                $havingIdx,
                $applyLimitAtStream ? $limitIdx : null
            );
        }

        if (!$this->isSortable()) {
            return yield from $this->applyUnions($stream);
        } elseif (!$this->isLimitable()) {
            return yield from $this->applyUnions($this->applySorting($stream, $sortIdx));
        }

        return yield from $this->applyUnions(
            $this->applyLimit($this->applySorting($stream, $sortIdx), $limitIdx)
        );
    }

    /**
     * @param \Traversable<StreamProviderArrayIteratorValue> $stream
     * @return \Generator<StreamProviderArrayIteratorValue>
     */
    private function applyUnions(\Traversable $stream): \Traversable
    {
        if ($this->unions === []) {
            yield from $stream;
            return;
        }

        $c = $this->collector;
        $seen = [];
        $emit = function (\Traversable $source, bool $deduplicate, ?int $idx) use (&$seen, $c): \Generator {
            foreach ($source as $row) {
                if ($c !== null && $idx !== null) {
                    $c->incrementIn($idx);
                }
                if (!$deduplicate) {
                    if ($c !== null && $idx !== null) {
                        $c->incrementOut($idx);
                    }
                    yield $row;
                    continue;
                }
                $hash = md5(serialize($row));
                if (!isset($seen[$hash])) {
                    $seen[$hash] = true;
                    if ($c !== null && $idx !== null) {
                        $c->incrementOut($idx);
                    }
                    yield $row;
                }
            }
        };

        $hasAnyUnion = !empty(array_filter($this->unions, fn($u) => $u['type'] === 'UNION'));
        yield from $emit($stream, $hasAnyUnion, null);

        $unionCount = count($this->unions);
        foreach ($this->unions as $index => $union) {
            $prefix = $unionCount === 1 ? 'union' : 'union_' . ($index + 1);
            $unionStart = $c !== null ? microtime(true) : null;
            $unionRowsIn = 0;
            $unionRowsOut = 0;

            $unionResult = $union['query']->execute();

            // Pass collector with prefix to union subquery for sub-phase instrumentation
            if ($c !== null && $unionResult instanceof self) {
                $unionResult->setCollector($c, $prefix);
            }

            $deduplicate = $union['type'] === 'UNION';
            foreach ($unionResult as $row) {
                $unionRowsIn++;
                if (!$deduplicate) {
                    $unionRowsOut++;
                    yield $row;
                    continue;
                }
                $hash = md5(serialize($row));
                if (!isset($seen[$hash])) {
                    $seen[$hash] = true;
                    $unionRowsOut++;
                    yield $row;
                }
            }

            // Add summary row AFTER sub-phases so it appears last
            if ($c !== null) {
                $summaryIdx = $c->addPhase($prefix, $union['type'], true);
                if ($unionStart !== null) {
                    $elapsed = (microtime(true) - $unionStart) * 1000;
                    $c->addTime($summaryIdx, $elapsed);
                }
                for ($i = 0; $i < $unionRowsIn; $i++) {
                    $c->incrementIn($summaryIdx);
                }
                for ($i = 0; $i < $unionRowsOut; $i++) {
                    $c->incrementOut($summaryIdx);
                }
                $c->recordMemPeak($summaryIdx);
            }
        }
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
     * @param int|null $whereIdx Pre-allocated collector index for where phase.
     * @param int|null $havingIdx Pre-allocated collector index for having phase.
     * @param int|null $limitIdx Pre-allocated collector index for limit phase (stream limit).
     * @return \Traversable<StreamProviderArrayIteratorValue>
     * @throws Exception\InvalidArgumentException
     */
    private function applyBaseStream(
        \Traversable $stream,
        ?int $whereIdx = null,
        ?int $havingIdx = null,
        ?int $limitIdx = null
    ): \Traversable {
        $count = 0;
        $currentOffset = 0;
        $applyLimitAtStream = $this->isLimitable() && !$this->isSortable();
        $c = $this->collector;

        foreach ($stream as $item) {
            if ($c !== null && $whereIdx !== null) {
                $c->incrementIn($whereIdx);
                $c->startAccumulator($whereIdx);
            }
            if (!$this->evaluateConditions(Condition::WHERE, $item)) {
                if ($c !== null && $whereIdx !== null) {
                    $c->stopAccumulator($whereIdx);
                }
                continue;
            }
            if ($c !== null && $whereIdx !== null) {
                $c->incrementOut($whereIdx);
                $c->stopAccumulator($whereIdx);
            }

            $resultItem = $this->applySelect($item);

            if ($c !== null && $havingIdx !== null) {
                $c->incrementIn($havingIdx);
                $c->startAccumulator($havingIdx);
            }
            if (!$this->evaluateConditions(Condition::HAVING, $resultItem)) {
                if ($c !== null && $havingIdx !== null) {
                    $c->stopAccumulator($havingIdx);
                }
                continue;
            }
            if ($c !== null && $havingIdx !== null) {
                $c->incrementOut($havingIdx);
                $c->stopAccumulator($havingIdx);
            }

            if ($this->distinct) {
                $hash = md5(serialize($resultItem));
                if (isset($seen[$hash])) {
                    continue;
                }
                $seen[$hash] = true;
            }

            if ($c !== null && $limitIdx !== null) {
                $c->incrementIn($limitIdx);
                $c->startAccumulator($limitIdx);
            }
            if ($applyLimitAtStream && $this->offset !== null && $currentOffset < $this->offset) {
                $currentOffset++;
                if ($c !== null && $limitIdx !== null) {
                    $c->stopAccumulator($limitIdx);
                }
                continue;
            }
            if ($c !== null && $limitIdx !== null) {
                $c->stopAccumulator($limitIdx);
            }

            yield $this->applyExcludeFromSelect($resultItem);

            if ($c !== null && $limitIdx !== null) {
                $c->incrementOut($limitIdx);
            }

            $count++;
            if ($applyLimitAtStream && $this->limit !== null && $count >= $this->limit) {
                break;
            }
        }
    }

    /**
     * @param \Traversable<StreamProviderArrayIteratorValue> $stream
     * @param int|null $whereIdx Pre-allocated collector index for where phase.
     * @param int|null $groupIdx Pre-allocated collector index for group phase.
     * @param int|null $havingIdx Pre-allocated collector index for having phase.
     * @param int|null $limitIdx Pre-allocated collector index for limit phase (stream limit).
     * @return \Generator<StreamProviderArrayIteratorValue>
     * @throws Exception\InvalidArgumentException
     */
    private function applyGrouping(
        \Traversable $stream,
        ?int $whereIdx = null,
        ?int $groupIdx = null,
        ?int $havingIdx = null,
        ?int $limitIdx = null
    ): \Traversable {
        $groupedData = [];
        $groupKey = Query::SELECT_ALL;
        $aggregateFunctions = $this->getAggregateFunctions();
        $incrementalAggregates = $aggregateFunctions;
        $applyLimitAtStream = $this->isLimitable() && !$this->isSortable();
        $c = $this->collector;

        foreach ($stream as $item) {
            if ($c !== null && $whereIdx !== null) {
                $c->incrementIn($whereIdx);
                $c->startAccumulator($whereIdx);
            }
            if (!$this->evaluateConditions(Condition::WHERE, $item)) {
                if ($c !== null && $whereIdx !== null) {
                    $c->stopAccumulator($whereIdx);
                }
                continue;
            }
            if ($c !== null && $whereIdx !== null) {
                $c->incrementOut($whereIdx);
                $c->stopAccumulator($whereIdx);
            }

            if ($c !== null && $groupIdx !== null) {
                $c->incrementIn($groupIdx);
                $c->startAccumulator($groupIdx);
            }

            if ($this->hasPhase('group')) {
                $groupKey = $this->createGroupKey($item);
            }

            if (!isset($groupedData[$groupKey])) {
                $groupedData[$groupKey] = $this->createGroupState(
                    $item,
                    $incrementalAggregates
                );
                if ($c !== null && $groupIdx !== null) {
                    $c->stopAccumulator($groupIdx);
                }
                continue;
            }

            foreach ($incrementalAggregates as $finalField => $function) {
                $groupedData[$groupKey]['accumulators'][$finalField] = $function->accumulate(
                    $groupedData[$groupKey]['accumulators'][$finalField],
                    $item
                );
            }
            if ($c !== null && $groupIdx !== null) {
                $c->stopAccumulator($groupIdx);
            }
        }

        if ($c !== null && $groupIdx !== null) {
            $c->incrementOut($groupIdx);
            for ($i = 1; $i < count($groupedData); $i++) {
                $c->incrementOut($groupIdx);
            }
        }

        if ($groupKey === Query::SELECT_ALL) {
            if (empty($groupedData[Query::SELECT_ALL] ?? null)) {
                return yield from [];
            }

            $aggregatedItem = $this->applyAggregations($groupedData[Query::SELECT_ALL], $aggregateFunctions);

            if ($c !== null && $havingIdx !== null) {
                $c->incrementIn($havingIdx);
                $c->startAccumulator($havingIdx);
            }
            if (!$this->evaluateConditions(Condition::HAVING, $aggregatedItem)) {
                if ($c !== null && $havingIdx !== null) {
                    $c->stopAccumulator($havingIdx);
                }
                return yield from [];
            }
            if ($c !== null && $havingIdx !== null) {
                $c->incrementOut($havingIdx);
                $c->stopAccumulator($havingIdx);
            }

            if ($c !== null && $limitIdx !== null) {
                $c->incrementIn($limitIdx);
                $c->incrementOut($limitIdx);
            }

            return yield $this->applyExcludeFromSelect($aggregatedItem);
        }

        $count = 0;
        $currentOffset = 0;
        foreach ($groupedData as $groupState) {
            $aggregatedItem = $this->applyAggregations($groupState, $aggregateFunctions);

            if ($c !== null && $havingIdx !== null) {
                $c->incrementIn($havingIdx);
                $c->startAccumulator($havingIdx);
            }
            if (!$this->evaluateConditions(Condition::HAVING, $aggregatedItem)) {
                if ($c !== null && $havingIdx !== null) {
                    $c->stopAccumulator($havingIdx);
                }
                continue;
            }
            if ($c !== null && $havingIdx !== null) {
                $c->incrementOut($havingIdx);
                $c->stopAccumulator($havingIdx);
            }

            if ($c !== null && $limitIdx !== null) {
                $c->incrementIn($limitIdx);
                $c->startAccumulator($limitIdx);
            }
            if ($applyLimitAtStream && $this->offset !== null && $currentOffset < $this->offset) {
                $currentOffset++;
                if ($c !== null && $limitIdx !== null) {
                    $c->stopAccumulator($limitIdx);
                }
                continue;
            }
            if ($c !== null && $limitIdx !== null) {
                $c->stopAccumulator($limitIdx);
            }

            yield $this->applyExcludeFromSelect($aggregatedItem);

            if ($c !== null && $limitIdx !== null) {
                $c->incrementOut($limitIdx);
            }

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
     * @param int|null $sortIdx Pre-allocated collector index for sort phase.
     * @return \Traversable<StreamProviderArrayIteratorValue>
     * @throws Exception\SortException
     */
    private function applySorting(\Traversable $iterator, ?int $sortIdx = null): \Traversable
    {
        if ($this->orderings === []) {
            return $iterator;
        }

        $c = $this->collector;

        if ($c !== null && $sortIdx !== null) {
            $c->startTimer($sortIdx);
        }

        $data = [];
        foreach ($iterator as $item) {
            if ($c !== null && $sortIdx !== null) {
                $c->incrementIn($sortIdx);
            }
            $data[] = $item;
        }

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

        if ($c !== null && $sortIdx !== null) {
            for ($i = 0; $i < count($data); $i++) {
                $c->incrementOut($sortIdx);
            }
        }

        foreach ($data as $item) {
            yield $item;
        }

        if ($c !== null && $sortIdx !== null) {
            $c->stopTimer($sortIdx);
        }
    }


    /**
     * @param \Traversable<StreamProviderArrayIteratorValue> $data
     * @param int|null $limitIdx Pre-allocated collector index for limit phase.
     * @return \Generator<StreamProviderArrayIteratorValue>
     */
    private function applyLimit(\Traversable $data, ?int $limitIdx = null): \Generator
    {
        $count = 0;
        $currentOffset = 0;
        $c = $this->collector;

        if ($c !== null && $limitIdx !== null) {
            $c->startTimer($limitIdx);
        }

        foreach ($data as $item) {
            if ($c !== null && $limitIdx !== null) {
                $c->incrementIn($limitIdx);
            }

            if ($this->offset !== null && $currentOffset < $this->offset) {
                $currentOffset++;
                continue;
            }

            yield $item;

            if ($c !== null && $limitIdx !== null) {
                $c->incrementOut($limitIdx);
            }

            $count++;
            if ($this->limit !== null && $count >= $this->limit) {
                break;
            }
        }

        if ($c !== null && $limitIdx !== null) {
            $c->stopTimer($limitIdx);
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
