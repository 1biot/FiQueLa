<?php

namespace UQL\Results;

use UQL\Exceptions\InvalidArgumentException;
use UQL\Functions;
use UQL\Helpers\ArrayHelper;

/**
 * @phpstan-import-type StreamProviderArrayIteratorValue from Stream
 * @implements \IteratorAggregate<StreamProviderArrayIteratorValue>
 */
abstract class ResultsProvider implements Results, \IteratorAggregate
{
    public function fetchAll(?string $dto = null): \Generator
    {
        foreach ($this->getIterator() as $resultItem) {
            if ($dto !== null) {
                $resultItem = ArrayHelper::mapArrayToObject($resultItem, $dto);
            }
            yield $resultItem;
        }
    }

    public function fetchNth(int|string $n, ?string $dto = null): \Generator
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
        $sum = new Functions\Sum($key);
        return $sum(iterator_to_array($this->fetchAll()));
    }

    public function avg(string $key, int $decimalPlaces = 2): float
    {
        $avg = new Functions\Avg($key);
        return round($avg(iterator_to_array($this->fetchAll())), $decimalPlaces);
    }

    public function min(string $key): float
    {
        $min = new Functions\Min($key);
        return $min(iterator_to_array($this->fetchAll()));
    }

    public function max(string $key): float
    {
        $max = new Functions\Max($key);
        return $max(iterator_to_array($this->fetchAll()));
    }

    public function getProxy(): Proxy
    {
        return new Proxy(iterator_to_array($this->getIterator()));
    }
}
