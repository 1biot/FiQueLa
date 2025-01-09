<?php

namespace UQL\Functions;

use UQL\Helpers\ArrayHelper;
use UQL\Helpers\StringHelper;
use UQL\Stream\ArrayStreamProvider;

/**
 * @phpstan-import-type StreamProviderArrayIteratorValue from ArrayStreamProvider
 */
abstract class AggregateFunction implements InvokableAggregate, \Stringable
{
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
     */
    protected function getFieldValue(string $field, array $item): mixed
    {
        return $item[$field] ?? null;
    }
}
