<?php

namespace FQL\Sql\Ast\Node;

use FQL\Sql\Ast\AstNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\HasSpan;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\Span;

final readonly class SelectFieldNode implements AstNode, HasSpan
{
    public function __construct(
        public ExpressionNode $expression,
        public ?string $alias,
        public bool $excluded,
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
     * Copy with another `expression` (and no span: it no longer matches the source).
     */
    public function withExpression(ExpressionNode $expression): self
    {
        return new self(
            $expression,
            $this->alias,
            $this->excluded,
            $this->position
        );
    }

    /**
     * Copy with another `alias` (and no span: it no longer matches the source).
     */
    public function withAlias(?string $alias): self
    {
        return new self(
            $this->expression,
            $alias,
            $this->excluded,
            $this->position
        );
    }

    /**
     * Copy with another `excluded` (and no span: it no longer matches the source).
     */
    public function withExcluded(bool $excluded): self
    {
        return new self(
            $this->expression,
            $this->alias,
            $excluded,
            $this->position
        );
    }
}
