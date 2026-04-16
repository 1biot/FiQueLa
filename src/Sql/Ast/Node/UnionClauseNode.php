<?php

namespace FQL\Sql\Ast\Node;

use FQL\Sql\Ast\AstNode;
use FQL\Sql\Token\Position;

final readonly class UnionClauseNode implements AstNode
{
    public function __construct(
        public SelectStatementNode $query,
        public bool $all,
        public Position $position
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
