<?php

namespace FQL\Sql\Formatter;

use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\Expression\FileQueryNode;
use FQL\Sql\Ast\Expression\SubQueryNode;
use FQL\Sql\Ast\ExplainMode;
use FQL\Sql\Ast\JoinType;
use FQL\Sql\Ast\Node\JoinClauseNode;
use FQL\Sql\Ast\Node\SelectFieldNode;
use FQL\Sql\Ast\Node\SelectStatementNode;
use FQL\Sql\Ast\Node\UnionClauseNode;
use FQL\Sql\Builder\ExpressionCompiler;
use FQL\Sql\Parser\Parser;
use FQL\Sql\Token\Tokenizer;
use FQL\Sql\Token\TokenStream;

/**
 * Pretty-prints an FQL AST back to a human-readable SQL source string.
 *
 * Designed primarily for debugging, code generation, and CLI / editor integrations.
 * The formatter is AST-level: comments and whitespace from the original input are not
 * preserved. For verbatim echo use {@see \FQL\Sql\Highlighter\ThemedHighlighter}.
 */
final class SqlFormatter
{
    private readonly ExpressionCompiler $compiler;

    public function __construct(private readonly FormatterOptions $options = new FormatterOptions())
    {
        $this->compiler = new ExpressionCompiler();
    }

    public function format(string $sql): string
    {
        $tokens = (new Tokenizer())->tokenize($sql);
        $ast = Parser::create()->parse(new TokenStream($tokens));
        return $this->formatStatement($ast);
    }

    public function formatStatement(SelectStatementNode $ast, int $depth = 0): string
    {
        $ind = str_repeat($this->options->indent, $depth);
        $fieldInd = $ind . $this->options->indent;

        $lines = [];

        if ($ast->explain === ExplainMode::EXPLAIN) {
            $lines[] = $ind . $this->kw('EXPLAIN');
        } elseif ($ast->explain === ExplainMode::EXPLAIN_ANALYZE) {
            $lines[] = $ind . $this->kw('EXPLAIN ANALYZE');
        }

        if ($ast->describe) {
            $from = $this->renderSource($ast->from?->source, $depth);
            $lines[] = $ind . $this->kw('DESCRIBE') . ' ' . $from;
            return implode($this->options->newline, $lines);
        }

        $lines[] = $ind . $this->renderSelectHeader($ast, $fieldInd);

        if ($ast->from !== null) {
            $source = $this->renderSource($ast->from->source, $depth);
            $aliasSuffix = $ast->from->alias !== null
                ? ' ' . $this->kw('AS') . ' ' . self::quoteAliasIfNeeded($ast->from->alias)
                : '';
            $lines[] = $ind . $this->kw('FROM') . ' ' . $source . $aliasSuffix;
        }

        foreach ($ast->joins as $join) {
            $lines[] = $ind . $this->renderJoin($join, $depth);
        }

        if ($ast->where !== null) {
            $lines[] = $ind . $this->kw('WHERE') . ' ' . $this->compiler->renderConditionGroup($ast->where->conditions);
        }

        if ($ast->groupBy !== null) {
            $parts = array_map(
                fn (ExpressionNode $n): string => $this->compiler->renderExpression($n),
                $ast->groupBy->fields
            );
            $lines[] = $ind . $this->kw('GROUP BY') . ' ' . implode(', ', $parts);
        }

        if ($ast->having !== null) {
            $lines[] = $ind . $this->kw('HAVING') . ' '
                . $this->compiler->renderConditionGroup($ast->having->conditions);
        }

        if ($ast->orderBy !== null) {
            $parts = [];
            foreach ($ast->orderBy->items as $item) {
                $dir = strtoupper($item->direction->value);
                $parts[] = $this->compiler->renderExpression($item->expression) . ' ' . $this->kw($dir);
            }
            $lines[] = $ind . $this->kw('ORDER BY') . ' ' . implode(', ', $parts);
        }

        if ($ast->limit !== null) {
            $limit = $ind . $this->kw('LIMIT') . ' ' . $ast->limit->limit;
            if ($ast->limit->offset !== null) {
                $limit .= ' ' . $this->kw('OFFSET') . ' ' . $ast->limit->offset;
            }
            $lines[] = $limit;
        }

        if ($ast->into !== null) {
            $lines[] = $ind . $this->kw('INTO') . ' ' . $ast->into->target->raw;
        }

        foreach ($ast->unions as $union) {
            $lines[] = $this->renderUnion($union, $depth);
        }

        return implode($this->options->newline, $lines);
    }

    private function renderSelectHeader(SelectStatementNode $ast, string $fieldInd): string
    {
        $header = $this->kw('SELECT');
        if ($ast->distinct) {
            $header .= ' ' . $this->kw('DISTINCT');
        }

        if ($ast->fields === []) {
            return $header . ' *';
        }

        $renderedFields = array_map(
            fn (SelectFieldNode $f): string => $this->renderSelectField($f),
            $ast->fields
        );

        if (!$this->options->fieldsPerLine || count($renderedFields) === 1) {
            return $header . ' ' . implode(', ', $renderedFields);
        }

        $sep = ',' . $this->options->newline . $fieldInd;
        return $header . $this->options->newline . $fieldInd . implode($sep, $renderedFields);
    }

    private function renderSelectField(SelectFieldNode $field): string
    {
        $prefix = $field->excluded ? $this->kw('EXCLUDE') . ' ' : '';
        $expr = $this->compiler->renderExpression($field->expression);
        if ($field->alias !== null) {
            $expr .= ' ' . $this->kw('AS') . ' ' . self::quoteAliasIfNeeded($field->alias);
        }
        return $prefix . $expr;
    }

    /**
     * Re-wraps aliases containing non-identifier-safe characters (spaces,
     * diacritics, dots, brackets, …) in backticks so the formatted SQL is
     * re-parseable. Aliases that are already plain ASCII identifiers are
     * returned verbatim.
     */
    private static function quoteAliasIfNeeded(string $alias): string
    {
        if ($alias === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias) === 1) {
            return $alias;
        }
        return '`' . $alias . '`';
    }

    private function renderSource(?ExpressionNode $source, int $depth): string
    {
        if ($source instanceof SubQueryNode) {
            $inner = $this->formatStatement($source->query, $depth + 1);
            return '(' . $this->options->newline . $inner . $this->options->newline
                . str_repeat($this->options->indent, $depth) . ')';
        }
        if ($source instanceof FileQueryNode) {
            return $source->raw;
        }
        return $source !== null ? $this->compiler->renderExpression($source) : '';
    }

    private function renderJoin(JoinClauseNode $join, int $depth): string
    {
        $type = match ($join->type) {
            JoinType::INNER => 'INNER JOIN',
            JoinType::LEFT => 'LEFT JOIN',
            JoinType::RIGHT => 'RIGHT JOIN',
            JoinType::FULL => 'FULL JOIN',
        };
        $source = $this->renderSource($join->source, $depth);
        $on = $this->compiler->renderCondition($join->on);
        return $this->kw($type) . ' ' . $source
            . ' ' . $this->kw('AS') . ' ' . self::quoteAliasIfNeeded($join->alias)
            . ' ' . $this->kw('ON') . ' ' . $on;
    }

    private function renderUnion(UnionClauseNode $union, int $depth): string
    {
        $ind = str_repeat($this->options->indent, $depth);
        $kw = $this->kw($union->all ? 'UNION ALL' : 'UNION');
        return $ind . $kw . $this->options->newline . $this->formatStatement($union->query, $depth);
    }

    private function kw(string $text): string
    {
        return $this->options->uppercaseKeywords ? strtoupper($text) : strtolower($text);
    }
}
