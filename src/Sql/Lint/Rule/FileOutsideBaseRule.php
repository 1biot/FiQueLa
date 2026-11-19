<?php

namespace FQL\Sql\Lint\Rule;

use FQL\Exception\FileQueryException;
use FQL\Exception\InvalidFormatException;
use FQL\Exception\PathOutsideBaseException;
use FQL\Sql\Lint\LintContext;
use FQL\Sql\Lint\LintIssue;
use FQL\Sql\Lint\Rule;
use FQL\Sql\Lint\Severity;
use FQL\Utils\FileQueryPathValidator;

/**
 * Opt-in rule (needs `LintContext::$checkFilesystem` and a `LintContext::$basePath`)
 * that reports files a query reads from or writes to (`INTO`) outside the base
 * path — the query builder refuses them with
 * {@see PathOutsideBaseException}. A missing file that would lie outside the
 * base is reported here, not by {@see FileNotFoundRule}.
 *
 * Span: the file query. Params: `['path' => …, 'basePath' => …]`, the path as
 * written.
 */
final class FileOutsideBaseRule implements Rule
{
    public function id(): string
    {
        return 'file-outside-base';
    }

    public function severity(): Severity
    {
        return Severity::ERROR;
    }

    public function check(LintContext $ctx): array
    {
        $basePath = $ctx->basePath;
        if (!$ctx->checkFilesystem || $basePath === null) {
            return [];
        }
        $issues = [];
        foreach ([false, true] as $into) {
            foreach (FileNotFoundRule::fileQueries($ctx, $into) as $node) {
                $path = $node->fileQuery->file;
                if ($path === null || $path === '') {
                    continue;
                }
                try {
                    FileQueryPathValidator::validate($node->fileQuery, $basePath, mustExist: !$into);
                    continue;
                } catch (PathOutsideBaseException) {
                    // reported below
                } catch (InvalidFormatException | FileQueryException) {
                    continue;
                }
                $issues[] = new LintIssue(
                    $this->severity(),
                    $this->id(),
                    sprintf('File is outside the base path: "%s".', $path),
                    $node->position,
                    $node->span,
                    ['path' => $path, 'basePath' => $basePath]
                );
            }
        }
        return $issues;
    }
}
