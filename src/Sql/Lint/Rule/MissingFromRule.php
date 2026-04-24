<?php

namespace FQL\Sql\Lint\Rule;

use FQL\Sql\Lint\LintContext;
use FQL\Sql\Lint\LintIssue;
use FQL\Sql\Lint\Rule;
use FQL\Sql\Lint\Severity;

/**
 * Warns when a SELECT statement has no FROM clause. Not promoted to an error
 * because FQL supports `Sql\Compiler::applyTo($existingQuery)` where FROM is
 * intentionally omitted — the missing clause only becomes fatal during
 * stand-alone `build()`.
 */
final class MissingFromRule implements Rule
{
    public function id(): string
    {
        return 'missing-from';
    }

    public function severity(): Severity
    {
        return Severity::WARNING;
    }

    public function check(LintContext $ctx): array
    {
        if ($ctx->ast->from !== null) {
            return [];
        }
        return [
            new LintIssue(
                $this->severity(),
                $this->id(),
                'SELECT statement is missing a FROM clause. '
                . 'This only works when the statement is applied to an existing Query via Compiler::applyTo().',
                null
            ),
        ];
    }
}
