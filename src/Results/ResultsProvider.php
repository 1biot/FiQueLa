<?php

namespace FQL\Results;

use FQL\Exception\InvalidArgumentException;
use FQL\Functions;
use FQL\Interface\Results;
use FQL\Query\FileQuery;
use FQL\Stream\Writers\WriterFactory;
use FQL\Traits\Helpers\EnhancedNestedArrayAccessor;

/**
 * @phpstan-import-type StreamProviderArrayIteratorValue from Stream
 * @implements \IteratorAggregate<StreamProviderArrayIteratorValue>
 */
abstract class ResultsProvider implements Results, \IteratorAggregate
{
    use EnhancedNestedArrayAccessor;

    protected int $lastIntoWriteCount = 0;

    /**
     * @param class-string|null $dto
     * @return \Generator<StreamProviderArrayIteratorValue|object>
     */
    public function fetchAll(?string $dto = null): \Generator
    {
        foreach ($this->getIterator() as $resultItem) {
            yield $this->mapArrayToObject($resultItem, $dto);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    /**
     * @param class-string|null $dto
     * @return \Generator<StreamProviderArrayIteratorValue|object>
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

    /**
     * @param class-string|null $dto
     * @return StreamProviderArrayIteratorValue|object|null
     */
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
        $item = $this->fetch();
        if (!is_array($item)) {
            return null;
        }

        return $this->accessNestedValue($item, $key, false);
    }

    public function count(): int
    {
        return iterator_count($this->getIterator());
    }

    /**
     * Sum of the numeric values of `$key`; values that are not numeric count as 0,
     * so an empty result (or one without numeric values) sums to 0.
     */
    public function sum(string $key): float
    {
        $sum = 0;
        foreach ($this->getIterator() as $item) {
            $value = $item[$key] ?? 0;
            $sum += is_numeric($value) ? $value : 0;
        }

        return $sum;
    }

    /**
     * Sum of the numeric values divided by the number of rows, rounded. Null for an
     * empty result or when no value of `$key` is numeric.
     */
    public function avg(string $key, int $decimalPlaces = 2): ?float
    {
        $average = $this->average($key);
        return $average === null ? null : round($average, $decimalPlaces);
    }

    /**
     * Smallest numeric value of `$key`; values that are not numeric (also `null` and
     * a missing key) are skipped. Null for an empty result or when no value is numeric.
     */
    public function min(string $key): ?float
    {
        $min = null;
        foreach ($this->numericValues($key) as $value) {
            $min = $min === null ? $value : min($min, $value);
        }

        return $min;
    }

    /**
     * Largest numeric value of `$key`; values that are not numeric (also `null` and
     * a missing key) are skipped. Null for an empty result or when no value is numeric.
     */
    public function max(string $key): ?float
    {
        $max = null;
        foreach ($this->numericValues($key) as $value) {
            $max = $max === null ? $value : max($max, $value);
        }

        return $max;
    }

    /**
     * Unrounded {@see avg()} in one pass over the rows.
     */
    protected function average(string $key): ?float
    {
        $sum = 0;
        $rows = 0;
        $hasNumeric = false;
        foreach ($this->getIterator() as $item) {
            $rows++;
            $value = $item[$key] ?? null;
            if (is_numeric($value)) {
                $sum += $value;
                $hasNumeric = true;
            }
        }

        return $hasNumeric ? $sum / $rows : null;
    }

    /**
     * @return \Generator<float>
     */
    private function numericValues(string $key): \Generator
    {
        foreach ($this->getIterator() as $item) {
            $value = $item[$key] ?? null;
            if (is_numeric($value)) {
                yield (float) $value;
            }
        }
    }

    /**
     * @throws \FQL\Exception\FileQueryException
     * @throws \FQL\Exception\InvalidFormatException
     */
    public function into(FileQuery|string $fileQuery): ?FileQuery
    {
        if (is_string($fileQuery)) {
            $fileQuery = new FileQuery($fileQuery);
        }

        $this->lastIntoWriteCount = 0;

        if ($fileQuery->file === null) {
            throw new InvalidArgumentException('Missing target file in INTO file query.');
        }

        $dir = dirname($fileQuery->file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $writer = WriterFactory::create($fileQuery);
        try {
            foreach ($this->getIterator() as $row) {
                $writerRow = [];
                foreach ($row as $key => $value) {
                    if (is_string($key)) {
                        $writerRow[$key] = $value;
                    }
                }

                $writer->write($writerRow);
                $this->lastIntoWriteCount++;
            }
        } finally {
            $writer->close();
        }

        return $writer->getFileQuery();
    }

    protected function getLastIntoWriteCount(): int
    {
        return $this->lastIntoWriteCount;
    }

    /**
     * @param StreamProviderArrayIteratorValue $data
     * @param class-string|null $className
     * @return StreamProviderArrayIteratorValue|object
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

                $instance = $reflectionClass->newInstanceArgs($constructorArgs);
                return $instance;
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
