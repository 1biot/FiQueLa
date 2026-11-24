<?php

namespace SQL\Printer;

use FQL\Enum\LogicalOperator;
use FQL\Enum\Operator;
use FQL\Enum\Sort;
use FQL\Enum\Type;
use FQL\Sql\Ast\ExplainMode;
use FQL\Sql\Ast\Expression\BinaryOperator;
use FQL\Sql\Ast\Expression\BinaryOpNode;
use FQL\Sql\Ast\Expression\ColumnReferenceNode;
use FQL\Sql\Ast\Expression\ConditionExpressionNode;
use FQL\Sql\Ast\Expression\ConditionGroupNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\Expression\FileQueryNode;
use FQL\Sql\Ast\Expression\FunctionCallNode;
use FQL\Sql\Ast\Expression\LiteralNode;
use FQL\Sql\Ast\Expression\StarNode;
use FQL\Sql\Ast\Expression\SubQueryNode;
use FQL\Sql\Ast\JoinType;
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
use FQL\Sql\Ast\Node\WhereClauseNode;
use FQL\Query\FileQuery;
use FQL\Sql\Compiler;
use FQL\Sql\Printer\SourcePrinter;
use FQL\Sql\Printer\UnprintableNodeException;
use FQL\Sql\Token\Position;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AstShape.php';

/**
 * Edits of a parsed query printed with {@see SourcePrinter}: only the changed part is
 * rewritten, and the output parses back to the edited tree.
 */
class SourcePrinterEditTest extends TestCase
{
    private const PRODUCTS = <<<'FQL'
        select name, price -- the price
        from csv(./products.csv, delimiter: ";")
        where price > 100 and brand = "Škoda"
        order by name
        limit 10
        FQL;

    private const MULTILINE = <<<'FQL'
        SELECT
            id,
            name AS `Název`,
            price
        FROM xml(./feed.xml).SHOP.SHOPITEM
        WHERE
            price > 100
            AND name LIKE "%kolo%" -- bikes only
        ORDER BY price DESC
        FQL;

    // ---------------------------------------------------------------- sorting

    public function testSortReplacesOnlyTheOrderByItems(): void
    {
        $this->assertEdit(
            self::PRODUCTS,
            fn (SelectStatementNode $a) => $a->withOrderBy($a->orderBy?->withItems([self::order('price', Sort::DESC)])),
            str_replace('order by name', 'order by price desc', self::PRODUCTS)
        );
    }

    public function testSortDirectionToggleKeepsTheExpression(): void
    {
        $this->assertEdit(
            self::PRODUCTS,
            function (SelectStatementNode $a) {
                $item = $a->orderBy?->items[0];
                $this->assertInstanceOf(OrderByItemNode::class, $item);
                return $a->withOrderBy($a->orderBy?->withItems([$item->withDirection(Sort::DESC)]));
            },
            str_replace('order by name', 'order by name desc', self::PRODUCTS)
        );
    }

    public function testExplicitAscStaysExplicit(): void
    {
        $this->assertEdit(
            'SELECT a FROM json(a.json) ORDER BY a DESC',
            function (SelectStatementNode $a) {
                $item = $a->orderBy?->items[0];
                $this->assertInstanceOf(OrderByItemNode::class, $item);
                return $a->withOrderBy($a->orderBy?->withItems([$item->withDirection(Sort::ASC)]));
            },
            'SELECT a FROM json(a.json) ORDER BY a ASC'
        );
        $this->assertEdit(
            'SELECT a FROM json(a.json) ORDER BY a',
            fn (SelectStatementNode $a) => $a->withOrderBy($a->orderBy?->withItems([self::order('b', Sort::ASC)])),
            'SELECT a FROM json(a.json) ORDER BY b'
        );
    }

    public function testSecondSortKeyIsAppended(): void
    {
        $this->assertEdit(
            self::PRODUCTS,
            fn (SelectStatementNode $a) => $a->withOrderBy(
                $a->orderBy?->withItems([...$a->orderBy->items, self::order('price', Sort::DESC)])
            ),
            str_replace('order by name', 'order by name, price desc', self::PRODUCTS)
        );
    }

    public function testRemovingOrderByTakesItsLineAlong(): void
    {
        $this->assertEdit(
            self::PRODUCTS,
            fn (SelectStatementNode $a) => $a->withOrderBy(null),
            str_replace("order by name\n", '', self::PRODUCTS)
        );
    }

    public function testNewClauseGoesOnItsOwnLineInAMultiLineQuery(): void
    {
        $this->assertEdit(
            "SELECT id\nFROM json(a.json)\nLIMIT 5",
            fn (SelectStatementNode $a) => $a->withOrderBy(new OrderByClauseNode([self::order('id', Sort::DESC)], self::pos())),
            "SELECT id\nFROM json(a.json)\nORDER BY id DESC\nLIMIT 5"
        );
    }

    public function testNewClauseIsSpaceSeparatedInASingleLineQuery(): void
    {
        $this->assertEdit(
            'select id from json(a.json) limit 5',
            fn (SelectStatementNode $a) => $a->withOrderBy(new OrderByClauseNode([self::order('id', Sort::DESC)], self::pos())),
            'select id from json(a.json) order by id desc limit 5'
        );
    }

    public function testNewClauseInIndentedSubqueryFollowsItsIndentation(): void
    {
        $sql = "SELECT *\nFROM json(b.json) AS b\nJOIN (\n    SELECT id\n    FROM json(a.json)\n) AS t ON b.id = t.id";
        $this->assertEdit(
            $sql,
            function (SelectStatementNode $a) {
                $join = $a->joins[0];
                $sub = $join->source;
                $this->assertInstanceOf(SubQueryNode::class, $sub);
                $inner = $sub->query->withWhere(self::where(self::cond('id', Operator::GREATER_THAN, self::int(1))));
                return $a->withJoins([$join->withSource(new SubQueryNode($inner, $sub->position))]);
            },
            "SELECT *\nFROM json(b.json) AS b\nJOIN (\n    SELECT id\n    FROM json(a.json)\n    WHERE id > 1\n) AS t ON b.id = t.id"
        );
    }

    // ---------------------------------------------------------------- conditions

    public function testConditionIsAppendedWithAnd(): void
    {
        $this->assertEdit(
            self::PRODUCTS,
            fn (SelectStatementNode $a) => $a->withWhere($a->where?->withConditions(
                $a->where->conditions->withAppended(LogicalOperator::AND, self::cond('stock', Operator::GREATER_THAN, self::int(0)))
            )),
            str_replace('"Škoda"', '"Škoda" and stock > 0', self::PRODUCTS)
        );
    }

    public function testAppendedConditionCopiesTheMultiLineLayout(): void
    {
        $this->assertEdit(
            self::MULTILINE,
            fn (SelectStatementNode $a) => $a->withWhere($a->where?->withConditions(
                $a->where->conditions->withAppended(LogicalOperator::OR, self::cond('stock', Operator::IS, Type::NULL))
            )),
            str_replace('-- bikes only', "-- bikes only\n    OR stock IS NULL", self::MULTILINE)
        );
    }

    public function testConditionAddedToAnOrGroupWrapsItInParentheses(): void
    {
        $this->assertEdit(
            'SELECT * FROM json(a.json).data WHERE a = 1 OR b = 2 ORDER BY a',
            fn (SelectStatementNode $a) => $a->withWhere($a->where?->withConditions(new ConditionGroupNode([
                ['logical' => LogicalOperator::AND, 'condition' => $a->where->conditions],
                ['logical' => LogicalOperator::AND, 'condition' => self::cond('c', Operator::EQUAL, self::str('x'))],
            ], self::pos()))),
            'SELECT * FROM json(a.json).data WHERE (a = 1 OR b = 2) AND c = "x" ORDER BY a'
        );
    }

    public function testUnwrappingANestedGroupDropsItsParentheses(): void
    {
        $this->assertEdit(
            'SELECT * FROM json(a.json) WHERE (a = 1 OR b = 2) AND c = 3',
            function (SelectStatementNode $a) {
                $nested = $a->where?->conditions->entries[0]['condition'];
                $this->assertInstanceOf(ConditionGroupNode::class, $nested);
                return $a->withWhere($a->where?->withConditions($nested));
            },
            'SELECT * FROM json(a.json) WHERE a = 1 OR b = 2'
        );
    }

    public function testRemovingConditionsTakesTheirConnectives(): void
    {
        $sql = 'SELECT * FROM json(a.json) WHERE a = 1 AND /* keep b */ b = 2 OR c = 3';
        $this->assertEdit(
            $sql,
            fn (SelectStatementNode $a) => $a->withWhere($a->where?->withConditions($a->where->conditions->withoutEntry(0))),
            'SELECT * FROM json(a.json) WHERE b = 2 OR c = 3'
        );
        $this->assertEdit(
            $sql,
            fn (SelectStatementNode $a) => $a->withWhere($a->where?->withConditions($a->where->conditions->withoutEntry(1))),
            'SELECT * FROM json(a.json) WHERE a = 1 OR c = 3'
        );
        $this->assertEdit(
            $sql,
            fn (SelectStatementNode $a) => $a->withWhere($a->where?->withConditions($a->where->conditions->withoutEntry(2))),
            'SELECT * FROM json(a.json) WHERE a = 1 AND /* keep b */ b = 2'
        );
    }

    public function testRemovingTheLastConditionRemovesTheClause(): void
    {
        $this->assertEdit(
            "SELECT *\nFROM json(a.json)\n-- filter\nWHERE a = 1\nLIMIT 5",
            fn (SelectStatementNode $a) => $a->withWhere(null),
            "SELECT *\nFROM json(a.json)\nLIMIT 5"
        );
    }

    public function testChangedConnectiveKeepsTheCommentsAroundIt(): void
    {
        $this->assertEdit(
            'SELECT * FROM json(a.json) WHERE a = 1 /* x */ OR /* y */ b = 2',
            function (SelectStatementNode $a) {
                $entries = $a->where?->conditions->entries ?? [];
                $entries[1]['logical'] = LogicalOperator::AND;
                return $a->withWhere($a->where?->withConditions($a->where->conditions->withEntries($entries)));
            },
            'SELECT * FROM json(a.json) WHERE a = 1 /* x */ AND /* y */ b = 2'
        );
    }

    public function testChangedValueKeepsTheOriginalSpacing(): void
    {
        $this->assertEdit(
            'SELECT * FROM json(a.json) WHERE price>100 AND name="x"',
            function (SelectStatementNode $a) {
                $entries = $a->where?->conditions->entries ?? [];
                $condition = $entries[0]['condition'];
                $this->assertInstanceOf(ConditionExpressionNode::class, $condition);
                $entries[0]['condition'] = $condition->withRight(self::int(200));
                return $a->withWhere($a->where?->withConditions($a->where->conditions->withEntries($entries)));
            },
            'SELECT * FROM json(a.json) WHERE price>200 AND name="x"'
        );
    }

    public function testInListValueRemoval(): void
    {
        $this->assertEdit(
            'SELECT * FROM json(a.json) WHERE a IN (1,  2,  3)',
            function (SelectStatementNode $a) {
                $condition = $a->where?->conditions->entries[0]['condition'];
                $this->assertInstanceOf(ConditionExpressionNode::class, $condition);
                $this->assertIsArray($condition->right);
                $entries = $a->where->conditions->entries;
                $entries[0]['condition'] = $condition->withRight([$condition->right[0], $condition->right[2]]);
                return $a->withWhere($a->where->withConditions($a->where->conditions->withEntries($entries)));
            },
            'SELECT * FROM json(a.json) WHERE a IN (1,  3)'
        );
    }

    public function testNewWhereClauseIsInsertedAfterFrom(): void
    {
        $this->assertEdit(
            "select a\nfrom json(a.json)\norder by a",
            fn (SelectStatementNode $a) => $a->withWhere(self::where(self::cond('flag', Operator::EQUAL, new LiteralNode(true, Type::BOOLEAN, '', self::pos())))),
            "select a\nfrom json(a.json)\nwhere flag = true\norder by a"
        );
    }

    public function testHavingConditionIsAppended(): void
    {
        $this->assertEdit(
            'SELECT brand, COUNT(*) AS cnt FROM json(a.json) GROUP BY brand HAVING cnt > 1',
            fn (SelectStatementNode $a) => $a->withHaving(new HavingClauseNode(
                ($a->having?->conditions ?? new ConditionGroupNode([], self::pos()))
                    ->withAppended(LogicalOperator::AND, self::cond('cnt', Operator::LESS_THAN, self::int(100))),
                self::pos()
            )),
            'SELECT brand, COUNT(*) AS cnt FROM json(a.json) GROUP BY brand HAVING cnt > 1 AND cnt < 100'
        );
    }

    // ---------------------------------------------------------------- source

    public function testPathInFileIsReplacedInsideTheSourceOnly(): void
    {
        $this->assertEdit(
            self::MULTILINE,
            fn (SelectStatementNode $a) => $this->withSource($a, fn (FileQuery $fq) => $fq->withQuery('SHOP.ITEM')),
            str_replace('.SHOP.SHOPITEM', '.SHOP.ITEM', self::MULTILINE)
        );
    }

    public function testSourceParamsKeepTheNamedStyleAndEveryParameter(): void
    {
        $this->assertEdit(
            self::PRODUCTS,
            fn (SelectStatementNode $a) => $this->withSource($a, fn (FileQuery $fq) => $fq->withParam('enclosure', "'")),
            str_replace('delimiter: ";"', 'delimiter: ";", enclosure: "\'"', self::PRODUCTS)
        );
        $this->assertEdit(
            'SELECT * FROM csv(a.csv, "windows-1250", ";")',
            fn (SelectStatementNode $a) => $this->withSource($a, fn (FileQuery $fq) => $fq->withParam('bom', '1')),
            'SELECT * FROM csv(a.csv, encoding: "windows-1250", delimiter: ";", bom: "1")'
        );
        $this->assertEdit(
            'SELECT * FROM log(./access.log)',
            fn (SelectStatementNode $a) => $this->withSource($a, fn (FileQuery $fq) => $fq->withParams(['pattern' => '%h %m %U'])),
            'SELECT * FROM log(./access.log, pattern: "%h %m %U")'
        );
    }

    public function testQuotedSourcePathIsKeptAndItsParamsKeepTheirStyle(): void
    {
        $this->assertEdit(
            'SELECT * FROM csv("./my data (1).csv", delimiter: ";").items WHERE id > 1',
            fn (SelectStatementNode $a) => $this->withSource($a, fn (FileQuery $fq) => $fq->withParam('encoding', 'windows-1250')),
            'SELECT * FROM csv("./my data (1).csv", encoding: "windows-1250", delimiter: ";").items WHERE id > 1'
        );
        $this->assertEdit(
            'SELECT * FROM csv("./my data (1).csv", "utf-8", ";")',
            fn (SelectStatementNode $a) => $this->withSource($a, fn (FileQuery $fq) => $fq->withParam('encoding', 'windows-1250')),
            'SELECT * FROM csv("./my data (1).csv", "windows-1250", ";")'
        );
        // a `, key: "` inside the quoted path does not make the copy named
        $this->assertEdit(
            'SELECT * FROM csv("./a, b: ", "utf-8", ";")',
            fn (SelectStatementNode $a) => $this->withSource($a, fn (FileQuery $fq) => $fq->withParam('encoding', 'windows-1250')),
            'SELECT * FROM csv("./a, b: ", "windows-1250", ";")'
        );
        $this->assertEdit(
            "SELECT * FROM csv('./a, b: \"x', \"utf-8\", \";\")",
            fn (SelectStatementNode $a) => $this->withSource($a, fn (FileQuery $fq) => $fq->withParam('encoding', 'windows-1250')),
            "SELECT * FROM csv('./a, b: \"x', \"windows-1250\", \";\")"
        );
        // an untouched single-quoted source keeps its quotes
        $this->assertEdit(
            "SELECT * FROM csv('./my data (1).csv') WHERE id > 1",
            fn (SelectStatementNode $a) => $a->withWhere(null),
            "SELECT * FROM csv('./my data (1).csv')"
        );
        $this->assertEdit(
            'SELECT * FROM csv(./a.csv)',
            fn (SelectStatementNode $a) => $this->withSource($a, fn (FileQuery $fq) => $fq->withFile('./reports (Q1)/a.csv')),
            'SELECT * FROM csv("./reports (Q1)/a.csv")'
        );
    }

    public function testSourceWithAnExplicitRawIsWrittenAsGiven(): void
    {
        $this->assertEdit(
            'SELECT * FROM csv(a.csv) AS t',
            function (SelectStatementNode $a) {
                $from = $a->from;
                $this->assertInstanceOf(FromClauseNode::class, $from);
                $this->assertInstanceOf(FileQueryNode::class, $from->source);
                $fq = $from->source->fileQuery->withParam('delimiter', ';');
                return $a->withFrom($from->withSource($from->source->withFileQuery($fq, $fq->toFql(true))));
            },
            'SELECT * FROM csv(a.csv, delimiter: ";") AS t'
        );
    }

    public function testJoinSourceIsEdited(): void
    {
        $this->assertEdit(
            "SELECT *\nFROM json(a.json) AS a\nLEFT OUTER JOIN csv(b.csv) AS b ON a.id = b.id",
            function (SelectStatementNode $a) {
                $join = $a->joins[0];
                $this->assertInstanceOf(FileQueryNode::class, $join->source);
                return $a->withJoins([$join->withSource($join->source->withFileQuery($join->source->fileQuery->withParam('delimiter', '|')))]);
            },
            "SELECT *\nFROM json(a.json) AS a\nLEFT OUTER JOIN csv(b.csv, \"utf-8\", \"|\") AS b ON a.id = b.id"
        );
    }

    // ---------------------------------------------------------------- columns

    public function testHiddenFieldTakesItsCommaAlong(): void
    {
        $this->assertEdit(
            self::MULTILINE,
            fn (SelectStatementNode $a) => $a->withFields([$a->fields[0], $a->fields[2]]),
            str_replace("    name AS `Název`,\n", '', self::MULTILINE)
        );
        $this->assertEdit(
            self::PRODUCTS,
            fn (SelectStatementNode $a) => $a->withFields([$a->fields[1]]),
            str_replace('select name, price', 'select price', self::PRODUCTS)
        );
    }

    public function testFieldIsAppendedInTheListLayout(): void
    {
        $this->assertEdit(
            self::MULTILINE,
            fn (SelectStatementNode $a) => $a->withFields([...$a->fields, self::field('skladem ks')]),
            str_replace("    price\n", "    price,\n    `skladem ks`\n", self::MULTILINE)
        );
    }

    public function testColumnFromStarIsHiddenWithExclude(): void
    {
        $this->assertEdit(
            'SELECT * FROM json(a.json)',
            fn (SelectStatementNode $a) => $a->withFields([...$a->fields, self::field('secret', true)]),
            'SELECT * EXCLUDE secret FROM json(a.json)'
        );
        $this->assertEdit(
            'SELECT * EXCLUDE a FROM json(a.json)',
            fn (SelectStatementNode $a) => $a->withFields([...$a->fields, self::field('Tajné', true)]),
            'SELECT * EXCLUDE a, `Tajné` FROM json(a.json)'
        );
        $this->assertEdit(
            "SELECT\n    a,\n    b\nFROM json(a.json)",
            fn (SelectStatementNode $a) => $a->withFields([$a->fields[0], $a->fields[1]->withExcluded(true)]),
            "SELECT\n    a\n    EXCLUDE b\nFROM json(a.json)"
        );
    }

    public function testRemovingTheExcludedFieldDropsTheExcludeKeyword(): void
    {
        $this->assertEdit(
            'SELECT *, totalPrice EXCLUDE secret FROM json(a.json)',
            fn (SelectStatementNode $a) => $a->withFields([$a->fields[0], $a->fields[1]]),
            'SELECT *, totalPrice FROM json(a.json)'
        );
        $this->assertEdit(
            'SELECT EXCLUDE a, b FROM json(a.json)',
            fn (SelectStatementNode $a) => $a->withFields([$a->fields[1]->withExcluded(false)]),
            'SELECT b FROM json(a.json)'
        );
    }

    public function testAliasIsAddedRenamedAndRemoved(): void
    {
        $this->assertEdit(
            'SELECT price, name AS n FROM json(a.json)',
            fn (SelectStatementNode $a) => $a->withFields([$a->fields[0]->withAlias('cena s DPH'), $a->fields[1]->withAlias('jméno')]),
            'SELECT price AS `cena s DPH`, name AS `jméno` FROM json(a.json)'
        );
        $this->assertEdit(
            'SELECT name   as   n FROM json(a.json)',
            fn (SelectStatementNode $a) => $a->withFields([$a->fields[0]->withAlias(null)]),
            'SELECT name FROM json(a.json)'
        );
    }

    public function testReorderedFieldsKeepTheirText(): void
    {
        $this->assertEdit(
            'SELECT `a b` /* first */, LOWER(c) AS d FROM json(a.json)',
            fn (SelectStatementNode $a) => $a->withFields([$a->fields[1], $a->fields[0]]),
            'SELECT LOWER(c) AS d, `a b` /* first */ FROM json(a.json)'
        );
    }

    public function testDistinctIsAddedAndRemoved(): void
    {
        $this->assertEdit(
            'select  brand from json(a.json)',
            fn (SelectStatementNode $a) => $a->withDistinct(true),
            'select distinct  brand from json(a.json)'
        );
        $this->assertEdit(
            'SELECT DISTINCT brand FROM json(a.json)',
            fn (SelectStatementNode $a) => $a->withDistinct(false),
            'SELECT brand FROM json(a.json)'
        );
    }

    // ---------------------------------------------------------------- grouping

    public function testGroupAndCount(): void
    {
        $this->assertEdit(
            "SELECT *\nFROM json(./products.json).data\nWHERE price > 0 -- only priced\nORDER BY name\nLIMIT 100",
            fn (SelectStatementNode $a) => $a
                ->withFields([
                    self::field('brand'),
                    new SelectFieldNode(new FunctionCallNode('COUNT', [new StarNode(self::pos())], false, self::pos()), 'count', false, self::pos()),
                ])
                ->withGroupBy(new GroupByClauseNode([self::col('brand')], self::pos()))
                ->withOrderBy(new OrderByClauseNode([self::order('count', Sort::DESC)], self::pos())),
            "SELECT brand, COUNT(*) AS count\nFROM json(./products.json).data\nWHERE price > 0 -- only priced\nGROUP BY brand\nORDER BY count DESC\nLIMIT 100"
        );
    }

    // ---------------------------------------------------------------- nested statements and clauses

    public function testCteBodyIsEditedInPlace(): void
    {
        $sql = "WITH active AS (\n  SELECT * FROM json(u.json) -- users\n)\nSELECT name FROM active";
        $this->assertEdit(
            $sql,
            function (SelectStatementNode $a) {
                $cte = $a->commonTables[0];
                $body = $cte->query->withWhere(self::where(self::cond('active', Operator::EQUAL, new LiteralNode(true, Type::BOOLEAN, '', self::pos()))));
                return $a->withCommonTables([new \FQL\Sql\Ast\Node\CommonTableExpressionNode($cte->name, $body, $cte->position)]);
            },
            "WITH active AS (\n  SELECT * FROM json(u.json) WHERE active = TRUE -- users\n)\nSELECT name FROM active"
        );
    }

    public function testUnionRightHandSideIsEdited(): void
    {
        $sql = "SELECT name FROM json(a.json)\nUNION ALL\nSELECT name FROM json(b.json)";
        $this->assertEdit(
            $sql,
            function (SelectStatementNode $a) {
                $union = $a->unions[0];
                $rhs = $union->query->withOrderBy(new OrderByClauseNode([self::order('name', Sort::ASC)], self::pos()));
                return $a->withUnions([new \FQL\Sql\Ast\Node\UnionClauseNode($rhs, $union->all, $union->position)]);
            },
            "SELECT name FROM json(a.json)\nUNION ALL\nSELECT name FROM json(b.json) ORDER BY name"
        );
    }

    public function testJoinIsAddedAndRemoved(): void
    {
        $sql = "SELECT *\nFROM json(a.json) AS a\nJOIN json(b.json) AS b ON a.id = b.id\nWHERE a.x = 1";
        $join = new JoinClauseNode(
            JoinType::LEFT,
            new FileQueryNode(new FileQuery('csv(c.csv)'), '', self::pos()),
            'c',
            new ConditionExpressionNode(self::col('c.id'), Operator::EQUAL, self::col('a.id'), self::pos()),
            self::pos()
        );
        $this->assertEdit(
            $sql,
            fn (SelectStatementNode $a) => $a->withJoins([...$a->joins, $join]),
            "SELECT *\nFROM json(a.json) AS a\nJOIN json(b.json) AS b ON a.id = b.id\nLEFT JOIN csv(c.csv) AS c ON c.id = a.id\nWHERE a.x = 1"
        );
        $this->assertEdit(
            $sql,
            fn (SelectStatementNode $a) => $a->withJoins([]),
            "SELECT *\nFROM json(a.json) AS a\nWHERE a.x = 1"
        );
    }

    public function testLimitIsChangedAndRemoved(): void
    {
        $this->assertEdit(
            'SELECT a FROM json(a.json) LIMIT 10 OFFSET 5',
            fn (SelectStatementNode $a) => $a->withLimit($a->limit?->withLimit(20)),
            'SELECT a FROM json(a.json) LIMIT 20 OFFSET 5'
        );
        $this->assertEdit(
            'SELECT a FROM json(a.json) LIMIT 10 ORDER BY a OFFSET 5',
            fn (SelectStatementNode $a) => $a->withLimit(null),
            'SELECT a FROM json(a.json) ORDER BY a'
        );
        $this->assertEdit(
            'SELECT a FROM json(a.json) LIMIT 10',
            fn (SelectStatementNode $a) => $a->withLimit($a->limit?->withOffset(30)),
            'SELECT a FROM json(a.json) LIMIT 10 OFFSET 30'
        );
        $this->assertEdit(
            'SELECT a FROM json(a.json)',
            fn (SelectStatementNode $a) => $a->withLimit(new LimitClauseNode(0, 15, self::pos())),
            'SELECT a FROM json(a.json) OFFSET 15'
        );
    }

    public function testRemovingARepeatedClauseRemovesEveryCopy(): void
    {
        $this->assertEdit(
            'SELECT a FROM json(a.json) WHERE a = 1 WHERE b = 2',
            fn (SelectStatementNode $a) => $a->withWhere(null),
            'SELECT a FROM json(a.json)'
        );
        $this->assertEdit(
            'SELECT a FROM json(a.json) WHERE a = 1 WHERE b = 2',
            fn (SelectStatementNode $a) => $a->withWhere(self::where(self::cond('c', Operator::EQUAL, self::int(3)))),
            'SELECT a FROM json(a.json) WHERE a = 1 WHERE c = 3'
        );
    }

    public function testIntoAndExplainAreAddedAndRemoved(): void
    {
        $this->assertEdit(
            'SELECT a FROM json(a.json) LIMIT 1',
            fn (SelectStatementNode $a) => $a->withInto(new IntoClauseNode(new FileQueryNode(new FileQuery('csv(./out.csv)'), '', self::pos()), self::pos())),
            'SELECT a FROM json(a.json) LIMIT 1 INTO csv(./out.csv)'
        );
        $this->assertEdit(
            "-- report\nSELECT a FROM json(a.json)",
            fn (SelectStatementNode $a) => $a->withExplain(ExplainMode::EXPLAIN_ANALYZE),
            "-- report\nEXPLAIN ANALYZE SELECT a FROM json(a.json)"
        );
        $this->assertEdit(
            "EXPLAIN\nSELECT a FROM json(a.json)",
            fn (SelectStatementNode $a) => $a->withExplain(ExplainMode::NONE),
            'SELECT a FROM json(a.json)'
        );
    }

    public function testFunctionArgumentIsEdited(): void
    {
        $this->assertEdit(
            'SELECT round( price ,2 ) AS p FROM json(a.json)',
            function (SelectStatementNode $a) {
                $call = $a->fields[0]->expression;
                $this->assertInstanceOf(FunctionCallNode::class, $call);
                return $a->withFields([$a->fields[0]->withExpression($call->withArguments([$call->arguments[0], self::int(3)]))]);
            },
            'SELECT round( price ,3 ) AS p FROM json(a.json)'
        );
    }

    public function testArithmeticIsWrittenWithOperators(): void
    {
        $this->assertEdit(
            'SELECT price FROM json(a.json)',
            fn (SelectStatementNode $a) => $a->withFields([
                $a->fields[0]->withExpression(new BinaryOpNode(self::col('price'), BinaryOperator::MULTIPLY, self::int(2), self::pos())),
            ]),
            'SELECT price * 2 FROM json(a.json)'
        );
    }

    // ---------------------------------------------------------------- comment ownership

    public function testTrailingCommentsStayWithTheirItems(): void
    {
        $sql = "SELECT\n    id, -- key\n    name, -- label\n    price -- money\nFROM json(a.json)";
        $this->assertEdit(
            $sql,
            fn (SelectStatementNode $a) => $a->withFields([$a->fields[0], $a->fields[2]]),
            "SELECT\n    id, -- key\n    price -- money\nFROM json(a.json)"
        );
        $this->assertEdit(
            $sql,
            fn (SelectStatementNode $a) => $a->withFields([$a->fields[0], $a->fields[1]]),
            "SELECT\n    id, -- key\n    name -- label\nFROM json(a.json)"
        );
        $this->assertEdit(
            $sql,
            fn (SelectStatementNode $a) => $a->withFields([...$a->fields, self::field('stock')]),
            "SELECT\n    id, -- key\n    name, -- label\n    price, -- money\n    stock\nFROM json(a.json)"
        );
        $this->assertEdit(
            $sql,
            fn (SelectStatementNode $a) => $a->withFields([$a->fields[0], self::field('stock'), $a->fields[1], $a->fields[2]]),
            "SELECT\n    id, -- key\n    stock,\n    name, -- label\n    price -- money\nFROM json(a.json)"
        );
    }

    public function testLeadingCommentsStayWithTheirItems(): void
    {
        $sql = "SELECT\n    -- key\n    id,\n    -- label\n    name,\n    -- money\n    price\nFROM json(a.json)";
        $this->assertEdit(
            $sql,
            fn (SelectStatementNode $a) => $a->withFields([$a->fields[0], $a->fields[2]]),
            "SELECT\n    -- key\n    id,\n    -- money\n    price\nFROM json(a.json)"
        );
    }

    public function testTrailingCommentOfTheLastConditionStaysOnItsLine(): void
    {
        $this->assertEdit(
            self::MULTILINE,
            fn (SelectStatementNode $a) => $a->withWhere($a->where?->withConditions($a->where->conditions->withoutEntry(1))),
            str_replace("\n    AND name LIKE \"%kolo%\" -- bikes only", '', self::MULTILINE)
        );
    }

    public function testRemovedClauseTakesItsComments(): void
    {
        $this->assertEdit(
            "SELECT a -- fields\nFROM json(a.json) -- source\nWHERE b = 1 -- filter\n",
            fn (SelectStatementNode $a) => $a->withWhere(null),
            "SELECT a -- fields\nFROM json(a.json) -- source\n"
        );
        $this->assertEdit(
            "SELECT a -- fields\nFROM json(a.json) -- source\nWHERE b = 1 -- filter\nLIMIT 5",
            fn (SelectStatementNode $a) => $a->withWhere(null),
            "SELECT a -- fields\nFROM json(a.json) -- source\nLIMIT 5"
        );
    }

    public function testMovedLineCommentStillEndsItsLine(): void
    {
        $this->assertEdit(
            "SELECT a, -- x\n b FROM json(a.json)",
            fn (SelectStatementNode $a) => $a->withFields([$a->fields[0]]),
            "SELECT a -- x\nFROM json(a.json)"
        );
        $this->assertEdit(
            "SELECT a, -- x\n b FROM json(a.json)",
            fn (SelectStatementNode $a) => $a->withFields([$a->fields[1], $a->fields[0]]),
            "SELECT b,\n a -- x\nFROM json(a.json)"
        );
    }

    // ---------------------------------------------------------------- unprintable

    public function testSelectedFieldAfterAnExcludedOneIsRejected(): void
    {
        $printer = new SourcePrinter('SELECT * EXCLUDE a FROM json(a.json)');
        $ast = $printer->original();

        $this->expectException(UnprintableNodeException::class);
        $printer->print($ast->withFields([...$ast->fields, self::field('b')]));
    }

    public function testStringWithBothQuotesIsRejected(): void
    {
        $printer = new SourcePrinter('SELECT * FROM json(a.json)');
        $ast = $printer->original();

        $this->expectException(UnprintableNodeException::class);
        $printer->print($ast->withWhere(self::where(self::cond('a', Operator::EQUAL, self::str('it\'s "x"')))));
    }

    public function testParenthesisedArithmeticIsRejected(): void
    {
        $printer = new SourcePrinter('SELECT a FROM json(a.json)');
        $ast = $printer->original();
        $sum = new BinaryOpNode(self::col('a'), BinaryOperator::ADD, self::col('b'), self::pos());

        $this->expectException(UnprintableNodeException::class);
        $printer->print($ast->withFields([self::fieldOf(new BinaryOpNode($sum, BinaryOperator::MULTIPLY, self::col('c'), self::pos()))]));
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param callable(SelectStatementNode): SelectStatementNode $edit
     */
    private function assertEdit(string $sql, callable $edit, string $expected): void
    {
        $printer = new SourcePrinter($sql);
        $edited = $edit($printer->original());

        $printed = $printer->print($edited);

        $this->assertSame($expected, $printed);
        $this->assertEquals(
            AstShape::of($edited),
            AstShape::of((new Compiler($printed))->toAst()),
            'The printed query does not parse back to the edited AST'
        );
    }

    /**
     * @param callable(FileQuery): FileQuery $change
     */
    private function withSource(SelectStatementNode $ast, callable $change): SelectStatementNode
    {
        $from = $ast->from;
        $this->assertInstanceOf(FromClauseNode::class, $from);
        $this->assertInstanceOf(FileQueryNode::class, $from->source);
        return $ast->withFrom($from->withSource($from->source->withFileQuery($change($from->source->fileQuery))));
    }

    private static function pos(): Position
    {
        return Position::synthetic();
    }

    private static function col(string $name): ColumnReferenceNode
    {
        return new ColumnReferenceNode($name, self::pos());
    }

    private static function int(int $value): LiteralNode
    {
        return new LiteralNode($value, Type::INTEGER, '', self::pos());
    }

    private static function str(string $value): LiteralNode
    {
        return new LiteralNode($value, Type::STRING, '', self::pos());
    }

    /**
     * @param ExpressionNode|Type|ExpressionNode[] $right
     */
    private static function cond(string $column, Operator $operator, ExpressionNode|Type|array $right): ConditionExpressionNode
    {
        return new ConditionExpressionNode(self::col($column), $operator, $right, self::pos());
    }

    private static function where(ConditionExpressionNode $condition): WhereClauseNode
    {
        return new WhereClauseNode(
            new ConditionGroupNode([['logical' => LogicalOperator::AND, 'condition' => $condition]], self::pos()),
            self::pos()
        );
    }

    private static function order(string $column, Sort $direction): OrderByItemNode
    {
        return new OrderByItemNode(self::col($column), $direction, self::pos());
    }

    private static function field(string $column, bool $excluded = false): SelectFieldNode
    {
        return new SelectFieldNode(self::col($column), null, $excluded, self::pos());
    }

    private static function fieldOf(ExpressionNode $expression): SelectFieldNode
    {
        return new SelectFieldNode($expression, null, false, self::pos());
    }
}
