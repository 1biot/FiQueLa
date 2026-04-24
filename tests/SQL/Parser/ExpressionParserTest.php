<?php

namespace SQL\Parser;

use FQL\Enum;
use FQL\Sql\Ast\Expression\CaseExpressionNode;
use FQL\Sql\Ast\Expression\CastExpressionNode;
use FQL\Sql\Ast\Expression\ColumnReferenceNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\Expression\FunctionCallNode;
use FQL\Sql\Ast\Expression\LiteralNode;
use FQL\Sql\Ast\Expression\MatchAgainstNode;
use FQL\Sql\Ast\Expression\StarNode;
use FQL\Sql\Ast\Node\SelectStatementNode;
use FQL\Sql\Parser\ConditionGroupParser;
use FQL\Sql\Parser\ConditionParser;
use FQL\Sql\Parser\ExpressionParser;
use FQL\Sql\Parser\ParseException;
use FQL\Sql\Parser\Parser;
use FQL\Sql\Token\Tokenizer;
use FQL\Sql\Token\TokenStream;
use FQL\Sql\Token\TokenType;
use PHPUnit\Framework\TestCase;

class ExpressionParserTest extends TestCase
{
    /**
     * Builds a wired ExpressionParser (with CASE-aware ConditionGroupParser attached)
     * so we can feed it arbitrary token streams.
     */
    private function makeParser(): ExpressionParser
    {
        $exprParser = new ExpressionParser();
        $conditionParser = new ConditionParser($exprParser);
        $groupParser = new ConditionGroupParser($conditionParser);
        $exprParser->setConditionGroupParser($groupParser);
        return $exprParser;
    }

    private function streamOf(string $sql): TokenStream
    {
        return new TokenStream((new Tokenizer())->tokenize($sql));
    }

    /**
     * Full end-to-end: parses a top-level SELECT so we can pick a single field
     * expression out of the resulting AST. Convenient for structural assertions.
     */
    private function fieldOf(string $sql): ExpressionNode
    {
        $parser = Parser::create();
        $ast = $parser->parse($this->streamOf($sql));
        $this->assertInstanceOf(SelectStatementNode::class, $ast);
        return $ast->fields[0]->expression;
    }

    public function testParsesIntegerLiteral(): void
    {
        $node = $this->fieldOf('SELECT 42 FROM x');
        $this->assertInstanceOf(LiteralNode::class, $node);
        $this->assertSame(42, $node->value);
        $this->assertSame(Enum\Type::INTEGER, $node->type);
    }

    public function testParsesFloatLiteral(): void
    {
        $node = $this->fieldOf('SELECT 3.14 FROM x');
        $this->assertInstanceOf(LiteralNode::class, $node);
        $this->assertEquals(3.14, $node->value);
        $this->assertSame(Enum\Type::FLOAT, $node->type);
    }

    public function testParsesStringLiteralStripsQuotes(): void
    {
        $node = $this->fieldOf('SELECT "hello" FROM x');
        $this->assertInstanceOf(LiteralNode::class, $node);
        $this->assertSame('hello', $node->value);
        $this->assertSame(Enum\Type::STRING, $node->type);
    }

    public function testParsesBooleanLiteral(): void
    {
        $node = $this->fieldOf('SELECT TRUE FROM x');
        $this->assertInstanceOf(LiteralNode::class, $node);
        $this->assertTrue($node->value);
        $this->assertSame(Enum\Type::BOOLEAN, $node->type);

        $node = $this->fieldOf('SELECT false FROM x');
        $this->assertFalse($node->value);
    }

    public function testParsesNullLiteral(): void
    {
        $node = $this->fieldOf('SELECT NULL FROM x');
        $this->assertInstanceOf(LiteralNode::class, $node);
        $this->assertNull($node->value);
        $this->assertSame(Enum\Type::NULL, $node->type);
    }

    public function testParsesColumnReference(): void
    {
        $node = $this->fieldOf('SELECT price FROM x');
        $this->assertInstanceOf(ColumnReferenceNode::class, $node);
        $this->assertSame('price', $node->name);
        $this->assertFalse($node->quoted);
    }

    public function testParsesBacktickIdentifierAsQuoted(): void
    {
        $node = $this->fieldOf('SELECT `weird name` FROM x');
        $this->assertInstanceOf(ColumnReferenceNode::class, $node);
        $this->assertSame('weird name', $node->name);
        $this->assertTrue($node->quoted);
    }

    public function testParsesStarAsStarNode(): void
    {
        $node = $this->fieldOf('SELECT * FROM x');
        $this->assertInstanceOf(StarNode::class, $node);
    }

    public function testParsesDottedIdentifier(): void
    {
        $node = $this->fieldOf('SELECT user.profile.name FROM x');
        $this->assertInstanceOf(ColumnReferenceNode::class, $node);
        $this->assertSame('user.profile.name', $node->name);
    }

    public function testParsesFunctionCallWithSingleArgument(): void
    {
        $node = $this->fieldOf('SELECT SUM(price) FROM x');
        $this->assertInstanceOf(FunctionCallNode::class, $node);
        $this->assertSame('SUM', $node->name);
        $this->assertCount(1, $node->arguments);
        $this->assertFalse($node->distinct);
    }

    public function testParsesCountStar(): void
    {
        $node = $this->fieldOf('SELECT COUNT(*) FROM x');
        $this->assertInstanceOf(FunctionCallNode::class, $node);
        $this->assertSame('COUNT', $node->name);
        $this->assertInstanceOf(StarNode::class, $node->arguments[0]);
    }

    public function testParsesDistinctInFunctionCall(): void
    {
        $node = $this->fieldOf('SELECT COUNT(DISTINCT id) FROM x');
        $this->assertInstanceOf(FunctionCallNode::class, $node);
        $this->assertTrue($node->distinct);
        $this->assertCount(1, $node->arguments);
        $this->assertInstanceOf(ColumnReferenceNode::class, $node->arguments[0]);
    }

    public function testParsesMultipleArguments(): void
    {
        $node = $this->fieldOf('SELECT CONCAT(a, b, c) FROM x');
        $this->assertInstanceOf(FunctionCallNode::class, $node);
        $this->assertCount(3, $node->arguments);
    }

    public function testParsesNestedFunctionCall(): void
    {
        $node = $this->fieldOf('SELECT UPPER(LOWER(name)) FROM x');
        $this->assertInstanceOf(FunctionCallNode::class, $node);
        $this->assertSame('UPPER', $node->name);
        $this->assertInstanceOf(FunctionCallNode::class, $node->arguments[0]);
        $this->assertSame('LOWER', $node->arguments[0]->name);
    }

    public function testParsesCastExpression(): void
    {
        $node = $this->fieldOf('SELECT CAST(price AS INT) FROM x');
        $this->assertInstanceOf(CastExpressionNode::class, $node);
        $this->assertInstanceOf(ColumnReferenceNode::class, $node->value);
        $this->assertSame(Enum\Type::INTEGER, $node->targetType);
    }

    public function testParsesCastWithAllAliasedTypes(): void
    {
        $types = [
            'DOUBLE' => Enum\Type::FLOAT,
            'FLOAT' => Enum\Type::FLOAT,
            'REAL' => Enum\Type::FLOAT,
            'INTEGER' => Enum\Type::INTEGER,
            'SIGNED' => Enum\Type::INTEGER,
            'DECIMAL' => Enum\Type::NUMBER,
            'NUMBER' => Enum\Type::NUMBER,
            'VARCHAR' => Enum\Type::STRING,
            'TEXT' => Enum\Type::STRING,
            'BOOL' => Enum\Type::BOOLEAN,
            'NULL' => Enum\Type::NULL,
        ];
        foreach ($types as $alias => $expected) {
            /** @var CastExpressionNode $node */
            $node = $this->fieldOf(sprintf('SELECT CAST(x AS %s) FROM t', $alias));
            $this->assertSame($expected, $node->targetType, "CAST AS $alias");
        }
    }

    public function testCastWithUnknownTypeThrows(): void
    {
        $this->expectException(\FQL\Exception\QueryLogicException::class);
        $this->fieldOf('SELECT CAST(x AS NONSENSE) FROM t');
    }

    public function testParsesMatchAgainstLegacyForm(): void
    {
        /** @var MatchAgainstNode $node */
        $node = $this->fieldOf('SELECT MATCH(a, b) AGAINST("query IN NATURAL MODE") FROM x');
        $this->assertInstanceOf(MatchAgainstNode::class, $node);
        $this->assertCount(2, $node->fields);
        $this->assertSame('query', $node->searchQuery);
        $this->assertSame(Enum\Fulltext::NATURAL, $node->mode);
    }

    public function testParsesMatchAgainstSplitForm(): void
    {
        /** @var MatchAgainstNode $node */
        $node = $this->fieldOf('SELECT MATCH(a) AGAINST("word" IN BOOLEAN MODE) FROM x');
        $this->assertInstanceOf(MatchAgainstNode::class, $node);
        $this->assertSame('word', $node->searchQuery);
        $this->assertSame(Enum\Fulltext::BOOLEAN, $node->mode);
    }

    public function testMatchAgainstRejectsNonColumnArguments(): void
    {
        $this->expectException(ParseException::class);
        $this->fieldOf('SELECT MATCH("literal") AGAINST("q IN NATURAL MODE") FROM x');
    }

    public function testMatchAgainstRejectsEmptySearchQuery(): void
    {
        $this->expectException(ParseException::class);
        $this->fieldOf('SELECT MATCH(a) AGAINST(" IN NATURAL MODE") FROM x');
    }

    public function testMatchAgainstRejectsUnknownMode(): void
    {
        $this->expectException(ParseException::class);
        $this->fieldOf('SELECT MATCH(a) AGAINST("q IN FUZZY MODE") FROM x');
    }

    public function testParsesCaseExpressionWithElse(): void
    {
        /** @var CaseExpressionNode $node */
        $node = $this->fieldOf('SELECT CASE WHEN x > 1 THEN "big" ELSE "small" END FROM t');
        $this->assertInstanceOf(CaseExpressionNode::class, $node);
        $this->assertCount(1, $node->branches);
        $this->assertNotNull($node->else);
        $this->assertInstanceOf(LiteralNode::class, $node->else);
    }

    public function testParsesCaseExpressionWithoutElse(): void
    {
        /** @var CaseExpressionNode $node */
        $node = $this->fieldOf('SELECT CASE WHEN x > 1 THEN "big" END FROM t');
        $this->assertInstanceOf(CaseExpressionNode::class, $node);
        $this->assertNull($node->else);
    }

    public function testParsesCaseExpressionWithMultipleBranches(): void
    {
        /** @var CaseExpressionNode $node */
        $node = $this->fieldOf(
            'SELECT CASE WHEN x = 1 THEN "one" WHEN x = 2 THEN "two" ELSE "other" END FROM t'
        );
        $this->assertInstanceOf(CaseExpressionNode::class, $node);
        $this->assertCount(2, $node->branches);
    }

    public function testParsesIfAsFunctionCallWithConditionArgument(): void
    {
        $node = $this->fieldOf('SELECT IF(age > 18, "adult", "minor") FROM x');
        $this->assertInstanceOf(FunctionCallNode::class, $node);
        $this->assertSame('IF', $node->name);
        $this->assertCount(3, $node->arguments);
    }

    public function testSoftKeywordIdentifierPromotion(): void
    {
        // `desc` is normally KEYWORD_DESC but in an expression slot it's a column name.
        $node = $this->fieldOf('SELECT MATCH(name, desc) AGAINST("w IN NATURAL MODE") FROM x');
        $this->assertInstanceOf(MatchAgainstNode::class, $node);
        $this->assertSame('desc', $node->fields[1]->name);

        // ASC/ALL/LEFT/RIGHT/FULL/INNER/OUTER/ANALYZE are all promotable in expression context.
        $softKeywords = ['asc', 'desc', 'all', 'outer', 'analyze', 'inner', 'left', 'right', 'full'];
        foreach ($softKeywords as $kw) {
            $node = $this->fieldOf(sprintf('SELECT MATCH(a, %s) AGAINST("q IN NATURAL MODE") FROM x', $kw));
            $this->assertInstanceOf(MatchAgainstNode::class, $node);
            $this->assertSame($kw, $node->fields[1]->name);
        }
    }

    public function testPrimaryRejectsUnexpectedToken(): void
    {
        $parser = $this->makeParser();
        $stream = new TokenStream([
            new \FQL\Sql\Token\Token(
                TokenType::KEYWORD_SELECT,
                'SELECT',
                'SELECT',
                new \FQL\Sql\Token\Position(0, 1, 1),
                6
            ),
        ]);
        $this->expectException(ParseException::class);
        $parser->parsePrimary($stream);
    }
}
