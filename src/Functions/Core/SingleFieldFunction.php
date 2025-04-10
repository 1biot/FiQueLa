<?php

namespace FQL\Functions\Core;

use FQL\Exception\UnexpectedValueException;
use FQL\Stream\ArrayStreamProvider;

/**
 * @phpstan-import-type StreamProviderArrayIteratorValue from ArrayStreamProvider
 */
abstract class SingleFieldFunction extends BaseFunction
{
    public function __construct(protected readonly string $field)
    {
    }

    /**
     * @throws UnexpectedValueException
     */
    public function __toString(): string
    {
        return sprintf(
            '%s(%s)',
            $this->getName(),
            $this->field
        );
    }
}
