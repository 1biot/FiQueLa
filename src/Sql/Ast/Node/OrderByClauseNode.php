<?php

namespace FQL\Sql\Ast\Node;

use FQL\Sql\Ast\AstNode;
use FQL\Sql\Token\Position;

final readonly class OrderByClauseNode implements AstNode
{
    /**
     * @param OrderByItemNode[] $items
     */
    public function __construct(
        public array $items,
        public Position $position
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
