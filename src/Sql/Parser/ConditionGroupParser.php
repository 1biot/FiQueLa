<?php

namespace FQL\Sql\Parser;

use FQL\Enum;
use FQL\Sql\Ast\Expression\ConditionExpressionNode;
use FQL\Sql\Ast\Expression\ConditionGroupNode;
use FQL\Sql\Token\Token;
use FQL\Sql\Token\TokenStream;
use FQL\Sql\Token\TokenType;

/**
 * Parses a logical group of conditions joined by AND/OR/XOR, with optional
 * nesting via parentheses.
 *
 * A caller-supplied `stopAt` predicate determines when the group ends (e.g. for
 * CASE WHEN branches it stops at KEYWORD_THEN; for WHERE it stops at the next
 * control keyword or a closing paren).
 */
final class ConditionGroupParser
{
    public function __construct(private readonly ConditionParser $conditionParser)
    {
    }

    public function conditionParser(): ConditionParser
    {
        return $this->conditionParser;
    }

    /**
     * @param callable(Token): bool|null $stopAt  called with the current peek token at
     *                                            depth 0; returning true terminates parsing.
     * @throws ParseException
     */
    public function parseGroup(TokenStream $stream, ?callable $stopAt = null): ConditionGroupNode
    {
        $startPosition = $stream->peek()->position;
        return $this->parseGroupInner($stream, $stopAt, $startPosition);
    }

    /**
     * @param callable(Token): bool|null $stopAt
     * @throws ParseException
     */
    private function parseGroupInner(
        TokenStream $stream,
        ?callable $stopAt,
        \FQL\Sql\Token\Position $groupPosition
    ): ConditionGroupNode {
        /** @var array<int, array{logical: Enum\LogicalOperator, condition: ConditionExpressionNode|ConditionGroupNode}> $entries */
        $entries = [];
        $logical = Enum\LogicalOperator::AND;

        while (!$stream->isAtEnd()) {
            $token = $stream->peek();

            if ($stopAt !== null && $stopAt($token)) {
                break;
            }

            // End of nested group
            if ($token->type === TokenType::PAREN_CLOSE) {
                break;
            }

            // Logical operator connects entries
            if ($token->type === TokenType::KEYWORD_AND) {
                $stream->consume();
                $logical = Enum\LogicalOperator::AND;
                continue;
            }
            if ($token->type === TokenType::KEYWORD_OR) {
                $stream->consume();
                $logical = Enum\LogicalOperator::OR;
                continue;
            }
            if ($token->type === TokenType::KEYWORD_XOR) {
                $stream->consume();
                $logical = Enum\LogicalOperator::XOR;
                continue;
            }

            // Nested group
            if ($token->type === TokenType::PAREN_OPEN) {
                $stream->consume();
                $nested = $this->parseGroupInner($stream, null, $token->position);
                $stream->expect(TokenType::PAREN_CLOSE);
                $entries[] = ['logical' => $logical, 'condition' => $nested];
                $logical = Enum\LogicalOperator::AND;
                continue;
            }

            // Single condition
            $condition = $this->conditionParser->parse($stream);
            $entries[] = ['logical' => $logical, 'condition' => $condition];
            $logical = Enum\LogicalOperator::AND;
        }

        return new ConditionGroupNode($entries, $groupPosition);
    }
}
