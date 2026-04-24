<?php

namespace FQL\Sql\Ast\Node;

use FQL\Sql\Ast\AstNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Token\Position;

final readonly class SelectFieldNode implements AstNode
{
    public function __construct(
        public ExpressionNode $expression,
        public ?string $alias,
        public bool $excluded,
        public Position $position
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
