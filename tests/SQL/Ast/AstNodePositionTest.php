<?php

namespace SQL\Ast;

use FQL\Enum;
use FQL\Query\FileQuery;
use FQL\Sql\Ast\Expression\CaseExpressionNode;
use FQL\Sql\Ast\Expression\CastExpressionNode;
use FQL\Sql\Ast\Expression\ColumnReferenceNode;
use FQL\Sql\Ast\Expression\ConditionExpressionNode;
use FQL\Sql\Ast\Expression\ConditionGroupNode;
use FQL\Sql\Ast\Expression\FileQueryNode;
use FQL\Sql\Ast\Expression\FunctionCallNode;
use FQL\Sql\Ast\Expression\LiteralNode;
use FQL\Sql\Ast\Expression\MatchAgainstNode;
use FQL\Sql\Ast\Expression\StarNode;
use FQL\Sql\Ast\Expression\SubQueryNode;
use FQL\Sql\Ast\Expression\WhenBranchNode;
use FQL\Sql\Ast\ExplainMode;
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
use FQL\Sql\Ast\Node\UnionClauseNode;
use FQL\Sql\Ast\Node\WhereClauseNode;
use FQL\Sql\Token\Position;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the `position()` accessor on every AST node so tooling consumers can rely
 * on it for diagnostics. Doubles as a structural smoke test verifying every node can
 * be constructed in isolation without cascade dependencies blowing up.
 */
class AstNodePositionTest extends TestCase
{
    private Position $pos;

    protected function setUp(): void
    {
        $this->pos = new Position(5, 2, 3);
    }

    public function testExpressionNodesExposePosition(): void
    {
        $column = new ColumnReferenceNode('x', $this->pos);
        $literal = new LiteralNode(1, Enum\Type::INTEGER, '1', $this->pos);
        $star = new StarNode($this->pos);
        $function = new FunctionCallNode('COUNT', [$column], false, $this->pos);
        $cast = new CastExpressionNode($column, Enum\Type::INTEGER, $this->pos);
        $match = new MatchAgainstNode([$column], 'q', Enum\Fulltext::NATURAL, $this->pos);

        $condition = new ConditionExpressionNode($column, Enum\Operator::EQUAL, $literal, $this->pos);
        $conditionGroup = new ConditionGroupNode([
            ['logical' => Enum\LogicalOperator::AND, 'condition' => $condition],
        ], $this->pos);
        $when = new WhenBranchNode($conditionGroup, $literal, $this->pos);
        $case = new CaseExpressionNode([$when], null, $this->pos);

        foreach ([$column, $literal, $star, $function, $cast, $match, $condition, $conditionGroup, $when, $case] as $node) {
            $this->assertSame($this->pos, $node->position());
        }
    }

    public function testFileQueryAndSubQueryExposePosition(): void
    {
        $fileQuery = new FileQuery('json(sample.json).data');
        $fileNode = new FileQueryNode($fileQuery, 'json(sample.json).data', $this->pos);
        $this->assertSame($this->pos, $fileNode->position());

        $selectStatement = new SelectStatementNode(
            from: new FromClauseNode($fileNode, null, $this->pos),
            fields: [],
            distinct: false,
            joins: [],
            where: null,
            groupBy: null,
            having: null,
            orderBy: null,
            limit: null,
            unions: [],
            into: null,
            describe: false,
            explain: ExplainMode::NONE,
            position: $this->pos
        );
        $subQuery = new SubQueryNode($selectStatement, $this->pos);
        $this->assertSame($this->pos, $subQuery->position());
        $this->assertSame($this->pos, $selectStatement->position());
    }

    public function testClauseNodesExposePosition(): void
    {
        $fileQuery = new FileQuery('csv(out.csv)');
        $fileNode = new FileQueryNode($fileQuery, 'csv(out.csv)', $this->pos);
        $column = new ColumnReferenceNode('x', $this->pos);
        $literal = new LiteralNode(1, Enum\Type::INTEGER, '1', $this->pos);
        $condition = new ConditionExpressionNode($column, Enum\Operator::EQUAL, $literal, $this->pos);
        $conditionGroup = new ConditionGroupNode([
            ['logical' => Enum\LogicalOperator::AND, 'condition' => $condition],
        ], $this->pos);

        $from = new FromClauseNode($fileNode, 'alias', $this->pos);
        $join = new JoinClauseNode(JoinType::INNER, $fileNode, 'alias2', $condition, $this->pos);
        $where = new WhereClauseNode($conditionGroup, $this->pos);
        $having = new HavingClauseNode($conditionGroup, $this->pos);
        $groupBy = new GroupByClauseNode([$column], $this->pos);
        $orderByItem = new OrderByItemNode($column, Enum\Sort::ASC, $this->pos);
        $orderBy = new OrderByClauseNode([$orderByItem], $this->pos);
        $limit = new LimitClauseNode(10, 5, $this->pos);
        $into = new IntoClauseNode($fileNode, $this->pos);
        $selectField = new SelectFieldNode($column, 'c', false, $this->pos);

        foreach ([$from, $join, $where, $having, $groupBy, $orderByItem, $orderBy, $limit, $into, $selectField] as $node) {
            $this->assertSame($this->pos, $node->position());
        }
    }

    public function testUnionClauseNodeExposesPosition(): void
    {
        $fileQuery = new FileQuery('json(x.json)');
        $fileNode = new FileQueryNode($fileQuery, 'json(x.json)', $this->pos);
        $selectStatement = new SelectStatementNode(
            from: new FromClauseNode($fileNode, null, $this->pos),
            fields: [],
            distinct: false,
            joins: [],
            where: null,
            groupBy: null,
            having: null,
            orderBy: null,
            limit: null,
            unions: [],
            into: null,
            describe: false,
            explain: ExplainMode::NONE,
            position: $this->pos
        );
        $union = new UnionClauseNode($selectStatement, true, $this->pos);
        $this->assertSame($this->pos, $union->position());
        $this->assertTrue($union->all);
    }

    public function testJoinTypeAndExplainModeEnumValues(): void
    {
        $this->assertSame('INNER', JoinType::INNER->value);
        $this->assertSame('LEFT', JoinType::LEFT->value);
        $this->assertSame('RIGHT', JoinType::RIGHT->value);
        $this->assertSame('FULL', JoinType::FULL->value);

        $this->assertSame('NONE', ExplainMode::NONE->value);
        $this->assertSame('EXPLAIN', ExplainMode::EXPLAIN->value);
        $this->assertSame('EXPLAIN_ANALYZE', ExplainMode::EXPLAIN_ANALYZE->value);
    }
}
