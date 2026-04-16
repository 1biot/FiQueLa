<?php

namespace FQL\Sql\Ast\Node;

use FQL\Enum\Sort;
use FQL\Sql\Ast\AstNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Token\Position;

final readonly class OrderByItemNode implements AstNode
{
    public function __construct(
        public ExpressionNode $expression,
        public Sort $direction,
        public Position $position
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
