<?php

namespace FQL\Sql\Ast;

use FQL\Sql\Token\Position;

/**
 * Marker interface for any node in the FQL abstract syntax tree.
 *
 * AST nodes are immutable readonly value objects produced by the parser and
 * consumed by the QueryBuildingVisitor (and any future formatter / optimizer).
 */
interface AstNode
{
    public function position(): Position;
}
