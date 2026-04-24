<?php

namespace FQL\Sql\Lint;

/**
 * Ordered severity of {@see LintIssue}. `ERROR` = broken query (parse failure,
 * unknown function, duplicate alias). `WARNING` = likely a problem but tool
 * can't be 100% sure (missing FROM when query may be composed via
 * `applyTo()`). `INFO` = style/performance tip.
 */
enum Severity: string
{
    case ERROR = 'error';
    case WARNING = 'warning';
    case INFO = 'info';

    /**
     * Returns true if this severity is at least as severe as `$other`.
     * Uses the enum ordering (ERROR > WARNING > INFO).
     */
    public function isAtLeast(self $other): bool
    {
        $order = [self::ERROR->value => 3, self::WARNING->value => 2, self::INFO->value => 1];
        return $order[$this->value] >= $order[$other->value];
    }
}
