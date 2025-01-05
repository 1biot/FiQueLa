<?php

namespace UQL\Functions;

use UQL\Helpers\ArrayHelper;
use UQL\Stream\ArrayStreamProvider;

/**
 * @phpstan-import-type StreamProviderArrayIteratorValue from ArrayStreamProvider
 */
abstract class SingleFieldFunction extends BaseFunction
{
    public function __construct(protected readonly string $field)
    {
    }

    public function __toString()
    {
        return sprintf(
            '%s(%s)',
            $this->getName(),
            $this->field
        );
    }
}
