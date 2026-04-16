<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Sql\Token\Position;

final readonly class WhenBranchNode implements ExpressionNode
{
    public function __construct(
        public ConditionGroupNode $condition,
        public ExpressionNode $then,
        public Position $position
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
