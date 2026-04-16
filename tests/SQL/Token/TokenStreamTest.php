<?php

namespace SQL\Token;

use FQL\Sql\Parser\ParseException;
use FQL\Sql\Token\Tokenizer;
use FQL\Sql\Token\TokenStream;
use FQL\Sql\Token\TokenType;
use PHPUnit\Framework\TestCase;

class TokenStreamTest extends TestCase
{
    private function streamOf(string $sql, bool $includeTrivia = false): TokenStream
    {
        $tokens = (new Tokenizer())->tokenize($sql);
        return new TokenStream($tokens, $includeTrivia);
    }

    public function testSkipsTriviaByDefault(): void
    {
        $stream = $this->streamOf("SELECT  id\n  FROM x");
        // Without trivia, expected first 4 non-trivia tokens are SELECT, id, FROM, FILE_QUERY.
        $this->assertSame(TokenType::KEYWORD_SELECT, $stream->consume()->type);
        $this->assertSame(TokenType::IDENTIFIER, $stream->consume()->type);
        $this->assertSame(TokenType::KEYWORD_FROM, $stream->consume()->type);
        $this->assertSame(TokenType::FILE_QUERY, $stream->consume()->type);
        $this->assertSame(TokenType::EOF, $stream->consume()->type);
    }

    public function testIncludesTriviaWhenRequested(): void
    {
        $stream = $this->streamOf('SELECT id', includeTrivia: true);
        $types = [];
        foreach ($stream as $token) {
            $types[] = $token->type;
        }
        $this->assertContains(TokenType::WHITESPACE, $types);
    }

    public function testPeekDoesNotAdvance(): void
    {
        $stream = $this->streamOf('SELECT id');
        $this->assertSame(TokenType::KEYWORD_SELECT, $stream->peek()->type);
        $this->assertSame(TokenType::KEYWORD_SELECT, $stream->peek()->type);
        $this->assertSame(TokenType::KEYWORD_SELECT, $stream->consume()->type);
    }

    public function testPeekWithOffset(): void
    {
        $stream = $this->streamOf('SELECT id FROM x');
        $this->assertSame(TokenType::KEYWORD_SELECT, $stream->peek(0)->type);
        $this->assertSame(TokenType::IDENTIFIER, $stream->peek(1)->type);
        $this->assertSame(TokenType::KEYWORD_FROM, $stream->peek(2)->type);
    }

    public function testConsumeIfMatchesReturnsTokenAndAdvances(): void
    {
        $stream = $this->streamOf('SELECT id');
        $token = $stream->consumeIf(TokenType::KEYWORD_SELECT, TokenType::KEYWORD_DESCRIBE);
        $this->assertNotNull($token);
        $this->assertSame(TokenType::KEYWORD_SELECT, $token->type);
        $this->assertSame(TokenType::IDENTIFIER, $stream->peek()->type);
    }

    public function testConsumeIfMissReturnsNullAndDoesNotAdvance(): void
    {
        $stream = $this->streamOf('SELECT id');
        $token = $stream->consumeIf(TokenType::KEYWORD_DESCRIBE);
        $this->assertNull($token);
        $this->assertSame(TokenType::KEYWORD_SELECT, $stream->peek()->type);
    }

    public function testExpectThrowsParseExceptionOnMismatch(): void
    {
        $stream = $this->streamOf('SELECT id');
        $this->expectException(ParseException::class);
        $stream->expect(TokenType::KEYWORD_DESCRIBE);
    }

    public function testMarkAndRewindToAllowSpeculativeParsing(): void
    {
        $stream = $this->streamOf('SELECT id FROM x');
        $marker = $stream->mark();
        $stream->consume();
        $stream->consume();
        $stream->rewindTo($marker);
        $this->assertSame(TokenType::KEYWORD_SELECT, $stream->peek()->type);
    }

    public function testIsAtEndReturnsTrueAfterEof(): void
    {
        $stream = $this->streamOf('SELECT id');
        while (!$stream->isAtEnd()) {
            $stream->consume();
        }
        $this->assertTrue($stream->isAtEnd());
        // Consuming at EOF returns the EOF token without advancing.
        $this->assertSame(TokenType::EOF, $stream->consume()->type);
        $this->assertTrue($stream->isAtEnd());
    }

    public function testIteratorYieldsAllTokensInOrder(): void
    {
        $stream = $this->streamOf('SELECT id');
        $collected = [];
        foreach ($stream as $token) {
            $collected[] = $token->type;
        }
        // Should include EOF as the final element.
        $this->assertSame(TokenType::EOF, end($collected));
    }
}
