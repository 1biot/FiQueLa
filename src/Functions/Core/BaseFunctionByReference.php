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
abstract class BaseFunctionByReference implements InvokableByReference, \Stringable
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
     * @param StreamProviderArrayIteratorValue $resultItem
     * @throws InvalidArgumentException
     */
    protected function getFieldValue(string $field, array $item, array $resultItem): mixed
    {
        return $this->isQuoted($field)
            ? Type::matchByString($field)
            : ($this->accessNestedValue($item, $field, false)
                ?? $this->accessNestedValue($resultItem, $field, false));
    }
}
