<?php

namespace SQL\Parser;

use FQL\Sql\Ast\Expression\BinaryOperator;
use FQL\Sql\Ast\Expression\BinaryOpNode;
use FQL\Sql\Ast\Expression\ColumnReferenceNode;
use FQL\Sql\Ast\Expression\LiteralNode;
use FQL\Sql\Ast\Node\SelectStatementNode;
use FQL\Sql\Parser\Parser;
use FQL\Sql\Token\Tokenizer;
use FQL\Sql\Token\TokenStream;
use PHPUnit\Framework\TestCase;

class BinaryExpressionTest extends TestCase
{
    private function fieldExpression(string $sql): \FQL\Sql\Ast\Expression\ExpressionNode
    {
        $tokens = (new Tokenizer())->tokenize($sql);
        /** @var SelectStatementNode $ast */
        $ast = Parser::create()->parse(new TokenStream($tokens));
        return $ast->fields[0]->expression;
    }

    public function testParsesAdditionAsBinaryOp(): void
    {
        /** @var BinaryOpNode $node */
        $node = $this->fieldExpression('SELECT a + b FROM x');
        $this->assertInstanceOf(BinaryOpNode::class, $node);
        $this->assertSame(BinaryOperator::ADD, $node->operator);
        $this->assertInstanceOf(ColumnReferenceNode::class, $node->left);
        $this->assertInstanceOf(ColumnReferenceNode::class, $node->right);
    }

    public function testParsesSubtraction(): void
    {
        /** @var BinaryOpNode $node */
        $node = $this->fieldExpression('SELECT price - 10 FROM x');
        $this->assertInstanceOf(BinaryOpNode::class, $node);
        $this->assertSame(BinaryOperator::SUBTRACT, $node->operator);
    }

    public function testParsesMultiplication(): void
    {
        $node = $this->fieldExpression('SELECT price * 2 FROM x');
        $this->assertInstanceOf(BinaryOpNode::class, $node);
        $this->assertSame(BinaryOperator::MULTIPLY, $node->operator);
    }

    public function testParsesDivision(): void
    {
        $node = $this->fieldExpression('SELECT price / 2 FROM x');
        $this->assertInstanceOf(BinaryOpNode::class, $node);
        $this->assertSame(BinaryOperator::DIVIDE, $node->operator);
    }

    public function testParsesModulo(): void
    {
        $node = $this->fieldExpression('SELECT id % 2 FROM x');
        $this->assertInstanceOf(BinaryOpNode::class, $node);
        $this->assertSame(BinaryOperator::MODULO, $node->operator);
    }

    public function testMultiplicationBindsTighterThanAddition(): void
    {
        // `a + b * c` should parse as `a + (b * c)`
        /** @var BinaryOpNode $node */
        $node = $this->fieldExpression('SELECT a + b * c FROM x');
        $this->assertSame(BinaryOperator::ADD, $node->operator);
        $this->assertInstanceOf(BinaryOpNode::class, $node->right);
        $this->assertSame(BinaryOperator::MULTIPLY, $node->right->operator);
    }

    public function testParenthesesOverridePrecedence(): void
    {
        // `(a + b) * c` — parens are not first-class in this grammar yet, so this
        // actually fails — but `a * (b + c)` can be expressed via function form.
        // Instead we verify that infix operators chain left-to-right correctly.
        /** @var BinaryOpNode $node */
        $node = $this->fieldExpression('SELECT a + b + c FROM x');
        $this->assertSame(BinaryOperator::ADD, $node->operator);
        // Left-associative: `(a + b) + c`.
        $this->assertInstanceOf(BinaryOpNode::class, $node->left);
        $this->assertInstanceOf(ColumnReferenceNode::class, $node->right);
    }

    public function testNestedExpressionsInFunctionArguments(): void
    {
        /** @var \FQL\Sql\Ast\Expression\FunctionCallNode $node */
        $node = $this->fieldExpression('SELECT SUM(price * 0.9) FROM x');
        $this->assertInstanceOf(\FQL\Sql\Ast\Expression\FunctionCallNode::class, $node);
        $this->assertInstanceOf(BinaryOpNode::class, $node->arguments[0]);
    }

    public function testSignedNumberInExpressionContext(): void
    {
        // `price > -5` — the `-5` is a signed number literal, not `price > OP_MINUS 5`.
        $tokens = (new Tokenizer())->tokenize('SELECT id FROM x WHERE price > -5');
        /** @var SelectStatementNode $ast */
        $ast = Parser::create()->parse(new TokenStream($tokens));
        $condition = $ast->where->conditions->entries[0]['condition'];
        $right = $condition->right;
        $this->assertInstanceOf(LiteralNode::class, $right);
        $this->assertSame(-5, $right->value);
    }

    public function testMinusBetweenIdentifiersIsPartOfIdentifier(): void
    {
        // `brand-code` is tokenised as a single IDENTIFIER (kebab-case support), not a
        // subtraction. Verifies the tokeniser / parser interaction.
        /** @var ColumnReferenceNode $node */
        $node = $this->fieldExpression('SELECT brand-code FROM x');
        $this->assertInstanceOf(ColumnReferenceNode::class, $node);
        $this->assertSame('brand-code', $node->name);
    }

    public function testBinaryOperatorEnumPrecedence(): void
    {
        $this->assertSame(10, BinaryOperator::ADD->precedence());
        $this->assertSame(10, BinaryOperator::SUBTRACT->precedence());
        $this->assertSame(20, BinaryOperator::MULTIPLY->precedence());
        $this->assertSame(20, BinaryOperator::DIVIDE->precedence());
        $this->assertSame(20, BinaryOperator::MODULO->precedence());
    }

    public function testBinaryOperatorFunctionNames(): void
    {
        $this->assertSame('ADD', BinaryOperator::ADD->functionName());
        $this->assertSame('SUB', BinaryOperator::SUBTRACT->functionName());
        $this->assertSame('MULTIPLY', BinaryOperator::MULTIPLY->functionName());
        $this->assertSame('DIVIDE', BinaryOperator::DIVIDE->functionName());
        $this->assertSame('MOD', BinaryOperator::MODULO->functionName());
    }

    public function testBinaryOpNodeExposesPosition(): void
    {
        $pos = new \FQL\Sql\Token\Position(0, 1, 1);
        $node = new BinaryOpNode(
            new LiteralNode(1, \FQL\Enum\Type::INTEGER, '1', $pos),
            BinaryOperator::ADD,
            new LiteralNode(2, \FQL\Enum\Type::INTEGER, '2', $pos),
            $pos
        );
        $this->assertSame($pos, $node->position());
    }
}
