<?php

namespace FQL\Functions\Core;

use FQL\Exception\InvalidArgumentException;
use FQL\Exception\UnexpectedValueException;
use FQL\Interface\Invokable;
use FQL\Stream\ArrayStreamProvider;
use FQL\Traits\Helpers\NestedArrayAccessor;
use FQL\Traits\Helpers\StringOperations;

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
