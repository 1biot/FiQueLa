<?php

namespace FQL\Sql\Printer;

use FQL\Sql\Ast\Node\SelectStatementNode;
use FQL\Sql\Compiler;
use FQL\Sql\Parser\ParseException;
use FQL\Sql\Printer\Internal\Renderer;
use FQL\Sql\Printer\Internal\SourceIndex;

/**
 * Prints an edited copy of a parsed query while keeping the user's original text —
 * whitespace, line breaks, comments, backticks, quotes, keyword case — everywhere
 * except the parts that changed.
 *
 * ```php
 * $printer = new SourcePrinter($sql);
 * $ast = $printer->original();
 * $edited = $ast->withOrderBy(new OrderByClauseNode([...], Position::synthetic()));
 * $newSql = $printer->print($edited);
 * ```
 *
 * AST nodes are immutable, so an edit rebuilds only the path from the root to the
 * changed node; every untouched subtree is still the same object as in
 * {@see original()}. The printer relies on that identity:
 *
 * - a node of the original AST is written as its original text (comments inside it
 *   included), wherever it appears in the edited tree;
 * - a changed node is written against the original node in the same place: its
 *   keywords, punctuation and inner gaps are copied from the original, only the
 *   changed parts are written fresh (see {@see FqlPrinter} for how);
 * - in lists (SELECT fields, conditions, arguments, ORDER BY / GROUP BY items, joins,
 *   clauses) the separators between items that were neighbours stay as written, a
 *   removed item takes its separator along (`, field`, `AND condition`, a clause with
 *   the line break before it) and an inserted item copies the layout of the nearest
 *   separator (a new clause goes on its own line when the statement is multi-line);
 * - new keywords follow the keyword case of the original query.
 *
 * Invariants: `print(original())` returns the original SQL byte for byte, and
 * `print($edited)` parses back to a tree structurally equal to `$edited`.
 */
final class SourcePrinter
{
    private readonly SelectStatementNode $original;

    private readonly SourceIndex $index;

    private readonly Renderer $renderer;

    /**
     * @param SelectStatementNode|null $original the AST parsed from `$sql`; parsed here when omitted
     * @throws ParseException
     */
    public function __construct(private readonly string $sql, ?SelectStatementNode $original = null)
    {
        $this->original = $original ?? (new Compiler($sql))->toAst();
        if ($this->original->span === null) {
            throw new \InvalidArgumentException('The original AST has no source span; parse it from the given SQL');
        }
        $this->index = new SourceIndex($sql, $this->original);
        $this->renderer = new Renderer($this->index, $this->index->keywordCase);
    }

    /**
     * The original SQL.
     */
    public function sql(): string
    {
        return $this->sql;
    }

    /**
     * The AST of the original SQL; edit copies of it and pass them to {@see print()}.
     */
    public function original(): SelectStatementNode
    {
        return $this->original;
    }

    /**
     * Keyword case of the original query (the majority of its keywords), used for new keywords.
     */
    public function keywordCase(): KeywordCase
    {
        return $this->index->keywordCase;
    }

    /**
     * Writes `$edited` as FQL, keeping the original text of every unchanged part.
     *
     * @throws UnprintableNodeException when a changed part cannot be written in FQL
     */
    public function print(SelectStatementNode $edited): string
    {
        return $this->renderer->renderRoot($edited, $this->original);
    }
}
