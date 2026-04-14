<?php

namespace SQL;

use FQL\Enum\Operator;
use FQL\Enum\Type;
use FQL\Exception\UnexpectedValueException;
use FQL\Sql\SqlLexer;
use PHPUnit\Framework\TestCase;

class SqlLexerTest extends TestCase
{
    public function testTokenizeRemovesComments(): void
    {
        $sql = "SELECT name -- comment\nFROM data # other\nWHERE age > 1 /* block */";
        $lexer = new SqlLexer();
        $tokens = $lexer->tokenize($sql);

        $this->assertContains('SELECT', $tokens);
        $this->assertContains('FROM', $tokens);
        $this->assertContains('WHERE', $tokens);
        $this->assertNotContains('comment', $tokens);
        $this->assertNotContains('other', $tokens);
        $this->assertNotContains('block', $tokens);
    }

    public function testTokenizeJoinMarkers(): void
    {
        $sql = 'SELECT * FROM data JOIN other AS o ON data.id = o.id';
        $lexer = new SqlLexer();
        $tokens = $lexer->tokenize($sql);

        $this->assertContains('JOIN', $tokens);
        $this->assertContains('AS', $tokens);
        $this->assertContains('ON', $tokens);
    }

    public function testTokenizeIntoSource(): void
    {
        $sql = 'SELECT name FROM json(products.json).data.products INTO csv(output.csv)';
        $lexer = new SqlLexer();
        $tokens = $lexer->tokenize($sql);

        $this->assertContains('INTO', $tokens);
        $this->assertContains('csv(output.csv)', $tokens);
    }

    public function testParseSingleConditionEquality(): void
    {
        $lexer = new SqlLexer();
        $lexer->tokenize('name = 10');

        [$field, $operator, $value] = $lexer->parseSingleCondition();
        $this->assertSame('name', $field);
        $this->assertSame(Operator::EQUAL, $operator);
        $this->assertSame(10, $value);
    }

    public function testParseSingleConditionIn(): void
    {
        $lexer = new SqlLexer();
        $lexer->tokenize('name IN ("a", "b")');

        [$field, $operator, $value] = $lexer->parseSingleCondition();
        $this->assertSame('name', $field);
        $this->assertSame(Operator::IN, $operator);
        $this->assertSame(['a', 'b'], $value);
    }

    public function testParseSingleConditionBetween(): void
    {
        $lexer = new SqlLexer();
        $lexer->tokenize('price BETWEEN 1 AND 5');

        [$field, $operator, $value] = $lexer->parseSingleCondition();
        $this->assertSame('price', $field);
        $this->assertSame(Operator::BETWEEN, $operator);
        $this->assertSame(['1', '5'], $value);
    }

    public function testParseSingleConditionIsNull(): void
    {
        $lexer = new SqlLexer();
        $lexer->tokenize('name IS NULL');

        [$field, $operator, $value] = $lexer->parseSingleCondition();
        $this->assertSame('name', $field);
        $this->assertSame(Operator::IS, $operator);
        $this->assertSame(Type::NULL, $value);
    }

    public function testParseSingleConditionRegexp(): void
    {
        $lexer = new SqlLexer();
        $lexer->tokenize('name REGEXP "^Product [A-B]$"');

        [$field, $operator, $value] = $lexer->parseSingleCondition();
        $this->assertSame('name', $field);
        $this->assertSame(Operator::REGEXP, $operator);
        $this->assertSame('^Product [A-B]$', $value);
    }

    public function testParseSingleConditionNotRegexp(): void
    {
        $lexer = new SqlLexer();
        $lexer->tokenize('name NOT REGEXP "^Product [A-B]$"');

        [$field, $operator, $value] = $lexer->parseSingleCondition();
        $this->assertSame('name', $field);
        $this->assertSame(Operator::NOT_REGEXP, $operator);
        $this->assertSame('^Product [A-B]$', $value);
    }

    public function testParseSingleConditionRejectsBoolean(): void
    {
        $lexer = new SqlLexer();
        $lexer->tokenize('flag = true');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('For compare NULL or BOOLEAN value, use IS or IS NOT operator');

        $lexer->parseSingleCondition();
    }

    public function testCommaIsToken(): void
    {
        $lexer = new SqlLexer();
        $tokens = $lexer->tokenize('SELECT id, name, price FROM data');

        $this->assertContains(',', $tokens);
        $this->assertSame(['SELECT', 'id', ',', 'name', ',', 'price', 'FROM', 'data'], $tokens);
    }

    public function testCommaInsideFunctionIsPreserved(): void
    {
        $lexer = new SqlLexer();
        $tokens = $lexer->tokenize('SELECT ROUND(price, 2) FROM data');

        // Function is one token, internal comma is part of the function string
        $this->assertContains('ROUND(price, 2)', $tokens);
        // Only the function token, no stray comma tokens from inside
        $this->assertSame(['SELECT', 'ROUND(price, 2)', 'FROM', 'data'], $tokens);
    }
}
