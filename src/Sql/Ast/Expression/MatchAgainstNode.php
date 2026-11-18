<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Enum\Fulltext;
use FQL\Sql\Ast\HasSpan;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\Span;

final readonly class MatchAgainstNode implements ExpressionNode, HasSpan
{
    /**
     * @param ColumnReferenceNode[] $fields
     */
    public function __construct(
        public array $fields,
        public string $searchQuery,
        public Fulltext $mode,
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
