<?php

namespace FQL\Sql\Lint\Rule;

use FQL\Exception\FileQueryException;
use FQL\Exception\InvalidFormatException;
use FQL\Exception\PathOutsideBaseException;
use FQL\Sql\Ast\Expression\FileQueryNode;
use FQL\Sql\Ast\Node\IntoClauseNode;
use FQL\Sql\Lint\AstWalker;
use FQL\Sql\Lint\LintContext;
use FQL\Sql\Lint\LintIssue;
use FQL\Sql\Lint\Rule;
use FQL\Sql\Lint\Severity;
use FQL\Utils\FileQueryPathValidator;

/**
 * Opt-in rule (driven by `LintContext::$checkFilesystem`) that verifies every
 * referenced source file exists and is readable. The parser validates the
 * FileQuery format + params, but never touches the filesystem — typos in
 * file paths only surface at execution time. Enabling this rule during CI /
 * local dev catches them earlier.
 *
 * Paths resolve against `LintContext::$basePath` the same way the query builder
 * resolves them (a file outside the base is reported by {@see FileOutsideBaseRule}
 * instead), or against the working directory when no base path is given. `INTO`
 * targets are not checked: the query creates them.
 *
 * Span: the file query (`json(./data.json)`). Params: `['path' => …]` as written.
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
        foreach (self::fileQueries($ctx, into: false) as $node) {
            $path = $node->fileQuery->file;
            if ($path === null || $path === '' || self::readable($node, $path, $ctx->basePath)) {
                continue;
            }
            $issues[] = new LintIssue(
                $this->severity(),
                $this->id(),
                sprintf('File not found or not readable: "%s".', $path),
                $node->position,
                $node->span,
                ['path' => $path]
            );
        }
        return $issues;
    }

    /**
     * The file queries a statement reads from (`$into = false`) or writes to.
     *
     * @return list<FileQueryNode>
     * @internal shared with {@see FileOutsideBaseRule}
     */
    public static function fileQueries(LintContext $ctx, bool $into): array
    {
        $targets = [];
        foreach (AstWalker::findAll($ctx->ast, IntoClauseNode::class) as $clause) {
            $targets[] = $clause->target;
        }
        $nodes = [];
        foreach (AstWalker::findAll($ctx->ast, FileQueryNode::class) as $node) {
            if (in_array($node, $targets, true) === $into) {
                $nodes[] = $node;
            }
        }
        return $nodes;
    }

    private static function readable(FileQueryNode $node, string $path, ?string $basePath): bool
    {
        if ($basePath === null) {
            return file_exists($path) && is_readable($path);
        }
        try {
            $resolved = FileQueryPathValidator::validate($node->fileQuery, $basePath)->file;
        } catch (PathOutsideBaseException | FileQueryException) {
            return true; // reported by FileOutsideBaseRule / not a filesystem problem
        } catch (InvalidFormatException) {
            return false; // the file (or the base path) does not exist
        }
        return $resolved !== null && is_readable($resolved);
    }
}
