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

    public function testParseSingleConditionRejectsBoolean(): void
    {
        $lexer = new SqlLexer();
        $lexer->tokenize('flag = true');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('For compare NULL or BOOLEAN value, use IS or IS NOT operator');

        $lexer->parseSingleCondition();
    }
}
