<?php

namespace FQL\Results;

/**
 * @phpstan-import-type StreamProviderArrayIteratorValue from Stream
*/
class InMemory extends ResultsProvider
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

    /**
     * @return \ArrayIterator<int, StreamProviderArrayIteratorValue>
     */
    public function getIterator(): \Traversable
    {
        return $this->data;
    }
}
