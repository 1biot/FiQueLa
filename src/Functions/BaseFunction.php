<?php

namespace UQL\Functions;

use UQL\Helpers\ArrayHelper;
use UQL\Helpers\StringHelper;
use UQL\Stream\ArrayStreamProvider;

/**
 * @phpstan-import-type StreamProviderArrayIteratorValue from ArrayStreamProvider
 */
abstract class BaseFunction implements Invokable, \Stringable
{
    public function isAggregate(): bool
    {
        return false;
    }

    public function getName(): string
    {
        $array = preg_split('/\\\/', $this::class);
        if ($array === false) {
            throw new \RuntimeException('Cannot split class name');
        }

        return StringHelper::camelCaseToUpperSnakeCase(end($array));
    }

    /**
     * @param StreamProviderArrayIteratorValue $item
     * @param StreamProviderArrayIteratorValue $resultItem
     */
    protected function getFieldValue(string $field, array $item, array $resultItem): mixed
    {
        return ArrayHelper::getNestedValue($item, $field, false) ?? $resultItem[$field] ?? null;
    }
}
