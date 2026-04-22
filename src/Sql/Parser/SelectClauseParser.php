<?php

namespace FQL\Sql\Parser;

use FQL\Sql\Ast\Expression\ColumnReferenceNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\Expression\StarNode;
use FQL\Sql\Ast\Node\SelectFieldNode;
use FQL\Sql\Token\Token;
use FQL\Sql\Token\TokenStream;
use FQL\Sql\Token\TokenType;

/**
 * Parses the field list following `SELECT`.
 *
 * Supports:
 *  - leading `DISTINCT` modifier
 *  - `*`, column references, function calls, CAST, MATCH/AGAINST, CASE expressions
 *  - optional `AS <alias>` per field
 *  - `EXCLUDE <field>[, <field>]` — switches subsequent items into exclude mode
 *
 * Returns the parsed field list together with the distinct flag; the caller owns the
 * actual SelectStatementNode construction.
 */
final class SelectClauseParser
{
    public function __construct(private readonly ExpressionParser $expressionParser)
    {
    }

    /**
     * @return array{distinct: bool, fields: SelectFieldNode[]}
     * @throws ParseException
     */
    public function parseClause(TokenStream $stream, Token $selectKeyword): array
    {
        unset($selectKeyword); // position info unused directly; kept for API symmetry
        $distinct = false;
        /** @var SelectFieldNode[] $fields */
        $fields = [];
        $mode = 'include';
        $expectComma = false;

        while (!$stream->isAtEnd()) {
            $peek = $stream->peek();

            if (ClauseBoundary::isControlKeyword($peek)) {
                break;
            }

            // EXCLUDE can appear mid-list; it flips mode for subsequent fields and does
            // not require a preceding comma.
            if ($peek->type === TokenType::KEYWORD_EXCLUDE) {
                $stream->consume();
                $mode = 'exclude';
                $expectComma = false;
                continue;
            }

            if ($expectComma) {
                if ($peek->type === TokenType::COMMA) {
                    $stream->consume();
                    $expectComma = false;
                    continue;
                }
                throw ParseException::context($peek, 'SELECT clause (Expected comma between SELECT expressions)');
            }

            if ($peek->type === TokenType::KEYWORD_DISTINCT && $fields === []) {
                $stream->consume();
                $distinct = true;
                continue;
            }

            $startPosition = $peek->position;
            $expression = $this->parseField($stream);
            $alias = null;
            if ($stream->consumeIf(TokenType::KEYWORD_AS) !== null) {
                $aliasToken = $stream->expect(TokenType::IDENTIFIER, TokenType::IDENTIFIER_QUOTED);
                $alias = IdentifierHelper::stripOuterBackticks($aliasToken->value);
            }

            $fields[] = new SelectFieldNode(
                $expression,
                $alias,
                excluded: $mode === 'exclude',
                position: $startPosition
            );
            $expectComma = true;
        }

        return ['distinct' => $distinct, 'fields' => $fields];
    }

    /**
     * @throws ParseException
     */
    private function parseField(TokenStream $stream): ExpressionNode
    {
        $peek = $stream->peek();

        // `table.*` — captured by the tokenizer as IDENTIFIER containing a '.*' suffix.
        if ($peek->type === TokenType::IDENTIFIER && str_ends_with($peek->value, '.*')) {
            $stream->consume();
            return new ColumnReferenceNode($peek->value, $peek->position, quoted: false);
        }

        // bare `*`
        if ($peek->type === TokenType::STAR) {
            $stream->consume();
            return new StarNode($peek->position);
        }

        return $this->expressionParser->parseExpression($stream);
    }
}
