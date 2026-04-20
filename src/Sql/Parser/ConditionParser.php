<?php

namespace FQL\Sql\Parser;

use FQL\Enum;
use FQL\Sql\Ast\Expression\ConditionExpressionNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\Expression\LiteralNode;
use FQL\Sql\Token\Token;
use FQL\Sql\Token\TokenStream;
use FQL\Sql\Token\TokenType;

/**
 * Parses a single FQL condition: `<left> <operator> <right>`.
 *
 * Supports:
 *  - comparison operators: =, ==, !=, !==, <, <=, >, >=
 *  - pattern matching: [NOT] LIKE / [NOT] REGEXP
 *  - set membership: [NOT] IN (list)
 *  - range: [NOT] BETWEEN x AND y
 *  - type introspection: IS [NOT] NULL/BOOLEAN/NUMBER/STRING/...
 */
final class ConditionParser
{
    public function __construct(private readonly ExpressionParser $expressionParser)
    {
    }

    /**
     * @throws ParseException
     */
    public function parse(TokenStream $stream): ConditionExpressionNode
    {
        $left = $this->expressionParser->parsePrimary($stream);
        $operator = $this->parseOperator($stream);
        $right = $this->parseRight($stream, $operator);

        return new ConditionExpressionNode($left, $operator, $right, $left->position());
    }

    /**
     * @throws ParseException
     */
    private function parseOperator(TokenStream $stream): Enum\Operator
    {
        $first = $stream->consume();

        if ($first->type->isOperator()) {
            return match ($first->type) {
                TokenType::OP_EQ => Enum\Operator::EQUAL,
                TokenType::OP_EQ_STRICT => Enum\Operator::EQUAL_STRICT,
                TokenType::OP_NEQ => Enum\Operator::NOT_EQUAL,
                TokenType::OP_NEQ_STRICT => Enum\Operator::NOT_EQUAL_STRICT,
                TokenType::OP_LT => Enum\Operator::LESS_THAN,
                TokenType::OP_LTE => Enum\Operator::LESS_THAN_OR_EQUAL,
                TokenType::OP_GT => Enum\Operator::GREATER_THAN,
                TokenType::OP_GTE => Enum\Operator::GREATER_THAN_OR_EQUAL,
                default => throw ParseException::context($first, 'condition operator'),
            };
        }

        if ($first->type === TokenType::KEYWORD_IS) {
            if ($stream->consumeIf(TokenType::KEYWORD_NOT) !== null) {
                return Enum\Operator::NOT_IS;
            }
            return Enum\Operator::IS;
        }

        if ($first->type === TokenType::KEYWORD_NOT) {
            $next = $stream->consume();
            return match ($next->type) {
                TokenType::KEYWORD_LIKE => Enum\Operator::NOT_LIKE,
                TokenType::KEYWORD_IN => Enum\Operator::NOT_IN,
                TokenType::KEYWORD_BETWEEN => Enum\Operator::NOT_BETWEEN,
                TokenType::KEYWORD_REGEXP => Enum\Operator::NOT_REGEXP,
                default => throw ParseException::unexpected(
                    $next,
                    TokenType::KEYWORD_LIKE,
                    TokenType::KEYWORD_IN,
                    TokenType::KEYWORD_BETWEEN,
                    TokenType::KEYWORD_REGEXP
                ),
            };
        }

        return match ($first->type) {
            TokenType::KEYWORD_LIKE => Enum\Operator::LIKE,
            TokenType::KEYWORD_IN => Enum\Operator::IN,
            TokenType::KEYWORD_BETWEEN => Enum\Operator::BETWEEN,
            TokenType::KEYWORD_REGEXP => Enum\Operator::REGEXP,
            default => throw ParseException::context($first, 'condition operator'),
        };
    }

    /**
     * @return ExpressionNode|Enum\Type|ExpressionNode[]
     * @throws ParseException
     */
    private function parseRight(TokenStream $stream, Enum\Operator $operator): ExpressionNode|Enum\Type|array
    {
        if ($operator === Enum\Operator::IS || $operator === Enum\Operator::NOT_IS) {
            return $this->parseIsType($stream);
        }
        if ($operator === Enum\Operator::IN || $operator === Enum\Operator::NOT_IN) {
            return $this->parseInList($stream);
        }
        if ($operator === Enum\Operator::BETWEEN || $operator === Enum\Operator::NOT_BETWEEN) {
            return $this->parseBetween($stream);
        }
        return $this->expressionParser->parsePrimary($stream);
    }

    /**
     * @throws ParseException
     */
    private function parseIsType(TokenStream $stream): Enum\Type
    {
        $token = $stream->consume();
        if ($token->type === TokenType::NULL_LITERAL) {
            return Enum\Type::NULL;
        }
        if ($token->type === TokenType::BOOLEAN_LITERAL) {
            return strtolower($token->value) === 'true' ? Enum\Type::TRUE : Enum\Type::FALSE;
        }
        $value = strtolower($token->value);
        try {
            return Enum\Type::from($value);
        } catch (\ValueError) {
            throw ParseException::context($token, 'IS condition (unknown type name)');
        }
    }

    /**
     * @return ExpressionNode[]
     * @throws ParseException
     */
    private function parseInList(TokenStream $stream): array
    {
        $stream->expect(TokenType::PAREN_OPEN);
        $values = [];
        if ($stream->peekType() !== TokenType::PAREN_CLOSE) {
            $values[] = $this->expressionParser->parsePrimary($stream);
            while ($stream->consumeIf(TokenType::COMMA) !== null) {
                $values[] = $this->expressionParser->parsePrimary($stream);
            }
        }
        $stream->expect(TokenType::PAREN_CLOSE);
        return $values;
    }

    /**
     * @return ExpressionNode[]
     * @throws ParseException
     */
    private function parseBetween(TokenStream $stream): array
    {
        $min = $this->expressionParser->parsePrimary($stream);
        $stream->expect(TokenType::KEYWORD_AND);
        $max = $this->expressionParser->parsePrimary($stream);
        return [$min, $max];
    }

    /**
     * Convenience: convert an ExpressionNode carrying a plain value into a raw PHP value.
     * Useful for handlers that need the literal form (e.g. IN with literal values).
     */
    public static function literalValueOf(ExpressionNode $node): mixed
    {
        if ($node instanceof LiteralNode) {
            return $node->value;
        }
        return null;
    }

    /**
     * Convenience: extract the string representation of an identifier-like right operand.
     * Returns null if the node is not a simple scalar-like expression.
     */
    public static function rawStringOf(Token $token): string
    {
        return $token->raw;
    }
}
