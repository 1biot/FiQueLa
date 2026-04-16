<?php

namespace FQL\Sql\Ast\Expression;

use FQL\Sql\Ast\AstNode;

/**
 * Marker for nodes that can appear inside expression contexts
 * (SELECT field, function argument, condition operand, ORDER BY/GROUP BY field).
 */
interface ExpressionNode extends AstNode
{
}
