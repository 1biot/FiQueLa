<?php

namespace FQL\Functions\Core;

use FQL\Enum\Type;
use FQL\Exception\InvalidArgumentException;
use FQL\Exception\UnexpectedValueException;
use FQL\Interface\InvokableByReference;
use FQL\Stream\ArrayStreamProvider;
use FQL\Traits\Helpers\EnhancedNestedArrayAccessor;
use FQL\Traits\Helpers\StringOperations;

/**
 * @phpstan-import-type StreamProviderArrayIteratorValue from ArrayStreamProvider
 */
abstract class SingleFieldFunctionByReference extends BaseFunctionByReference
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
