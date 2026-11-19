<?php

namespace FQL\Sql\Lint\Rule;

use FQL\Sql\Ast\Expression\FunctionCallNode;
use FQL\Sql\Lint\AstWalker;
use FQL\Sql\Lint\LintContext;
use FQL\Sql\Lint\LintIssue;
use FQL\Sql\Lint\Rule;
use FQL\Sql\Lint\Severity;
use FQL\Sql\Runtime\FunctionInvoker;
use FQL\Sql\Token\Span;

/**
 * Flags every `FunctionCallNode` whose name isn't known to the runtime
 * {@see FunctionInvoker} (neither scalar nor aggregate). The parser accepts
 * any identifier followed by `(...)` as a function call, so the only way to
 * catch typos like `LOEWR(name)` pre-execution is to consult the dispatch
 * table.
 *
 * Span: the function name. Params: `['function' => NAME]` (upper-cased).
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
                $call->position ?? null,
                self::nameSpan($call, $ctx->source),
                ['function' => $call->name]
            );
        }
        return $issues;
    }

    /**
     * Span of the function name (`LOEWR` in `LOEWR(name)`), or of the whole call when
     * the name cannot be found at its start.
     */
    private static function nameSpan(FunctionCallNode $call, string $sql): ?Span
    {
        $span = $call->span;
        if ($span === null) {
            return null;
        }
        $length = strlen($call->name);
        if ($length <= $span->length() && strcasecmp(substr($sql, $span->start, $length), $call->name) === 0) {
            return new Span($span->start, $span->start + $length);
        }
        return $span;
    }
}
