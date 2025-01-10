<?php

namespace UQL\Functions\Core;

use UQL\Exceptions\UnexpectedValueException;
use UQL\Stream\ArrayStreamProvider;

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
