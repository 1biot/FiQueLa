<?php

namespace FQL\Sql\Ast\Node;

use FQL\Sql\Ast\AstNode;
use FQL\Sql\Ast\Expression\ConditionGroupNode;
use FQL\Sql\Ast\HasSpan;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\Span;

final readonly class WhereClauseNode implements AstNode, HasSpan
{
    public function __construct(
        public ConditionGroupNode $conditions,
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

    /**
     * Copy with another `conditions` (and no span: it no longer matches the source).
     */
    public function withConditions(ConditionGroupNode $conditions): self
    {
        return new self(
            $conditions,
            $this->position
        );
    }
}
