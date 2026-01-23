<?php

namespace FQL\Functions\Core;

use FQL\Exception\UnexpectedValueException;
use FQL\Interface\IncrementalAggregate;
use FQL\Interface\InvokableAggregate;
use FQL\Stream\ArrayStreamProvider;
use FQL\Traits\Helpers\EnhancedNestedArrayAccessor;
use FQL\Traits\Helpers\StringOperations;

/**
 * @phpstan-import-type StreamProviderArrayIteratorValue from ArrayStreamProvider
 */
abstract class AggregateFunction implements InvokableAggregate, IncrementalAggregate, \Stringable
{
    use StringOperations;
    use EnhancedNestedArrayAccessor;

    /**
     * @throws UnexpectedValueException
     */
    public function getName(): string
    {
        $array = preg_split('/\\\/', $this::class);
        if ($array === false) {
            throw new UnexpectedValueException('Cannot split class name');
        }

        $functionName = end($array);
        return $this->camelCaseToUpperSnakeCase($functionName === false ? '' : $functionName);
    }

    /**
     * @param StreamProviderArrayIteratorValue $item
     */
    protected function getFieldValue(string $field, array $item, bool $throwOnMissing = true): mixed
    {
        return $this->accessNestedValue($item, $field, $throwOnMissing);
    }
}
