<?php

namespace FQL\Sql\Lint\Rule;

use FQL\Sql\Ast\Node\SelectFieldNode;
use FQL\Sql\Lint\LintContext;
use FQL\Sql\Lint\LintIssue;
use FQL\Sql\Lint\Rule;
use FQL\Sql\Lint\Severity;
use FQL\Sql\Token\Span;

/**
 * Reports duplicate explicit aliases inside a single SELECT list
 * (e.g. `SELECT a AS x, b AS x`). The fluent / runtime path would silently
 * overwrite the second occurrence in the `selectedFields` map keyed by
 * alias, producing a single column in the output — nearly always a bug.
 *
 * Span: the repeated alias (position stays the start of its field). Params:
 * `['alias' => …]`.
 */
final class DuplicateAliasRule implements Rule
{
    public function id(): string
    {
        return 'duplicate-alias';
    }

    public function severity(): Severity
    {
        return Severity::ERROR;
    }

    public function check(LintContext $ctx): array
    {
        $issues = [];
        $seen = [];   // alias => SelectFieldNode
        foreach ($ctx->ast->fields as $field) {
            if (!$field instanceof SelectFieldNode) {
                continue;
            }
            if ($field->alias === null || $field->excluded) {
                continue;
            }
            $alias = $field->alias;
            if (isset($seen[$alias])) {
                $issues[] = new LintIssue(
                    $this->severity(),
                    $this->id(),
                    sprintf('Alias "%s" is defined more than once in SELECT.', $alias),
                    $field->position,
                    self::aliasSpan($field, $alias, $ctx->source),
                    ['alias' => $alias]
                );
            } else {
                $seen[$alias] = $field;
            }
        }
        return $issues;
    }

    /**
     * Span of the alias itself (`x` or `` `x` `` in `b AS x`): the field ends with it.
     * Falls back to the whole field.
     */
    private static function aliasSpan(SelectFieldNode $field, string $alias, string $sql): ?Span
    {
        $span = $field->span;
        if ($span === null) {
            return null;
        }
        $text = $span->slice($sql);
        foreach (['`' . $alias . '`', $alias] as $written) {
            if (strlen($written) < strlen($text) && str_ends_with($text, $written)) {
                return new Span($span->end - strlen($written), $span->end);
            }
        }
        return $span;
    }
}
