<?php

namespace SQL\Parser;

use FQL\Enum;
use FQL\Sql\Ast\Expression\ColumnReferenceNode;
use FQL\Sql\Ast\Expression\ConditionExpressionNode;
use FQL\Sql\Ast\Expression\ConditionGroupNode;
use FQL\Sql\Ast\Expression\LiteralNode;
use FQL\Sql\Ast\Node\SelectStatementNode;
use FQL\Sql\Parser\ParseException;
use FQL\Sql\Parser\Parser;
use FQL\Sql\Token\Tokenizer;
use FQL\Sql\Token\TokenStream;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the condition parser via end-to-end WHERE parsing so we cover all
 * operator variants (=, !=, <, <=, >, >=, IS [NOT], [NOT] LIKE/IN/BETWEEN/REGEXP).
 */
class ConditionParserTest extends TestCase
{
    private function whereConditions(string $sql): ConditionGroupNode
    {
        $parser = Parser::create();
        /** @var SelectStatementNode $ast */
        $ast = $parser->parse(new TokenStream((new Tokenizer())->tokenize($sql)));
        $this->assertNotNull($ast->where, 'Expected a WHERE clause in AST');
        return $ast->where->conditions;
    }

    private function firstCondition(string $sql): ConditionExpressionNode
    {
        $group = $this->whereConditions($sql);
        $entry = $group->entries[0]['condition'];
        $this->assertInstanceOf(ConditionExpressionNode::class, $entry);
        return $entry;
    }

    public function testEqualOperator(): void
    {
        $c = $this->firstCondition('SELECT * FROM x WHERE a = 1');
        $this->assertSame(Enum\Operator::EQUAL, $c->operator);
        $this->assertInstanceOf(ColumnReferenceNode::class, $c->left);
        $this->assertInstanceOf(LiteralNode::class, $c->right);
    }

    public function testStrictEqualOperator(): void
    {
        $c = $this->firstCondition('SELECT * FROM x WHERE a == 1');
        $this->assertSame(Enum\Operator::EQUAL_STRICT, $c->operator);
    }

    public function testNotEqualOperator(): void
    {
        $c = $this->firstCondition('SELECT * FROM x WHERE a != 1');
        $this->assertSame(Enum\Operator::NOT_EQUAL, $c->operator);
    }

    public function testStrictNotEqualOperator(): void
    {
        $c = $this->firstCondition('SELECT * FROM x WHERE a !== 1');
        $this->assertSame(Enum\Operator::NOT_EQUAL_STRICT, $c->operator);
    }

    public function testSqlStandardNotEqualOperator(): void
    {
        $c = $this->firstCondition('SELECT * FROM x WHERE a <> 1');
        $this->assertSame(Enum\Operator::NOT_EQUAL, $c->operator);
    }

    public function testLessThanOperators(): void
    {
        $this->assertSame(
            Enum\Operator::LESS_THAN,
            $this->firstCondition('SELECT * FROM x WHERE a < 1')->operator
        );
        $this->assertSame(
            Enum\Operator::LESS_THAN_OR_EQUAL,
            $this->firstCondition('SELECT * FROM x WHERE a <= 1')->operator
        );
    }

    public function testGreaterThanOperators(): void
    {
        $this->assertSame(
            Enum\Operator::GREATER_THAN,
            $this->firstCondition('SELECT * FROM x WHERE a > 1')->operator
        );
        $this->assertSame(
            Enum\Operator::GREATER_THAN_OR_EQUAL,
            $this->firstCondition('SELECT * FROM x WHERE a >= 1')->operator
        );
    }

    public function testLikeAndNotLike(): void
    {
        $this->assertSame(
            Enum\Operator::LIKE,
            $this->firstCondition('SELECT * FROM x WHERE a LIKE "A%"')->operator
        );
        $this->assertSame(
            Enum\Operator::NOT_LIKE,
            $this->firstCondition('SELECT * FROM x WHERE a NOT LIKE "A%"')->operator
        );
    }

    public function testRegexpAndNotRegexp(): void
    {
        $this->assertSame(
            Enum\Operator::REGEXP,
            $this->firstCondition('SELECT * FROM x WHERE a REGEXP "^A"')->operator
        );
        $this->assertSame(
            Enum\Operator::NOT_REGEXP,
            $this->firstCondition('SELECT * FROM x WHERE a NOT REGEXP "^A"')->operator
        );
    }

    public function testInOperatorReturnsExpressionArray(): void
    {
        $c = $this->firstCondition('SELECT * FROM x WHERE a IN (1, 2, 3)');
        $this->assertSame(Enum\Operator::IN, $c->operator);
        $this->assertIsArray($c->right);
        $this->assertCount(3, $c->right);
        $this->assertInstanceOf(LiteralNode::class, $c->right[0]);
    }

    public function testNotInOperator(): void
    {
        $c = $this->firstCondition('SELECT * FROM x WHERE a NOT IN (1, 2)');
        $this->assertSame(Enum\Operator::NOT_IN, $c->operator);
        $this->assertIsArray($c->right);
        $this->assertCount(2, $c->right);
    }

    public function testBetweenOperatorReturnsTwoElementArray(): void
    {
        $c = $this->firstCondition('SELECT * FROM x WHERE a BETWEEN 1 AND 10');
        $this->assertSame(Enum\Operator::BETWEEN, $c->operator);
        $this->assertIsArray($c->right);
        $this->assertCount(2, $c->right);
    }

    public function testNotBetweenOperator(): void
    {
        $c = $this->firstCondition('SELECT * FROM x WHERE a NOT BETWEEN 1 AND 10');
        $this->assertSame(Enum\Operator::NOT_BETWEEN, $c->operator);
    }

    public function testIsNullReturnsTypeEnum(): void
    {
        $c = $this->firstCondition('SELECT * FROM x WHERE a IS NULL');
        $this->assertSame(Enum\Operator::IS, $c->operator);
        $this->assertSame(Enum\Type::NULL, $c->right);
    }

    public function testIsNotNull(): void
    {
        $c = $this->firstCondition('SELECT * FROM x WHERE a IS NOT NULL');
        $this->assertSame(Enum\Operator::NOT_IS, $c->operator);
        $this->assertSame(Enum\Type::NULL, $c->right);
    }

    public function testIsTrueReturnsBooleanType(): void
    {
        $c = $this->firstCondition('SELECT * FROM x WHERE a IS TRUE');
        $this->assertSame(Enum\Type::TRUE, $c->right);

        $c = $this->firstCondition('SELECT * FROM x WHERE a IS FALSE');
        $this->assertSame(Enum\Type::FALSE, $c->right);
    }

    public function testIsWithTypeIdentifierResolvesToEnum(): void
    {
        $c = $this->firstCondition('SELECT * FROM x WHERE a IS int');
        $this->assertSame(Enum\Type::INTEGER, $c->right);

        $c = $this->firstCondition('SELECT * FROM x WHERE a IS string');
        $this->assertSame(Enum\Type::STRING, $c->right);
    }

    public function testIsWithUnknownTypeThrows(): void
    {
        $this->expectException(ParseException::class);
        $this->firstCondition('SELECT * FROM x WHERE a IS nonsense');
    }

    public function testAndChainProducesMultipleEntries(): void
    {
        $group = $this->whereConditions('SELECT * FROM x WHERE a = 1 AND b = 2 AND c = 3');
        $this->assertCount(3, $group->entries);
        $this->assertSame(Enum\LogicalOperator::AND, $group->entries[0]['logical']);
        $this->assertSame(Enum\LogicalOperator::AND, $group->entries[1]['logical']);
        $this->assertSame(Enum\LogicalOperator::AND, $group->entries[2]['logical']);
    }

    public function testOrChain(): void
    {
        $group = $this->whereConditions('SELECT * FROM x WHERE a = 1 OR b = 2');
        $this->assertCount(2, $group->entries);
        $this->assertSame(Enum\LogicalOperator::OR, $group->entries[1]['logical']);
    }

    public function testXorChain(): void
    {
        $group = $this->whereConditions('SELECT * FROM x WHERE a = 1 XOR b = 2');
        $this->assertCount(2, $group->entries);
        $this->assertSame(Enum\LogicalOperator::XOR, $group->entries[1]['logical']);
    }

    public function testNestedParenthesizedGroup(): void
    {
        $group = $this->whereConditions('SELECT * FROM x WHERE (a = 1 OR b = 2) AND c = 3');
        $this->assertCount(2, $group->entries);
        $this->assertInstanceOf(ConditionGroupNode::class, $group->entries[0]['condition']);
        $this->assertSame(Enum\LogicalOperator::AND, $group->entries[1]['logical']);
        $this->assertInstanceOf(ConditionExpressionNode::class, $group->entries[1]['condition']);
    }

    public function testStringLiteralOnRight(): void
    {
        $c = $this->firstCondition('SELECT * FROM x WHERE name = "Alice"');
        $this->assertInstanceOf(LiteralNode::class, $c->right);
        $this->assertSame('Alice', $c->right->value);
    }

    public function testUnknownOperatorThrows(): void
    {
        $this->expectException(ParseException::class);
        // `:` is not a supported operator in FQL — expect parse error.
        $this->firstCondition('SELECT * FROM x WHERE a ~~ 1');
    }

    public function testNotWithBadFollowingTokenThrows(): void
    {
        $this->expectException(ParseException::class);
        $this->firstCondition('SELECT * FROM x WHERE a NOT 5');
    }
}
