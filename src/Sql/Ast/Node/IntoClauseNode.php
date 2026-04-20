<?php

namespace FQL\Sql\Ast\Node;

use FQL\Sql\Ast\AstNode;
use FQL\Sql\Ast\Expression\FileQueryNode;
use FQL\Sql\Token\Position;

final readonly class IntoClauseNode implements AstNode
{
    public function __construct(
        public FileQueryNode $target,
        public Position $position
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
