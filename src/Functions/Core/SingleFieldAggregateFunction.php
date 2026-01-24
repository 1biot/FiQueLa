<?php

namespace FQL\Functions\Core;

use FQL\Exception\UnexpectedValueException;
use FQL\Interface\Query;
use FQL\Stream\ArrayStreamProvider;

/**
 * @phpstan-import-type StreamProviderArrayIteratorValue from ArrayStreamProvider
 */
abstract class SingleFieldAggregateFunction extends AggregateFunction
{
    /**
     * @var array<string, bool>
     */
    protected array $distinctSeen = [];

    public function __construct(
        protected readonly string $field,
        protected readonly bool $distinct = false
    ) {
    }

    /**
     * @return array<string, bool>
     */
    protected function resetDistinctSeen(): array
    {
        $this->distinctSeen = [];
        return $this->distinctSeen;
    }

    /**
     * @param array<string, bool> $seen
     */
    protected function isDistinctValue(mixed $value, array &$seen): bool
    {
        if (!$this->distinct) {
            return true;
        }

        $hash = md5(serialize($value));
        if (isset($seen[$hash])) {
            return false;
        }

        $seen[$hash] = true;
        return true;
    }

    /**
     * @throws UnexpectedValueException
     */
    public function __toString(): string
    {
        $distinct = $this->distinct ? Query::DISTINCT . ' ' : '';
        return sprintf(
            '%s(%s%s)',
            $this->getName(),
            $distinct,
            $this->field
        );
    }
}
