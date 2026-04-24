<?php

namespace FQL\Sql\Ast\Node;

use FQL\Sql\Ast\AstNode;
use FQL\Sql\Token\Position;

final readonly class LimitClauseNode implements AstNode
{
    public function __construct(
        public int $limit,
        public ?int $offset,
        public Position $position
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
