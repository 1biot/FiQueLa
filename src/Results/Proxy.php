<?php

namespace UQL\Results;

/**
 * @phpstan-import-type StreamProviderArrayIteratorValue from Stream
*/
class Proxy extends ResultsProvider
{
    /**
     * @var \ArrayIterator<int, StreamProviderArrayIteratorValue>
     */
    private \ArrayIterator $data;

    /**
     * @param array<int, StreamProviderArrayIteratorValue> $results
    */
    public function __construct(array $results)
    {
        $this->data = new \ArrayIterator($results);
    }

    public function getIterator(): \Traversable
    {
        return $this->data;
    }

    public function getProxy(): Proxy
    {
        return $this;
    }
}
