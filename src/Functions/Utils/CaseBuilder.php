<?php

namespace FQL\Functions\Utils;

use FQL\Sql\Ast\Expression\CaseExpressionNode;
use FQL\Sql\Ast\Expression\ConditionGroupNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\Expression\WhenBranchNode;
use FQL\Sql\Token\Position;

/**
 * Fluent accumulator for `case()->whenCase()->elseCase()->endCase()` chains on
 * `Traits\Select`. Each `whenCase(cond, then)` adds a parsed `WhenBranchNode`;
 * `elseCase(expr)` records the default branch; `build()` emits a ready
 * `CaseExpressionNode` that the trait hands to `addExpressionInternal()`.
 *
 * Not registered in the function registry — this is pure fluent sugar and the
 * final result is a plain AST node consumed by the runtime evaluator.
 */
final class CaseBuilder
{
    /** @var WhenBranchNode[] */
    private array $whens = [];

    private ?ExpressionNode $else = null;

    public function when(ConditionGroupNode $condition, ExpressionNode $then): void
    {
        $this->whens[] = new WhenBranchNode($condition, $then, Position::synthetic());
    }

    public function setElse(ExpressionNode $else): void
    {
        $this->else = $else;
    }

    public function hasWhens(): bool
    {
        return $this->whens !== [];
    }

    public function hasElse(): bool
    {
        return $this->else !== null;
    }

    public function build(): CaseExpressionNode
    {
        return new CaseExpressionNode($this->whens, $this->else, Position::synthetic());
    }
}
