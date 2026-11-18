<?php

namespace FQL\Sql\Ast\Node;

use FQL\Sql\Ast\AstNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\HasSpan;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\Span;

final readonly class GroupByClauseNode implements AstNode, HasSpan
{
    /**
     * @param ExpressionNode[] $fields
     */
    public function __construct(
        public array $fields,
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
     * Copy with another `fields` (and no span: it no longer matches the source).
     *
     * @param ExpressionNode[] $fields
     */
    public function withFields(array $fields): self
    {
        return new self(
            $fields,
            $this->position
        );
    }
}
