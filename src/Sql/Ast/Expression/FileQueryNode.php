<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Query\FileQuery;
use FQL\Sql\Token\Position;

final readonly class FileQueryNode implements ExpressionNode
{
    public function __construct(
        public FileQuery $fileQuery,
        public string $raw,
        public Position $position
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }
}
