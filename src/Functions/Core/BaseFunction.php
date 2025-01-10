<?php

namespace UQL\Functions\Core;

use UQL\Exceptions\InvalidArgumentException;
use UQL\Exceptions\UnexpectedValueException;
use UQL\Stream\ArrayStreamProvider;
use UQL\Traits\Helpers\NestedArrayAccessor;
use UQL\Traits\Helpers\StringOperations;

/**
 * @phpstan-import-type StreamProviderArrayIteratorValue from ArrayStreamProvider
 */
abstract class BaseFunction implements Invokable, \Stringable
{
    use StringOperations;
    use NestedArrayAccessor;

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
     * @param StreamProviderArrayIteratorValue $resultItem
     * @throws InvalidArgumentException
     */
    protected function getFieldValue(string $field, array $item, array $resultItem): mixed
    {
        return $this->accessNestedValue($item, $field, false) ?? $resultItem[$field] ?? null;
    }
}
