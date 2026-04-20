<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Sql\Ast\Node\SelectStatementNode;
use FQL\Sql\Token\Position;

final readonly class SubQueryNode implements ExpressionNode
{
    public function __construct(
        public SelectStatementNode $query,
        public Position $position
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
