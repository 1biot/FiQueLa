<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Sql\Ast\HasSpan;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\Span;

final readonly class FunctionCallNode implements ExpressionNode, HasSpan
{
    /**
     * @param ExpressionNode[] $arguments
     */
    public function __construct(
        public string $name,
        public array $arguments,
        public bool $distinct,
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
     * Copy with another `arguments` (and no span: it no longer matches the source).
     *
     * @param ExpressionNode[] $arguments
     */
    public function withArguments(array $arguments): self
    {
        return new self(
            $this->name,
            $arguments,
            $this->distinct,
            $this->position
        );
    }
}
