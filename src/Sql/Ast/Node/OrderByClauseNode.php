<?php

namespace FQL\Sql\Ast\Node;

use FQL\Sql\Ast\AstNode;
use FQL\Sql\Ast\HasSpan;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\Span;

final readonly class OrderByClauseNode implements AstNode, HasSpan
{
    /**
     * @param OrderByItemNode[] $items
     */
    public function __construct(
        public array $items,
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
     * Copy with another `items` (and no span: it no longer matches the source).
     *
     * @param OrderByItemNode[] $items
     */
    public function withItems(array $items): self
    {
        return new self(
            $items,
            $this->position
        );
    }
}
