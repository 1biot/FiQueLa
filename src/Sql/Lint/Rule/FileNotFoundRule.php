<?php

namespace FQL\Sql\Lint\Rule;

use FQL\Sql\Ast\Expression\FileQueryNode;
use FQL\Sql\Lint\AstWalker;
use FQL\Sql\Lint\LintContext;
use FQL\Sql\Lint\LintIssue;
use FQL\Sql\Lint\Rule;
use FQL\Sql\Lint\Severity;

/**
 * Opt-in rule (driven by `LintContext::$checkFilesystem`) that verifies every
 * referenced source file exists and is readable. The parser validates the
 * FileQuery format + params, but never touches the filesystem — typos in
 * file paths only surface at execution time. Enabling this rule during CI /
 * local dev catches them earlier.
 */
final class FileNotFoundRule implements Rule
{
    public function id(): string
    {
        return 'file-not-found';
    }

    public function severity(): Severity
    {
        return Severity::ERROR;
    }

    public function check(LintContext $ctx): array
    {
        if (!$ctx->checkFilesystem) {
            return [];
        }
        $issues = [];
        foreach (AstWalker::findAll($ctx->ast, FileQueryNode::class) as $node) {
            $path = $node->fileQuery->file;
            if ($path === null || $path === '') {
                continue;
            }
            if (!file_exists($path) || !is_readable($path)) {
                $issues[] = new LintIssue(
                    $this->severity(),
                    $this->id(),
                    sprintf('File not found or not readable: "%s".', $path),
                    $node->position
                );
            }
        }
        return $issues;
    }
}
