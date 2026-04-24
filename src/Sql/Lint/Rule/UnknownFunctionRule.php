<?php

namespace FQL\Sql\Lint\Rule;

use FQL\Sql\Ast\Expression\FunctionCallNode;
use FQL\Sql\Lint\AstWalker;
use FQL\Sql\Lint\LintContext;
use FQL\Sql\Lint\LintIssue;
use FQL\Sql\Lint\Rule;
use FQL\Sql\Lint\Severity;
use FQL\Sql\Runtime\FunctionInvoker;

/**
 * Flags every `FunctionCallNode` whose name isn't known to the runtime
 * {@see FunctionInvoker} (neither scalar nor aggregate). The parser accepts
 * any identifier followed by `(...)` as a function call, so the only way to
 * catch typos like `LOEWR(name)` pre-execution is to consult the dispatch
 * table.
 */
final class UnknownFunctionRule implements Rule
{
    public function __construct(private readonly FunctionInvoker $invoker = new FunctionInvoker())
    {
    }

    public function id(): string
    {
        return 'unknown-function';
    }

    public function severity(): Severity
    {
        return Severity::ERROR;
    }

    public function check(LintContext $ctx): array
    {
        $issues = [];
        foreach (AstWalker::findAll($ctx->ast, FunctionCallNode::class) as $call) {
            if ($this->invoker->has($call->name) || $this->invoker->isAggregate($call->name)) {
                continue;
            }
            $issues[] = new LintIssue(
                $this->severity(),
                $this->id(),
                sprintf('Unknown function "%s".', $call->name),
                $call->position ?? null
            );
        }
        return $issues;
    }
}
