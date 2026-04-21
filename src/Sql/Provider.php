<?php

namespace FQL\Sql;

use FQL\Sql\Ast\Expression\ConditionGroupNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Formatter\FormatterOptions;
use FQL\Sql\Formatter\SqlFormatter;
use FQL\Sql\Highlighter\BashHighlighter;
use FQL\Sql\Highlighter\HighlighterKind;
use FQL\Sql\Highlighter\HtmlHighlighter;
use FQL\Sql\Parser\ConditionGroupParser;
use FQL\Sql\Parser\ConditionParser;
use FQL\Sql\Parser\ExpressionParser;
use FQL\Sql\Parser\ParseException;
use FQL\Sql\Token\Tokenizer;
use FQL\Sql\Token\TokenStream;

/**
 * Public entry point for the FQL Sql pipeline.
 *
 * - `compile($sql)` — full pipeline: source → tokens → AST → Query (typical consumer
 *   is `Query\Provider::fql()`).
 * - `tokenize($sql)` — token stream, useful for highlighters / editor integrations.
 * - `highlight($sql, $kind)` — ANSI (Bash) or HTML pretty-printed source.
 * - `format($sql, $options)` — AST-driven pretty-print, useful for debug output.
 * - `parseExpression($fragment)` / `parseCondition($fragment)` — fragment parsers
 *   used by the fluent Query API to turn string inputs (`sum('price + vat')`,
 *   `groupBy('year(date)')`, `where('lower(name)', Op::EQ, 'alice')`) into AST
 *   nodes the runtime evaluator can consume.
 */
final class Provider
{
    public static function compile(string $sql, ?string $basePath = null): Compiler
    {
        return new Compiler(trim($sql), $basePath);
    }

    public static function tokenize(string $sql, bool $includeTrivia = true): TokenStream
    {
        return new TokenStream((new Tokenizer())->tokenize($sql), $includeTrivia);
    }

    public static function highlight(string $sql, HighlighterKind $kind = HighlighterKind::BASH): string
    {
        return match ($kind) {
            HighlighterKind::BASH => (new BashHighlighter())->highlight($sql),
            HighlighterKind::HTML => (new HtmlHighlighter())->highlight($sql),
        };
    }

    public static function format(string $sql, ?FormatterOptions $options = null): string
    {
        return (new SqlFormatter($options ?? new FormatterOptions()))->format($sql);
    }

    /**
     * Parses a single FQL expression fragment into an AST node. Accepts anything
     * the `ExpressionParser` does: plain identifiers, dotted paths, numeric and
     * string literals, function calls, binary arithmetic, CAST, CASE, MATCH.
     *
     * The resulting node is used by the fluent Query API traits to back every
     * string input with a typed expression. Trailing input is treated as an
     * error — this is a fragment, not a full statement.
     *
     * @throws ParseException when the fragment is syntactically invalid or has
     *         extra tokens beyond the expression.
     */
    public static function parseExpression(string $fragment): ExpressionNode
    {
        $stream = new TokenStream((new Tokenizer())->tokenize(trim($fragment)));
        $expressionParser = self::freshExpressionParser();
        $node = $expressionParser->parseExpression($stream);
        if (!$stream->isAtEnd()) {
            throw ParseException::context(
                $stream->peek(),
                sprintf('trailing tokens after expression fragment "%s"', $fragment)
            );
        }
        return $node;
    }

    /**
     * Parses an FQL condition fragment (e.g. `price > 100 AND status = "active"`)
     * into a `ConditionGroupNode` consumable by the runtime evaluator.
     *
     * Used by the fluent `case()->whenCase(...)` builder and by condition-aware
     * fluent helpers.
     *
     * @throws ParseException on trailing input or malformed syntax.
     */
    public static function parseCondition(string $fragment): ConditionGroupNode
    {
        $stream = new TokenStream((new Tokenizer())->tokenize(trim($fragment)));
        $expressionParser = self::freshExpressionParser();
        $conditionParser = new ConditionParser($expressionParser);
        $groupParser = new ConditionGroupParser($conditionParser);
        $expressionParser->setConditionGroupParser($groupParser);

        $node = $groupParser->parseGroup($stream);
        if (!$stream->isAtEnd()) {
            throw ParseException::context(
                $stream->peek(),
                sprintf('trailing tokens after condition fragment "%s"', $fragment)
            );
        }
        return $node;
    }

    /**
     * Builds a minimal ExpressionParser wired to its condition sub-parser so
     * `CASE WHEN cond THEN ...` inside an expression fragment parses correctly.
     */
    private static function freshExpressionParser(): ExpressionParser
    {
        $expressionParser = new ExpressionParser();
        $conditionParser = new ConditionParser($expressionParser);
        $groupParser = new ConditionGroupParser($conditionParser);
        $expressionParser->setConditionGroupParser($groupParser);
        return $expressionParser;
    }
}
