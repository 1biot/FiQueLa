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
        $start = $stream->peek();
        $entries = $this->parseEntries($stream, $stopAt);
        return new ConditionGroupNode($entries, $start->position, $stream->spanFrom($start));
    }

    /**
     * @param callable(Token): bool|null $stopAt
     * @return array<int, array{logical: Enum\LogicalOperator, condition: ConditionExpressionNode|ConditionGroupNode}>
     * @throws ParseException
     */
    private function parseEntries(TokenStream $stream, ?callable $stopAt): array
    {
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
                $nestedEntries = $this->parseEntries($stream, null);
                $stream->expect(TokenType::PAREN_CLOSE);
                // A nested group's span includes its parentheses.
                $nested = new ConditionGroupNode($nestedEntries, $token->position, $stream->spanFrom($token));
                $entries[] = ['logical' => $logical, 'condition' => $nested];
                $logical = Enum\LogicalOperator::AND;
                continue;
            }

            // Single condition
            $condition = $this->conditionParser->parse($stream);
            $entries[] = ['logical' => $logical, 'condition' => $condition];
            $logical = Enum\LogicalOperator::AND;
        }

        return $entries;
    }
}
