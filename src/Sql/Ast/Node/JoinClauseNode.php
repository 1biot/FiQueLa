<?php

namespace FQL\Sql\Ast\Node;

use FQL\Sql\Ast\AstNode;
use FQL\Sql\Ast\Expression\ConditionExpressionNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\JoinType;
use FQL\Sql\Token\Position;

final readonly class JoinClauseNode implements AstNode
{
    public function __construct(
        public JoinType $type,
        public ExpressionNode $source,
        public string $alias,
        public ConditionExpressionNode $on,
        public Position $position
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
