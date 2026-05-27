<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Sql\Token\Position;

/**
 * Reference to a Common Table Expression from a FROM or JOIN source position.
 *
 * Produced by {@see \FQL\Sql\Parser\FromClauseParser} when an identifier in a
 * source position matches a name declared by an enclosing WITH clause. The
 * builder resolves the reference against the per-build CTE registry.
 */
final readonly class CteReferenceNode implements ExpressionNode
{
    public function __construct(
        public string $name,
        public Position $position
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
