<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Enum\LogicalOperator;
use FQL\Sql\Token\Position;

/**
 * Logical group of conditions joined by AND / OR / XOR.
 *
 * Each entry is `[logical, condition]` where the first entry's `logical` is
 * by convention `LogicalOperator::AND` (it does not connect to anything).
 */
final readonly class ConditionGroupNode implements ExpressionNode
{
    /**
     * @param array<int, array{logical: LogicalOperator, condition: ConditionExpressionNode|ConditionGroupNode}> $entries
     */
    public function __construct(
        public array $entries,
        public Position $position
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
