<?php

namespace FQL\Interfaces;

use FQL\Stream\ArrayStreamProvider;

/**
 * @phpstan-import-type StreamProviderArrayIteratorValue from ArrayStreamProvider
 */
interface InvokableAggregate
{
    public function getName(): string;

    /**
     * @param StreamProviderArrayIteratorValue[] $items
     */
    public function __invoke(array $items): mixed;
}
