<?php

namespace SQL\Token;

use FQL\Sql\Token\Position;
use FQL\Sql\Token\Token;
use FQL\Sql\Token\TokenType;
use PHPUnit\Framework\TestCase;

class TokenTest extends TestCase
{
    private function token(TokenType $type, string $value = 'x', string $raw = 'x'): Token
    {
        return new Token($type, $value, $raw, new Position(0, 1, 1), strlen($raw));
    }

    public function testIsMatchesOnlyExactType(): void
    {
        $token = $this->token(TokenType::IDENTIFIER);
        $this->assertTrue($token->is(TokenType::IDENTIFIER));
        $this->assertFalse($token->is(TokenType::KEYWORD_SELECT));
    }

    public function testIsAnyOfMatchesAnyGivenType(): void
    {
        $token = $this->token(TokenType::KEYWORD_FROM);
        $this->assertTrue($token->isAnyOf(TokenType::KEYWORD_SELECT, TokenType::KEYWORD_FROM));
        $this->assertFalse($token->isAnyOf(TokenType::KEYWORD_WHERE, TokenType::KEYWORD_ORDER));
    }

    public function testIsAnyOfReturnsFalseForEmptyList(): void
    {
        $token = $this->token(TokenType::KEYWORD_SELECT);
        $this->assertFalse($token->isAnyOf());
    }

    public function testStringRepresentationContainsTypePositionAndRaw(): void
    {
        $token = new Token(
            TokenType::IDENTIFIER,
            'col',
            'col',
            new Position(10, 2, 4),
            3
        );
        $output = (string) $token;
        $this->assertStringContainsString('IDENTIFIER', $output);
        $this->assertStringContainsString('col', $output);
        $this->assertStringContainsString('line 2', $output);
        $this->assertStringContainsString('column 4', $output);
    }

    public function testPositionStringIsHumanFriendly(): void
    {
        $position = new Position(0, 3, 7);
        $this->assertSame('line 3, column 7', (string) $position);
    }

    public function testTokenTypeIsKeywordDetectsAllKeywords(): void
    {
        $this->assertTrue(TokenType::KEYWORD_SELECT->isKeyword());
        $this->assertTrue(TokenType::KEYWORD_FROM->isKeyword());
        $this->assertTrue(TokenType::KEYWORD_AND->isKeyword());
        $this->assertFalse(TokenType::IDENTIFIER->isKeyword());
        $this->assertFalse(TokenType::STRING_LITERAL->isKeyword());
        $this->assertFalse(TokenType::OP_EQ->isKeyword());
    }

    public function testTokenTypeIsLiteralDetectsAllLiteralKinds(): void
    {
        $this->assertTrue(TokenType::STRING_LITERAL->isLiteral());
        $this->assertTrue(TokenType::NUMBER_LITERAL->isLiteral());
        $this->assertTrue(TokenType::BOOLEAN_LITERAL->isLiteral());
        $this->assertTrue(TokenType::NULL_LITERAL->isLiteral());
        $this->assertFalse(TokenType::IDENTIFIER->isLiteral());
        $this->assertFalse(TokenType::KEYWORD_SELECT->isLiteral());
    }

    public function testTokenTypeIsOperatorDetectsOnlyOpPrefix(): void
    {
        $this->assertTrue(TokenType::OP_EQ->isOperator());
        $this->assertTrue(TokenType::OP_LTE->isOperator());
        $this->assertTrue(TokenType::OP_NEQ_STRICT->isOperator());
        $this->assertFalse(TokenType::KEYWORD_LIKE->isOperator());
        $this->assertFalse(TokenType::IDENTIFIER->isOperator());
    }

    public function testTokenTypeIsTriviaDetectsWhitespaceAndComments(): void
    {
        $this->assertTrue(TokenType::WHITESPACE->isTrivia());
        $this->assertTrue(TokenType::COMMENT_LINE->isTrivia());
        $this->assertTrue(TokenType::COMMENT_BLOCK->isTrivia());
        $this->assertFalse(TokenType::EOF->isTrivia());
        $this->assertFalse(TokenType::IDENTIFIER->isTrivia());
    }

    public function testMetadataIsPreserved(): void
    {
        $metadata = ['source' => 'test'];
        $token = new Token(
            TokenType::FILE_QUERY,
            'json(x.json)',
            'json(x.json)',
            new Position(0, 1, 1),
            12,
            $metadata
        );
        $this->assertSame($metadata, $token->metadata);
    }
}
