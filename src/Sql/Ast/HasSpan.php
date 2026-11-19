<?php

namespace FQL\Sql\Ast;

use FQL\Sql\Token\Span;

/**
 * Implemented by every AST node class of the library: exposes the node's source range.
 *
 * Kept separate from {@see AstNode} so third-party `AstNode` / `ExpressionNode`
 * implementations stay compatible. A node has a span only when the parser produced
 * it; nodes built in code (and `with…()` copies) return null.
 */
interface HasSpan
{
    public function span(): ?Span;
}
