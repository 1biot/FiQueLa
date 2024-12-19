<?php

namespace JQL;

use ArrayIterator;
use Generator;
use JQL\Enum\LogicalOperator;
use JQL\Enum\Sort;
use JQL\Exceptions\InvalidArgumentException;
use JQL\Helpers\ArrayHelper;

final class QueryProvider implements Query
{
    use Traits\Conditions;
    use Traits\From;
    use Traits\Limit;
    use Traits\Select;

    /** @var ArrayIterator<string|int, mixed> $stream */
    private ArrayIterator $stream;

    public function __construct(private Json $json)
    {
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
        $this->stream = $iterator;

        return $this;
    }

    public function fetchAll(?string $dto = null): Generator
    {
        $count = 0;
        $currentOffset = 0; // Number of skipped records

        if (!isset($this->stream)) {
            $this->stream = $this->json->getStream($this->getStreamSource());
        }

        foreach ($this->stream as $item) {
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

    public function fetchSingle(string $key): mixed
    {
        $item = $this->fetch();
        return ArrayHelper::getNestedValue($item, $key);
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
        $queryParts = [];

        // SELECT
        $queryParts[] = $this->selectToString();
        // FROM
        $queryParts[] = $this->fromToString();
        // WHERE
        $queryParts[] = $this->conditionsToString();
        // LIMIT
        $queryParts[] = $this->limitToString();
        // OFFSET
        $queryParts[] = $this->offsetToString();

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
                $key = $condition['key'] ?? null;
                $value = $key ? ArrayHelper::getNestedValue($item, $key) : null;
                $matches = $this->evaluateCondition(
                    $value,
                    $condition['operator'],
                    $condition['value']
                );
            }

            if ($condition['type'] === LogicalOperator::AND) {
                $result = $result === null ? $matches : $result && $matches;
            } elseif ($condition['type'] === LogicalOperator::OR) {
                $result = $result === null ? $matches : $result || $matches;
            }
        }

        return $result ?? true;
    }
}
