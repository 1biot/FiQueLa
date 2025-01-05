<?php

namespace UQL\Query;

use Generator;
use UQL\Exceptions\InvalidArgumentException;
use UQL\Helpers\ArrayHelper;
use UQL\Stream\Csv;
use UQL\Stream\Json;
use UQL\Stream\Neon;
use UQL\Stream\Xml;
use UQL\Stream\Yaml;
use UQL\Traits;

/**
 * @phpstan-type StreamProviderArrayIteratorValue array<int|string, array<int|string, mixed>|scalar|null>
 * @codingStandardsIgnoreStart
 * @phpstan-type StreamProviderArrayIterator \ArrayIterator<int|string, StreamProviderArrayIteratorValue>|\ArrayIterator<int, StreamProviderArrayIteratorValue>|\ArrayIterator<string, StreamProviderArrayIteratorValue>
 * @codingStandardsIgnoreEnd
 */
final class Provider implements Query, \Stringable
{
    use Traits\Conditions;
    use Traits\From;
    use Traits\Joinable;
    use Traits\Limit;
    use Traits\Select;
    use Traits\Sortable;

    private readonly Xml|Json|Yaml|Neon|Csv $stream;

    public function __construct(Xml|Json|Yaml|Neon|Csv $stream)
    {
        $this->stream = $stream;
    }

    private function hasPhase(string $phase): bool
    {
        $phaseArray = [];
        if ($this->joins !== []) {
            $phaseArray[] = 'join';
        }

        if ($this->orderings !== []) {
            $phaseArray[] = 'sort';
        }

        if ($this->limit !== null || $this->offset !== null) {
            $phaseArray[] = 'limit';
        }

        return in_array($phase, $phaseArray, true);
    }

    public function fetchAll(?string $dto = null): Generator
    {
        if ($this->hasPhase('sort')) {
            return $this->applyLimit(
                $this->applySorting(
                    $this->getResults()
                ),
                $dto
            );
        }

        return $this->getResults($dto);
    }

    public function fetchNth(int|string $n, ?string $dto = null): Generator
    {
        if (!is_int($n) && !in_array($n, ['even', 'odd'], true)) {
            throw new InvalidArgumentException(
                "Invalid parameter: $n. Allowed values are an integer, 'even', or 'odd'."
            );
        }

        $index = 0; // Record index
        foreach ($this->fetchAll($dto) as $item) {
            $matchesNth = false;

            if ($n === 'even') {
                $matchesNth = $index % 2 === 0;
            } elseif ($n === 'odd') {
                $matchesNth = $index % 2 !== 0;
            } elseif (is_int($n)) {
                $matchesNth = ($index + 1) % $n === 0;
            }

            if ($matchesNth) {
                yield $item;
            }

            $index++;
        }
    }

    public function fetch(?string $dto = null): mixed
    {
        foreach ($this->fetchAll($dto) as $item) {
            return $item;
        }
        return null;
    }

    public function exists(): bool
    {
        return $this->fetch() !== null;
    }

    public function fetchSingle(string $key): mixed
    {
        return ArrayHelper::getNestedValue($this->fetch(), $key, false);
    }

    public function count(): int
    {
        return iterator_count($this->fetchAll());
    }

    public function sum(string $key): float
    {
        $sum = 0;
        foreach ($this->fetchAll() as $item) {
            $sum += $item[$key] ?? 0;
        }
        return $sum;
    }

    public function avg(string $key, int $decimalPlaces = 2): float
    {
        return round($this->sum($key) / $this->count(), $decimalPlaces);
    }

    public function min(string $key): float
    {
        $min = PHP_INT_MAX;
        foreach ($this->fetchAll() as $item) {
            $min = min($min, $item[$key] ?? PHP_INT_MAX);
        }
        return $min;
    }

    public function max(string $key): float
    {
        $max = PHP_INT_MIN;
        foreach ($this->fetchAll() as $item) {
            $max = max($max, $item[$key] ?? PHP_INT_MIN);
        }
        return $max;
    }

    public function test(): string
    {
        return trim((string) $this);
    }

    public function __toString(): string
    {
        $queryParts = [];

        // SELECT
        $queryParts[] = $this->selectToString();
        // FROM
        $queryParts[] = $this->fromToString();
        // JOIN
        $queryParts[] = $this->joinsToString();
        // WHERE
        $queryParts[] = $this->conditionsToString('where');
        // HAVING
        $queryParts[] = $this->conditionsToString('having');
        // ORDER BY
        $queryParts[] = $this->orderByToString();
        // OFFSET
        $queryParts[] = $this->offsetToString();
        // LIMIT
        $queryParts[] = $this->limitToString();

        return implode('', $queryParts);
    }

    private function applyStreamSource(): Generator
    {
        $streamSource = $this->getFrom() === self::SELECT_ALL
            ? null
            : $this->getFrom();
        return $this->stream->getStreamGenerator($streamSource);
    }

    /**
     * @param class-string|null $dto
     * @return Generator
     */
    private function getResults(?string $dto = null): Generator
    {
        $applyLimit = $this->hasPhase('limit') && !$this->hasPhase('sort');
        $count = 0;
        $currentOffset = 0; // Number of already skipped records

        $stream = $this->hasPhase('join')
            ? $this->applyJoins($this->applyStreamSource())
            : $this->applyStreamSource();

        foreach ($stream as $item) {
            if (!$this->evaluateWhereConditions($item)) {
                continue;
            }

            $resultItem = $this->applySelect($item);
            if (!$this->evaluateHavingConditions($resultItem, $this->getAliasedFields())) {
                continue;
            }

            // Offset application
            if ($applyLimit && $this->getOffset() !== null && $currentOffset < $this->getOffset()) {
                $currentOffset++;
                continue;
            }

            if (!$this->hasPhase('sort') && $dto !== null) {
                $resultItem = ArrayHelper::mapArrayToObject($resultItem, $dto);
            }

            yield $resultItem;

            $count++;
            if ($applyLimit && $this->getLimit() !== null && $count >= $this->getLimit()) {
                break;
            }
        }
    }
}
