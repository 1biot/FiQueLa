<?php

namespace FQL\Results;

use FQL\Exceptions\InvalidArgumentException;
use FQL\Functions;
use FQL\Query\Query;
use FQL\Traits\Helpers\NestedArrayAccessor;

/**
 * @phpstan-import-type StreamProviderArrayIteratorValue from Stream
 * @implements \IteratorAggregate<StreamProviderArrayIteratorValue>
 */
abstract class ResultsProvider implements Results, \IteratorAggregate
{
    use NestedArrayAccessor;

    /** @var array<string, float> */
    private array $avgCache = [];
    /** @var array<string, float> */
    private array $sumCache = [];
    private ?int $innerCounter = null;

    public function fetchAll(?string $dto = null): \Generator
    {
        $counter = 0;
        foreach ($this->getIterator() as $resultItem) {
            yield $this->mapArrayToObject($resultItem, $dto);
            $counter++;
        }

        if ($this->innerCounter === null) {
            $this->innerCounter = $counter;
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function fetchNth(int|string $n, ?string $dto = null): \Generator
    {
        if (!is_int($n) && !in_array($n, ['even', 'odd'], true)) {
            throw new InvalidArgumentException(
                "Invalid parameter: $n. Allowed values are an integer, 'even', or 'odd'."
            );
        }

        $index = 0; // Record index
        foreach ($this->getIterator() as $item) {
            $matchesNth = false;

            if ($n === 'even') {
                $matchesNth = $index % 2 === 0;
            } elseif ($n === 'odd') {
                $matchesNth = $index % 2 !== 0;
            } elseif (is_int($n)) {
                $matchesNth = ($index + 1) % $n === 0;
            }

            if ($matchesNth) {
                yield $this->mapArrayToObject($item, $dto);
            }

            $index++;
        }
    }

    public function fetch(?string $dto = null): mixed
    {
        foreach ($this->getIterator() as $item) {
            return $this->mapArrayToObject($item, $dto);
        }
        return null;
    }

    public function exists(): bool
    {
        return $this->fetch() !== null;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function fetchSingle(string $key): mixed
    {
        return $this->accessNestedValue($this->fetch(), $key, false);
    }

    public function count(): int
    {
        if ($this->innerCounter !== null) {
            return $this->innerCounter;
        }

        $this->innerCounter = iterator_count($this->getIterator());
        return $this->innerCounter;
    }

    public function sum(string $key): float
    {
        if (isset($this->sumCache[$key])) {
            return $this->sumCache[$key];
        }

        $sum = 0;
        foreach ($this->getIterator() as $item) {
            $sum += $item[$key] ?? 0;
        }

        $this->sumCache[$key] = $sum;
        return $this->sumCache[$key];
    }

    public function avg(string $key, int $decimalPlaces = 2): float
    {
        if (isset($this->avgCache[$key])) {
            return $this->avgCache[$key];
        }

        $this->avgCache[$key] = round($this->sum($key) / $this->count(), $decimalPlaces);
        return $this->avgCache[$key];
    }

    public function min(string $key): float
    {
        $min = PHP_INT_MAX;
        foreach ($this->getIterator() as $item) {
            $min = min($min, $item[$key] ?? PHP_INT_MAX);
        }
        return $min;
    }

    public function max(string $key): float
    {
        $max = PHP_INT_MIN;
        foreach ($this->getIterator() as $item) {
            $max = max($max, $item[$key] ?? PHP_INT_MIN);
        }
        return $max;
    }

    /**
     * @template T of mixed
     * @param StreamProviderArrayIteratorValue $data
     * @return ($className is class-string<T> ? T : StreamProviderArrayIteratorValue)
     */
    private function mapArrayToObject(array $data, ?string $className = null): mixed
    {
        if ($className === null) {
            return $data;
        }

        try {
            $reflectionClass = new \ReflectionClass($className);

            $constructor = $reflectionClass->getConstructor();
            if ($constructor) {
                $params = $constructor->getParameters();

                $constructorArgs = [];
                foreach ($params as $param) {
                    $paramName = $param->getName();
                    $constructorArgs[] = $data[$paramName] ?? $param->getDefaultValue();
                }

                return $reflectionClass->newInstanceArgs($constructorArgs);
            }

            $instance = $reflectionClass->newInstance();
            foreach ($data as $key => $value) {
                if (!is_string($key)) {
                    continue;
                } elseif (!$reflectionClass->hasProperty($key)) {
                    continue;
                }

                $property = $reflectionClass->getProperty($key);
                if ($property->isPublic()) {
                    $instance->$key = $value;
                }
            }

            return $instance;
        } catch (\ReflectionException $e) {
            user_error("Cannot map array to object of type $className: " . $e->getMessage(), E_USER_WARNING);
            return $data;
        }
    }
}
