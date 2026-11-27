<?php

namespace SQL\Builder;

use FQL\Enum;
use FQL\Sql\Ast\Expression\CastExpressionNode;
use FQL\Sql\Ast\Expression\ColumnReferenceNode;
use FQL\Sql\Ast\Expression\ConditionExpressionNode;
use FQL\Sql\Ast\Expression\ConditionGroupNode;
use FQL\Sql\Ast\Expression\FunctionCallNode;
use FQL\Sql\Ast\Expression\LiteralNode;
use FQL\Sql\Ast\Expression\MatchAgainstNode;
use FQL\Sql\Ast\Expression\StarNode;
use FQL\Sql\Builder\ExpressionCompiler;
use FQL\Sql\Token\Position;
use PHPUnit\Framework\TestCase;

class ExpressionCompilerTest extends TestCase
{
    private ExpressionCompiler $compiler;
    private Position $pos;

    protected function setUp(): void
    {
        $this->compiler = new ExpressionCompiler();
        $this->pos = new Position(0, 1, 1);
    }

    private function literal(mixed $value, Enum\Type $type, string $raw): LiteralNode
    {
        return new LiteralNode($value, $type, $raw, $this->pos);
    }

    private function column(string $name): ColumnReferenceNode
    {
        return new ColumnReferenceNode($name, $this->pos);
    }

    public function testRenderStringLiteralWrapsInQuotes(): void
    {
        $node = $this->literal('hello', Enum\Type::STRING, '"hello"');
        $this->assertSame('"hello"', $this->compiler->renderLiteral($node));
        $this->assertSame('"hello"', $this->compiler->renderExpression($node));
    }

    public function testRenderIntegerLiteral(): void
    {
        $node = $this->literal(42, Enum\Type::INTEGER, '42');
        $this->assertSame('42', $this->compiler->renderLiteral($node));
    }

    public function testRenderFloatLiteral(): void
    {
        $node = $this->literal(3.14, Enum\Type::FLOAT, '3.14');
        $this->assertSame('3.14', $this->compiler->renderLiteral($node));
    }

    public function testRenderNullLiteral(): void
    {
        $node = $this->literal(null, Enum\Type::NULL, 'NULL');
        $this->assertSame('NULL', $this->compiler->renderLiteral($node));
    }

    public function testRenderBooleanLiteral(): void
    {
        $this->assertSame(
            'TRUE',
            $this->compiler->renderLiteral($this->literal(true, Enum\Type::BOOLEAN, 'TRUE'))
        );
        $this->assertSame(
            'FALSE',
            $this->compiler->renderLiteral($this->literal(false, Enum\Type::BOOLEAN, 'FALSE'))
        );
    }

    public function testRenderColumnReferenceUsesName(): void
    {
        $this->assertSame('user.name', $this->compiler->renderExpression($this->column('user.name')));
    }

    public function testRenderStarNode(): void
    {
        $this->assertSame('*', $this->compiler->renderExpression(new StarNode($this->pos)));
    }

    public function testRenderFunctionCallWithoutDistinct(): void
    {
        $node = new FunctionCallNode(
            'SUM',
            [$this->column('price')],
            false,
            $this->pos
        );
        $this->assertSame('SUM(price)', $this->compiler->renderExpression($node));
    }

    public function testRenderFunctionCallWithDistinct(): void
    {
        $node = new FunctionCallNode(
            'COUNT',
            [$this->column('id')],
            true,
            $this->pos
        );
        $this->assertSame('COUNT(DISTINCT id)', $this->compiler->renderExpression($node));
    }

    public function testRenderFunctionCallWithMultipleArguments(): void
    {
        $node = new FunctionCallNode(
            'CONCAT',
            [$this->column('a'), $this->column('b'), $this->literal('x', Enum\Type::STRING, '"x"')],
            false,
            $this->pos
        );
        $this->assertSame('CONCAT(a, b, "x")', $this->compiler->renderExpression($node));
    }

    public function testRenderNestedFunctionCall(): void
    {
        $inner = new FunctionCallNode('LOWER', [$this->column('name')], false, $this->pos);
        $outer = new FunctionCallNode('UPPER', [$inner], false, $this->pos);
        $this->assertSame('UPPER(LOWER(name))', $this->compiler->renderExpression($outer));
    }

    public function testRenderCastExpression(): void
    {
        $node = new CastExpressionNode(
            $this->column('price'),
            Enum\Type::INTEGER,
            $this->pos
        );
        $this->assertSame('CAST(price AS INT)', $this->compiler->renderExpression($node));
    }

    public function testRenderMatchAgainst(): void
    {
        $node = new MatchAgainstNode(
            [$this->column('title'), $this->column('desc')],
            'hello world',
            Enum\Fulltext::NATURAL,
            $this->pos
        );
        $rendered = $this->compiler->renderExpression($node);
        // `desc` is a keyword, so it is backticked to parse back as a column.
        $this->assertSame('MATCH(title, `desc`) AGAINST("hello world IN NATURAL MODE")', $rendered);
    }

    public function testRenderUnsupportedNodeThrows(): void
    {
        $this->expectException(\FQL\Exception\QueryLogicException::class);
        // Create an AstNode that isn't one of the handled expression types.
        $node = new class ($this->pos) implements \FQL\Sql\Ast\Expression\ExpressionNode {
            public function __construct(private readonly Position $position)
            {
            }
            public function position(): Position
            {
                return $this->position;
            }
        };
        $this->compiler->renderExpression($node);
    }

    public function testRenderConditionUsesOperatorRendering(): void
    {
        $node = new ConditionExpressionNode(
            $this->column('price'),
            Enum\Operator::GREATER_THAN,
            $this->literal(100, Enum\Type::INTEGER, '100'),
            $this->pos
        );
        $rendered = $this->compiler->renderCondition($node);
        $this->assertStringContainsString('price', $rendered);
        $this->assertStringContainsString('>', $rendered);
        $this->assertStringContainsString('100', $rendered);
    }

    public function testRenderConditionWithStringRight(): void
    {
        $node = new ConditionExpressionNode(
            $this->column('name'),
            Enum\Operator::EQUAL,
            $this->literal('alice', Enum\Type::STRING, '"alice"'),
            $this->pos
        );
        $rendered = $this->compiler->renderCondition($node);
        $this->assertStringContainsString("'alice'", $rendered);
    }

    public function testRenderConditionWithInList(): void
    {
        $node = new ConditionExpressionNode(
            $this->column('id'),
            Enum\Operator::IN,
            [
                $this->literal(1, Enum\Type::INTEGER, '1'),
                $this->literal(2, Enum\Type::INTEGER, '2'),
                $this->literal(3, Enum\Type::INTEGER, '3'),
            ],
            $this->pos
        );
        $rendered = $this->compiler->renderCondition($node);
        $this->assertStringContainsString('IN', $rendered);
        $this->assertStringContainsString('1', $rendered);
        $this->assertStringContainsString('3', $rendered);
    }

    public function testRenderConditionWithIsType(): void
    {
        $node = new ConditionExpressionNode(
            $this->column('a'),
            Enum\Operator::IS,
            Enum\Type::NULL,
            $this->pos
        );
        $rendered = $this->compiler->renderCondition($node);
        $this->assertStringContainsString('IS', $rendered);
    }

    public function testRenderConditionGroupJoinsEntriesWithLogicalOperator(): void
    {
        $a = new ConditionExpressionNode(
            $this->column('a'),
            Enum\Operator::EQUAL,
            $this->literal(1, Enum\Type::INTEGER, '1'),
            $this->pos
        );
        $b = new ConditionExpressionNode(
            $this->column('b'),
            Enum\Operator::EQUAL,
            $this->literal(2, Enum\Type::INTEGER, '2'),
            $this->pos
        );
        $group = new ConditionGroupNode(
            [
                ['logical' => Enum\LogicalOperator::AND, 'condition' => $a],
                ['logical' => Enum\LogicalOperator::OR, 'condition' => $b],
            ],
            $this->pos
        );
        $rendered = $this->compiler->renderConditionGroup($group);
        $this->assertStringContainsString('OR', $rendered);
    }

    public function testRenderConditionGroupNested(): void
    {
        $inner = new ConditionGroupNode(
            [[
                'logical' => Enum\LogicalOperator::AND,
                'condition' => new ConditionExpressionNode(
                    $this->column('x'),
                    Enum\Operator::EQUAL,
                    $this->literal(1, Enum\Type::INTEGER, '1'),
                    $this->pos
                ),
            ]],
            $this->pos
        );
        $outer = new ConditionGroupNode(
            [
                ['logical' => Enum\LogicalOperator::AND, 'condition' => $inner],
            ],
            $this->pos
        );
        $rendered = $this->compiler->renderConditionGroup($outer);
        $this->assertStringStartsWith('(', $rendered);
        $this->assertStringEndsWith(')', $rendered);
    }

    public function testScalarRightValueWithLiteral(): void
    {
        $value = $this->compiler->scalarRightValue(
            $this->literal(42, Enum\Type::INTEGER, '42'),
            Enum\Operator::EQUAL
        );
        $this->assertSame(42, $value);
    }

    public function testScalarRightValueWithColumnReferenceReturnsName(): void
    {
        $value = $this->compiler->scalarRightValue(
            $this->column('other.id'),
            Enum\Operator::EQUAL
        );
        $this->assertSame('other.id', $value);
    }

    public function testScalarRightValueWithTypeReturnsType(): void
    {
        $value = $this->compiler->scalarRightValue(Enum\Type::NULL, Enum\Operator::IS);
        $this->assertSame(Enum\Type::NULL, $value);
    }

    public function testScalarRightValueWithArrayReturnsArrayOfScalars(): void
    {
        $value = $this->compiler->scalarRightValue(
            [
                $this->literal(1, Enum\Type::INTEGER, '1'),
                $this->literal('two', Enum\Type::STRING, '"two"'),
            ],
            Enum\Operator::IN
        );
        $this->assertIsArray($value);
        $this->assertSame([1, 'two'], $value);
    }

    public function testScalarRightValueWithBooleanLiteralCoercesToString(): void
    {
        $value = $this->compiler->scalarRightValue(
            $this->literal(true, Enum\Type::BOOLEAN, 'TRUE'),
            Enum\Operator::EQUAL
        );
        $this->assertSame('true', $value);
    }

    /**
     * Regression: rendering a condition with a backtick-quoted chained path on the
     * left side used to chop the outer pair via removeQuotes(), corrupting
     * `` `info`.`invoiceNumber` `` into `info`.`invoiceNumber` (an unbalanced
     * lexeme). When QueryBuildingVisitor stringifies an `IF(... IS ARRAY, …)`
     * expression and feeds it back into `$query->select()`, the broken left
     * side blows up the re-parse and the IF collapses to garbage — the user
     * observed `null` in every result row instead of the expected branch
     * value. Keep the backticks verbatim so the round-trip is lossless.
     */
    public function testRenderConditionPreservesBacktickChainedPath(): void
    {
        $node = new ConditionExpressionNode(
            $this->column('`info`.`invoiceNumber`'),
            Enum\Operator::IS,
            Enum\Type::ARRAY,
            $this->pos
        );
        $rendered = $this->compiler->renderCondition($node);
        $this->assertStringContainsString('`info`.`invoiceNumber`', $rendered);

        // Round-trip: the rendered fragment must re-parse into an equivalent
        // condition (left side keeps the chained backtick path, operator IS,
        // right side Type::ARRAY).
        $reparsed = \FQL\Sql\Provider::parseExpression(
            sprintf('IF(%s, "yes", "no")', $rendered)
        );
        $this->assertInstanceOf(\FQL\Sql\Ast\Expression\FunctionCallNode::class, $reparsed);
        $this->assertSame('IF', $reparsed->name);
        $this->assertInstanceOf(ConditionExpressionNode::class, $reparsed->arguments[0]);
        $this->assertInstanceOf(ColumnReferenceNode::class, $reparsed->arguments[0]->left);
        $this->assertSame('`info`.`invoiceNumber`', $reparsed->arguments[0]->left->name);
        $this->assertSame(Enum\Operator::IS, $reparsed->arguments[0]->operator);
        $this->assertSame(Enum\Type::ARRAY, $reparsed->arguments[0]->right);
    }

    /**
     * A single backticked segment reaches the AST without its backticks, so the name has
     * to be quoted again or the rendered call does not parse back.
     */
    public function testRenderColumnQuotesNamesThatAreNotPlainIdentifiers(): void
    {
        $this->assertSame('`variant:Rozmer uteráku`', $this->compiler->renderExpression(
            new ColumnReferenceNode('variant:Rozmer uteráku', $this->pos, true)
        ));
        $this->assertSame('`a b`', $this->compiler->renderExpression($this->column('a b')));
        $this->assertSame('`group`', $this->compiler->renderExpression($this->column('group')));
        $this->assertSame('price', $this->compiler->renderExpression(
            new ColumnReferenceNode('price', $this->pos, true)
        ));
        $this->assertSame('items[].name', $this->compiler->renderExpression($this->column('items[].name')));
        $this->assertSame('`info`.`a b`', $this->compiler->renderExpression($this->column('`info`.`a b`')));
    }

    public function testRenderFunctionCallWithQuotedColumnParsesBack(): void
    {
        $node = new FunctionCallNode('CONCAT', [
            $this->column('name'),
            $this->literal(': ', Enum\Type::STRING, '": "'),
            new ColumnReferenceNode('variant:Rozmer uteráku', $this->pos, true),
        ], false, $this->pos);

        $rendered = $this->compiler->renderExpression($node);
        $this->assertSame('CONCAT(name, ": ", `variant:Rozmer uteráku`)', $rendered);

        $reparsed = \FQL\Sql\Provider::parseExpression($rendered);
        $this->assertInstanceOf(FunctionCallNode::class, $reparsed);
        $this->assertInstanceOf(ColumnReferenceNode::class, $reparsed->arguments[2]);
        $this->assertSame('variant:Rozmer uteráku', $reparsed->arguments[2]->name);
    }

    public function testRenderStringLiteralWithDoubleQuoteUsesSingleQuotes(): void
    {
        $node = $this->literal('say "hi"', Enum\Type::STRING, "'say \"hi\"'");
        $this->assertSame("'say \"hi\"'", $this->compiler->renderLiteral($node));
    }

    /**
     * Names FQL cannot write (a backtick inside, an index like `x[0]`) are rendered as
     * they are, as before the quoting was added.
     */
    public function testRenderKeepsNamesThatFqlCannotWrite(): void
    {
        $this->assertSame('a`b', $this->compiler->renderExpression($this->column('a`b')));
        $this->assertSame('x[0]', $this->compiler->renderExpression($this->column('x[0]')));

        $node = new \FQL\Sql\Ast\Expression\CollectObjectExpressionNode(
            [
                ['expression' => $this->column('name'), 'alias' => 'my key'],
                ['expression' => $this->column('price'), 'alias' => 'a`b'],
            ],
            [],
            $this->pos
        );
        $this->assertSame('name AS `my key`, price AS a`b', $this->compiler->renderExpression($node));
    }
}
