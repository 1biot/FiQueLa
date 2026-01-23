<?php

namespace FQL\Stream;

use FQL\Exception;
use FQL\Interface;

/**
 * @codingStandardsIgnoreStart
 * @phpstan-type StreamProviderArrayIteratorValue array<int|string, array<int|string, mixed>|scalar|null>
 * @phpstan-type StreamProviderArrayIterator \ArrayIterator<int|string, StreamProviderArrayIteratorValue>|\ArrayIterator<int, StreamProviderArrayIteratorValue>|\ArrayIterator<string, StreamProviderArrayIteratorValue>
 * @codingStandardsIgnoreEnd
 */
abstract class ArrayStreamProvider extends AbstractStream
{
    /**
     * @param StreamProviderArrayIterator $stream
     */
    public function __construct(protected \ArrayIterator $stream)
    {
    }

    /**
     * @param string|null $query
     * @return StreamProviderArrayIterator
     * @throws Exception\InvalidArgumentException
     */
    public function getStream(?string $query): \ArrayIterator
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
     * @throws Exception\InvalidArgumentException
     */
    public function getStreamGenerator(?string $query): \Generator
    {
        $stream = $this->getStream($query);
        foreach ($stream as $item) {
            yield $item;
        }
    }

    /**
     * @param StreamProviderArrayIterator $stream
     * @param string $key
     * @param bool $isLast
     * @return StreamProviderArrayIterator
     * @throws Exception\InvalidArgumentException
     */
    protected function applyKeyFilter(\ArrayIterator $stream, string $key, bool $isLast): \ArrayIterator
    {
        foreach ($stream as $k => $v) {
            if ($k === $key) {
                if ($isLast) {
                    if (array_is_list($v)) {
                        /** @var StreamProviderArrayIteratorValue[] $items */
                        $items = [];
                        foreach ($v as $entry) {
                            /** @var StreamProviderArrayIteratorValue $item */
                            $item = is_array($entry) ? $entry : [$key => $entry];
                            $items[] = $item;
                        }

                        return new \ArrayIterator($items);
                    }

                    /** @var StreamProviderArrayIteratorValue $item */
                    $item = $v;
                    return new \ArrayIterator([$item]);
                }

                // Other iterations: always return an iterator
                /** @var StreamProviderArrayIteratorValue[] $items */
                $items = $v;
                return new \ArrayIterator($items);
            }
        }
        throw new Exception\InvalidArgumentException("Key '$key' not found.");
    }
}
