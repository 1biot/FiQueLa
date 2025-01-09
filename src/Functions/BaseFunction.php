<?php

namespace UQL\Functions;

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

    public function getName(): string
    {
        $array = preg_split('/\\\/', $this::class);
        if ($array === false) {
            throw new \RuntimeException('Cannot split class name');
        }

        return $this->camelCaseToUpperSnakeCase(end($array));
    }

    /**
     * @param StreamProviderArrayIteratorValue $item
     * @param StreamProviderArrayIteratorValue $resultItem
     */
    protected function getFieldValue(string $field, array $item, array $resultItem): mixed
    {
        return $this->accessNestedValue($item, $field, false) ?? $resultItem[$field] ?? null;
    }
}
