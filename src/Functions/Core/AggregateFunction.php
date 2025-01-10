<?php

namespace UQL\Functions\Core;

use UQL\Exceptions\UnexpectedValueException;
use UQL\Stream\ArrayStreamProvider;
use UQL\Traits\Helpers\StringOperations;

/**
 * @phpstan-import-type StreamProviderArrayIteratorValue from ArrayStreamProvider
 */
abstract class AggregateFunction implements InvokableAggregate, \Stringable
{
    use StringOperations;

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
    protected function getFieldValue(string $field, array $item): mixed
    {
        return $item[$field] ?? null;
    }
}
