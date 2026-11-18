<?php

namespace FQL\Sql\Printer;

use FQL\Exception\InvalidArgumentException;
use FQL\Sql\Ast\AstNode;

/**
 * Thrown when an AST cannot be written as FQL text that parses back to the same tree,
 * e.g. an identifier containing a backtick, a string containing both quote characters,
 * `(a + b) * c` (FQL has no parentheses in arithmetic) or an included SELECT field
 * after an excluded one.
 */
final class UnprintableNodeException extends InvalidArgumentException
{
    public function __construct(string $message, public readonly ?AstNode $node = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
