<?php

namespace SQL\Runtime;

use FQL\Enum;
use FQL\Exception;
use FQL\Sql\Ast\Expression\BinaryOperator;
use FQL\Sql\Ast\Expression\BinaryOpNode;
use FQL\Sql\Ast\Expression\CaseExpressionNode;
use FQL\Sql\Ast\Expression\CastExpressionNode;
use FQL\Sql\Ast\Expression\ColumnReferenceNode;
use FQL\Sql\Ast\Expression\ConditionExpressionNode;
use FQL\Sql\Ast\Expression\ConditionGroupNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\Expression\FileQueryNode;
use FQL\Sql\Ast\Expression\FunctionCallNode;
use FQL\Sql\Ast\Expression\LiteralNode;
use FQL\Sql\Ast\Expression\MatchAgainstNode;
use FQL\Sql\Ast\Expression\StarNode;
use FQL\Sql\Ast\Expression\SubQueryNode;
use FQL\Sql\Ast\Expression\WhenBranchNode;
use FQL\Sql\Runtime\ExpressionEvaluator;
use FQL\Sql\Token\Position;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the runtime expression evaluator.
 *
 * Focuses on per-node evaluation, ensuring that nested compositions
 * (`UPPER(LOWER(x))`, `ROUND(5 * price, 2)`, CASE expressions) produce the
 * expected value for a given row without going through the full SQL pipeline.
 */
class ExpressionEvaluatorTest extends TestCase
{
    private ExpressionEvaluator $evaluator;
    private Position $pos;

    protected function setUp(): void
    {
        $this->evaluator = new ExpressionEvaluator();
        $this->pos = new Position(0, 1, 1);
    }

    // ─── Literal & column ────────────────────────────────────────────────

    public function testLiteralNodeReturnsValueDirectly(): void
    {
        $node = new LiteralNode(42, Enum\Type::INTEGER, '42', $this->pos);
        $this->assertSame(42, $this->evaluator->evaluate($node, []));
    }

    public function testLiteralStringAndFloatAndBoolAndNull(): void
    {
        $this->assertSame('hi', $this->evaluator->evaluate(
            new LiteralNode('hi', Enum\Type::STRING, '"hi"', $this->pos),
            []
        ));
        $this->assertSame(3.14, $this->evaluator->evaluate(
            new LiteralNode(3.14, Enum\Type::FLOAT, '3.14', $this->pos),
            []
        ));
        $this->assertTrue($this->evaluator->evaluate(
            new LiteralNode(true, Enum\Type::BOOLEAN, 'TRUE', $this->pos),
            []
        ));
        $this->assertNull($this->evaluator->evaluate(
            new LiteralNode(null, Enum\Type::NULL, 'NULL', $this->pos),
            []
        ));
    }

    public function testColumnReferenceResolvesFromItem(): void
    {
        $node = new ColumnReferenceNode('price', $this->pos);
        $this->assertSame(100, $this->evaluator->evaluate($node, ['price' => 100]));
    }

    public function testColumnReferenceHandlesNestedPath(): void
    {
        $node = new ColumnReferenceNode('user.name', $this->pos);
        $this->assertSame('Alice', $this->evaluator->evaluate(
            $node,
            ['user' => ['name' => 'Alice']]
        ));
    }

    public function testColumnReferenceFallsBackToResultItem(): void
    {
        $node = new ColumnReferenceNode('total', $this->pos);
        $this->assertSame(200, $this->evaluator->evaluate(
            $node,
            [],
            ['total' => 200]
        ));
    }

    public function testColumnReferenceReturnsNullWhenMissing(): void
    {
        $node = new ColumnReferenceNode('missing', $this->pos);
        $this->assertNull($this->evaluator->evaluate($node, []));
    }

    public function testQuotedColumnNameReturnsLiteralString(): void
    {
        $node = new ColumnReferenceNode('"hello"', $this->pos);
        $this->assertSame('hello', $this->evaluator->evaluate($node, []));
    }

    public function testStarNodeReturnsAsterisk(): void
    {
        $this->assertSame('*', $this->evaluator->evaluate(new StarNode($this->pos), []));
    }

    // ─── Function calls ──────────────────────────────────────────────────

    public function testFunctionCallLower(): void
    {
        $node = new FunctionCallNode('LOWER', [new ColumnReferenceNode('name', $this->pos)], false, $this->pos);
        $this->assertSame('alice', $this->evaluator->evaluate($node, ['name' => 'Alice']));
    }

    public function testFunctionCallRoundWithLiteralPrecision(): void
    {
        $node = new FunctionCallNode('ROUND', [
            new ColumnReferenceNode('price', $this->pos),
            new LiteralNode(2, Enum\Type::INTEGER, '2', $this->pos),
        ], false, $this->pos);
        $this->assertSame(3.14, $this->evaluator->evaluate($node, ['price' => 3.14159]));
    }

    public function testNestedFunctionCallsEvaluateInnerFirst(): void
    {
        $lower = new FunctionCallNode('LOWER', [new ColumnReferenceNode('name', $this->pos)], false, $this->pos);
        $upper = new FunctionCallNode('UPPER', [$lower], false, $this->pos);
        $this->assertSame('ALICE', $this->evaluator->evaluate($upper, ['name' => 'Alice']));
    }

    public function testFunctionCallOverBinaryOpArgument(): void
    {
        // ROUND(5 * price, 2)
        $mul = new BinaryOpNode(
            new LiteralNode(5, Enum\Type::INTEGER, '5', $this->pos),
            BinaryOperator::MULTIPLY,
            new ColumnReferenceNode('price', $this->pos),
            $this->pos
        );
        $round = new FunctionCallNode('ROUND', [
            $mul,
            new LiteralNode(2, Enum\Type::INTEGER, '2', $this->pos),
        ], false, $this->pos);
        $this->assertSame(15.71, $this->evaluator->evaluate($round, ['price' => 3.14159]));
    }

    public function testFunctionCallConcatAcceptsManyEvaluatedArgs(): void
    {
        $node = new FunctionCallNode('CONCAT', [
            new ColumnReferenceNode('first', $this->pos),
            new LiteralNode(' ', Enum\Type::STRING, '" "', $this->pos),
            new ColumnReferenceNode('last', $this->pos),
        ], false, $this->pos);
        $this->assertSame(
            'Alice Smith',
            $this->evaluator->evaluate($node, ['first' => 'Alice', 'last' => 'Smith'])
        );
    }

    public function testAggregateFunctionCallRejectsRowLevelEvaluation(): void
    {
        $node = new FunctionCallNode('SUM', [new ColumnReferenceNode('price', $this->pos)], false, $this->pos);
        $this->expectException(Exception\QueryLogicException::class);
        $this->evaluator->evaluate($node, ['price' => 10]);
    }

    public function testUnknownFunctionCallThrows(): void
    {
        $node = new FunctionCallNode('NONSENSE', [], false, $this->pos);
        $this->expectException(Exception\UnexpectedValueException::class);
        $this->evaluator->evaluate($node, []);
    }

    // ─── Binary op / arithmetic ──────────────────────────────────────────

    public function testBinaryAdd(): void
    {
        $node = new BinaryOpNode(
            new ColumnReferenceNode('a', $this->pos),
            BinaryOperator::ADD,
            new ColumnReferenceNode('b', $this->pos),
            $this->pos
        );
        $this->assertSame(15, $this->evaluator->evaluate($node, ['a' => 10, 'b' => 5]));
    }

    public function testBinarySubtractMultiplyDivide(): void
    {
        $cases = [
            [BinaryOperator::SUBTRACT, 5],
            [BinaryOperator::MULTIPLY, 50],
            [BinaryOperator::DIVIDE, 2],
        ];
        foreach ($cases as [$op, $expected]) {
            $node = new BinaryOpNode(
                new ColumnReferenceNode('a', $this->pos),
                $op,
                new ColumnReferenceNode('b', $this->pos),
                $this->pos
            );
            $this->assertEqualsWithDelta(
                $expected,
                $this->evaluator->evaluate($node, ['a' => 10, 'b' => 5]),
                0.0001,
                "Operator {$op->value}"
            );
        }
    }

    public function testDivisionByZeroReturnsNull(): void
    {
        $node = new BinaryOpNode(
            new LiteralNode(10, Enum\Type::INTEGER, '10', $this->pos),
            BinaryOperator::DIVIDE,
            new LiteralNode(0, Enum\Type::INTEGER, '0', $this->pos),
            $this->pos
        );
        $this->assertNull($this->evaluator->evaluate($node, []));
    }

    public function testBinaryOpPropagatesNull(): void
    {
        $node = new BinaryOpNode(
            new ColumnReferenceNode('missing', $this->pos),
            BinaryOperator::ADD,
            new LiteralNode(5, Enum\Type::INTEGER, '5', $this->pos),
            $this->pos
        );
        $this->assertNull($this->evaluator->evaluate($node, []));
    }

    public function testModuloOperator(): void
    {
        $node = new BinaryOpNode(
            new LiteralNode(10, Enum\Type::INTEGER, '10', $this->pos),
            BinaryOperator::MODULO,
            new LiteralNode(3, Enum\Type::INTEGER, '3', $this->pos),
            $this->pos
        );
        $this->assertSame(1, $this->evaluator->evaluate($node, []));
    }

    public function testStringOperandCoercedToNumber(): void
    {
        // `a + b` where both are numeric strings ("5" + "3") → 8
        $node = new BinaryOpNode(
            new ColumnReferenceNode('a', $this->pos),
            BinaryOperator::ADD,
            new ColumnReferenceNode('b', $this->pos),
            $this->pos
        );
        $this->assertSame(8, $this->evaluator->evaluate($node, ['a' => '5', 'b' => '3']));
    }

    // ─── Cast ────────────────────────────────────────────────────────────

    public function testCastStringToInt(): void
    {
        $node = new CastExpressionNode(
            new LiteralNode('42', Enum\Type::STRING, '"42"', $this->pos),
            Enum\Type::INTEGER,
            $this->pos
        );
        $this->assertSame(42, $this->evaluator->evaluate($node, []));
    }

    public function testCastNumericToString(): void
    {
        $node = new CastExpressionNode(
            new LiteralNode(3.14, Enum\Type::FLOAT, '3.14', $this->pos),
            Enum\Type::STRING,
            $this->pos
        );
        $this->assertSame('3.14', $this->evaluator->evaluate($node, []));
    }

    // ─── CASE ────────────────────────────────────────────────────────────

    public function testCaseWithMatchingBranch(): void
    {
        // CASE WHEN price > 100 THEN "big" ELSE "small" END
        $condition = new ConditionGroupNode([
            [
                'logical' => Enum\LogicalOperator::AND,
                'condition' => new ConditionExpressionNode(
                    new ColumnReferenceNode('price', $this->pos),
                    Enum\Operator::GREATER_THAN,
                    new LiteralNode(100, Enum\Type::INTEGER, '100', $this->pos),
                    $this->pos
                ),
            ],
        ], $this->pos);
        $case = new CaseExpressionNode(
            [new WhenBranchNode(
                $condition,
                new LiteralNode('big', Enum\Type::STRING, '"big"', $this->pos),
                $this->pos
            )],
            new LiteralNode('small', Enum\Type::STRING, '"small"', $this->pos),
            $this->pos
        );
        $this->assertSame('big', $this->evaluator->evaluate($case, ['price' => 150]));
        $this->assertSame('small', $this->evaluator->evaluate($case, ['price' => 50]));
    }

    public function testCaseWithoutElseReturnsNullWhenNoMatch(): void
    {
        $condition = new ConditionGroupNode([
            [
                'logical' => Enum\LogicalOperator::AND,
                'condition' => new ConditionExpressionNode(
                    new ColumnReferenceNode('x', $this->pos),
                    Enum\Operator::EQUAL,
                    new LiteralNode(1, Enum\Type::INTEGER, '1', $this->pos),
                    $this->pos
                ),
            ],
        ], $this->pos);
        $case = new CaseExpressionNode(
            [new WhenBranchNode(
                $condition,
                new LiteralNode('one', Enum\Type::STRING, '"one"', $this->pos),
                $this->pos
            )],
            null,
            $this->pos
        );
        $this->assertNull($this->evaluator->evaluate($case, ['x' => 2]));
    }

    // ─── Condition evaluation ────────────────────────────────────────────

    public function testEvaluateConditionEquality(): void
    {
        $condition = new ConditionExpressionNode(
            new ColumnReferenceNode('status', $this->pos),
            Enum\Operator::EQUAL,
            new LiteralNode('active', Enum\Type::STRING, '"active"', $this->pos),
            $this->pos
        );
        $this->assertTrue($this->evaluator->evaluateCondition($condition, ['status' => 'active']));
        $this->assertFalse($this->evaluator->evaluateCondition($condition, ['status' => 'archived']));
    }

    public function testEvaluateConditionWithExpressionLeft(): void
    {
        // LOWER(name) = 'alice'
        $lower = new FunctionCallNode('LOWER', [new ColumnReferenceNode('name', $this->pos)], false, $this->pos);
        $condition = new ConditionExpressionNode(
            $lower,
            Enum\Operator::EQUAL,
            new LiteralNode('alice', Enum\Type::STRING, '"alice"', $this->pos),
            $this->pos
        );
        $this->assertTrue($this->evaluator->evaluateCondition($condition, ['name' => 'Alice']));
        $this->assertFalse($this->evaluator->evaluateCondition($condition, ['name' => 'Bob']));
    }

    public function testEvaluateConditionWithInList(): void
    {
        $condition = new ConditionExpressionNode(
            new ColumnReferenceNode('id', $this->pos),
            Enum\Operator::IN,
            [
                new LiteralNode(1, Enum\Type::INTEGER, '1', $this->pos),
                new LiteralNode(2, Enum\Type::INTEGER, '2', $this->pos),
                new LiteralNode(3, Enum\Type::INTEGER, '3', $this->pos),
            ],
            $this->pos
        );
        $this->assertTrue($this->evaluator->evaluateCondition($condition, ['id' => 2]));
        $this->assertFalse($this->evaluator->evaluateCondition($condition, ['id' => 99]));
    }

    public function testEvaluateConditionIsNull(): void
    {
        $condition = new ConditionExpressionNode(
            new ColumnReferenceNode('x', $this->pos),
            Enum\Operator::IS,
            Enum\Type::NULL,
            $this->pos
        );
        $this->assertTrue($this->evaluator->evaluateCondition($condition, ['x' => null]));
        $this->assertFalse($this->evaluator->evaluateCondition($condition, ['x' => 'value']));
    }

    public function testEvaluateGroupWithAndOr(): void
    {
        // (a = 1) AND (b = 2)
        $left = new ConditionExpressionNode(
            new ColumnReferenceNode('a', $this->pos),
            Enum\Operator::EQUAL,
            new LiteralNode(1, Enum\Type::INTEGER, '1', $this->pos),
            $this->pos
        );
        $right = new ConditionExpressionNode(
            new ColumnReferenceNode('b', $this->pos),
            Enum\Operator::EQUAL,
            new LiteralNode(2, Enum\Type::INTEGER, '2', $this->pos),
            $this->pos
        );
        $group = new ConditionGroupNode([
            ['logical' => Enum\LogicalOperator::AND, 'condition' => $left],
            ['logical' => Enum\LogicalOperator::AND, 'condition' => $right],
        ], $this->pos);
        $this->assertTrue($this->evaluator->evaluateGroup($group, ['a' => 1, 'b' => 2]));
        $this->assertFalse($this->evaluator->evaluateGroup($group, ['a' => 1, 'b' => 99]));
    }

    public function testEvaluateEmptyGroupReturnsTrue(): void
    {
        $group = new ConditionGroupNode([], $this->pos);
        $this->assertTrue($this->evaluator->evaluateGroup($group, []));
    }

    // ─── Error branches ──────────────────────────────────────────────────

    public function testFileQueryNodeThrows(): void
    {
        $fileQuery = new \FQL\Query\FileQuery('json(x.json)');
        $node = new FileQueryNode($fileQuery, 'json(x.json)', $this->pos);
        $this->expectException(Exception\QueryLogicException::class);
        $this->evaluator->evaluate($node, []);
    }

    public function testSubQueryNodeThrows(): void
    {
        // Construct minimal SelectStatementNode for the SubQueryNode wrapper.
        $from = new \FQL\Sql\Ast\Node\FromClauseNode(
            new FileQueryNode(new \FQL\Query\FileQuery('json(x.json)'), 'json(x.json)', $this->pos),
            null,
            $this->pos
        );
        $select = new \FQL\Sql\Ast\Node\SelectStatementNode(
            from: $from,
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
            explain: \FQL\Sql\Ast\ExplainMode::NONE,
            position: $this->pos
        );
        $sub = new SubQueryNode($select, $this->pos);
        $this->expectException(Exception\QueryLogicException::class);
        $this->evaluator->evaluate($sub, []);
    }

    public function testMatchAgainstRunsFulltext(): void
    {
        $node = new MatchAgainstNode(
            [new ColumnReferenceNode('description', $this->pos)],
            'apple',
            Enum\Fulltext::NATURAL,
            $this->pos
        );
        $score = $this->evaluator->evaluate($node, ['description' => 'apple pie']);
        $this->assertIsNumeric($score);
        $this->assertGreaterThan(0, $score);
    }
}
