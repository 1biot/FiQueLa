<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Sql\Ast\HasSpan;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\Span;

/**
 * Reference to a Common Table Expression from a FROM or JOIN source position.
 *
 * Produced by {@see \FQL\Sql\Parser\FromClauseParser} when an identifier in a
 * source position matches a name declared by an enclosing WITH clause. The
 * builder resolves the reference against the per-build CTE registry.
 */
final readonly class CteReferenceNode implements ExpressionNode, HasSpan
{
    public function __construct(
        public string $name,
        public Position $position,
        public ?Span $span = null
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }

    public function span(): ?Span
    {
        return $this->span;
    }
}
