<?php

namespace FQL\Sql\Printer\Internal;

use FQL\Enum\Operator;
use FQL\Enum\Sort;
use FQL\Enum\Type;
use FQL\Query\FileQuery;
use FQL\Sql\Ast\AstNode;
use FQL\Sql\Ast\ExplainMode;
use FQL\Sql\Ast\Expression\BinaryOperator;
use FQL\Sql\Ast\Expression\BinaryOpNode;
use FQL\Sql\Ast\Expression\CaseExpressionNode;
use FQL\Sql\Ast\Expression\CastExpressionNode;
use FQL\Sql\Ast\Expression\CollectObjectExpressionNode;
use FQL\Sql\Ast\Expression\ColumnReferenceNode;
use FQL\Sql\Ast\Expression\ConditionExpressionNode;
use FQL\Sql\Ast\Expression\ConditionGroupNode;
use FQL\Sql\Ast\Expression\CteReferenceNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\Expression\FileQueryNode;
use FQL\Sql\Ast\Expression\FunctionCallNode;
use FQL\Sql\Ast\Expression\LiteralNode;
use FQL\Sql\Ast\Expression\MatchAgainstNode;
use FQL\Sql\Ast\Expression\StarNode;
use FQL\Sql\Ast\Expression\SubQueryNode;
use FQL\Sql\Ast\Expression\WhenBranchNode;
use FQL\Sql\Ast\HasSpan;
use FQL\Sql\Ast\JoinType;
use FQL\Sql\Ast\Node\CommonTableExpressionNode;
use FQL\Sql\Ast\Node\FromClauseNode;
use FQL\Sql\Ast\Node\GroupByClauseNode;
use FQL\Sql\Ast\Node\HavingClauseNode;
use FQL\Sql\Ast\Node\IntoClauseNode;
use FQL\Sql\Ast\Node\JoinClauseNode;
use FQL\Sql\Ast\Node\LimitClauseNode;
use FQL\Sql\Ast\Node\OrderByClauseNode;
use FQL\Sql\Ast\Node\OrderByItemNode;
use FQL\Sql\Ast\Node\SelectFieldNode;
use FQL\Sql\Ast\Node\SelectStatementNode;
use FQL\Sql\Ast\Node\UnionClauseNode;
use FQL\Sql\Ast\Node\WhereClauseNode;
use FQL\Sql\Parser\ParseException;
use FQL\Sql\Printer\KeywordCase;
use FQL\Sql\Printer\UnprintableNodeException;
use FQL\Sql\Token\Span;
use FQL\Sql\Token\Token;
use FQL\Sql\Token\Tokenizer;
use FQL\Sql\Token\TokenType;

/**
 * Writes AST nodes as FQL text. Shared by {@see \FQL\Sql\Printer\FqlPrinter} (no source:
 * every node is written fresh) and {@see \FQL\Sql\Printer\SourcePrinter} (with source):
 *
 * - a node of the original AST (same object) is written as its original text slice;
 * - a changed node with an original counterpart in the same place is written by
 *   splicing: keywords, punctuation and the gaps between its parts come from the
 *   counterpart's text, only the changed parts are written fresh;
 * - lists (fields, conditions, arguments, clauses) reuse the original separators
 *   between items that were neighbours, a removed item takes its separator along and
 *   an inserted item copies the layout of an existing separator;
 * - anything else is written fresh in canonical single-line form.
 *
 * @internal
 */
final class Renderer
{
    private const CTX_DEFAULT = 0;
    /** A condition group used as an entry of another group: written in parentheses. */
    private const CTX_NESTED = 1;

    private const RANK = [
        'explain' => 0,
        'with' => 1,
        'select' => 2,
        'from' => 3,
        'join' => 4,
        'where' => 5,
        'groupBy' => 6,
        'having' => 7,
        'orderBy' => 8,
        'limit' => 9,
        'into' => 10,
        'union' => 11,
    ];

    /**
     * Original text ranges an edit moved or dropped (a trailing comment that follows its
     * item to a new line, or leaves with a removed item); gaps skip them.
     *
     * @var list<array{0: int, 1: int}>
     */
    private array $consumed = [];

    public function __construct(
        private readonly ?SourceIndex $source,
        private readonly KeywordCase $case
    ) {
    }

    /**
     * Writes `$node`; `$counterpart` is the original node it replaces (if any).
     */
    public function render(AstNode $node, ?AstNode $counterpart = null): string
    {
        $this->consumed = [];
        return $this->node($node, $counterpart);
    }

    /**
     * Writes the edited root statement together with the original text around the
     * original root (leading and trailing comments and whitespace).
     */
    public function renderRoot(SelectStatementNode $edited, SelectStatementNode $original): string
    {
        $this->consumed = [];
        $span = $original->span;
        if ($this->source === null || $span === null) {
            return $this->node($edited, null);
        }
        $body = $this->node($edited, $original);
        $prefix = $this->gapText(0, $span->start);
        $suffix = $this->gapText($span->end, strlen($this->source->sql));
        return $this->concat($this->concat($prefix, $body), $suffix);
    }

    // ------------------------------------------------------------------ dispatch

    private function node(AstNode $e, ?AstNode $c, int $ctx = self::CTX_DEFAULT): string
    {
        if ($this->isOriginal($e)) {
            $span = self::spanOf($e);
            $sameForm = !$e instanceof ConditionGroupNode
                || $this->isParenthesized($e) === ($ctx === self::CTX_NESTED);
            if ($span !== null && $sameForm) {
                return $this->slice($span);
            }
            // An original group moved between a parenthesised and a bare position:
            // splice it against itself to add or drop the parentheses.
            $c = $e;
        } elseif ($c !== null && ($c::class !== $e::class || !$this->isOriginal($c))) {
            $c = null;
        }

        return match (true) {
            $e instanceof SelectStatementNode => $this->statement($e, $c instanceof SelectStatementNode ? $c : null),
            $e instanceof SelectFieldNode => $this->selectField($e, $c instanceof SelectFieldNode ? $c : null),
            $e instanceof FromClauseNode => $this->fromClause($e, $c instanceof FromClauseNode ? $c : null, false),
            $e instanceof JoinClauseNode => $this->joinClause($e, $c instanceof JoinClauseNode ? $c : null),
            $e instanceof WhereClauseNode
                => $this->conditionClause('WHERE', $e, $c instanceof WhereClauseNode ? $c : null),
            $e instanceof HavingClauseNode
                => $this->conditionClause('HAVING', $e, $c instanceof HavingClauseNode ? $c : null),
            $e instanceof GroupByClauseNode => $this->groupBy($e, $c instanceof GroupByClauseNode ? $c : null),
            $e instanceof OrderByClauseNode => $this->orderBy($e, $c instanceof OrderByClauseNode ? $c : null),
            $e instanceof OrderByItemNode => $this->orderByItem($e, $c instanceof OrderByItemNode ? $c : null),
            $e instanceof LimitClauseNode => $this->limit($e),
            $e instanceof IntoClauseNode => $this->into($e, $c instanceof IntoClauseNode ? $c : null),
            $e instanceof UnionClauseNode => $this->union($e, $c instanceof UnionClauseNode ? $c : null),
            $e instanceof CommonTableExpressionNode
                => $this->cte($e, $c instanceof CommonTableExpressionNode ? $c : null),
            $e instanceof SubQueryNode => $this->subQuery($e, $c instanceof SubQueryNode ? $c : null),
            $e instanceof ConditionGroupNode
                => $this->conditionGroup($e, $c instanceof ConditionGroupNode ? $c : null, $ctx),
            $e instanceof ConditionExpressionNode
                => $this->condition($e, $c instanceof ConditionExpressionNode ? $c : null),
            $e instanceof BinaryOpNode => $this->binary($e, $c instanceof BinaryOpNode ? $c : null),
            $e instanceof FunctionCallNode => $this->functionCall($e, $c instanceof FunctionCallNode ? $c : null),
            $e instanceof CastExpressionNode => $this->cast($e, $c instanceof CastExpressionNode ? $c : null),
            $e instanceof MatchAgainstNode => $this->matchAgainst($e, $c instanceof MatchAgainstNode ? $c : null),
            $e instanceof CaseExpressionNode => $this->caseExpression($e, $c instanceof CaseExpressionNode ? $c : null),
            $e instanceof WhenBranchNode => $this->whenBranch($e, $c instanceof WhenBranchNode ? $c : null),
            $e instanceof CollectObjectExpressionNode => $this->collectObject($e),
            $e instanceof LiteralNode => Lexical::literal($e, $this->case),
            $e instanceof ColumnReferenceNode => Lexical::columnReference($e->name, $e->quoted, $e),
            $e instanceof StarNode => '*',
            $e instanceof CteReferenceNode => $this->cteReference($e),
            $e instanceof FileQueryNode => $this->fileQuery($e, $c instanceof FileQueryNode ? $c : null),
            default => throw new UnprintableNodeException(
                sprintf('%s cannot be written as FQL', $e::class),
                $e
            ),
        };
    }

    // ------------------------------------------------------------------ statement

    private function statement(SelectStatementNode $e, ?SelectStatementNode $c): string
    {
        if ($c === null || $c->span === null || $this->source === null || $c->describe !== $e->describe) {
            return $this->freshStatement($e);
        }

        $segments = $this->segments($c);
        $joinMap = $this->mapSequence($e->joins, $c->joins);
        $unionMap = $this->mapSequence($e->unions, $c->unions);

        /** @var list<array{text: string, seg: ?int, rank: int, join: ?int, union: ?int}> $items */
        $items = [];
        $seen = [];
        foreach ($segments as $index => $segment) {
            $slot = $segment['slot'];
            $joinIndex = null;
            $unionIndex = null;
            $text = null;
            switch ($slot) {
                case 'explain':
                    $text = $e->explain === $c->explain
                        ? $this->slice($segment['span'])
                        : ($e->explain === ExplainMode::NONE ? null : $this->explainText($e->explain));
                    break;
                case 'with':
                    $text = self::sameList($e->commonTables, $c->commonTables)
                        ? $this->slice($segment['span'])
                        : ($e->commonTables === [] ? null : $this->withClause($e, $c, $segment['span']));
                    break;
                case 'select':
                    $text = self::sameList($e->fields, $c->fields) && $e->distinct === $c->distinct
                        ? $this->slice($segment['span'])
                        : $this->selectClause($e, $c, $segment['span']);
                    break;
                case 'join':
                    $cIndex = array_search($segment['node'], $c->joins, true);
                    $eIndex = $cIndex === false ? false : array_search($cIndex, $joinMap, true);
                    if ($eIndex !== false) {
                        $joinIndex = (int) $eIndex;
                        $text = $this->node($e->joins[$joinIndex], $segment['node']);
                    } elseif ($cIndex === false) {
                        $text = $this->slice($segment['span']);
                    }
                    break;
                case 'union':
                    $cIndex = array_search($segment['node'], $c->unions, true);
                    $eIndex = $cIndex === false ? false : array_search($cIndex, $unionMap, true);
                    if ($eIndex !== false) {
                        $unionIndex = (int) $eIndex;
                        $text = $this->node($e->unions[$unionIndex], $segment['node']);
                    } elseif ($cIndex === false) {
                        $text = $this->slice($segment['span']);
                    }
                    break;
                case 'limit':
                    if ($e->limit === $c->limit) {
                        $text = $this->slice($segment['span']);
                    } elseif ($e->limit !== null && $segment['effective']) {
                        $text = $this->limit($e->limit);
                    }
                    break;
                default:
                    $eNode = self::clauseOf($e, $slot);
                    $cNode = self::clauseOf($c, $slot);
                    if ($eNode === $cNode) {
                        $text = $this->slice($segment['span']);
                    } elseif ($eNode !== null) {
                        // Earlier (dead) duplicates of a clause stay as written; only the
                        // effective one is replaced. A removed clause takes them along.
                        $text = $segment['effective']
                            ? $this->clause($e, $slot, $eNode, $cNode)
                            : $this->slice($segment['span']);
                    }
            }
            if ($segment['effective']) {
                $seen[$slot] = true;
            }
            if ($text !== null) {
                $items[] = [
                    'text' => $text,
                    'seg' => $index,
                    'rank' => self::RANK[$slot],
                    'join' => $joinIndex,
                    'union' => $unionIndex,
                ];
            }
        }

        // Clauses the original did not have.
        $new = [];
        if ($e->explain !== ExplainMode::NONE && !isset($seen['explain'])) {
            $new[] = ['explain', $this->explainText($e->explain)];
        }
        if ($e->commonTables !== [] && !isset($seen['with'])) {
            $new[] = ['with', $this->withClause($e, null, null)];
        }
        foreach (['from', 'where', 'groupBy', 'having', 'orderBy', 'limit', 'into'] as $slot) {
            $node = self::clauseOf($e, $slot);
            if ($node !== null && !isset($seen[$slot])) {
                $new[] = [$slot, $this->clause($e, $slot, $node, null)];
            }
        }
        foreach ($new as [$slot, $text]) {
            $items = self::insertByRank($items, $slot, $text);
        }
        foreach ($joinMap as $eIndex => $cIndex) {
            if ($cIndex === null) {
                $items = self::insertInSequence($items, 'join', $eIndex, $this->node($e->joins[$eIndex], null));
            }
        }
        foreach ($unionMap as $eIndex => $cIndex) {
            if ($cIndex === null) {
                $items = self::insertInSequence($items, 'union', $eIndex, $this->node($e->unions[$eIndex], null));
            }
        }

        $gaps = [];
        for ($k = 0, $n = count($segments) - 1; $k < $n; $k++) {
            $gaps[$k] = $this->gapText($segments[$k]['span']->end, $segments[$k + 1]['span']->start);
        }
        $lastSegment = $segments !== [] ? $segments[count($segments) - 1] : null;
        $trail = $lastSegment !== null ? $this->trailingComment($lastSegment['span']->end) : null;
        $texts = array_map(static fn (array $item): string => $item['text'], $items);
        $slots = array_map(static fn (array $item): ?int => $item['seg'], $items);
        $expected = array_fill(0, count($items), [[]]);
        return $this->joinItems($texts, $slots, $gaps, count($segments), $expected, $trail, true);
    }

    /**
     * A clause of `$statement`; FROM knows whether it belongs to a DESCRIBE.
     */
    private function clause(SelectStatementNode $statement, string $slot, AstNode $e, ?AstNode $c): string
    {
        if ($slot === 'from' && $e instanceof FromClauseNode) {
            return $this->fromClause($e, $c instanceof FromClauseNode ? $c : null, $statement->describe);
        }
        return $this->node($e, $c);
    }

    private function freshStatement(SelectStatementNode $e): string
    {
        $out = '';
        if ($e->explain !== ExplainMode::NONE) {
            $out = $this->explainText($e->explain);
        }
        if ($e->describe) {
            if ($e->from === null) {
                throw new UnprintableNodeException('DESCRIBE needs a source', $e);
            }
            return $this->concat($out, $this->fromClause($e->from, null, true), ' ');
        }
        $parts = [];
        if ($e->commonTables !== []) {
            $parts[] = $this->withClause($e, null, null);
        }
        $parts[] = $this->selectClause($e, null, null);
        if ($e->from !== null) {
            $parts[] = $this->fromClause($e->from, null, false);
        }
        foreach ($e->joins as $join) {
            $parts[] = $this->node($join, null);
        }
        foreach ([$e->where, $e->groupBy, $e->having, $e->orderBy, $e->limit, $e->into] as $clause) {
            if ($clause !== null) {
                $parts[] = $this->node($clause, null);
            }
        }
        foreach ($e->unions as $union) {
            $parts[] = $this->node($union, null);
        }
        foreach ($parts as $part) {
            $out = $this->concat($out, $part, ' ');
        }
        return $out;
    }

    private function explainText(ExplainMode $mode): string
    {
        return $mode === ExplainMode::EXPLAIN_ANALYZE ? $this->kw('EXPLAIN ANALYZE') : $this->kw('EXPLAIN');
    }

    /**
     * Top-level parts of an original statement in source order: the EXPLAIN / WITH /
     * SELECT heads, one segment per clause node, and clause keywords the AST does not
     * keep (an earlier WHERE overridden by a later one, the far part of a LIMIT … OFFSET
     * split by another clause) as "dead" segments of their clause slot.
     *
     * @return list<array{slot: string, span: Span, node: ?AstNode, effective: bool}>
     */
    private function segments(SelectStatementNode $c): array
    {
        $source = $this->source;
        $span = $c->span;
        if ($source === null || $span === null) {
            return [];
        }

        /** @var list<array{slot: string, span: Span, node: ?AstNode, effective: bool}> $known */
        $known = [];
        $cursor = $span->start;
        if ($c->explain !== ExplainMode::NONE) {
            $explain = $source->tokenAtOrAfter($cursor);
            if ($explain !== null) {
                $last = $explain;
                $next = $source->tokenAtOrAfter(self::tokenEnd($explain));
                if ($c->explain === ExplainMode::EXPLAIN_ANALYZE && $next?->type === TokenType::KEYWORD_ANALYZE) {
                    $last = $next;
                }
                $known[] = self::segment('explain', Span::fromTokens($explain, $last));
                $cursor = self::tokenEnd($last);
            }
        }
        if ($c->commonTables !== []) {
            $with = $source->tokenAtOrAfter($cursor);
            $lastCte = self::spanOf($c->commonTables[count($c->commonTables) - 1]);
            if ($with !== null && $lastCte !== null) {
                $known[] = self::segment('with', new Span($with->position->offset, $lastCte->end));
                $cursor = $lastCte->end;
            }
        }
        if (!$c->describe) {
            $select = $source->tokenAtOrAfter($cursor);
            if ($select !== null && $select->type === TokenType::KEYWORD_SELECT) {
                $end = self::tokenEnd($select);
                $distinct = $source->tokenAtOrAfter($end);
                if ($c->distinct && $distinct !== null && $distinct->type === TokenType::KEYWORD_DISTINCT) {
                    $end = self::tokenEnd($distinct);
                }
                if ($c->fields !== []) {
                    $end = max($end, self::spanOf($c->fields[count($c->fields) - 1])?->end ?? $end);
                }
                $known[] = self::segment('select', new Span($select->position->offset, $end));
            }
        }
        $clauses = [['from', $c->from]];
        foreach ($c->joins as $join) {
            $clauses[] = ['join', $join];
        }
        foreach (['where', 'groupBy', 'having', 'orderBy', 'limit', 'into'] as $slot) {
            $clauses[] = [$slot, self::clauseOf($c, $slot)];
        }
        foreach ($c->unions as $union) {
            $clauses[] = ['union', $union];
        }
        foreach ($clauses as [$slot, $node]) {
            $nodeSpan = $node !== null ? self::spanOf($node) : null;
            if ($node !== null && $nodeSpan !== null) {
                $known[] = self::segment($slot, $nodeSpan, $node);
            }
        }
        usort($known, static fn (array $a, array $b): int => $a['span']->start <=> $b['span']->start);

        // Tokens outside the known segments: dead clauses, or stray tokens that belong to
        // the segment before them.
        $all = $known;
        $open = null;
        $depth = 0;
        foreach ($source->tokensIn($span->start, $span->end) as $token) {
            $offset = $token->position->offset;
            $end = self::tokenEnd($token);
            $covered = false;
            foreach ($known as $segment) {
                if ($offset >= $segment['span']->start && $end <= $segment['span']->end) {
                    $covered = true;
                    break;
                }
            }
            if ($covered) {
                $open = null;
                $depth = 0;
                continue;
            }
            $slot = $depth === 0 ? self::deadSlot($token->type) : null;
            if ($slot !== null) {
                $all[] = self::segment($slot, Span::fromTokens($token, $token), null, false);
                $open = count($all) - 1;
            } elseif ($open !== null) {
                $all[$open]['span'] = new Span($all[$open]['span']->start, $end);
            } else {
                $previous = null;
                foreach ($all as $i => $segment) {
                    $start = $segment['span']->start;
                    if ($start < $offset && ($previous === null || $start > $all[$previous]['span']->start)) {
                        $previous = $i;
                    }
                }
                if ($previous !== null) {
                    $extended = $all[$previous]['span'];
                    $all[$previous]['span'] = new Span($extended->start, max($extended->end, $end));
                    $open = $previous;
                }
            }
            if ($token->type === TokenType::PAREN_OPEN) {
                $depth++;
            } elseif ($token->type === TokenType::PAREN_CLOSE) {
                $depth = max(0, $depth - 1);
            }
        }
        usort($all, static fn (array $a, array $b): int => $a['span']->start <=> $b['span']->start);
        return $all;
    }

    /**
     * @return array{slot: string, span: Span, node: ?AstNode, effective: bool}
     */
    private static function segment(string $slot, Span $span, ?AstNode $node = null, bool $effective = true): array
    {
        return ['slot' => $slot, 'span' => $span, 'node' => $node, 'effective' => $effective];
    }

    private static function deadSlot(TokenType $type): ?string
    {
        return match ($type) {
            TokenType::KEYWORD_FROM => 'from',
            TokenType::KEYWORD_WHERE => 'where',
            TokenType::KEYWORD_GROUP => 'groupBy',
            TokenType::KEYWORD_HAVING => 'having',
            TokenType::KEYWORD_ORDER => 'orderBy',
            TokenType::KEYWORD_LIMIT, TokenType::KEYWORD_OFFSET => 'limit',
            TokenType::KEYWORD_INTO => 'into',
            default => null,
        };
    }

    private static function clauseOf(SelectStatementNode $statement, string $slot): ?AstNode
    {
        return match ($slot) {
            'from' => $statement->from,
            'where' => $statement->where,
            'groupBy' => $statement->groupBy,
            'having' => $statement->having,
            'orderBy' => $statement->orderBy,
            'limit' => $statement->limit,
            'into' => $statement->into,
            default => null,
        };
    }

    /**
     * Maps edited list items to original ones: identical objects first, then a changed
     * item to the original at the same index. Returns null for every item when the
     * matched originals would appear out of their original order.
     *
     * @param list<AstNode> $edited
     * @param list<AstNode> $original
     * @return array<int, ?int> edited index => original index
     */
    private function mapSequence(array $edited, array $original): array
    {
        $map = self::matchItems($edited, $original);
        $last = -1;
        foreach ($map as $k) {
            if ($k === null) {
                continue;
            }
            if ($k <= $last) {
                return array_fill_keys(array_keys($edited), null);
            }
            $last = $k;
        }
        return $map;
    }

    /**
     * Original index each edited item stands for: the same object, otherwise (for a
     * changed item) the original at the same index when nothing else claimed it.
     *
     * @param list<AstNode> $edited
     * @param list<AstNode> $original
     * @return array<int, ?int>
     */
    private static function matchItems(array $edited, array $original): array
    {
        $slots = [];
        $taken = [];
        foreach ($edited as $j => $item) {
            $k = array_search($item, $original, true);
            if ($k !== false && !isset($taken[$k])) {
                $slots[$j] = (int) $k;
                $taken[$k] = true;
            } else {
                $slots[$j] = null;
            }
        }
        foreach ($edited as $j => $item) {
            $candidate = $original[$j] ?? null;
            $sameKind = $candidate !== null && $candidate::class === $item::class;
            if ($slots[$j] === null && $sameKind && !isset($taken[$j])) {
                $slots[$j] = $j;
                $taken[$j] = true;
            }
        }
        return $slots;
    }

    /**
     * @param list<array{text: string, seg: ?int, rank: int, join: ?int, union: ?int}> $items
     * @return list<array{text: string, seg: ?int, rank: int, join: ?int, union: ?int}>
     */
    private static function insertByRank(array $items, string $slot, string $text): array
    {
        $rank = self::RANK[$slot];
        $at = 0;
        foreach ($items as $i => $item) {
            if ($item['rank'] <= $rank) {
                $at = $i + 1;
            }
        }
        $new = ['text' => $text, 'seg' => null, 'rank' => $rank, 'join' => null, 'union' => null];
        array_splice($items, $at, 0, [$new]);
        return $items;
    }

    /**
     * Inserts the `$index`-th join / union of the edited statement right after the
     * previous one, or before the first of its kind.
     *
     * @param list<array{text: string, seg: ?int, rank: int, join: ?int, union: ?int}> $items
     * @return list<array{text: string, seg: ?int, rank: int, join: ?int, union: ?int}>
     */
    private static function insertInSequence(array $items, string $slot, int $index, string $text): array
    {
        $rank = self::RANK[$slot];
        $at = null;
        if ($index > 0) {
            foreach ($items as $i => $item) {
                if ($item[$slot] === $index - 1) {
                    $at = $i + 1;
                }
            }
        }
        if ($at === null) {
            $at = 0;
            foreach ($items as $i => $item) {
                if ($item['rank'] < $rank) {
                    $at = $i + 1;
                }
            }
        }
        $new = [
            'text' => $text,
            'seg' => null,
            'rank' => $rank,
            'join' => $slot === 'join' ? $index : null,
            'union' => $slot === 'union' ? $index : null,
        ];
        array_splice($items, $at, 0, [$new]);
        return $items;
    }

    /**
     * `SELECT [DISTINCT] fields [EXCLUDE fields]`.
     */
    private function selectClause(SelectStatementNode $e, ?SelectStatementNode $c, ?Span $segment): string
    {
        $cParts = null;
        $cLeadStart = null;
        if ($c !== null && $segment !== null && $this->source !== null) {
            $select = $this->source->tokenAtOrAfter($segment->start);
            if ($select !== null) {
                $cParts = ['SELECT' => self::tokenSpan($select)];
                $headerEnd = self::tokenEnd($select);
                $distinct = $this->source->tokenAtOrAfter($headerEnd);
                if ($c->distinct && $distinct !== null && $distinct->type === TokenType::KEYWORD_DISTINCT) {
                    $cParts['DISTINCT'] = self::tokenSpan($distinct);
                    $headerEnd = self::tokenEnd($distinct);
                }
                $lastField = $c->fields !== [] ? self::spanOf($c->fields[count($c->fields) - 1]) : null;
                $regionStart = $this->source->tokenAtOrAfter($headerEnd);
                if ($lastField !== null && $regionStart !== null) {
                    $cLeadStart = $regionStart->position->offset;
                    $cParts['fields'] = new Span($cLeadStart, $lastField->end);
                }
            }
        }

        $expected = [];
        $previousExcluded = null;
        foreach ($e->fields as $j => $field) {
            if ($previousExcluded === null) {
                $expected[$j] = $field->excluded ? [['EXCLUDE']] : [[]];
            } elseif ($previousExcluded && !$field->excluded) {
                throw new UnprintableNodeException(
                    'A selected field cannot follow an excluded one: EXCLUDE applies to every field after it',
                    $field
                );
            } elseif (!$previousExcluded && $field->excluded) {
                $expected[$j] = [['EXCLUDE'], [',', 'EXCLUDE']];
            } else {
                $expected[$j] = [[',']];
            }
            $previousExcluded = $field->excluded;
        }

        $parts = [self::part('SELECT', $this->keep($cParts, 'SELECT', $this->kw('SELECT')))];
        if ($e->distinct) {
            $parts[] = self::part('DISTINCT', $this->keep($cParts, 'DISTINCT', $this->kw('DISTINCT')));
        }
        $parts[] = self::part('fields', $this->renderList(
            $e->fields,
            $c?->fields,
            $expected,
            fn (AstNode $item, ?AstNode $counterpart): string => $this->node($item, $counterpart),
            $cLeadStart
        ));
        return $this->join($parts, $cParts);
    }

    private function withClause(SelectStatementNode $e, ?SelectStatementNode $c, ?Span $segment): string
    {
        $cParts = null;
        if ($c !== null && $segment !== null && $this->source !== null && $c->commonTables !== []) {
            $with = $this->source->tokenAtOrAfter($segment->start);
            $first = self::spanOf($c->commonTables[0]);
            $last = self::spanOf($c->commonTables[count($c->commonTables) - 1]);
            if ($with !== null && $first !== null && $last !== null) {
                $cParts = ['WITH' => self::tokenSpan($with), 'ctes' => new Span($first->start, $last->end)];
            }
        }
        return $this->join([
            self::part('WITH', $this->keep($cParts, 'WITH', $this->kw('WITH'))),
            self::part('ctes', $this->renderList(
                $e->commonTables,
                $c?->commonTables,
                self::uniformSeparators(count($e->commonTables), [',']),
                fn (AstNode $item, ?AstNode $counterpart): string => $this->node($item, $counterpart)
            )),
        ], $cParts);
    }

    // ------------------------------------------------------------------ clauses

    private function selectField(SelectFieldNode $e, ?SelectFieldNode $c): string
    {
        $this->assertOperand($e->expression, $e);
        $cParts = null;
        if ($c !== null && $c->span !== null && ($expr = self::spanOf($c->expression)) !== null) {
            $cParts = ['expr' => $expr];
            foreach ($this->ownTokens($c->span, [$expr]) as $token) {
                if ($token->type === TokenType::KEYWORD_AS) {
                    $cParts['AS'] = self::tokenSpan($token);
                } elseif ($token->isAnyOf(TokenType::IDENTIFIER, TokenType::IDENTIFIER_QUOTED)) {
                    $cParts['alias:' . $c->alias] = self::tokenSpan($token);
                }
            }
        }
        $parts = [self::part('expr', $this->node($e->expression, $c?->expression))];
        if ($e->alias !== null) {
            $parts[] = self::part('AS', $this->keep($cParts, 'AS', $this->kw('AS')));
            $parts[] = $this->aliasPart($cParts, $e->alias, $e);
        }
        return $this->join($parts, $cParts);
    }

    private function fromClause(FromClauseNode $e, ?FromClauseNode $c, bool $describe): string
    {
        if ($this->isOriginal($e) && $e->span !== null) {
            return $this->slice($e->span);
        }
        if ($c !== null && !$this->isOriginal($c)) {
            $c = null;
        }
        $lead = $describe ? 'DESCRIBE' : 'FROM';
        $cParts = null;
        if ($c !== null && $c->span !== null && ($source = self::spanOf($c->source)) !== null) {
            $cParts = ['source' => $source];
            foreach ($this->ownTokens($c->span, [$source]) as $i => $token) {
                if ($i === 0) {
                    $cParts[strtoupper($token->raw)] = self::tokenSpan($token);
                } elseif ($token->type === TokenType::KEYWORD_AS) {
                    $cParts['AS'] = self::tokenSpan($token);
                } elseif ($token->isAnyOf(TokenType::IDENTIFIER, TokenType::IDENTIFIER_QUOTED)) {
                    $cParts['alias:' . $c->alias] = self::tokenSpan($token);
                }
            }
        }
        $parts = [
            self::part($lead, $this->keep($cParts, $lead, $this->kw($lead))),
            self::part('source', $this->source($e->source, $c?->source)),
        ];
        if ($e->alias !== null) {
            $parts[] = self::part('AS', $this->keep($cParts, 'AS', $this->kw('AS')));
            $parts[] = $this->aliasPart($cParts, $e->alias, $e);
        }
        return $this->join($parts, $cParts);
    }

    /**
     * @param array<string, Span>|null $cParts
     * @return array{k: string, t: string, gl: bool, gr: bool}
     */
    private function aliasPart(?array $cParts, string $alias, AstNode $node): array
    {
        $key = 'alias:' . $alias;
        return self::part($key, $this->keep($cParts, $key, Lexical::identifier($alias, $node)));
    }

    private function source(ExpressionNode $e, ?ExpressionNode $c): string
    {
        if (!$e instanceof FileQueryNode && !$e instanceof SubQueryNode && !$e instanceof CteReferenceNode) {
            throw new UnprintableNodeException(
                sprintf('A FROM / JOIN source must be a file query, a subquery or a CTE name, got %s', $e::class),
                $e
            );
        }
        return $this->node($e, $c);
    }

    private function joinClause(JoinClauseNode $e, ?JoinClauseNode $c): string
    {
        $typeKey = 'join:' . $e->type->value;
        $cParts = null;
        $source = $c !== null ? self::spanOf($c->source) : null;
        $on = $c !== null ? self::spanOf($c->on) : null;
        if ($c !== null && $c->span !== null && $source !== null && $on !== null) {
            $cParts = ['source' => $source, 'on' => $on];
            $lead = [];
            foreach ($this->ownTokens($c->span, [$source, $on]) as $token) {
                $offset = $token->position->offset;
                if ($offset < $source->start) {
                    $lead[] = $token;
                } elseif ($token->type === TokenType::KEYWORD_AS) {
                    $cParts['AS'] = self::tokenSpan($token);
                } elseif ($token->type === TokenType::KEYWORD_ON) {
                    $cParts['ON'] = self::tokenSpan($token);
                } elseif ($token->isAnyOf(TokenType::IDENTIFIER, TokenType::IDENTIFIER_QUOTED)) {
                    $cParts['alias:' . $c->alias] = self::tokenSpan($token);
                }
            }
            if ($lead !== []) {
                $cParts['join:' . $c->type->value] = Span::fromTokens($lead[0], $lead[count($lead) - 1]);
            }
        }
        $fresh = match ($e->type) {
            JoinType::INNER => 'INNER JOIN',
            JoinType::LEFT => 'LEFT JOIN',
            JoinType::RIGHT => 'RIGHT JOIN',
            JoinType::FULL => 'FULL JOIN',
        };
        return $this->join([
            self::part($typeKey, $this->keep($cParts, $typeKey, $this->kw($fresh))),
            self::part('source', $this->source($e->source, $c?->source)),
            self::part('AS', $this->keep($cParts, 'AS', $this->kw('AS'))),
            $this->aliasPart($cParts, $e->alias, $e),
            self::part('ON', $this->keep($cParts, 'ON', $this->kw('ON'))),
            self::part('on', $this->node($e->on, $c?->on)),
        ], $cParts);
    }

    private function conditionClause(
        string $keyword,
        WhereClauseNode|HavingClauseNode $e,
        WhereClauseNode|HavingClauseNode|null $c
    ): string {
        $cParts = null;
        if ($c !== null && $c->span !== null && ($group = $c->conditions->span) !== null) {
            $own = $this->ownTokens($c->span, [$group]);
            if ($own !== []) {
                $cParts = [$keyword => self::tokenSpan($own[0]), 'conditions' => $group];
            }
        }
        return $this->join([
            self::part($keyword, $this->keep($cParts, $keyword, $this->kw($keyword))),
            self::part('conditions', $this->node($e->conditions, $c?->conditions)),
        ], $cParts);
    }

    private function groupBy(GroupByClauseNode $e, ?GroupByClauseNode $c): string
    {
        if ($e->fields === []) {
            throw new UnprintableNodeException('GROUP BY needs at least one field', $e);
        }
        foreach ($e->fields as $field) {
            $this->assertOperand($field, $e);
        }
        $cParts = $c !== null ? $this->keywordAndList($c, 'GROUP BY', 'fields', $c->fields) : null;
        return $this->join([
            self::part('GROUP BY', $this->keep($cParts, 'GROUP BY', $this->kw('GROUP BY'))),
            self::part('fields', $this->renderList(
                $e->fields,
                $c?->fields,
                self::uniformSeparators(count($e->fields), [',']),
                fn (AstNode $item, ?AstNode $counterpart): string => $this->node($item, $counterpart)
            )),
        ], $cParts);
    }

    private function orderBy(OrderByClauseNode $e, ?OrderByClauseNode $c): string
    {
        if ($e->items === []) {
            throw new UnprintableNodeException('ORDER BY needs at least one item', $e);
        }
        $cParts = $c !== null ? $this->keywordAndList($c, 'ORDER BY', 'items', $c->items) : null;
        return $this->join([
            self::part('ORDER BY', $this->keep($cParts, 'ORDER BY', $this->kw('ORDER BY'))),
            self::part('items', $this->renderList(
                $e->items,
                $c?->items,
                self::uniformSeparators(count($e->items), [',']),
                fn (AstNode $item, ?AstNode $counterpart): string => $this->node($item, $counterpart)
            )),
        ], $cParts);
    }

    /**
     * Parts of `GROUP BY a, b` / `ORDER BY a, b`: the two keywords and the item region.
     *
     * @param list<AstNode> $items
     * @return array<string, Span>|null
     */
    private function keywordAndList(
        GroupByClauseNode|OrderByClauseNode $c,
        string $keyword,
        string $key,
        array $items
    ): ?array {
        $first = $items !== [] ? self::spanOf($items[0]) : null;
        $last = $items !== [] ? self::spanOf($items[count($items) - 1]) : null;
        if ($c->span === null || $first === null || $last === null) {
            return null;
        }
        $lead = $this->source?->tokensIn($c->span->start, $first->start) ?? [];
        if ($lead === []) {
            return null;
        }
        return [
            $keyword => Span::fromTokens($lead[0], $lead[count($lead) - 1]),
            $key => new Span($first->start, $last->end),
        ];
    }

    private function orderByItem(OrderByItemNode $e, ?OrderByItemNode $c): string
    {
        $this->assertOperand($e->expression, $e);
        $cParts = null;
        if ($c !== null && $c->span !== null && ($expr = self::spanOf($c->expression)) !== null) {
            $cParts = ['expr' => $expr];
            foreach ($this->ownTokens($c->span, [$expr]) as $token) {
                if ($token->isAnyOf(TokenType::KEYWORD_ASC, TokenType::KEYWORD_DESC)) {
                    $cParts['dir:' . strtoupper($token->raw)] = self::tokenSpan($token);
                }
            }
        }
        $parts = [self::part('expr', $this->node($e->expression, $c?->expression))];
        // ASC is the default: written only when the original item spelled out a direction.
        if ($e->direction === Sort::DESC) {
            $parts[] = self::part('dir:DESC', $this->keep($cParts, 'dir:DESC', $this->kw('DESC')));
        } elseif (isset($cParts['dir:ASC']) || isset($cParts['dir:DESC'])) {
            $parts[] = self::part('dir:ASC', $this->keep($cParts, 'dir:ASC', $this->kw('ASC')));
        }
        return $this->join($parts, $cParts);
    }

    private function limit(LimitClauseNode $e): string
    {
        if ($e->limit < 0 || ($e->offset !== null && $e->offset < 0)) {
            throw new UnprintableNodeException('LIMIT and OFFSET cannot be negative', $e);
        }
        if ($e->limit === 0 && $e->offset !== null) {
            // A bare OFFSET parses as limit 0.
            return $this->kw('OFFSET') . ' ' . $e->offset;
        }
        $text = $this->kw('LIMIT') . ' ' . $e->limit;
        if ($e->offset !== null) {
            $text .= ' ' . $this->kw('OFFSET') . ' ' . $e->offset;
        }
        return $text;
    }

    private function into(IntoClauseNode $e, ?IntoClauseNode $c): string
    {
        $cParts = null;
        if ($c !== null && $c->span !== null && ($target = $c->target->span) !== null) {
            $own = $this->ownTokens($c->span, [$target]);
            if ($own !== []) {
                $cParts = ['INTO' => self::tokenSpan($own[0]), 'target' => $target];
            }
        }
        return $this->join([
            self::part('INTO', $this->keep($cParts, 'INTO', $this->kw('INTO'))),
            self::part('target', $this->node($e->target, $c?->target)),
        ], $cParts);
    }

    private function union(UnionClauseNode $e, ?UnionClauseNode $c): string
    {
        $key = $e->all ? 'UNION ALL' : 'UNION';
        $cParts = null;
        if ($c !== null && $c->span !== null && ($query = $c->query->span) !== null) {
            $lead = $this->source?->tokensIn($c->span->start, $query->start) ?? [];
            if ($lead !== []) {
                $cParts = [
                    ($c->all ? 'UNION ALL' : 'UNION') => Span::fromTokens($lead[0], $lead[count($lead) - 1]),
                    'query' => $query,
                ];
            }
        }
        return $this->join([
            self::part($key, $this->keep($cParts, $key, $this->kw($key))),
            self::part('query', $this->node($e->query, $c?->query)),
        ], $cParts);
    }

    private function cte(CommonTableExpressionNode $e, ?CommonTableExpressionNode $c): string
    {
        $cParts = null;
        if ($c !== null && $c->span !== null && ($query = $c->query->span) !== null) {
            $cParts = ['query' => $query];
            foreach ($this->ownTokens($c->span, [$query]) as $i => $token) {
                $key = match (true) {
                    $i === 0 => 'name:' . $c->name,
                    $token->type === TokenType::KEYWORD_AS => 'AS',
                    $token->type === TokenType::PAREN_OPEN => '(',
                    $token->type === TokenType::PAREN_CLOSE => ')',
                    default => null,
                };
                if ($key !== null) {
                    $cParts[$key] = self::tokenSpan($token);
                }
            }
        }
        return $this->join([
            self::part('name:' . $e->name, $this->keep($cParts, 'name:' . $e->name, Lexical::identifier($e->name, $e))),
            self::part('AS', $this->keep($cParts, 'AS', $this->kw('AS'))),
            self::part('(', '(', false, true),
            self::part('query', $this->node($e->query, $c?->query)),
            self::part(')', ')', true),
        ], $cParts);
    }

    private function subQuery(SubQueryNode $e, ?SubQueryNode $c): string
    {
        $cParts = null;
        if ($c !== null && $c->span !== null && ($query = $c->query->span) !== null) {
            $own = $this->ownTokens($c->span, [$query]);
            if (count($own) >= 2) {
                $cParts = [
                    '(' => self::tokenSpan($own[0]),
                    'query' => $query,
                    ')' => self::tokenSpan($own[count($own) - 1]),
                ];
            }
        }
        return $this->join([
            self::part('(', '(', false, true),
            self::part('query', $this->node($e->query, $c?->query)),
            self::part(')', ')', true),
        ], $cParts);
    }

    // ------------------------------------------------------------------ conditions

    private function conditionGroup(ConditionGroupNode $e, ?ConditionGroupNode $c, int $ctx): string
    {
        $parenthesized = $ctx === self::CTX_NESTED;
        $cParts = null;
        if ($c !== null && $c->span !== null) {
            $cParts = [];
            $entrySpans = [];
            foreach ($c->entries as $entry) {
                $entrySpans[] = self::spanOf($entry['condition']);
            }
            $first = $entrySpans[0] ?? null;
            $last = $entrySpans !== [] ? $entrySpans[count($entrySpans) - 1] : null;
            if ($first !== null && $last !== null) {
                $cParts['entries'] = new Span($first->start, $last->end);
            }
            if ($this->isParenthesized($c)) {
                $own = $this->ownTokens($c->span, array_values(array_filter($entrySpans)));
                if ($own !== [] && $own[0]->type === TokenType::PAREN_OPEN) {
                    $cParts['('] = self::tokenSpan($own[0]);
                }
                $close = $own !== [] ? $own[count($own) - 1] : null;
                if ($close !== null && $close->type === TokenType::PAREN_CLOSE) {
                    $cParts[')'] = self::tokenSpan($close);
                }
            }
        }

        $conditions = [];
        $expected = [];
        foreach ($e->entries as $j => $entry) {
            $conditions[] = $entry['condition'];
            $expected[] = $j === 0 ? [[]] : [[$entry['logical']->value]];
        }
        $cConditions = $c !== null
            ? array_map(static fn (array $entry): AstNode => $entry['condition'], $c->entries)
            : null;

        $parts = [];
        if ($parenthesized) {
            $parts[] = self::part('(', '(', false, true);
        }
        $parts[] = self::part('entries', $this->renderList(
            $conditions,
            $cConditions,
            $expected,
            fn (AstNode $item, ?AstNode $counterpart): string => $this->node($item, $counterpart, self::CTX_NESTED)
        ));
        if ($parenthesized) {
            $parts[] = self::part(')', ')', true);
        }
        return $this->join($parts, $cParts);
    }

    private function condition(ConditionExpressionNode $e, ?ConditionExpressionNode $c): string
    {
        $operator = $e->operator;
        $right = $e->right;
        $isType = $operator === Operator::IS || $operator === Operator::NOT_IS;
        $isList = $operator === Operator::IN || $operator === Operator::NOT_IN;
        $isRange = $operator === Operator::BETWEEN || $operator === Operator::NOT_BETWEEN;
        if ($isType !== $right instanceof Type || (($isList || $isRange) !== is_array($right))) {
            throw new UnprintableNodeException(
                sprintf('The right side of %s does not match the operator', $operator->value),
                $e
            );
        }
        $this->assertOperand($e->left, $e);

        $cParts = $c !== null ? $this->conditionParts($c) : null;
        $opKey = 'op:' . $operator->value;
        $parts = [
            self::part('left', $this->node($e->left, $c?->left)),
            self::part($opKey, $this->keep($cParts, $opKey, $this->kw($operator->value))),
        ];
        $cRight = $c?->right;
        if ($right instanceof Type) {
            $typeKey = 'is:' . $right->value;
            $parts[] = self::part($typeKey, $this->keep($cParts, $typeKey, $this->isTypeName($right, $e)));
        } elseif (is_array($right) && $isList) {
            foreach ($right as $value) {
                $this->assertPrimary($value, $e);
            }
            $parts[] = self::part('(', '(', false, true);
            $parts[] = self::part('values', $this->renderList(
                array_values($right),
                is_array($cRight) ? array_values($cRight) : null,
                self::uniformSeparators(count($right), [',']),
                fn (AstNode $item, ?AstNode $counterpart): string => $this->node($item, $counterpart)
            ));
            $parts[] = self::part(')', ')', true);
        } elseif (is_array($right)) {
            if (count($right) !== 2) {
                throw new UnprintableNodeException('BETWEEN needs exactly two bounds', $e);
            }
            [$min, $max] = array_values($right);
            $this->assertPrimary($min, $e);
            $this->assertPrimary($max, $e);
            $cBounds = is_array($cRight) ? array_values($cRight) : [];
            $parts[] = self::part('min', $this->node($min, $cBounds[0] ?? null));
            $parts[] = self::part('AND', $this->keep($cParts, 'AND', $this->kw('AND')));
            $parts[] = self::part('max', $this->node($max, $cBounds[1] ?? null));
        } else {
            $this->assertOperand($right, $e);
            $parts[] = self::part('right', $this->node($right, $cRight instanceof ExpressionNode ? $cRight : null));
        }
        return $this->join($parts, $cParts);
    }

    /**
     * @return array<string, Span>|null
     */
    private function conditionParts(ConditionExpressionNode $c): ?array
    {
        $left = self::spanOf($c->left);
        if ($c->span === null || $left === null) {
            return null;
        }
        $cParts = ['left' => $left];
        $children = [$left];
        $right = $c->right;
        if ($right instanceof ExpressionNode) {
            $rightSpan = self::spanOf($right);
            if ($rightSpan === null) {
                return null;
            }
            $cParts['right'] = $rightSpan;
            $children[] = $rightSpan;
        } elseif (is_array($right)) {
            $spans = array_values(array_map(static fn (AstNode $n): ?Span => self::spanOf($n), $right));
            if (in_array(null, $spans, true)) {
                return null;
            }
            /** @var list<Span> $spans */
            $children = array_merge($children, $spans);
            if ($c->operator === Operator::BETWEEN || $c->operator === Operator::NOT_BETWEEN) {
                if (count($spans) === 2) {
                    $cParts['min'] = $spans[0];
                    $cParts['max'] = $spans[1];
                }
            } elseif ($spans !== []) {
                $cParts['values'] = new Span($spans[0]->start, $spans[count($spans) - 1]->end);
            }
        }

        $own = $this->ownTokens($c->span, $children);
        $operatorTokens = [];
        foreach ($own as $i => $token) {
            if ($right instanceof Type && $i === count($own) - 1) {
                $cParts['is:' . $right->value] = self::tokenSpan($token);
            } elseif ($token->type === TokenType::PAREN_OPEN && is_array($right) && !isset($cParts['('])) {
                $cParts['('] = self::tokenSpan($token);
            } elseif ($token->type === TokenType::PAREN_CLOSE && isset($cParts['('])) {
                $cParts[')'] = self::tokenSpan($token);
            } elseif (
                $token->type === TokenType::KEYWORD_AND
                && isset($cParts['min'])
                && $token->position->offset > $cParts['min']->start
            ) {
                $cParts['AND'] = self::tokenSpan($token);
            } elseif (!isset($cParts['(']) && !isset($cParts['AND']) && $token->type !== TokenType::COMMA) {
                $operatorTokens[] = $token;
            }
        }
        if ($operatorTokens !== []) {
            $lastOperatorToken = $operatorTokens[count($operatorTokens) - 1];
            $cParts['op:' . $c->operator->value] = Span::fromTokens($operatorTokens[0], $lastOperatorToken);
        }
        return $cParts;
    }

    private function isTypeName(Type $type, AstNode $node): string
    {
        if (preg_match('/^[a-z]+$/', $type->value) !== 1) {
            throw new UnprintableNodeException(sprintf('IS %s cannot be written in FQL', $type->value), $node);
        }
        return $this->kw($type->value);
    }

    // ------------------------------------------------------------------ expressions

    private function binary(BinaryOpNode $e, ?BinaryOpNode $c): string
    {
        $this->assertOperand($e->left, $e);
        $this->assertOperand($e->right, $e);
        $precedence = $e->operator->precedence();
        // FQL has no parentheses in arithmetic: the tree must match left-associative
        // precedence parsing exactly.
        if ($e->left instanceof BinaryOpNode && $e->left->operator->precedence() < $precedence) {
            throw new UnprintableNodeException('FQL cannot write (a + b) * c: arithmetic has no parentheses', $e);
        }
        if ($e->right instanceof BinaryOpNode && $e->right->operator->precedence() <= $precedence) {
            throw new UnprintableNodeException('FQL cannot write a - (b - c): arithmetic has no parentheses', $e);
        }

        $cParts = null;
        $left = $c !== null ? self::spanOf($c->left) : null;
        $right = $c !== null ? self::spanOf($c->right) : null;
        if ($c !== null && $c->span !== null && $left !== null && $right !== null) {
            $cParts = ['left' => $left, 'right' => $right];
            $own = $this->ownTokens($c->span, [$left, $right]);
            if ($own !== []) {
                $cParts['op:' . $c->operator->value] = Span::fromTokens($own[0], $own[count($own) - 1]);
            }
        }
        $opKey = 'op:' . $e->operator->value;
        $rightText = $this->node($e->right, $c?->right);
        if ($e->operator === BinaryOperator::MULTIPLY && preg_match('/^[-+]\d/', $rightText) === 1) {
            throw new UnprintableNodeException('FQL cannot write a signed number right after *', $e);
        }
        return $this->join([
            self::part('left', $this->node($e->left, $c?->left)),
            self::part($opKey, $this->keep($cParts, $opKey, $e->operator->value)),
            self::part('right', $rightText),
        ], $cParts);
    }

    private function functionCall(FunctionCallNode $e, ?FunctionCallNode $c): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $e->name) !== 1 || Lexical::isReservedWord($e->name)) {
            throw new UnprintableNodeException(sprintf('Function name "%s" cannot be written in FQL', $e->name), $e);
        }
        if (strtoupper($e->name) === 'IF') {
            if (count($e->arguments) !== 3 || !$e->arguments[0] instanceof ConditionExpressionNode) {
                throw new UnprintableNodeException('IF() needs a condition and two expressions', $e);
            }
        } else {
            foreach ($e->arguments as $argument) {
                if (!$argument instanceof CollectObjectExpressionNode) {
                    $this->assertOperand($argument, $e);
                }
            }
        }

        $cParts = null;
        if ($c !== null && $c->span !== null) {
            $argSpans = self::spansOf($c->arguments);
            $own = $this->ownTokens($c->span, $argSpans);
            if (count($own) >= 3) {
                $cParts = [
                    'fn:' . $c->name => self::tokenSpan($own[0]),
                    '(' => self::tokenSpan($own[1]),
                    ')' => self::tokenSpan($own[count($own) - 1]),
                ];
                if ($c->distinct && $own[2]->type === TokenType::KEYWORD_DISTINCT) {
                    $cParts['DISTINCT'] = self::tokenSpan($own[2]);
                }
                if ($argSpans !== [] && count($argSpans) === count($c->arguments)) {
                    $cParts['args'] = new Span($argSpans[0]->start, $argSpans[count($argSpans) - 1]->end);
                }
            }
        }
        $nameKey = 'fn:' . $e->name;
        $parts = [
            self::part($nameKey, $this->keep($cParts, $nameKey, $this->kw($e->name)), false, true),
            self::part('(', '(', true, true),
        ];
        if ($e->distinct) {
            $parts[] = self::part('DISTINCT', $this->keep($cParts, 'DISTINCT', $this->kw('DISTINCT')));
        }
        $parts[] = self::part('args', $this->renderList(
            $e->arguments,
            $c?->arguments,
            self::uniformSeparators(count($e->arguments), [',']),
            fn (AstNode $item, ?AstNode $counterpart): string => $this->node($item, $counterpart)
        ));
        $parts[] = self::part(')', ')', true);
        return $this->join($parts, $cParts);
    }

    private function cast(CastExpressionNode $e, ?CastExpressionNode $c): string
    {
        $this->assertPrimary($e->value, $e);
        $typeName = match ($e->targetType) {
            Type::INTEGER => 'INT',
            Type::FLOAT => 'DOUBLE',
            Type::NUMBER => 'NUMBER',
            Type::BOOLEAN => 'BOOLEAN',
            Type::STRING => 'STRING',
            Type::NULL => 'NULL',
            Type::ARRAY => 'ARRAY',
            Type::OBJECT => 'OBJECT',
            default => throw new UnprintableNodeException(
                sprintf('CAST to %s cannot be written in FQL', $e->targetType->value),
                $e
            ),
        };
        $cParts = null;
        if ($c !== null && $c->span !== null && ($value = self::spanOf($c->value)) !== null) {
            $own = $this->ownTokens($c->span, [$value]);
            if (count($own) === 5) {
                $cParts = [
                    'fn' => self::tokenSpan($own[0]),
                    '(' => self::tokenSpan($own[1]),
                    'value' => $value,
                    'AS' => self::tokenSpan($own[2]),
                    'type:' . $c->targetType->value => self::tokenSpan($own[3]),
                    ')' => self::tokenSpan($own[4]),
                ];
            }
        }
        $typeKey = 'type:' . $e->targetType->value;
        return $this->join([
            self::part('fn', $this->keep($cParts, 'fn', $this->kw('CAST')), false, true),
            self::part('(', '(', true, true),
            self::part('value', $this->node($e->value, $c?->value)),
            self::part('AS', $this->keep($cParts, 'AS', $this->kw('AS'))),
            self::part($typeKey, $this->keep($cParts, $typeKey, $this->kw($typeName))),
            self::part(')', ')', true),
        ], $cParts);
    }

    private function matchAgainst(MatchAgainstNode $e, ?MatchAgainstNode $c): string
    {
        if ($e->fields === []) {
            throw new UnprintableNodeException('MATCH() needs at least one column', $e);
        }
        foreach ($e->fields as $field) {
            if (!$field instanceof ColumnReferenceNode) {
                throw new UnprintableNodeException('MATCH() accepts column references only', $e);
            }
        }
        $cParts = null;
        if ($c !== null && $c->span !== null) {
            $fieldSpans = self::spansOf($c->fields);
            $own = $this->ownTokens($c->span, $fieldSpans);
            if ($fieldSpans !== [] && count($own) >= 6) {
                $cParts = [
                    'MATCH' => self::tokenSpan($own[0]),
                    '(' => self::tokenSpan($own[1]),
                    'fields' => new Span($fieldSpans[0]->start, $fieldSpans[count($fieldSpans) - 1]->end),
                ];
                $rest = array_values(array_filter(
                    $own,
                    static fn (Token $t): bool => $t->position->offset >= $fieldSpans[count($fieldSpans) - 1]->end
                ));
                if (count($rest) >= 4) {
                    $cParts[')'] = self::tokenSpan($rest[0]);
                    $cParts['AGAINST'] = self::tokenSpan($rest[1]);
                    $cParts['(2'] = self::tokenSpan($rest[2]);
                    $cParts[')2'] = self::tokenSpan($rest[count($rest) - 1]);
                    $payload = array_slice($rest, 3, -1);
                    if ($payload !== []) {
                        $payloadKey = 'payload:' . $c->mode->value . ':' . $c->searchQuery;
                        $cParts[$payloadKey] = Span::fromTokens($payload[0], $payload[count($payload) - 1]);
                    }
                }
            }
        }
        $payloadKey = 'payload:' . $e->mode->value . ':' . $e->searchQuery;
        return $this->join([
            self::part('MATCH', $this->keep($cParts, 'MATCH', $this->kw('MATCH')), false, true),
            self::part('(', '(', true, true),
            self::part('fields', $this->renderList(
                $e->fields,
                $c?->fields,
                self::uniformSeparators(count($e->fields), [',']),
                fn (AstNode $item, ?AstNode $counterpart): string => $this->node($item, $counterpart)
            )),
            self::part(')', ')', true),
            self::part('AGAINST', $this->keep($cParts, 'AGAINST', $this->kw('AGAINST')), false, true),
            self::part('(2', '(', true, true),
            self::part($payloadKey, $this->keep(
                $cParts,
                $payloadKey,
                Lexical::stringLiteral(sprintf('%s IN %s MODE', $e->searchQuery, $e->mode->value), $e)
            )),
            self::part(')2', ')', true),
        ], $cParts);
    }

    private function caseExpression(CaseExpressionNode $e, ?CaseExpressionNode $c): string
    {
        if ($e->branches === []) {
            throw new UnprintableNodeException('CASE needs at least one WHEN branch', $e);
        }
        $cParts = null;
        if ($c !== null && $c->span !== null) {
            $branchSpans = self::spansOf($c->branches);
            $elseSpan = $c->else !== null ? self::spanOf($c->else) : null;
            $own = $this->ownTokens($c->span, array_values(array_filter([...$branchSpans, $elseSpan])));
            if ($own !== [] && $branchSpans !== []) {
                $cParts = [
                    'CASE' => self::tokenSpan($own[0]),
                    'branches' => new Span($branchSpans[0]->start, $branchSpans[count($branchSpans) - 1]->end),
                    'END' => self::tokenSpan($own[count($own) - 1]),
                ];
                foreach ($own as $token) {
                    if ($token->type === TokenType::KEYWORD_ELSE) {
                        $cParts['ELSE'] = self::tokenSpan($token);
                    }
                }
                if ($elseSpan !== null) {
                    $cParts['else'] = $elseSpan;
                }
            }
        }
        $parts = [
            self::part('CASE', $this->keep($cParts, 'CASE', $this->kw('CASE'))),
            self::part('branches', $this->renderList(
                $e->branches,
                $c?->branches,
                self::uniformSeparators(count($e->branches), []),
                fn (AstNode $item, ?AstNode $counterpart): string => $this->node($item, $counterpart)
            )),
        ];
        if ($e->else !== null) {
            $this->assertOperand($e->else, $e);
            $parts[] = self::part('ELSE', $this->keep($cParts, 'ELSE', $this->kw('ELSE')));
            $parts[] = self::part('else', $this->node($e->else, $c?->else));
        }
        $parts[] = self::part('END', $this->keep($cParts, 'END', $this->kw('END')));
        return $this->join($parts, $cParts);
    }

    private function whenBranch(WhenBranchNode $e, ?WhenBranchNode $c): string
    {
        $this->assertOperand($e->then, $e);
        $cParts = null;
        $condition = $c?->condition->span;
        $then = $c !== null ? self::spanOf($c->then) : null;
        if ($c !== null && $c->span !== null && $condition !== null && $then !== null) {
            $own = $this->ownTokens($c->span, [$condition, $then]);
            if (count($own) === 2) {
                $cParts = [
                    'WHEN' => self::tokenSpan($own[0]),
                    'condition' => $condition,
                    'THEN' => self::tokenSpan($own[1]),
                    'then' => $then,
                ];
            }
        }
        return $this->join([
            self::part('WHEN', $this->keep($cParts, 'WHEN', $this->kw('WHEN'))),
            self::part('condition', $this->node($e->condition, $c?->condition)),
            self::part('THEN', $this->keep($cParts, 'THEN', $this->kw('THEN'))),
            self::part('then', $this->node($e->then, $c?->then)),
        ], $cParts);
    }

    private function collectObject(CollectObjectExpressionNode $e): string
    {
        if ($e->selectItems === []) {
            throw new UnprintableNodeException('COLLECT_OBJECT needs at least one item', $e);
        }
        $items = [];
        foreach ($e->selectItems as $item) {
            $this->assertOperand($item['expression'], $e);
            $text = $this->node($item['expression'], null);
            if ($item['alias'] !== null) {
                $text .= ' ' . $this->kw('AS') . ' ' . Lexical::identifier($item['alias'], $e);
            }
            $items[] = $text;
        }
        $text = implode(', ', $items);
        if ($e->orderings !== []) {
            $orderings = array_map(fn (OrderByItemNode $item): string => $this->node($item, null), $e->orderings);
            $text .= ' ' . $this->kw('ORDER BY') . ' ' . implode(', ', $orderings);
        }
        return $text;
    }

    private function cteReference(CteReferenceNode $e): string
    {
        $keyword = preg_match('/^[A-Za-z]+$/', $e->name) === 1 && Lexical::isReservedWord($e->name);
        if ($keyword || preg_match('/^[\w*.\-]+$/u', $e->name) !== 1) {
            throw new UnprintableNodeException(sprintf('CTE name "%s" cannot be referenced in FROM', $e->name), $e);
        }
        return $e->name;
    }

    private function fileQuery(FileQueryNode $e, ?FileQueryNode $c): string
    {
        if ($e->raw !== '') {
            try {
                if ((string) new FileQuery($e->raw) === (string) $e->fileQuery) {
                    return $e->raw;
                }
            } catch (\Throwable) {
                // stale raw: write it from the FileQuery below
            }
        }
        // Named arguments in the original keep the copy named; a quoted file path is
        // skipped so that a `, key: ` inside it does not count.
        $named = $c !== null
            && preg_match(
                '/,\s*\w+\s*:\s*"/',
                (string) preg_replace('/^\w+\(\s*(?:"[^"]*"|\'[^\']*\')/', '', $c->raw)
            ) === 1;
        $text = $e->fileQuery->toFql($named);
        if (!$this->isFileQueryToken($text)) {
            throw new UnprintableNodeException(sprintf('File query "%s" cannot be written in FQL', $text), $e);
        }
        return $text;
    }

    private function isFileQueryToken(string $text): bool
    {
        try {
            $tokens = (new Tokenizer())->tokenize('FROM ' . $text);
        } catch (ParseException) {
            return false;
        }
        return count($tokens) === 4
            && $tokens[2]->type === TokenType::FILE_QUERY
            && $tokens[2]->raw === $text;
    }

    // ------------------------------------------------------------------ validation

    /**
     * An operand of arithmetic, a comparison, a function argument or a SELECT field.
     */
    private function assertOperand(AstNode $node, AstNode $parent): void
    {
        if (
            $node instanceof ConditionExpressionNode
            || $node instanceof ConditionGroupNode
            || $node instanceof SubQueryNode
            || $node instanceof FileQueryNode
            || $node instanceof CteReferenceNode
            || $node instanceof WhenBranchNode
            || $node instanceof CollectObjectExpressionNode
        ) {
            throw new UnprintableNodeException(
                sprintf('%s cannot be used as an operand in FQL', $node::class),
                $parent
            );
        }
    }

    /**
     * A single term (IN values, BETWEEN bounds, CAST value): no arithmetic.
     */
    private function assertPrimary(AstNode $node, AstNode $parent): void
    {
        $this->assertOperand($node, $parent);
        if ($node instanceof BinaryOpNode) {
            throw new UnprintableNodeException(
                'Arithmetic is not allowed here in FQL (IN list, BETWEEN, CAST)',
                $parent
            );
        }
    }

    // ------------------------------------------------------------------ lists and gaps

    /**
     * Writes a list, reusing the original separators. `$expected[$j]` lists the accepted
     * separator token sequences before item `$j` (the first one is written when a new
     * separator is needed); `$expected[0]` is the lead before the first item, which is part
     * of the list only when `$cLeadStart` marks where it starts in the original.
     *
     * @param list<AstNode> $items
     * @param list<AstNode>|null $original
     * @param array<int, list<list<string>>> $expected
     * @param \Closure(AstNode, ?AstNode): string $render
     */
    private function renderList(
        array $items,
        ?array $original,
        array $expected,
        \Closure $render,
        ?int $cLeadStart = null
    ): string {
        if ($items === []) {
            return '';
        }
        $original = $this->source !== null ? array_values($original ?? []) : [];
        $slots = self::matchItems(array_values($items), $original);

        $texts = [];
        foreach ($items as $j => $item) {
            $slot = $slots[$j];
            $texts[] = $render($item, $slot !== null ? $original[$slot] : null);
        }

        $gaps = [];
        for ($k = 0, $n = count($original) - 1; $k < $n; $k++) {
            $a = self::spanOf($original[$k]);
            $b = self::spanOf($original[$k + 1]);
            $gaps[$k] = ($a !== null && $b !== null) ? $this->gapText($a->end, $b->start) : null;
        }
        $last = $original !== [] ? self::spanOf($original[count($original) - 1]) : null;
        $trail = $last !== null ? $this->trailingComment($last->end) : null;

        $body = $this->joinItems($texts, array_values($slots), $gaps, count($original), $expected, $trail);

        $lead = $expected[0] ?? [[]];
        $firstOriginal = $original !== [] ? self::spanOf($original[0]) : null;
        if ($cLeadStart !== null && $firstOriginal !== null) {
            $leadText = $this->gapText($cLeadStart, $firstOriginal->start);
            $prefix = $this->tokensMatch($leadText, $lead)
                ? $leadText
                : $this->rewriteGap($leadText, $lead, true, true);
        } else {
            $prefix = $lead[0] === [] ? '' : $this->separatorText($lead[0]) . ' ';
        }
        return $this->concat($prefix, $body);
    }

    /**
     * @param list<string> $separator
     * @return array<int, list<list<string>>>
     */
    private static function uniformSeparators(int $count, array $separator): array
    {
        $expected = [];
        for ($j = 0; $j < $count; $j++) {
            $expected[$j] = $j === 0 ? [[]] : [$separator];
        }
        return $expected;
    }

    /**
     * Joins rendered items. `$slots[$j]` is the original index item `$j` stands for (null
     * for a new item), `$gaps[$k]` the original text between original items `$k` and
     * `$k + 1`, `$trail` a comment written after the last original item on its line.
     *
     * Every gap splits at its first line break into a head (what follows the previous
     * item on its line: its trailing comments, and the separator when it ends the line)
     * and a tail (the next item's leading line breaks, comments and indentation, and the
     * separator when it starts the line). Items keep their own comments when neighbours
     * are added, removed or reordered; separators between former neighbours stay verbatim.
     *
     * @param list<string> $texts
     * @param list<?int> $slots
     * @param array<int, ?string> $gaps
     * @param array<int, list<list<string>>> $expected
     * @param array{start: int, end: int}|null $trail
     */
    private function joinItems(
        array $texts,
        array $slots,
        array $gaps,
        int $count,
        array $expected,
        ?array $trail,
        bool $preferNewline = false
    ): string {
        $multiLine = false;
        foreach ($gaps as $gap) {
            if ($gap !== null && str_contains($gap, "\n")) {
                $multiLine = true;
                break;
            }
        }

        $out = $texts[0] ?? '';
        for ($j = 1, $n = count($texts); $j < $n; $j++) {
            $previous = $slots[$j - 1];
            $current = $slots[$j];
            $accepted = $expected[$j] ?? [[]];
            if (
                $previous !== null
                && $current === $previous + 1
                && isset($gaps[$previous])
                && $this->tokensMatch($gaps[$previous], $accepted)
            ) {
                $separator = $gaps[$previous];
            } else {
                $separator = $this->composeSeparator(
                    $previous,
                    $current,
                    $gaps,
                    $count,
                    $accepted,
                    $multiLine ? $trail : null,
                    $preferNewline
                );
            }
            $out = $this->concat($this->concat($out, $separator), $texts[$j]);
        }

        $lastSlot = $slots[count($slots) - 1] ?? null;
        if ($lastSlot !== null && $lastSlot < $count - 1 && isset($gaps[$lastSlot])) {
            // The item is last now: it keeps the comments that followed it.
            $out = $this->concat($out, $this->commentsOf($this->splitGap($gaps[$lastSlot])[0]));
        }
        if ($trail !== null && $multiLine && $count > 0 && !in_array($count - 1, $slots, true)) {
            // The last original item was removed: its trailing comment goes with it.
            $this->consume($trail['start'], $trail['end']);
        }
        return $out;
    }

    /**
     * Separator between two items that were not neighbours in the original.
     *
     * @param array<int, ?string> $gaps
     * @param list<list<string>> $accepted
     * @param array{start: int, end: int}|null $trail
     */
    private function composeSeparator(
        ?int $previous,
        ?int $current,
        array $gaps,
        int $count,
        array $accepted,
        ?array $trail,
        bool $preferNewline
    ): string {
        $template = $this->nearestGap($gaps, $current ?? $previous ?? 0, $preferNewline);
        [$templateHead, $templateTail] = $template !== null ? $this->splitGap($template) : [null, null];
        $cleanTail = $templateTail !== null ? $this->clean($templateTail) : null;

        if ($previous !== null && isset($gaps[$previous])) {
            $head = $this->splitGap($gaps[$previous])[0];
        } else {
            $head = $templateHead !== null ? $this->clean($templateHead) : null;
            if ($head !== null && trim($head) === '' && $cleanTail !== null && str_starts_with($cleanTail, ',')) {
                // `a , b` → `a, b`: no space in front of a comma.
                $head = '';
            }
            if (
                $previous !== null
                && $previous === $count - 1
                && $trail !== null
                && $this->source !== null
                && $cleanTail !== null
                && str_contains($cleanTail, "\n")
            ) {
                // Appending after the last original item: its trailing comment stays on its line.
                $head = rtrim($head ?? '', " \t") . $this->source->slice($trail['start'], $trail['end']);
                $this->consume($trail['start'], $trail['end']);
            }
        }

        if ($current !== null && $current > 0 && isset($gaps[$current - 1])) {
            $tail = $this->splitGap($gaps[$current - 1])[1];
        } else {
            $tail = $cleanTail;
        }

        if ($head === null && $tail === null) {
            return $this->defaultSeparator($accepted[0]);
        }
        $head ??= '';
        $tail ??= '';
        if (preg_match('/^\r?\n/', $tail) === 1) {
            $head = rtrim($head, " \t");
        }
        $separator = $head . $tail;
        return $this->tokensMatch($separator, $accepted) ? $separator : $this->rewriteGap($separator, $accepted, true);
    }

    /**
     * Splits a gap at its first line break: [head, tail]. Without a line break the head is
     * the text before the first separator token (or the whole gap when it has none).
     *
     * @return array{0: string, 1: string}
     */
    private function splitGap(string $gap): array
    {
        $tokens = $this->gapTokens($gap);
        if ($tokens === null) {
            return [$gap, ''];
        }
        $offset = 0;
        $firstToken = null;
        foreach ($tokens as $token) {
            if ($token->type === TokenType::WHITESPACE && ($newline = strpos($token->raw, "\n")) !== false) {
                if ($newline > 0 && $token->raw[$newline - 1] === "\r") {
                    $newline--;
                }
                $at = $offset + $newline;
                return [substr($gap, 0, $at), substr($gap, $at)];
            }
            if ($firstToken === null && !$token->type->isTrivia()) {
                $firstToken = $offset;
            }
            $offset += strlen($token->raw);
        }
        if ($firstToken === null) {
            return [$gap, ''];
        }
        return [substr($gap, 0, $firstToken), substr($gap, $firstToken)];
    }

    /**
     * Separator text without comments: its tokens, and whitespace collapsed to a single
     * space or to a line break plus the indentation of its last line.
     */
    private function clean(string $text): string
    {
        $tokens = $this->gapTokens($text);
        if ($tokens === null) {
            return $text;
        }
        $out = '';
        $whitespace = '';
        foreach ($tokens as $token) {
            if ($token->type === TokenType::WHITESPACE) {
                $whitespace .= $token->raw;
            } elseif ($token->type === TokenType::COMMENT_BLOCK) {
                $whitespace .= ' ';
            } elseif ($token->type !== TokenType::COMMENT_LINE) {
                $out .= self::collapse($whitespace) . $token->raw;
                $whitespace = '';
            }
        }
        return $out . self::collapse($whitespace);
    }

    private static function collapse(string $whitespace): string
    {
        if ($whitespace === '') {
            return '';
        }
        $newline = strrpos($whitespace, "\n");
        if ($newline === false) {
            return ' ';
        }
        $lineBreak = str_contains($whitespace, "\r\n") ? "\r\n" : "\n";
        return $lineBreak . substr($whitespace, $newline + 1);
    }

    /**
     * The comments of a gap head, without its separator tokens (` -- note`).
     */
    private function commentsOf(string $head): string
    {
        $tokens = $this->gapTokens($head) ?? [];
        $text = '';
        $hasComment = false;
        foreach ($tokens as $token) {
            if ($token->type->isTrivia()) {
                $text .= $token->raw;
                $hasComment = $hasComment || $token->type !== TokenType::WHITESPACE;
            }
        }
        if (!$hasComment) {
            return '';
        }
        return ' ' . ltrim($text);
    }

    /**
     * A comment written after `$offset` on the same line (only whitespace before it, only
     * a line break after it): the trailing comment of the item ending at `$offset`.
     *
     * @return array{start: int, end: int}|null
     */
    private function trailingComment(int $offset): ?array
    {
        if ($this->source === null) {
            return null;
        }
        $sql = $this->source->sql;
        $newline = strpos($sql, "\n", $offset);
        $end = $newline === false ? strlen($sql) : $newline;
        if ($end > $offset && $sql[$end - 1] === "\r") {
            $end--;
        }
        $text = substr($sql, $offset, $end - $offset);
        $tokens = $this->gapTokens($text);
        if ($tokens === null || $tokens === []) {
            return null;
        }
        $hasComment = false;
        foreach ($tokens as $token) {
            if (!$token->type->isTrivia()) {
                return null;
            }
            $hasComment = $hasComment || $token->type !== TokenType::WHITESPACE;
        }
        return $hasComment ? ['start' => $offset, 'end' => $end] : null;
    }

    /**
     * Marks original text as already written elsewhere (or dropped) so no gap repeats it.
     */
    private function consume(int $start, int $end): void
    {
        $this->consumed[] = [$start, $end];
    }

    /**
     * Original text between `$start` and `$end`, without the parts consumed by an edit.
     */
    private function gapText(int $start, int $end): string
    {
        if ($this->source === null) {
            return '';
        }
        $ranges = array_filter($this->consumed, static fn (array $r): bool => $r[0] >= $start && $r[1] <= $end);
        usort($ranges, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $text = '';
        $cursor = $start;
        foreach ($ranges as [$from, $to]) {
            if ($from < $cursor) {
                continue;
            }
            $text .= $this->source->slice($cursor, $from);
            $cursor = $to;
        }
        return $text . $this->source->slice($cursor, $end);
    }

    /**
     * @param array<int, ?string> $gaps
     */
    private function nearestGap(array $gaps, int $anchor, bool $preferNewline): ?string
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;
        $bestNewline = null;
        $bestNewlineDistance = PHP_INT_MAX;
        foreach ($gaps as $k => $gap) {
            if ($gap === null) {
                continue;
            }
            $distance = min(abs($k - $anchor), abs($k + 1 - $anchor));
            if ($distance < $bestDistance) {
                $best = $gap;
                $bestDistance = $distance;
            }
            if (str_contains($gap, "\n") && $distance < $bestNewlineDistance) {
                $bestNewline = $gap;
                $bestNewlineDistance = $distance;
            }
        }
        return $preferNewline && $bestNewline !== null ? $bestNewline : $best;
    }

    /**
     * @param list<list<string>> $expected
     */
    private function tokensMatch(string $gap, array $expected): bool
    {
        $tokens = $this->gapTokens($gap);
        if ($tokens === null) {
            return false;
        }
        $significant = [];
        foreach ($tokens as $token) {
            if (!$token->type->isTrivia()) {
                $significant[] = strtoupper($token->raw);
            }
        }
        return in_array($significant, $expected, true);
    }

    /**
     * Rewrites a separator to one of the expected token sequences, keeping its whitespace
     * layout (and comments, when `$keepComments`).
     *
     * @param list<list<string>> $expected
     */
    private function rewriteGap(string $gap, array $expected, bool $keepComments, bool $isLead = false): string
    {
        $tokens = $this->gapTokens($gap);
        if ($tokens === null) {
            return $isLead && $expected[0] === [] ? '' : $this->defaultSeparator($expected[0]);
        }
        $first = null;
        $last = null;
        $significant = [];
        foreach ($tokens as $i => $token) {
            if (!$token->type->isTrivia()) {
                $first ??= $i;
                $last = $i;
                $significant[] = strtoupper($token->raw);
            }
        }
        if (in_array($significant, $expected, true)) {
            return $keepComments ? $gap : $this->clean($gap);
        }
        $target = $expected[0];

        if ($first === null || $last === null) {
            $trivia = $this->trivia($tokens, $keepComments);
            if ($target === []) {
                return $trivia === '' && !$isLead ? ' ' : $trivia;
            }
            // Only whitespace and comments: place the tokens before the line break.
            $text = $this->separatorText($target);
            [$head, $tail] = $this->splitGap($trivia);
            if ($target[0] === ',') {
                return $head . $text . ($tail === '' ? ' ' : $tail);
            }
            if ($tail !== '') {
                return $head . $tail . $text . ' ';
            }
            return ($isLead ? '' : ' ') . $text . ' ';
        }

        $leadText = $this->trivia(array_slice($tokens, 0, $first), $keepComments);
        $trailText = $this->trivia(array_slice($tokens, $last + 1), $keepComments);
        if ($target === []) {
            if ($isLead) {
                return '';
            }
            $merged = $this->trivia(
                array_merge(array_slice($tokens, 0, $first), array_slice($tokens, $last + 1)),
                $keepComments
            );
            return $merged === '' ? ' ' : $merged;
        }

        $core = $this->separatorText($target);
        // Comma layout (`a,\n  b`) turned into a keyword separator: start the new line
        // with the keyword (`a\n  AND b`).
        if (
            $target[0] !== ','
            && !str_contains($leadText, "\n")
            && str_contains($trailText, "\n")
            && trim($trailText) === ''
        ) {
            $leadText = $trailText;
            $trailText = ' ';
        }
        if ($target[0] === ',' && !str_contains($leadText, "\n") && trim($leadText) === '') {
            $leadText = '';
        }
        if ($leadText === '' && !$isLead && $target[0] !== ',') {
            $leadText = ' ';
        }
        if ($trailText === '') {
            $trailText = ' ';
        }
        return $leadText . $core . $trailText;
    }

    /**
     * @param list<string> $target
     */
    private function separatorText(array $target): string
    {
        $text = '';
        foreach ($target as $i => $token) {
            $word = $token === ',' ? ',' : $this->kw($token);
            $text .= ($i === 0 || $token === ',') ? $word : ' ' . $word;
        }
        return $text;
    }

    /**
     * @param list<string> $separator
     */
    private function defaultSeparator(array $separator): string
    {
        if ($separator === []) {
            return ' ';
        }
        $text = $this->separatorText($separator);
        return $separator[0] === ',' ? $text . ' ' : ' ' . $text . ' ';
    }

    /**
     * Whitespace and comments of a token run; without comments a run containing a line
     * break collapses to the break plus the indentation of its last line.
     *
     * @param list<Token> $tokens
     */
    private function trivia(array $tokens, bool $keepComments): string
    {
        if ($keepComments) {
            return implode('', array_map(static fn (Token $t): string => $t->raw, $tokens));
        }
        $whitespace = '';
        foreach ($tokens as $token) {
            if ($token->type === TokenType::WHITESPACE) {
                $whitespace .= $token->raw;
            } elseif ($token->type === TokenType::COMMENT_BLOCK) {
                $whitespace .= ' ';
            }
        }
        return self::collapse($whitespace);
    }

    /**
     * @return list<Token>|null
     */
    private function gapTokens(string $gap): ?array
    {
        try {
            $tokens = (new Tokenizer())->tokenize($gap);
        } catch (ParseException) {
            return null;
        }
        array_pop($tokens); // EOF
        return array_values($tokens);
    }

    // ------------------------------------------------------------------ parts

    /**
     * @return array{k: string, t: string, gl: bool, gr: bool}
     */
    private static function part(string $key, string $text, bool $glueLeft = false, bool $glueRight = false): array
    {
        return ['k' => $key, 't' => $text, 'gl' => $glueLeft, 'gr' => $glueRight];
    }

    /**
     * Concatenates parts. Between two parts that were neighbours in the counterpart the
     * original gap is kept; otherwise a single space, or nothing next to parentheses.
     *
     * @param list<array{k: string, t: string, gl: bool, gr: bool}> $parts
     * @param array<string, Span>|null $cParts original part spans by key
     */
    private function join(array $parts, ?array $cParts): string
    {
        $keys = [];
        if ($cParts !== null && $this->source !== null) {
            $sorted = $cParts;
            uasort($sorted, static fn (Span $a, Span $b): int => $a->start <=> $b->start);
            $keys = array_keys($sorted);
        }
        $order = array_flip($keys);
        $present = [];
        foreach ($parts as $part) {
            if ($part['t'] !== '') {
                $present[$part['k']] = true;
            }
        }
        $cGap = fn (string $a, string $b): string => $cParts !== null
            ? $this->gapText($cParts[$a]->end, $cParts[$b]->start)
            : '';

        $out = '';
        $previous = null;
        $lastOriginal = null;
        foreach ($parts as $part) {
            if ($part['t'] === '') {
                continue;
            }
            if ($previous !== null) {
                $gap = null;
                $key = $part['k'];
                if (isset($order[$previous['k']], $order[$key]) && $order[$key] === $order[$previous['k']] + 1) {
                    $gap = $cGap($previous['k'], $key);
                } elseif (isset($order[$key]) && $order[$key] > 0) {
                    $predecessor = $keys[$order[$key] - 1];
                    if (!isset($present[$predecessor])) {
                        // The part before it was removed (or replaced): it keeps its own gap.
                        $gap = $cGap($predecessor, $key);
                    } elseif (!isset($order[$previous['k']]) && $lastOriginal === $predecessor) {
                        // A new part was inserted after its original predecessor.
                        $gap = $cGap($predecessor, $key);
                    }
                }
                $gap ??= ($previous['gr'] || $part['gl']) ? '' : ' ';
                $out = $this->concat($out, $gap);
            }
            $out = $this->concat($out, $part['t']);
            if (isset($order[$part['k']])) {
                $lastOriginal = $part['k'];
            }
            $previous = $part;
        }
        return $out;
    }

    /**
     * `$left . $right`, with a space when the two would otherwise run together into
     * different tokens (`AS` + `x`, `-` + `-5`, `/` + `*`).
     */
    private function concat(string $left, string $right, string $separator = ''): string
    {
        if ($left === '') {
            return $right;
        }
        if ($right === '') {
            return $left;
        }
        $right = $separator . $right;
        if ($this->endsWithLineComment($left) && preg_match('/^\r?\n/', $right) !== 1) {
            // A line comment moved in front of more text must still end its line.
            return $left . "\n" . ltrim($right, " \t");
        }
        $a = $left[strlen($left) - 1];
        $b = $right[0];
        if (self::runsTogether($a, $b)) {
            return $left . ' ' . $right;
        }
        return $left . $right;
    }

    private function endsWithLineComment(string $text): bool
    {
        $newline = strrpos($text, "\n");
        $lastLine = $newline === false ? $text : substr($text, $newline + 1);
        if (!str_contains($lastLine, '--') && !str_contains($lastLine, '#')) {
            return false;
        }
        $tokens = $this->gapTokens($lastLine);
        if ($tokens === null || $tokens === []) {
            return false;
        }
        return $tokens[count($tokens) - 1]->type === TokenType::COMMENT_LINE;
    }

    private static function runsTogether(string $a, string $b): bool
    {
        $word = static fn (string $c): bool => ctype_alnum($c) || str_contains('_@$-', $c) || ord($c) >= 0x80;
        if ($word($a) && $word($b)) {
            return true;
        }
        if (($a === '/' && $b === '*') || ($a === '*' && $b === '-')) {
            return true;
        }
        return str_contains('=<>!', $a) && str_contains('=<>', $b);
    }

    /**
     * Own tokens of a node: tokens inside its span outside its children's spans.
     *
     * @param list<Span> $children
     * @return list<Token>
     */
    private function ownTokens(Span $span, array $children): array
    {
        if ($this->source === null) {
            return [];
        }
        $own = [];
        foreach ($this->source->tokensIn($span->start, $span->end) as $token) {
            $offset = $token->position->offset;
            foreach ($children as $child) {
                if ($offset >= $child->start && $offset < $child->end) {
                    continue 2;
                }
            }
            $own[] = $token;
        }
        return $own;
    }

    /**
     * @param array<string, Span>|null $cParts
     */
    private function keep(?array $cParts, string $key, string $fresh): string
    {
        if ($cParts !== null && $this->source !== null && isset($cParts[$key])) {
            return $this->source->sliceSpan($cParts[$key]);
        }
        return $fresh;
    }

    private function kw(string $keyword): string
    {
        return $this->case->apply($keyword);
    }

    private function isOriginal(AstNode $node): bool
    {
        return $this->source !== null && $this->source->isOriginal($node);
    }

    private function isParenthesized(ConditionGroupNode $group): bool
    {
        $span = $group->span;
        if ($span === null) {
            return false;
        }
        if ($group->entries === []) {
            return $span->length() > 0;
        }
        $first = self::spanOf($group->entries[0]['condition']);
        return $first !== null && $first->start > $span->start;
    }

    private function slice(Span $span): string
    {
        return $this->source !== null ? $this->source->sliceSpan($span) : '';
    }

    private static function spanOf(AstNode $node): ?Span
    {
        return $node instanceof HasSpan ? $node->span() : null;
    }

    /**
     * @param array<AstNode> $nodes
     * @return list<Span>
     */
    private static function spansOf(array $nodes): array
    {
        $spans = [];
        foreach ($nodes as $node) {
            $span = self::spanOf($node);
            if ($span !== null) {
                $spans[] = $span;
            }
        }
        return $spans;
    }

    private static function tokenSpan(Token $token): Span
    {
        return Span::fromTokens($token, $token);
    }

    private static function tokenEnd(Token $token): int
    {
        return $token->position->offset + $token->length;
    }

    /**
     * @param list<AstNode> $a
     * @param list<AstNode> $b
     */
    private static function sameList(array $a, array $b): bool
    {
        if (count($a) !== count($b)) {
            return false;
        }
        foreach (array_values($a) as $i => $node) {
            if ($node !== array_values($b)[$i]) {
                return false;
            }
        }
        return true;
    }
}
