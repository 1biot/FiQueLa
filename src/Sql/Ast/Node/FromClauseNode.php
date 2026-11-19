<?php

namespace FQL\Sql\Ast\Node;

use FQL\Sql\Ast\AstNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\HasSpan;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\Span;

final readonly class FromClauseNode implements AstNode, HasSpan
{
    public function __construct(
        public ExpressionNode $source,
        public ?string $alias,
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
     * Copy with another `source` (and no span: it no longer matches the source).
     */
    public function withSource(ExpressionNode $source): self
    {
        return new self(
            $source,
            $this->alias,
            $this->position
        );
    }

    /**
     * Copy with another `alias` (and no span: it no longer matches the source).
     */
    public function withAlias(?string $alias): self
    {
        return new self(
            $this->source,
            $alias,
            $this->position
        );
    }
}
