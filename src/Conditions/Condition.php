<?php

namespace FQL\Conditions;

use FQL\Enum;
use FQL\Results;

/**
 * Abstract class for conditions
 * @phpstan-import-type StreamProviderArrayIteratorValue from Results\Stream
 */
abstract class Condition
{
    public const WHERE = 'where';
    public const HAVING = 'having';

    public function __construct(public readonly Enum\LogicalOperator $logicalOperator)
    {
    }

    /**
     * @param StreamProviderArrayIteratorValue $item
     */
    abstract public function evaluate(array $item, bool $nestingValues): bool;
    abstract public function render(): string;
}
