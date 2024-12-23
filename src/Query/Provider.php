<?php

namespace UQL\Query;

use ArrayIterator;
use Generator;
use UQL\Enum\LogicalOperator;
use UQL\Enum\Sort;
use UQL\Exceptions\InvalidArgumentException;
use UQL\Helpers\ArrayHelper;
use UQL\Stream\ArrayStreamProvider;
use UQL\Stream\Json;
use UQL\Stream\Neon;
use UQL\Stream\Stream;
use UQL\Stream\StreamProvider;
use UQL\Stream\Xml;
use UQL\Stream\XmlProvider;
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
    use Traits\Limit;
    use Traits\Select;

    private readonly Xml|Json|Yaml|Neon $stream;

    /** @var StreamProviderArrayIterator|\Generator $streamData */
    private \Generator|ArrayIterator $streamData;

    public function __construct(Xml|Json|Yaml|Neon $stream)
    {
        $this->stream = $stream;
    }

    public function orderBy(string $key, Sort $direction = Sort::ASC): Query
    {
        $data = iterator_to_array($this->fetchAll());

        usort($data, function ($a, $b) use ($key, $direction) {
            $valA = $a[$key] ?? null;
            $valB = $b[$key] ?? null;
            $comparison = $valA <=> $valB;
            return $direction->value === Sort::ASC->value ? $comparison : -$comparison;
        });

        /** @var ArrayIterator<int|string, mixed> $iterator */
        $iterator = new ArrayIterator($data);
        $this->streamData = $iterator;

        return $this;
    }

    public function fetchAll(?string $dto = null): Generator
    {
        $count = 0;
        $currentOffset = 0; // Number of already skipped records

        $this->applyStreamSource();
        foreach ($this->streamData as $item) {
            if ($this->filterStream($item)) {
                // Offset application
                if ($this->getOffset() !== null && $currentOffset < $this->getOffset()) {
                    $currentOffset++;
                    continue;
                }

                $item = $this->applySelect($item);
                if ($dto !== null) {
                    $item = new $dto($item);
                }

                yield $item;

                $count++;
                if ($this->getLimit() !== null && $count >= $this->getLimit()) {
                    break;
                }
            }
        }
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

    public function test(): string
    {
        return (string) $this;
    }

    public function __toString(): string
    {
        $queryParts = [];

        // SELECT
        $queryParts[] = $this->selectToString();
        // FROM
        $queryParts[] = $this->fromToString();
        // WHERE
        $queryParts[] = $this->conditionsToString();
        // OFFSET
        $queryParts[] = $this->offsetToString();
        // LIMIT
        $queryParts[] = $this->limitToString();

        return implode(' ', $queryParts);
    }

    /**
     * @param mixed $item
     * @return bool
     */
    private function filterStream(mixed $item): bool
    {
        $this->flushGroup();
        $result = null;

        foreach ($this->conditions as $condition) {
            if (isset($condition['group'])) {
                $matches = $this->evaluateGroup($item, $condition['group']);
            } else {
                $matches = $this->evaluateConditionWithIteration($item, $condition);
            }

            if ($condition['type'] === LogicalOperator::AND) {
                $result = $result === null ? $matches : $result && $matches;
            } elseif ($condition['type'] === LogicalOperator::OR) {
                $result = $result === null ? $matches : $result || $matches;
            }
        }

        return $result ?? true;
    }

    private function applyStreamSource(): void
    {
        if (!isset($this->streamData)) {
            $streamSource = $this->getFrom() === self::SELECT_ALL
                ? null
                : $this->getFrom();
            $this->streamData = $this->stream->getStream($streamSource);
        }
    }
}
