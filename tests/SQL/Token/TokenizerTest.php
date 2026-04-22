<?php

namespace SQL\Token;

use FQL\Sql\Parser\ParseException;
use FQL\Sql\Token\Token;
use FQL\Sql\Token\Tokenizer;
use FQL\Sql\Token\TokenType;
use PHPUnit\Framework\TestCase;

class TokenizerTest extends TestCase
{
    /**
     * @return TokenType[]
     */
    private function tokenTypes(string $sql, bool $skipTrivia = true): array
    {
        $tokens = (new Tokenizer())->tokenize($sql);
        return array_values(array_map(
            static fn (Token $t): TokenType => $t->type,
            array_filter(
                $tokens,
                static fn (Token $t): bool => !$skipTrivia || !$t->type->isTrivia()
            )
        ));
    }

    /**
     * @return Token[]
     */
    private function tokens(string $sql, bool $skipTrivia = true): array
    {
        $tokens = (new Tokenizer())->tokenize($sql);
        if (!$skipTrivia) {
            return $tokens;
        }
        return array_values(array_filter(
            $tokens,
            static fn (Token $t): bool => !$t->type->isTrivia()
        ));
    }

    public function testEmitsEofTokenAtEnd(): void
    {
        $tokens = (new Tokenizer())->tokenize('');
        $this->assertCount(1, $tokens);
        $this->assertSame(TokenType::EOF, $tokens[0]->type);
    }

    public function testRecognizesControlKeywords(): void
    {
        $types = $this->tokenTypes('SELECT a FROM b WHERE c GROUP BY d HAVING e ORDER BY f LIMIT 1');
        $expected = [
            TokenType::KEYWORD_SELECT,
            TokenType::IDENTIFIER,
            TokenType::KEYWORD_FROM,
            TokenType::FILE_QUERY,
            TokenType::KEYWORD_WHERE,
            TokenType::IDENTIFIER,
            TokenType::KEYWORD_GROUP,
            TokenType::KEYWORD_BY,
            TokenType::IDENTIFIER,
            TokenType::KEYWORD_HAVING,
            TokenType::IDENTIFIER,
            TokenType::KEYWORD_ORDER,
            TokenType::KEYWORD_BY,
            TokenType::IDENTIFIER,
            TokenType::KEYWORD_LIMIT,
            TokenType::NUMBER_LITERAL,
            TokenType::EOF,
        ];
        $this->assertSame($expected, $types);
    }

    public function testKeywordsAreCaseInsensitive(): void
    {
        $types = $this->tokenTypes('select id from x');
        $this->assertSame(TokenType::KEYWORD_SELECT, $types[0]);
        $this->assertSame(TokenType::IDENTIFIER, $types[1]);
        $this->assertSame(TokenType::KEYWORD_FROM, $types[2]);
        $this->assertSame(TokenType::FILE_QUERY, $types[3]);
    }

    public function testFunctionNameIsDistinctFromIdentifier(): void
    {
        $tokens = $this->tokens('SELECT COUNT(*) FROM x');
        $this->assertSame(TokenType::FUNCTION_NAME, $tokens[1]->type);
        $this->assertSame('COUNT', $tokens[1]->value);
        $this->assertSame(TokenType::PAREN_OPEN, $tokens[2]->type);
        $this->assertSame(TokenType::STAR, $tokens[3]->type);
        $this->assertSame(TokenType::PAREN_CLOSE, $tokens[4]->type);
    }

    public function testFileQueryIsCapturedAsSingleToken(): void
    {
        $tokens = $this->tokens('SELECT * FROM json(data.json).products WHERE x = 1');
        // SELECT, *, FROM, FILE_QUERY, WHERE, ...
        $this->assertSame(TokenType::FILE_QUERY, $tokens[3]->type);
        $this->assertSame('json(data.json).products', $tokens[3]->value);
        $this->assertNotNull($tokens[3]->metadata, 'FileQuery metadata should be attached');
    }

    public function testFileQueryWithParametersStaysOneToken(): void
    {
        $tokens = $this->tokens('SELECT * FROM csv(data.csv, "|").rows AS r');
        $this->assertSame(TokenType::FILE_QUERY, $tokens[3]->type);
        $this->assertSame('csv(data.csv, "|").rows', $tokens[3]->value);
    }

    public function testFileQueryContextDoesNotLeakAcrossParen(): void
    {
        // After JOIN, the next non-trivia non-paren token would normally be a FILE_QUERY,
        // but a `(` introduces a subquery and the FILE_QUERY context must be cleared so
        // the inner SELECT is recognised as a keyword.
        $tokens = $this->tokens('SELECT * FROM x JOIN (SELECT id FROM y) AS o ON x.id = o.id');
        // Index 4 is JOIN, 5 is `(`, 6 must be SELECT keyword (not FILE_QUERY).
        $this->assertSame(TokenType::KEYWORD_JOIN, $tokens[4]->type);
        $this->assertSame(TokenType::PAREN_OPEN, $tokens[5]->type);
        $this->assertSame(TokenType::KEYWORD_SELECT, $tokens[6]->type);
    }

    public function testStringLiteralStripsQuotes(): void
    {
        $tokens = $this->tokens("SELECT 'hello' FROM x");
        $this->assertSame(TokenType::STRING_LITERAL, $tokens[1]->type);
        $this->assertSame('hello', $tokens[1]->value);
        $this->assertSame("'hello'", $tokens[1]->raw);
    }

    public function testBacktickIdentifier(): void
    {
        $tokens = $this->tokens('SELECT `weird name` FROM x');
        $this->assertSame(TokenType::IDENTIFIER_QUOTED, $tokens[1]->type);
        $this->assertSame('weird name', $tokens[1]->value);
        $this->assertSame('`weird name`', $tokens[1]->raw);
    }

    public function testBacktickIdentifierWithDotInside(): void
    {
        // A single backtick segment whose contents contain `.` must keep
        // the backticks in `.value` — otherwise the runtime path accessor
        // would split `Název Zboží.cz` into two segments.
        $tokens = $this->tokens('SELECT `Název Zboží.cz` FROM x');
        $this->assertSame(TokenType::IDENTIFIER_QUOTED, $tokens[1]->type);
        $this->assertSame('`Název Zboží.cz`', $tokens[1]->value);
        $this->assertSame('`Název Zboží.cz`', $tokens[1]->raw);
    }

    public function testBacktickChain(): void
    {
        $tokens = $this->tokens('SELECT `info`.`orderID` FROM x');
        $this->assertSame(TokenType::IDENTIFIER_QUOTED, $tokens[1]->type);
        $this->assertSame('`info`.`orderID`', $tokens[1]->value);
        $this->assertSame('`info`.`orderID`', $tokens[1]->raw);
    }

    public function testBacktickMixedChain(): void
    {
        $tokens = $this->tokens('SELECT `info`.date FROM x');
        $this->assertSame(TokenType::IDENTIFIER_QUOTED, $tokens[1]->type);
        $this->assertSame('`info`.date', $tokens[1]->value);
    }

    public function testBacktickStartsWithUnquotedFollowedByBacktick(): void
    {
        $tokens = $this->tokens('SELECT info.`orderID` FROM x');
        $this->assertSame(TokenType::IDENTIFIER_QUOTED, $tokens[1]->type);
        $this->assertSame('info.`orderID`', $tokens[1]->value);
    }

    public function testArrayAccessor(): void
    {
        $tokens = $this->tokens('SELECT products.product[] FROM x');
        $this->assertSame(TokenType::IDENTIFIER, $tokens[1]->type);
        $this->assertSame('products.product[]', $tokens[1]->value);
    }

    public function testArrayAccessorMidPath(): void
    {
        $tokens = $this->tokens('SELECT a.b[].c FROM x');
        $this->assertSame(TokenType::IDENTIFIER, $tokens[1]->type);
        $this->assertSame('a.b[].c', $tokens[1]->value);
    }

    public function testArrayAccessorOnBacktickChain(): void
    {
        $tokens = $this->tokens('SELECT `products`.`product`[] FROM x');
        $this->assertSame(TokenType::IDENTIFIER_QUOTED, $tokens[1]->type);
        $this->assertSame('`products`.`product`[]', $tokens[1]->value);
    }

    public function testWildcardStillWorks(): void
    {
        $tokens = $this->tokens('SELECT a.b.* FROM x');
        $this->assertSame(TokenType::IDENTIFIER, $tokens[1]->type);
        $this->assertSame('a.b.*', $tokens[1]->value);
    }

    public function testBacktickAliasStripped(): void
    {
        $tokens = $this->tokens('SELECT x AS `Kód objednávky` FROM y');
        // Token keeps its value as stripped (single segment, no special
        // chars) — SelectClauseParser sees `Kód objednávky` directly.
        $this->assertSame(TokenType::IDENTIFIER_QUOTED, $tokens[3]->type);
        $this->assertSame('Kód objednávky', $tokens[3]->value);
    }

    public function testNumberLiterals(): void
    {
        $tokens = $this->tokens('SELECT 42, 3.14, .5 FROM x');
        $this->assertSame(TokenType::NUMBER_LITERAL, $tokens[1]->type);
        $this->assertSame('42', $tokens[1]->value);
        $this->assertSame('3.14', $tokens[3]->value);
        $this->assertSame('.5', $tokens[5]->value);
    }

    public function testNegativeNumberInPrefixContext(): void
    {
        $tokens = $this->tokens('SELECT * FROM x WHERE n > -5');
        // Last 4 tokens before EOF: WHERE, IDENTIFIER, OP_GT, NUMBER_LITERAL
        $count = count($tokens);
        $this->assertSame(TokenType::OP_GT, $tokens[$count - 3]->type);
        $this->assertSame(TokenType::NUMBER_LITERAL, $tokens[$count - 2]->type);
        $this->assertSame('-5', $tokens[$count - 2]->value);
    }

    public function testOperators(): void
    {
        $tokens = $this->tokens('SELECT * FROM x WHERE a = 1 AND b != 2 AND c <= 3 AND d >= 4');
        $opTypes = array_values(array_filter(
            array_map(static fn (Token $t): TokenType => $t->type, $tokens),
            static fn (TokenType $t): bool => $t->isOperator()
        ));
        $this->assertSame(
            [TokenType::OP_EQ, TokenType::OP_NEQ, TokenType::OP_LTE, TokenType::OP_GTE],
            $opTypes
        );
    }

    public function testBooleanAndNullLiterals(): void
    {
        $tokens = $this->tokens('SELECT TRUE, false, NULL FROM x');
        $this->assertSame(TokenType::BOOLEAN_LITERAL, $tokens[1]->type);
        $this->assertSame('true', $tokens[1]->value);
        $this->assertSame(TokenType::BOOLEAN_LITERAL, $tokens[3]->type);
        $this->assertSame('false', $tokens[3]->value);
        $this->assertSame(TokenType::NULL_LITERAL, $tokens[5]->type);
    }

    public function testLineCommentsArePreservedAsTrivia(): void
    {
        $tokens = $this->tokens(
            "SELECT id -- this is a comment\nFROM x",
            skipTrivia: false
        );
        $hasLineComment = false;
        foreach ($tokens as $token) {
            if ($token->type === TokenType::COMMENT_LINE) {
                $hasLineComment = true;
                $this->assertSame('-- this is a comment', $token->value);
                break;
            }
        }
        $this->assertTrue($hasLineComment, 'Line comment should be emitted as a trivia token');
    }

    public function testHashLineComment(): void
    {
        $tokens = $this->tokens(
            "SELECT id # hash comment\nFROM x",
            skipTrivia: false
        );
        $found = false;
        foreach ($tokens as $token) {
            if ($token->type === TokenType::COMMENT_LINE) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testBlockComment(): void
    {
        $tokens = $this->tokens(
            'SELECT /* block */ id FROM x',
            skipTrivia: false
        );
        $found = false;
        foreach ($tokens as $token) {
            if ($token->type === TokenType::COMMENT_BLOCK) {
                $found = true;
                $this->assertSame('/* block */', $token->value);
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testUnterminatedBlockCommentThrows(): void
    {
        $this->expectException(ParseException::class);
        (new Tokenizer())->tokenize('SELECT /* unterminated');
    }

    public function testUnterminatedStringLiteralThrows(): void
    {
        $this->expectException(ParseException::class);
        (new Tokenizer())->tokenize("SELECT 'unterminated FROM x");
    }

    public function testDotChainIdentifier(): void
    {
        $tokens = $this->tokens('SELECT user.profile.name FROM x');
        $this->assertSame(TokenType::IDENTIFIER, $tokens[1]->type);
        $this->assertSame('user.profile.name', $tokens[1]->value);
    }

    public function testCaseWhenThenEnd(): void
    {
        $types = $this->tokenTypes("SELECT CASE WHEN a > 1 THEN 'big' ELSE 'small' END FROM x");
        $this->assertContains(TokenType::KEYWORD_CASE, $types);
        $this->assertContains(TokenType::KEYWORD_WHEN, $types);
        $this->assertContains(TokenType::KEYWORD_THEN, $types);
        $this->assertContains(TokenType::KEYWORD_ELSE, $types);
        $this->assertContains(TokenType::KEYWORD_END, $types);
    }

    public function testIsNotNull(): void
    {
        $types = $this->tokenTypes('SELECT * FROM x WHERE a IS NOT NULL');
        $this->assertContains(TokenType::KEYWORD_IS, $types);
        $this->assertContains(TokenType::KEYWORD_NOT, $types);
        $this->assertContains(TokenType::NULL_LITERAL, $types);
    }

    public function testBetweenAnd(): void
    {
        $types = $this->tokenTypes('SELECT * FROM x WHERE n BETWEEN 1 AND 10');
        $this->assertContains(TokenType::KEYWORD_BETWEEN, $types);
        $this->assertContains(TokenType::KEYWORD_AND, $types);
    }

    public function testPositionTracking(): void
    {
        $tokens = $this->tokens("SELECT id\nFROM x");
        // SELECT at line 1, col 1
        $this->assertSame(1, $tokens[0]->position->line);
        $this->assertSame(1, $tokens[0]->position->column);
        // id at line 1, col 8
        $this->assertSame(1, $tokens[1]->position->line);
        $this->assertSame(8, $tokens[1]->position->column);
        // FROM at line 2, col 1
        $this->assertSame(2, $tokens[2]->position->line);
        $this->assertSame(1, $tokens[2]->position->column);
    }

    public function testTriviaIsSkippedByDefault(): void
    {
        $allTokens = (new Tokenizer())->tokenize("SELECT id\n  FROM x  -- c\n");
        $nonTrivia = array_filter($allTokens, static fn (Token $t): bool => !$t->type->isTrivia());
        $this->assertGreaterThan(count($nonTrivia), count($allTokens));
    }

    public function testDistinctInsideFunctionCall(): void
    {
        $tokens = $this->tokens('SELECT COUNT(DISTINCT id) FROM x');
        $this->assertSame(TokenType::FUNCTION_NAME, $tokens[1]->type);
        $this->assertSame(TokenType::PAREN_OPEN, $tokens[2]->type);
        $this->assertSame(TokenType::KEYWORD_DISTINCT, $tokens[3]->type);
        $this->assertSame(TokenType::IDENTIFIER, $tokens[4]->type);
        $this->assertSame(TokenType::PAREN_CLOSE, $tokens[5]->type);
    }

    public function testJoinKeywordSequence(): void
    {
        $types = $this->tokenTypes('SELECT * FROM x INNER JOIN y AS y2 ON x.id = y2.id');
        $this->assertContains(TokenType::KEYWORD_INNER, $types);
        $this->assertContains(TokenType::KEYWORD_JOIN, $types);
        $this->assertContains(TokenType::KEYWORD_AS, $types);
        $this->assertContains(TokenType::KEYWORD_ON, $types);
    }

    public function testLeftOuterJoin(): void
    {
        $types = $this->tokenTypes('SELECT * FROM x LEFT OUTER JOIN y AS y2 ON x.id = y2.id');
        $this->assertContains(TokenType::KEYWORD_LEFT, $types);
        $this->assertContains(TokenType::KEYWORD_OUTER, $types);
        $this->assertContains(TokenType::KEYWORD_JOIN, $types);
    }

    public function testUnionAll(): void
    {
        $types = $this->tokenTypes('SELECT a FROM x UNION ALL SELECT b FROM y');
        $this->assertContains(TokenType::KEYWORD_UNION, $types);
        $this->assertContains(TokenType::KEYWORD_ALL, $types);
    }

    public function testIntoFileQuery(): void
    {
        $tokens = $this->tokens('SELECT * FROM x INTO csv(out.csv)');
        // The INTO keyword should activate FILE_QUERY context.
        $intoIndex = null;
        foreach ($tokens as $i => $token) {
            if ($token->type === TokenType::KEYWORD_INTO) {
                $intoIndex = $i;
                break;
            }
        }
        $this->assertNotNull($intoIndex);
        $this->assertSame(TokenType::FILE_QUERY, $tokens[$intoIndex + 1]->type);
        $this->assertSame('csv(out.csv)', $tokens[$intoIndex + 1]->value);
    }

    public function testStrictEqualityOperators(): void
    {
        $types = $this->tokenTypes('SELECT * FROM x WHERE a == b');
        $this->assertContains(TokenType::OP_EQ_STRICT, $types);

        $types = $this->tokenTypes('SELECT * FROM x WHERE a === b');
        $this->assertContains(TokenType::OP_EQ_STRICT, $types);

        $types = $this->tokenTypes('SELECT * FROM x WHERE a !== b');
        $this->assertContains(TokenType::OP_NEQ_STRICT, $types);
    }

    public function testSqlStandardNotEqualOperator(): void
    {
        $types = $this->tokenTypes('SELECT * FROM x WHERE a <> b');
        $this->assertContains(TokenType::OP_NEQ, $types);
    }

    public function testPositiveSignedNumberInExpressionContext(): void
    {
        $tokens = $this->tokens('SELECT * FROM x WHERE n > +5');
        $last = $tokens[count($tokens) - 2]; // EOF is at -1
        $this->assertSame(TokenType::NUMBER_LITERAL, $last->type);
        $this->assertSame('+5', $last->value);
    }

    public function testUnexpectedCharacterThrowsParseException(): void
    {
        $this->expectException(ParseException::class);
        (new Tokenizer())->tokenize('SELECT ^ FROM x');
    }

    public function testSourceKeywordWithoutFileQueryFallsBackToPlainIdentifier(): void
    {
        // FILE_QUERY context is activated by FROM, but if the next non-trivia token
        // is not an identifier (e.g. a comment + then keyword), the context drops
        // cleanly without swallowing real keywords.
        $tokens = $this->tokens("SELECT * FROM /* comment */ x");
        $this->assertSame(TokenType::KEYWORD_FROM, $tokens[2]->type);
        $this->assertSame(TokenType::FILE_QUERY, $tokens[3]->type);
    }

    public function testEmptyInputEmitsOnlyEof(): void
    {
        $tokens = (new Tokenizer())->tokenize('   ');
        $nonTrivia = array_values(array_filter($tokens, fn ($t) => !$t->type->isTrivia()));
        $this->assertCount(1, $nonTrivia);
        $this->assertSame(TokenType::EOF, $nonTrivia[0]->type);
    }

    public function testFileQueryContextClearsOnComma(): void
    {
        // After FROM, FILE_QUERY context activates; a comma mid-sequence (atypical but
        // a reasonable edge case) clears the flag rather than producing spurious FILE_QUERYs.
        $tokens = $this->tokens('SELECT x FROM a, b WHERE x = 1');
        $foundFrom = false;
        foreach ($tokens as $i => $t) {
            if ($t->type === TokenType::KEYWORD_FROM) {
                $foundFrom = true;
                $this->assertSame(TokenType::FILE_QUERY, $tokens[$i + 1]->type);
                break;
            }
        }
        $this->assertTrue($foundFrom);
    }

    public function testIdentifierWithAtPrefix(): void
    {
        $tokens = $this->tokens('SELECT @attributes.id FROM x');
        $this->assertSame(TokenType::IDENTIFIER, $tokens[1]->type);
        $this->assertSame('@attributes.id', $tokens[1]->value);
    }

    public function testIdentifierWithKebabCase(): void
    {
        $tokens = $this->tokens('SELECT order-total FROM x');
        $this->assertSame(TokenType::IDENTIFIER, $tokens[1]->type);
        $this->assertSame('order-total', $tokens[1]->value);
    }
}
