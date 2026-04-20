<?php

namespace FQL\Sql\Parser;

use FQL\Enum;
use FQL\Exception;
use FQL\Sql\Ast\Expression\CaseExpressionNode;
use FQL\Sql\Ast\Expression\CastExpressionNode;
use FQL\Sql\Ast\Expression\ColumnReferenceNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\Expression\FunctionCallNode;
use FQL\Sql\Ast\Expression\LiteralNode;
use FQL\Sql\Ast\Expression\MatchAgainstNode;
use FQL\Sql\Ast\Expression\StarNode;
use FQL\Sql\Ast\Expression\WhenBranchNode;
use FQL\Sql\Token\Token;
use FQL\Sql\Token\TokenStream;
use FQL\Sql\Token\TokenType;

/**
 * Parses FQL expressions: literals, column references, function calls, CASE expressions,
 * CAST expressions, and MATCH/AGAINST fulltext expressions.
 *
 * Mutually recursive with ConditionGroupParser (for CASE WHEN branches); the parser is
 * passed in lazily at construction time.
 */
final class ExpressionParser
{
    private ConditionGroupParser $conditionGroupParser;

    public function setConditionGroupParser(ConditionGroupParser $parser): void
    {
        $this->conditionGroupParser = $parser;
    }

    /**
     * @throws ParseException
     */
    public function parseExpression(TokenStream $stream): ExpressionNode
    {
        return $this->parsePrimary($stream);
    }

    /**
     * @throws ParseException
     */
    public function parsePrimary(TokenStream $stream): ExpressionNode
    {
        $token = $stream->peek();

        if ($token->type === TokenType::STAR) {
            $stream->consume();
            return new StarNode($token->position);
        }

        if ($token->type === TokenType::NUMBER_LITERAL) {
            $stream->consume();
            $value = Enum\Type::matchByString($token->value);
            $type = is_float($value) ? Enum\Type::FLOAT : Enum\Type::INTEGER;
            return new LiteralNode($value, $type, $token->raw, $token->position);
        }

        if ($token->type === TokenType::STRING_LITERAL) {
            $stream->consume();
            return new LiteralNode($token->value, Enum\Type::STRING, $token->raw, $token->position);
        }

        if ($token->type === TokenType::BOOLEAN_LITERAL) {
            $stream->consume();
            $value = strtolower($token->value) === 'true';
            return new LiteralNode($value, Enum\Type::BOOLEAN, $token->raw, $token->position);
        }

        if ($token->type === TokenType::NULL_LITERAL) {
            $stream->consume();
            return new LiteralNode(null, Enum\Type::NULL, $token->raw, $token->position);
        }

        if ($token->type === TokenType::KEYWORD_CASE) {
            return $this->parseCase($stream);
        }

        if ($token->type === TokenType::FUNCTION_NAME) {
            return $this->parseFunctionCall($stream);
        }

        if ($token->type === TokenType::IDENTIFIER) {
            $stream->consume();
            return new ColumnReferenceNode($token->value, $token->position, quoted: false);
        }

        if ($token->type === TokenType::IDENTIFIER_QUOTED) {
            $stream->consume();
            return new ColumnReferenceNode($token->value, $token->position, quoted: true);
        }

        // Some keywords are context-sensitive and may legitimately appear as field
        // names outside of the clause that introduces them (e.g. `desc`, `left`,
        // `right`). When encountered in expression context we promote them to a
        // plain column reference using the raw lexeme (which preserves original case).
        if ($this->isSoftKeyword($token->type)) {
            $stream->consume();
            return new ColumnReferenceNode($token->raw, $token->position, quoted: false);
        }

        throw ParseException::context($token, 'expression');
    }

    private function isSoftKeyword(TokenType $type): bool
    {
        return match ($type) {
            TokenType::KEYWORD_ASC,
            TokenType::KEYWORD_DESC,
            TokenType::KEYWORD_ALL,
            TokenType::KEYWORD_OUTER,
            TokenType::KEYWORD_ANALYZE,
            TokenType::KEYWORD_INNER,
            TokenType::KEYWORD_LEFT,
            TokenType::KEYWORD_RIGHT,
            TokenType::KEYWORD_FULL => true,
            default => false,
        };
    }

    /**
     * @throws ParseException
     */
    public function parseFunctionCall(TokenStream $stream): ExpressionNode
    {
        $nameToken = $stream->expect(TokenType::FUNCTION_NAME);
        $name = strtoupper($nameToken->value);

        // Special case: CAST(expr AS type) uses the AS keyword internally.
        if ($name === 'CAST') {
            return $this->parseCastCall($stream, $nameToken);
        }

        // Special case: MATCH(fields) AGAINST('query IN NATURAL MODE')
        if ($name === 'MATCH') {
            return $this->parseMatchAgainst($stream, $nameToken);
        }

        // Special case: IF(condition, then, else) — first argument is a condition.
        if ($name === 'IF') {
            return $this->parseIfCall($stream, $nameToken);
        }

        $stream->expect(TokenType::PAREN_OPEN);
        $distinct = false;
        if ($stream->consumeIf(TokenType::KEYWORD_DISTINCT) !== null) {
            $distinct = true;
        }

        $arguments = [];
        if ($stream->peekType() !== TokenType::PAREN_CLOSE) {
            $arguments[] = $this->parsePrimary($stream);
            while ($stream->consumeIf(TokenType::COMMA) !== null) {
                $arguments[] = $this->parsePrimary($stream);
            }
        }
        $stream->expect(TokenType::PAREN_CLOSE);

        return new FunctionCallNode($name, $arguments, $distinct, $nameToken->position);
    }

    /**
     * @throws ParseException
     */
    private function parseIfCall(TokenStream $stream, Token $nameToken): FunctionCallNode
    {
        $stream->expect(TokenType::PAREN_OPEN);
        $condition = $this->conditionParser()->parse($stream);
        $stream->expect(TokenType::COMMA);
        $then = $this->parsePrimary($stream);
        $stream->expect(TokenType::COMMA);
        $else = $this->parsePrimary($stream);
        $stream->expect(TokenType::PAREN_CLOSE);

        return new FunctionCallNode('IF', [$condition, $then, $else], false, $nameToken->position);
    }

    private function conditionParser(): ConditionParser
    {
        // Reusing the parser wired for CASE branches. It was attached via
        // setConditionGroupParser(); pull the ConditionParser out of that graph lazily.
        return $this->conditionGroupParser->conditionParser();
    }

    /**
     * @throws ParseException
     */
    private function parseCastCall(TokenStream $stream, Token $nameToken): CastExpressionNode
    {
        $stream->expect(TokenType::PAREN_OPEN);
        $value = $this->parsePrimary($stream);
        $stream->expect(TokenType::KEYWORD_AS);
        $typeToken = $stream->peek();
        // Type name is normally an IDENTIFIER (INT, VARCHAR, STRING, ...) but `NULL`,
        // `TRUE`, and `FALSE` are tokenized as literals; accept them here as type aliases.
        $acceptableTypeTokens = [
            TokenType::IDENTIFIER,
            TokenType::IDENTIFIER_QUOTED,
            TokenType::NULL_LITERAL,
            TokenType::BOOLEAN_LITERAL,
        ];
        if (!$typeToken->isAnyOf(...$acceptableTypeTokens)) {
            throw ParseException::unexpected($typeToken, ...$acceptableTypeTokens);
        }
        $stream->consume();
        $stream->expect(TokenType::PAREN_CLOSE);

        return new CastExpressionNode(
            $value,
            $this->resolveCastType($typeToken->value),
            $nameToken->position
        );
    }

    /**
     * @throws ParseException
     */
    private function parseMatchAgainst(TokenStream $stream, Token $matchToken): MatchAgainstNode
    {
        $stream->expect(TokenType::PAREN_OPEN);
        $fields = [];
        $first = $this->parsePrimary($stream);
        if (!$first instanceof ColumnReferenceNode) {
            throw ParseException::context($matchToken, 'MATCH() argument list (expected column reference)');
        }
        $fields[] = $first;
        while ($stream->consumeIf(TokenType::COMMA) !== null) {
            $next = $this->parsePrimary($stream);
            if (!$next instanceof ColumnReferenceNode) {
                throw ParseException::context($matchToken, 'MATCH() argument list (expected column reference)');
            }
            $fields[] = $next;
        }
        $stream->expect(TokenType::PAREN_CLOSE);

        $againstToken = $stream->peek();
        if ($againstToken->type !== TokenType::FUNCTION_NAME || strtoupper($againstToken->value) !== 'AGAINST') {
            throw ParseException::context($againstToken, 'MATCH expression (expected AGAINST)');
        }
        $stream->consume();

        $stream->expect(TokenType::PAREN_OPEN);
        $queryToken = $stream->expect(TokenType::STRING_LITERAL);

        // Two supported syntaxes for the mode specifier:
        //  1. AGAINST("term IN NATURAL MODE")   — mode baked into the string literal
        //  2. AGAINST("term" IN NATURAL MODE)   — mode tokens follow the literal
        $searchQuery = $queryToken->value;
        $mode = null;

        if ($stream->peekType() === TokenType::KEYWORD_IN) {
            $stream->consume(); // IN
            $modeToken = $stream->expect(TokenType::IDENTIFIER);
            $trailing = $stream->expect(TokenType::IDENTIFIER);
            if (strtoupper($trailing->value) !== 'MODE') {
                throw ParseException::context($trailing, 'AGAINST payload (expected MODE keyword)');
            }
            try {
                $mode = Enum\Fulltext::from(strtoupper($modeToken->value));
            } catch (\ValueError) {
                throw ParseException::context($modeToken, 'AGAINST payload (unknown fulltext mode)');
            }
        } else {
            [$searchQuery, $mode] = $this->parseAgainstPayload($queryToken);
        }

        $stream->expect(TokenType::PAREN_CLOSE);

        if (trim($searchQuery) === '') {
            throw ParseException::context($queryToken, 'AGAINST payload (empty search query)');
        }

        return new MatchAgainstNode($fields, trim($searchQuery), $mode, $matchToken->position);
    }

    /**
     * Legacy single-literal `"query IN MODE MODE"` form used by the old tokenizer.
     *
     * @return array{0: string, 1: Enum\Fulltext}
     * @throws ParseException
     */
    private function parseAgainstPayload(Token $queryToken): array
    {
        $pattern = '/^(.*?)\s+IN\s+(NATURAL|BOOLEAN)\s+MODE$/i';
        if (!preg_match($pattern, trim($queryToken->value), $matches)) {
            throw ParseException::context($queryToken, 'AGAINST payload (expected "query IN NATURAL|BOOLEAN MODE")');
        }
        try {
            $mode = Enum\Fulltext::from(strtoupper($matches[2]));
        } catch (\ValueError) {
            throw ParseException::context($queryToken, 'AGAINST payload (unknown fulltext mode)');
        }
        return [trim($matches[1]), $mode];
    }

    /**
     * @throws ParseException
     */
    public function parseCase(TokenStream $stream): CaseExpressionNode
    {
        $caseToken = $stream->expect(TokenType::KEYWORD_CASE);
        $branches = [];
        $else = null;

        while ($stream->peekType() === TokenType::KEYWORD_WHEN) {
            $whenToken = $stream->expect(TokenType::KEYWORD_WHEN);
            $condition = $this->conditionGroupParser->parseGroup(
                $stream,
                static fn (Token $t): bool => $t->type === TokenType::KEYWORD_THEN
            );
            $stream->expect(TokenType::KEYWORD_THEN);
            $then = $this->parsePrimary($stream);
            $branches[] = new WhenBranchNode($condition, $then, $whenToken->position);
        }

        if ($stream->consumeIf(TokenType::KEYWORD_ELSE) !== null) {
            $else = $this->parsePrimary($stream);
        }

        $stream->expect(TokenType::KEYWORD_END);

        return new CaseExpressionNode($branches, $else, $caseToken->position);
    }

    /**
     * @throws ParseException
     */
    private function resolveCastType(string $typeString): Enum\Type
    {
        $normalized = strtoupper(trim($typeString));
        return match ($normalized) {
            'DOUBLE', 'FLOAT', 'REAL' => Enum\Type::FLOAT,
            'INT', 'INTEGER', 'SIGNED', 'UNSIGNED' => Enum\Type::INTEGER,
            'DECIMAL', 'NUMERIC', 'NUMBER' => Enum\Type::NUMBER,
            'BOOLEAN', 'BOOL', 'TRUE', 'FALSE' => Enum\Type::BOOLEAN,
            'CHAR', 'VARCHAR', 'STRING', 'TEXT' => Enum\Type::STRING,
            'NULL' => Enum\Type::NULL,
            default => $this->resolveCastTypeEnum($typeString),
        };
    }

    /**
     * @throws ParseException
     */
    private function resolveCastTypeEnum(string $typeString): Enum\Type
    {
        try {
            return Enum\Type::from(strtolower($typeString));
        } catch (\ValueError) {
            throw new Exception\QueryLogicException(sprintf('Unsupported CAST type: %s', $typeString));
        }
    }
}
