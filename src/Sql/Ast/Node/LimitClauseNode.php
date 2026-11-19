<?php

namespace FQL\Sql\Ast\Node;

use FQL\Sql\Ast\AstNode;
use FQL\Sql\Ast\HasSpan;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\Span;

final readonly class LimitClauseNode implements AstNode, HasSpan
{
    public function __construct(
        public int $limit,
        public ?int $offset,
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
     * Copy with another `limit` (and no span: it no longer matches the source).
     */
    public function withLimit(int $limit): self
    {
        return new self(
            $limit,
            $this->offset,
            $this->position
        );
    }

    /**
     * Copy with another `offset` (and no span: it no longer matches the source).
     */
    public function withOffset(?int $offset): self
    {
        return new self(
            $this->limit,
            $offset,
            $this->position
        );
    }
}
