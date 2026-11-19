<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Query\FileQuery;
use FQL\Sql\Ast\HasSpan;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\Span;

final readonly class FileQueryNode implements ExpressionNode, HasSpan
{
    public function __construct(
        public FileQuery $fileQuery,
        public string $raw,
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
     * Copy with another file query. Without `$raw` the printers write the source from
     * the {@see FileQuery} (all non-default parameters); pass `$raw` to choose the text.
     */
    public function withFileQuery(FileQuery $fileQuery, string $raw = ''): self
    {
        return new self($fileQuery, $raw, $this->position);
    }
}
