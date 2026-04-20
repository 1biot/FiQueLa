<?php

namespace FQL\Sql\Ast\Node;

use FQL\Sql\Ast\AstNode;
use FQL\Sql\Ast\Expression\ConditionGroupNode;
use FQL\Sql\Token\Position;

final readonly class WhereClauseNode implements AstNode
{
    public function __construct(
        public ConditionGroupNode $conditions,
        public Position $position
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
