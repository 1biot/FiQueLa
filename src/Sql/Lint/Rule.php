<?php

namespace FQL\Sql\Lint;

/**
 * A single lint rule inspecting a parsed AST and returning zero or more
 * {@see LintIssue}. Stateless — one instance per `Linter` can be reused
 * across many checks. Rules that need filesystem access must consult
 * `LintContext::$checkFilesystem` and no-op when it's false.
 */
interface Rule
{
    /**
     * Stable machine-readable identifier (e.g. `unknown-function`) — used in
     * `LintIssue::$rule` and CLI `--rules=...` filtering.
     */
    public function id(): string;

    /**
     * Default severity for findings produced by this rule. Individual
     * issues may override via `new LintIssue(severity, …)` if the rule
     * needs finer-grained control.
     */
    public function severity(): Severity;

    /**
     * @return list<LintIssue>
     */
    public function check(LintContext $ctx): array;
}
