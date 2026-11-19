<?php

namespace FQL\Sql\Ast\Node;

use FQL\Sql\Ast\AstNode;
use FQL\Sql\Ast\Expression\ConditionExpressionNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\JoinType;
use FQL\Sql\Ast\HasSpan;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\Span;

final readonly class JoinClauseNode implements AstNode, HasSpan
{
    public function __construct(
        public JoinType $type,
        public ExpressionNode $source,
        public string $alias,
        public ConditionExpressionNode $on,
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
     * Copy with another `type` (and no span: it no longer matches the source).
     */
    public function withType(JoinType $type): self
    {
        return new self(
            $type,
            $this->source,
            $this->alias,
            $this->on,
            $this->position
        );
    }

    /**
     * Copy with another `source` (and no span: it no longer matches the source).
     */
    public function withSource(ExpressionNode $source): self
    {
        return new self(
            $this->type,
            $source,
            $this->alias,
            $this->on,
            $this->position
        );
    }

    /**
     * Copy with another `alias` (and no span: it no longer matches the source).
     */
    public function withAlias(string $alias): self
    {
        return new self(
            $this->type,
            $this->source,
            $alias,
            $this->on,
            $this->position
        );
    }

    /**
     * Copy with another `on` (and no span: it no longer matches the source).
     */
    public function withOn(ConditionExpressionNode $on): self
    {
        return new self(
            $this->type,
            $this->source,
            $this->alias,
            $on,
            $this->position
        );
    }
}
