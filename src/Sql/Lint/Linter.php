<?php

namespace FQL\Sql\Lint;

use FQL\Sql\Compiler;
use FQL\Sql\Lint\Rule\DuplicateAliasRule;
use FQL\Sql\Lint\Rule\FileNotFoundRule;
use FQL\Sql\Lint\Rule\MissingFromRule;
use FQL\Sql\Lint\Rule\UnknownFunctionRule;
use FQL\Sql\Parser\ParseException;

/**
 * Entry point for lint-checking FQL strings. Orchestrates the pipeline:
 *
 *  1. Tokenize + parse (via {@see Compiler}). If parsing throws
 *     {@see ParseException}, emit a single `syntax-error` issue carrying the
 *     offending token's position and return — semantic rules wouldn't have a
 *     valid AST to work with.
 *  2. Walk AST with every registered rule, collecting their findings.
 *  3. Sort issues by source position for consistent output.
 *
 * Rule set is fixed at construction time — the default set covers the issues
 * that can't be detected by simply trying to execute the query: unknown
 * function names, duplicate SELECT aliases, missing FROM, and optionally
 * filesystem-level file existence.
 */
final class Linter
{
    /** @var list<Rule> */
    private readonly array $rules;

    /**
     * @param list<Rule>|null $rules pass `null` to use the default rule set
     */
    public function __construct(?array $rules = null)
    {
        $this->rules = $rules ?? self::defaultRules();
    }

    /**
     * @return list<Rule>
     */
    public static function defaultRules(): array
    {
        return [
            new UnknownFunctionRule(),
            new DuplicateAliasRule(),
            new MissingFromRule(),
            new FileNotFoundRule(),
        ];
    }

    /**
     * Runs every registered rule against `$sql` and returns the collected
     * findings. Passing `$checkFilesystem = true` enables the opt-in
     * {@see FileNotFoundRule} — rules that don't need the flag ignore it.
     */
    public function lint(string $sql, bool $checkFilesystem = false): LintReport
    {
        $compiler = new Compiler($sql);

        try {
            $ast = $compiler->toAst();
        } catch (ParseException $e) {
            return new LintReport([
                new LintIssue(
                    Severity::ERROR,
                    'syntax-error',
                    $e->getMessage(),
                    $e->token->position
                ),
            ]);
        }

        $context = new LintContext($ast, $sql, $checkFilesystem);
        $issues = [];
        foreach ($this->rules as $rule) {
            foreach ($rule->check($context) as $issue) {
                $issues[] = $issue;
            }
        }

        // Stable ordering by source offset (null-position issues first —
        // they're structural, not tied to a specific token).
        usort($issues, static function (LintIssue $a, LintIssue $b): int {
            $ao = $a->position?->offset;
            $bo = $b->position?->offset;
            if ($ao === null && $bo === null) {
                return 0;
            }
            if ($ao === null) {
                return -1;
            }
            if ($bo === null) {
                return 1;
            }
            return $ao <=> $bo;
        });

        return new LintReport($issues);
    }
}
