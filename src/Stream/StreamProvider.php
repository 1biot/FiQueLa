<?php

namespace UQL\Stream;

use UQL\Exceptions;
use UQL\Query;

/**
 * @phpstan-type StreamProviderArrayIteratorValue array<int|string, array<int|string, mixed>|scalar|null>
 * @codingStandardsIgnoreStart
 * @phpstan-type StreamProviderArrayIterator \ArrayIterator<int|string, StreamProviderArrayIteratorValue>|\ArrayIterator<int, StreamProviderArrayIteratorValue>|\ArrayIterator<string, StreamProviderArrayIteratorValue>
 * @codingStandardsIgnoreEnd
 */
abstract class StreamProvider implements Stream
{
    /**
     * @var StreamProviderArrayIterator $stream
     */
    private \ArrayIterator $stream;

    /**
     * @param StreamProviderArrayIterator $stream
     */
    protected function __construct(\ArrayIterator $stream)
    {
        $this->setStream($stream);
    }

    /**
     * @param StreamProviderArrayIterator $stream
     */
    public function setStream(\ArrayIterator $stream): void
    {
        $this->stream = $stream;
    }

    /**
     * @param string|null $query
     * @return StreamProviderArrayIterator
     */
    public function getStream(?string $query): \ArrayIterator
    {
        $keys = $query !== null ? explode('.', $query) : [];
        $lastKey = array_key_last($keys);
        $stream = new \ArrayIterator($this->stream->getArrayCopy());
        foreach ($keys as $index => $key) {
            $stream = $this->applyKeyFilter($stream, $key, ($index === $lastKey));
        }
        return $stream;
    }

    public function query(): Query\Query
    {
        return new Query\Provider($this);
    }

    /**
     * Not implemented yet, now it just returns a fetchAll() results from Query/Query instance.
     *
     * @param string $sql
     * @return \Generator
     */
    public function sql(string $sql): \Generator
    {
        // parse SQL and return results
        // return Parser::parse($sql, $this->query())->fetchAll();
        return $this->query()->fetchAll();
    }

    /**
     * @param \ArrayIterator<int|string, mixed> $stream
     * @param string $key
     * @param bool $isLast
     * @return StreamProviderArrayIterator
     */
    protected function applyKeyFilter(\ArrayIterator $stream, string $key, bool $isLast): \ArrayIterator
    {
        foreach ($stream as $k => $v) {
            if ($k === $key) {
                if ($isLast) {
                    // Final iteration: check if it's a list
                    if (is_array($v) && array_is_list($v)) {
                        return new \ArrayIterator($v);
                    }

                    // Last iteration: check if it's a list
                    return is_array($v) ? new \ArrayIterator([$v]) : new \ArrayIterator([$key => $v]);
                }

                // Other iterations: always return an iterator
                return is_iterable($v) ? new \ArrayIterator($v) : new \ArrayIterator([$v]);
            }
        }
        throw new Exceptions\InvalidArgumentException("Key '$key' not found.");
    }
}
