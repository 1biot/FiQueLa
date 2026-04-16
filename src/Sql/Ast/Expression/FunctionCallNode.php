<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Sql\Token\Position;

final readonly class FunctionCallNode implements ExpressionNode
{
    /**
     * @param ExpressionNode[] $arguments
     */
    public function __construct(
        public string $name,
        public array $arguments,
        public bool $distinct,
        public Position $position
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
