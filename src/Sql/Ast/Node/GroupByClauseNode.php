<?php

namespace FQL\Sql\Ast\Node;

use FQL\Sql\Ast\AstNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Token\Position;

final readonly class GroupByClauseNode implements AstNode
{
    /**
     * @param ExpressionNode[] $fields
     */
    public function __construct(
        public array $fields,
        public Position $position
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
