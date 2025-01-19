<?php

namespace FQL\Stream;

use FQL\Exceptions;
use FQL\Interfaces;

/**
 * @phpstan-type StreamProviderArrayIteratorValue array<int|string, array<int|string, mixed>|scalar|null>
 * @codingStandardsIgnoreStart
 * @phpstan-type StreamProviderArrayIterator \ArrayIterator<int|string, StreamProviderArrayIteratorValue>|\ArrayIterator<int, StreamProviderArrayIteratorValue>|\ArrayIterator<string, StreamProviderArrayIteratorValue>
 * @codingStandardsIgnoreEnd
 */
abstract class ArrayStreamProvider extends StreamProvider
{
    /**
     * @param StreamProviderArrayIterator $stream
     */
    protected function __construct(private \ArrayIterator $stream)
    {
    }

    /**
     * @param string|null $query
     * @return StreamProviderArrayIterator|null
     * @throws Exceptions\InvalidArgumentException
     */
    public function getStream(?string $query): ?\ArrayIterator
    {
        $keys = $query !== null ? explode('.', $query) : [];
        $lastKey = array_key_last($keys);
        $stream = $this->stream;
        foreach ($keys as $index => $key) {
            $stream = $this->applyKeyFilter($stream, $key, ($index === $lastKey));
        }
        return $stream;
    }

    /**
     * @throws Exceptions\InvalidArgumentException
     */
    public function getStreamGenerator(?string $query): ?\Generator
    {
        $stream = $this->getStream($query);
        foreach ($stream as $item) {
            yield $item;
        }
    }

    /**
     * @param \ArrayIterator<int|string, mixed> $stream
     * @param string $key
     * @param bool $isLast
     * @return StreamProviderArrayIterator
     * @throws Exceptions\InvalidArgumentException
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
