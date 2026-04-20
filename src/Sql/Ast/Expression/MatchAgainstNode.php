<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Enum\Fulltext;
use FQL\Sql\Token\Position;

final readonly class MatchAgainstNode implements ExpressionNode
{
    /**
     * @param ColumnReferenceNode[] $fields
     */
    public function __construct(
        public array $fields,
        public string $searchQuery,
        public Fulltext $mode,
        public Position $position
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
