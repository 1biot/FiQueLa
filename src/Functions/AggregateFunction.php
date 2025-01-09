<?php

namespace UQL\Functions;

use UQL\Stream\ArrayStreamProvider;
use UQL\Traits\Helpers\StringOperations;

/**
 * @phpstan-import-type StreamProviderArrayIteratorValue from ArrayStreamProvider
 */
abstract class AggregateFunction implements InvokableAggregate, \Stringable
{
    use StringOperations;

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
     */
    protected function getFieldValue(string $field, array $item): mixed
    {
        return $item[$field] ?? null;
    }
}
