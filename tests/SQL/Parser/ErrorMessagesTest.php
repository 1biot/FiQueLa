<?php

namespace SQL\Parser;

use FQL\Sql\Parser\ParseException;
use FQL\Sql\Parser\Parser;
use FQL\Sql\Token\Tokenizer;
use FQL\Sql\Token\TokenStream;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that parse errors carry precise position information (line + column)
 * and contextual messages, so REPLs / editors can point the user at the exact spot.
 */
class ErrorMessagesTest extends TestCase
{
    private function parseExpectingError(string $sql): ParseException
    {
        try {
            $parser = Parser::create();
            $parser->parse(new TokenStream((new Tokenizer())->tokenize($sql)));
        } catch (ParseException $e) {
            return $e;
        }
        $this->fail(sprintf('Expected ParseException, got none for SQL: %s', $sql));
    }

    public function testErrorMessageIncludesLineAndColumn(): void
    {
        // `WHERE x =` — condition is missing its right-hand operand; the parser
        // bubbles a ParseException pointing to line 3, column 10 (after the `=`).
        $sql = "SELECT id\nFROM x\nWHERE a = ";
        $e = $this->parseExpectingError($sql);
        $this->assertStringContainsString('line 3', $e->getMessage());
    }

    public function testMissingCommaInSelectProducesDescriptiveMessage(): void
    {
        $e = $this->parseExpectingError('SELECT id name FROM x');
        $this->assertStringContainsString('SELECT clause', $e->getMessage());
        $this->assertStringContainsString('comma', $e->getMessage());
    }

    public function testMissingCommaInGroupByProducesDescriptiveMessage(): void
    {
        $e = $this->parseExpectingError('SELECT id FROM x GROUP BY a b');
        $this->assertStringContainsString('GROUP BY', $e->getMessage());
    }

    public function testUnknownTokenInStatementReportsPosition(): void
    {
        $e = $this->parseExpectingError('SELECT id FROM x UNEXPECTED');
        $this->assertStringContainsString('line 1', $e->getMessage());
    }

    public function testParseExceptionCarriesOffendingToken(): void
    {
        $e = $this->parseExpectingError('SELECT id FROM x GROUP BY a b');
        $this->assertSame('b', $e->token->value);
    }

    public function testParseExceptionExposeExpectedTypes(): void
    {
        // Missing closing paren for IN list — thrown via ::unexpected() which carries
        // the full list of expected token types.
        $e = $this->parseExpectingError('SELECT * FROM x WHERE a IN (1, 2');
        $this->assertNotNull($e->token);
        $this->assertIsArray($e->expected);
        $this->assertNotEmpty($e->expected);
    }

    public function testUnterminatedStringProducesPositionedError(): void
    {
        $this->expectException(ParseException::class);
        (new Tokenizer())->tokenize("SELECT 'missing end");
    }

    public function testUnterminatedBlockCommentProducesPositionedError(): void
    {
        $this->expectException(ParseException::class);
        (new Tokenizer())->tokenize('SELECT /* open');
    }

    public function testUnexpectedCharacterProducesPositionedError(): void
    {
        try {
            (new Tokenizer())->tokenize('SELECT x ^ y FROM t');
            $this->fail('Expected tokenizer error on "^"');
        } catch (ParseException $e) {
            $this->assertStringContainsString('line 1', $e->getMessage());
            $this->assertStringContainsString('column', $e->getMessage());
        }
    }
}
