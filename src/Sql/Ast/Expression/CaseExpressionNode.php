<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Sql\Token\Position;

final readonly class CaseExpressionNode implements ExpressionNode
{
    /**
     * @param WhenBranchNode[] $branches
     */
    public function __construct(
        public array $branches,
        public ?ExpressionNode $else,
        public Position $position
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
