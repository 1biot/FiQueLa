<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Sql\Token\Position;

/**
 * Resolves to the entire source row when evaluated.
 *
 * Used as the `spec.expression` of aggregates that need to inspect the whole
 * row rather than a single pre-evaluated value (currently `COLLECT_OBJECT`).
 * The Stream grouping pipeline routes every aggregate through
 * `ExpressionEvaluator::evaluate(spec.expression, $item)` and hands the result
 * to `accumulate()` — by using this node as the expression, the aggregate's
 * `accumulate()` receives the source `$item` array as its value.
 *
 * Not produced by the parser; only by the fluent / SQL-string aggregate setup
 * inside {@see \FQL\Traits\Select::storeAggregate()}.
 */
final readonly class WholeRowNode implements ExpressionNode
{
    public function __construct(public Position $position)
    {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
