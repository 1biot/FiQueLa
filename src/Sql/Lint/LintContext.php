<?php

namespace FQL\Sql\Lint;

use FQL\Sql\Ast\Node\SelectStatementNode;

/**
 * Read-only bundle passed to every {@see Rule::check()} call. Carries the
 * parsed AST, the original SQL source (useful for position-accurate
 * diagnostics), and linter options (e.g. whether filesystem-touching rules
 * are enabled).
 */
final readonly class LintContext
{
    public function __construct(
        public SelectStatementNode $ast,
        public string $source,
        public bool $checkFilesystem = false,
    ) {
    }
}
