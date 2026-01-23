<?php

namespace FQL\Interface;

use FQL\Stream\ArrayStreamProvider;

/**
 * @phpstan-import-type StreamProviderArrayIteratorValue from ArrayStreamProvider
 */
interface IncrementalAggregate
{
    public function initAccumulator(): mixed;

    /**
     * @param StreamProviderArrayIteratorValue $item
     */
    public function accumulate(mixed $accumulator, array $item): mixed;

    public function finalize(mixed $accumulator): mixed;
}
