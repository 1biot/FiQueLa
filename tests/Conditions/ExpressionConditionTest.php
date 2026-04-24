<?php

namespace Conditions;

use FQL\Conditions\ExpressionCondition;
use FQL\Enum;
use FQL\Sql\Ast\Expression\BinaryOperator;
use FQL\Sql\Ast\Expression\BinaryOpNode;
use FQL\Sql\Ast\Expression\ColumnReferenceNode;
use FQL\Sql\Ast\Expression\FunctionCallNode;
use FQL\Sql\Ast\Expression\LiteralNode;
use FQL\Sql\Runtime\ExpressionEvaluator;
use FQL\Sql\Token\Position;
use PHPUnit\Framework\TestCase;

class ExpressionConditionTest extends TestCase
{
    private ExpressionEvaluator $evaluator;
    private Position $pos;

    protected function setUp(): void
    {
        $this->evaluator = new ExpressionEvaluator();
        $this->pos = new Position(0, 1, 1);
    }

    public function testEvaluatesSimpleColumnEquality(): void
    {
        // WHERE status = 'active'
        $cond = new ExpressionCondition(
            Enum\LogicalOperator::AND,
            new ColumnReferenceNode('status', $this->pos),
            Enum\Operator::EQUAL,
            new LiteralNode('active', Enum\Type::STRING, '"active"', $this->pos),
            $this->evaluator
        );
        $this->assertTrue($cond->evaluate(['status' => 'active'], true));
        $this->assertFalse($cond->evaluate(['status' => 'archived'], true));
    }

    public function testEvaluatesFunctionCallOnLeft(): void
    {
        // WHERE LOWER(name) = 'alice'
        $lower = new FunctionCallNode(
            'LOWER',
            [new ColumnReferenceNode('name', $this->pos)],
            false,
            $this->pos
        );
        $cond = new ExpressionCondition(
            Enum\LogicalOperator::AND,
            $lower,
            Enum\Operator::EQUAL,
            new LiteralNode('alice', Enum\Type::STRING, '"alice"', $this->pos),
            $this->evaluator
        );
        $this->assertTrue($cond->evaluate(['name' => 'Alice'], true));
        $this->assertTrue($cond->evaluate(['name' => 'ALICE'], true));
        $this->assertFalse($cond->evaluate(['name' => 'Bob'], true));
    }

    public function testEvaluatesBinaryArithmeticOnLeft(): void
    {
        // WHERE price * qty > 50
        $mul = new BinaryOpNode(
            new ColumnReferenceNode('price', $this->pos),
            BinaryOperator::MULTIPLY,
            new ColumnReferenceNode('qty', $this->pos),
            $this->pos
        );
        $cond = new ExpressionCondition(
            Enum\LogicalOperator::AND,
            $mul,
            Enum\Operator::GREATER_THAN,
            new LiteralNode(50, Enum\Type::INTEGER, '50', $this->pos),
            $this->evaluator
        );
        $this->assertTrue($cond->evaluate(['price' => 20, 'qty' => 3], true)); // 60 > 50
        $this->assertFalse($cond->evaluate(['price' => 10, 'qty' => 3], true)); // 30 > 50
    }

    public function testEvaluatesInListAsExpressionArray(): void
    {
        // WHERE id IN (1, 2, 3)
        $cond = new ExpressionCondition(
            Enum\LogicalOperator::AND,
            new ColumnReferenceNode('id', $this->pos),
            Enum\Operator::IN,
            [
                new LiteralNode(1, Enum\Type::INTEGER, '1', $this->pos),
                new LiteralNode(2, Enum\Type::INTEGER, '2', $this->pos),
                new LiteralNode(3, Enum\Type::INTEGER, '3', $this->pos),
            ],
            $this->evaluator
        );
        $this->assertTrue($cond->evaluate(['id' => 2], true));
        $this->assertFalse($cond->evaluate(['id' => 99], true));
    }

    public function testEvaluatesBetweenAsTwoElementList(): void
    {
        // WHERE price BETWEEN 100 AND 200
        $cond = new ExpressionCondition(
            Enum\LogicalOperator::AND,
            new ColumnReferenceNode('price', $this->pos),
            Enum\Operator::BETWEEN,
            [
                new LiteralNode(100, Enum\Type::INTEGER, '100', $this->pos),
                new LiteralNode(200, Enum\Type::INTEGER, '200', $this->pos),
            ],
            $this->evaluator
        );
        $this->assertTrue($cond->evaluate(['price' => 150], true));
        $this->assertFalse($cond->evaluate(['price' => 50], true));
        $this->assertFalse($cond->evaluate(['price' => 250], true));
    }

    public function testEvaluatesIsNullWithTypeRight(): void
    {
        $cond = new ExpressionCondition(
            Enum\LogicalOperator::AND,
            new ColumnReferenceNode('x', $this->pos),
            Enum\Operator::IS,
            Enum\Type::NULL,
            $this->evaluator
        );
        $this->assertTrue($cond->evaluate(['x' => null], true));
        $this->assertFalse($cond->evaluate(['x' => 'value'], true));
    }

    public function testEvaluatesLikeOperator(): void
    {
        $cond = new ExpressionCondition(
            Enum\LogicalOperator::AND,
            new ColumnReferenceNode('name', $this->pos),
            Enum\Operator::LIKE,
            new LiteralNode('Prod%', Enum\Type::STRING, '"Prod%"', $this->pos),
            $this->evaluator
        );
        $this->assertTrue($cond->evaluate(['name' => 'Product A'], true));
        $this->assertFalse($cond->evaluate(['name' => 'Widget'], true));
    }

    public function testNestingValuesFlagIsIgnored(): void
    {
        $cond = new ExpressionCondition(
            Enum\LogicalOperator::AND,
            new ColumnReferenceNode('x', $this->pos),
            Enum\Operator::EQUAL,
            new LiteralNode(1, Enum\Type::INTEGER, '1', $this->pos),
            $this->evaluator
        );
        // Both flags produce the same result — evaluator handles resolution uniformly.
        $this->assertTrue($cond->evaluate(['x' => 1], true));
        $this->assertTrue($cond->evaluate(['x' => 1], false));
    }

    public function testRenderProducesSqlLikeString(): void
    {
        // `status = 'active'`
        $cond = new ExpressionCondition(
            Enum\LogicalOperator::AND,
            new ColumnReferenceNode('status', $this->pos),
            Enum\Operator::EQUAL,
            new LiteralNode('active', Enum\Type::STRING, '"active"', $this->pos),
            $this->evaluator
        );
        $rendered = $cond->render();
        $this->assertStringContainsString('status', $rendered);
        $this->assertStringContainsString('=', $rendered);
        $this->assertStringContainsString('active', $rendered);
    }

    public function testRenderHandlesInList(): void
    {
        $cond = new ExpressionCondition(
            Enum\LogicalOperator::AND,
            new ColumnReferenceNode('id', $this->pos),
            Enum\Operator::IN,
            [
                new LiteralNode(1, Enum\Type::INTEGER, '1', $this->pos),
                new LiteralNode(2, Enum\Type::INTEGER, '2', $this->pos),
            ],
            $this->evaluator
        );
        $rendered = $cond->render();
        $this->assertStringContainsString('IN', $rendered);
        $this->assertStringContainsString('1', $rendered);
        $this->assertStringContainsString('2', $rendered);
    }

    public function testRenderHandlesIsType(): void
    {
        $cond = new ExpressionCondition(
            Enum\LogicalOperator::AND,
            new ColumnReferenceNode('x', $this->pos),
            Enum\Operator::IS,
            Enum\Type::NULL,
            $this->evaluator
        );
        $this->assertStringContainsString('IS', $cond->render());
    }

    public function testDefaultEvaluatorIsProvided(): void
    {
        $cond = new ExpressionCondition(
            Enum\LogicalOperator::AND,
            new ColumnReferenceNode('a', $this->pos),
            Enum\Operator::EQUAL,
            new LiteralNode(1, Enum\Type::INTEGER, '1', $this->pos)
            // no evaluator — uses default
        );
        $this->assertTrue($cond->evaluate(['a' => 1], true));
    }
}
