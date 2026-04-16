<?php

namespace FQL\Sql\Token;

/**
 * Source position of a token within the original SQL string.
 *
 * `offset` is the zero-based byte offset.
 * `line` and `column` are one-based for human-friendly error reporting.
 */
final readonly class Position
{
    public function __construct(
        public int $offset,
        public int $line,
        public int $column
    ) {
    }

    public function __toString(): string
    {
        return sprintf('line %d, column %d', $this->line, $this->column);
    }
}
